<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignmentSyncQuestion;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\LearningTreeNodeDescription;
use App\LearningTreeNodeSubmission;
use App\LearningTreeReset;
use App\Question;
use App\QuestionRevision;
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
        // Helper::isAdmin() checks Auth::user()->email against admin_emails,
        // and in the testing environment automatically treats me@me.com as
        // an admin regardless of what's in that table - see
        // App\Helpers\Helper::isAdmin().
        $this->admin_user = factory(User::class)->create(['email' => 'me@me.com']);
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

    /** @test */
    public function does_not_reseed_a_non_random_technology_node_after_an_incomplete_attempt()
    {
        // Regression test: LearningTreeNodeSubmissionController::show() used
        // to decide whether to reseed/reset a node's display by calling
        // $question->where('webwork_code', 'LIKE', '%random(%') - which
        // builds an Eloquent query Builder (always truthy as an object)
        // instead of actually checking the question's webwork_code, so the
        // reseed branch fired for *any* technology (h5p included) whenever
        // the assignment had reset_node_after_incorrect_attempt on and the
        // node wasn't yet completed - wiping the student's submission_array
        // and appending "You will be given a similar question to attempt."
        // even though this node has nothing to do with randomized
        // webwork/imathas seeding. h5p is used here specifically because
        // it's unaffected by the *fixed* condition either way (neither
        // disjunct can ever be true for it), so this isolates the reseed
        // flag's effect on the response message from the technology-specific
        // rendering call, which the h5p branch already exercises safely in
        // correctly_applies_reset() above.
        $this->assignment->reset_node_after_incorrect_attempt = 1;
        $this->assignment->save();

        $this->node_question->technology = 'h5p';
        $this->node_question->save();

        $this->learning_tree_node_submission->submission = json_encode(['answer' => 'some response']);
        $this->learning_tree_node_submission->completed = 0;
        // sidestep the unrelated check_for_reset/earned_reset bookkeeping
        // (covered separately by correctly_applies_reset() above) so this
        // test isolates only the reseed condition being fixed here.
        $this->learning_tree_node_submission->check_for_reset = 0;
        $this->learning_tree_node_submission->save();

        $this->actingAs($this->student_user)
            ->getJson("api/learning-tree-node-submission/{$this->learning_tree_node_submission->id}")
            ->assertJson([
                'type' => 'success',
                'message' => 'Your submission was not correct.  ',
            ]);
    }

    // ------------------------------------------------------------------
    // Revision-locking additions below. These build their own
    // assignment_question_learning_tree snapshot on top of the shared
    // setup() above, since setup() inserts assignment_question by hand
    // (pre-dating this feature) and never creates a snapshot row at all.
    // ------------------------------------------------------------------

    /**
     * Adds an assignment_question_learning_tree row with a real snapshot,
     * on top of the assignment_question row setup() already created by hand,
     * so getLockedQuestionRevisionId() has something to find.
     */
    private function addSnapshotWithLockedRevision(): void
    {
        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->root_node_question->id)
            ->first();
        $assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();
        DB::table('assignment_question_learning_tree')->insert([
            'assignment_question_id' => $assignment_question->id,
            'learning_tree_id' => $this->learning_tree->id,
            'learning_tree' => $assignmentQuestionLearningTree->buildLearningTreeSnapshot($this->learning_tree),
            'number_of_successful_paths_for_a_reset' => 1
        ]);
    }

    /** @test */
    public function node_is_served_using_its_locked_revision_not_the_live_question()
    {
        // NOTE: not using `title` here - show() deliberately overwrites it
        // afterward with this node's LearningTreeNodeDescription (created in
        // setup() above, title 'sdfdsf'), which is correct, separate
        // behavior unrelated to revision locking. text_question is a
        // straight passthrough from formatQuestionFromDatabase() that
        // nothing else in show() touches.
        factory(QuestionRevision::class)->create([
            'question_id' => $this->node_question->id,
            'revision_number' => 1,
            'technology' => 'text',
            'text_question' => 'Locked text content'
        ]);
        $this->addSnapshotWithLockedRevision();

        // question changes after the snapshot was recorded
        $this->node_question->text_question = 'Live text changed after locking';
        $this->node_question->save();

        $response = $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}")
            ->content();

        $this->assertEquals('Locked text content', json_decode($response)->node_question->text_question);
    }

    /** @test */
    public function node_falls_back_to_live_question_when_no_revision_was_recorded()
    {
        // no QuestionRevision rows created for node_question at all
        $this->addSnapshotWithLockedRevision();

        $response = $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}")
            ->content();

        $this->assertEquals($this->node_question->text_question, json_decode($response)->node_question->text_question);
    }

    /** @test */
    public function exposition_node_iframe_uses_the_locked_revision_number()
    {
        // Question::formatQuestionFromDatabase() builds non_technology_iframe_src
        // from $question_info['revision_number'] - since the locked revision is
        // merged onto $nodeQuestion before that call, the URL comes out using the
        // locked revision's number directly (confirmed against Question.php).
        //
        // NOTE: getHeaderHtmlIframeSrc() itself (App\Traits\IframeFormatter)
        // isn't a file I've seen - non_technology=1 here is a guess at what
        // makes it actually build a URL rather than returning ''. If this
        // still fails, the trait itself is needed to know what it actually
        // requires.
        $this->node_question->non_technology = 1;
        $this->node_question->save();
        $locked_revision = factory(QuestionRevision::class)->create([
            'question_id' => $this->node_question->id,
            'revision_number' => 3,
            'technology' => 'text',
            'non_technology' => 1
        ]);
        $this->addSnapshotWithLockedRevision();

        $response = $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}")
            ->content();
        $iframe_src = json_decode($response)->node_question->non_technology_iframe_src;

        $this->assertStringEndsWith('/' . $locked_revision->revision_number, $iframe_src);
    }

    /** @test */
    public function viewing_a_past_node_submission_uses_the_locked_revision()
    {
        // Question::getTechnologySrcAndProblemJWT()'s h5p case builds
        // technology_src by parsing the src attribute out of an actual
        // <iframe> tag in technology_iframe (via getIframeSrcFromHtml()) -
        // technology_id itself isn't read directly, confirmed against
        // Question.php.
        $locked_iframe = '<iframe src="https://studio.libretexts.org/h5p/111/embed"></iframe>';
        $live_iframe = '<iframe src="https://studio.libretexts.org/h5p/222/embed"></iframe>';

        $this->node_question->technology = 'h5p';
        $this->node_question->technology_iframe = $locked_iframe;
        $this->node_question->save();
        factory(QuestionRevision::class)->create([
            'question_id' => $this->node_question->id,
            'revision_number' => 1,
            'technology' => 'h5p',
            'technology_iframe' => $locked_iframe
        ]);
        $this->addSnapshotWithLockedRevision();

        // question's iframe changes after the snapshot was recorded
        $this->node_question->technology_iframe = $live_iframe;
        $this->node_question->save();

        $response = $this->actingAs($this->student_user)
            ->getJson("api/learning-tree-node-submission/{$this->learning_tree_node_submission->id}")
            ->content();
        $data = json_decode($response, true);

        $this->assertStringContainsString('111', $data['technology_iframe_src'] ?? '');
        $this->assertStringNotContainsString('222', $data['technology_iframe_src'] ?? '');
    }

    /** @test */
    public function editing_node_metadata_is_allowed_even_when_submissions_exist()
    {
        // learning_tree_node_submission already exists for this tree from
        // setup() - this used to be unconditionally blocked
        $this->actingAs($this->user)
            ->patchJson("/api/learning-trees/nodes/{$this->learning_tree->id}", [
                'question_id' => $this->node_question->id,
                'title' => 'Updated node title',
                'notes' => '',
                'node_description' => 'Some node description',
                'is_root_node' => false,
                'learning_outcome' => null
            ])
            ->assertJson(['type' => 'success']);
    }

    // ------------------------------------------------------------------
    // Snapshot-drift additions below. _commonLearningNodeAccess() used to
    // compare a clicked node against the *live* tree's root_node_question_id
    // and questionIds() - both of which reflect live edits, and can drift
    // from what's actually assigned once an instructor edits a tree without
    // running "Update to Latest Revision". That denied legitimate clicks on
    // nodes that were still part of the assigned snapshot. These tests build
    // a real snapshot via addSnapshotWithLockedRevision() (above), then
    // drift the live tree away from it, and confirm access is still decided
    // by the snapshot.
    // ------------------------------------------------------------------

    /** @test */
    public function can_still_view_a_snapshotted_node_after_the_live_trees_root_question_changes()
    {
        $this->addSnapshotWithLockedRevision();

        // simulate editing the tree's root after it was assigned, without
        // running "Update to Latest Revision" - assignment_question (and
        // the snapshot) still reflect the original root question
        $new_root_question = factory(Question::class)->create();
        $this->learning_tree->root_node_question_id = $new_root_question->id;
        $this->learning_tree->save();

        $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function still_denies_a_question_that_was_never_in_the_snapshot_even_after_the_root_changes()
    {
        $this->addSnapshotWithLockedRevision();

        $new_root_question = factory(Question::class)->create();
        $this->learning_tree->root_node_question_id = $new_root_question->id;
        $this->learning_tree->save();

        $unrelated_question = factory(Question::class)->create();

        $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$unrelated_question->id}")
            ->assertJson(['message' => 'That is not a question node in the learning tree.']);
    }

    /** @test */
    public function falls_back_to_the_live_tree_when_no_snapshot_was_ever_recorded()
    {
        // deliberately no addSnapshotWithLockedRevision() call here -
        // setup() already inserts assignment_question by hand with no
        // assignment_question_learning_tree row at all, same as every other
        // test above this section, confirming the fallback still serves
        // node_question_id correctly with nothing to compare against
        $this->actingAs($this->student_user)
            ->getJson("/api/learning-tree-node-assignment-question/assignment/{$this->assignment->id}/learning-tree/{$this->learning_tree->id}/question/{$this->node_question_id}")
            ->assertJson(['type' => 'success']);
    }

    // ------------------------------------------------------------------
    // LearningTreePolicy::destroy() - admin bypass regression tests.
    // setup() already attaches a learning_tree_node_submission to
    // $this->learning_tree for the tests above; the two tests below that
    // actually delete the tree clear that row first so they're only
    // exercising the permission check, not FK/cascade behavior.
    // ------------------------------------------------------------------

    /** @test */
    public function owner_can_destroy_their_own_learning_tree()
    {
        $this->learning_tree_node_submission->delete();

        $this->actingAs($this->user)
            ->deleteJson("/api/learning-trees/{$this->learning_tree->id}")
            ->assertJson(['type' => 'info', 'message' => 'The Learning Tree has been deleted.']);

        $this->assertDatabaseMissing('learning_trees', ['id' => $this->learning_tree->id]);
    }

    /** @test */
    public function non_owner_non_admin_cannot_destroy_someone_elses_learning_tree()
    {
        $this->actingAs($this->user_2)
            ->deleteJson("/api/learning-trees/{$this->learning_tree->id}")
            ->assertJson(['message' => 'You are not allowed to delete this Learning Tree.']);

        $this->assertDatabaseHas('learning_trees', ['id' => $this->learning_tree->id]);
    }

    /** @test */
    public function admin_can_destroy_a_learning_tree_they_do_not_own()
    {
        // Regression test: LearningTreePolicy::destroy() was missing the
        // "|| Helper::isAdmin()" bypass that update()/updateNode() already
        // had, so an admin could edit any Learning Tree but not delete one
        // they didn't own.
        $this->learning_tree_node_submission->delete();

        $this->actingAs($this->admin_user)
            ->deleteJson("/api/learning-trees/{$this->learning_tree->id}")
            ->assertJson(['type' => 'info', 'message' => 'The Learning Tree has been deleted.']);

        $this->assertDatabaseMissing('learning_trees', ['id' => $this->learning_tree->id]);
    }
}
