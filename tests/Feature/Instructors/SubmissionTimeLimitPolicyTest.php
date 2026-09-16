<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

/**
 * NOTE: filed under Tests\Feature\Instructors to match the existing
 * Assignment*Test.php files' namespace/directory - move if student-facing
 * submission tests actually live elsewhere in your suite.
 *
 * These specifically cover the time-limit branch added to
 * canSubmitBasedOnGeneralSubmissionPolicy(), not the pre-existing
 * due-date/late-policy logic in that same method.
 */
class SubmissionTimeLimitPolicyTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id, 'formative' => 0]);
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

        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'assessment_type' => 'real time',
            'formative' => 0,
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->fake_student->id);

        $this->assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assign_to_timing->time_limit = '10 minutes';
        $this->assign_to_timing->save();

        // assignUserToAssignment makes one AssignToTiming per call, so the
        // fake student landed on their own separate row (same pattern noted
        // in AssignmentTimeLimitControllerTest) - give it the same
        // time_limit so the fake_student tests below actually exercise the
        // timed branch instead of silently skipping it.
        $this->assign_to_timing_for_fake_student = AssignToTiming::where('assignment_id', $this->assignment->id)
            ->where('id', '!=', $this->assign_to_timing->id)
            ->first();
        $this->assign_to_timing_for_fake_student->time_limit = '10 minutes';
        $this->assign_to_timing_for_fake_student->save();

        $this->question = factory(Question::class)->create(['page_id' => rand(1, 1000000000)]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);
    }

    private function canSubmitUrl(): string
    {
        return "/api/submissions/can-submit/assignment/{$this->assignment->id}/question/{$this->question->id}/is-forge/0";
    }

    private function startTimer(User $user): void
    {
        $this->actingAs($user)
            ->postJson("/api/assignments/{$this->assignment->id}/time-limit/start");
    }

    /** @test */
    public function student_cannot_submit_before_starting_the_timer()
    {

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since you have not started the timer for this assignment.'
            ]);
    }

    /** @test */
    public function student_can_submit_once_started_and_within_the_time_window()
    {
        $this->startTimer($this->student_user);

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function student_cannot_submit_once_their_personal_timer_has_expired_even_though_the_assignment_is_still_due_later()
    {
        $this->startTimer($this->student_user);

        // Assignment is still open (due in ~1 hour per assignUserToAssignment),
        // but this student's personal clock has run out.
        DB::table('assign_to_timing_starts')
            ->where('assign_to_timing_id', $this->assign_to_timing->id)
            ->where('user_id', $this->student_user->id)
            ->update(['expires_at' => Carbon::now()->subMinute()]);

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since your time limit for this assignment has expired.'
            ]);
    }

    /** @test */
    public function a_student_with_no_time_limit_configured_is_unaffected()
    {
        $this->assign_to_timing->time_limit = null;
        $this->assign_to_timing->save();

        // Never started a timer at all - should still succeed since no time
        // limit applies to this assign-to group.
        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function the_course_owner_bypasses_the_time_limit_check_entirely()
    {
        // Never started a timer, and $this->user owns the course - the
        // top-level ownsCourseOrIsCoInstructor bypass should apply before the
        // time-limit check is ever reached.
        $this->actingAs($this->user)
            ->getJson($this->canSubmitUrl())
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function fake_student_or_instructor_impersonation_is_still_subject_to_the_time_limit()
    {
        // Regression test: the instructor_user_id ("logged in as student")
        // bypass sits AFTER the time-limit check specifically so this case
        // still gets enforced. Simulates impersonation by setting
        // instructor_user_id directly, same as a real "log in as student"
        // session would have set on this user record.
        $this->student_user->instructor_user_id = $this->user->id;
        $this->student_user->save();

        // Never started the timer.
        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since you have not started the timer for this assignment.'
            ]);
    }

    /** @test */
    public function fake_student_impersonation_still_blocks_submission_after_expiry()
    {
        $this->startTimer($this->student_user);
        DB::table('assign_to_timing_starts')
            ->where('assign_to_timing_id', $this->assign_to_timing->id)
            ->where('user_id', $this->student_user->id)
            ->update(['expires_at' => Carbon::now()->subMinute()]);

        $this->student_user->instructor_user_id = $this->user->id;
        $this->student_user->save();

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since your time limit for this assignment has expired.'
            ]);
    }

    /** @test */
    public function impersonation_bypass_still_applies_for_ordinary_due_date_checks_once_the_timer_passes()
    {
        // The instructor_user_id bypass should still work for anything AFTER
        // the time-limit check (e.g. due date/late policy) - only the
        // time-limit check itself was moved ahead of it.
        $this->startTimer($this->student_user);
        $this->assign_to_timing->due = Carbon::now()->subDay(); // due date already passed
        $this->assign_to_timing->save();

        $this->student_user->instructor_user_id = $this->user->id;
        $this->student_user->save();

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function never_started_and_past_due_shows_the_normal_due_date_message_instead_of_not_started()
    {
        // Regression test: the time-limit "not started" check used to
        // short-circuit before the due-date check ever ran, so this always
        // said "you have not started the timer" even once the window had
        // closed. It should now fall through to the normal due-date handling.
        $this->assignment->late_policy = 'not accepted';
        $this->assignment->save();
        $this->assign_to_timing->due = Carbon::now()->subMinute();
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since the due date for this assignment has passed.'
            ]);
    }

    /** @test */
    public function never_started_and_past_due_with_a_late_accepting_policy_is_let_through_with_no_timer_at_all()
    {
        // Intended behavior (confirmed with the product owner): once the
        // window has closed, a student who never started should get the
        // normal late-submission experience, not be blocked or handed a
        // timer at this point - the personal time limit only ever applies
        // to someone who actually started it while the window was open.
        $this->assignment->late_policy = 'deduction';
        $this->assignment->save();
        $this->assign_to_timing->due = Carbon::now()->subMinute();
        $this->assign_to_timing->final_submission_deadline = Carbon::now()->addDay();
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
    }

    /** @test */
    public function a_real_fake_student_cannot_submit_before_starting_the_timer()
    {        // Regression test: an actual fake_student=1 user satisfies
        // ownsCourseOrIsCoInstructor($user->id) (they're the instructor's
        // own testing account for this course), which was returning success
        // before the time-limit check ever ran - letting a Fake Student see
        // and submit to a timed question without ever clicking Start. The
        // two tests above this one are misleadingly named "fake_student" but
        // only exercise instructor_user_id impersonation of a real student,
        // not this case.
        $this->actingAs($this->fake_student)
            ->getJson("/api/submissions/can-submit/assignment/{$this->assignment->id}/question/{$this->question->id}/is-forge/0")
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since you have not started the timer for this assignment.'
            ]);
    }

    /** @test */
    public function a_real_fake_student_can_submit_once_they_start_the_timer()
    {
        $this->startTimer($this->fake_student);

        $this->actingAs($this->fake_student)
            ->getJson("/api/submissions/can-submit/assignment/{$this->assignment->id}/question/{$this->question->id}/is-forge/0")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function a_real_fake_student_cannot_submit_once_their_personal_timer_has_expired()
    {
        $this->startTimer($this->fake_student);

        DB::table('assign_to_timing_starts')
            ->where('assign_to_timing_id', $this->assign_to_timing_for_fake_student->id)
            ->where('user_id', $this->fake_student->id)
            ->update(['expires_at' => Carbon::now()->subMinute()]);

        $this->actingAs($this->fake_student)
            ->getJson("/api/submissions/can-submit/assignment/{$this->assignment->id}/question/{$this->question->id}/is-forge/0")
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since your time limit for this assignment has expired.'
            ]);
    }

    /** @test */
    public function a_real_fake_student_who_never_started_is_still_blocked_once_past_due()
    {
        // Regression test: real students get an untimed late-policy
        // fallthrough once the window closes (see the two tests below), but
        // a fake student must not - they're allowed to start() past due
        // specifically so instructors can test the timed-out experience,
        // which only means something if they're still required to actually
        // click Start first, same as when the window is open.
        $this->assign_to_timing_for_fake_student->due = Carbon::now()->subMinute();
        $this->assign_to_timing_for_fake_student->save();

        $this->actingAs($this->fake_student)
            ->getJson("/api/submissions/can-submit/assignment/{$this->assignment->id}/question/{$this->question->id}/is-forge/0")
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since you have not started the timer for this assignment.'
            ]);
    }

    /** @test */
    public function never_started_and_before_available_from_shows_the_normal_not_yet_available_message()
    {
        // Same fix, other side of the window: never started and too early
        // should say "not yet available", not "not started".
        $this->assign_to_timing->available_from = Carbon::now()->addDay();
        $this->assign_to_timing->save();

        $this->actingAs($this->student_user)
            ->getJson($this->canSubmitUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'No responses will be saved since this assignment is not yet available.'
            ]);
    }
}
