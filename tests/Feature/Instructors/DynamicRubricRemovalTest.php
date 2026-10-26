<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\Helpers\DynamicRubricRemover;
use App\Mail\RubricScoreChanged;
use App\Question;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Deleting the overriding rubric, or switching rubrics, when students were graded with the dynamic rubric:
 * only with confirmation, and their scores are cleared.
 */
class DynamicRubricRemovalTest extends TestCase
{
    private $user;
    private $course;
    private $section;
    private $assignment;
    private $question;
    private $graded;
    private $overridden;
    private $dropped;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 0]);
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 0]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'rubric_shown' => true, 'rubric_items' => [['title' => 'Question criterion', 'description' => '', 'points' => 1]]])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10,
            'use_existing_rubric' => 0,
            'custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 2, 'rubric_shown' => true,
                'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 6],
                    ['id' => 'b', 'title' => 'Correct arrow', 'description' => '', 'points' => 4]]])]);
        $this->graded = $this->gradedStudent(6, true);
        $this->overridden = $this->gradedStudent(4, true);
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->overridden->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '9',
            'created_at' => now(),
            'updated_at' => now()]);
        $this->dropped = $this->gradedStudent(0, false);
    }

    private function gradedStudent(float $score, bool $with_submission): User
    {
        $student = factory(User::class)->create(['role' => 3]);
        if ($with_submission) {
            factory(Enrollment::class)->create([
                'user_id' => $student->id,
                'section_id' => $this->section->id,
                'course_id' => $this->course->id
            ]);
            SubmissionFile::create([
                'type' => 'q',
                'user_id' => $student->id,
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'submission' => 'fake_1.pdf',
                'original_filename' => 'orig_fake_1.pdf',
                'score' => $score,
                'text_feedback' => 'Good start',
                'date_submitted' => Carbon::now()->subDay(),
                'date_graded' => Carbon::now()]);
        }
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $student->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode(['a']),
            'adjustment' => 0,
            'rubric_version' => 2]);
        return $student;
    }

    private function url(string $suffix): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function remove(string $action, int $expected = 1, bool $confirmed = true, bool $email = false, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->patchJson($this->url('/remove-with-scores'), [
            'action' => $action, 'expected_number_to_clear' => $expected, 'confirmed' => $confirmed, 'email_students' => $email]);
    }

    private function assignmentQuestion()
    {
        return DB::table('assignment_question')->where('assignment_id', $this->assignment->id)->first();
    }

    private function submissionFile(User $student)
    {
        return DB::table('submission_files')->where('assignment_id', $this->assignment->id)->where('user_id', $student->id)->first();
    }

    #[Test]
    public function the_preview_counts_the_scores_that_would_be_cleared()
    {
        $this->actingAs($this->user)->postJson($this->url('/remove-with-scores-preview'))
            ->assertJson(['type' => 'success', 'required' => true, 'number_to_clear' => 1, 'scores_released' => false]);
    }

    #[Test]
    public function deleting_the_override_clears_the_scores_and_keeps_feedback_and_overrides()
    {
        $this->remove('delete_override')->assertJson(['type' => 'success', 'number_of_students_cleared' => 1,
            'use_existing_rubric' => true, 'overriding_rubric' => false]);
        $assignment_question = $this->assignmentQuestion();
        $this->assertNull($assignment_question->custom_rubric);
        $this->assertEquals(1, (int)$assignment_question->use_existing_rubric);
        $this->assertEquals(0, DB::table('rubric_points_breakdowns')->where('assignment_id', $this->assignment->id)->count());
        $cleared = $this->submissionFile($this->graded);
        $this->assertNull($cleared->score);
        $this->assertNull($cleared->date_graded);
        $this->assertEquals('Good start', $cleared->text_feedback);
        $this->assertEquals(4, (float)$this->submissionFile($this->overridden)->score);
        $log = DB::table('rubric_change_logs')->first();
        $this->assertEquals('delete_override_clear', $log->action);
        $this->assertEquals(1, $log->students_rescored);
        $this->assertEquals(3, json_decode($log->after, true)['version']);
    }

    #[Test]
    public function switching_to_the_question_rubric_keeps_the_override_for_later()
    {
        $this->remove('switch_rubric')->assertJson(['type' => 'success', 'use_existing_rubric' => true, 'overriding_rubric' => true]);
        $assignment_question = $this->assignmentQuestion();
        $this->assertEquals(1, (int)$assignment_question->use_existing_rubric);
        $this->assertNotNull($assignment_question->custom_rubric);
        $this->assertNull($this->submissionFile($this->graded)->score);
        $this->assertEquals('switch_rubric_clear', DB::table('rubric_change_logs')->first()->action);
    }

    #[Test]
    public function nothing_happens_without_confirmation_or_if_the_count_changed()
    {
        $this->remove('delete_override', 1, false)->assertJson(['type' => 'error']);
        $this->remove('delete_override', 2)->assertJson(['type' => 'error', 'message' => DynamicRubricRemover::CHANGED_MESSAGE]);
        $this->remove('something_else')->assertJson(['type' => 'error']);
        $this->assertNotNull($this->assignmentQuestion()->custom_rubric);
        $this->assertEquals(6, (float)$this->submissionFile($this->graded)->score);
        $this->assertEquals(3, DB::table('rubric_points_breakdowns')->where('assignment_id', $this->assignment->id)->count());
    }

    #[Test]
    public function only_instructors_can_do_it()
    {
        $grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $grader_user->id, 'section_id' => $this->section->id]);
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $grader_user->id]);
        $this->remove('delete_override', 1, true, false, $grader_user)
            ->assertJson(['type' => 'error', 'message' => DynamicRubricRemover::ONLY_INSTRUCTORS_MESSAGE]);
        $this->actingAs($grader_user)->postJson($this->url('/remove-with-scores-preview'))
            ->assertJson(['type' => 'error', 'message' => DynamicRubricRemover::ONLY_INSTRUCTORS_MESSAGE]);
    }

    #[Test]
    public function cleared_students_are_emailed_when_asked_and_scores_are_released()
    {
        Mail::fake();
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->remove('delete_override', 1, true, true)->assertJson(['type' => 'success', 'number_of_students_emailed' => 1]);
        Mail::assertQueued(RubricScoreChanged::class, 1);
    }

    #[Test]
    public function a_rubric_without_dynamic_scores_uses_the_normal_flow()
    {
        DB::table('rubric_points_breakdowns')->delete();
        $this->actingAs($this->user)->postJson($this->url('/remove-with-scores-preview'))
            ->assertJson(['type' => 'success', 'required' => false]);
    }
}
