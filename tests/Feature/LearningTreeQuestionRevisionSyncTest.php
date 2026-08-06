<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\Course;
use App\LearningTree;
use App\Question;
use App\QuestionRevision;
use App\QuestionRevisionPropagation;
use App\SavedQuestionsFolder;
use App\User;
use App\Traits\Test;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeQuestionRevisionSyncTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();
        $this->my_questions_folder = factory(SavedQuestionsFolder::class)->create([
            'user_id' => $this->user->id,
            'type' => 'my_questions'
        ]);
    }

    private function learningTreeJsonWithRootQuestionId(int $question_id): string
    {
        $tree = json_decode($this->learningTree(), true);
        foreach ($tree['blocks'] as $key => $block) {
            if ($block['id'] === 0) {
                foreach ($block['data'] as $data_key => $entry) {
                    if ($entry['name'] === 'question_id') {
                        $tree['blocks'][$key]['data'][$data_key]['value'] = (string)$question_id;
                    }
                }
            }
        }
        return json_encode($tree);
    }

    private function makeTreeAssignedToAssignment(Assignment $assignment, int $root_question_id = 1): LearningTree
    {
        // Test::learningTree()'s fixture JSON always has its root block
        // (id: 0) pointing at question_id "1" - when a different
        // $root_question_id is requested, the JSON itself has to be mutated
        // to match, not just the root_node_question_id column, or the "same"
        // fixture tree ends up referencing question_id 1 regardless of what
        // was asked for.
        $learning_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'root_node_question_id' => $root_question_id,
            'learning_tree' => $this->learningTreeJsonWithRootQuestionId($root_question_id)]);
        if (!Question::find($root_question_id)) {
            factory(Question::class)->create(['id' => $root_question_id]);
        }
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$assignment->id}/learning-trees/{$learning_tree->id}");
        return $learning_tree;
    }

    private function snapshotFor(LearningTree $learningTree, int $assignment_id): object
    {
        return $this->assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($assignment_id, $learningTree->id);
    }

    private function revisionIdForBlock(object $assignment_question_learning_tree, int $block_id): ?int
    {
        $blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'];
        $block = collect($blocks)->firstWhere('id', $block_id);
        $entry = collect($block['data'])->firstWhere('name', 'question_revision_id');
        return $entry['value'] !== '' ? (int)$entry['value'] : null;
    }

    // ------------------------------------------------------------------
    // AssignmentQuestionLearningTree::propagateQuestionRevisionToLearningTreeSnapshots()
    // ------------------------------------------------------------------

    /** @test */
    public function propagate_patches_the_root_nodes_revision_in_every_assignment_using_the_tree()
    {
        $assignment_1 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree_1 = $this->makeTreeAssignedToAssignment($assignment_1);
        // a second, independent tree with the same root question_id, assigned
        // to a different assignment
        $learning_tree_2 = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'root_node_question_id' => 1,
            'learning_tree' => $this->learningTree()]);
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$assignment_2->id}/learning-trees/{$learning_tree_2->id}");

        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $this->assignmentQuestionLearningTree->propagateQuestionRevisionToLearningTreeSnapshots(1, $new_revision->id);

        $this->assertEquals($new_revision->id, $this->revisionIdForBlock($this->snapshotFor($learning_tree_1, $assignment_1->id), 0));
        $this->assertEquals($new_revision->id, $this->revisionIdForBlock($this->snapshotFor($learning_tree_2, $assignment_2->id), 0));
    }

    /** @test */
    public function propagate_patches_a_non_root_nodes_revision_too()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        // block id 2 in the fixture tree points to question_id 102439
        $node_question = factory(Question::class)->create(['id' => 102439]);
        $new_revision = factory(QuestionRevision::class)->create(['question_id' => $node_question->id, 'revision_number' => 1]);

        $this->assignmentQuestionLearningTree->propagateQuestionRevisionToLearningTreeSnapshots($node_question->id, $new_revision->id);

        $this->assertEquals($new_revision->id, $this->revisionIdForBlock($this->snapshotFor($learning_tree, $assignment->id), 2));
    }

    /** @test */
    public function propagate_does_not_touch_a_snapshot_that_doesnt_reference_the_question()
    {
        $assignment_1 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->makeTreeAssignedToAssignment($assignment_1, 1);
        $unrelated_tree = $this->makeTreeAssignedToAssignment($assignment_2, 500);
        $unrelated_snapshot_before = $this->snapshotFor($unrelated_tree, $assignment_2->id)->learning_tree;

        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $this->assignmentQuestionLearningTree->propagateQuestionRevisionToLearningTreeSnapshots(1, $new_revision->id);

        $unrelated_snapshot_after = $this->snapshotFor($unrelated_tree, $assignment_2->id)->learning_tree;
        $this->assertEquals(json_decode($unrelated_snapshot_before, true), json_decode($unrelated_snapshot_after, true));
    }

    /** @test */
    public function propagate_does_not_wipe_any_submissions()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $student = factory(User::class)->create(['role' => 3]);
        DB::table('learning_tree_resets')->insert([
            'user_id' => $student->id, 'assignment_id' => $assignment->id,
            'learning_tree_id' => $learning_tree->id, 'number_resets_available' => 1]);

        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $this->assignmentQuestionLearningTree->propagateQuestionRevisionToLearningTreeSnapshots(1, $new_revision->id);

        $this->assertDatabaseHas('learning_tree_resets', [
            'user_id' => $student->id, 'assignment_id' => $assignment->id,
            'learning_tree_id' => $learning_tree->id, 'number_resets_available' => 1]);
    }

    /** @test */
    public function propagate_is_a_no_op_when_the_question_id_matches_no_block()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $snapshot_before = $this->snapshotFor($learning_tree, $assignment->id)->learning_tree;

        $unrelated_question = factory(Question::class)->create();
        $new_revision = factory(QuestionRevision::class)->create(['question_id' => $unrelated_question->id, 'revision_number' => 1]);
        $this->assignmentQuestionLearningTree->propagateQuestionRevisionToLearningTreeSnapshots($unrelated_question->id, $new_revision->id);

        $snapshot_after = $this->snapshotFor($learning_tree, $assignment->id)->learning_tree;
        $this->assertEquals(json_decode($snapshot_before, true), json_decode($snapshot_after, true));
    }

    // ------------------------------------------------------------------
    // AssignmentQuestionLearningTree::patchNodeRevisionInAssignmentSnapshot()
    // ------------------------------------------------------------------

    /** @test */
    public function patch_updates_the_snapshot_for_the_root_node_in_this_specific_assignment()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);

        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $this->assignmentQuestionLearningTree->patchNodeRevisionInAssignmentSnapshot($assignment->id, 1, $new_revision->id);

        $this->assertEquals($new_revision->id, $this->revisionIdForBlock($this->snapshotFor($learning_tree, $assignment->id), 0));
    }

    /** @test */
    public function patch_does_not_touch_the_snapshot_for_a_different_assignment_using_the_same_question()
    {
        $assignment_1 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree_1 = $this->makeTreeAssignedToAssignment($assignment_1, 1);
        $learning_tree_2 = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            'root_node_question_id' => 1,
            'learning_tree' => $this->learningTree()]);
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$assignment_2->id}/learning-trees/{$learning_tree_2->id}");
        $snapshot_2_before = $this->snapshotFor($learning_tree_2, $assignment_2->id)->learning_tree;

        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $this->assignmentQuestionLearningTree->patchNodeRevisionInAssignmentSnapshot($assignment_1->id, 1, $new_revision->id);

        $this->assertEquals($new_revision->id, $this->revisionIdForBlock($this->snapshotFor($learning_tree_1, $assignment_1->id), 0));
        $snapshot_2_after = $this->snapshotFor($learning_tree_2, $assignment_2->id)->learning_tree;
        $this->assertEquals(json_decode($snapshot_2_before, true), json_decode($snapshot_2_after, true));
    }

    /** @test */
    public function patch_is_a_no_op_when_the_question_is_not_this_assignments_tree_root()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $snapshot_before = $this->snapshotFor($learning_tree, $assignment->id)->learning_tree;

        $unrelated_question = factory(Question::class)->create();
        $new_revision = factory(QuestionRevision::class)->create(['question_id' => $unrelated_question->id, 'revision_number' => 1]);
        // unrelated_question has no assignment_question row in this assignment at all
        $this->assignmentQuestionLearningTree->patchNodeRevisionInAssignmentSnapshot($assignment->id, $unrelated_question->id, $new_revision->id);

        $snapshot_after = $this->snapshotFor($learning_tree, $assignment->id)->learning_tree;
        $this->assertEquals(json_decode($snapshot_before, true), json_decode($snapshot_after, true));
    }

    /** @test */
    public function patch_is_a_no_op_when_the_assignment_question_has_no_learning_tree_row_at_all()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $plain_question = factory(Question::class)->create();
        DB::table('assignment_question')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $plain_question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $new_revision = factory(QuestionRevision::class)->create(['question_id' => $plain_question->id, 'revision_number' => 1]);

        // just confirming this doesn't throw
        $this->assignmentQuestionLearningTree->patchNodeRevisionInAssignmentSnapshot($assignment->id, $plain_question->id, $new_revision->id);
        $this->assertDatabaseMissing('assignment_question_learning_tree', ['learning_tree_id' => null]);
    }

    // ------------------------------------------------------------------
    // Full wiring, through QuestionController@update
    // ------------------------------------------------------------------

    /**
     * Builds the payload from $root_question->toArray() (matching
     * QuestionRevisionsTest::_getQuestionInfo()'s established pattern),
     * overriding only the meta fields actually being changed, so every
     * other field - not just Question::nonMetaProperties()'s 15 - echoes
     * back exactly what's already on the question. That avoids any spurious
     * "differing properties" rejection from revision_action=propagate's
     * nonMetaPropertiesDiffer() gate, whatever factory(Question::class)
     * happens to default those fields to.
     *
     * @test
     */
    public function propagating_a_root_nodes_question_updates_the_tree_snapshot_via_the_real_endpoint()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $root_question = Question::find(1);
        $root_question->technology = 'webwork';
        $root_question->technology_id = 'some file path';
        $root_question->author = 'some author';
        $root_question->license = 'publicdomain';
        $root_question->question_editor_user_id = $this->user->id;
        $root_question->question_type = 'assessment';
        $root_question->save();

        $payload = $root_question->toArray();
        $payload['public'] = 1;
        $payload['title'] = 'updated title - topical only';
        $payload['tags'] = [];
        $payload['folder_id'] = $this->my_questions_folder->id;
        $payload['revision_action'] = 'propagate';
        $payload['reason_for_edit'] = 'typo fix only';

        $this->actingAs($this->user)->patchJson("/api/questions/{$root_question->id}", $payload)
            ->assertJson(['type' => 'success']);

        $new_revision_id = QuestionRevision::where('question_id', $root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $this->assertEquals($new_revision_id, $this->revisionIdForBlock($this->snapshotFor($learning_tree, $assignment->id), 0));
        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $assignment->id,
            'question_id' => $root_question->id,
            'question_revision_id' => $new_revision_id]);
    }

    /**
     * Same ->toArray()-based payload approach as
     * propagating_a_root_nodes_question_updates_the_tree_snapshot_via_the_real_endpoint().
     *
     * @test
     */
    public function propagating_a_non_root_nodes_question_updates_the_tree_snapshot_via_the_real_endpoint()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        // block id 2 points to question_id 102439
        $node_question = factory(Question::class)->create(['id' => 102439]);
        $node_question->technology = 'webwork';
        $node_question->technology_id = 'some file path';
        $node_question->author = 'some author';
        $node_question->license = 'publicdomain';
        $node_question->question_editor_user_id = $this->user->id;
        $node_question->question_type = 'assessment';
        $node_question->save();
        // factory(Question::class)->create() only populates the attributes it
        // was explicitly given, not the DB's own column defaults for
        // everything else (auto_attribution, non_technology, etc.) - those
        // exist in the real row (MySQL applies them at INSERT time) but
        // never get read back into this in-memory object without a refresh.
        // Without this, $node_question->toArray() below would build a
        // payload missing those defaults, while the server re-fetches the
        // question fresh and sees the real values - a spurious mismatch
        // under revision_action=propagate's nonMetaPropertiesDiffer() check.
        $node_question = $node_question->fresh();

        $payload = $node_question->toArray();
        $payload['public'] = 1;
        $payload['title'] = 'updated title - topical only';
        $payload['tags'] = [];
        $payload['folder_id'] = $this->my_questions_folder->id;
        $payload['revision_action'] = 'propagate';
        $payload['reason_for_edit'] = 'typo fix only';

        $this->actingAs($this->user)->patchJson("/api/questions/{$node_question->id}", $payload)
            ->assertJson(['type' => 'success']);

        $new_revision_id = QuestionRevision::where('question_id', $node_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $this->assertEquals($new_revision_id, $this->revisionIdForBlock($this->snapshotFor($learning_tree, $assignment->id), 2));
    }

    /**
     * Course-level auto-update: no real students enrolled in this course at
     * all, so the assignment qualifies for
     * $assignment_ids_from_courses_with_auto_update_question_revision_without_students
     * regardless of open/closed status or the editor's own
     * automatically_update_revision choice.
     *
     * Unlike propagate, notify doesn't call nonMetaPropertiesDiffer() at
     * all, so the ->toArray()-based payload trick used in the propagate
     * tests above isn't needed here - StoreQuestionRequest's own validation
     * (folder_id, technology_id, etc.) still applies though.
     *
     * @test
     */
    public function notify_auto_update_at_course_level_keeps_the_tree_snapshot_in_sync()
    {
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $root_question = Question::find(1);
        $root_question->technology = 'webwork';
        $root_question->technology_id = 'some file path';
        $root_question->author = 'some author';
        $root_question->license = 'publicdomain';
        $root_question->question_editor_user_id = $this->user->id;
        $root_question->question_type = 'assessment';
        $root_question->save();

        $payload = [
            'id' => $root_question->id,
            'question_type' => 'assessment',
            'public' => 1,
            'title' => 'some title',
            'technology' => $root_question->technology,
            'technology_id' => $root_question->technology_id,
            'author' => $root_question->author,
            'license' => $root_question->license,
            'open_ended_submission_type' => '0',
            'tags' => [],
            'folder_id' => $this->my_questions_folder->id,
            // a substantive change - notify, not propagate
            'text_question' => 'a genuinely different question body',
            'revision_action' => 'notify',
            'reason_for_edit' => 'substantive fix',
            'automatically_update_revision' => false
        ];

        $this->actingAs($this->user)->patchJson("/api/questions/{$root_question->id}", $payload)
            ->assertJson(['type' => 'success']);

        $new_revision_id = QuestionRevision::where('question_id', $root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $assignment->id,
            'question_id' => $root_question->id,
            'question_revision_id' => $new_revision_id]);
        $this->assertEquals($new_revision_id, $this->revisionIdForBlock($this->snapshotFor($learning_tree, $assignment->id), 0));
    }

    /** @test */
    public function propagate_reports_back_only_the_snapshot_ids_it_actually_touched()
    {
        $assignment_1 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree_1 = $this->makeTreeAssignedToAssignment($assignment_1);
        // a tree that doesn't reference question_id 1 at all - shouldn't show up
        $this->makeTreeAssignedToAssignment($assignment_2, 102685);
        $new_revision = factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 2]);
        $expected_row_id = $this->snapshotFor($learning_tree_1, $assignment_1->id)->id;

        $affected = $this->assignmentQuestionLearningTree
            ->propagateQuestionRevisionToLearningTreeSnapshots(1, $new_revision->id);

        $this->assertEquals([$expected_row_id], $affected);
    }

    // ------------------------------------------------------------------
    // QuestionRevisionPropagation - the "who propagated this, and what did
    // it reach" audit trail, written by QuestionController@update
    // ------------------------------------------------------------------

    /** @test */
    public function propagating_a_root_nodes_question_logs_the_learning_tree_snapshot_row_it_reached()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);
        $root_question = Question::find(1);
        $root_question->technology = 'webwork';
        $root_question->technology_id = 'some file path';
        $root_question->author = 'some author';
        $root_question->license = 'publicdomain';
        $root_question->question_editor_user_id = $this->user->id;
        $root_question->question_type = 'assessment';
        $root_question->save();

        $payload = $root_question->toArray();
        $payload['public'] = 1;
        $payload['title'] = 'updated title - topical only';
        $payload['tags'] = [];
        $payload['folder_id'] = $this->my_questions_folder->id;
        $payload['revision_action'] = 'propagate';
        $payload['reason_for_edit'] = 'typo fix only';

        $this->actingAs($this->user)->patchJson("/api/questions/{$root_question->id}", $payload)
            ->assertJson(['type' => 'success']);

        $new_revision_id = QuestionRevision::where('question_id', $root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $expected_snapshot_row_id = $this->snapshotFor($learning_tree, $assignment->id)->id;
        // the root node's question also has an ordinary assignment_question
        // row (that's what assignment_question_learning_tree.assignment_question_id
        // points at), so propagating it legitimately touches both kinds of row
        $expected_assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $root_question->id)
            ->value('id');

        $this->assertDatabaseHas('question_revision_propagations', [
            'question_revision_id' => $new_revision_id,
            'user_id' => $this->user->id
        ]);
        $log = QuestionRevisionPropagation::where('question_revision_id', $new_revision_id)->first();
        $this->assertEquals([$expected_assignment_question_id], $log->assignment_question_ids);
        $this->assertEquals([$expected_snapshot_row_id], $log->assignment_question_learning_tree_ids);
    }

    /** @test */
    public function propagating_a_question_used_directly_and_in_a_tree_logs_both_kinds_of_row()
    {
        // question_id 1 as an ordinary (non-tree) assignment_question in one
        // assignment, and as a tree's root node in another
        $direct_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $root_question = factory(Question::class)->create(['id' => 1]);
        $direct_assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $direct_assignment->id,
            'question_id' => $root_question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $tree_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($tree_assignment);

        $root_question->technology = 'webwork';
        $root_question->technology_id = 'some file path';
        $root_question->author = 'some author';
        $root_question->license = 'publicdomain';
        $root_question->question_editor_user_id = $this->user->id;
        $root_question->question_type = 'assessment';
        $root_question->save();
        // factory(Question::class)->create() only populates the attributes it
        // was explicitly given, not the DB's own column defaults for
        // everything else - without this, $root_question->toArray() below
        // would build a payload missing those defaults, while the server
        // re-fetches the question fresh and sees the real values - a
        // spurious mismatch under revision_action=propagate's
        // nonMetaPropertiesDiffer() check.
        $root_question = $root_question->fresh();

        $payload = $root_question->toArray();
        $payload['public'] = 1;
        $payload['title'] = 'updated title - topical only';
        $payload['tags'] = [];
        $payload['folder_id'] = $this->my_questions_folder->id;
        $payload['revision_action'] = 'propagate';
        $payload['reason_for_edit'] = 'typo fix only';

        $this->actingAs($this->user)->patchJson("/api/questions/{$root_question->id}", $payload)
            ->assertJson(['type' => 'success']);

        $new_revision_id = QuestionRevision::where('question_id', $root_question->id)
            ->orderBy('revision_number', 'desc')->first()->id;
        $expected_snapshot_row_id = $this->snapshotFor($learning_tree, $tree_assignment->id)->id;
        // the tree's root node also has its own ordinary assignment_question
        // row (that's what assignment_question_learning_tree.assignment_question_id
        // points at), so both assignments' rows should show up here
        $tree_assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $tree_assignment->id)
            ->where('question_id', $root_question->id)
            ->value('id');
        $log = QuestionRevisionPropagation::where('question_revision_id', $new_revision_id)->first();

        $assignment_question_ids = $log->assignment_question_ids;
        sort($assignment_question_ids);
        $expected_assignment_question_ids = [$direct_assignment_question_id, $tree_assignment_question_id];
        sort($expected_assignment_question_ids);
        $this->assertEquals($expected_assignment_question_ids, $assignment_question_ids);
        $this->assertEquals([$expected_snapshot_row_id], $log->assignment_question_learning_tree_ids);
    }

    // ------------------------------------------------------------------
    // html/blockarr preservation regression coverage. patchQuestionRevisionInSnapshotRow()
    // - shared by propagate and patchNodeRevisionInAssignmentSnapshot() -
    // used to re-encode the snapshot as {"blocks": [...]} only, silently
    // stripping html/blockarr back out of a snapshot that had them (from
    // buildLearningTreeSnapshot()). flowy.import() renders purely from
    // html/blockarr and never reads blocks at all, so this made the tree
    // un-renderable ("undefined" on canvas, then a JS crash) the next time
    // anyone opened it inside the affected assignment, even though nothing
    // about the tree's actual content was wrong.
    // ------------------------------------------------------------------

    /** @test */
    public function propagate_preserves_html_and_blockarr_in_the_patched_snapshot()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);

        // sanity check: the pre-patch snapshot (built by the real
        // addToAssignment() call in makeTreeAssignedToAssignment()) already
        // has html/blockarr - if this fails, the bug is back in
        // buildLearningTreeSnapshot(), not in propagate's patch path
        $before = json_decode($this->snapshotFor($learning_tree, $assignment->id)->learning_tree, true);
        $this->assertArrayHasKey('html', $before);
        $this->assertArrayHasKey('blockarr', $before);

        $this->assignmentQuestionLearningTree
            ->propagateQuestionRevisionToLearningTreeSnapshots(102438, 999999);

        $after = json_decode($this->snapshotFor($learning_tree, $assignment->id)->learning_tree, true);
        $this->assertArrayHasKey('html', $after);
        $this->assertNotEmpty($after['html']);
        $this->assertArrayHasKey('blockarr', $after);
        $this->assertNotEmpty($after['blockarr']);
    }

    /** @test */
    public function patch_preserves_html_and_blockarr_in_the_patched_snapshot()
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $learning_tree = $this->makeTreeAssignedToAssignment($assignment);

        $this->assignmentQuestionLearningTree
            ->patchNodeRevisionInAssignmentSnapshot($assignment->id, 1, 999999);

        $after = json_decode($this->snapshotFor($learning_tree, $assignment->id)->learning_tree, true);
        $this->assertArrayHasKey('html', $after);
        $this->assertNotEmpty($after['html']);
        $this->assertArrayHasKey('blockarr', $after);
        $this->assertNotEmpty($after['blockarr']);
    }
}
