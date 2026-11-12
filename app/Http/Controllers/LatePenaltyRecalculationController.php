<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Jobs\ProcessLatePenaltyRecalculation;
use App\Services\LatePenaltyRecalculator;
use Exception;
use Illuminate\Support\Facades\Gate;

/**
 * Lets an instructor recalculate late penalties after changing an assignment's due date.
 * The assignment page calls show() when a save reports check_late_penalties, and asks the
 * instructor only if some scores would change; update() then applies the change.
 */
class LatePenaltyRecalculationController extends Controller
{
    /**
     * What recalculating would change, without saving anything.
     */
    public function show(Assignment $assignment, LatePenaltyRecalculator $latePenaltyRecalculator): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['num_changes'] = 0;
            $response['students'] = [];
            $response['num_skipped'] = 0;
            $response['lms_grade_passback'] = $assignment->lms_grade_passback;
            $response['total_points'] = round($latePenaltyRecalculator->totalPoints($assignment), 4);
            $response['num_hand_applied_late_penalties'] = 0;
            $response['submitted_after_final_deadline'] = [];

            $reason = $latePenaltyRecalculator->notApplicableReason($assignment);
            if ($reason) {
                $response['type'] = 'success';
                $response['message'] = $reason;
                return $response;
            }

            $rows = $latePenaltyRecalculator->rows($assignment);
            foreach ($latePenaltyRecalculator->studentSummaries($assignment, $rows) as $student) {
                if ($student['changes']) {
                    $response['students'][] = [
                        'name' => $student['student'],
                        'current_score' => round($student['current'], 4),
                        'new_score' => round($student['new'], 4),
                        'change' => round($student['delta'], 4),
                        'pending_change' => round($student['pending_delta'], 4),
                    ];
                }
            }
            $response['num_changes'] = count($latePenaltyRecalculator->changes($rows));
            $response['num_skipped'] = count(array_filter($rows, function ($row) {
                return $row['status'] === 'skip';
            }));
            $response['num_hand_applied_late_penalties'] = $latePenaltyRecalculator->handAppliedLatePenaltyCount($assignment);
            foreach ($latePenaltyRecalculator->submittedAfterFinalDeadline($assignment, $rows) as $student) {
                $response['submitted_after_final_deadline'][] = [
                    'name' => $student['student'],
                    'questions' => $student['questions'],
                ];
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to check the late penalties for <strong>$assignment->name</strong>. Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Queues the recalculation (ProcessLatePenaltyRecalculation) and answers right away, so a large class
     * can't hit the web request time limit. The job recalculates from the current due dates, in case
     * anything changed since the preview.
     */
    public function update(Assignment $assignment, LatePenaltyRecalculator $latePenaltyRecalculator): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $reason = $latePenaltyRecalculator->notApplicableReason($assignment);
            if ($reason) {
                $response['message'] = $reason;
                return $response;
            }
            ProcessLatePenaltyRecalculation::dispatch($assignment->id);

            $message = 'The new scores will appear within a few minutes';
            switch ($assignment->lms_grade_passback) {
                case('automatic'):
                    $message .= ' and will then be passed back to your LMS.';
                    break;
                case('manual'):
                    $message .= '. Pass the grades back to your LMS when you are ready.';
                    break;
                default:
                    $message .= '.';
            }
            $response['type'] = 'success';
            $response['message'] = $message;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to recalculate the late penalties for <strong>$assignment->name</strong>. Please try again or contact us for assistance.";
        }
        return $response;
    }
}
