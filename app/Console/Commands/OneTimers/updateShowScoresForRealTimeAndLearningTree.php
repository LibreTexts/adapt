<?php

namespace App\Console\Commands\OneTimers;

use App\Assignment;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class updateShowScoresForRealTimeAndLearningTree extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:showScoresForRealTimeAndLearningTree {--dry-run} {--batch-size=2000}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Releases the scores for all real time and learning tree assignments, clears their show scores auto-release, and saves the previous values so that they can be reverted. Each batch is its own transaction so the command can be re-run to finish any that failed.';

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
        $assessment_types = Assignment::ALWAYS_SHOW_SCORES_ASSESSMENT_TYPES;

        //Only assignments that still need updating are selected, so a re-run picks up where the last one stopped.
        $hidden_assignment_ids = DB::table('assignments')
            ->whereIn('assessment_type', $assessment_types)
            ->where('show_scores', 0)
            ->pluck('id')
            ->toArray();

        $auto_release_assignment_ids = DB::table('auto_releases')
            ->join('assignments', 'auto_releases.type_id', '=', 'assignments.id')
            ->where('auto_releases.type', 'assignment')
            ->whereIn('assignments.assessment_type', $assessment_types)
            ->where(function ($query) {
                $query->whereNotNull('auto_releases.show_scores')
                    ->orWhereNotNull('auto_releases.show_scores_after');
            })
            ->pluck('auto_releases.type_id')
            ->toArray();

        $assignment_ids = array_values(array_unique(array_merge($hidden_assignment_ids, $auto_release_assignment_ids)));
        sort($assignment_ids);

        $this->info(count($hidden_assignment_ids) . " assignments with hidden scores.");
        $this->info(count($auto_release_assignment_ids) . " auto-releases with a show scores timing.");
        $this->info(count($assignment_ids) . " assignments to update.");

        if ($dry_run) {
            $this->line("Assignment ids: " . implode(', ', $assignment_ids));
            $this->info("Dry run: nothing was updated.");
            return 0;
        }

        $failed = [];
        $num_updated = 0;
        $bar = $this->output->createProgressBar(count($assignment_ids));
        foreach (array_chunk($assignment_ids, $batch_size) as $batch) {
            try {
                DB::transaction(function () use ($batch) {
                    $this->updateBatch($batch);
                });
                $num_updated += count($batch);
            } catch (Exception $e) {
                $failed[] = ['ids' => $batch, 'message' => $e->getMessage()];
            }
            $bar->advance(count($batch));
        }
        $bar->finish();
        $this->line('');

        $this->info("$num_updated assignments updated.");
        if ($failed) {
            $num_failed = array_sum(array_map(function ($batch) {
                return count($batch['ids']);
            }, $failed));
            $this->error("$num_failed assignments in " . count($failed) . " batches failed and were not changed. Re-run the command to retry them.");
            foreach ($failed as $batch) {
                $this->error("Assignments " . min($batch['ids']) . "-" . max($batch['ids']) . ": {$batch['message']}");
            }
            return 1;
        }
        return 0;
    }

    /**
     * Saves the current values, then releases the scores and clears the show scores auto-release.
     * Runs inside the caller's transaction so that the whole batch happens or none of it does.
     *
     * @param array $batch
     * @return void
     */
    private function updateBatch(array $batch): void
    {
        $now = now();
        $assignments = DB::table('assignments')
            ->whereIn('id', $batch)
            ->lockForUpdate()
            ->get(['id', 'show_scores'])
            ->keyBy('id');
        $auto_releases = DB::table('auto_releases')
            ->where('type', 'assignment')
            ->whereIn('type_id', $batch)
            ->where(function ($query) {
                $query->whereNotNull('show_scores')
                    ->orWhereNotNull('show_scores_after');
            })
            ->lockForUpdate()
            ->get(['id', 'type_id', 'show_scores', 'show_scores_after', 'show_scores_activated'])
            ->keyBy('type_id');

        $saved_rows = [];
        foreach ($assignments as $assignment_id => $assignment) {
            $auto_release = $auto_releases->get($assignment_id);
            $saved_rows[] = [
                'assignment_id' => $assignment_id,
                'previous_show_scores' => (int)$assignment->show_scores,
                'auto_release_changed' => $auto_release ? 1 : 0,
                'previous_auto_release_show_scores' => $auto_release ? $auto_release->show_scores : null,
                'previous_auto_release_show_scores_after' => $auto_release ? $auto_release->show_scores_after : null,
                'previous_auto_release_show_scores_activated' => $auto_release ? $auto_release->show_scores_activated : null,
                'created_at' => $now,
                'updated_at' => $now];
        }
        if (!$saved_rows) {
            return;
        }
        //insertOrIgnore: if a row already exists, the original values from an earlier run are kept
        DB::table('show_scores_released_assignments')->insertOrIgnore($saved_rows);

        $hidden_assignment_ids = $assignments->filter(function ($assignment) {
            return !$assignment->show_scores;
        })->keys()->toArray();
        if ($hidden_assignment_ids) {
            DB::table('assignments')
                ->whereIn('id', $hidden_assignment_ids)
                ->update(['show_scores' => 1, 'updated_at' => $now]);
        }
        if ($auto_releases->isNotEmpty()) {
            DB::table('auto_releases')
                ->whereIn('id', $auto_releases->pluck('id')->toArray())
                ->update(['show_scores' => null,
                    'show_scores_after' => null,
                    'show_scores_activated' => 0,
                    'updated_at' => $now]);
        }
    }
}
