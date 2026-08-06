<?php

namespace App\Console\Commands\OneTimers;

use App\AssignmentQuestionLearningTree;
use App\Exceptions\Handler;
use App\LearningTree;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class backfillLearningTreeSnapshotHtml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning-trees:backfill-snapshot-html';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Rebuilds assignment_question_learning_tree.learning_tree snapshots that were "
        . "written blocks-only by the pre-fix buildLearningTreeSnapshot() (missing html/blockarr, which "
        . "flowy.import() requires to render at all - a blocks-only snapshot renders the canvas as the literal "
        . "text 'undefined' and then throws trying to read blockarr.length). Only backfills rows whose stored "
        . "blocks already match the Learning Tree's current live blocks (learningTreeNeedsUpdate() would report "
        . "false) - for those, rebuilding from the live tree is safe since there's no actual content drift, just "
        . "a rendering-format gap. Rows with real drift are left alone; they'll get a correct, renderable "
        . "snapshot the normal way, the next time someone runs 'Update to Latest Revision' on that assignment - "
        . "that's also the only path that correctly notifies the instructor and wipes affected student "
        . "submissions, which this backfill must not do silently.";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        try {
            $assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();

            $assignment_question_learning_trees = DB::table('assignment_question_learning_tree')
                ->whereNotNull('learning_tree')
                ->where('learning_tree', '!=', '')
                ->get();

            $total = $assignment_question_learning_trees->count();
            $updated = 0;
            $skipped_already_fixed = 0;
            $skipped_drifted = 0;
            $skipped_missing_tree = 0;

            foreach ($assignment_question_learning_trees as $assignment_question_learning_tree) {
                $decoded = json_decode($assignment_question_learning_tree->learning_tree, true);
                if (isset($decoded['html']) && isset($decoded['blockarr'])) {
                    // already in the fixed format - either written by the
                    // corrected buildLearningTreeSnapshot() already (e.g. via
                    // a later "Update to Latest Revision"), or backfilled by
                    // a prior run of this command
                    $skipped_already_fixed++;
                    continue;
                }

                $learningTree = LearningTree::find($assignment_question_learning_tree->learning_tree_id);
                if (!$learningTree) {
                    $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id}: no Learning Tree with id {$assignment_question_learning_tree->learning_tree_id} exists, skipping.");
                    $skipped_missing_tree++;
                    continue;
                }

                if ($assignmentQuestionLearningTree->learningTreeNeedsUpdate($assignment_question_learning_tree, $learningTree)) {
                    // real content/structure drift exists between the stored
                    // snapshot and the live tree - rebuilding from the live
                    // tree here would silently change what's shown, without
                    // the notify/wipe-submissions handling "Update to Latest
                    // Revision" normally does, so leave it for that flow.
                    $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id} (learning tree {$learningTree->id}): stored snapshot has drifted from the live tree, skipping - run 'Update to Latest Revision' on this assignment instead.");
                    $skipped_drifted++;
                    continue;
                }

                DB::table('assignment_question_learning_tree')
                    ->where('id', $assignment_question_learning_tree->id)
                    ->update([
                        'learning_tree' => $assignmentQuestionLearningTree->buildLearningTreeSnapshot($learningTree),
                        'updated_at' => now()
                    ]);

                $updated++;
                $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id} (learning tree {$learningTree->id}): backfilled with html/blockarr.");
            }

            $summary = "Done. Checked $total row(s) with a stored snapshot: backfilled $updated, "
                . "already fixed $skipped_already_fixed, drifted (skipped) $skipped_drifted, "
                . "missing tree (skipped) $skipped_missing_tree.";
            $this->info($summary);
            echo $summary . PHP_EOL;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
