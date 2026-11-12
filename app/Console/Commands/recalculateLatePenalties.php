<?php

namespace App\Console\Commands;

use App\Assignment;
use App\Exceptions\Handler;
use App\Services\LatePenaltyRecalculator;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-applies an assignment's late-deduction policy after an instructor has changed the due date.
 * The scoring rules and safety checks live in App\Services\LatePenaltyRecalculator; this command
 * shows the preview and, on a real run, saves the new scores and updates each student's assignment
 * score, passing grades back to the LMS if the assignment does that automatically.
 */
class recalculateLatePenalties extends Command
{
    protected $signature = 'assignment:recalculateLatePenalties
                            {assignment_id : The assignment whose due date changed}
                            {--dry-run : Show what would change without saving anything}
                            {--timezone= : Time zone for displayed dates (defaults to the instructor\'s time zone)}
                            {--user= : Only process this student user id}
                            {--by-question : Also show which questions\' late penalties change, and why any score was skipped}
                            {--show-all : With --by-question, list every question, including ones that don\'t change}
                            {--force : Save without asking. Required when there is no terminal to answer, e.g. on Vapor}';

    protected $description = 'Recalculate late-deduction penalties on an assignment after its due date has changed. Use --dry-run to preview.';

    /** @var string */
    private $tz;

    /** @var LatePenaltyRecalculator */
    private $recalculator;

    public function handle(LatePenaltyRecalculator $recalculator): int
    {
        $this->recalculator = $recalculator;
        try {
            $assignment = Assignment::find($this->argument('assignment_id'));
            if (!$assignment) {
                $this->error("No assignment with id {$this->argument('assignment_id')}.");
                return 1;
            }
            $dry_run = (bool)$this->option('dry-run');
            $user_id = $this->option('user') ? (int)$this->option('user') : null;

            $this->tz = $this->resolveTimezone($assignment);
            if (!$this->tz) {
                return 1;
            }

            $this->printHeader($assignment, $dry_run);

            $reason = $recalculator->notApplicableReason($assignment);
            if ($reason) {
                $this->warn("$reason Nothing to do.");
                return 0;
            }

            $rows = $recalculator->rows($assignment, $user_id);
            if (!$rows) {
                $this->info('No auto-graded scores found for this assignment.');
                $this->printHandAppliedNote($assignment, $user_id);
                return 0;
            }

            if ($this->option('by-question') || $this->option('show-all')) {
                $this->printScoreTable($rows);
            }
            $students = $recalculator->studentSummaries($assignment, $rows);
            $this->printStudentSummary($assignment, $students);
            $changes = $recalculator->changes($rows);
            $this->printTotals($rows, $changes, $students);
            $this->printSubmittedAfterFinalDeadline($assignment, $rows);
            $this->printHandAppliedNote($assignment, $user_id);

            if (!$changes) {
                $this->info('No scores need to change.');
                return 0;
            }
            if ($dry_run) {
                $this->info('Dry run: nothing was saved. Run again without --dry-run to apply.');
                return 0;
            }
            if (!Schema::hasTable('late_penalty_recalculations')) {
                $this->error('The late_penalty_recalculations table does not exist. Run `php artisan migrate` first.');
                return 1;
            }
            $passback = $assignment->lms_grade_passback === 'automatic';
            if (!$this->option('force')) {
                // Without a terminal (Vapor, cron, --no-interaction) nobody can answer, so require --force
                // rather than saving unasked. Vapor can report itself as interactive, so also check that
                // a terminal is actually attached.
                if (!$this->input->isInteractive() || !$this->hasTerminal()) {
                    $this->warn('Nothing was saved: there is no terminal to confirm. Run again with --force to save these changes.');
                    return 0;
                }
                if (!$this->confirm('Save ' . count($changes) . ' new score(s) and update the assignment scores'
                    . ($passback ? ' (including LMS passback)' : '') . '?')) {
                    $this->info('Cancelled. Nothing was saved.');
                    return 0;
                }
            }

            $this->line('Saving ' . count($changes) . ' score(s):');
            $save_bar = $this->runningOnVapor() ? null : $this->output->createProgressBar(count($changes));
            if ($save_bar) {
                $save_bar->start();
            }
            $user_ids = $recalculator->save($assignment, $changes, function (int $num_saved) use ($save_bar) {
                if ($save_bar) {
                    $save_bar->advance($num_saved);
                }
            });
            if ($save_bar) {
                $save_bar->finish();
                $this->newLine();
            }
            $this->info(count($changes) . ' score(s) saved.');
            // One student at a time (with an LMS call each when passing back), so show progress,
            // except on Vapor, where each redraw would just add noise to the logs.
            $progress_bar = null;
            if (!$this->runningOnVapor()) {
                $this->line($passback ? 'Updating HW scores and passing them back to the LMS:' : 'Updating HW scores:');
                $progress_bar = $this->output->createProgressBar(count($user_ids));
                $progress_bar->start();
            }
            $failed = $recalculator->updateAssignmentScores($assignment, $user_ids, $passback, function () use ($progress_bar) {
                if ($progress_bar) {
                    $progress_bar->advance();
                }
            });
            if ($progress_bar) {
                $progress_bar->finish();
                $this->newLine();
            }
            $this->info('Assignment scores updated for ' . (count($user_ids) - count($failed)) . ' student(s)'
                . ($passback ? ' and passed back to the LMS.' : '.'));
            if ($failed) {
                $this->error('Could not update the assignment score for user id(s): ' . implode(', ', $failed) . '. Check the logs.');
            }
            return 0;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * AWS sets LAMBDA_TASK_ROOT on every Lambda, which is where Vapor runs commands.
     */
    private function runningOnVapor(): bool
    {
        return (bool)getenv('LAMBDA_TASK_ROOT');
    }

    /**
     * Whether a real terminal is attached. Vapor can report itself as interactive without one.
     */
    private function hasTerminal(): bool
    {
        return defined('STDIN') && function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    private function resolveTimezone(Assignment $assignment): ?string
    {
        $tz = $this->option('timezone');
        if (!$tz) {
            $tz = DB::table('courses')
                ->join('users', 'courses.user_id', '=', 'users.id')
                ->where('courses.id', $assignment->course_id)
                ->value('users.time_zone');
        }
        $tz = $tz ?: 'UTC';
        try {
            new DateTimeZone($tz);
        } catch (Exception $e) {
            $this->error("'$tz' is not a valid time zone.");
            return null;
        }
        return $tz;
    }

    private function printHeader(Assignment $assignment, bool $dry_run): void
    {
        $course_name = DB::table('courses')->where('id', $assignment->course_id)->value('name');
        $this->line('');
        $this->line(($dry_run ? '<comment>DRY RUN</comment> ' : '') . "<info>{$assignment->name}</info> (assignment $assignment->id, $course_name)");
        if ($assignment->late_policy === 'deduction') {
            $policy = $assignment->late_deduction_application_period === 'once'
                ? "{$assignment->late_deduction_percent}% once if late"
                : "{$assignment->late_deduction_percent}% per {$assignment->late_deduction_application_period} late";
            $this->line("Late policy: $policy");
        }
        switch ($assignment->lms_grade_passback) {
            case('automatic'):
                $this->line('LMS grade passback: automatic. New HW scores are sent to the LMS when saved.');
                break;
            case('manual'):
                $this->line('LMS grade passback: manual. After saving, pass the grades back from ADAPT as usual.');
                break;
        }
        $this->line("Times shown in {$this->tz}");
        $this->line('');
    }

    private function printScoreTable(array $rows): void
    {
        $show_all = (bool)$this->option('show-all');
        $table = [];
        foreach ($rows as $row) {
            if (!$show_all && $row['status'] === 'same') {
                continue;
            }
            $skipped = $row['status'] === 'skip';
            $table[] = [
                $row['student'],
                $row['question'],
                $row['kind'],
                $this->fmt($row['submitted_at']),
                $this->fmt($row['due']),
                $row['base_score'] === null ? '-' : $this->num($row['base_score']),
                $row['old_pct'] === null ? '-' : $row['old_pct'] . '%',
                $row['new_pct'] === null ? '-' : $row['new_pct'] . '%',
                $this->num($row['old_score']),
                $skipped ? '-' : $this->num($row['new_score']),
                $this->whatChanges($row),
            ];
        }
        $this->line('<info>By question' . ($show_all ? '' : ': scores that change or need attention') . '</info>');
        if (!$table) {
            $this->line('  (none)');
        } else {
            $this->table(['Student', 'Q#', 'Type', 'Submitted', 'Due (now)', 'Score before late penalty', 'Late penalty now', 'New late penalty', 'Question score now', 'New question score', 'What changes'], $table);
        }
        $this->line('');
    }

    private function whatChanges(array $row): string
    {
        if ($row['status'] === 'skip') {
            return 'SKIPPED: ' . $row['note'];
        }
        if ($row['status'] === 'same') {
            return 'No change (late penalty stays ' . $row['old_pct'] . '%)';
        }
        $text = "Late penalty {$row['old_pct']}% -> {$row['new_pct']}%, so "
            . $this->num($row['old_score']) . ' -> ' . $this->num($row['new_score'])
            . ' (' . $this->signed($row['new_score'] - $row['old_score']) . ')';
        if ($row['table'] === 'submitted_work_pending_scores') {
            $text .= '; counts once the submitted work is approved';
        }
        return $text;
    }

    private function printStudentSummary(Assignment $assignment, array $students): void
    {
        $total_points = $this->recalculator->totalPoints($assignment);
        $by_question = $this->option('by-question') || $this->option('show-all');
        $table = [];
        foreach ($students as $student) {
            $change = abs($student['delta']) < 0.00005 ? '0' : $this->signed($student['delta']);
            if (abs($student['pending_delta']) >= 0.00005) {
                $change .= ' (pending approval: ' . $this->signed($student['pending_delta']) . ')';
            }
            if ($student['skipped']) {
                $change .= " ({$student['skipped']} skipped)";
            }
            $line = [$student['student'], $this->fmt($student['due'])];
            if ($by_question) {
                $line[] = $student['question_changes'] ? implode(', ', $student['question_changes']) : '-';
            }
            $line[] = $this->num($student['current']) . ' / ' . $this->num($total_points);
            $line[] = $this->num($student['new']) . ' / ' . $this->num($total_points);
            $line[] = $change;
            $table[] = $line;
        }
        $headers = $by_question
            ? ['Student', 'Due (now)', 'Late penalty changes', 'HW score now', 'New HW score', 'Change']
            : ['Student', 'Due (now)', 'HW score now', 'New HW score', 'Change'];
        $this->line('<info>Homework score by student</info>');
        $this->table($headers, $table);
        $this->line('');
    }

    private function printTotals(array $rows, array $changes, array $students): void
    {
        $skipped = count(array_filter($rows, function ($row) {
            return $row['status'] === 'skip';
        }));
        $students_changed = count(array_filter($students, function ($student) {
            return $student['changes'];
        }));
        $this->line(count($rows) . ' score(s) checked; ' . count($changes) . " will change for $students_changed student(s); $skipped skipped.");
        if ($skipped && !$this->option('by-question') && !$this->option('show-all')) {
            $this->line('Run again with --by-question to see why scores were skipped.');
        }
    }

    private function printSubmittedAfterFinalDeadline(Assignment $assignment, array $rows): void
    {
        $students = $this->recalculator->submittedAfterFinalDeadline($assignment, $rows);
        if (!$students) {
            return;
        }
        $this->warn(count($students) . ' student(s) submitted after their final submission deadline. Those scores are kept and recalculated like the rest:');
        foreach ($students as $student) {
            $this->line("  {$student['student']}: Q" . implode(', Q', $student['questions']));
        }
    }

    private function printHandAppliedNote(Assignment $assignment, ?int $user_id): void
    {
        $count = $this->recalculator->handAppliedLatePenaltyCount($assignment, $user_id);
        if ($count) {
            $this->warn("$count open-ended submission(s) have a late penalty applied by a grader. Those are not changed; review them in the grader if needed.");
        }
        $this->line('');
    }

    private function fmt(?Carbon $date): string
    {
        return $date ? $date->copy()->setTimezone($this->tz)->format('M j, Y g:i a') : '-';
    }

    private function num(float $value): string
    {
        return $this->recalculator->num($value);
    }

    private function signed(float $value): string
    {
        return ($value > 0 ? '+' : '') . $this->num($value);
    }
}
