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

class BackfillLearningTreeQuestionRevisionsTest extends TestCase
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

        // simulate a tree added to an assignment *before* this feature
        // existed: no question_revision_id on assignment_question, no
        // snapshot on assignment_question_learning_tree - i.e. built by hand
        // rather than through AssignmentQuestionLearningTree::addToAssignment()
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->root_question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $this->assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_question->id)
            ->first()->id;
        DB::table('assignment_question_learning_tree')->insert([
            'assignment_question_id' => $this->assignment_question_id,
            'learning_tree_id' => $this->learning_tree->id,
            'number_of_successful_paths_for_a_reset' => 1
        ]);
    }

    /** @test */
    public function backfills_snapshot_for_trees_with_no_snapshot_recorded()
    {
        $this->artisan('learning-trees:backfill-question-revisions');

        $row = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $this->assignment_question_id)
            ->first();
        $this->assertNotNull($row->learning_tree);
    }

    /** @test */
    public function sets_root_question_revision_id_when_missing()
    {
        $this->artisan('learning-trees:backfill-question-revisions');

        $latest_revision_id = QuestionRevision::where('question_id', $this->root_question->id)->first()->id;
        $this->assertDatabaseHas('assignment_question', [
            'id' => $this->assignment_question_id,
            'question_revision_id' => $latest_revision_id]);
    }

    /** @test */
    public function does_not_overwrite_the_root_question_revision_id_if_already_set()
    {
        $revision_2 = factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 2]);
        DB::table('assignment_question')
            ->where('id', $this->assignment_question_id)
            ->update(['question_revision_id' => $revision_2->id]);
        // a later, newer revision exists but shouldn't be picked up by the backfill
        factory(QuestionRevision::class)->create(['question_id' => $this->root_question->id, 'revision_number' => 3]);

        $this->artisan('learning-trees:backfill-question-revisions');

        $this->assertDatabaseHas('assignment_question', [
            'id' => $this->assignment_question_id,
            'question_revision_id' => $revision_2->id]);
    }

    /** @test */
    public function does_not_touch_rows_that_already_have_a_snapshot()
    {
        $existing_snapshot = json_encode(['blocks' => ['already here']]);
        DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $this->assignment_question_id)
            ->update(['learning_tree' => $existing_snapshot]);

        $this->artisan('learning-trees:backfill-question-revisions');

        $row = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $this->assignment_question_id)
            ->first();
        $this->assertEquals(json_decode($existing_snapshot, true), json_decode($row->learning_tree, true));
    }

    /** @test */
    public function is_safe_to_run_twice()
    {
        $this->artisan('learning-trees:backfill-question-revisions');
        $first_run_snapshot = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $this->assignment_question_id)
            ->first()->learning_tree;

        $this->artisan('learning-trees:backfill-question-revisions');
        $second_run_snapshot = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $this->assignment_question_id)
            ->first()->learning_tree;

        $this->assertEquals($first_run_snapshot, $second_run_snapshot);
    }

    /** @test */
    public function skips_gracefully_when_the_learning_tree_no_longer_exists()
    {
        // a foreign key constraint (assignment_question_learning_tree_learning_tree_id_foreign)
        // correctly blocks a normal delete here while assignment_question_learning_tree
        // still references this tree - bypass it deliberately to simulate an
        // orphaned reference, since that's the scenario being tested, not
        // whether the constraint itself works.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('learning_trees')->where('id', $this->learning_tree->id)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->artisan('learning-trees:backfill-question-revisions')
            ->assertExitCode(0);
    }
}
