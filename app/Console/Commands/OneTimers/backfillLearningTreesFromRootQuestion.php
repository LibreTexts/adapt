<?php

namespace App\Console\Commands\OneTimers;

use App\Exceptions\Handler;
use App\LearningTree;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class backfillLearningTreesFromRootQuestion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning-trees:backfill-from-root-question';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "For every existing Learning Tree, check its root node question for tags, framework alignment, and subject/chapter/section. If the tree is missing any of these and the root question has them, copy them onto the tree.";

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
            $learning_trees = LearningTree::whereNotNull('root_node_question_id')->get();
            $total = $learning_trees->count();
            $updated = 0;

            foreach ($learning_trees as $learningTree) {
                $tags_before = DB::table('learning_tree_tag')
                    ->where('learning_tree_id', $learningTree->id)
                    ->count();
                $framework_items_before = DB::table('framework_item_learning_tree')
                    ->where('learning_tree_id', $learningTree->id)
                    ->count();
                $subject_chapter_section_before = $learningTree->question_subject_id
                    || $learningTree->question_chapter_id
                    || $learningTree->question_section_id;

                $learningTree->fillMissingAttributesFromRootQuestion();

                $tags_after = DB::table('learning_tree_tag')
                    ->where('learning_tree_id', $learningTree->id)
                    ->count();
                $framework_items_after = DB::table('framework_item_learning_tree')
                    ->where('learning_tree_id', $learningTree->id)
                    ->count();
                $learningTree->refresh();
                $subject_chapter_section_after = $learningTree->question_subject_id
                    || $learningTree->question_chapter_id
                    || $learningTree->question_section_id;

                $tree_was_changed = ($tags_after > $tags_before)
                    || ($framework_items_after > $framework_items_before)
                    || (!$subject_chapter_section_before && $subject_chapter_section_after);

                if ($tree_was_changed) {
                    $updated++;
                    $this->info("Learning Tree {$learningTree->id}: backfilled from root question {$learningTree->root_node_question_id}.");
                }
            }

            $this->info("Done. Checked $total learning trees, backfilled $updated.");
            echo "Done. Checked $total learning trees, backfilled $updated." . PHP_EOL;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
