<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\Services\LatePenaltyRecalculator;
use App\Traits\Test;
use App\User;
use Carbon\Carbon;
use App\Jobs\ProcessLatePenaltyRecalculation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asking the instructor to recalculate late penalties after an assignment's due date changes:
 *   PATCH /api/assignments/{assignment}  returns check_late_penalties when a due date moved
 *   GET   /api/assignments/{assignment}/late-penalty-recalculation  previews the change
 *   PATCH /api/assignments/{assignment}/late-penalty-recalculation  applies it
 *
 * Same scenario as RecalculateLatePenaltiesTest: 10% per 1 day, a 10-point question, submitted
 * Jan 10, 2026 at 1:00pm UTC against a due date of Jan 9 at 12:00pm (20% late, so 5 is stored as 4).
 */
class LatePenaltyRecalculationControllerTest extends TestCase
{
    use Test;

    private $user;
    private $user_2;
    private $student_user;
    private $course;
    private $assignment;
    private $question;
    private $submission_id;

    public function setup(): void
    {
        parent::setUp();
        // The question is switched off in the code until it launches; these tests check it working.
        LatePenaltyRecalculator::$askInstructorsAfterDueDateChanges = true;
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3, 'first_name' => 'Ana', 'last_name' => 'Ruiz']);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        // Enrolled, so a course-wide assign-to saved through the form still includes the student.
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'late_policy' => 'deduction',
            'late_deduction_percent' => 10,
            'late_deduction_application_period' => '1 day',
            'assessment_type' => 'delayed',
            'scoring_type' => 'p',
        ]);
        $this->question = factory(Question::class)->create(['page_id' => rand(1, 1000000000), 'technology' => 'webwork']);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0',
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->setDue('2026-01-09 12:00:00');

        $submitted_at = Carbon::parse('2026-01-10 13:00:00', 'UTC');
        $response = json_encode(['score' => [
            'result' => 0.5,
            'answers' => ['AnSwEr0001' => ['score' => 0.5]],
        ]]);
        $this->submission_id = DB::table('submissions')->insertGetId([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $response,
            'score' => 4,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => 0,
            'created_at' => $submitted_at,
            'updated_at' => $submitted_at,
        ]);
        DB::table('submission_histories')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $response,
            'created_at' => $submitted_at,
            'updated_at' => $submitted_at,
        ]);
        DB::table('scores')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'score' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function tearDown(): void
    {
        LatePenaltyRecalculator::$askInstructorsAfterDueDateChanges = false;
        parent::tearDown();
    }

    private function setDue(string $due): void
    {
        DB::table('assign_to_timings')
            ->where('assignment_id', $this->assignment->id)
            ->update(['due' => Carbon::parse($due, 'UTC')]);
    }

    private function url(): string
    {
        return "/api/assignments/{$this->assignment->id}/late-penalty-recalculation";
    }

    private function submissionScore(): float
    {
        return (float)DB::table('submissions')->where('id', $this->submission_id)->value('score');
    }

    private function assignmentScore(): float
    {
        return (float)DB::table('scores')
            ->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->value('score');
    }

    /**
     * The assignment properties form, as the page sends it, with one assign-to for the whole course.
     */
    private function assignmentInfo(string $due_date, string $late_policy = 'deduction'): array
    {
        $assign_to = [
            'groups' => [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']],
            'available_from' => '2026-01-01 09:00:00',
            'available_from_date' => '2026-01-01',
            'available_from_time' => '9:00 AM',
            'due' => "$due_date 09:00:00",
            'due_date' => $due_date,
            'due_time' => '9:00 AM',
            'final_submission_deadline' => '2027-01-01 09:00:00',
            'final_submission_deadline_date' => '2027-01-01',
            'final_submission_deadline_time' => '9:00 AM',
        ];
        return [
            'course_id' => $this->course->id,
            'name' => 'First Assignment',
            'can_view_hint' => 0,
            'assign_tos' => [$assign_to],
            'scoring_type' => 'p',
            'source' => 'a',
            'points_per_question' => 'number of points',
            'default_points_per_question' => 2,
            'can_submit_work' => 0,
            'students_can_view_assignment_statistics' => 0,
            'include_in_weighted_average' => 1,
            'can_contact_instructor_auto_graded' => 'never',
            'late_policy' => $late_policy,
            'late_deduction_percent' => 10,
            'late_deduction_applied_once' => 0,
            'late_deduction_application_period' => '1 day',
            'assessment_type' => 'delayed',
            'default_open_ended_submission_type' => 'file',
            'instructions' => 'Some instructions',
            'number_of_randomized_assessments' => null,
            'notifications' => 1,
            'assignment_group_id' => 1,
            'algorithmic' => 0,
            'formative' => 0,
            'file_upload_mode' => 'both',
            'groups_0' => ['Everybody'],
            'due_0' => "$due_date 09:00:00",
            'due_date_0' => $due_date,
            'due_time_0' => '9:00 AM',
            'available_from_0' => '2026-01-01 09:00:00',
            'available_from_date_0' => '2026-01-01',
            'available_from_time_0' => '9:00 AM',
            'final_submission_deadline_0' => '2027-01-01 09:00:00',
            'final_submission_deadline_date_0' => '2027-01-01',
            'final_submission_deadline_time_0' => '9:00 AM',
        ];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_a_new_due_date_asks_to_check_late_penalties()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfo('2026-01-20'))
            ->assertJson(['type' => 'success', 'check_late_penalties' => true]);

        // The student is still assigned, now with the new due date.
        $this->assertTrue(DB::table('assign_to_timings')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->where('assign_to_timings.assignment_id', $this->assignment->id)
            ->where('assign_to_users.user_id', $this->student_user->id)
            ->where('assign_to_timings.due', 'like', '2026-01-%')
            ->exists());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function nothing_is_asked_while_the_question_is_switched_off()
    {
        LatePenaltyRecalculator::$askInstructorsAfterDueDateChanges = false;

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfo('2026-01-20'))
            ->assertJson(['type' => 'success', 'check_late_penalties' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_without_changing_the_due_date_does_not_ask()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfo('2026-01-20'))
            ->assertJson(['type' => 'success']);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfo('2026-01-20'))
            ->assertJson(['type' => 'success', 'check_late_penalties' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function saving_a_new_due_date_without_a_deduction_policy_does_not_ask()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfo('2026-01-20', 'marked late'))
            ->assertJson(['type' => 'success', 'check_late_penalties' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_preview_lists_students_whose_hw_score_changes_and_saves_nothing()
    {
        $this->setDue('2026-01-11 12:00:00');

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson([
                'type' => 'success',
                'num_changes' => 1,
                'num_skipped' => 0,
                'total_points' => 10,
                'students' => [[
                    'name' => 'Ana Ruiz',
                    'current_score' => 4,
                    'new_score' => 5,
                    'change' => 1,
                    'pending_change' => 0,
                ]],
            ]);
        $this->assertEquals(4, $this->submissionScore());
        $this->assertEquals(4, $this->assignmentScore());
        $this->assertEquals(0, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_preview_is_empty_when_no_score_changes()
    {
        // Moving the due date from Jan 9 noon to Jan 9 1:00pm keeps the submission 20% late.
        $this->setDue('2026-01-09 13:00:00');

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'num_changes' => 0, 'students' => []]);
    }

    private function setFinalSubmissionDeadline(string $final_submission_deadline): void
    {
        DB::table('assign_to_timings')
            ->where('assignment_id', $this->assignment->id)
            ->update(['final_submission_deadline' => Carbon::parse($final_submission_deadline, 'UTC')]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_preview_lists_students_who_submitted_after_the_final_deadline()
    {
        // Submitted Jan 10 at 1:00pm; the final submission deadline is now Jan 10 at noon.
        $this->setFinalSubmissionDeadline('2026-01-10 12:00:00');

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'submitted_after_final_deadline' => [['name' => 'Ana Ruiz', 'questions' => [1]]]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submissions_before_the_final_deadline_are_not_listed()
    {
        $this->setFinalSubmissionDeadline('2026-01-20 12:00:00');

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'submitted_after_final_deadline' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function students_allowed_to_submit_late_are_not_listed()
    {
        $this->setFinalSubmissionDeadline('2026-01-10 12:00:00');
        DB::table('assignment_level_overrides')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'submitted_after_final_deadline' => []]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_student_in_two_assign_tos_uses_the_first_one_like_adapt()
    {
        // The first assign-to keeps the Jan 9 due date (20% late, as stored). A later one with Jan 11
        // would make it on time; ADAPT uses the first, so nothing should change.
        $timing_id = DB::table('assign_to_timings')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'available_from' => Carbon::parse('2026-01-01 00:00:00', 'UTC'),
            'due' => Carbon::parse('2026-01-11 12:00:00', 'UTC'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('assign_to_users')->insert([
            'assign_to_timing_id' => $timing_id,
            'user_id' => $this->student_user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'num_changes' => 0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function recalculating_saves_the_new_scores()
    {
        $this->setDue('2026-01-11 12:00:00');

        $response = $this->actingAs($this->user)
            ->patchJson($this->url())
            ->assertJson(['type' => 'success']);

        $this->assertStringStartsWith('The new scores will appear within a few minutes', $response->json('message'));
        $this->assertEquals(5, $this->submissionScore());
        $this->assertEquals(5, $this->assignmentScore());
        $this->assertDatabaseHas('late_penalty_recalculations', [
            'record_table' => 'submissions',
            'record_id' => $this->submission_id,
            'old_penalty_percent' => 20,
            'new_penalty_percent' => 0,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function recalculating_runs_in_the_background()
    {
        Bus::fake();
        $this->setDue('2026-01-11 12:00:00');

        $this->actingAs($this->user)
            ->patchJson($this->url())
            ->assertJson(['type' => 'success']);

        Bus::assertDispatched(ProcessLatePenaltyRecalculation::class);
        // Nothing is saved during the request itself.
        $this->assertEquals(4, $this->submissionScore());
        $this->assertEquals(0, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_job_can_run_twice_safely()
    {
        $this->setDue('2026-01-11 12:00:00');

        (new ProcessLatePenaltyRecalculation($this->assignment->id))->handle(app(LatePenaltyRecalculator::class));
        (new ProcessLatePenaltyRecalculation($this->assignment->id))->handle(app(LatePenaltyRecalculator::class));

        $this->assertEquals(5, $this->submissionScore());
        $this->assertEquals(5, $this->assignmentScore());
        $this->assertEquals(1, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function automatic_passback_is_queued_for_the_scheduler()
    {
        $this->assignment->update(['lms_grade_passback' => 'automatic']);
        $this->setDue('2026-01-11 12:00:00');

        $response = $this->actingAs($this->user)->patchJson($this->url());

        $this->assertStringContainsString('passed back to your LMS', $response->json('message'));
        $this->assertDatabaseHas('passback_by_assignments', [
            'assignment_id' => $this->assignment->id,
            'status' => 'manual_pending',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_passback_is_left_to_the_instructor()
    {
        $this->assignment->update(['lms_grade_passback' => 'manual']);
        $this->setDue('2026-01-11 12:00:00');

        $response = $this->actingAs($this->user)->patchJson($this->url());

        $this->assertStringContainsString('when you are ready', $response->json('message'));
        $this->assertDatabaseMissing('passback_by_assignments', ['assignment_id' => $this->assignment->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function recalculating_when_nothing_changes_saves_nothing()
    {
        $this->actingAs($this->user)
            ->patchJson($this->url())
            ->assertJson(['type' => 'success']);

        $this->assertEquals(4, $this->submissionScore());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function assignments_without_a_deduction_policy_are_not_recalculated()
    {
        $this->assignment->update(['late_policy' => 'marked late']);
        $this->setDue('2026-01-11 12:00:00');

        $this->actingAs($this->user)
            ->getJson($this->url())
            ->assertJson(['type' => 'success', 'num_changes' => 0]);
        $this->actingAs($this->user)
            ->patchJson($this->url())
            ->assertJson(['type' => 'error']);
        $this->assertEquals(4, $this->submissionScore());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function only_the_course_instructors_can_preview()
    {
        $this->actingAs($this->user_2)
            ->getJson($this->url())
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to update this assignment.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function only_the_course_instructors_can_recalculate()
    {
        $this->setDue('2026-01-11 12:00:00');

        $this->actingAs($this->user_2)
            ->patchJson($this->url())
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to update this assignment.']);
        $this->assertEquals(4, $this->submissionScore());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function students_cannot_preview_or_recalculate()
    {
        $this->setDue('2026-01-11 12:00:00');

        $this->actingAs($this->student_user)->getJson($this->url())->assertJson(['type' => 'error']);
        $this->actingAs($this->student_user)->patchJson($this->url())->assertJson(['type' => 'error']);
        $this->assertEquals(4, $this->submissionScore());
    }
}
