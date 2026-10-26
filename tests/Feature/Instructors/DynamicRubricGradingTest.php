<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Helpers\RubricHelper;
use App\Question;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saving scores with a dynamic rubric through POST /api/grading (setup mirrors GradingTest).
 */
class DynamicRubricGradingTest extends TestCase
{
    private $user;
    private $student_user;
    private $course;
    private $section;
    private $assignment;
    private $question;
    private $dynamic_rubric;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->student_user->save();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);

        $this->dynamic_rubric = [
            'scoring_style' => 'dynamic',
            'version' => 1,
            'rubric_shown' => true,
            'rubric_items' => [
                ['id' => 'a', 'title' => 'Correct six-membered ring', 'description' => '', 'points' => 2],
                ['id' => 'b', 'title' => 'Correct arrow', 'description' => '', 'points' => 2],
                ['id' => 'c', 'title' => 'Correct intermediate', 'description' => '', 'points' => 3],
                ['id' => 'z', 'title' => 'Five-membered ring instead (comment only)', 'description' => '', 'points' => 0]]];
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode($this->dynamic_rubric)]);

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        SubmissionFile::create([
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()]);
    }

    private function gradingData(array $overrides = []): array
    {
        return array_merge([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'text_feedback_editor' => 'plain',
            'question_submission_score' => 0,
            'textFeedback' => '',
            'dynamic_rubric' => ['applied_item_ids' => ['a', 'c'], 'adjustment' => 0, 'rubric_version' => 1]
        ], $overrides);
    }

    private function savedFileSubmissionScore(): float
    {
        return (float)DB::table('submission_files')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->first()
            ->score;
    }

    private function assignmentQuestion()
    {
        return DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first();
    }

    #[Test]
    public function score_is_the_sum_of_the_applied_items()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData())
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 5]]);
        $this->assertEquals(5, $this->savedFileSubmissionScore());

        $breakdown = DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->first();
        $this->assertEquals(['a', 'c'], json_decode($breakdown->applied_item_ids, true));
        $this->assertEquals(1, (int)$breakdown->rubric_version);
        $this->assertEquals(0, (int)$breakdown->needs_review);
    }

    #[Test]
    public function score_is_capped_at_the_question_points_minus_the_auto_graded_score()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'question_submission_score' => 4,
            'dynamic_rubric' => ['applied_item_ids' => ['a', 'b', 'c'], 'adjustment' => 0, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['raw' => 7, 'clamped' => 6, 'final' => 6]]);
        $this->assertEquals(6, $this->savedFileSubmissionScore());
    }

    #[Test]
    public function late_penalty_is_applied_after_the_cap()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'applied_late_penalty' => 10,
            'dynamic_rubric' => ['applied_item_ids' => ['a', 'b', 'c'], 'adjustment' => 5, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['raw' => 12, 'clamped' => 10, 'final' => 9]]);
        $this->assertEquals(9, $this->savedFileSubmissionScore());
    }

    #[Test]
    public function a_negative_adjustment_cannot_take_the_score_below_zero()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => ['applied_item_ids' => ['a'], 'adjustment' => -5, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 0]]);
        $this->assertEquals(0, $this->savedFileSubmissionScore());
    }

    #[Test]
    public function no_credit_saves_an_explicit_zero()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => ['applied_item_ids' => [], 'adjustment' => 0, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 0]]);
        $this->assertEquals(0, $this->savedFileSubmissionScore());
        $this->assertTrue(DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->exists());
    }

    #[Test]
    public function an_out_of_date_rubric_version_is_refused()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => ['applied_item_ids' => ['a'], 'adjustment' => 0, 'rubric_version' => 0]]))
            ->assertJson(['type' => 'error',
                'message' => 'The rubric was changed by someone else. Please reload the page before saving.']);
    }

    #[Test]
    public function an_item_that_is_not_in_the_rubric_is_refused()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => ['applied_item_ids' => ['a', 'not-an-item'], 'adjustment' => 0, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'error',
                'message' => 'Some of the rubric items you selected no longer exist. Please reload the page.']);
    }

    #[Test]
    public function a_typed_in_score_is_refused_for_a_dynamic_rubric()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => null,
            'file_submission_score' => 5]))
            ->assertJson(['type' => 'error',
                'message' => "This question's rubric uses dynamic scoring. Please reload the page."]);
    }

    #[Test]
    public function dynamic_items_are_refused_for_a_static_rubric()
    {
        DB::table('questions')->where('id', $this->question->id)
            ->update(['rubric' => json_encode(['rubric_items' => [['title' => 'Static item', 'description' => '', 'points' => 2]], 'rubric_shown' => true])]);
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData())
            ->assertJson(['type' => 'error',
                'message' => "This question's rubric no longer uses dynamic scoring. Please reload the page."]);
    }

    #[Test]
    public function non_owner_cannot_save_a_dynamic_rubric_score()
    {
        $other_instructor = factory(User::class)->create();
        $this->actingAs($other_instructor)->postJson('/api/grading', $this->gradingData())
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to provide a score for this assignment.']);
    }

    #[Test]
    public function first_use_copies_the_rubric_into_the_assignment_so_author_edits_cannot_change_scores()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData())
            ->assertJson(['type' => 'success']);
        $assignment_question = $this->assignmentQuestion();
        $this->assertEquals(0, (int)$assignment_question->use_existing_rubric);
        $this->assertEquals($this->dynamic_rubric, json_decode($assignment_question->custom_rubric, true));

        //the question's author later changes the default rubric
        $authors_new_rubric = json_encode(array_merge($this->dynamic_rubric, ['version' => 2, 'rubric_items' => [
            ['id' => 'a', 'title' => 'Correct six-membered ring', 'description' => '', 'points' => 9]]]));
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => $authors_new_rubric]);

        $this->assertEquals($this->dynamic_rubric,
            json_decode(RubricHelper::assignmentQuestionRubric($this->assignmentQuestion(), $authors_new_rubric), true));
    }

    #[Test]
    public function each_dynamic_save_is_logged()
    {
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData())
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => ['applied_item_ids' => ['a'], 'adjustment' => 1, 'rubric_version' => 1]]))
            ->assertJson(['type' => 'success']);

        $logs = DB::table('rubric_score_logs')
            ->where('assignment_id', $this->assignment->id)
            ->where('student_user_id', $this->student_user->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $logs);
        $this->assertEquals('graded', $logs[1]->reason);
        $this->assertEquals($this->user->id, $logs[1]->grader_id);
        $before = json_decode($logs[1]->before, true);
        $after = json_decode($logs[1]->after, true);
        $this->assertEquals(['a', 'c'], $before['applied_item_ids']);
        $this->assertEquals(5, (float)$before['score']);
        $this->assertEquals(['applied_item_ids' => ['a'], 'adjustment' => 1, 'score' => 3], $after);
    }

    #[Test]
    public function resaving_an_unchanged_static_rubric_score_reports_that_nothing_changed()
    {
        DB::table('questions')->where('id', $this->question->id)
            ->update(['rubric' => json_encode(['rubric_items' => [['title' => 'Static item', 'description' => '', 'points' => 3]], 'rubric_shown' => true])]);
        $static_data = $this->gradingData([
            'dynamic_rubric' => null,
            'file_submission_score' => 3,
            'rubric_points_breakdown' => [['title' => 'Static item', 'description' => '', 'points' => 3]]]);

        $this->actingAs($this->user)->postJson('/api/grading', $static_data)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->postJson('/api/grading', $static_data)
            ->assertJson(['type' => 'info',
                'message' => 'Neither the total score nor the overall feedback has been updated.']);
    }

    #[Test]
    public function changing_a_static_rubric_breakdown_is_saved()
    {
        DB::table('questions')->where('id', $this->question->id)
            ->update(['rubric' => json_encode(['rubric_items' => [
                ['title' => 'Item one', 'description' => '', 'points' => 3],
                ['title' => 'Item two', 'description' => '', 'points' => 3]], 'rubric_shown' => true])]);
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => null,
            'file_submission_score' => 3,
            'rubric_points_breakdown' => [['title' => 'Item one', 'description' => '', 'points' => 3],
                ['title' => 'Item two', 'description' => '', 'points' => 0]]]))
            ->assertJson(['type' => 'success']);
        //same total, different breakdown: must still be saved
        $this->actingAs($this->user)->postJson('/api/grading', $this->gradingData([
            'dynamic_rubric' => null,
            'file_submission_score' => 3,
            'rubric_points_breakdown' => [['title' => 'Item one', 'description' => '', 'points' => 0],
                ['title' => 'Item two', 'description' => '', 'points' => 3]]]))
            ->assertJson(['type' => 'success']);
    }
}
