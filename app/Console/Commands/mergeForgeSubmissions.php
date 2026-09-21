<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class mergeForgeSubmissions extends Command
{
    protected $signature = 'submissions:merge-forge {assignment_id} {--dry-run : Preview changes without modifying the database}';
    protected $description = 'Merge duplicate submission_files where both a "forge" and "q" entry exist for the same user/question. Copies the q data into the forge row, then deletes the q row.';

    public function handle()
    {
        $assignmentId = $this->argument('assignment_id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('*** DRY RUN — no changes will be made ***');
            $this->line('');
        }

        // Find all user/question combos that have BOTH a forge and a q entry
        $duplicates = DB::table('submission_files')
            ->select('user_id', 'question_id')
            ->where('assignment_id', $assignmentId)
            ->groupBy('user_id', 'question_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found for assignment ' . $assignmentId);
            return;
        }

        $this->info("Found {$duplicates->count()} duplicate(s) to merge.");

        $mergedCount = 0;
        $skippedCount = 0;

        foreach ($duplicates as $dup) {
            $forgeRow = DB::table('submission_files')
                ->where('assignment_id', $assignmentId)
                ->where('user_id', $dup->user_id)
                ->where('question_id', $dup->question_id)
                ->where('type', 'forge')
                ->first();

            $qRow = DB::table('submission_files')
                ->where('assignment_id', $assignmentId)
                ->where('user_id', $dup->user_id)
                ->where('question_id', $dup->question_id)
                ->where('type', 'q')
                ->first();

            // Safety check: only merge if both rows actually exist
            if (!$forgeRow || !$qRow) {
                $this->warn("Skipping user {$dup->user_id} / question {$dup->question_id} — missing forge or q row.");
                $skippedCount++;
                continue;
            }

            // Show what we're about to do
            $this->line('');
            $this->info("User {$dup->user_id} / Question {$dup->question_id}:");
            $this->table(
                ['id', 'type', 'original_filename', 'submission'],
                [
                    [$forgeRow->id, 'forge', $forgeRow->original_filename ?: '(empty)', mb_strimwidth($forgeRow->submission ?: '(empty)', 0, 60, '...')],
                    [$qRow->id,     'q',     $qRow->original_filename ?: '(empty)',     mb_strimwidth($qRow->submission ?: '(empty)', 0, 60, '...')],
                ]
            );
            $this->line("  → Copying q data into forge (id {$forgeRow->id}), then deleting q (id {$qRow->id})");

            if (!$dryRun) {
                // Copy the filename and submission from the q row into the forge row
                DB::table('submission_files')
                    ->where('id', $forgeRow->id)
                    ->update([
                        'original_filename' => $qRow->original_filename,
                        'submission'        => $qRow->submission,
                    ]);

                // Delete the now-redundant q row
                DB::table('submission_files')
                    ->where('id', $qRow->id)
                    ->delete();
            }

            $mergedCount++;
        }

        $this->line('');
        $this->info('=== Summary ===');
        $this->info("Rows merged:  {$mergedCount}");
        $this->info("Rows deleted: {$mergedCount}");
        if ($skippedCount > 0) {
            $this->warn("Skipped:      {$skippedCount}");
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('This was a dry run. Re-run without --dry-run to apply changes.');
        } else {
            $this->info('Done!');
        }
    }
}
