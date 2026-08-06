<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\Question;
use App\QuestionRevision;
use App\Section;
use App\User;
use App\Traits\Test;
use Tests\TestCase;

class LearningTreeQuestionsViewTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'assessment_type' => 'learning tree']);
        $this->learning_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            // Test::learningTree()'s fixture JSON has its root block (id: 0)
            // pointing at question_id "1" - the LearningTree factory's own
            // default root_node_question_id (102685) has nothing to do with
            // that JSON, so it's overridden here to keep the two in sync.
            'root_node_question_id' => 1,
            'learning_tree' => $this->learningTree()]);
        $this->root_question = factory(Question::class)->create(['id' => $this->learning_tree->root_node_question_id]);
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 1]);

        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/learning-trees/{$this->learning_tree->id}");
    }

    private function viewQuestions(User $user = null)
    {
        return $this->actingAs($user ?? $this->user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers());
    }

    private function swapRootQuestionIdInLiveTree(int $new_question_id): void
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        foreach ($tree['blocks'] as $key => $block) {
            if ($block['id'] === 0) {
                foreach ($block['data'] as $data_key => $entry) {
                    if ($entry['name'] === 'question_id') {
                        $tree['blocks'][$key]['data'][$data_key]['value'] = (string)$new_question_id;
                    }
                }
            }
        }
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->root_node_question_id = $new_question_id;
        $this->learning_tree->save();
    }

    /** @test */
    public function flag_is_false_when_tree_is_up_to_date()
    {
        $this->viewQuestions()
            ->assertJson(['questions' => [['learning_tree_needs_update' => false]]]);
    }

    /** @test */
    public function instructor_sees_learning_tree_needs_update_flag_when_a_node_revision_changed()
    {
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);

        $this->viewQuestions()
            ->assertJson(['questions' => [['learning_tree_needs_update' => true]]]);
    }

    /** @test */
    public function viewing_assignment_does_not_error_when_root_node_question_was_swapped()
    {
        // direct regression test for the "Undefined offset" crash
        $new_root_question = factory(Question::class)->create();
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $this->viewQuestions()->assertJson(['type' => 'success']);
    }

    /** @test */
    public function needs_update_flag_stays_correct_when_root_node_question_was_swapped()
    {
        // regression test for the "keyed by root_node_question_id instead of
        // learning_tree_id" bug that silently returned false here
        $new_root_question = factory(Question::class)->create();
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $this->viewQuestions()
            ->assertJson(['questions' => [['learning_tree_needs_update' => true]]]);
    }

    /** @test */
    public function title_stays_correct_when_root_node_question_was_swapped()
    {
        $new_root_question = factory(Question::class)->create();
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $this->viewQuestions()
            ->assertJson(['questions' => [['title' => $this->learning_tree->fresh()->title]]]);
    }

    /** @test */
    public function risks_real_submissions_flag_is_true_when_tree_needs_update_and_assignment_is_closed_but_no_submissions_exist_is_false()
    {
        // this assignment has no assign_to_timings row at all, so it's
        // closed, and nothing has submitted - once the tree is flagged as
        // needing an update, the risk flag underneath it should read false
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);

        $this->viewQuestions()
            ->assertJson(['questions' => [[
                'learning_tree_needs_update' => true,
                'learning_tree_update_risks_real_submissions' => false
            ]]]);
    }

    /** @test */
    public function risks_real_submissions_flag_defaults_true_when_the_tree_does_not_need_an_update()
    {
        $this->viewQuestions()
            ->assertJson(['questions' => [[
                'learning_tree_needs_update' => false,
                'learning_tree_update_risks_real_submissions' => true
            ]]]);
    }

    /** @test */
    public function student_never_receives_a_true_learning_tree_needs_update_flag()
    {
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);

        $this->viewQuestions($this->student_user)
            ->assertJson(['questions' => [['learning_tree_needs_update' => false]]]);
    }
}
