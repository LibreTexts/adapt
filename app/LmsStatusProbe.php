<?php

namespace App;

use App\Overrides\imsglobal\lti\Firebase\JWT;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks whether each school's LMS is up, using only the LTI 1.3 credentials ADAPT already has.
 *
 * TOKEN CHECK (every registration)
 * Does exactly what grade passback does first: sign a JWT with ADAPT's LTI private key for that registration and
 * exchange it at the registration's token URL with the OAuth2 client-credentials grant. No user tokens and no LMS
 * admin tokens are involved, nothing is written to the LMS, and the access token that comes back is discarded.
 *
 *   - HTTP 200 with an access_token    => up
 *   - HTTP 4xx with an OAuth error body => auth_error  (the LMS answered but rejected ADAPT's key: developer key /
 *                                                      tool registration turned off or deleted, wrong client id...)
 *   - timeout, connection error, 5xx, or anything else => down
 *
 * Where the token URL lives differs by LMS, which changes what a passing check proves:
 *   - Canvas, Moodle: the token URL is on the school's own instance, so the token check covers the school.
 *   - Brightspace:    every school shares D2L's auth.brightspace.com, so we ALSO make a plain GET to the school's own
 *                     site ({iss}/d2l/login, redirects not followed so a school's SSO provider can't skew it). Any
 *                     response below 500 counts as "the school's Brightspace answered".
 *   - Blackboard:     ADAPT has one shared registration for every Blackboard school and the token URL is Anthology's
 *                     central developer portal. That single check covers "Blackboard's LTI service + ADAPT's key";
 *                     it can't see an individual school's Blackboard site (ADAPT doesn't store those URLs).
 *
 * A "down" result is retried once a few seconds later before it is recorded, so one dropped connection doesn't
 * email anybody.
 */
class LmsStatusProbe
{
    const CANVAS = 'canvas';
    const BRIGHTSPACE = 'brightspace';
    const BLACKBOARD = 'blackboard';
    const MOODLE = 'moodle';
    const OTHER = 'other';

    const LMS_LABELS = [
        self::CANVAS => 'Canvas',
        self::BRIGHTSPACE => 'Brightspace',
        self::BLACKBOARD => 'Blackboard',
        self::MOODLE => 'Moodle',
        self::OTHER => 'Other',
    ];

    /**
     * Requested only so the LMS has something to grant. ADAPT's registrations always include it because grade passback
     * needs it.
     */
    const SCOPE = 'https://purl.imsglobal.org/spec/lti-ags/scope/score';

    const USER_AGENT = 'ADAPT https://adapt.libretexts.org/ (adapt@libretexts.org)';

    /** @var int seconds */
    private $timeout;

    /** @var int how many registrations to probe at the same time */
    private $concurrency;

    /** @var array lti_key_id => private key contents (or false if unreadable) */
    private $privateKeys = [];

    public function __construct(int $timeout = 15, int $concurrency = 10)
    {
        $this->timeout = $timeout;
        $this->concurrency = $concurrency;
    }

    /**
     * Every active LTI registration, with its LMS, a display name and the URL of the school's own site (if known).
     *
     * @return Collection of stdClass {id, lms, name, school_count, base_url, site_check_url, auth_token_url, client_id, kid, lti_key_id}
     */
    public function registrations(): Collection
    {
        $schoolNames = DB::table('lti_schools')
            ->join('schools', 'schools.id', '=', 'lti_schools.school_id')
            ->select('lti_schools.lti_registration_id', 'schools.name')
            ->orderBy('schools.name')
            ->get()
            ->groupBy('lti_registration_id');

        return DB::table('lti_registrations')
            ->where('active', 1)
            ->orderBy('id')
            ->get()
            ->map(function ($registration) use ($schoolNames) {
                $lms = self::lmsType($registration->iss, $registration->auth_token_url);
                $names = isset($schoolNames[$registration->id])
                    ? $schoolNames[$registration->id]->pluck('name')->all()
                    : [];

                switch ($lms) {
                    case self::BLACKBOARD:
                        $baseUrl = null; // shared registration; individual schools' sites aren't stored
                        break;
                    case self::BRIGHTSPACE:
                        // iss is the school's own Brightspace; auth_server isn't reliable here (one row has a token URL in it)
                        $baseUrl = rtrim((string)$registration->iss, '/');
                        break;
                    default:
                        $baseUrl = rtrim((string)($registration->auth_server ?: $registration->iss), '/');
                }

                if ($lms === self::BLACKBOARD) {
                    $name = 'Blackboard (shared LTI service' . ($names ? ', ' . count($names) . ' ' . Str::plural('school', count($names)) : '') . ')';
                } else {
                    $name = $names
                        ? implode(', ', $names)
                        : ($registration->campus_id ?: (parse_url((string)$baseUrl, PHP_URL_HOST) ?: "Registration #$registration->id"));
                }

                return (object)[
                    'id' => $registration->id,
                    'lms' => $lms,
                    'name' => $name,
                    'school_count' => count($names),
                    'base_url' => $baseUrl ?: null,
                    'site_check_url' => ($lms === self::BRIGHTSPACE && $baseUrl) ? $baseUrl . '/d2l/login' : null,
                    'auth_token_url' => $registration->auth_token_url,
                    'client_id' => $registration->client_id,
                    'kid' => $registration->kid,
                    'lti_key_id' => $registration->lti_key_id,
                ];
            })
            ->values();
    }

    /**
     * Same signals the rest of ADAPT uses (LtiRegistration::isBrightSpace, LmsOutageController::lmsTypeFromIss),
     * plus the token URL as a fallback for schools whose Brightspace lives on their own domain.
     */
    public static function lmsType(?string $iss, ?string $authTokenUrl = null): string
    {
        $iss = (string)$iss;
        $authTokenUrl = (string)$authTokenUrl;
        if (Str::contains($iss, 'blackboard')) {
            return self::BLACKBOARD;
        }
        if (Str::contains($iss, 'moodle') || Str::endsWith($authTokenUrl, '/mod/lti/token.php')) {
            return self::MOODLE;
        }
        if (LtiRegistration::isBrightSpace($iss) || Str::contains($authTokenUrl, 'brightspace.com')) {
            return self::BRIGHTSPACE;
        }
        if (Str::contains($iss, 'instructure') || Str::contains($iss, 'canvas')) {
            return self::CANVAS;
        }
        return self::OTHER;
    }

    /**
     * @param Collection $registrations from registrations()
     * @param int $retryDelaySeconds wait before retrying "down" results once; 0 disables the retry
     * @return array lti_registration_id => ['status', 'http_code', 'response_ms', 'error']
     */
    public function probeAll(Collection $registrations, int $retryDelaySeconds = 5): array
    {
        $results = $this->probeBatch($registrations);

        $down = $registrations->filter(function ($registration) use ($results) {
            return $results[$registration->id]['status'] === LmsStatusCheck::DOWN;
        });
        if ($retryDelaySeconds > 0 && $down->isNotEmpty()) {
            Sleep::for($retryDelaySeconds)->seconds();
            foreach ($this->probeBatch($down) as $id => $retry) {
                if ($retry['status'] !== LmsStatusCheck::DOWN) {
                    $results[$id] = $retry;
                }
            }
        }
        return $results;
    }

    /**
     * @param Collection $registrations
     * @return array
     */
    private function probeBatch(Collection $registrations): array
    {
        $results = [];
        foreach ($registrations->chunk($this->concurrency) as $chunk) {
            $requests = [];
            foreach ($chunk as $registration) {
                if (!$registration->auth_token_url) {
                    $results[$registration->id] = $this->result(LmsStatusCheck::AUTH_ERROR, null, null,
                        'This registration has no token URL, so ADAPT cannot get tokens from it.');
                    continue;
                }
                try {
                    $requests[$registration->id] = $this->tokenRequestBody($registration);
                } catch (Throwable $e) {
                    // Nothing was sent: ADAPT couldn't even build the request (unreadable key, etc.).
                    $results[$registration->id] = $this->result(LmsStatusCheck::AUTH_ERROR, null, null,
                        'ADAPT could not build the token request: ' . $e->getMessage());
                }
            }
            if (!$requests) {
                continue;
            }

            $byId = $chunk->keyBy('id');
            $responses = Http::pool(function (Pool $pool) use ($requests, $byId) {
                foreach ($requests as $id => $body) {
                    $registration = $byId[$id];
                    $pool->as("token-$id")
                        ->asForm()
                        ->timeout($this->timeout)
                        ->connectTimeout(min(10, $this->timeout))
                        ->withHeaders(['User-Agent' => self::USER_AGENT])
                        ->post($registration->auth_token_url, $body);
                    if ($registration->site_check_url) {
                        $pool->as("site-$id")
                            ->withoutRedirecting()
                            ->timeout($this->timeout)
                            ->connectTimeout(min(10, $this->timeout))
                            ->withHeaders(['User-Agent' => self::USER_AGENT])
                            ->get($registration->site_check_url);
                    }
                }
            });

            foreach ($requests as $id => $body) {
                $registration = $byId[$id];
                $token = $this->classifyToken($responses["token-$id"] ?? null, $registration->lms);
                if ($registration->site_check_url) {
                    $site = $this->classifySite($responses["site-$id"] ?? null, $registration->base_url);
                    // The school's own site being down outranks everything; a shared token server being down is
                    // reported as-is (it's down for this school too).
                    if ($site !== null) {
                        $token = $site;
                    }
                }
                $results[$id] = $token;
            }
        }
        return $results;
    }

    /**
     * The same client-credentials request LTI_Service_Connector::get_access_token() makes for grade passback
     * (every LMS ADAPT supports uses the token URL as the JWT audience).
     *
     * @param object $registration
     * @return array
     * @throws \Exception
     */
    private function tokenRequestBody($registration): array
    {
        $privateKey = $this->privateKey($registration->lti_key_id);
        $now = time();
        $jwt = JWT::encode([
            'iss' => $registration->client_id,
            'sub' => $registration->client_id,
            'aud' => $registration->auth_token_url,
            'iat' => $now - 5,
            'exp' => $now + 60,
            'jti' => 'lti-service-token' . hash('sha256', random_bytes(64)),
        ], $privateKey, 'RS256', $registration->kid);

        return [
            'grant_type' => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $jwt,
            'scope' => self::SCOPE,
        ];
    }

    /**
     * Mirrors LTIDatabase::private_key().
     *
     * @param $ltiKeyId
     * @return string
     * @throws \Exception
     */
    private function privateKey($ltiKeyId): string
    {
        if (!array_key_exists($ltiKeyId, $this->privateKeys)) {
            $file = app()->environment('local')
                ? storage_path('app/lti/private.key')
                : optional(LtiKey::find($ltiKeyId))->private_key_file;
            $this->privateKeys[$ltiKeyId] = ($file && is_readable($file)) ? file_get_contents($file) : false;
        }
        if (!$this->privateKeys[$ltiKeyId]) {
            throw new \Exception("the LTI private key for lti_key_id $ltiKeyId is missing or unreadable.");
        }
        return $this->privateKeys[$ltiKeyId];
    }

    /**
     * @param Response|Throwable|null $response
     * @param string $lms
     * @return array
     */
    private function classifyToken($response, string $lms): array
    {
        $shared = in_array($lms, [self::BRIGHTSPACE, self::BLACKBOARD]);
        $server = $shared ? (self::LMS_LABELS[$lms] . "'s shared token server") : 'the token server';

        if (!$response instanceof Response) {
            $message = $response instanceof Throwable ? $response->getMessage() : 'No response.';
            return $this->result(LmsStatusCheck::DOWN, null, null, $this->shorten("Could not connect to $server: $message"));
        }

        $code = $response->status();
        $ms = $this->elapsedMs($response);
        $json = $response->json();

        if ($code === 200 && is_array($json) && !empty($json['access_token'])) {
            return $this->result(LmsStatusCheck::UP, $code, $ms, null);
        }
        if ($code >= 400 && $code < 500 && $code !== 429 && is_array($json) && isset($json['error'])) {
            $error = is_string($json['error']) ? $json['error'] : json_encode($json['error']);
            if (!empty($json['error_description'])) {
                $error .= ': ' . $json['error_description'];
            }
            return $this->result(LmsStatusCheck::AUTH_ERROR, $code, $ms, $this->shorten($error));
        }
        $reason = $code === 429 ? 'rate limited' : ($code >= 500 ? 'server error' : 'unexpected response');
        return $this->result(LmsStatusCheck::DOWN, $code, $ms, "HTTP $code from $server ($reason)");
    }

    /**
     * @param Response|Throwable|null $response
     * @param string|null $baseUrl
     * @return array|null null when the school's site answered
     */
    private function classifySite($response, ?string $baseUrl): ?array
    {
        $host = parse_url((string)$baseUrl, PHP_URL_HOST) ?: $baseUrl;
        if (!$response instanceof Response) {
            $message = $response instanceof Throwable ? $response->getMessage() : 'No response.';
            return $this->result(LmsStatusCheck::DOWN, null, null, $this->shorten("The school's site ($host) did not respond: $message"));
        }
        if ($response->status() >= 500) {
            return $this->result(LmsStatusCheck::DOWN, $response->status(), $this->elapsedMs($response),
                "The school's site ($host) returned HTTP {$response->status()}");
        }
        return null;
    }

    private function elapsedMs(Response $response): ?int
    {
        $stats = $response->handlerStats();
        return isset($stats['total_time']) ? (int)round($stats['total_time'] * 1000) : null;
    }

    private function result(string $status, ?int $httpCode, ?int $ms, ?string $error): array
    {
        return ['status' => $status, 'http_code' => $httpCode, 'response_ms' => $ms, 'error' => $error];
    }

    private function shorten(string $message): string
    {
        return Str::limit($message, 490);
    }
}
