<?php

namespace App\Services;

use App\Assignment;
use App\Exceptions\Handler;
use App\Score;
use App\Submission;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Re-applies an assignment's late-deduction policy after its due date has changed.
 * Used by the assignment:recalculateLatePenalties command and by LatePenaltyRecalculationController.
 *
 * ADAPT only stores scores with the late penalty already applied, so for each score this
 * recomputes what the student earned *before* the late penalty, the same way ADAPT scores it, then
 * applies the late penalty for the current due date:
 *
 *   Auto-graded submissions (Submission::store()):
 *     points x proportion correct (or the completion score)
 *     x (1 - (attempt deductions x attempt penalty + hint penalty) / 100)   real time and learning tree only
 *     x (1 - late penalty / 100)
 *   H5P activity sets (Submission::processh5pActivitySet()):
 *     (correct across every h5p_activity_sets row / h5p max score) x points x (1 - late penalty / 100)
 *   Auto-graded Discuss-it (DiscussionComment::updateScore()):
 *     points x (1 - late penalty / 100)
 *
 * When the submission happened:
 *   Auto-graded: the latest submission_histories row whose response matches the stored response.
 *     submissions.updated_at is NOT used: grading and approving submitted work also change it.
 *   Discuss-it: submission_files.date_submitted.
 *
 * Safety check: the recomputed score, under some late penalty the policy could have produced
 * (0%, 10%, 20%, ...), must reproduce the score stored today. That penalty is the "current penalty".
 * If none does (an instructor regraded it, points changed, ...) the score is left alone and flagged,
 * so only the late penalty is ever changed. Scores with an instructor override, Discuss-it scores a
 * grader has set by hand, and open-ended (file/text/audio) scores are never touched.
 *
 * Scores waiting for submitted work to be approved (submitted_work_pending_scores) are recalculated
 * in that table, since the submission itself holds 0 until approval.
 *
 * Each row returned by rows() has a status: 'change', 'same' or 'skip' (with a note saying why).
 */
class LatePenaltyRecalculator
{
    /**
     * Whether saving an assignment with a new due date asks the instructor to recalculate late penalties.
     * Set to true to turn the question on. While false, nothing is asked; the artisan command still works.
     */
    public static $askInstructorsAfterDueDateChanges = false;

    /** @var Submission */
    private $submissionModel;

    public function __construct(Submission $submissionModel)
    {
        $this->submissionModel = $submissionModel;
    }

    /**
     * Why late penalties can't be recalculated for this assignment, or null if they can.
     */
    public function notApplicableReason(Assignment $assignment): ?string
    {
        if ($assignment->late_policy !== 'deduction') {
            return "This assignment's late policy is '{$assignment->late_policy}', not 'deduction', so no late penalties are applied to scores.";
        }
        if (!(int)$assignment->late_deduction_percent || !$assignment->late_deduction_application_period) {
            return 'The late deduction percent or application period is not set.';
        }
        return null;
    }

    /**
     * Every auto-graded and Discuss-it score on the assignment, with its current and new late penalty.
     */
    public function rows(Assignment $assignment, ?int $user_id = null): array
    {
        $context = $this->loadContext($assignment);
        return array_merge(
            $this->autoGradedRows($assignment, $context, $user_id),
            $this->discussItRows($assignment, $context, $user_id)
        );
    }

    public function changes(array $rows): array
    {
        return array_values(array_filter($rows, function ($row) {
            return $row['status'] === 'change';
        }));
    }

    /**
     * One entry per student, keyed by user id, with their HW score now and after the changes.
     * Pending-approval scores don't count toward the HW score until approved, so they're kept separate.
     */
    public function studentSummaries(Assignment $assignment, array $rows): array
    {
        $current_scores = DB::table('scores')->where('assignment_id', $assignment->id)->pluck('score', 'user_id');

        $students = [];
        foreach ($rows as $row) {
            $user_id = $row['user_id'];
            if (!isset($students[$user_id])) {
                $students[$user_id] = [
                    'user_id' => $user_id,
                    'student' => $row['student'],
                    'due' => $row['due'],
                    'question_changes' => [],
                    'delta' => 0.0,
                    'pending_delta' => 0.0,
                    'skipped' => 0,
                ];
            }
            if ($row['status'] === 'change') {
                $students[$user_id]['question_changes'][(int)$row['question']] = "Q{$row['question']}: {$row['old_pct']}% -> {$row['new_pct']}%"
                    . ($row['table'] === 'submitted_work_pending_scores' ? ' (pending)' : '');
                $key = $row['table'] === 'submitted_work_pending_scores' ? 'pending_delta' : 'delta';
                $students[$user_id][$key] += $row['new_score'] - $row['old_score'];
            }
            if ($row['status'] === 'skip') {
                $students[$user_id]['skipped']++;
            }
        }
        foreach ($students as $user_id => $student) {
            ksort($students[$user_id]['question_changes']);
            $students[$user_id]['current'] = (float)($current_scores[$user_id] ?? 0);
            $students[$user_id]['new'] = $students[$user_id]['current'] + $student['delta'];
            $students[$user_id]['changes'] = abs($student['delta']) >= 0.00005 || abs($student['pending_delta']) >= 0.00005;
        }
        return $students;
    }

