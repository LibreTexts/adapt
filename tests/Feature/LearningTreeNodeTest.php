<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\LearningTreeNodeDescription;
use App\LearningTreeNodeSubmission;
use App\LearningTreeReset;
use App\Question;
use App\Section;
use App\User;
use App\Traits\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeNodeTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->learning_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'learning_tree' => $this->learningTree()]);
        $final_node_question_id = $this->learning_tree->finalQuestionIds()[0];
        $this->node_question_id = $final_node_question_id;
        $this->node_question = factory(Question::class)->create(['id' => $this->node_question_id, 'technology' => 'text']);
        $this->root_node_question = factory(Question::class)->create([
            'id' => $this->learning_tree->root_node_question_id, 'technology' => 'h5p']);
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->root_node_question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $this->learning_tree_node_submission = factory(LearningTreeNodeSubmission::class)
            ->create(['user_id' => $this->student_user->id,
                'assignment_id' => $this->assignment->id,
                'learning_tree_id' => $this->learning_tree->id,
                'question_id' => $this->node_question->id]);
            LearningTreeNodeDescription::create(['user_id' => $this->student_user->id,
                'learning_tree_id' => $this->learning_tree->id,
                'question_id' => $this->node_question->id,
                'title' => 'sdfdsf',
                'description'=> 'sdfsdfsdfsd']);
    }

    /** @test */
    public function gets_the_correct_time_left_for_exposition_node_node_or_text_question()
    {
        $this->assignment->min_number_of_minutes_in_exposition_node = 15;
        $this->assignment->save();
        $response = $this->actingAs($this->student_user)->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/$this->node_question_id")
            ->content();
        $this->assertEquals(15 * 60 * 1000, json_decode($response)->node_question->time_left);
    }

    /** @test */
    public function only_valid_student_can_get_credit_for_completion()
    {
        $this->actingAs($this->user_2)
            ->postJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/$this->node_question_id/give-credit-for-completion")
            ->assertJson(['message' => 'You are not a student in this course.']);

    }

    /** @test */
    public function must_be_text_based_or_exposition_to_get_timed_credit()
    {

        $this->node_question->technology = 'webwork';
        $this->node_question->save();
        $this->actingAs($this->student_user)->postJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}/give-credit-for-completion")
            ->assertJson(['message' => 'The question should either be text-based or an exposition question.']);
    }

    /** @test */
    public function can_only_reset_root_node_submission_question_if_question_is_in_assignment()
    {
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_node_question->id)
            ->delete();
        $this->actingAs($this->student_user)->postJson("/api/learning-tree-node/reset-root-node-submission/assignment/{$this->assignment->id}/question/{$this->root_node_question->id}")
            ->assertJson(['message' => "That question cannot be reset since it's not in the assignment."]);
    }

    /** @test */
    public function can_only_reset_root_node_submission_question_if_assignment_is_in_your_course()
    {

        $this->actingAs($this->user_2)->postJson("/api/learning-tree-node/reset-root-node-submission/assignment/{$this->assignment->id}/question/{$this->root_node_question->id}")
            ->assertJson(['message' => 'You are not a student in this course so you cannot reset the root node submission.']);
    }

    /** @test */
    public function can_not_reset_root_node_submission_question_if_assignment_is_past_due()
    {
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = "2000-03-05 09:00:00";//was due in the past
        $assignToTiming->save();

        $this->actingAs($this->student_user)->postJson("/api/learning-tree-node/reset-root-node-submission/assignment/{$this->assignment->id}/question/{$this->root_node_question->id}")
            ->assertJson(['message' => 'Since this assignment is past due, you cannot reset the original submission.']);
    }

    /** @test */
    public function only_owner_of_learning_tree_node_submission_can_view_it()
    {

        $this->actingAs($this->user_2)->getJson("api/learning-tree-node-submission/{$this->learning_tree_node_submission->id}")
            ->assertJson(['message' => 'You are not allowed to show this learning tree node submission.']);
    }

    /** @test */
    public function correctly_applies_reset()
    {
        $this->assertDatabaseHas('learning_tree_node_submissions', [
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'question_id' => $this->node_question->id,
            'check_for_reset' => 1]);
        $assignment_question = AssignmentSyncQuestion::where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_node_question->id)
            ->first();
        DB::table('assignment_question_learning_tree')->insert([
            'assignment_question_id' => $assignment_question->id,
            'learning_tree_id' => $this->learning_tree->id,
            'number_of_successful_paths_for_a_reset' => 1
        ]);
        $this->node_question->technology = 'h5p';
        $this->node_question->save();
        $this->actingAs($this->student_user)->getJson("api/learning-tree-node-submission/{$this->learning_tree_node_submission->id}")
            ->assertJson(['message' => 'Your submission was correct. You have earned a reset and can retry the root question for points.']);

        $this->assertDatabaseHas('learning_tree_resets', [
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'number_resets_available' => 1]);
        $this->assertDatabaseHas('learning_tree_node_submissions', [
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'question_id' => $this->node_question->id,
            'check_for_reset' => 0]);
    }

}
