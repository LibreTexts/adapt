<?php

namespace App\Console\Commands\OneTimers;

use App\Exceptions\Handler;
use App\Score;
use App\Submission;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class fixAccountingJournalEntryTAccountScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:accountingJournalEntryTAccountScores {assignment_id} {--question_ids=340446,340447} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Recomputes submission scores for accounting_journal_entry submissions that were incorrectly graded against a T-Accounts solution (property_exists() vs includeTAccounts bug), preserving any penalty already baked into the stored score, then rebuilds and passes back the affected users' assignment scores. Does not touch created_at/updated_at on submissions so late-marking is unaffected.";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $assignment_id = (int)$this->argument('assignment_id');
            $question_ids = array_map('intval', explode(',', $this->option('question_ids')));
            $dry_run = (bool)$this->option('dry-run');

            $submissionModel = new Submission();
            $scoreModel = new Score();

            $submissions = DB::table('submissions')
                ->where('assignment_id', $assignment_id)
                ->whereIn('question_id', $question_ids)
                ->get();

            $points_by_question_id = DB::table('assignment_question')
                ->where('assignment_id', $assignment_id)
                ->whereIn('question_id', $question_ids)
                ->pluck('points', 'question_id');

            $affected_user_ids = [];
            $updated = 0;
            $skipped = 0;

            DB::beginTransaction();
            foreach ($submissions as $submission) {
                $decoded = json_decode($submission->submission);
                if (!$decoded || !isset($decoded->question) || !isset($decoded->student_response)) {
                    $this->info("submission {$submission->id}: could not decode stored submission blob, skipping.");
                    $skipped++;
                    continue;
                }

                $solution = $decoded->question->entries ?? null;
                if (!$solution) {
                    $this->info("submission {$submission->id}: no entries on stored question, skipping.");
                    $skipped++;
                    continue;
                }

                // Corrected includeTAccounts check - only pull in the T-Accounts
                // solution when the question actually turned that feature on.
                $tAccountsSolution = !empty($decoded->question->includeTAccounts)
                    ? ($decoded->question->tAccounts ?? [])
                    : [];

                $studentSubmission = is_string($decoded->student_response)
                    ? json_decode($decoded->student_response, true)
                    : json_decode(json_encode($decoded->student_response), true);

                $solutionArray = json_decode(json_encode($solution), true);
                $tAccountsSolutionArray = json_decode(json_encode($tAccountsSolution), true);

                $result = $submissionModel->computeScoreForAccountingJournalEntry($solutionArray, $studentSubmission, $tAccountsSolutionArray);
                $new_proportion_correct = $result['proportionCorrect'];

                $old_proportion_correct = $decoded->proportion_correct ?? null;
                $points = floatval($points_by_question_id[$submission->question_id] ?? 0);

                if ($old_proportion_correct === null || $points <= 0) {
                    $this->info("submission {$submission->id}: missing old proportion_correct or points, skipping - needs manual review.");
                    $skipped++;
                    continue;
                }

                // Back out whatever penalty ratio was already baked into the stored
                // score (there's no late penalty configured for this assignment, but
                // this keeps the fix safe/generic in case an attempt or hint penalty
                // was applied) so the fix only corrects the T-Accounts bug and
                // doesn't disturb anything else about how the score was computed.
                $old_raw_score = $points * floatval($old_proportion_correct);
                $penalty_ratio = $old_raw_score > 0 ? floatval($submission->score) / $old_raw_score : 1;
                $new_score = round(($points * $new_proportion_correct) * $penalty_ratio, 4);

                if (abs($new_score - floatval($submission->score)) < 0.0001) {
                    continue;
                }

                $this->info("submission {$submission->id} (user {$submission->user_id}, question {$submission->question_id}): {$submission->score} -> $new_score (proportion $old_proportion_correct -> $new_proportion_correct)");

                if (!$dry_run) {
                    $decoded->proportion_correct = $new_proportion_correct;
                    // Deliberately not touching created_at/updated_at here - the
                    // submission's timing (and therefore its late-marking) must
                    // stay exactly as it was.
                    DB::table('submissions')
                        ->where('id', $submission->id)
                        ->update([
                            'score' => $new_score,
                            'submission' => json_encode($decoded)
                        ]);
                    $affected_user_ids[$submission->user_id] = true;
                }
                $updated++;
            }
            DB::commit();

            if (!$dry_run) {
                foreach (array_keys($affected_user_ids) as $user_id) {
                    // true = pass the corrected grade back to the LMS.
                    $scoreModel->updateAssignmentScore($user_id, $assignment_id, true);
                }
            }

            $prefix = $dry_run ? "[DRY RUN] " : "";
            $this->info("{$prefix}Done. Checked {$submissions->count()} submissions, updated $updated, skipped $skipped.");
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $this->error("Something went wrong: " . $e->getMessage());
        }
    }
}
