<?php

namespace App\Jobs;

use App\Assignment;
use App\Exceptions\Handler;
use App\PassbackByAssignment;
use App\Services\LatePenaltyRecalculator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalculates an assignment's late penalties in the background after the instructor agrees to it,
 * so a large class can't run into the web request time limit. Recalculates from the current due dates
 * (not the preview the instructor saw), saves the scores, updates each student's HW score, and then,
 * for automatic LMS passback, asks the scheduled passback:manualPendingScores command to send the grades.
 * Running it twice is harmless: the second run finds nothing to change.
 */
class ProcessLatePenaltyRecalculation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $assignment_id;

    public function __construct(int $assignment_id)
    {
        $this->assignment_id = $assignment_id;
    }

    /**
     * @return void
     */
    public function handle(LatePenaltyRecalculator $latePenaltyRecalculator)
    {
        try {
            $assignment = Assignment::find($this->assignment_id);
            if (!$assignment || $latePenaltyRecalculator->notApplicableReason($assignment)) {
                return;
            }
            $changes = $latePenaltyRecalculator->changes($latePenaltyRecalculator->rows($assignment));
            if (!$changes) {
                return;
            }
            $user_ids = $latePenaltyRecalculator->save($assignment, $changes);
            $latePenaltyRecalculator->updateAssignmentScores($assignment, $user_ids, false);
            if ($assignment->lms_grade_passback === 'automatic') {
                $passbackByAssignment = new PassbackByAssignment();
                $passbackByAssignment->assignment_id = $assignment->id;
                $passbackByAssignment->status = 'manual_pending';
                $passbackByAssignment->save();
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
