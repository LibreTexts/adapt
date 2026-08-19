<?php

namespace Tests\Feature;

use App\LmsAPI;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CanvasAPITest extends TestCase
{

    /**
     * Builds the same shape of object Course::getLtiRegistration() returns
     * (a plain stdClass from a raw DB join), scoped to the given school.
     *
     * @param int $school_id
     * @return object
     */
    private function makeLtiRegistration(int $school_id): object
    {
        return (object) [
            'school_id' => $school_id,
            'campus_id' => null,
            'api_key' => 'test-api-key',
            'api_secret' => 'test-api-secret',
            'auth_server' => 'https://staging-canvas.libretexts.org',
            'iss' => 'https://staging-canvas.libretexts.org',
        ];
    }

    /**
     * Inserts a row into lms_access_tokens and returns its id.
     *
     * @param int $user_id
     * @param int $school_id
     * @param string $refresh_token
     * @param string $access_token
     * @param Carbon $updated_at
     * @return int
     */
    private function makeLmsAccessToken(
        int    $user_id,
        int    $school_id,
        string $refresh_token,
        string $access_token = 'old-access-token',
        Carbon $updated_at = null
    ): int
    {
        $updated_at = $updated_at ?? Carbon::now()->subHour();

        return DB::table('lms_access_tokens')->insertGetId([
            'user_id' => $user_id,
            'school_id' => $school_id,
            'lms' => 'canvas',
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'created_at' => $updated_at,
            'updated_at' => $updated_at,
        ]);
    }

    /** @test */
    public function update_access_token_uses_the_refresh_token_scoped_to_the_correct_school()
    {
        $user = factory(User::class)->create();

        // Same user, two different schools -- this is the exact scenario that
        // triggered the bug: getAccessToken() used to re-query by user_id alone
        // and could grab whichever row came first, regardless of school.
        $this->makeLmsAccessToken($user->id, 100, 'wrong-school-refresh-token');
        $this->makeLmsAccessToken($user->id, 200, 'correct-school-refresh-token');

        Http::fake([
            '*/login/oauth2/token' => Http::response(['access_token' => 'new-access-token'], 200),
            '*/api/v1/courses*' => Http::response([], 200),
        ]);

        $lmsAPI = new LmsAPI();
        $lti_registration = $this->makeLtiRegistration(200);
        $result = $lmsAPI->getCourses($lti_registration, $user->id);

        $debug_message = is_array($result['message'] ?? null) ? json_encode($result['message']) : ($result['message'] ?? 'no message returned');
        $this->assertEquals('success', $result['type'], $debug_message);

        Http::assertSent(function ($request) {
            return strpos($request->url(), 'login/oauth2/token') !== false
                && $request['refresh_token'] === 'correct-school-refresh-token';
        });
    }

    /** @test */
    public function update_access_token_persists_a_rotated_refresh_token()
    {
        $user = factory(User::class)->create();
        $token_id = $this->makeLmsAccessToken($user->id, 200, 'original-refresh-token');

        Http::fake([
            '*/login/oauth2/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'rotated-refresh-token',
            ], 200),
            '*/api/v1/courses*' => Http::response([], 200),
        ]);

        $lmsAPI = new LmsAPI();
        $lti_registration = $this->makeLtiRegistration(200);
        $lmsAPI->getCourses($lti_registration, $user->id);

        $this->assertDatabaseHas('lms_access_tokens', [
            'id' => $token_id,
            'refresh_token' => 'rotated-refresh-token',
            'access_token' => 'new-access-token',
        ]);
    }

    /** @test */
    public function update_access_token_leaves_refresh_token_unchanged_when_canvas_does_not_rotate_it()
    {
        $user = factory(User::class)->create();
        $token_id = $this->makeLmsAccessToken($user->id, 200, 'original-refresh-token');

        Http::fake([
            // No refresh_token in the response -- Canvas instances that don't rotate.
            '*/login/oauth2/token' => Http::response(['access_token' => 'new-access-token'], 200),
            '*/api/v1/courses*' => Http::response([], 200),
        ]);

        $lmsAPI = new LmsAPI();
        $lti_registration = $this->makeLtiRegistration(200);
        $lmsAPI->getCourses($lti_registration, $user->id);

        $this->assertDatabaseHas('lms_access_tokens', [
            'id' => $token_id,
            'refresh_token' => 'original-refresh-token',
            'access_token' => 'new-access-token',
        ]);
    }

    /** @test */
    public function update_access_token_is_not_called_when_token_is_still_fresh()
    {
        $user = factory(User::class)->create();
        $this->makeLmsAccessToken(
            $user->id,
            200,
            'still-fresh-refresh-token',
            'still-fresh-access-token',
            Carbon::now()->subMinutes(5)
        );

        Http::fake([
            '*/api/v1/courses*' => Http::response([], 200),
        ]);

        $lmsAPI = new LmsAPI();
        $lti_registration = $this->makeLtiRegistration(200);
        $result = $lmsAPI->getCourses($lti_registration, $user->id);

        $this->assertEquals('success', $result['type']);

        Http::assertNotSent(function ($request) {
            return strpos($request->url(), 'login/oauth2/token') !== false;
        });
    }

    /** @test */
    public function get_courses_returns_an_error_when_canvas_rejects_the_refresh_token()
    {
        $user = factory(User::class)->create();
        $this->makeLmsAccessToken($user->id, 200, 'stale-refresh-token');

        Http::fake([
            '*/login/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $lmsAPI = new LmsAPI();
        $lti_registration = $this->makeLtiRegistration(200);
        $result = $lmsAPI->getCourses($lti_registration, $user->id);

        $this->assertEquals('error', $result['type']);
        $this->assertStringContainsString('invalid_grant', $result['message']);
    }
}
