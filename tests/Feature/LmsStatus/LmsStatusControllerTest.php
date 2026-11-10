<?php

namespace Tests\Feature\LmsStatus;

use App\LmsStatusCheck;
use App\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LmsStatusControllerTest extends TestCase
{
    use BuildsLmsRegistrations;

    /** @var User */
    private $admin;

    /** @var User */
    private $instructor;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpLtiKey();
        // Helper::isAdmin() treats me@me.com as an admin in the testing environment.
        $this->admin = factory(User::class)->create(['email' => 'me@me.com']);
        $this->instructor = factory(User::class)->create(['role' => 2]);
    }

    public function tearDown(): void
    {
        $this->tearDownLtiKey();
        parent::tearDown();
    }

    private function record($registration, string $status, Carbon $checkedAt, array $extra = []): void
    {
        LmsStatusCheck::insert(array_merge([
            'lti_registration_id' => $registration->id,
            'checked_at' => $checkedAt,
            'status' => $status,
        ], $extra));
    }

    #[Test]
    public function non_admins_cannot_see_the_status()
    {
        $canvas = $this->canvasRegistration('foothill.instructure.com');
        $this->record($canvas, 'up', now()->subMinutes(5));

        $this->actingAs($this->instructor)->getJson('/api/lms-status')
            ->assertJson(['type' => 'error'])
            ->assertJsonMissingPath('schools');
        $this->actingAs($this->instructor)->getJson("/api/lms-status/{$canvas->id}/history")
            ->assertJson(['type' => 'error'])
            ->assertJsonMissingPath('history');
    }

    #[Test]
    public function guests_cannot_see_the_status()
    {
        $this->getJson('/api/lms-status')->assertStatus(401);
    }

    #[Test]
    public function it_returns_an_empty_state_before_any_checks()
    {
        $this->actingAs($this->admin)->getJson('/api/lms-status')
            ->assertJson([
                'type' => 'success',
                'timeline' => [],
                'schools' => [],
                'latest_checked_at' => null,
            ]);
    }

    #[Test]
    public function it_returns_the_latest_run_with_problems_first()
    {
        $foothill = $this->canvasRegistration('foothill.instructure.com');
        $this->attachSchool($foothill, 'Foothill College');
        $coker = $this->brightspaceRegistration('coker.brightspace.com');
        $this->attachSchool($coker, 'Coker University');

        $earlier = now()->subMinutes(20)->startOfMinute();
        $latest = now()->subMinutes(10)->startOfMinute();
        $this->record($foothill, 'up', $earlier, ['response_ms' => 100]);
        $this->record($coker, 'up', $earlier, ['response_ms' => 200]);
        $this->record($foothill, 'up', $latest, ['response_ms' => 300, 'http_code' => 200]);
        $this->record($coker, 'down', $latest, ['error' => "The school's site (coker.brightspace.com) returned HTTP 503", 'http_code' => 503]);

        $response = $this->actingAs($this->admin)->getJson('/api/lms-status')
            ->assertJson([
                'type' => 'success',
                'retention_hours' => 72,
                'summary' => ['total' => 2, 'up' => 1, 'down' => 1, 'auth_error' => 0, 'avg_response_ms' => 300],
            ])
            ->assertJsonCount(2, 'timeline')
            ->assertJsonCount(2, 'schools')
            ->json();

        $this->assertSame($response['latest_checked_at'], $response['viewing_checked_at']);
        $this->assertSame(['up' => 1, 'down' => 1, 'auth_error' => 0], array_intersect_key($response['timeline'][1], array_flip(['up', 'down', 'auth_error'])));

        [$first, $second] = $response['schools'];
        $this->assertSame('Coker University', $first['name']);
        $this->assertSame('brightspace', $first['lms']);
        $this->assertSame('down', $first['status']);
        $this->assertSame('https://coker.brightspace.com/d2l/login', $first['site_check_url']);
        $this->assertSame(50.0, (float)$first['uptime_pct']);

        $this->assertSame('Foothill College', $second['name']);
        $this->assertSame('canvas', $second['lms']);
        $this->assertSame(100.0, (float)$second['uptime_pct']);
        $this->assertSame([100, 300], array_column($second['sparkline'], 'ms'));
    }

    #[Test]
    public function at_returns_an_earlier_run()
    {
        $foothill = $this->canvasRegistration('foothill.instructure.com');
        $this->record($foothill, 'down', now()->subMinutes(20)->startOfMinute());
        $this->record($foothill, 'up', now()->subMinutes(10)->startOfMinute());

        $timeline = $this->actingAs($this->admin)->getJson('/api/lms-status')->json('timeline');
        $earlier = $timeline[0]['checked_at'];

        $response = $this->actingAs($this->admin)->getJson('/api/lms-status?at=' . urlencode($earlier))
            ->assertJson(['type' => 'success', 'viewing_checked_at' => $earlier, 'summary' => ['down' => 1, 'up' => 0]])
            ->json();
        $this->assertNotSame($response['latest_checked_at'], $response['viewing_checked_at']);
    }

    #[Test]
    public function at_with_no_matching_run_is_an_error()
    {
        $foothill = $this->canvasRegistration('foothill.instructure.com');
        $this->record($foothill, 'up', now()->subMinutes(10)->startOfMinute());

        $this->actingAs($this->admin)->getJson('/api/lms-status?at=' . urlencode(now()->subYear()->toIso8601String()))
            ->assertJson(['type' => 'error', 'message' => 'There is no check at that time.']);
    }

    #[Test]
    public function the_timeline_only_covers_the_retention_window()
    {
        config(['myconfig.lms_status_retention_hours' => 24]);
        $foothill = $this->canvasRegistration('foothill.instructure.com');
        $this->record($foothill, 'up', now()->subHours(30));
        $this->record($foothill, 'up', now()->subHours(2));

        $this->actingAs($this->admin)->getJson('/api/lms-status')
            ->assertJson(['retention_hours' => 24])
            ->assertJsonCount(1, 'timeline');
    }

    #[Test]
    public function history_returns_one_schools_checks_in_order()
    {
        $foothill = $this->canvasRegistration('foothill.instructure.com');
        $other = $this->canvasRegistration('other.instructure.com');
        $this->record($foothill, 'down', now()->subMinutes(20), ['error' => 'Could not connect']);
        $this->record($foothill, 'up', now()->subMinutes(10), ['response_ms' => 150, 'http_code' => 200]);
        $this->record($other, 'up', now()->subMinutes(10));

        $history = $this->actingAs($this->admin)->getJson("/api/lms-status/{$foothill->id}/history")
            ->assertJson(['type' => 'success'])
            ->assertJsonCount(2, 'history')
            ->json('history');

        $this->assertSame(['down', 'up'], array_column($history, 'status'));
        $this->assertSame('Could not connect', $history[0]['error']);
        $this->assertSame(150, $history[1]['response_ms']);
    }
}
