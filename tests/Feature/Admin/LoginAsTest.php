<?php

namespace Tests\Feature\Auth;

use App\Assignment;
use App\Course;
use App\User;
use Tests\TestCase;

class LoginAsTest extends TestCase
{
    protected $admin_user;
    protected $non_admin_user;
    protected $instructor;
    protected $course;
    protected $assignment;

    public function setUp(): void
    {
        parent::setUp();

        // Matches the admin/non-admin convention already used in
        // Tests\Feature\Admin\UserTest: Helper::isAdmin() treats id 1 (and 5) as admin.
        $this->admin_user = factory(User::class)->create(['id' => 1, 'email' => 'me@me.com']); // Admin
        $this->non_admin_user = factory(User::class)->create(['id' => 9999]); // not Admin

        $this->instructor = factory(User::class)->create(['role' => 2, 'email' => 'instructor@example.com']);
        $this->course = factory(Course::class)->create(['user_id' => $this->instructor->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
    }

    /**
     * Decode a login-as JWT and return the User it belongs to, the same way the
     * app itself would when the token is later presented on a request.
     */
    private function userFromToken(string $token): User
    {
        return \JWTAuth::setToken($token)->toUser();
    }

    /** @test */
    public function admin_can_log_in_as_instructor_using_a_bare_assignment_id()
    {
        $response = $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => (string) $this->assignment->id])
            ->assertJson(['type' => 'success']);

        $loggedInAs = $this->userFromToken($response->json('token'));
        $this->assertEquals($this->instructor->id, $loggedInAs->id);
    }

    /** @test */
    public function admin_can_log_in_as_instructor_using_a_url_containing_the_assignment_id()
    {
        $url = "https://adapt.libretexts.org/instructors/assignments/{$this->assignment->id}/information/questions";

        $response = $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => $url])
            ->assertJson(['type' => 'success']);

        $loggedInAs = $this->userFromToken($response->json('token'));
        $this->assertEquals($this->instructor->id, $loggedInAs->id);
    }

    /** @test */
    public function admin_can_still_log_in_as_instructor_using_the_original_question_view_url_shape()
    {
        // Regression check: the URL pattern this branch originally matched
        // (before it was loosened to match an assignment id anywhere in the URL)
        // must keep working.
        $url = "https://adapt.libretexts.org/instructors/assignments/{$this->assignment->id}/questions/view/5/";

        $response = $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => $url])
            ->assertJson(['type' => 'success']);

        $loggedInAs = $this->userFromToken($response->json('token'));
        $this->assertEquals($this->instructor->id, $loggedInAs->id);
    }

    /** @test */
    public function login_as_fails_gracefully_for_a_nonexistent_assignment_id()
    {
        $bogus_id = $this->assignment->id + 999999;

        $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => (string) $bogus_id])
            ->assertJson([
                'type' => 'error',
                'message' => "$bogus_id is not a valid assignment id.",
            ]);
    }

    /** @test */
    public function login_as_fails_gracefully_for_a_url_with_a_nonexistent_assignment_id()
    {
        $bogus_id = $this->assignment->id + 999999;
        $url = "https://adapt.libretexts.org/instructors/assignments/{$bogus_id}/information/questions";

        $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => $url])
            ->assertJson([
                'type' => 'error',
                'message' => "$bogus_id is not a valid assignment id.",
            ]);
    }

    /** @test */
    public function login_as_fails_gracefully_for_a_url_with_no_assignment_id()
    {
        $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => 'https://adapt.libretexts.org/instructors/courses'])
            ->assertJson([
                'type' => 'error',
                'message' => 'That is not a valid URL to log in as.',
            ]);
    }

    /** @test */
    public function admin_can_still_log_in_as_a_user_selected_by_name()
    {
        // Regression check: the original "First Last --- email" selection flow,
        // which the assignment id/URL branches were added alongside, must keep working.
        $selection = "{$this->instructor->first_name} {$this->instructor->last_name} --- {$this->instructor->email}";

        $response = $this->actingAs($this->admin_user)
            ->postJson('/api/user/login-as', ['user' => $selection])
            ->assertJson(['type' => 'success']);

        $loggedInAs = $this->userFromToken($response->json('token'));
        $this->assertEquals($this->instructor->id, $loggedInAs->id);
    }

    /** @test */
    public function non_admin_cannot_log_in_as_instructor_via_assignment_id()
    {
        $this->actingAs($this->non_admin_user)
            ->postJson('/api/user/login-as', ['user' => (string) $this->assignment->id])
            ->assertJson([
                'type' => 'error',
                'message' => 'You are not allowed to log in as a different user.',
            ]);
    }
}
