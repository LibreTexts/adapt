<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\Course;
use App\LearningTree;
use App\Question;
use App\QuestionRevision;
use App\User;
use App\Traits\Test;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeNeedsUpdateTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
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

        $this->assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();
        $this->assignment_question_learning_tree = $this->assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($this->assignment->id, $this->learning_tree->id);
    }

    /**
     * Mutates the *live* learning_trees.learning_tree JSON for a single
     * block's question_id, simulating an instructor editing the tree after
     * it was already added to an assignment. The stored snapshot on
     * assignment_question_learning_tree is left untouched.
     */
    private function replaceBlockQuestionId(int $block_id, int $new_question_id): void
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        foreach ($tree['blocks'] as $key => $block) {
            if ($block['id'] === $block_id) {
                foreach ($block['data'] as $data_key => $entry) {
                    if ($entry['name'] === 'question_id') {
                        $tree['blocks'][$key]['data'][$data_key]['value'] = (string)$new_question_id;
                    }
                }
            }
        }
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->save();
    }

    /** @test */
    public function flags_false_when_nothing_has_changed()
    {
        $this->assertFalse($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_a_node_is_added_to_the_tree()
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        $tree['blocks'][] = [
            'id' => 6,
            'parent' => 0,
            'data' => [
                ['name' => 'blockelemtype', 'value' => '2'],
                ['name' => 'question_id', 'value' => '999'],
                ['name' => 'blockid', 'value' => '6']
            ],
            'attr' => []
        ];
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->save();

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_a_node_is_removed_from_the_tree()
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        array_pop($tree['blocks']);
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->save();

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_a_nodes_parent_changes()
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        foreach ($tree['blocks'] as $key => $block) {
            if ($block['id'] === 2) {
                $tree['blocks'][$key]['parent'] = 4;
            }
        }
        $this->learning_tree->learning_tree = json_encode($tree);
        $this->learning_tree->save();

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_a_nodes_question_id_changes()
    {
        // the root-swap scenario specifically - a node pointing at an
        // entirely different question, not just a new revision of the same one
        $new_question = factory(Question::class)->create();
        $this->replaceBlockQuestionId(0, $new_question->id);

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_a_nodes_question_has_a_newer_revision()
    {
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_false_when_a_node_question_exists_but_has_no_revisions()
    {
        // block id 1 in the fixture tree points to question_id 102438, which
        // has no Question row at all until now, and gets none created here -
        // shouldn't cause a false "needs update" once it exists with zero revisions
        factory(Question::class)->create(['id' => 102438]);

        $this->assertFalse($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate(
            $this->assignment_question_learning_tree, $this->learning_tree->fresh()));
    }

    /** @test */
    public function flags_true_when_no_snapshot_has_ever_been_recorded()
    {
        // simulates a tree added to an assignment before this feature existed
        DB::table('assignment_question_learning_tree')
            ->where('id', $this->assignment_question_learning_tree->id)
            ->update(['learning_tree' => null]);
        $row = $this->assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($this->assignment->id, $this->learning_tree->id);

        $this->assertTrue($this->assignmentQuestionLearningTree->learningTreeNeedsUpdate($row, $this->learning_tree->fresh()));
    }
}
