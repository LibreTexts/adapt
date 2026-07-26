<?php

namespace App\Services;

use App\Assignment;
use Illuminate\Support\Facades\DB;

class MasteryRetakeEligibility
{
    /**
     * Return stable eligibility reasons for assignment properties and assigned questions.
     */
    public function evaluate(Assignment $assignment, array $overrides = [], bool $require_questions = true): MasteryRetakeEligibilityResult
    {
        $reasons = [];
        $value = function (string $property) use ($assignment, $overrides) {
            return array_key_exists($property, $overrides)
                ? $overrides[$property]
                : $assignment->{$property};
        };

        $this->require($reasons,
            $value('assessment_type') === 'real time',
            'assessment_type',
            'Whole-assignment attempts require a real-time assignment.');
        $this->require($reasons,
            $value('scoring_type') === 'p',
            'scoring_type',
            'Whole-assignment attempts require point-based scoring.');
        $this->require($reasons,
            (string)$value('number_of_allowed_attempts') === '1',
            'question_attempts',
            'Whole-assignment attempts require one response per question.');
        $this->require($reasons,
            (int)$value('randomizations') === 0,
            'random_sampling',
            'Whole-assignment attempts do not currently support ADAPT question sampling.');
        $this->require($reasons,
            empty($value('number_of_randomized_assessments')),
            'random_sampling',
            'Whole-assignment attempts do not currently support ADAPT question sampling.');
        $this->require($reasons,
            !(bool)$value('can_submit_work'),
            'submitted_work',
            'Whole-assignment attempts do not support required submitted work.');
        $this->require($reasons,
            $this->percentIsZero($value('hint_penalty')),
            'hint_penalty',
            'Whole-assignment attempts require a zero hint penalty.');
        $this->require($reasons,
            $this->percentIsZero($value('number_of_allowed_attempts_penalty')),
            'attempt_penalty',
            'Whole-assignment attempts require a zero attempt penalty.');
        $this->require($reasons,
            $value('late_policy') !== 'deduction',
            'late_deduction',
            'Whole-assignment attempts do not support late score deductions.');

        if (!$assignment->exists) {
            return new MasteryRetakeEligibilityResult($reasons);
        }

        $this->require($reasons,
            !(bool)$assignment->course->anonymous_users,
            'anonymous_users',
            'Whole-assignment attempts are not available in courses that allow anonymous users.');

        $questions = DB::table('assignment_question')
            ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
            ->where('assignment_question.assignment_id', $assignment->id)
            ->select(
                'questions.id',
                'questions.technology',
                'assignment_question.open_ended_submission_type',
                'assignment_question.points'
            )
            ->get();

        if ($require_questions) {
            $this->require($reasons,
                $questions->isNotEmpty(),
                'no_questions',
                'Whole-assignment attempts require at least one assigned question.');
        }

        foreach ($questions as $question) {
            if (!in_array($question->technology, ['qti', 'webwork', 'imathas'], true)) {
                $this->addReason($reasons,
                    'unsupported_question',
                    "Question {$question->id} is not a supported auto-graded question.");
            }
            if ((string)$question->open_ended_submission_type !== '0') {
                $this->addReason($reasons,
                    'open_ended_question',
                    "Question {$question->id} includes an open-ended response.");
            }
            if (!is_numeric($question->points) || (float)$question->points <= 0) {
                $this->addReason($reasons,
                    'invalid_question_points',
                    "Question {$question->id} must have more than zero points.");
            }
        }

        return new MasteryRetakeEligibilityResult($reasons);
    }

    /**
     * Add an eligibility reason when a required condition is not met.
     */
    private function require(array &$reasons, bool $condition, string $code, string $message): void
    {
        if (!$condition) {
            $this->addReason($reasons, $code, $message);
        }
    }

    /**
     * Add a unique machine-readable reason and its instructor-facing message.
     */
    private function addReason(array &$reasons, string $code, string $message): void
    {
        foreach ($reasons as $reason) {
            if ($reason['code'] === $code && $reason['message'] === $message) {
                return;
            }
        }
        $reasons[] = compact('code', 'message');
    }

    /**
     * Treat empty percentage fields and numeric zero as a zero penalty.
     */
    private function percentIsZero($value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        return (float)str_replace('%', '', (string)$value) === 0.0;
    }
}
