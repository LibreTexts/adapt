<?php

namespace Tests\Feature\LmsStatus;

use App\LmsStatusCheck;
use App\LmsStatusProbe;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LmsStatusProbeTest extends TestCase
{
    use BuildsLmsRegistrations;

    /** @var LmsStatusProbe */
    private $probe;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpLtiKey();
        Http::preventStrayRequests();
        Sleep::fake();
        $this->probe = new LmsStatusProbe(5, 10);
    }

    public function tearDown(): void
    {
        $this->tearDownLtiKey();
        parent::tearDown();
    }

    private function probeOne($registration, int $retryDelay = 0): array
    {
        $registrations = $this->probe->registrations()->where('id', $registration->id)->values();
        $this->assertCount(1, $registrations, 'The registration was not picked up by registrations().');
        return $this->probe->probeAll($registrations, $retryDelay)[$registration->id];
    }

    #[Test]
    public function it_classifies_the_lms_for_each_kind_of_registration_adapt_has()
    {
        $this->assertSame('canvas', LmsStatusProbe::lmsType('https://canvas.instructure.com', 'https://x.instructure.com/login/oauth2/token'));
        $this->assertSame('canvas', LmsStatusProbe::lmsType('https://canvas.libretexts.org'));
        $this->assertSame('blackboard', LmsStatusProbe::lmsType('https://blackboard.com', 'https://developer.blackboard.com/api/v1/gateway/oauth2/jwttoken'));
        $this->assertSame('moodle', LmsStatusProbe::lmsType('https://adapt.moodlecloud.com', 'https://adapt.moodlecloud.com/mod/lti/token.php'));
        $this->assertSame('brightspace', LmsStatusProbe::lmsType('https://coker.brightspace.com', 'https://auth.brightspace.com/core/connect/token'));
        // Brightspace schools on their own domains
        $this->assertSame('brightspace', LmsStatusProbe::lmsType('https://online.pcc.edu', 'https://auth.brightspace.com/core/connect/token'));
        $this->assertSame('brightspace', LmsStatusProbe::lmsType('https://d2l.hartwick.edu', 'https://auth.brightspace.com/core/connect/token'));
        $this->assertSame('other', LmsStatusProbe::lmsType('https://example.edu', 'https://example.edu/token'));
    }

    #[Test]
    public function registrations_only_includes_active_ones_and_names_them_from_their_schools()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->attachSchool($canvas, 'Foothill College');
        $this->canvasRegistration('inactive.instructure.com', ['active' => 0]);

        $registrations = $this->probe->registrations();

        $this->assertSame([$canvas->id], $registrations->pluck('id')->all());
        $this->assertSame('Foothill College', $registrations->first()->name);
        $this->assertSame('canvas', $registrations->first()->lms);
        $this->assertSame('https://foothill.instructure.com', $registrations->first()->base_url);
        $this->assertNull($registrations->first()->site_check_url);
    }

    #[Test]
    public function brightspace_uses_iss_as_the_school_site_even_when_auth_server_holds_a_token_url()
    {
        // Mirrors the real online.pcc.edu row
        $pcc = $this->brightspaceRegistration('online.pcc.edu', ['auth_server' => 'https://api.brightspace.com/auth/token']);

        $registration = $this->probe->registrations()->firstWhere('id', $pcc->id);

        $this->assertSame('brightspace', $registration->lms);
        $this->assertSame('https://online.pcc.edu', $registration->base_url);
        $this->assertSame('https://online.pcc.edu/d2l/login', $registration->site_check_url);
    }

    #[Test]
    public function blackboard_is_one_shared_card_labeled_with_its_school_count()
    {
        $blackboard = $this->blackboardRegistration();
        $this->attachSchool($blackboard, 'School A');
        $this->attachSchool($blackboard, 'School B');

        $registration = $this->probe->registrations()->firstWhere('id', $blackboard->id);

        $this->assertSame('blackboard', $registration->lms);
        $this->assertSame('Blackboard (shared LTI service, 2 schools)', $registration->name);
        $this->assertNull($registration->base_url);
        $this->assertNull($registration->site_check_url);
    }

    #[Test]
    public function it_sends_the_same_signed_client_credentials_request_as_grade_passback()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com', ['client_id' => '10000000000123', 'kid' => 'key-1']);
        Http::fake(['foothill.instructure.com/*' => Http::response($this->tokenOk())]);

        $this->probeOne($canvas);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://foothill.instructure.com/login/oauth2/token' || $request->method() !== 'POST') {
                return false;
            }
            $this->assertSame('client_credentials', $request['grant_type']);
            $this->assertSame('urn:ietf:params:oauth:client-assertion-type:jwt-bearer', $request['client_assertion_type']);
            $this->assertSame(LmsStatusProbe::SCOPE, $request['scope']);
            [$header, $claims] = $this->verifiedJwt($request['client_assertion']);
            $this->assertSame('RS256', $header['alg']);
            $this->assertSame('key-1', $header['kid']);
            $this->assertSame('10000000000123', $claims['iss']);
            $this->assertSame('10000000000123', $claims['sub']);
            $this->assertSame('https://foothill.instructure.com/login/oauth2/token', $claims['aud']);
            $this->assertLessThanOrEqual(65, $claims['exp'] - $claims['iat']);
            return true;
        });
    }

    #[Test]
    public function a_granted_token_is_up()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response($this->tokenOk())]);

        $result = $this->probeOne($canvas);

        $this->assertSame(LmsStatusCheck::UP, $result['status']);
        $this->assertSame(200, $result['http_code']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function an_oauth_error_is_an_auth_error_with_the_lms_message()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response($this->tokenRejected(), 401)]);

        $result = $this->probeOne($canvas);

        $this->assertSame(LmsStatusCheck::AUTH_ERROR, $result['status']);
        $this->assertSame(401, $result['http_code']);
        $this->assertSame('invalid_client: Client authentication failed', $result['error']);
    }

    #[Test]
    public function a_server_error_is_down()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response('<html>Service Unavailable</html>', 503)]);

        $result = $this->probeOne($canvas);

        $this->assertSame(LmsStatusCheck::DOWN, $result['status']);
        $this->assertSame(503, $result['http_code']);
        $this->assertStringContainsString('HTTP 503', $result['error']);
    }

    #[Test]
    public function a_4xx_without_an_oauth_error_body_is_down_not_an_auth_error()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response('<html>Not Found</html>', 404)]);

        $this->assertSame(LmsStatusCheck::DOWN, $this->probeOne($canvas)['status']);
    }

    #[Test]
    public function rate_limiting_is_down_not_an_auth_error()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response(['error' => 'rate_limited'], 429)]);

        $this->assertSame(LmsStatusCheck::DOWN, $this->probeOne($canvas)['status']);
    }

    #[Test]
    public function a_connection_failure_is_down()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::failedConnection('Connection timed out')]);

        $result = $this->probeOne($canvas);

        $this->assertSame(LmsStatusCheck::DOWN, $result['status']);
        $this->assertNull($result['http_code']);
        $this->assertStringContainsString('Could not connect', $result['error']);
    }

    #[Test]
    public function a_down_result_is_retried_once_and_the_retry_wins_if_it_succeeds()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::sequence()
            ->push('<html>Bad Gateway</html>', 502)
            ->push($this->tokenOk())]);

        $result = $this->probeOne($canvas, 5);

        $this->assertSame(LmsStatusCheck::UP, $result['status']);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    #[Test]
    public function auth_errors_are_not_retried()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        Http::fake(['foothill.instructure.com/*' => Http::response($this->tokenRejected(), 401)]);

        $this->probeOne($canvas, 5);

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    #[Test]
    public function an_unreadable_private_key_is_an_auth_error_and_nothing_is_sent()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        unlink($this->privateKeyFile);
        Http::fake();

        $result = $this->probeOne($canvas);

        $this->assertSame(LmsStatusCheck::AUTH_ERROR, $result['status']);
        $this->assertStringContainsString('could not build the token request', $result['error']);
        Http::assertNothingSent();
    }

    #[Test]
    public function brightspace_is_up_when_the_shared_token_server_and_the_school_site_both_answer()
    {
        $coker = $this->brightspaceRegistration('coker.brightspace.com');
        Http::fake([
            'auth.brightspace.com/*' => Http::response($this->tokenOk()),
            // Brightspace usually redirects /d2l/login to the school's SSO provider
            'coker.brightspace.com/*' => Http::response('', 302, ['Location' => 'https://sso.coker.edu/login']),
        ]);

        $this->assertSame(LmsStatusCheck::UP, $this->probeOne($coker)['status']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET' && $request->url() === 'https://coker.brightspace.com/d2l/login';
        });
        // Redirects aren't followed, so the school's SSO provider is never contacted
        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), 'sso.coker.edu');
        });
    }

    #[Test]
    public function brightspace_is_down_when_the_school_site_is_down_even_if_the_token_is_granted()
    {
        $coker = $this->brightspaceRegistration('coker.brightspace.com');
        Http::fake([
            'auth.brightspace.com/*' => Http::response($this->tokenOk()),
            'coker.brightspace.com/*' => Http::response('down', 503),
        ]);

        $result = $this->probeOne($coker);

        $this->assertSame(LmsStatusCheck::DOWN, $result['status']);
        $this->assertStringContainsString("school's site (coker.brightspace.com)", $result['error']);
    }

    #[Test]
    public function brightspace_names_the_shared_token_server_when_that_is_what_failed()
    {
        $coker = $this->brightspaceRegistration('coker.brightspace.com');
        Http::fake([
            'auth.brightspace.com/*' => Http::failedConnection(),
            'coker.brightspace.com/*' => Http::response('', 302),
        ]);

        $result = $this->probeOne($coker);

        $this->assertSame(LmsStatusCheck::DOWN, $result['status']);
        $this->assertStringContainsString("Brightspace's shared token server", $result['error']);
    }

    #[Test]
    public function brightspace_auth_errors_are_reported_when_the_school_site_is_up()
    {
        $coker = $this->brightspaceRegistration('coker.brightspace.com');
        Http::fake([
            'auth.brightspace.com/*' => Http::response($this->tokenRejected(), 400),
            'coker.brightspace.com/*' => Http::response('', 302),
        ]);

        $this->assertSame(LmsStatusCheck::AUTH_ERROR, $this->probeOne($coker)['status']);
    }

    #[Test]
    public function blackboard_and_moodle_only_send_the_token_request()
    {
        $blackboard = $this->blackboardRegistration();
        $moodle = $this->moodleRegistration('adapt.moodlecloud.com');
        Http::fake([
            'developer.blackboard.com/*' => Http::response($this->tokenOk()),
            'adapt.moodlecloud.com/*' => Http::response($this->tokenOk()),
        ]);

        $registrations = $this->probe->registrations();
        $results = $this->probe->probeAll($registrations, 0);

        $this->assertSame(LmsStatusCheck::UP, $results[$blackboard->id]['status']);
        $this->assertSame(LmsStatusCheck::UP, $results[$moodle->id]['status']);
        Http::assertSentCount(2);
    }

    #[Test]
    public function one_school_failing_does_not_affect_the_others()
    {
        $up = $this->canvasRegistration('up.instructure.com');
        $down = $this->canvasRegistration('down.instructure.com');
        $broken = $this->canvasRegistration('broken.instructure.com');
        Http::fake([
            'up.instructure.com/*' => Http::response($this->tokenOk()),
            'down.instructure.com/*' => Http::failedConnection(),
            'broken.instructure.com/*' => Http::response($this->tokenRejected(), 401),
        ]);

        $results = $this->probe->probeAll($this->probe->registrations(), 0);

        $this->assertSame(LmsStatusCheck::UP, $results[$up->id]['status']);
        $this->assertSame(LmsStatusCheck::DOWN, $results[$down->id]['status']);
        $this->assertSame(LmsStatusCheck::AUTH_ERROR, $results[$broken->id]['status']);
    }
}
