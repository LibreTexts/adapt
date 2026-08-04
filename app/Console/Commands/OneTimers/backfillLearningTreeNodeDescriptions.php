<?php

namespace App\Console\Commands\OneTimers;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class backfillLearningTreeNodeDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning-trees:backfill-node-descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "For every existing learning_tree_node_descriptions row with an empty description, check the linked question for its own description. If the question has one, copy it onto the node.";

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
            $node_descriptions_missing_description = DB::table('learning_tree_node_descriptions')
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '');
                })
                ->get();

            $total = $node_descriptions_missing_description->count();
            $updated = 0;

            foreach ($node_descriptions_missing_description as $node_description) {
                $question = DB::table('questions')
                    ->where('id', $node_description->question_id)
                    ->select('description')
                    ->first();

                if ($question && $question->description) {
                    DB::table('learning_tree_node_descriptions')
                        ->where('id', $node_description->id)
                        ->update(['description' => $question->description, 'updated_at' => now()]);
                    $updated++;
                    $this->info("learning_tree_node_descriptions {$node_description->id} (question {$node_description->question_id}): backfilled.");
                }
            }

            $this->info("Done. Checked $total node descriptions with nothing set, backfilled $updated.");
            echo "Done. Checked $total node descriptions with nothing set, backfilled $updated." . PHP_EOL;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
