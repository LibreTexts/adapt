<?php

namespace Tests\Feature\LmsStatus;

use App\LmsStatusCheck;
use App\Mail\LmsStatusChanged;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckLmsStatusCommandTest extends TestCase
{
    use BuildsLmsRegistrations;

    /** @var User */
    private $alertUser;

    /** @var User */
    private $otherAlertUser;

    /** @var User */
    private $notAlerted;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpLtiKey();
        Http::preventStrayRequests();
        Sleep::fake();
        Mail::fake();

        $this->alertUser = factory(User::class)->create(['email' => 'alert-one@test.app']);
        $this->otherAlertUser = factory(User::class)->create(['email' => 'alert-two@test.app']);
        $this->notAlerted = factory(User::class)->create(['email' => 'not-alerted@test.app']);
        // Production uses users 1 and 5; tests point the setting at the users created here.
        config(['myconfig.lms_status_alert_user_ids' => "{$this->alertUser->id},{$this->otherAlertUser->id}"]);
    }

    public function tearDown(): void
    {
        $this->tearDownLtiKey();
        parent::tearDown();
    }

    private function fakeCanvas(string $host, $response): void
    {
        Http::fake([$host . '/*' => $response]);
    }

    private function record($registration, string $status, Carbon $checkedAt): void
    {
        LmsStatusCheck::insert([
            'lti_registration_id' => $registration->id,
            'checked_at' => $checkedAt,
            'status' => $status,
        ]);
    }

    #[Test]
    public function alerts_go_to_users_1_and_5_by_default()
    {
        // setUp() overrides the setting, so read the shipped config file directly.
        $this->assertSame('1,5', (include base_path('config/myconfig.php'))['lms_status_alert_user_ids']);
    }

    #[Test]
    public function the_first_run_records_every_registration_and_emails_nobody()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $brightspace = $this->brightspaceRegistration('coker.brightspace.com');
        Http::fake([
            'foothill.instructure.com/*' => Http::response($this->tokenRejected(), 401),
            'auth.brightspace.com/*' => Http::response($this->tokenOk()),
            'coker.brightspace.com/*' => Http::response('', 302),
        ]);

        $this->artisan('check:lmsStatus')->assertExitCode(0);

        $this->assertDatabaseHas('lms_status_checks', ['lti_registration_id' => $canvas->id, 'status' => 'auth_error', 'http_code' => 401]);
        $this->assertDatabaseHas('lms_status_checks', ['lti_registration_id' => $brightspace->id, 'status' => 'up']);
        $this->assertSame(1, LmsStatusCheck::distinct()->count('checked_at'), 'Every row from one run should share one checked_at.');
        Mail::assertNothingSent();
    }

    #[Test]
    public function a_status_change_emails_only_the_configured_users()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->attachSchool($canvas, 'Foothill College');
        $this->record($canvas, 'up', now()->subMinutes(10));
        $this->fakeCanvas('foothill.instructure.com', Http::response('<html>Service Unavailable</html>', 503));

        $this->artisan('check:lmsStatus', ['--no-retry' => true])->assertExitCode(0);

        Mail::assertSent(LmsStatusChanged::class, 2);
        foreach ([$this->alertUser, $this->otherAlertUser] as $user) {
            Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) use ($user) {
                return $mail->hasTo($user->email);
            });
        }
        Mail::assertNotSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) {
            return $mail->hasTo($this->notAlerted->email);
        });
        Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) {
            $change = $mail->changes[0];
            return !$mail->isTest
                && count($mail->changes) === 1
                && $change['name'] === 'Foothill College'
                && $change['lms'] === 'Canvas'
                && $change['from'] === 'up'
                && $change['status'] === 'down'
                && $mail->counts['down'] === 1;
        });
    }

    #[Test]
    public function a_recovery_is_also_a_change()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'down', now()->subMinutes(10));
        $this->fakeCanvas('foothill.instructure.com', Http::response($this->tokenOk()));

        $this->artisan('check:lmsStatus')->assertExitCode(0);

        Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) {
            return $mail->changes[0]['from'] === 'down' && $mail->changes[0]['status'] === 'up';
        });
    }

    #[Test]
    public function a_school_that_stays_broken_does_not_email_again()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'auth_error', now()->subMinutes(10));
        $this->fakeCanvas('foothill.instructure.com', Http::response($this->tokenRejected(), 401));

        $this->artisan('check:lmsStatus')->assertExitCode(0);

        Mail::assertNothingSent();
    }

    #[Test]
    public function every_change_in_one_run_goes_in_one_email()
    {
        $a = $this->canvasRegistration('a.instructure.com');
        $b = $this->canvasRegistration('b.instructure.com');
        $c = $this->canvasRegistration('c.instructure.com');
        foreach ([$a, $b, $c] as $registration) {
            $this->record($registration, 'up', now()->subMinutes(10));
        }
        Http::fake([
            'a.instructure.com/*' => Http::failedConnection(),
            'b.instructure.com/*' => Http::response($this->tokenRejected(), 401),
            'c.instructure.com/*' => Http::response($this->tokenOk()),
        ]);

        $this->artisan('check:lmsStatus', ['--no-retry' => true])->assertExitCode(0);

        Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) {
            return $mail->hasTo($this->alertUser->email) && count($mail->changes) === 2;
        });
        Mail::assertSent(LmsStatusChanged::class, 2); // one per recipient, not one per school
    }

    #[Test]
    public function no_email_records_but_never_emails()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'up', now()->subMinutes(10));
        $this->fakeCanvas('foothill.instructure.com', Http::failedConnection());

        $this->artisan('check:lmsStatus', ['--no-email' => true, '--no-retry' => true])->assertExitCode(0);

        $this->assertSame(2, LmsStatusCheck::where('lti_registration_id', $canvas->id)->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function test_email_sends_now_even_without_a_change_and_shows_current_problems()
    {
        $broken = $this->canvasRegistration('broken.instructure.com');
        $fine = $this->canvasRegistration('fine.instructure.com');
        $this->record($broken, 'auth_error', now()->subMinutes(10));
        $this->record($fine, 'up', now()->subMinutes(10));
        Http::fake([
            'broken.instructure.com/*' => Http::response($this->tokenRejected(), 401),
            'fine.instructure.com/*' => Http::response($this->tokenOk()),
        ]);

        $this->artisan('check:lmsStatus', ['--test-email' => true])
            ->expectsOutputToContain('Test email sent')
            ->assertExitCode(0);

        Mail::assertSent(LmsStatusChanged::class, 2);
        Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) use ($broken) {
            return $mail->isTest
                && count($mail->changes) === 1
                && $mail->changes[0]['status'] === 'auth_error'
                && $mail->counts['total'] === 2;
        });
    }

    #[Test]
    public function test_email_still_sends_when_everything_is_up()
    {
        $this->canvasRegistration('fine.instructure.com');
        $this->fakeCanvas('fine.instructure.com', Http::response($this->tokenOk()));

        $this->artisan('check:lmsStatus', ['--test-email' => true])->assertExitCode(0);

        Mail::assertSent(LmsStatusChanged::class, function (LmsStatusChanged $mail) {
            return $mail->isTest && $mail->changes === [];
        });
    }

    #[Test]
    public function it_warns_instead_of_failing_when_nobody_is_configured()
    {
        config(['myconfig.lms_status_alert_user_ids' => '999999']);
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'up', now()->subMinutes(10));
        $this->fakeCanvas('foothill.instructure.com', Http::failedConnection());

        $this->artisan('check:lmsStatus', ['--no-retry' => true])
            ->expectsOutputToContain('There is nobody to email')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    #[Test]
    public function it_prunes_checks_older_than_the_retention_window()
    {
        config(['myconfig.lms_status_retention_hours' => 72]);
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'up', now()->subHours(73));
        $this->record($canvas, 'up', now()->subHours(71));
        $this->fakeCanvas('foothill.instructure.com', Http::response($this->tokenOk()));

        $this->artisan('check:lmsStatus')->assertExitCode(0);

        $this->assertSame(2, LmsStatusCheck::count());
        $this->assertSame(0, LmsStatusCheck::where('checked_at', '<', now()->subHours(72))->count());
    }

    #[Test]
    public function inactive_registrations_are_not_checked()
    {
        $this->canvasRegistration('inactive.instructure.com', ['active' => 0]);
        Http::fake();

        $this->artisan('check:lmsStatus')
            ->expectsOutputToContain('No active LTI registrations to check.')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, LmsStatusCheck::count());
    }

    #[Test]
    public function the_email_renders()
    {
        $html = (new LmsStatusChanged([[
            'name' => 'Foothill College',
            'lms' => 'Canvas',
            'base_url' => 'https://foothill.instructure.com',
            'from' => 'up',
            'status' => 'down',
            'http_code' => 503,
            'response_ms' => 120,
            'error' => 'HTTP 503 from the token server (server error)',
        ]], [
            'total' => 3, 'up' => 2, 'down' => 1, 'auth_error' => 0,
            'by_lms' => ['Canvas' => ['total' => 3, 'up' => 2]],
        ], true))->render();

        $this->assertStringContainsString('[TEST] LMS Status Changed', $html);
        $this->assertStringContainsString('Foothill College', $html);
        $this->assertStringContainsString('HTTP 503 from the token server', $html);
        $this->assertStringContainsString('Canvas: 2 of 3 up', $html);
    }
}
