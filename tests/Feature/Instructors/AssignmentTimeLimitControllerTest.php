<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\Section;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

/**
 * NOTE: filed under Tests\Feature\Instructors to match the existing
 * Assignment*Test.php files' namespace/directory - move this if your app
 * actually keeps student-facing endpoint tests somewhere else
 * (e.g. Tests\Feature\Students).
 */
class AssignmentTimeLimitControllerTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->student_user = factory(User::class)->create(['role' => 3]);
        Enrollment::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->student_user->id
        ]);

        $this->fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        Enrollment::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->fake_student->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        // available_from = now, due = now + 1 hour (see Test trait) - a
        // sensible default window for most of these tests.
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->fake_student->id);
        // assignUserToAssignment makes one AssignToTiming per call, so both
        // students actually landed on their own separate timing rows - grab
        // the one for the real student for tests that only care about them.
        $this->assign_to_timing = AssignToTiming::whereIn('id', DB::table('assign_to_users')
            ->where('user_id', $this->student_user->id)
            ->pluck('assign_to_timing_id'))
            ->where('assignment_id', $this->assignment->id)
            ->first();
        $this->assign_to_timing->time_limit = '10 minutes';
        $this->assign_to_timing->save();
    }

    /** @test */
    public function a_student_not_enrolled_in_the_course_cannot_view_their_status()
    {
        $outside_student = factory(User::class)->create(['role' => 3]);

        $this->actingAs($outside_student)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/status")
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function a_student_not_enrolled_in_the_course_cannot_start_the_timer()
    {
        $outside_student = factory(User::class)->create(['role' => 3]);

        $this->actingAs($outside_student)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'error']);

        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $outside_student->id,
        ]);
    }

    /** @test */
    public function student_can_start_the_timer_for_a_timed_assignment()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'instructor_adjusted' => false,
        ]);
    }

    /** @test */
    public function starting_twice_does_not_reset_the_clock()
    {
        $first = $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->json();

        $second = $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->json();

        $this->assertEquals($first['expires_at'], $second['expires_at']);
    }

    /** @test */
    public function cannot_start_if_the_assignment_has_no_time_limit()
    {
        $this->assign_to_timing->time_limit = null;
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'error', 'message' => 'This assignment does not have a time limit.']);
    }

    /** @test */
    public function cannot_start_before_available_from()
    {
        $this->assign_to_timing->available_from = Carbon::now()->addDay();
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'error', 'message' => 'This assignment is not yet available.']);
    }

    /** @test */
    public function cannot_start_after_due()
    {
        $this->assign_to_timing->due = Carbon::now()->subMinute();
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'error', 'message' => 'This assignment is past due and can no longer be started.']);
    }

    /** @test */
    public function a_fake_student_can_start_the_timer_even_after_the_due_date()
    {
        // Fake students exist for instructors to test the timed experience,
        // including what happens past due - unlike a real student, they
        // shouldn't be locked out of starting just because the group's due
        // date has passed.
        $this->assign_to_timing->due = Carbon::now()->subMinute();
        $this->assign_to_timing->save();

        $this->assign_to_timing_for_fake_student = AssignToTiming::whereIn('id', DB::table('assign_to_users')
            ->where('user_id', $this->fake_student->id)
            ->pluck('assign_to_timing_id'))
            ->where('assignment_id', $this->assignment->id)
            ->first();
        $this->assign_to_timing_for_fake_student->time_limit = '10 minutes';
        $this->assign_to_timing_for_fake_student->due = Carbon::now()->subMinute();
        $this->assign_to_timing_for_fake_student->save();

        $data = $this->actingAs($this->fake_student)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'success'])
            ->json();

        $this->assertGreaterThan(0, $data['seconds_left']);
    }

    /** @test */
    public function a_fake_students_expiry_is_not_capped_at_a_due_date_that_has_already_passed()
    {
        // Regression check for the capping logic specifically: without the
        // fake_student exemption, expires_at would get clamped down to the
        // (already past) due date, handing them a timer that's expired the
        // instant it starts.
        $this->assign_to_timing_for_fake_student = AssignToTiming::whereIn('id', DB::table('assign_to_users')
            ->where('user_id', $this->fake_student->id)
            ->pluck('assign_to_timing_id'))
            ->where('assignment_id', $this->assignment->id)
            ->first();
        $this->assign_to_timing_for_fake_student->time_limit = '10 minutes';
        $this->assign_to_timing_for_fake_student->due = Carbon::now()->subDay();
        $this->assign_to_timing_for_fake_student->save();

        $data = $this->actingAs($this->fake_student)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->assertJson(['type' => 'success'])
            ->json();

        // Should be ~10 minutes from now, not clamped to a day ago.
        $this->assertGreaterThan(500, $data['seconds_left']);
    }

    /** @test */
    public function start_caps_expiry_at_the_due_date_when_the_time_limit_would_exceed_it()
    {
        $this->assign_to_timing->time_limit = '2 hours'; // longer than the 1-hour window
        $this->assign_to_timing->save();

        $data = $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start")
            ->json();

        $expiresAt = Carbon::parse($data['expires_at']);
        $due = Carbon::parse($this->assign_to_timing->due);
        $this->assertTrue($expiresAt->equalTo($due) || $expiresAt->lessThanOrEqualTo($due));
    }

    /** @test */
    public function status_shows_no_time_limit_when_none_is_configured()
    {
        $this->assign_to_timing->time_limit = null;
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/status")
            ->assertJson(['type' => 'success', 'time_limit' => null]);
    }

    /** @test */
    public function status_shows_seconds_left_for_a_running_timer()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $data = $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/status")
            ->json();

        $this->assertGreaterThan(0, $data['seconds_left']);
        $this->assertLessThanOrEqual(600, $data['seconds_left']); // 10 minutes
    }

    /** @test */
    public function instructor_can_add_time_to_a_students_running_clock()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $data = $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/add-time", ['minutes' => 5])
            ->assertJson(['type' => 'success'])
            ->json();

        $this->assertGreaterThan(600, $data['seconds_left']); // more than the original 10 minutes
        $this->assertDatabaseHas('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'instructor_adjusted' => true,
        ]);
    }

    /** @test */
    public function non_owner_instructor_cannot_add_time()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->user_2)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/add-time", ['minutes' => 5])
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function student_cannot_add_time_to_their_own_clock()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/add-time", ['minutes' => 5])
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function adding_time_to_an_already_expired_clock_recomputes_from_now()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        DB::table('assign_to_timing_starts')
            ->where('assign_to_timing_id', $this->assign_to_timing->id)
            ->where('user_id', $this->student_user->id)
            ->update(['expires_at' => Carbon::now()->subHours(3)]);

        $data = $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/add-time", ['minutes' => 10])
            ->assertJson(['type' => 'success', 'was_expired' => true])
            ->json();

        // Should be ~10 minutes from now, not ~2h50m in the past.
        $this->assertGreaterThan(0, $data['seconds_left']);
    }

    /** @test */
    public function adding_time_flags_when_it_extends_past_the_due_date()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/add-time", ['minutes' => 120])
            ->assertJson(['type' => 'success', 'extended_past_due' => true]);
    }

    /** @test */
    public function instructor_setting_a_new_time_limit_for_an_already_expired_student_starts_fresh_from_now()
    {
        // Regression test: setTime() used to always recompute from the
        // student's original started_at, even once their timer had already
        // fully expired - adding a short duration back onto a long-past
        // start time just produced another already-expired timestamp,
        // making the override look like it did nothing.
        $this->assign_to_timing->time_limit = '15 seconds';
        $this->assign_to_timing->save();
        DB::table('assign_to_timing_starts')->insert([
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'started_at' => Carbon::now()->subHour(),
            'expires_at' => Carbon::now()->subHour()->addSeconds(15),
            'created_at' => Carbon::now()->subHour(),
            'updated_at' => Carbon::now()->subHour(),
        ]);

        $data = $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}", ['time_limit' => '15 seconds'])
            ->assertJson(['type' => 'success', 'was_expired' => true])
            ->json();

        $this->assertGreaterThan(0, $data['seconds_left']);
    }

    /** @test */
    public function instructor_can_set_a_new_time_limit_for_a_student()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}", ['time_limit' => '30 minutes'])
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'time_limit_override' => '30 minutes',
            'instructor_adjusted' => true,
        ]);
    }

    /** @test */
    public function instructor_can_fully_reset_a_students_timer()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/reset")
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
    }

    /** @test */
    public function fake_student_can_reset_their_own_timer()
    {
        $this->actingAs($this->fake_student)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->fake_student)
            ->deleteJson("/api/assignments/{$this->assignment->id}/time-limit/reset")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function a_real_student_cannot_reset_their_own_timer()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->student_user)
            ->deleteJson("/api/assignments/{$this->assignment->id}/time-limit/reset")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to reset this timer.']);
    }

    /** @test */
    public function instructor_can_view_a_specific_students_status()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");

        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/status")
            ->assertJson(['type' => 'success', 'time_limit' => '10 minutes']);
    }

    /** @test */
    public function non_owner_instructor_cannot_view_another_instructors_student_status()
    {
        $this->actingAs($this->user_2)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/status")
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function instructor_can_view_status_of_a_student_who_has_not_started_yet()
    {
        // Regression test: buildTimeLimitStatus() used to read started_at/
        // expires_at off the assign_to_timing_starts row unconditionally,
        // which blew up when the student had never started (no row exists
        // at all). This is exactly the case an instructor hits when trying
        // to grant a time-limit override to a student who never began the
        // assignment.
        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}/status")
            ->assertJson([
                'type' => 'success',
                'time_limit' => '10 minutes',
                'started_at' => null,
                'expires_at' => null,
                'seconds_left' => null,
            ]);
    }

    /** @test */
    public function students_own_status_check_does_not_error_before_they_have_started()
    {
        // Same regression as above, via the student-facing self-status
        // endpoint that every student's assignment page calls on load,
        // before they've ever clicked Start.
        $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/time-limit/status")
            ->assertJson([
                'type' => 'success',
                'time_limit' => '10 minutes',
                'started_at' => null,
            ]);
    }

    /** @test */
    public function instructor_can_set_a_time_limit_override_for_a_student_who_never_started_and_is_past_due()
    {
        // The actual override scenario this was reported from: the group's
        // due date has already passed and the student never clicked Start,
        // but the instructor wants to give them a fresh window anyway.
        // setTime() should start the clock for them right now regardless of
        // the due date having already passed - only the student's own
        // self-service start() endpoint blocks on that.
        $this->assign_to_timing->due = Carbon::now()->subDay();
        $this->assign_to_timing->save();

        $data = $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/time-limit/{$this->student_user->id}", ['time_limit' => '20 minutes'])
            ->assertJson(['type' => 'success', 'extended_past_due' => true])
            ->json();

        $this->assertGreaterThan(0, $data['seconds_left']);
        $this->assertDatabaseHas('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'time_limit_override' => '20 minutes',
        ]);
    }
}
