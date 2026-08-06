<?php

namespace App\Console\Commands\OneTimers;

use App\AssignmentQuestionLearningTree;
use App\Exceptions\Handler;
use App\LearningTree;
use App\Question;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class backfillLearningTreeQuestionRevisions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning-trees:backfill-question-revisions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "For every assignment_question_learning_tree row without a stored structure/revision snapshot (added before this feature existed), build one from the Learning Tree's current live definition. Also sets the root node's assignment_question.question_revision_id to that question's latest revision if it isn't already set.";

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
                ->where(function ($query) {
                    $query->whereNull('learning_tree')
                        ->orWhere('learning_tree', '');
                })
                ->get();

            $total = $assignment_question_learning_trees->count();
            $updated = 0;

            foreach ($assignment_question_learning_trees as $assignment_question_learning_tree) {
                $learningTree = LearningTree::find($assignment_question_learning_tree->learning_tree_id);
                if (!$learningTree) {
                    $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id}: no Learning Tree with id {$assignment_question_learning_tree->learning_tree_id} exists, skipping.");
                    continue;
                }

                $assignment_question = DB::table('assignment_question')
                    ->where('id', $assignment_question_learning_tree->assignment_question_id)
                    ->first();
                if (!$assignment_question) {
                    $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id}: no matching assignment_question {$assignment_question_learning_tree->assignment_question_id}, skipping.");
                    continue;
                }

                if (!$assignment_question->question_revision_id) {
                    $root_question = Question::find($assignment_question->question_id);
                    $latest_question_revision_id = $root_question ? $root_question->latestQuestionRevision('id') : null;
                    if ($latest_question_revision_id) {
                        DB::table('assignment_question')
                            ->where('id', $assignment_question->id)
                            ->update(['question_revision_id' => $latest_question_revision_id]);
                    }
                }

                DB::table('assignment_question_learning_tree')
                    ->where('id', $assignment_question_learning_tree->id)
                    ->update([
                        'learning_tree' => $assignmentQuestionLearningTree->buildLearningTreeSnapshot($learningTree),
                        'updated_at' => now()
                    ]);

                $updated++;
                $this->info("assignment_question_learning_tree {$assignment_question_learning_tree->id} (learning tree {$learningTree->id}): backfilled.");
            }

            $this->info("Done. Checked $total assignment_question_learning_tree rows with nothing set, backfilled $updated.");
            echo "Done. Checked $total assignment_question_learning_tree rows with nothing set, backfilled $updated." . PHP_EOL;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
