<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Exceptions\MasteryRetakeConflictException;
use App\Services\MasteryAssignmentAttemptService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MasteryAssignmentAttemptController extends Controller
{
    /**
     * Start or idempotently return the next whole-assignment attempt for a student.
     */
    public function store(
        Request $request,
        Assignment $assignment,
        MasteryAssignmentAttemptService $attemptService
    ): JsonResponse {
        $authorized = Gate::inspect('view', $assignment);
        if (!$authorized->allowed() || $request->user()->role !== 3) {
            return response()->json([
                'type' => 'error',
                'reason' => 'not_authorized',
                'message' => 'You are not authorized to start this assignment attempt.'
            ], 403);
        }

        $validated = $request->validate([
            'previous_attempt_id' => 'required|integer|min:1'
        ]);

        try {
            $result = $attemptService->startNextAttempt(
                $assignment,
                $request->user(),
                (int)$validated['previous_attempt_id']
            );

            return response()->json([
                'type' => 'success',
                'message' => $result['already_started']
                    ? 'That assignment attempt has already started.'
                    : 'A new assignment attempt is ready.',
                'already_started' => $result['already_started'],
                'mastery_attempt' => $attemptService->payload($result['attempt'])
            ]);
        } catch (MasteryRetakeConflictException $e) {
            Log::warning('mastery_attempt.retake_rejected', [
                'assignment_id' => $assignment->id,
                'user_id' => $request->user()->id,
                'previous_attempt_id' => $validated['previous_attempt_id'],
                'reason' => $e->reason()
            ]);
            return response()->json([
                'type' => 'error',
                'reason' => $e->reason(),
                'message' => $e->getMessage()
            ], 409);
        } catch (Exception $e) {
            $handler = new Handler(app());
            $handler->report($e);
            return response()->json([
                'type' => 'error',
                'reason' => 'retake_failed',
                'message' => 'A new assignment attempt could not be prepared. Your completed attempt was not changed. Please try again.'
            ], 500);
        }
    }
}
