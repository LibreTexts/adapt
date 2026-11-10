<?php

namespace App\Http\Controllers;

use App\Course;
use App\Exceptions\Handler;
use App\LmsStatusCheck;
use App\LmsStatusProbe;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only data for the LMS Status tab on LTI Integrations. Written by check:lmsStatus; see LmsStatusProbe.
 */
class LmsStatusController extends Controller
{
    /** Matches the schedule in Console\Kernel. Only used for display. */
    const CADENCE_MINUTES = 10;

    /** How many recent checks each card's sparkline shows (36 x 10 min = 6 hours). */
    const SPARKLINE_POINTS = 36;

    /**
     * The latest run (or the run at ?at=<checked_at>) for every school, plus the timeline of all retained runs.
     *
     * @param Request $request
     * @param LmsStatusProbe $probe
     * @return array
     */
    public function index(Request $request, LmsStatusProbe $probe): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('manageLmsOutage', Course::class);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $retentionHours = max(1, (int)config('myconfig.lms_status_retention_hours', 72));
            $since = Carbon::now()->subHours($retentionHours);

            $timeline = DB::table('lms_status_checks')
                ->where('checked_at', '>=', $since)
                ->select('checked_at',
                    DB::raw("SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) AS up"),
                    DB::raw("SUM(CASE WHEN status = 'down' THEN 1 ELSE 0 END) AS down"),
                    DB::raw("SUM(CASE WHEN status = 'auth_error' THEN 1 ELSE 0 END) AS auth_error"))
                ->groupBy('checked_at')
                ->orderBy('checked_at')
                ->get()
                ->map(function ($run) {
                    return [
                        'checked_at' => Carbon::parse($run->checked_at)->toIso8601String(),
                        'up' => (int)$run->up,
                        'down' => (int)$run->down,
                        'auth_error' => (int)$run->auth_error,
                    ];
                })->values();

            $response['type'] = 'success';
            $response['retention_hours'] = $retentionHours;
            $response['cadence_minutes'] = self::CADENCE_MINUTES;
            $response['lms_labels'] = LmsStatusProbe::LMS_LABELS;
            $response['timeline'] = $timeline;
            $response['latest_checked_at'] = $timeline->isNotEmpty() ? $timeline->last()['checked_at'] : null;
            $response['viewing_checked_at'] = null;
            $response['schools'] = [];
            $response['summary'] = ['total' => 0, 'up' => 0, 'down' => 0, 'auth_error' => 0, 'avg_response_ms' => null];
            if ($timeline->isEmpty()) {
                return $response;
            }

            $runAt = $request->query('at')
                ? Carbon::parse($request->query('at'))
                : Carbon::parse($response['latest_checked_at']);
            $rows = LmsStatusCheck::where('checked_at', $runAt)->get();
            if ($rows->isEmpty()) {
                $response['type'] = 'error';
                $response['message'] = 'There is no check at that time.';
                return $response;
            }
            $response['viewing_checked_at'] = $runAt->toIso8601String();

            $registrations = $probe->registrations()->keyBy('id');
            $uptime = DB::table('lms_status_checks')
                ->where('checked_at', '>=', $since)
                ->select('lti_registration_id',
                    DB::raw('COUNT(*) AS checks'),
                    DB::raw("SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) AS up_checks"))
                ->groupBy('lti_registration_id')
                ->get()
                ->keyBy('lti_registration_id');
            $sparklines = DB::table('lms_status_checks')
                ->where('checked_at', '<=', $runAt)
                ->where('checked_at', '>', $runAt->copy()->subMinutes(self::CADENCE_MINUTES * self::SPARKLINE_POINTS))
                ->orderBy('checked_at')
                ->get(['lti_registration_id', 'status', 'response_ms'])
                ->groupBy('lti_registration_id');

            $schools = $rows->map(function ($row) use ($registrations, $uptime, $sparklines) {
                $registration = $registrations[$row->lti_registration_id] ?? null;
                $stats = $uptime[$row->lti_registration_id] ?? null;
                $points = $sparklines[$row->lti_registration_id] ?? collect();
                return [
                    'id' => $row->lti_registration_id,
                    'lms' => $registration ? $registration->lms : LmsStatusProbe::OTHER,
                    'name' => $registration ? $registration->name : "Registration #$row->lti_registration_id (no longer active)",
                    'base_url' => $registration ? $registration->base_url : null,
                    'token_url' => $registration ? $registration->auth_token_url : null,
                    'site_check_url' => $registration ? $registration->site_check_url : null,
                    'status' => $row->status,
                    'http_code' => $row->http_code,
                    'response_ms' => $row->response_ms,
                    'error' => $row->error,
                    'uptime_pct' => $stats && $stats->checks ? round(100 * $stats->up_checks / $stats->checks, 1) : null,
                    'checks' => $stats ? (int)$stats->checks : 0,
                    'sparkline' => $points->map(function ($point) {
                        return ['status' => $point->status, 'ms' => $point->response_ms];
                    })->values(),
                ];
            })->sortBy(function ($school) {
                // problems first, then alphabetical
                $order = ['down' => 0, 'auth_error' => 1, 'up' => 2];
                return ($order[$school['status']] ?? 3) . strtolower($school['name']);
            })->values();

            $upTimes = $rows->where('status', LmsStatusCheck::UP)->pluck('response_ms')->filter(function ($ms) {
                return $ms !== null;
            });
            $response['schools'] = $schools;
            $response['summary'] = [
                'total' => $rows->count(),
                'up' => $rows->where('status', LmsStatusCheck::UP)->count(),
                'down' => $rows->where('status', LmsStatusCheck::DOWN)->count(),
                'auth_error' => $rows->where('status', LmsStatusCheck::AUTH_ERROR)->count(),
                'avg_response_ms' => $upTimes->isNotEmpty() ? (int)round($upTimes->avg()) : null,
            ];
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['type'] = 'error';
            $response['message'] = 'We were unable to get the LMS status.  Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Every retained check for one school, for the detail view.
     *
     * @param int $ltiRegistrationId
     * @return array
     */
    public function history(int $ltiRegistrationId): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('manageLmsOutage', Course::class);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['history'] = LmsStatusCheck::where('lti_registration_id', $ltiRegistrationId)
                ->orderBy('checked_at')
                ->get()
                ->map(function ($check) {
                    return [
                        'checked_at' => $check->checked_at->toIso8601String(),
                        'status' => $check->status,
                        'http_code' => $check->http_code,
                        'response_ms' => $check->response_ms,
                        'error' => $check->error,
                    ];
                });
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We were unable to get the history for that school.  Please try again or contact us for assistance.';
        }
        return $response;
    }
}
