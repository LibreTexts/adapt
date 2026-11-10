<?php

namespace Tests\Feature\LmsStatus;

use App\LtiKey;
use App\LtiRegistration;
use App\School;
use Illuminate\Support\Facades\DB;

/**
 * Shared setup for the LMS status tests: a real RSA key on disk (so the probe can sign JWTs exactly as it does in
 * production) and helpers that create registrations shaped like the real Canvas / Brightspace / Blackboard / Moodle rows.
 */
trait BuildsLmsRegistrations
{
    /** @var string PEM */
    protected $publicKey;

    /** @var string path to the private key file the LtiKey row points at */
    protected $privateKeyFile;

    /** @var LtiKey */
    protected $ltiKey;

    protected function setUpLtiKey(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];
        $this->privateKeyFile = tempnam(sys_get_temp_dir(), 'lms-status-test-key-');
        file_put_contents($this->privateKeyFile, $privatePem);
        $this->ltiKey = factory(LtiKey::class)->create(['private_key_file' => $this->privateKeyFile]);
    }

    protected function tearDownLtiKey(): void
    {
        if ($this->privateKeyFile && file_exists($this->privateKeyFile)) {
            unlink($this->privateKeyFile);
        }
    }

    protected function canvasRegistration(string $host, array $overrides = []): LtiRegistration
    {
        return $this->registration(array_merge([
            'iss' => 'https://canvas.instructure.com',
            'auth_server' => "https://$host",
            'auth_token_url' => "https://$host/login/oauth2/token",
        ], $overrides));
    }

    protected function brightspaceRegistration(string $host, array $overrides = []): LtiRegistration
    {
        return $this->registration(array_merge([
            'iss' => "https://$host",
            'auth_server' => "https://$host",
            'auth_token_url' => 'https://auth.brightspace.com/core/connect/token',
            'kid' => 'ADAPT',
        ], $overrides));
    }

    protected function blackboardRegistration(array $overrides = []): LtiRegistration
    {
        return $this->registration(array_merge([
            'iss' => 'https://blackboard.com',
            'auth_server' => '', // the real row's auth_server is empty
            'auth_token_url' => 'https://developer.blackboard.com/api/v1/gateway/oauth2/jwttoken',
        ], $overrides));
    }

    protected function moodleRegistration(string $host, array $overrides = []): LtiRegistration
    {
        return $this->registration(array_merge([
            'iss' => "https://$host",
            'auth_server' => "https://$host",
            'auth_token_url' => "https://$host/mod/lti/token.php",
        ], $overrides));
    }

    protected function registration(array $attributes): LtiRegistration
    {
        return factory(LtiRegistration::class)->create(array_merge(['lti_key_id' => $this->ltiKey->id], $attributes));
    }

    protected function attachSchool(LtiRegistration $registration, string $name): void
    {
        // School names are unique and the test database is seeded with real schools, so reuse one if it exists.
        $school = School::where('name', $name)->first() ?: factory(School::class)->create(['name' => $name]);
        DB::table('lti_schools')->insert([
            'lti_registration_id' => $registration->id,
            'school_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Decodes and signature-checks the client_assertion JWT from a token request.
     *
     * @param string $jwt
     * @return array [header, claims]
     */
    protected function verifiedJwt(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $decode = function ($part) {
            return base64_decode(strtr($part, '-_', '+/') . str_repeat('=', (4 - strlen($part) % 4) % 4));
        };
        $this->assertSame(1, openssl_verify("$header.$payload", $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256),
            'The client_assertion JWT was not signed with the registration\'s LTI private key.');
        return [json_decode($decode($header), true), json_decode($decode($payload), true)];
    }

    protected function tokenOk(): array
    {
        return ['access_token' => 'issued-token', 'token_type' => 'Bearer', 'expires_in' => 3600];
    }

    protected function tokenRejected(): array
    {
        return ['error' => 'invalid_client', 'error_description' => 'Client authentication failed'];
    }
}
