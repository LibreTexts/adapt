<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\LearningTree;
use App\Question;
use App\QuestionRevision;
use App\User;
use App\Traits\Test;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeAssignmentQuestionTest extends TestCase
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
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);
    }

    private function addLearningTreeToAssignment()
    {
        return $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/learning-trees/{$this->learning_tree->id}");
    }

    /** @test */
    public function adding_a_learning_tree_sets_the_root_nodes_question_revision_id()
    {
        $this->addLearningTreeToAssignment();
        $latest_revision_id = QuestionRevision::where('question_id', $this->root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->root_question->id,
            'question_revision_id' => $latest_revision_id]);
    }

    /** @test */
    public function adding_a_learning_tree_stores_a_structure_and_revision_snapshot()
    {
        $this->addLearningTreeToAssignment();
        $assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_question->id)
            ->first()->id;
        $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $assignment_question_id)
            ->first();

        $this->assertNotNull($assignment_question_learning_tree->learning_tree);
        // the fixture tree (Test::learningTree()) has 6 blocks: root + 3 exposition + webwork + h5p
        $blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'];
        $this->assertCount(6, $blocks);
    }

    /** @test */
    public function snapshot_records_the_latest_revision_id_for_the_root_node()
    {
        $this->addLearningTreeToAssignment();
        $latest_revision_id = QuestionRevision::where('question_id', $this->root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;

        $assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_question->id)
            ->first()->id;
        $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $assignment_question_id)
            ->first();
        $blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'];
        $root_block = collect($blocks)->firstWhere('id', 0);
        $revision_entry = collect($root_block['data'])->firstWhere('name', 'question_revision_id');

        $this->assertEquals($latest_revision_id, (int)$revision_entry['value']);
    }

    /** @test */
    public function snapshot_records_an_empty_revision_for_a_node_question_with_no_revisions()
    {
        // block id 5 in the fixture tree points to question_id 2, which has
        // no factory(Question::class) row at all in this setup - the snapshot
        // should still be built without erroring, just with a blank revision
        // for that node.
        $this->addLearningTreeToAssignment();
        $assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_question->id)
            ->first()->id;
        $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $assignment_question_id)
            ->first();
        $blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'];
        $block_five = collect($blocks)->firstWhere('id', 5);
        $revision_entry = collect($block_five['data'])->firstWhere('name', 'question_revision_id');

        $this->assertSame('', $revision_entry['value']);
    }

    /** @test */
    public function cannot_add_a_learning_tree_whose_root_question_is_already_in_the_assignment()
    {
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->root_question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $this->addLearningTreeToAssignment()
            ->assertJson(['message' => 'A Learning Tree with the same root node question already exists in the assignment.']);
    }
}
