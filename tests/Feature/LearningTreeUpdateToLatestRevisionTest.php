<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\LearningTreeNodeSubmission;
use App\Question;
use App\QuestionRevision;
use App\Section;
use App\User;
use App\Traits\Test;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeUpdateToLatestRevisionTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
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

    private function updateEndpoint(User $user = null, bool $understand = true)
    {
        return $this->actingAs($user ?? $this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/update-to-latest-revision",
                ['understand_student_submissions_removed' => $understand]);
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
    public function cannot_update_without_confirming_submissions_will_be_removed_when_the_assignment_is_open()
    {
        $this->openTheAssignment();

        $this->updateEndpoint($this->user, false)
            ->assertJson(['message' => 'You must confirm that you understand that student submissions will be removed.']);
    }

    /** @test */
    public function cannot_update_without_confirming_submissions_will_be_removed_when_a_real_node_submission_exists()
    {
        // assignment itself is still closed (no assign_to_timings at all) -
        // the real submission alone should be enough to require confirmation
        $student = factory(User::class)->create(['role' => 3, 'fake_student' => 0]);
        factory(Question::class)->create(['id' => 102438]);
        factory(LearningTreeNodeSubmission::class)->create([
            'user_id' => $student->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'question_id' => 102438]);

        $this->updateEndpoint($this->user, false)
            ->assertJson(['message' => 'You must confirm that you understand that student submissions will be removed.']);
    }

    /** @test */
    public function can_update_without_confirming_when_the_assignment_is_closed_and_has_no_real_submissions()
    {
        // no assign_to_timings row at all is created in setup(), so the
        // assignment is closed by definition here, and nothing else has
        // added a submission - there's nothing at risk, so the confirmation
        // shouldn't be required.
        $this->updateEndpoint($this->user, false)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function fake_student_node_submissions_dont_require_confirmation()
    {
        $fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        factory(Question::class)->create(['id' => 102438]);
        factory(LearningTreeNodeSubmission::class)->create([
            'user_id' => $fake_student->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'question_id' => 102438]);

        $this->updateEndpoint($this->user, false)
            ->assertJson(['type' => 'success']);
    }

    private function openTheAssignment(): void
    {
        $student = factory(User::class)->create(['role' => 3]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $student->id);
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = now()->addWeek();
        $assignToTiming->save();
    }

    /** @test */
    public function only_course_owner_or_co_instructor_can_update()
    {
        $this->updateEndpoint($this->user_2)
            ->assertJson(['message' => 'You are not allowed to update to the latest revision for that question.']);
    }

    /** @test */
    public function updating_sets_the_root_nodes_question_revision_id_to_latest()
    {
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);
        $latest_revision_id = QuestionRevision::where('question_id', $this->root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;

        $this->updateEndpoint();

        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->root_question->id,
            'question_revision_id' => $latest_revision_id]);
    }

    /** @test */
    public function updating_resyncs_the_snapshot_to_the_live_tree()
    {
        $assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();

        // add a node to the live tree after it was already assigned
        $tree = json_decode($this->learning_tree->learning_tree, true);
        $new_question = factory(Question::class)->create();
        $tree['blocks'][] = ['id' => 6, 'parent' => 0, 'data' => [
            ['name' => 'blockelemtype', 'value' => '2'],
            ['name' => 'question_id', 'value' => (string)$new_question->id],
            ['name' => 'blockid', 'value' => '6']], 'attr' => []];
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->save();

        $this->updateEndpoint();

        $row_after = $assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($this->assignment->id, $this->learning_tree->id);
        $this->assertCount(7, json_decode($row_after->learning_tree, true)['blocks']);
        $this->assertFalse($assignmentQuestionLearningTree->learningTreeNeedsUpdate($row_after, $this->learning_tree->fresh()));
    }

    /** @test */
    public function updating_moves_assignment_question_to_a_new_root_question_id_when_the_root_was_swapped()
    {
        // direct regression test for the "Undefined offset" crash / silent
        // no-op that happened when the root node's question_id itself changed
        $new_root_question = factory(Question::class)->create();
        factory(QuestionRevision::class)->create(['question_id' => $new_root_question->id, 'revision_number' => 1]);
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $assignment_question_id_before = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)->first()->id;

        $this->updateEndpoint()->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('assignment_question', [
            'id' => $assignment_question_id_before,
            'question_id' => $new_root_question->id]);
    }

    /** @test */
    public function authorization_uses_the_currently_attached_question_not_the_new_root()
    {
        // direct regression test for the policy-denial bug: authorizing
        // against the tree's *live* root (already swapped) instead of
        // whatever assignment_question.question_id currently is fails the
        // policy's in_array($question->id, $assignment->questions...) check
        $new_root_question = factory(Question::class)->create();
        factory(QuestionRevision::class)->create(['question_id' => $new_root_question->id, 'revision_number' => 1]);
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $this->updateEndpoint()->assertJson(['type' => 'success']);
    }

    /** @test */
    public function updating_removes_learning_tree_node_seeds_resets_and_submissions_for_the_whole_tree()
    {
        $student = factory(User::class)->create(['role' => 3]);
        factory(Question::class)->create(['id' => 102438]);
        factory(LearningTreeNodeSubmission::class)->create([
            'user_id' => $student->id,
            'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id,
            'question_id' => 102438]);
        DB::table('learning_tree_resets')->insert([
            'user_id' => $student->id, 'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id, 'number_resets_available' => 1]);
        DB::table('learning_tree_node_seeds')->insert([
            'user_id' => $student->id, 'assignment_id' => $this->assignment->id,
            'learning_tree_id' => $this->learning_tree->id, 'question_id' => 102438, 'seed' => 'abc']);

        $this->updateEndpoint();

        $this->assertDatabaseMissing('learning_tree_node_submissions', ['learning_tree_id' => $this->learning_tree->id]);
        $this->assertDatabaseMissing('learning_tree_resets', ['learning_tree_id' => $this->learning_tree->id]);
        $this->assertDatabaseMissing('learning_tree_node_seeds', ['learning_tree_id' => $this->learning_tree->id]);
    }

    /** @test */
    public function updating_returns_no_emails_when_there_were_no_submissions()
    {
        $this->updateEndpoint()
            ->assertJson(['student_emails_associated_with_submissions' => []]);
    }

    /** @test */
    public function response_includes_the_new_root_question_id()
    {
        $new_root_question = factory(Question::class)->create();
        factory(QuestionRevision::class)->create(['question_id' => $new_root_question->id, 'revision_number' => 1]);
        $this->swapRootQuestionIdInLiveTree($new_root_question->id);

        $this->updateEndpoint()
            ->assertJson(['new_root_question_id' => $new_root_question->id]);
    }

    /** @test */
    public function throws_when_the_tree_isnt_actually_attached_to_the_assignment()
    {
        $other_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'learning_tree' => $this->learningTree()]);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/learning-tree/{$other_tree->id}/update-to-latest-revision",
                ['understand_student_submissions_removed' => true])
            ->assertJson(['type' => 'error']);
    }
}
