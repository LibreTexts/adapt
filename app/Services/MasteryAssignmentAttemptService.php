<?php

namespace App\Services;

use App\Assignment;
use App\Exceptions\MasteryRetakeConflictException;
use App\MasteryAssignmentAttempt;
use App\Question;
use App\Traits\Seed;
use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MasteryAssignmentAttemptService
{
    use Seed;

    const MAX_VARIANT_GENERATION_ATTEMPTS = 10;

    private $eligibility;

    /**
     * Create the attempt coordinator with the shared assignment eligibility validator.
     */
    public function __construct(MasteryRetakeEligibility $eligibility)
    {
        $this->eligibility = $eligibility;
    }

    /**
     * Return whether whole-assignment attempts are enabled for an assignment.
     */
    public function enabled(Assignment $assignment): bool
    {
        return (bool)$assignment->mastery_retake_enabled;
    }

    /**
     * Evaluate the assignment against the canonical whole-assignment eligibility rules.
     */
    public function eligibility(Assignment $assignment, bool $require_questions = true): MasteryRetakeEligibilityResult
    {
        return $this->eligibility->evaluate($assignment, [], $require_questions);
    }

    /**
     * Return the student's most recent persisted attempt, whether active or completed.
     */
    public function latestAttempt(Assignment $assignment, User $user): ?MasteryAssignmentAttempt
    {
        return MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->orderBy('attempt_number', 'desc')
            ->first();
    }

    /**
     * Return the current attempt at assignment launch, creating attempt one when needed.
     */
    public function getOrCreateForLaunch(Assignment $assignment, User $user, array $question_ids): MasteryAssignmentAttempt
    {
        $question_ids = array_values(array_map('intval', $question_ids));
        $existing_attempt = $this->latestAttempt($assignment, $user);
        if ($existing_attempt) {
            if ($user->fake_student
                && array_values(array_map('intval', $existing_attempt->question_ids)) !== $question_ids) {
                return $this->replaceChangedFakeStudentAttempt($assignment, $user, $question_ids);
            }
            return $existing_attempt;
        }

        $eligibility = $this->eligibility($assignment);
        if (!$eligibility->eligible()) {
            throw new MasteryRetakeConflictException('ineligible_assignment', $eligibility->firstMessage());
        }

        try {
            return DB::transaction(function () use ($assignment, $user, $question_ids) {
                $latest = MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
                    ->where('user_id', $user->id)
                    ->orderBy('attempt_number', 'desc')
                    ->lockForUpdate()
                    ->first();

                if ($latest) {
                    return $latest;
                }

                return $this->createInitialAttempt($assignment, $user, $question_ids);
            });
        } catch (QueryException $e) {
            // A concurrent initial launch may win the unique attempt-number insert.
            $attempt = $this->latestAttempt($assignment, $user);
            if ($attempt) {
                return $attempt;
            }
            throw $e;
        }
    }

    /**
     * Reject callbacks that do not belong to the student's active assignment attempt.
     */
    public function validateSubmission(
        Assignment $assignment,
        User $user,
        $attempt_id,
        ?int $question_id = null
    ): ?MasteryAssignmentAttempt
    {
        if (!$this->enabled($assignment) || $user->role !== 3) {
            return null;
        }

        if (!$attempt_id) {
            $this->logStaleSubmission($assignment, $user, null, 'missing_attempt_id');
            throw new MasteryRetakeConflictException(
                'missing_attempt_id',
                'This submission is missing its assignment attempt identifier. Refresh the assignment and try again.'
            );
        }

        $attempt = MasteryAssignmentAttempt::where('id', $attempt_id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$attempt || $attempt->status !== MasteryAssignmentAttempt::STATUS_IN_PROGRESS) {
            $this->logStaleSubmission($assignment, $user, $attempt_id, 'stale_attempt');
            throw new MasteryRetakeConflictException(
                'stale_attempt',
                'This question belongs to an earlier assignment attempt. Refresh the assignment before submitting.'
            );
        }

        if ($question_id !== null && !in_array($question_id, array_map('intval', $attempt->question_ids), true)) {
            $this->logStaleSubmission($assignment, $user, $attempt_id, 'question_not_in_attempt');
            throw new MasteryRetakeConflictException(
                'question_not_in_attempt',
                'This question does not belong to the active assignment attempt. Refresh the assignment and try again.'
            );
        }

        return $attempt;
    }

    /**
     * Lock and recheck the active attempt inside the submission transaction.
     */
    public function lockForSubmission(Assignment $assignment, User $user, int $attempt_id): MasteryAssignmentAttempt
    {
        $attempt = MasteryAssignmentAttempt::where('id', $attempt_id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (!$attempt || $attempt->status !== MasteryAssignmentAttempt::STATUS_IN_PROGRESS) {
            $this->logStaleSubmission($assignment, $user, $attempt_id, 'attempt_closed_during_submission');
            throw new MasteryRetakeConflictException(
                'stale_attempt',
                'This assignment attempt has already closed. Refresh the assignment before submitting.'
            );
        }

        return $attempt;
    }

    /**
     * Complete an attempt after every snapshotted question has one recorded response.
     * The caller must hold the submission transaction and attempt row lock.
     */
    public function completeIfReady(Assignment $assignment, User $user, MasteryAssignmentAttempt $attempt): array
    {
        $question_ids = array_values(array_map('intval', $attempt->question_ids));
        $submissions = DB::table('submissions')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->whereIn('question_id', $question_ids)
            ->get()
            ->keyBy('question_id');

        if ($submissions->count() !== count($question_ids)) {
            return ['completed' => false, 'attempt' => $attempt];
        }

        $assignment_questions = DB::table('assignment_question')
            ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
            ->where('assignment_question.assignment_id', $assignment->id)
            ->whereIn('assignment_question.question_id', $question_ids)
            ->select(
                'assignment_question.question_id',
                'assignment_question.points',
                'questions.technology'
            )
            ->get()
            ->keyBy('question_id');
        $variants = DB::table('seeds')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->whereIn('question_id', $question_ids)
            ->pluck('seed', 'question_id')
            ->toArray();

        $score = 0.0;
        $possible_score = 0.0;
        $all_correct = true;
        $question_results = [];

        foreach ($question_ids as $question_id) {
            $submission = $submissions->get($question_id);
            $assignment_question = $assignment_questions->get($question_id);
            if (!$submission || !$assignment_question) {
                throw new RuntimeException('The assignment attempt question set changed while the attempt was active.');
            }

            $question_score = (float)$submission->score;
            $question_possible_score = (float)$assignment_question->points;
            $correct = (bool)$submission->answered_correctly_at_least_once;
            $score += $question_score;
            $possible_score += $question_possible_score;
            $all_correct = $all_correct && $correct;
            $question_results[] = [
                'question_id' => $question_id,
                'technology' => $assignment_question->technology,
                'variant_identifier' => $variants[$question_id] ?? null,
                'submission' => $submission->submission,
                'score' => $question_score,
                'possible_score' => $question_possible_score,
                'correct' => $correct,
                'submitted_at' => $submission->updated_at
            ];
        }

        if ($possible_score <= 0) {
            throw new RuntimeException('An assignment attempt must have a positive possible score.');
        }

        $attempt->status = $all_correct
            ? MasteryAssignmentAttempt::STATUS_MASTERED
            : MasteryAssignmentAttempt::STATUS_COMPLETED;
        $attempt->variant_identifiers = $variants;
        $attempt->question_results = $question_results;
        $attempt->score = $score;
        $attempt->possible_score = $possible_score;
        $attempt->completed_at = now();
        $attempt->save();

        Log::info('mastery_attempt.completed', $this->logContext($attempt) + [
            'score' => $score,
            'possible_score' => $possible_score,
            'mastered' => $all_correct
        ]);

        return ['completed' => true, 'attempt' => $attempt];
    }

    /**
     * Replace current response state and start the next whole-assignment attempt.
     */
    public function startNextAttempt(Assignment $assignment, User $user, int $previous_attempt_id): array
    {
        $eligibility = $this->eligibility($assignment);
        if (!$eligibility->eligible()) {
            throw new MasteryRetakeConflictException('ineligible_assignment', $eligibility->firstMessage());
        }

        return DB::transaction(function () use ($assignment, $user, $previous_attempt_id) {
            $latest = MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->orderBy('attempt_number', 'desc')
                ->lockForUpdate()
                ->first();

            if (!$latest) {
                throw new MasteryRetakeConflictException('no_completed_attempt', 'There is no completed assignment attempt to restart.');
            }

            if ($latest->status === MasteryAssignmentAttempt::STATUS_IN_PROGRESS) {
                $previous = MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
                    ->where('user_id', $user->id)
                    ->where('attempt_number', $latest->attempt_number - 1)
                    ->first();
                if ($previous && $previous->id === $previous_attempt_id) {
                    return ['attempt' => $latest, 'already_started' => true];
                }
                throw new MasteryRetakeConflictException('attempt_in_progress', 'An assignment attempt is already in progress.');
            }

            if ($latest->id !== $previous_attempt_id) {
                throw new MasteryRetakeConflictException('stale_retake', 'The requested assignment attempt is no longer current. Refresh the assignment.');
            }

            if (!$this->canRetake($latest)) {
                throw new MasteryRetakeConflictException(
                    'attempt_limit_reached',
                    'You have used all available assignment attempts.'
                );
            }

            $question_ids = array_values(array_map('intval', $latest->question_ids));
            $old_variants = $latest->variant_identifiers ?: [];
            $new_variants = [];

            foreach ($question_ids as $question_id) {
                $question = Question::find($question_id);
                if (!$question || !in_array($question->technology, ['qti', 'webwork', 'imathas'], true)) {
                    throw new MasteryRetakeConflictException(
                        'question_set_changed',
                        'The assignment question set is no longer eligible for whole-assignment attempts.'
                    );
                }
                $old_variant = $old_variants[$question_id] ?? $old_variants[(string)$question_id] ?? null;
                $new_variants[$question_id] = $this->nextVariant($assignment, $question, $old_variant);
            }

            $this->clearActiveAttemptState($assignment, $user);
            foreach ($new_variants as $question_id => $variant_identifier) {
                DB::table('seeds')->insert([
                    'assignment_id' => $assignment->id,
                    'question_id' => $question_id,
                    'user_id' => $user->id,
                    'seed' => $variant_identifier,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $attempt = MasteryAssignmentAttempt::create([
                'assignment_id' => $assignment->id,
                'user_id' => $user->id,
                'attempt_number' => $latest->attempt_number + 1,
                'status' => MasteryAssignmentAttempt::STATUS_IN_PROGRESS,
                'question_ids' => $question_ids,
                'variant_identifiers' => $new_variants
            ]);

            Log::info('mastery_attempt.retake_started', $this->logContext($attempt) + [
                'previous_attempt_id' => $latest->id
            ]);

            return ['attempt' => $attempt, 'already_started' => false];
        });
    }

    /**
     * Format the attempt state consumed by the student assignment interface.
     */
    public function payload(?MasteryAssignmentAttempt $attempt): ?array
    {
        if (!$attempt) {
            return null;
        }
        return [
            'id' => $attempt->id,
            'number' => $attempt->attempt_number,
            'status' => $attempt->status,
            'score' => $attempt->score,
            'possible_score' => $attempt->possible_score,
            'can_retake' => $this->canRetake($attempt)
        ];
    }

    /**
     * Return whether a non-preview student has started a whole-assignment attempt.
     */
    public function hasRealStudentAttempts(Assignment $assignment): bool
    {
        return MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
            ->join('users', 'mastery_assignment_attempts.user_id', '=', 'users.id')
            ->where('users.fake_student', 0)
            ->where('users.formative_student', 0)
            ->where('users.role', 3)
            ->exists();
    }

    /**
     * Rebuild fake-student preview state after an instructor changes question membership.
     */
    private function replaceChangedFakeStudentAttempt(
        Assignment $assignment,
        User $user,
        array $question_ids
    ): MasteryAssignmentAttempt
    {
        $eligibility = $this->eligibility($assignment);
        if (!$eligibility->eligible()) {
            throw new MasteryRetakeConflictException('ineligible_assignment', $eligibility->firstMessage());
        }

        return DB::transaction(function () use ($assignment, $user, $question_ids) {
            $latest = MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->orderBy('attempt_number', 'desc')
                ->lockForUpdate()
                ->first();
            if ($latest && array_values(array_map('intval', $latest->question_ids)) === $question_ids) {
                return $latest;
            }

            $this->clearActiveAttemptState($assignment, $user);
            MasteryAssignmentAttempt::where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->delete();
            return $this->createInitialAttempt($assignment, $user, $question_ids);
        });
    }

    /**
     * Persist the first in-progress attempt for the supplied question snapshot.
     */
    private function createInitialAttempt(
        Assignment $assignment,
        User $user,
        array $question_ids
    ): MasteryAssignmentAttempt
    {
        $attempt = MasteryAssignmentAttempt::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'attempt_number' => 1,
            'status' => MasteryAssignmentAttempt::STATUS_IN_PROGRESS,
            'question_ids' => $question_ids
        ]);

        Log::info('mastery_attempt.created', $this->logContext($attempt));
        return $attempt;
    }

    /**
     * Return whether the completed attempt is below the instructor's attempt limit.
     */
    private function canRetake(MasteryAssignmentAttempt $attempt): bool
    {
        if (!in_array($attempt->status, [
            MasteryAssignmentAttempt::STATUS_COMPLETED,
            MasteryAssignmentAttempt::STATUS_MASTERED
        ], true)) {
            return false;
        }

        $limit = $attempt->assignment->mastery_number_of_allowed_attempts ?: 'unlimited';
        return $limit === 'unlimited' || $attempt->attempt_number < (int)$limit;
    }

    /**
     * Generate a variant identifier different from the preceding attempt.
     */
    private function differentVariant(Assignment $assignment, Question $question, $old_variant)
    {
        for ($generation_attempt = 1; $generation_attempt <= self::MAX_VARIANT_GENERATION_ATTEMPTS; $generation_attempt++) {
            $variant = $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $question);
            if ((string)$variant !== (string)$old_variant) {
                return $variant;
            }
        }

        Log::warning('mastery_attempt.variant_generation_failed', [
            'assignment_id' => $assignment->id,
            'question_id' => $question->id,
            'maximum_attempts' => self::MAX_VARIANT_GENERATION_ATTEMPTS
        ]);
        throw new RuntimeException('A different problem version could not be generated. Please try starting the assignment attempt again.');
    }

    /**
     * Reuse fixed versions and require a new version only for algorithmic providers.
     */
    private function nextVariant(Assignment $assignment, Question $question, $old_variant)
    {
        if ($question->technology === 'qti' || !$assignment->algorithmic) {
            return $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $question);
        }

        return $this->differentVariant($assignment, $question, $old_variant);
    }

    /**
     * Clear only current-attempt records before installing the next set of variants.
     */
    private function clearActiveAttemptState(Assignment $assignment, User $user): void
    {
        // Starting another attempt clears current responses and feedback; the completed snapshot
        // remains authoritative. Ownership of the current-state tables follows these existing paths:
        // - submissions, seeds, can_give_ups, shown_hints, and submission_histories
        //   mirror SubmissionController::resetSubmission().
        // - unconfirmed_submissions is written by JWTController before confirmed WeBWorK grading.
        // - submission_confirmations is written by SubmissionConfirmationController.
        $tables = [
            'submissions',
            'seeds',
            'can_give_ups',
            'shown_hints',
            'submission_histories',
            'unconfirmed_submissions',
            'submission_confirmations'
        ];
        foreach ($tables as $table) {
            DB::table($table)
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->delete();
        }
    }

    /**
     * Record enough context to diagnose a rejected late or duplicate callback.
     */
    private function logStaleSubmission(Assignment $assignment, User $user, $attempt_id, string $reason): void
    {
        Log::warning('mastery_attempt.stale_submission', [
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'attempt_id' => $attempt_id,
            'reason' => $reason
        ]);
    }

    /**
     * Build the common structured-log context for an attempt lifecycle event.
     */
    private function logContext(MasteryAssignmentAttempt $attempt): array
    {
        return [
            'assignment_id' => $attempt->assignment_id,
            'user_id' => $attempt->user_id,
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status
        ];
    }
}
