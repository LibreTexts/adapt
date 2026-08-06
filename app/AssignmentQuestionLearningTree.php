<?php

namespace App;

use App\Helpers\Helper;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignmentQuestionLearningTree extends Model
{
    /**
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     */
    public function addToAssignment(Assignment             $assignment,
                                    LearningTree           $learningTree,
                                    AssignmentSyncQuestion $assignmentSyncQuestion,
                                    BetaCourseApproval     $betaCourseApproval): array
    {
        $response['type'] = 'error';
        try {
            $question_id = $learningTree->root_node_question_id;
            $in_assignment = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question_id)
                ->get()
                ->isNotEmpty();
            if ($in_assignment) {
                $response['message'] = 'A Learning Tree with the same root node question already exists in the assignment.';
                return $response;
            }

            // EK: root node question needs question_revision_id set at add-time,
            // exactly like a normal question added to an assignment
            // (AssignmentSyncQuestion::store()/addQuestionToAssignmentByQuestionId()),
            // so that the root node is served/graded against a locked revision
            // rather than whatever the question currently looks like.
            $root_question = Question::find($question_id);
            $question_revision_id = $root_question ? $root_question->latestQuestionRevision('id') : null;

            DB::table('assignment_question')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question_id,
                'question_revision_id' => $question_revision_id,
                'order' => $assignmentSyncQuestion->getNewQuestionOrder($assignment),
                'points' => $assignment->points_per_question === 'number of points'
                    ? $assignment->default_points_per_question
                    : 0,
                'weight' => $assignment->points_per_question === 'number of points' ? null : 1,
                'open_ended_submission_type' => 0
            ]);
            $assignment_question_id = DB::getPdo()->lastInsertId();
            DB::table('assignment_question_learning_tree')->insert([
                'assignment_question_id' => $assignment_question_id,
                'learning_tree_id' => $learningTree->id,
                // EK: snapshot of the tree's structure + each node's latest
                // question_revision_id at add-time - see buildLearningTreeSnapshot().
                // This is what learningTreeNeedsUpdate() later compares the live
                // tree against to decide whether to alert the instructor.
                'learning_tree' => $this->buildLearningTreeSnapshot($learningTree),
                'number_of_successful_paths_for_a_reset' => $assignment->number_of_successful_paths_for_a_reset,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment);
            $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question_id, 'add', $learningTree->id);

            $response['type'] = 'success';
            $response['message'] = 'The Learning Tree has been added to the assignment.';
        } catch (Exception $e) {
            $response['message'] = "There was an error adding the Learning Tree to the assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function getAssignmentQuestionLearningTreeByRootNodeQuestionId(int $assignment_id, int $root_node_question_id)
    {
        return DB::table('assignment_question_learning_tree')
            ->join('assignment_question', 'assignment_question_learning_tree.assignment_question_id', '=', 'assignment_question.id')
            ->select('assignment_question_learning_tree.*')
            ->where('assignment_question.assignment_id', $assignment_id)
            ->where('assignment_question.question_id', $root_node_question_id)
            ->first();
    }

    /**
     * @throws Exception
     */
    public function getAssignmentQuestionLearningTreeByLearningTreeId(int $assignment_id, int $learning_tree_id)
    {
        $assignment_question_learning_tree = DB::table('assignment_question')
            ->join('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
            ->select('assignment_question_learning_tree.*')
            ->where('assignment_question.assignment_id', $assignment_id)
            ->where('assignment_question_learning_tree.learning_tree_id', $learning_tree_id)
            ->first();
        if (!$assignment_question_learning_tree) {
            throw new Exception ("Assignment question with assignment id $assignment_id and learning tree id $learning_tree_id does not exist.");
        }
        return $assignment_question_learning_tree;
    }

    /**
     * Builds a JSON snapshot of a Learning Tree's node structure, augmented
     * with each node's *current* latest question_revision_id, for storage on
     * assignment_question_learning_tree.learning_tree.
     *
     * Only `blocks` is stored (not `html`/`blockarr`) since `blocks` already
     * carries everything needed to determine the node set, parent/child
     * relationships, and each node's question_id - the same shape
     * LearningTree::questionIds()/nodeParents()/etc. already rely on.
     *
     * Called both when a tree is first added to an assignment, and again
     * (as a full resync) from updateToLatestRevision().
     *
     * @param LearningTree $learningTree
     * @return string
     */
    public function buildLearningTreeSnapshot(LearningTree $learningTree): string
    {
        $blocks = json_decode($learningTree->learning_tree, true)['blocks'] ?? [];

        $latest_revision_ids_by_question_id = [];
        foreach ($blocks as $block) {
            $question_id = $this->questionIdFromBlockData($block['data']);
            if ($question_id !== null && !array_key_exists($question_id, $latest_revision_ids_by_question_id)) {
                $question = Question::find($question_id);
                $latest_revision_ids_by_question_id[$question_id] = $question
                    ? $question->latestQuestionRevision('id')
                    : null;
            }
        }

        foreach ($blocks as $key => $block) {
            $question_id = $this->questionIdFromBlockData($block['data']);
            // strip out any question_revision_id entry left over from a prior
            // snapshot before adding the current one back in
            $blocks[$key]['data'] = array_values(array_filter($block['data'], function ($entry) {
                return $entry['name'] !== 'question_revision_id';
            }));
            $blocks[$key]['data'][] = [
                'name' => 'question_revision_id',
                'value' => (string)($latest_revision_ids_by_question_id[$question_id] ?? '')
            ];
        }

        return json_encode(['blocks' => $blocks]);
    }

    /**
     * Compares the tree snapshot stored at add-time (or last "update to
     * latest") against the tree's live/current definition to decide whether
     * the instructor should be alerted that the assigned tree is out of date.
     *
     * Flags true if either:
     *  - the node set, parent/child relationships, or any node's question_id
     *    no longer match the snapshot (the tree's structure changed), or
     *  - any node's snapshotted question_revision_id is older than that
     *    question's current latest revision (a node's content changed).
     *
     * A question with no question_revisions row at all is never flagged on
     * revision grounds - same fallback as
     * AssignmentSyncQuestion::getAssignmentQuestionsConsideringRevisions().
     *
     * @param object $assignment_question_learning_tree row from assignment_question_learning_tree
     * @param LearningTree $learningTree
     * @return bool
     */
    public function learningTreeNeedsUpdate(object $assignment_question_learning_tree, LearningTree $learningTree): bool
    {
        if (!$assignment_question_learning_tree->learning_tree) {
            //tree was added before this feature existed - nothing to compare against, so treat as needing a resync
            return true;
        }

        $snapshot_blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'] ?? [];
        $live_blocks = json_decode($learningTree->learning_tree, true)['blocks'] ?? [];

        $snapshot_by_block_id = [];
        foreach ($snapshot_blocks as $block) {
            $snapshot_by_block_id[(int)$block['id']] = $this->extractBlockInfo($block);
        }

        $live_by_block_id = [];
        foreach ($live_blocks as $block) {
            $live_by_block_id[(int)$block['id']] = $this->extractBlockInfo($block);
        }

        //structure changed: different number of nodes, different parents, or a different question per node
        if (count($live_by_block_id) !== count($snapshot_by_block_id)) {
            return true;
        }
        foreach ($live_by_block_id as $block_id => $live_block) {
            if (!isset($snapshot_by_block_id[$block_id])
                || $snapshot_by_block_id[$block_id]['parent'] !== $live_block['parent']
                || $snapshot_by_block_id[$block_id]['question_id'] !== $live_block['question_id']) {
                return true;
            }
        }

        //revision changed: any node's snapshotted revision is behind that question's current latest revision
        $latest_revision_ids_by_question_id = [];
        foreach ($snapshot_by_block_id as $snapshot_block) {
            $question_id = $snapshot_block['question_id'];
            if (!$question_id) {
                continue;
            }
            if (!array_key_exists($question_id, $latest_revision_ids_by_question_id)) {
                $question = Question::find($question_id);
                $latest_revision_ids_by_question_id[$question_id] = $question ? $question->latestQuestionRevision('id') : null;
            }
            $latest_revision_id = $latest_revision_ids_by_question_id[$question_id];
            if ($latest_revision_id && $latest_revision_id !== $snapshot_block['question_revision_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Looks up the locked question_revision_id for a given node (question_id)
     * from a tree's stored snapshot, for use when serving that node's
     * content to a student. Returns null if there's no snapshot, no matching
     * node, or no revision was recorded for that node (in which case callers
     * should fall back to the live Question record).
     *
     * @param object|null $assignment_question_learning_tree row from assignment_question_learning_tree
     * @param int $question_id
     * @return int|null
     */
    public function getLockedQuestionRevisionId(?object $assignment_question_learning_tree, int $question_id): ?int
    {
        if (!$assignment_question_learning_tree || !$assignment_question_learning_tree->learning_tree) {
            return null;
        }
        $blocks = json_decode($assignment_question_learning_tree->learning_tree, true)['blocks'] ?? [];
        foreach ($blocks as $block) {
            $info = $this->extractBlockInfo($block);
            if ($info['question_id'] === $question_id) {
                return $info['question_revision_id'];
            }
        }
        return null;
    }

    /**
     * Whether updating this Learning Tree to its latest revision could put
     * real (non-fake) student work at risk - i.e. whether the "your
     * students' submissions will be removed" warning/confirmation is
     * actually necessary, or whether the update is a safe no-op as far as
     * students are concerned.
     *
     * True if either:
     *  - the assignment is currently open (a student could submit between
     *    now and when the instructor confirms, so the risk exists even if
     *    nothing has been submitted yet), or
     *  - there's already at least one real student submission tied to the
     *    tree - the root node (an ordinary assignment_question submission)
     *    or any other node (a learning_tree_node_submissions row).
     *
     * Deliberately ignores fake students on both counts, same as every other
     * "does this affect real students" check in this feature (see
     * AssignmentSyncQuestion::questionHasSomeTypeOfRealStudentSubmission()).
     *
     * Used both to decide what the frontend shows (a scary checkbox vs. a
     * plain confirm) and, authoritatively, by
     * AssignmentSyncQuestionController::updateLearningTreeToLatestRevision()
     * itself to decide whether to actually require that confirmation - never
     * trust the frontend's copy of this alone, since it can go stale between
     * page load and the button click.
     *
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return bool
     */
    public function updateRisksRealStudentSubmissions(Assignment             $assignment,
                                                       LearningTree           $learningTree,
                                                       AssignmentSyncQuestion $assignmentSyncQuestion): bool
    {
        $is_open = !empty($assignment->getOpenAssignmentIdsFromSubsetOfAssignmentIds([$assignment->id]));
        if ($is_open) {
            return true;
        }

        try {
            $assignment_question_learning_tree = $this
                ->getAssignmentQuestionLearningTreeByLearningTreeId($assignment->id, $learningTree->id);
        } catch (Exception $e) {
            //tree isn't actually attached to this assignment - nothing to risk
            return false;
        }

        $assignment_question = DB::table('assignment_question')
            ->where('id', $assignment_question_learning_tree->assignment_question_id)
            ->first();
        $current_root_question = $assignment_question ? Question::find($assignment_question->question_id) : null;
        if ($current_root_question
            && $assignmentSyncQuestion->questionHasSomeTypeOfRealStudentSubmission($assignment, $current_root_question)) {
            return true;
        }

        return DB::table('learning_tree_node_submissions')
            ->join('users', 'learning_tree_node_submissions.user_id', '=', 'users.id')
            ->where('learning_tree_node_submissions.assignment_id', $assignment->id)
            ->where('learning_tree_node_submissions.learning_tree_id', $learningTree->id)
            ->where('users.fake_student', 0)
            ->exists();
    }

    /**
     * Resyncs an assigned Learning Tree to its live/current definition: stores
     * a fresh snapshot (structure + latest revision id per node), updates the
     * root node's assignment_question.question_revision_id, and removes all
     * student work tied to the tree.
     *
     * Mirrors two existing patterns rather than inventing a new one:
     *  - the root node is handled exactly like
     *    AssignmentSyncQuestionController::updateToLatestRevision() handles a
     *    normal question (score recompute + removeAllStudentSubmissionTypesByAssignmentAndQuestion()).
     *  - every other node is cleared using the same
     *    learning_tree_node_seeds/learning_tree_resets/learning_tree_node_submissions
     *    cleanup already used in AssignmentSyncQuestionController::destroy()
     *    when a learning tree question is removed from an assignment.
     *
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array{student_emails_associated_with_submissions: array}
     * @throws Exception
     */
    public function updateToLatestRevision(Assignment             $assignment,
                                           LearningTree           $learningTree,
                                           AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $new_root_question = Question::find($learningTree->root_node_question_id);
        if (!$new_root_question) {
            throw new Exception("The root node's question no longer exists.");
        }

        //throws if there's no matching row - that would mean the tree isn't actually in this assignment
        $assignment_question_learning_tree = $this->getAssignmentQuestionLearningTreeByLearningTreeId($assignment->id, $learningTree->id);

        // EK: look the assignment_question row up by id (via the tree relationship),
        // not by matching question_id against the *new* root question - the root
        // node's question_id can itself have changed (an instructor swapped which
        // question the root node points to entirely, not just picked up a new
        // revision of the same question), in which case assignment_question.question_id
        // still holds the *old* root question's id at this point.
        $assignment_question = DB::table('assignment_question')
            ->where('id', $assignment_question_learning_tree->assignment_question_id)
            ->first();
        if (!$assignment_question) {
            throw new Exception("No assignment_question row found for this Learning Tree.");
        }
        $old_root_question = Question::find($assignment_question->question_id);

        //root node: same handling as a normal question's "update to latest revision" -
        //cleanup runs against whichever question the assignment_question row is
        //currently pointing at (the old root), since that's what any existing
        //submissions/scores were actually recorded under.
        $student_emails_associated_with_submissions = [];
        if ($old_root_question) {
            if ($assignmentSyncQuestion->questionHasSomeTypeOfRealStudentSubmission($assignment, $old_root_question)) {
                $student_emails_associated_with_submissions = $assignmentSyncQuestion->studentEmailsAssociatedWithSomeTypeOfStudentSubmission($assignment, $old_root_question);
            }
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $old_root_question);
            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $old_root_question->id);
        }

        DB::table('assignment_question')
            ->where('id', $assignment_question->id)
            ->update([
                'question_id' => $new_root_question->id,
                'question_revision_id' => $new_root_question->latestQuestionRevision('id')
            ]);

        //every other node: clear all in-progress/completed node work for the whole tree.
        //NOTE: also clears learning_tree_node_seeds so students get fresh random seeds
        //under the new revision rather than a stale seed paired with new content.
        $learning_tree_tables = ['learning_tree_node_seeds', 'learning_tree_resets', 'learning_tree_node_submissions'];
        foreach ($learning_tree_tables as $learning_tree_table) {
            DB::table($learning_tree_table)
                ->where('assignment_id', $assignment->id)
                ->where('learning_tree_id', $learningTree->id)
                ->delete();
        }

        //full resync: re-snapshot the tree exactly as it looks right now, with fresh revisions for every node
        DB::table('assignment_question_learning_tree')
            ->where('id', $assignment_question_learning_tree->id)
            ->update([
                'learning_tree' => $this->buildLearningTreeSnapshot($learningTree),
                'updated_at' => Carbon::now()
            ]);

        return ['student_emails_associated_with_submissions' => $student_emails_associated_with_submissions];
    }

    /**
     * When a question is edited with the "propagate" revision action
     * (QuestionController::store()), this patches that question's locked
     * revision inside every Learning Tree snapshot that references it as a
     * node - root or not - leaving everything else about each snapshot
     * (structure, other nodes) untouched.
     *
     * Deliberately does NOT wipe any submissions, unlike updateToLatestRevision()
     * - propagate is reserved for safe/topical changes
     * (Question::nonMetaPropertiesDiffer() must be false), so there's nothing
     * to protect students' existing work from.
     *
     * A question used as a tree's root node also has its own assignment_question
     * row, which QuestionController::store()'s propagate case already updates
     * separately - this method runs alongside that, for the same
     * $new_question_revision_id, so the two stay in sync without needing to
     * know about each other.
     *
     * This is deliberately global (every assignment using the question, not
     * just one) because propagate itself is global - it updates every
     * assignment_question row for the question unconditionally, with no
     * per-assignment distinction. Compare to patchNodeRevisionInAssignmentSnapshot()
     * below, which is scoped to one assignment for exactly that reason.
     *
     * @param int $question_id
     * @param int $new_question_revision_id
     * @return void
     */
    /**
     * Patches this question's locked revision inside every Learning Tree
     * snapshot that uses it as a node (root or not), across every
     * assignment - propagate is reserved for safe/topical changes, so this
     * deliberately doesn't wipe any submissions the way every other
     * tree-snapshot update in this app does.
     *
     * @param int $question_id
     * @param int $new_question_revision_id
     * @return int[] ids of the assignment_question_learning_tree rows actually patched, for callers that want to log what was affected (see QuestionRevisionPropagation)
     */
    public function propagateQuestionRevisionToLearningTreeSnapshots(int $question_id, int $new_question_revision_id): array
    {
        $assignment_question_learning_trees = DB::table('assignment_question_learning_tree')
            ->whereNotNull('learning_tree')
            ->where('learning_tree', '!=', '')
            ->get();

        $affected_ids = [];
        foreach ($assignment_question_learning_trees as $assignment_question_learning_tree) {
            if ($this->patchQuestionRevisionInSnapshotRow($assignment_question_learning_tree, $question_id, $new_question_revision_id)) {
                $affected_ids[] = $assignment_question_learning_tree->id;
            }
        }
        return $affected_ids;
    }

    /**
     * When a question is edited with the "notify" revision action and its
     * auto-update path (course-level auto_update_question_revisions, or an
     * owner's own opted-in open assignments) bumps a single assignment's
     * assignment_question.question_revision_id, this keeps that same
     * assignment's Learning Tree snapshot in sync for that one node -
     * scoped to just this assignment, since notify's auto-update (unlike
     * propagate) only ever touches specific assignments, not every
     * assignment using the question.
     *
     * A no-op if this question isn't this assignment's tree root (the only
     * way notify's auto-update path can reach a tree node at all, since it
     * only ever operates on real assignment_question rows, which non-root
     * nodes don't have).
     *
     * Without this, learningTreeNeedsUpdate() would keep flagging the tree
     * as stale even though the root is already current, and a later manual
     * "Update to Latest" - triggered only by that stale flag - would wipe
     * this assignment's submissions a second time for no reason.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param int $new_question_revision_id
     * @return void
     */
    public function patchNodeRevisionInAssignmentSnapshot(int $assignment_id, int $question_id, int $new_question_revision_id): void
    {
        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->first();
        if (!$assignment_question) {
            return;
        }
        $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $assignment_question->id)
            ->first();
        if (!$assignment_question_learning_tree || !$assignment_question_learning_tree->learning_tree) {
            return;
        }
        $this->patchQuestionRevisionInSnapshotRow($assignment_question_learning_tree, $question_id, $new_question_revision_id);
    }

    /**
     * Shared by propagateQuestionRevisionToLearningTreeSnapshots() and
     * patchNodeRevisionInAssignmentSnapshot() - patches a single
     * assignment_question_learning_tree row's snapshot in place for one
     * question_id's node(s), leaving structure and every other node alone.
     *
     * @param object $assignment_question_learning_tree row from assignment_question_learning_tree
     * @param int $question_id
     * @param int $new_question_revision_id
     * @return bool whether anything was actually changed
     */
    private function patchQuestionRevisionInSnapshotRow(object $assignment_question_learning_tree, int $question_id, int $new_question_revision_id): bool
    {
        $decoded = json_decode($assignment_question_learning_tree->learning_tree, true);
        $blocks = $decoded['blocks'] ?? [];
        $changed = false;

        foreach ($blocks as $key => $block) {
            if ($this->questionIdFromBlockData($block['data']) !== $question_id) {
                continue;
            }
            foreach ($block['data'] as $data_key => $entry) {
                if ($entry['name'] === 'question_revision_id') {
                    $blocks[$key]['data'][$data_key]['value'] = (string)$new_question_revision_id;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            DB::table('assignment_question_learning_tree')
                ->where('id', $assignment_question_learning_tree->id)
                ->update([
                    'learning_tree' => json_encode(['blocks' => $blocks]),
                    'updated_at' => Carbon::now()
                ]);
        }
        return $changed;
    }

    /**
     * @param array $block_data the `data` array from a single block (either from
     *  the live learning_trees.learning_tree JSON, or from our own snapshot JSON)
     * @return int|null
     */
    private function questionIdFromBlockData(array $block_data): ?int
    {
        foreach ($block_data as $entry) {
            if ($entry['name'] === 'question_id') {
                return (int)trim($entry['value']);
            }
        }
        return null;
    }

    /**
     * @param array $block a single decoded block (array form, not stdClass)
     * @return array{parent: int, question_id: int|null, question_revision_id: int|null}
     */
    private function extractBlockInfo(array $block): array
    {
        $question_id = null;
        $question_revision_id = null;
        foreach ($block['data'] as $entry) {
            if ($entry['name'] === 'question_id') {
                $question_id = (int)trim($entry['value']);
            }
            if ($entry['name'] === 'question_revision_id') {
                $question_revision_id = $entry['value'] !== '' ? (int)$entry['value'] : null;
            }
        }
        return [
            'parent' => (int)$block['parent'],
            'question_id' => $question_id,
            'question_revision_id' => $question_revision_id
        ];
    }
}