    public function totalPoints(Assignment $assignment): float
    {
        return (float)DB::table('assignment_question')->where('assignment_id', $assignment->id)->sum('points');
    }

    /**
     * Open-ended work graded with a rubric can carry a late penalty the grader applied by hand.
     * Those aren't changed, but the instructor may want to revisit them.
     */
    public function handAppliedLatePenaltyCount(Assignment $assignment, ?int $user_id = null): int
    {
        return DB::table('submission_files')
            ->where('assignment_id', $assignment->id)
            ->where('applied_late_penalty', '>', 0)
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('user_id', $user_id);
            })
            ->count();
    }

    /**
     * Saves the new scores and logs each change (with the old score) to late_penalty_recalculations.
     * Only the score column changes: dates and timestamps are left alone. Everything is saved in one
     * transaction, so it's all or nothing. $afterBatch, if given, is called with the number of scores
     * in each batch saved (the command uses it for its progress bar).
     *
     * @return array the ids of the students whose scores changed
     * @throws Exception
     */
    public function save(Assignment $assignment, array $changes, ?callable $afterBatch = null): array
    {
        $tables = ['submissions', 'submitted_work_pending_scores', 'submission_files'];
        DB::beginTransaction();
        try {
            $now = Carbon::now('UTC');
            // Scores are updated 500 at a time with one UPDATE ... CASE per batch, so a large recalculation
            // is a handful of queries rather than one per score (slow when the database is not local).
            foreach (collect($changes)->groupBy('table') as $table => $table_changes) {
                if (!in_array($table, $tables)) {
                    throw new Exception("Unexpected table $table");
                }
                foreach ($table_changes->chunk(500) as $batch) {
                    $cases = [];
                    $bindings = [];
                    foreach ($batch as $change) {
                        $cases[] = 'WHEN ? THEN ?';
                        $bindings[] = $change['record_id'];
                        $bindings[] = $change['new_score'];
                    }
                    $ids = $batch->pluck('record_id')->all();
                    DB::update("UPDATE `$table` SET `score` = CASE `id` " . implode(' ', $cases) . ' END'
                        . ' WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
                        array_merge($bindings, $ids));

                    DB::table('late_penalty_recalculations')->insert($batch->map(function ($change) use ($assignment, $now) {
                        return [
                            'assignment_id' => $assignment->id,
                            'user_id' => $change['user_id'],
                            'question_id' => $change['question_id'],
                            'record_table' => $change['table'],
                            'record_id' => $change['record_id'],
                            'submitted_at' => $change['submitted_at'],
                            'due' => $change['due'],
                            'old_penalty_percent' => $change['old_pct'],
                            'new_penalty_percent' => $change['new_pct'],
                            'old_score' => $change['old_score'],
                            'new_score' => $change['new_score'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->values()->all());
                    if ($afterBatch) {
                        $afterBatch(count($batch));
                    }
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        return array_values(array_unique(array_column($changes, 'user_id')));
    }

    /**
     * Recomputes each student's assignment score with Score::updateAssignmentScore().
     * With $passback true, each grade is passed back to the LMS as it's updated (one LMS call per student).
     * $afterEach, if given, is called after each student (the command uses it for its progress bar).
     *
     * @return array the ids of the students whose assignment score could not be updated
     */
    public function updateAssignmentScores(Assignment $assignment, array $user_ids, bool $passback, ?callable $afterEach = null): array
    {
        $score = new Score();
        $failed = [];
        foreach ($user_ids as $user_id) {
            try {
                $score->updateAssignmentScore($user_id, $assignment->id, $passback);
            } catch (Exception $e) {
                $failed[] = $user_id;
                $h = new Handler(app());
                $h->report($e);
            }
            if ($afterEach) {
                $afterEach();
            }
        }
        return $failed;
    }

    /**
     * Each student's current due date, as a string, keyed by user id. Used to tell whether
     * saving an assignment changed anyone's due date.
     */
    public function dueDatesByUser(int $assignment_id): array
    {
        $dues = [];
        foreach ($this->timingsByUser($assignment_id) as $user_id => $timing) {
            $dues[$user_id] = (string)$timing->due;
        }
        ksort($dues);
        return $dues;
    }

    /**
     * Each student's assign-to timing (due and final submission deadline), keyed by user id. If a student
     * is in more than one assign-to, the first one is used, as in Assignment::assignToTimingDueDateGivenUserId().
     */
    private function timingsByUser(int $assignment_id): array
    {
        $timings = [];
        $rows = DB::table('assign_to_timings')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->where('assign_to_timings.assignment_id', $assignment_id)
            ->orderBy('assign_to_timings.id')
            ->select('assign_to_users.user_id', 'assign_to_timings.due', 'assign_to_timings.final_submission_deadline')
            ->get();
        foreach ($rows as $row) {
            if (!isset($timings[$row->user_id])) {
                $timings[$row->user_id] = $row;
            }
        }
        return $timings;
    }

    /**
     * Students with scores submitted after their final submission deadline, which can happen when the
     * instructor moves that deadline earlier. These scores are kept and recalculated like any other;
     * this just lets the instructor know. Students with an assignment-level override are left out,
     * since ADAPT lets them submit past the deadline.
     *
     * @return array [['user_id' => .., 'student' => .., 'questions' => [question numbers]], ...]
     */
    public function submittedAfterFinalDeadline(Assignment $assignment, array $rows): array
    {
        $allowed_late = DB::table('assignment_level_overrides')
            ->where('assignment_id', $assignment->id)
            ->pluck('user_id')
            ->flip();
        $students = [];
        foreach ($rows as $row) {
            if ($row['submitted_at']
                && $row['final_submission_deadline']
                && $row['submitted_at'] > $row['final_submission_deadline']
                && !$allowed_late->has($row['user_id'])) {
                $students[$row['user_id']]['user_id'] = $row['user_id'];
                $students[$row['user_id']]['student'] = $row['student'];
                $students[$row['user_id']]['questions'][] = $row['question'];
            }
        }
        foreach ($students as $user_id => $student) {
            sort($students[$user_id]['questions']);
        }
        return array_values($students);
    }

    /**
     * Submission::_computeLatePercent(), with the due date passed in so nothing is queried per score.
     */
    public function penaltyPercent(Assignment $assignment, Carbon $due, Carbon $submitted_at): int
    {
        $percent = (int)$assignment->late_deduction_percent;
        $period = $assignment->late_deduction_application_period;
        if ($assignment->late_policy !== 'deduction' || !$percent) {
            return 0;
        }
        if ($period === 'once') {
            return $submitted_at > $due ? $percent : 0;
        }
        $due = $due->copy();
        $max_num_iterations = (int)floor(100 / $percent);
        for ($num_late_periods = 0; $num_late_periods < $max_num_iterations; $num_late_periods++) {
            if ($due > $submitted_at) {
                break;
            }
            $due->add($period);
        }
        return $percent * $num_late_periods;
    }

    /**
     * Everything that's looked up once per assignment.
     */
    private function loadContext(Assignment $assignment): array
    {
        $assignment_questions = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->orderBy('order')
            ->select('id', 'points', 'question_id', 'assignment_id', 'completion_scoring_mode', 'open_ended_submission_type')
            ->get();

        $overrides = [];
        foreach (DB::table('submission_score_overrides')->where('assignment_id', $assignment->id)->get() as $override) {
            $overrides["$override->user_id|$override->question_id"] = true;
        }

        $pending_scores = [];
        foreach (DB::table('submitted_work_pending_scores')->where('assignment_id', $assignment->id)->get() as $pending) {
            $pending_scores["$pending->user_id|$pending->question_id"] = $pending;
        }

        // Who viewed a hint (Submission::getHintPenalty()).
        $viewed_hints = [];
        foreach (DB::table('shown_hints')->where('assignment_id', $assignment->id)->select('user_id', 'question_id')->get() as $hint) {
            $viewed_hints["$hint->user_id|$hint->question_id"] = true;
        }

        // Who has an open-ended submission (completion scoring, "100% for either").
        $open_ended_submissions = [];
        foreach (DB::table('submission_files')->where('assignment_id', $assignment->id)->select('user_id', 'question_id')->distinct()->get() as $file) {
            $open_ended_submissions["$file->user_id|$file->question_id"] = true;
        }

        // H5P activity sets: every part each student saved, and each question's max score.
        $h5p_parts = [];
        foreach (DB::table('h5p_activity_sets')->where('assignment_id', $assignment->id)->select('user_id', 'question_id', 'submission')->get() as $part) {
            $h5p_parts["$part->user_id|$part->question_id"][] = $part->submission;
        }
        $h5p_max_scores = DB::table('h5p_max_scores')
            ->whereIn('question_id', $assignment_questions->pluck('question_id'))
            ->pluck('max_score', 'question_id');

        return [
            'timings_by_user' => $this->timingsByUser($assignment->id),
            'assignment_questions' => $assignment_questions->keyBy('question_id'),
            'question_numbers' => $assignment_questions->pluck('question_id')->flip()->map(function ($index) {
                return $index + 1;
            }),
            'overrides' => $overrides,
            'pending_scores' => $pending_scores,
            'viewed_hints' => $viewed_hints,
            'open_ended_submissions' => $open_ended_submissions,
            'h5p_parts' => $h5p_parts,
            'h5p_max_scores' => $h5p_max_scores,
            'possible_penalties' => $this->possiblePenalties($assignment),
        ];
    }

    private function autoGradedRows(Assignment $assignment, array $context, ?int $user_id): array
    {
        $submissions = DB::table('submissions')
            ->join('users', 'submissions.user_id', '=', 'users.id')
            ->join('questions', 'submissions.question_id', '=', 'questions.id')
            ->where('submissions.assignment_id', $assignment->id)
            ->where('users.role', 3)
            ->where('users.fake_student', 0)
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('submissions.user_id', $user_id);
            })
            ->select('submissions.id',
                'submissions.user_id',
                'submissions.question_id',
                'submissions.submission',
                'submissions.submission_count',
                'submissions.score',
                'questions.technology',
                'users.first_name',
                'users.last_name')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get();

        // When each saved response was submitted: the latest submission_histories row with the same response.
        // MySQL does the matching (MD5 for an exact, case-sensitive comparison), so no response text comes back here.
        $submitted_at_by_submission_id = DB::table('submissions')
            ->join('submission_histories', function ($join) {
                $join->on('submission_histories.user_id', '=', 'submissions.user_id')
                    ->on('submission_histories.assignment_id', '=', 'submissions.assignment_id')
                    ->on('submission_histories.question_id', '=', 'submissions.question_id');
            })
            ->where('submissions.assignment_id', $assignment->id)
            ->whereRaw('MD5(submission_histories.submission) = MD5(submissions.submission)')
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('submissions.user_id', $user_id);
            })
            ->groupBy('submissions.id')
            ->select('submissions.id', DB::raw('MAX(submission_histories.created_at) AS submitted_at'))
            ->pluck('submitted_at', 'id');

        $rows = [];
        foreach ($submissions as $submission) {
            $key = "$submission->user_id|$submission->question_id";
            $pending = $context['pending_scores'][$key] ?? null;
            $row = $this->newRow($submission, $context, $pending ? 'pending work approval' : 'auto-graded');
            if ($pending) {
                // The submission holds 0 until the work is approved; the real score is waiting here.
                $row['table'] = 'submitted_work_pending_scores';
                $row['record_id'] = $pending->id;
                $row['old_score'] = $row['new_score'] = (float)$pending->score;
            } else {
                $row['table'] = 'submissions';
                $row['record_id'] = $submission->id;
                $row['old_score'] = $row['new_score'] = (float)$submission->score;
            }

            $submitted_at = $submitted_at_by_submission_id[$submission->id] ?? null;
            if (!$submitted_at) {
                $rows[] = $this->skip($row, 'No submission history matches the saved response, so the submission time is unknown');
                continue;
            }
            $row['submitted_at'] = Carbon::parse($submitted_at, 'UTC');

            $rows[] = $this->evaluate($assignment, $row, $context, function ($assignment_question) use ($assignment, $submission, $context) {
                return $this->autoGradedScoreBeforeLatePenalty($assignment, $submission, $assignment_question, $context);
            });
        }
        return $rows;
    }

    private function discussItRows(Assignment $assignment, array $context, ?int $user_id): array
    {
        $submission_files = DB::table('submission_files')
            ->join('users', 'submission_files.user_id', '=', 'users.id')
            ->where('submission_files.assignment_id', $assignment->id)
            ->where('submission_files.type', 'discuss_it')
            ->whereNull('submission_files.grader_id') // a grader setting the score by hand takes it out of scope
            ->where('users.role', 3)
            ->where('users.fake_student', 0)
            ->when($user_id, function ($query) use ($user_id) {
                return $query->where('submission_files.user_id', $user_id);
            })
            ->select('submission_files.id',
                'submission_files.user_id',
                'submission_files.question_id',
                'submission_files.score',
                'submission_files.date_submitted',
                'users.first_name',
                'users.last_name')
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get();

        $rows = [];
        foreach ($submission_files as $submission_file) {
            $row = $this->newRow($submission_file, $context, 'Discuss-it');
            $row['table'] = 'submission_files';
            $row['record_id'] = $submission_file->id;
            $row['old_score'] = $row['new_score'] = (float)$submission_file->score;
            $row['submitted_at'] = Carbon::parse($submission_file->date_submitted, 'UTC');
            $rows[] = $this->evaluate($assignment, $row, $context, function ($assignment_question) {
                return floatval($assignment_question->points);
            });
        }
        return $rows;
    }

    private function newRow($record, array $context, string $kind): array
    {
        $timing = $context['timings_by_user'][$record->user_id] ?? null;
        return [
            'kind' => $kind,
            'table' => null,
            'record_id' => null,
            'user_id' => $record->user_id,
            'question_id' => $record->question_id,
            'student' => trim("$record->first_name $record->last_name"),
            'question' => $context['question_numbers']->get($record->question_id, '?'),
            'submitted_at' => null,
            'due' => $timing && $timing->due ? Carbon::parse($timing->due, 'UTC') : null,
            'final_submission_deadline' => $timing && $timing->final_submission_deadline
                ? Carbon::parse($timing->final_submission_deadline, 'UTC')
                : null,
            'base_score' => null,
            'old_pct' => null,
            'new_pct' => null,
            'old_score' => 0.0,
            'new_score' => 0.0,
            'status' => 'skip',
            'note' => '',
        ];
    }

    private function skip(array $row, string $note): array
    {
        $row['status'] = 'skip';
        $row['note'] = $note;
        return $row;
    }

    /**
     * Shared checks, the safety check, and the new score.
     */
    private function evaluate(Assignment $assignment, array $row, array $context, callable $scoreBeforeLatePenalty): array
    {
        $assignment_question = $context['assignment_questions']->get($row['question_id']);
        if (!$assignment_question) {
            return $this->skip($row, 'Question is no longer in the assignment');
        }
        if (isset($context['overrides']["{$row['user_id']}|{$row['question_id']}"])) {
            return $this->skip($row, 'Instructor score override in place; this score is not used');
        }
        if (!$row['due']) {
            return $this->skip($row, 'Student is not in any assign-to group');
        }

        $base_score = $scoreBeforeLatePenalty($assignment_question);
        if (is_string($base_score)) {
            return $this->skip($row, $base_score);
        }
        $row['base_score'] = $base_score;

        // Which late penalty explains the score stored today?
        foreach ($context['possible_penalties'] as $penalty) {
            if (abs($this->applyLatePenalty($base_score, $penalty) - $row['old_score']) < 0.001) {
                $row['old_pct'] = $penalty;
                break;
            }
        }
        if ($row['old_pct'] === null) {
            return $this->skip($row, 'Recomputed score (' . $this->num($base_score) . ' before late penalty) does not match the stored score under any late penalty; left as is');
        }

        $row['new_pct'] = $this->penaltyPercent($assignment, $row['due'], $row['submitted_at']);
        $row['new_score'] = $this->applyLatePenalty($base_score, $row['new_pct']);
        $row['status'] = abs($row['new_score'] - $row['old_score']) < 0.00005 ? 'same' : 'change';
        return $row;
    }

    /**
     * Every late penalty the policy can produce, e.g. [0, 10, 20, ..., 100] or [0, 25] for "once".
     */
    private function possiblePenalties(Assignment $assignment): array
    {
        $percent = (int)$assignment->late_deduction_percent;
        if ($assignment->late_deduction_application_period === 'once') {
            return [0, $percent];
        }
        $penalties = [];
        for ($i = 0; $i <= (int)floor(100 / $percent); $i++) {
            $penalties[] = $i * $percent;
        }
        return $penalties;
    }

    private function applyLatePenalty(float $score, int $penalty_percent): float
    {
        return round($score * (100 - $penalty_percent) / 100, 4);
    }

    /**
     * The score Submission::store() would give this submission before the late penalty,
     * or a string explaining why it can't be recomputed.
     *
     * @return float|string
     */
    private function autoGradedScoreBeforeLatePenalty(Assignment $assignment, $submission, $assignment_question, array $context)
    {
        $stored = json_decode($submission->submission);
        if ($stored === null) {
            return 'Saved response could not be read';
        }
        try {
            switch ($submission->technology) {
                case('h5p'):
                    if (isset($stored->object->id)) {
                        parse_str((string)parse_url($stored->object->id, PHP_URL_QUERY), $params);
                        if (isset($params['subContentId'])) {
                            return $this->h5pActivitySetScoreBeforeLatePenalty($assignment, $submission, $assignment_question, $context);
                        }
                    }
                    $proportion_correct = $this->submissionModel->getProportionCorrect('h5p', $stored);
                    break;
                case('imathas'):
                    $proportion_correct = $this->submissionModel->getProportionCorrect('imathas', $stored);
                    break;
                case('webwork'):
                    $proportion_correct = $this->submissionModel->getProportionCorrect('webwork', (object)$stored);
                    break;
                case('qti'):
                    // ADAPT saves the proportion correct alongside the QTI response.
                    if (!isset($stored->proportion_correct)) {
                        return 'QTI response has no saved proportion correct';
                    }
                    $proportion_correct = floatval($stored->proportion_correct);
                    break;
                default:
                    return "Technology '{$submission->technology}' is not auto-graded";
            }
        } catch (Exception $e) {
            return 'Saved response could not be scored: ' . $e->getMessage();
        }

        $score = $assignment->scoring_type === 'p'
            ? floatval($assignment_question->points) * $proportion_correct
            : $this->completionScore($assignment_question, $submission->user_id, $context);

        if (in_array($assignment->assessment_type, ['real time', 'learning tree'])) {
            // First submission: hint penalty only. Each accepted resubmission adds one attempt deduction.
            // (A learning-tree reset deletes the submission, so the count starts again after a reset.)
            $hint_penalty = isset($context['viewed_hints']["$submission->user_id|$submission->question_id"]) && $assignment->hint_penalty
                ? $assignment->hint_penalty
                : 0;
            $num_deductions = max((int)$submission->submission_count - 1, 0);
            $proportion_received = 1 - (($num_deductions * $assignment->number_of_allowed_attempts_penalty + $hint_penalty) / 100);
            $score = max($score * $proportion_received, 0);
        }
        return $score;
    }

    /**
     * H5P activity set, as in Submission::processh5pActivitySet(): each part is saved in h5p_activity_sets,
     * and the question score is (correct across every part / max score) x points. No attempt or hint penalties.
     *
     * @return float|string
     */
    private function h5pActivitySetScoreBeforeLatePenalty(Assignment $assignment, $submission, $assignment_question, array $context)
    {
        $max_score = $context['h5p_max_scores'][$submission->question_id] ?? null;
        if (!$max_score) {
            return 'H5P activity set has no saved max score';
        }
        $parts = $context['h5p_parts']["$submission->user_id|$submission->question_id"] ?? [];
        if (!$parts) {
            return 'H5P activity set has no saved parts';
        }
        $num_correct = 0;
        foreach ($parts as $part) {
            $decoded = json_decode($part);
            if (!isset($decoded->result->score)) {
                return 'An H5P activity set response could not be read';
            }
            $num_correct += $this->submissionModel->geth5pActivitySetNumCorrect($assignment, $decoded);
        }
        return ($num_correct / $max_score) * floatval($assignment_question->points);
    }

    /**
     * Submission::computeScoreForCompletion(), but for a given student rather than the logged-in user.
     */
    private function completionScore($assignment_question, int $user_id, array $context): float
    {
        $factor = 1;
        if (in_array($assignment_question->open_ended_submission_type, ['file', 'audio', 'text'])) {
            if ($assignment_question->completion_scoring_mode === '100% for either') {
                if (isset($context['open_ended_submissions']["$user_id|$assignment_question->question_id"])) {
                    $factor = 0;
                }
            } else {
                $factor = floatval(preg_replace('~\D~', '', $assignment_question->completion_scoring_mode)) / 100;
            }
        }
        return floatval($assignment_question->points) * $factor;
    }

    public function num(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    }
}
