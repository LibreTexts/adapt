<?php

namespace App\Http\Controllers;

use App\Course;
use App\Exceptions\Handler;
use App\LmsOutageCourse;
use App\Mail\LmsOutageDisabled;
use App\Mail\LmsOutageEnabled;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class LmsOutageController extends Controller
{
    /**
     * The LMS types this page can toggle. Keys are the values expected
     * in the {lmsType} route parameter; values are the display name and
     * also what's stored in lms_outage_courses.lms_type.
     */
    private const LMS_TYPES = [
        'canvas' => 'Canvas',
        'blackboard' => 'Blackboard',
        'brightspace' => 'Brightspace',
    ];

    /**
     * Derives a human-readable LMS type from an lti_registrations.iss
     * value, matching the substring checks already used inline in
     * CourseController@show (e.g. is_canvas / is_brightspace).
     *
     * @param string|null $iss
     * @return string|null
     */
    private function lmsTypeFromIss(?string $iss): ?string
    {
        if (!$iss) {
            return null;
        }
        if (strpos($iss, 'instructure') !== false) {
            return 'Canvas';
        }
        if (strpos($iss, 'blackboard') !== false) {
            return 'Blackboard';
        }
        if (strpos($iss, 'brightspace') !== false) {
            return 'Brightspace';
        }
        return null;
    }

    /**
     * Every school currently registered for a given LMS type, keyed by
     * school_id => lti_registration_id. A school is only ever counted
     * once (mirroring Course::getLtiRegistration()'s ->first()
     * assumption that a school has a single active LMS registration at
     * a time).
     *
     * @param string $lmsTypeDisplayName e.g. 'Canvas'
     * @return array [school_id => lti_registration_id]
     */
    private function schoolsForLmsType(string $lmsTypeDisplayName): array
    {
        $rows = DB::table('lti_schools')
            ->join('lti_registrations', 'lti_registrations.id', '=', 'lti_schools.lti_registration_id')
            ->select('lti_schools.school_id', 'lti_registrations.id as lti_registration_id', 'lti_registrations.iss')
            ->get()
            ->unique('school_id');

        $schoolMap = [];
        foreach ($rows as $row) {
            if ($this->lmsTypeFromIss($row->iss) === $lmsTypeDisplayName) {
                $schoolMap[$row->school_id] = $row->lti_registration_id;
            }
        }
        return $schoolMap;
    }

    /**
     * Only courses currently within their start_date/end_date window are
     * considered "active" and eligible to be turned off. Matches the
     * date comparison style used by Course::concludedCourses().
     *
     * @param array $schoolIds
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function activeLmsLinkedCoursesQueryForSchoolIds(array $schoolIds)
    {
        return Course::whereIn('school_id', $schoolIds)
            ->where('lms', 1)
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now());
    }

    /**
     * Resolves and validates the {lmsType} route parameter (e.g.
     * 'canvas') to its display name (e.g. 'Canvas'), or null if it
     * isn't one of the supported types.
     *
     * @param string $lmsType
     * @return string|null
     */
    private function resolveLmsType(string $lmsType): ?string
    {
        return self::LMS_TYPES[strtolower($lmsType)] ?? null;
    }

    /**
     * Returns, for each supported LMS type (Canvas, Blackboard,
     * Brightspace), how many active courses across all schools are
     * currently linked to it and how many are currently marked as
     * affected by an outage - so the admin can see at a glance which
     * LMS types are on/off.
     *
     * @return array
     */
    public function getStatus(): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('manageLmsOutage', Course::class);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $lmsTypes = [];
        foreach (self::LMS_TYPES as $key => $displayName) {
            $schoolMap = $this->schoolsForLmsType($displayName);
            $schoolIds = array_keys($schoolMap);

            $currentlyLinkedCount = empty($schoolIds)
                ? 0
                : $this->activeLmsLinkedCoursesQueryForSchoolIds($schoolIds)->count();
            $currentlyOffCount = LmsOutageCourse::currentlyOff()->forLmsType($displayName)->count();

            $lmsTypes[] = [
                'lms_type_key' => $key,
                'lms_type' => $displayName,
                'school_count' => count($schoolIds),
                'currently_linked_count' => $currentlyLinkedCount,
                'currently_off_count' => $currentlyOffCount,
            ];
        }

        $response['type'] = 'success';
        $response['lms_types'] = $lmsTypes;
        return $response;
    }

    /**
     * Turns off LMS access (courses.lms: 1 -> 0) for every active course
     * across all schools using the given LMS type, logs each one in
     * lms_outage_courses, and queues an email to each course's main
     * instructor.
     *
     * @param string $lmsType
     * @return array
     * @throws Exception
     */
    public function turnOff(string $lmsType): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('manageLmsOutage', Course::class);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $displayName = $this->resolveLmsType($lmsType);
        if (!$displayName) {
            $response['message'] = "'$lmsType' is not a supported LMS type.";
            return $response;
        }

        $schoolMap = $this->schoolsForLmsType($displayName);
        if (empty($schoolMap)) {
            $response['type'] = 'info';
            $response['message'] = "No schools are currently registered for $displayName.";
            return $response;
        }

        $coursesToTurnOff = $this->activeLmsLinkedCoursesQueryForSchoolIds(array_keys($schoolMap))
            ->get(['id', 'name', 'user_id', 'school_id']);

        if ($coursesToTurnOff->isEmpty()) {
            $response['type'] = 'info';
            $response['message'] = "There are no active $displayName courses currently linked to an LMS.";
            return $response;
        }

        try {
            $courseIds = $coursesToTurnOff->pluck('id');

            DB::transaction(function () use ($coursesToTurnOff, $schoolMap, $displayName) {
                $now = Carbon::now();
                $outageRows = $coursesToTurnOff->map(function ($courseToTurnOff) use ($schoolMap, $displayName, $now) {
                    return [
                        'course_id' => $courseToTurnOff->id,
                        'school_id' => $courseToTurnOff->school_id,
                        'lti_registration_id' => $schoolMap[$courseToTurnOff->school_id] ?? null,
                        'lms_type' => $displayName,
                        'turned_on_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray();

                // Bulk insert + bulk update instead of one query per course,
                // so this stays fast even for a few hundred courses.
                LmsOutageCourse::insert($outageRows);
                Course::whereIn('id', $coursesToTurnOff->pluck('id'))->update(['lms' => 0]);
            });

            // Emails are queued (not sent synchronously), so looping over
            // however many courses here just dispatches jobs - it doesn't
            // wait for the emails to actually go out.
            $instructorsByUserId = User::whereIn('id', $coursesToTurnOff->pluck('user_id'))
                ->get(['id', 'email', 'first_name'])
                ->keyBy('id');
            foreach ($coursesToTurnOff as $courseToTurnOff) {
                $instructor = $instructorsByUserId->get($courseToTurnOff->user_id);
                if ($instructor && $instructor->email) {
                    Mail::to($instructor->email)
                        ->queue(new LmsOutageDisabled($instructor->first_name, $courseToTurnOff->name));
                }
            }

            $response['type'] = 'success';
            $response['message'] = "LMS access has been turned off for {$coursesToTurnOff->count()} active $displayName courses, and instructors have been emailed.";
            return $response;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error turning off LMS access. Please try again.';
            return $response;
        }
    }

    /**
     * Turns LMS access back on (courses.lms: 0 -> 1) for every course
     * still marked as affected by an outage for the given LMS type,
     * stamps turned_on_at, and queues an email to each course's main
     * instructor.
     *
     * @param string $lmsType
     * @return array
     * @throws Exception
     */
    public function turnOn(string $lmsType): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('manageLmsOutage', Course::class);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $displayName = $this->resolveLmsType($lmsType);
        if (!$displayName) {
            $response['message'] = "'$lmsType' is not a supported LMS type.";
            return $response;
        }

        $outageRecords = LmsOutageCourse::currentlyOff()->forLmsType($displayName)->with('course:id,name,user_id')->get();

        if ($outageRecords->isEmpty()) {
            $response['type'] = 'info';
            $response['message'] = "There are no courses currently marked as affected by a $displayName outage.";
            return $response;
        }

        try {
            $courseIds = $outageRecords->pluck('course_id');
            $outageRecordIds = $outageRecords->pluck('id');

            DB::transaction(function () use ($courseIds, $outageRecordIds) {
                Course::whereIn('id', $courseIds)->update(['lms' => 1]);
                LmsOutageCourse::whereIn('id', $outageRecordIds)->update(['turned_on_at' => Carbon::now()]);
            });

            $userIds = $outageRecords->pluck('course.user_id')->filter()->unique();
            $instructorsByUserId = User::whereIn('id', $userIds)->get(['id', 'email', 'first_name'])->keyBy('id');
            foreach ($outageRecords as $record) {
                $courseTurnedOn = $record->course;
                if (!$courseTurnedOn) {
                    continue;
                }
                $instructor = $instructorsByUserId->get($courseTurnedOn->user_id);
                if ($instructor && $instructor->email) {
                    Mail::to($instructor->email)
                        ->queue(new LmsOutageEnabled($instructor->first_name, $courseTurnedOn->name));
                }
            }

            $response['type'] = 'success';
            $response['message'] = "LMS access has been restored for {$outageRecords->count()} $displayName courses, and instructors have been emailed.";
            return $response;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error restoring LMS access. Please try again.';
            return $response;
        }
    }
}
