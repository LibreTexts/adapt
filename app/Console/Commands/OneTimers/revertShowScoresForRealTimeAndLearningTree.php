<?php

namespace App\Console\Commands\OneTimers;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class revertShowScoresForRealTimeAndLearningTree extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'revert:showScoresForRealTimeAndLearningTree {--dry-run} {--batch-size=2000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restores the show scores and show scores auto-release values saved by update:showScoresForRealTimeAndLearningTree. Each batch is its own transaction so the command can be re-run to finish any that failed.';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dry_run = $this->option('dry-run');
        $batch_size = max(1, (int)$this->option('batch-size'));

        //Rows are deleted as soon as their assignments are reverted, so a re-run only sees what's left.
        $saved_ids = DB::table('show_scores_released_assignments')
            ->orderBy('assignment_id')
            ->pluck('id')
            ->toArray();
        $num_auto_releases = DB::table('show_scores_released_assignments')
            ->where('auto_release_changed', 1)
            ->count();

        $this->info(count($saved_ids) . " assignments to revert.");
        $this->info("$num_auto_releases auto-releases to restore.");

        if ($dry_run) {
            $this->line("Assignment ids: " . implode(', ', DB::table('show_scores_released_assignments')
                    ->orderBy('assignment_id')
                    ->pluck('assignment_id')
                    ->toArray()));
            $this->info("Dry run: nothing was updated.");
            return 0;
        }

        $failed = [];
        $num_reverted = 0;
        $num_skipped = 0;
        $bar = $this->output->createProgressBar(count($saved_ids));
        foreach (array_chunk($saved_ids, $batch_size) as $batch) {
            try {
                $result = DB::transaction(function () use ($batch) {
                    return $this->revertBatch($batch);
                });
                $num_reverted += $result['reverted'];
                $num_skipped += $result['skipped'];
            } catch (Exception $e) {
                $failed[] = ['ids' => $batch, 'message' => $e->getMessage()];
            }
            $bar->advance(count($batch));
        }
        $bar->finish();
        $this->line('');

        $this->info("$num_reverted assignments reverted.");
        if ($num_skipped) {
            $this->info("$num_skipped saved assignments no longer exist and were removed from the table.");
        }
        if ($failed) {
            $num_failed = array_sum(array_map(function ($batch) {
                return count($batch['ids']);
            }, $failed));
            $this->error("$num_failed assignments in " . count($failed) . " batches failed and were not changed. Re-run the command to retry them.");
            foreach ($failed as $batch) {
                $this->error("show_scores_released_assignments ids " . min($batch['ids']) . "-" . max($batch['ids']) . ": {$batch['message']}");
            }
            return 1;
        }
        return 0;
    }

    /**
     * Restores a batch of assignments and deletes their saved rows.
     * Runs inside the caller's transaction so that the whole batch happens or none of it does.
     *
     * @param array $batch show_scores_released_assignments ids
     * @return array
     */
    private function revertBatch(array $batch): array
    {
        $now = now();
        $saved_assignments = DB::table('show_scores_released_assignments')
            ->whereIn('id', $batch)
            ->lockForUpdate()
            ->get();
        $existing_assignment_ids = DB::table('assignments')
            ->whereIn('id', $saved_assignments->pluck('assignment_id')->toArray())
            ->pluck('id')
            ->toArray();
        $existing = $saved_assignments->whereIn('assignment_id', $existing_assignment_ids);

        foreach ($existing->groupBy('previous_show_scores') as $previous_show_scores => $group) {
            DB::table('assignments')
                ->whereIn('id', $group->pluck('assignment_id')->toArray())
                ->update(['show_scores' => $previous_show_scores, 'updated_at' => $now]);
        }

        foreach ($existing->where('auto_release_changed', 1) as $saved_assignment) {
            //matched on type/type_id since the auto-release row may have been re-created since the update
            DB::table('auto_releases')->updateOrInsert(
                ['type' => 'assignment', 'type_id' => $saved_assignment->assignment_id],
                ['show_scores' => $saved_assignment->previous_auto_release_show_scores,
                    'show_scores_after' => $saved_assignment->previous_auto_release_show_scores_after,
                    'show_scores_activated' => $saved_assignment->previous_auto_release_show_scores_activated,
                    'updated_at' => $now]);
        }

        DB::table('show_scores_released_assignments')->whereIn('id', $batch)->delete();
        return ['reverted' => count($existing),
            'skipped' => count($saved_assignments) - count($existing)];
    }
}
