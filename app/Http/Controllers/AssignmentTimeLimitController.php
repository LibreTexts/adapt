<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Traits\DateFormatter;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AssignmentTimeLimitController extends Controller
{
    use DateFormatter;

    /**
     * Student-facing: tells the frontend whether to show a "Start" button
     * or a running countdown for the current user.
     *
     * @param Assignment $assignment
     * @return array
     */
    public function getStatus(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('view', $assignment);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $user = Auth::user();
            $response = $this->buildTimeLimitStatus($assignment, $user);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error getting the time limit status. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Instructor-facing: same shape as getStatus(), but for a specific
     * student rather than the authenticated user - this is what feeds the
     * per-student start/expiry display in the grading view.
     *
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    public function getStatusForStudent(Assignment $assignment, User $user): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('manageAssignmentTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $response = $this->buildTimeLimitStatus($assignment, $user);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error getting the time limit status for this student. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Shared by getStatus (self) and getStatusForStudent (instructor, on
     * behalf of a student) so the two never drift out of sync with each
     * other's response shape.
     *
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    private function buildTimeLimitStatus(Assignment $assignment, User $user): array
    {
        $response['type'] = 'error';
        $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
        if (!$assign_to_timing || !$assign_to_timing->time_limit) {
            $response['type'] = 'success';
            $response['time_limit'] = null;
            return $response;
        }

        $timing_start = DB::table('assign_to_timing_starts')
            ->where('assign_to_timing_id', $assign_to_timing->id)
            ->where('user_id', $user->id)
            ->first();

        $response['type'] = 'success';
        $response['time_limit'] = $assign_to_timing->time_limit;
        $response['due'] = $assign_to_timing->due;
        $start = Carbon::now();
        $response['time_limit_seconds'] = $start->copy()->add(CarbonInterval::make($assign_to_timing->time_limit))->getTimestamp() - $start->getTimestamp();
        $response['started_at'] = ($timing_start && $timing_start->started_at)
            ? $this->convertUTCMysqlFormattedDateToLocalDateAndTime($timing_start->started_at, Auth::user()->time_zone)
            : null;
        $response['expires_at'] = ($timing_start && $timing_start->expires_at)
            ? $this->convertUTCMysqlFormattedDateToLocalDateAndTime($timing_start->expires_at, Auth::user()->time_zone)
            : null;
        $response['extended_past_due'] = (bool)($timing_start->extended_past_due ?? false);
        $response['time_limit_override'] = $timing_start->time_limit_override ?? null;
        $response['instructor_adjusted'] = (bool)($timing_start->instructor_adjusted ?? false);
        $response['seconds_left'] = ($timing_start && $timing_start->expires_at)
            ? max(0, strtotime($timing_start->expires_at) - time())
            : null;
        return $response;
    }

    /**
     * Student-facing: starts the student's personal clock. Idempotent -
     * calling it again after a clock is already running just returns the
     * existing expiry rather than restarting it.
     *
     * @param Assignment $assignment
     * @return array
     */
    public function start(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('view', $assignment);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $user = Auth::user();
            $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
            if (!$assign_to_timing) {
                $response['message'] = 'You are not assigned to this assignment.';
                return $response;
            }
            if (!$assign_to_timing->time_limit) {
                $response['message'] = 'This assignment does not have a time limit.';
                return $response;
            }
            if (strtotime($assign_to_timing->available_from) > time()) {
                $response['message'] = 'This assignment is not yet available.';
                return $response;
            }
            if (strtotime($assign_to_timing->due) < time() && !$user->fake_student) {
                $response['message'] = 'This assignment is past due and can no longer be started.';
                return $response;
            }

            $existing = DB::table('assign_to_timing_starts')
                ->where('assign_to_timing_id', $assign_to_timing->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing && $existing->started_at) {
                // Already started - hand back the existing expiry rather than
                // resetting the clock (that's what the instructor add/set
                // endpoints below are for).
                $response['type'] = 'success';
                $response['expires_at'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($existing->expires_at, Auth::user()->time_zone);
                $response['seconds_left'] = max(0, strtotime($existing->expires_at) - time());
                return $response;
            }

            $now = Carbon::now();
            $interval = CarbonInterval::make($assign_to_timing->time_limit);
            $expires_at = $now->copy()->add($interval);

            // Automatic starts always cap at the group's due date - only an
            // explicit instructor add/set action (below) can push a student's
            // personal expiry past due. Fake students are exempt: they're
            // only reaching this line at all because the due-date check
            // above let them start past due for testing purposes, and
            // capping them here would hand them an already-expired timer.
            $due = Carbon::parse($assign_to_timing->due);
            if ($expires_at->greaterThan($due) && !$user->fake_student) {
                $expires_at = $due;
            }

            DB::table('assign_to_timing_starts')->updateOrInsert(
                ['assign_to_timing_id' => $assign_to_timing->id, 'user_id' => $user->id],
                ['started_at' => $now, 'expires_at' => $expires_at, 'updated_at' => $now, 'created_at' => $now]
            );

            $response['type'] = 'success';
            $response['expires_at'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($expires_at->toDateTimeString(), Auth::user()->time_zone);
            // getTimestamp() is an unambiguous UTC epoch, so this is immune to
            // the naive-datetime-string timezone-parsing problem the frontend
            // would otherwise hit trying to compute this itself from expires_at.
            $response['seconds_left'] = max(0, $expires_at->getTimestamp() - time());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error starting the timer for this assignment. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Instructor-facing: add minutes to a specific student's running clock.
     * Can push expires_at past the assign-to group's due date - the frontend
     * is expected to have already confirmed that with the instructor before
     * calling this (see extended_past_due in the response, which the
     * frontend should surface even if it didn't catch it beforehand).
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    public function addTime(Request $request, Assignment $assignment, User $user): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('manageAssignmentTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
            if (!$assign_to_timing || !$assign_to_timing->time_limit) {
                $response['message'] = 'This student does not have a time limit set for this assignment.';
                return $response;
            }

            $timing_start = DB::table('assign_to_timing_starts')
                ->where('assign_to_timing_id', $assign_to_timing->id)
                ->where('user_id', $user->id)
                ->first();
            if (!$timing_start || !$timing_start->started_at) {
                $response['message'] = 'This student has not started the assignment yet, so there is no clock to add time to.';
                return $response;
            }

            $minutes_to_add = (int)$request->minutes;
            $current_expires_at = Carbon::parse($timing_start->expires_at);
            // If already expired, "add time" only means something if it's
            // measured from now - adding 10 minutes to a timer that expired
            // 3 hours ago wouldn't give the student any usable time at all.
            // The frontend is expected to confirm with the instructor first
            // when this path is taken (see was_expired in the response).
            $was_expired = $current_expires_at->isPast();
            $base = $was_expired ? Carbon::now() : $current_expires_at;
            $new_expires_at = $base->copy()->addMinutes($minutes_to_add);

            $due = Carbon::parse($assign_to_timing->due);
            $extended_past_due = $new_expires_at->greaterThan($due);

            DB::table('assign_to_timing_starts')
                ->where('id', $timing_start->id)
                ->update([
                    'expires_at' => $new_expires_at,
                    'extended_past_due' => $extended_past_due,
                    'instructor_adjusted' => true,
                    'updated_at' => Carbon::now(),
                ]);

            $response['type'] = 'success';
            $response['expires_at'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($new_expires_at->toDateTimeString(), Auth::user()->time_zone);
            $response['seconds_left'] = max(0, $new_expires_at->getTimestamp() - time());
            $response['was_expired'] = $was_expired;
            $response['extended_past_due'] = $extended_past_due;
            $this->publishTimeLimitUpdate($assignment, $user->id, [
                'seconds_left' => $response['seconds_left'],
                'extended_past_due' => $extended_past_due,
            ]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error adding time for this student. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Instructor-facing: set an explicit new duration for a specific student,
     * recomputed from when they started (or from now, if starting fresh on
     * their behalf). Same "may exceed due" behavior as addTime.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    public function setTime(Request $request, Assignment $assignment, User $user): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('manageAssignmentTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
            if (!$assign_to_timing || !$assign_to_timing->time_limit) {
                $response['message'] = 'This student does not have a time limit set for this assignment.';
                return $response;
            }

            try {
                $interval = CarbonInterval::make($request->time_limit);
            } catch (Exception $e) {
                $interval = null;
            }
            if (!$interval) {
                $response['message'] = 'That is not a valid duration.';
                return $response;
            }

            $timing_start = DB::table('assign_to_timing_starts')
                ->where('assign_to_timing_id', $assign_to_timing->id)
                ->where('user_id', $user->id)
                ->first();

            // A total duration only means "from when they started" while
            // they're still actively running. For a student who never
            // started, or whose timer already fully expired, recomputing
            // from a stale (or absent) started_at would almost always still
            // land in the past - both cases should give them a fresh window
            // starting now, the same way addTime() already treats an
            // already-expired timer.
            $was_expired = $timing_start && $timing_start->started_at && Carbon::parse($timing_start->expires_at)->isPast();
            $is_still_running = $timing_start && $timing_start->started_at && !$was_expired;
            $started_at = $is_still_running ? Carbon::parse($timing_start->started_at) : Carbon::now();
            $new_expires_at = $started_at->copy()->add($interval);

            $due = Carbon::parse($assign_to_timing->due);
            $extended_past_due = $new_expires_at->greaterThan($due);

            DB::table('assign_to_timing_starts')->updateOrInsert(
                ['assign_to_timing_id' => $assign_to_timing->id, 'user_id' => $user->id],
                [
                    'started_at' => $started_at,
                    'expires_at' => $new_expires_at,
                    'time_limit_override' => $request->time_limit,
                    'extended_past_due' => $extended_past_due,
                    'instructor_adjusted' => true,
                    'updated_at' => Carbon::now(),
                ]
            );

            $response['type'] = 'success';
            $response['expires_at'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($new_expires_at->toDateTimeString(), Auth::user()->time_zone);
            $response['seconds_left'] = max(0, $new_expires_at->getTimestamp() - time());
            $response['was_expired'] = $was_expired;
            $response['extended_past_due'] = $extended_past_due;
            $this->publishTimeLimitUpdate($assignment, $user->id, [
                'seconds_left' => $response['seconds_left'],
                'extended_past_due' => $extended_past_due,
            ]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error setting the time limit for this student. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Fake-student / instructor-testing only: deletes the current user's own
     * timing-start row so they can click "Start Assignment" again. This is
     * deliberately scoped to the authenticated user's own row and gated to
     * fake_student/instructor - a real student can never reset their own
     * clock, which is the whole point of the feature.
     *
     * @param Assignment $assignment
     * @return array
     */
    public function resetTimer(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('resetOwnTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $user = Auth::user();
            $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
            if (!$assign_to_timing) {
                $response['message'] = 'You are not assigned to this assignment.';
                return $response;
            }

            DB::table('assign_to_timing_starts')
                ->where('assign_to_timing_id', $assign_to_timing->id)
                ->where('user_id', $user->id)
                ->delete();

            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error resetting the timer. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Instructor-facing: fully resets a specific student's clock - wipes the
     * timing-start row entirely, so the student sees the Start Assignment
     * screen again and gets a full fresh duration whenever they click it.
     * This is deliberately destructive (loses the original started_at) -
     * for extending an already-running or already-expired clock without
     * losing that history, use addTime/setTime instead.
     *
     * @param Assignment $assignment
     * @param User $user
     * @return array
     */
    public function resetTimerForStudent(Assignment $assignment, User $user): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('manageAssignmentTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $assign_to_timing = $assignment->assignToTimingByUser('', $user->id);
            if (!$assign_to_timing) {
                $response['message'] = 'This student is not assigned to this assignment.';
                return $response;
            }

            DB::table('assign_to_timing_starts')
                ->where('assign_to_timing_id', $assign_to_timing->id)
                ->where('user_id', $user->id)
                ->delete();

            $response['type'] = 'success';
            $this->publishTimeLimitUpdate($assignment, $user->id, ['seconds_left' => null]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error resetting the timer for this student. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Instructor-facing: lists every real student who has started their
     * personal timer for this assignment, across all assign-to groups - lets
     * the Overrides page show what's currently happening without the
     * instructor picking through students one at a time from the dropdown.
     * Fake students are excluded, same as elsewhere in this feature's
     * instructor-facing views.
     *
     * @param Assignment $assignment
     * @return array
     */
    public function getAllStatuses(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('manageAssignmentTimeLimit', [$assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $assign_to_timing_ids = DB::table('assign_to_timings')
                ->where('assignment_id', $assignment->id)
                ->pluck('id');

            $rows = DB::table('assign_to_timing_starts')
                ->join('users', 'assign_to_timing_starts.user_id', '=', 'users.id')
                ->join('assign_to_timings', 'assign_to_timing_starts.assign_to_timing_id', '=', 'assign_to_timings.id')
                ->whereIn('assign_to_timing_starts.assign_to_timing_id', $assign_to_timing_ids)
                ->where('users.fake_student', 0)
                ->orderBy('users.last_name')
                ->orderBy('users.first_name')
                ->select(
                    'users.id AS user_id',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS name"),
                    'assign_to_timings.time_limit',
                    'assign_to_timing_starts.started_at',
                    'assign_to_timing_starts.expires_at',
                    'assign_to_timing_starts.extended_past_due',
                    'assign_to_timing_starts.time_limit_override',
                    'assign_to_timing_starts.instructor_adjusted'
                )
                ->get();

            $statuses = [];
            foreach ($rows as $row) {
                $statuses[] = [
                    'user_id' => $row->user_id,
                    'name' => $row->name,
                    // The override string (e.g. "2 hours") reflects what's
                    // actually governing this student's clock right now -
                    // the group's base time_limit (e.g. "15 seconds") is
                    // misleading once an instructor has set a different
                    // total duration for them specifically.
                    'time_limit' => $row->time_limit_override ?: $row->time_limit,
                    'is_override' => (bool)$row->time_limit_override,
                    'started_at' => $this->convertUTCMysqlFormattedDateToLocalDateAndTime($row->started_at, Auth::user()->time_zone),
                    'expires_at' => $this->convertUTCMysqlFormattedDateToLocalDateAndTime($row->expires_at, Auth::user()->time_zone),
                    'seconds_left' => max(0, strtotime($row->expires_at) - time()),
                    'extended_past_due' => (bool)$row->extended_past_due,
                    'instructor_adjusted' => (bool)$row->instructor_adjusted,
                ];
            }

            $response['type'] = 'success';
            $response['statuses'] = $statuses;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error getting the timer statuses for this assignment. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Pushes a live update to the affected student's own assignment page via
     * Centrifugo, mirroring the clicker feature's publish pattern (see
     * AssignmentSyncQuestionController::setCurrentPage() etc.) - so an
     * instructor's addTime/setTime/reset action is reflected immediately on
     * the student's page instead of waiting for a manual reload or tab
     * refocus. expires_at: null signals "no longer running" (a reset).
     * Swallows its own failures: a Centrifugo hiccup must never turn an
     * otherwise-successful timer change into a reported error, since the
     * actual change already succeeded in the database regardless of whether
     * the live notification went out.
     */
    private function publishTimeLimitUpdate(Assignment $assignment, int $user_id, array $data): void
    {
        try {
            $client = Helper::centrifuge();
            $client->publish("time-limit-update-$assignment->id", array_merge([
                'assignment_id' => $assignment->id,
                'user_id' => $user_id,
            ], $data));
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }
}
