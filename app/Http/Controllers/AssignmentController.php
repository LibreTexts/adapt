<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentGroup;
use App\AssignmentGroupWeight;
use App\AssignmentSyncQuestion;
use App\AssignToGroup;
use App\AssignToTiming;
use App\AssignToUser;
use App\AutoRelease;
use App\BetaAssignment;
use App\BetaCourse;
use App\CaseStudyNote;
use App\Course;
use App\Exceptions\Handler;
use App\Extension;
use App\Helpers\Helper;
use App\Http\Requests\ShiftDatesRequest;
use App\Http\Requests\StoreAssignmentProperties;
use App\LmsAPI;
use App\LtiAssignmentsAndGradesUrl;
use App\PendingQuestionRevision;
use App\Question;
use App\QuestionRevision;
use App\Score;
use App\Section;
use App\Solution;
use App\Submission;
use App\SubmissionFile;
use App\Traits\AssignmentProperties;
use App\Traits\DateFormatter;
use App\Traits\S3;
use App\User;
use App\Webwork;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Snowfire\Beautymail\Beautymail;
use Telegram\Bot\Laravel\Facades\Telegram;

class AssignmentController extends Controller
{
    use DateFormatter;
    use S3;
    use AssignmentProperties;

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public function contactInstructorToReleaseAssignment(Assignment $assignment)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('contactInstructorToReleaseAssignment', $assignment);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $instructor_user_id = $assignment->course->user_id;
            $course_id = $assignment->course->id;
            if (DB::table('release_assignment_contacted_instructors')
                ->where('user_id', $instructor_user_id)
                ->where('assignment_id', $assignment->id)
                ->first()) {
                $response['message'] = "Your instructor has already been notified about this issue. Please be patient while they
                resolve it or email them directly.";
                $response['type'] = 'info';
            } else {
                $beauty_mail = app()->make(Beautymail::class);
                $instructor = User::find($instructor_user_id);
                $email_info = ['assignment_name' => $assignment->name,
                    'course_name' => $assignment->course->name,
                    'url' => Helper::schemaAndHost() . "instructors/courses/$course_id/assignments"
                ];
                $to_email = $instructor->email;
                $beauty_mail->send('emails.unpublished_adapt_assignment', $email_info, function ($message)
                use ($to_email) {
                    $message
                        ->from('adapt@noreply.libretexts.org', 'ADAPT')
                        ->to($to_email)
                        ->subject('Unreleased ADAPT Assignment');
                });
                DB::table('release_assignment_contacted_instructors')
                    ->insert(['user_id' => $instructor_user_id,
                        'assignment_id' => $assignment->id,
                        'created_at' => now(),
                        'updated_at' => now()]);
                $response['type'] = 'success';
                $response['message'] = "Your instructor has been notified.";
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error contacting your instructor.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getReviewHistoryByAssignment(Assignment $assignment)
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getReviewHistoryByAssignment', $assignment);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $review_histories = DB::table('review_histories')
                ->join('users', 'review_histories.user_id', '=', 'users.id')
                ->select('users.email',
                    'review_histories.assignment_id',
                    'review_histories.question_id',
                    'review_histories.created_at',
                    'review_histories.updated_at')
                ->where('assignment_id', $assignment->id)
                ->get();
            $response['review_histories'] = $review_histories;
            $response['type'] = 'success';

        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the review histories for this assignment.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param ShiftDatesRequest $request
     * @param AssignToTiming $assignToTiming
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function shiftDates(ShiftDatesRequest $request,
                        AssignToTiming    $assignToTiming,
                        Assignment        $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('shiftDates', [$assignment, $request->assignment_ids]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $shift_by = $request->shift_by;
        try {
            $assign_to_timings = $assignToTiming->whereIn('assignment_id', $request->assignment_ids)->get();
            DB::beginTransaction();
            foreach ($assign_to_timings as $assign_to_timing) {
                foreach (['available_from', 'due', 'final_submission_deadline'] as $key) {
                    if ($request->shifting_method !== 'assignment date property') {
                        if ($assign_to_timing->{$key}) {
                            $date = CarbonImmutable::parse($assign_to_timing->{$key});
                            $assign_to_timing->{$key} = $date->add($shift_by)->sub("$request->offset_difference minutes")->toDateTimeString();
                        }
                    } else {
                        if ($key === $request->assignment_date_property) {
                            $input_date_time = $request->change_date_form['date'] . ' ' . $request->change_date_form['time'];
                            $datetimeInUserTimezone = Carbon::createFromFormat('Y-m-d g:i A', $input_date_time, $request->user()->time_zone);
                            $datetimeInUTC = $datetimeInUserTimezone->setTimezone('UTC');
                            $assign_to_timing->{$key} = $datetimeInUTC->format('Y-m-d H:i:s');
                        }
                    }
                }
                $assign_to_timing->save();
            }
            DB::commit();
            $message = "";
            switch ($request->shifting_method) {
                case('period of time'):
                    $message = "The dates have been shifted by $shift_by.";
                    break;
                case('assignment date property'):
                    $message = "The dates have been updated.";
                    break;
                case('first available on'):
                    $message = "The dates have been shifted based on your new first available from.";
                    break;
            }
            $response['message'] = $message;
            $response['type'] = 'success';

        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error shifting the dates for this course.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Request $request
     * @return array
     */
    public
    function previewShiftDates(Request $request): array
    {

        $shift_by = $request->shift_by;
        $chosen_assignments = $request->chosen_assignments;
        $date = CarbonImmutable::now();
        $response['type'] = 'error';
        if (in_array($shift_by, ['period of time', 'first available on'])) {
            try {
                $date->add($shift_by)->calendar();
            } catch (Exception $e) {
                $response['message'] = "$shift_by is not a valid period of time.";
                return $response;
            }
        }
        $preview_shift_dates = [];
        foreach ($chosen_assignments as $chosen_assignment) {
            $preview_shift_date = [];
            foreach (['due', 'available_from', 'final_submission_deadline'] as $key) {
                if ($request->shifting_method !== 'assignment date property') {
                    if ($chosen_assignment[$key]) {
                        $date = CarbonImmutable::parse($chosen_assignment[$key]);
                        $preview_shift_date[$key] = $date->add($shift_by)->toDateTimeString();
                    }
                } else {
                    if ($key === $request->assignment_date_property) {
                        $input_date_time = $request->change_date_form['date'] . ' ' . $request->change_date_form['time'];
                        $datetimeInUserTimezone = Carbon::createFromFormat('Y-m-d g:i A', $input_date_time);
                        $preview_shift_date[$key] = $datetimeInUserTimezone->format('Y-m-d H:i:s');
                    } else {
                        if (isset($chosen_assignment[$key])) {
                            $preview_shift_date[$key] = $chosen_assignment[$key];
                        }
                    }
                }
            }
            $preview_shift_date['name'] = $chosen_assignment['name'];
            $preview_shift_date['assignment_id'] = $chosen_assignment['assignment_id'];
            $preview_shift_dates[] = $preview_shift_date;
        }
        $response['type'] = 'info';
        $response['message'] = 'The dates have been updated for preview.';
        $response['preview_shift_dates'] = $preview_shift_dates;
        return $response;
    }

    /**
     * @param Course $course
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getDates(Course $course, Assignment $assignment): array
    {

        $authorized = Gate::inspect('getDates', [$assignment, $course]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignment_dates = DB::table('assign_to_timings')
                ->join('assign_to_groups', 'assign_to_timings.id', '=', 'assign_to_groups.assign_to_timing_id')
                ->join('assignments', 'assign_to_timings.assignment_id', '=', 'assignments.id')
                ->whereIn('assignment_id', $course->assignments->pluck('id')->toArray())
                ->select('assignments.id as id', 'assign_to_timings.*', 'assignments.name')
                ->where('group', 'course')
                ->where('assessment_type', '<>', 'clicker')
                ->get();
            foreach ($assignment_dates as &$assignment_date) {
                foreach (['due', 'available_from', 'final_submission_deadline'] as $key) {
                    if ($assignment_date->{$key}) {
                        $assignment_date->{$key} = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($assignment_date->{$key}, Auth::user()->time_zone);
                    }
                }

            }
            $response['type'] = 'success';
            $response['assignment_dates'] = $assignment_dates;
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the dates for this course.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function unlinkFromLMS(Assignment $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('unlinkFromLMS', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            DB::beginTransaction();
            $assignment->lms_resource_link_id = null;
            $assignment->lms_assignment_id = null;
            $assignment->lms_assignment_group_id = null;
            $assignment->save();
            DB::table('lti_assignments_and_grades_urls')->where('assignment_id', $assignment->id)->delete();
            $response['type'] = 'info';
            $response['message'] = "$assignment->name has been unlinked from your LMS.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error unsyncing this assignment from your LMS.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function resyncFromLMS(Assignment $assignment): array
    {
        $response['type'] = 'error';
        /*$authorized = Gate::inspect('unlinkFromLMS', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }*/
        try {
            $course = $assignment->course;
            DB::beginTransaction();

            DB::table('assignments')
                ->where('course_id', $course->id)
                ->update(['lms_resource_link_id' => null, 'lms_assignment_id' => null]);
            $lti_registration = $course->getLtiRegistration();
            $lmsApi = new LmsAPI();
            $result = $lmsApi->getAssignments($lti_registration, $course->user_id, $course->lms_course_id);
            if ($result['type'] === 'error') {
                if (DB::transactionLevel()) {
                    DB::rollback();
                }
                throw new Exception("Could not get LMS course assignments: {$result['message']} for $lti_registration->id");
            }
            $canvas_name = "$assignment->name (ADAPT)";
            $assignment_count = 0;
            $lms_assignment_to_resync = null;
            foreach ($result['message'] as $lms_assignment) {
                if (str_replace(" (ADAPT)", '', $lms_assignment->name) === $assignment->name) {
                    $lms_assignment_to_resync = $lms_assignment;
                    $assignment_count++;
                }
            }
            $lmsApi = new LmsAPI();
            if ($assignment_count > 1) {
                $response['message'] = "There are multiple versions of $canvas_name on Canvas.  Please remove all but the one that you
                would like re-synced and try again.";
                if (DB::transactionLevel()) {
                    DB::rollback();
                }
                return $response;
            }
            if ($lms_assignment_to_resync) {
                Log::info($lms_assignment_to_resync->lti_context_id);
                $lms_assignment_to_resync_arr = (array)$lms_assignment_to_resync;
            } else {
                $lms_assignment_to_resync_arr = $assignment->toArray();
                $lms_assignment_to_resync_arr = $assignment->getIsoUnlockAtDueAt($lms_assignment_to_resync_arr);
            }
            if ($assignment_count) {
                $lms_result = $lmsApi->deleteAssignment($assignment->course->getLtiRegistration(),
                    $assignment->course->user_id,
                    $assignment->course->lms_course_id,
                    $lms_assignment_to_resync->id);
                if ($lms_result['type'] === 'error') {
                    if (strpos($lms_result['message'], 'The specified resource does not exist.') === false) {
                        $response['message'] = 'Error re-syncing this assignment on your LMS: ' . $lms_result['message'];
                        if (DB::transactionLevel()) {
                            DB::rollback();
                        }
                        return $response;
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => config('myconfig.telegram_channel_id'),
                            'parse_mode' => 'HTML',
                            'text' => "Could not find $assignment->name with LMS assignment ID $assignment->lms_assignment_id on Canvas when trying to delete it"
                        ]);

                    }
                }
            }

            $lms_result = $lmsApi->createAssignment($course->getLtiRegistration(),
                $course->user_id, $course->lms_course_id, $lms_assignment_to_resync_arr);
            if ($lms_result['type'] === 'error') {
                if (DB::transactionLevel()) {
                    DB::rollback();
                }
                $response['message'] = 'Error re-syncing this assignment on your LMS: ' . $lms_result['message'];
                return $response;
            } else {
                $assignment->lms_assignment_id = $lms_result['message']->id;
                $assignment->lms_resource_link_id = $lms_result['message']->lti_context_id;
                $assignment->save();
            }


            DB::commit();
            $response['message'] = "$assignment->name has been re-synced.";
            $response['type'] = 'success';

        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error resyncing this course to your LMS.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getClickerAssignmentsForEnrolledAndOpenCourses(Request $request, Assignment $assignment)
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('getClickerAssignmentsForEnrolledAndOpenCourses', $assignment);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $clicker_assignments = DB::table('enrollments')
                ->join('courses', 'enrollments.course_id', '=', 'courses.id')
                ->join('assignments', 'assignments.course_id', '=', 'courses.id')
                ->where('enrollments.user_id', $request->user()->id)
                ->where('courses.shown', 1)
                ->where('assignments.assessment_type', 'clicker')
                ->select('assignments.*')
                ->get();
            $response['clicker_assignments'] = $clicker_assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get retrieve your course assignments with clickers.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function validateNotWeightedPointsPerQuestionWithSubmissions(Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('validateNotWeightedPointsPerQuestionWithSubmissions', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['weighted_points_per_question_with_submissions'] =
                $assignment->points_per_question === 'question weight'
                && $assignment->hasNonFakeStudentFileOrQuestionSubmissions();
            $response['type'] = 'success';
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error determining whether there were submissions for this weighted points per question assignment.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function linkToLMS(Request $request, Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('linkToLMS', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $course = Course::find($assignment->course_id);
            $existing_lms_assignment = $assignment->existsInLMS($request->unlinked_assignments);
            $lmsApi = new LmsAPI();
            if ($existing_lms_assignment) {
                $existing_lms_assignment['lms_assignment_group_id'] = $existing_lms_assignment['assignment_group_id'];
            }
            $assignment_arr = $existing_lms_assignment ?: $assignment->toArray();
            $assignment_arr = $assignment->getIsoUnlockAtDueAt($assignment_arr);
            if ($assignment->formative) {
                $total_points = 0;
            } else if ($assignment->number_of_randomized_assessments) {
                $total_points = $assignment->default_points_per_question * $assignment->number_of_randomized_assessments;
            } else {
                $assignment_questions = DB::table('assignment_question')
                    ->where('assignment_id', $assignment->id)
                    ->get();
                if ($assignment_questions) {
                    $total_points = 0;
                    foreach ($assignment_questions as $assignment_question) {
                        $total_points += $assignment_question->points;
                    }
                } else {
                    $total_points = 0;
                }
            }
            $assignment_arr['total_points'] = $total_points;
            $lti_registration = $course->getLtiRegistration();
            $lms_result = $existing_lms_assignment ? $lmsApi->updateAssignment($lti_registration,
                $course->user_id, $course->lms_course_id, $existing_lms_assignment['id'], $assignment_arr)
                : $lmsApi->createAssignment($lti_registration,
                    $course->user_id, $course->lms_course_id, $assignment_arr);

            if ($lms_result['type'] === 'error') {
                $response['message'] = 'Error: ' . $lms_result['message'];
                return $response;
            }
            $url = "$lti_registration->auth_server/api/lti/courses/$course->lms_course_id/line_items/$assignment->lms_assignment_id";
            //LtiAssignmentsAndGradesUrl::updateOrCreate(['assignment_id' => $assignment->id], ['url' => $url]);
//	Message: 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'https://some-lms.instructure.com/api/lti/courses/25345/line_items/' for key 'lti_assignments_and_grades_urls.lti_assignments_and_grades_urls_url_unique' (SQL: insert into `lti_assignments_and_grades_urls` (`assignment_id`, `url`, `updated_at`, `created_at`) values (128999, https://scccd.instructure.com/api/lti/courses/25345/line_items/, 2025-05-22 16:18:36, 2025-05-22 16:18:36))'
//	File: /var/task/vendor/laravel/framework/src/Illuminate/Database/Connection.php:631 When is it not passed pack is the question? scccd
            $assignment->lms_assignment_id = $lms_result['message']->id;
            $assignment->save();
            $response['type'] = 'success';
            $response['message'] = 'Linked';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error linking the assignment.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function unlinkLti(Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('unlinkLti', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            DB::beginTransaction();
            $assignment->lms_resource_link_id = null;
            $assignment->save();
            DB::table('lti_assignments_and_grades_urls')->where('assignment_id', $assignment->id)->delete();
            $response['message'] = "$assignment->name has been unlinked from your LMS.";
            $response['type'] = 'success';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error unlinking the assignment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function showCommonQuestionText(Request $request, Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('showCommonQuestionText', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['commmon_question_text'] = $assignment->common_question_text;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the common question text.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function updateCommonQuestionText(Request $request, Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateCommonQuestionText', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignment->common_question_text = $request->common_question_text;
            $assignment->save();
            $response['type'] = 'success';
            $response['message'] = 'The common question text has been updated.';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the common question text.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    public
    function getAssignmentNamesIdsByCourse(Course $course)
    {
        {

            $response['type'] = 'error';
            $authorized = Gate::inspect('getAssignmentNamesIdsByCourse', $course);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            try {
                $assignments = DB::table('assignments')
                    ->where('course_id', $course->id)
                    ->orderBy('order')
                    ->select('id AS value', 'name AS text')
                    ->get();
                if ($assignments->isEmpty()) {
                    $response['message'] = "This course has no assignments.";
                    return $response;
                }

                $response['assignments'] = $assignments;
                $response['type'] = 'success';

            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
                $response['message'] = "There was an error getting the assignments for this course.  Please try again or contact us for assistance.";
            }
            return $response;

        }
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getAssignmentsForAnonymousUser(Course $course): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('getAssignmentsForAnonymousUser', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $assignments = DB::table('assignments')
                ->where('course_id', $course->id)
                ->orderBy('order')
                ->get();
            $assignment_questions = DB::table('assignment_question')
                ->whereIn('assignment_id', $course->assignments->pluck('id')->toArray())
                ->get();
            $assignment_questions_by_assignment_id = [];
            foreach ($assignment_questions as $assignment_question) {
                !isset($assignment_questions_by_assignment_id[$assignment_question->assignment_id])
                    ? $assignment_questions_by_assignment_id[$assignment_question->assignment_id] = 1
                    : $assignment_questions_by_assignment_id[$assignment_question->assignment_id]++;
            }
            foreach ($assignments as $assignment) {
                $assignment->number_of_questions = $assignment_questions_by_assignment_id[$assignment->id] ?? 0;
            }

            $response['assignments'] = $assignments;
            $response['course_name'] = $course->name;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignments for this course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function startPageInfo(Assignment $assignment): array
    {

        try {
            $textbook_url = $assignment->textbook_url;
            $response['name'] = $assignment->name;
            $response['adapt_launch'] = !$textbook_url;
            $response['start_page_url'] = $textbook_url;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $response['type'] = 'error';
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment start page information.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param string $type
     * @param Course $course
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getOpenCourseAssignments(string $type, Course $course, Assignment $assignment): array
    {
        $response['type'] = 'error';
        switch ($type) {
            case('commons'):
                if (User::where('email', 'commons@libretexts.org')->first()->id !== $course->user_id) {
                    $response['message'] = 'You are not allowed to access the assignments in that course since it is not a Commons course.';
                    return $response;
                }
                break;
            case('public'):
                if (!$course->public) {
                    $response['message'] = 'You are not allowed to access the assignments in that course since it is not a Public course.';
                }
                break;
            default:
                $response['message'] = "That is not a valid open course type.";
                return $response;
        }


        try {
            $assignments = DB::table('assignments')
                ->leftJoin('assignment_question', 'assignments.id', '=', 'assignment_question.assignment_id')
                ->where('course_id', $course->id)
                ->select('assignments.id',
                    'assignments.id AS assignment_id',
                    'assignments.formative',
                    'name',
                    'public_description AS description',
                    DB::raw("COUNT(assignment_question.question_id) as num_questions")
                )
                ->groupBy('assignments.id')
                ->orderBy('assignments.order')
                ->get();
            $topics_by_assignment_id = $assignment->getTopicsByAssignmentId($course, $course->assignments->pluck('id')->toArray());
            foreach ($assignments as $key => $assignment) {
                $assignments[$key]->topics = $topics_by_assignment_id[$assignment->id];
            }

            $response['assignments'] = $assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment information.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function getAssignmentNamesForPublicCourse(Course $course, AssignmentGroup $assignmentGroup, Assignment $assignment)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getAssignmentNamesForPublicCourse', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $assignment_groups = $assignmentGroup->assignmentGroupsByCourse($course->id);
        $assignment_ids = $course->assignments->pluck('id')->toArray();
        $num_questions = DB::table('assignment_question')
            ->whereIn('assignment_id', $assignment_ids)
            ->select('assignment_id', DB::raw("count(*) as num_questions"))
            ->groupBy('assignment_id')
            ->get();
        $num_questions_by_assignment_id = [];
        foreach ($num_questions as $num_question) {
            $num_questions_by_assignment_id[$num_question->assignment_id] = $num_question->num_questions;

        }
        try {

            $topics_by_assignment_id = $assignment->getTopicsByAssignmentId($course, $assignment_ids);
            $assignments = [];
            foreach ($course->assignments as $assignment) {
                $assignment->name = strpos($assignment->name, $assignment_groups[$assignment->id]) !== false
                    ? $assignment->name
                    : $assignment->name . " (" . $assignment_groups[$assignment->id] . ")";
                $assignments[] = $assignment;
                $assignment->num_questions = $num_questions_by_assignment_id[$assignment->id] ?? 0;
                $assignment->topics = $topics_by_assignment_id[$assignment->id];
            }
            $response['assignments'] = $assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment information.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @return array|void
     * @throws Exception
     */
    public
    function downloadUsersForAssignmentOverride(Assignment $assignment)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('downloadUsersForAssignmentOverride', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $enrolled_users = $assignment->course->enrolledUsers;
            $users_by_id = [];
            foreach ($enrolled_users as $enrolled_user) {
                $users_by_id[] = [$enrolled_user->id, "$enrolled_user->first_name $enrolled_user->last_name", ''];
            }
            if (!$users_by_id) {
                $response['type'] = 'info';
                $response['message'] = 'This course has no students yet.';
                return $response;
            }
            usort($users_by_id, function ($a, $b) {
                return $a[1] <=> $b[1];
            });
            array_unshift($users_by_id, ['User Id', 'Name', 'Score']);
            $assignment_name = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $assignment->name);
            Helper::arrayToCsvDownload($users_by_id, $assignment_name);

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the user and assignment information.  Please try again or contact us for assistance.";
            return $response;
        }
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getAssignmentOptions(Course $course): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('getAssignmentOptions', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $assignments = $course->assignments;
            $assignments_by_id[0] = ['value' => 0, 'text' => 'Please choose an assignment'];
            foreach ($assignments as $assignment) {
                $assignments_by_id[] = ['value' => $assignment->id, 'text' => $assignment->name];
            }
            $response['assignments'] = $assignments_by_id;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the user and assignment information.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function validateAssessmentType(Request $request, Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $learning_tree = DB::table('assignment_question_learning_tree')
                ->join('assignment_question', 'assignment_question_id', '=', 'assignment_question.id')
                ->where('assignment_id', $assignment->id)
                ->select('question_id')
                ->first();
            $open_ended = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('open_ended_submission_type', '<>', '0')
                ->select('assignment_id')
                ->first();
            $question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->select('assignment_id')
                ->first();
            $assessment_type = $request->assessment_type;
            $source = $request->source;
            if ($source === 'x' && ($question || $learning_tree)) {
                $response['message'] = "You can't switch to an external assignment until you remove all ADAPT questions from the assignment.";
                return $response;
            }

            if ($assessment_type == 'clicker' && $open_ended !== null) {
                $response['message'] = "You can't switch to a $assessment_type assessment type until you remove the open-ended questions from the assignment.";
                return $response;
            }
            if (in_array($assessment_type, ['delayed', 'real time', 'clicker']) && $learning_tree !== null) {
                $response['message'] = "You can't switch to a $assessment_type assessment type since this is not a learning tree assignment.";
                return $response;
            }
            if ($assessment_type === 'learning tree' && $question !== null) {
                $response['message'] = "You can't switch to a learning tree assessment type since this is not a learning tree assignment and you already have non-learning tree questions.";
                return $response;
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment question information.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Course $course
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function order(Request $request, Course $course, Assignment $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('order', [$assignment, $course]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {
            DB::beginTransaction();
            $assignment->orderAssignments($request->ordered_assignments, $course);
            DB::commit();
            $response['message'] = 'Your assignments have been re-ordered.';
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error ordering the assignments for this course.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Course $course
     * @param AssignmentGroup $assignmentGroup
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param AssignmentGroupWeight $assignmentGroupWeight
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function importAssignment(Request                $request,
                              Assignment             $assignment,
                              Course                 $course,
                              AssignmentGroup        $assignmentGroup,
                              AssignmentSyncQuestion $assignmentSyncQuestion,
                              AssignmentGroupWeight  $assignmentGroupWeight,
                              AutoRelease            $autoRelease
    ): array
    {

        $response['type'] = 'error';
        $level = $request->level;

        $authorized = Gate::inspect('importAssignment', [$course, $assignment]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        if (!in_array($level, ['properties_and_questions', 'properties_and_not_questions'])) {
            $response['message'] = "You should either choose 'properties and questions' or 'properties and not questions'.";
            return $response;

        }

        if ($assignment->course->lms && !in_array($request->lms_grade_passback, ['automatic', 'manual'])) {
            $response['message'] = "Since this course is an LMS course, please choose an option for the LMS grade passback.";
            return $response;

        }

        try {

            $assignment = Assignment::find($assignment->id);

            DB::beginTransaction();

            $imported_assignment_group_id = $assignmentGroup->importAssignmentGroupToCourse($course, $assignment);
            $assignmentGroupWeight->importAssignmentGroupWeightToCourse($assignment->course, $course, $imported_assignment_group_id, true);
            $imported_assignment = $assignment->replicate();
            $imported_assignment->show_scores = 0;
            $imported_assignment->solutions_released = 0;
            $imported_assignment->students_can_view_assignment_statistics = 0;

            $imported_assignment->name = "$imported_assignment->name Import";
            $imported_assignment->course_id = $course->id;
            $imported_assignment->assignment_group_id = $imported_assignment_group_id;
            $imported_assignment->lms_resource_link_id = null;
            $imported_assignment->lms_grade_passback = $course->lms ? ($request->lms_grade_passback ? $request->lms_grade_passback : 'automatic') : null;
            $imported_assignment->save();
            $auto_release = [];
            if ($request->auto_releases) {
                foreach ($autoRelease->keys() as $key) {
                    if ($key === 'shown') {
                        if (isset($request->auto_releases['shown'])) {
                            $before_pos = strpos($request->auto_releases['shown'], 'before');
                            $substring_before_before = substr($request->auto_releases['shown'], 0, $before_pos);
                            $substring_before_before = rtrim($substring_before_before);
                            $auto_release['shown'] = $substring_before_before;
                        }
                    } else {
                        if (isset($request->auto_releases[$key])) {
                            $info = $autoRelease->timingAndAfterArr($request->auto_releases[$key]);
                            $auto_release[$key] = $info['timing'];
                            $auto_release[$key . '_after'] = $info['after'];
                        }
                    }
                }
            } else {
                if ($course->auto_release_shown) {
                    $auto_release['shown'] = $course->auto_release_shown;
                }
                if ($course->auto_release_show_scores) {
                    $auto_release['show_scores'] = $course->auto_release_show_scores;
                    $auto_release['show_scores_after'] = $course->auto_release_show_scores_after;
                }
                if ($course->auto_release_solutions_released) {
                    $auto_release['solutions_released'] = $course->auto_release_solutions_released;
                    $auto_release['solutions_released_after'] = $course->auto_release_solutions_released_after;
                }
                if ($course->auto_release_students_can_view_assignment_statistics) {
                    $auto_release['students_can_view_assignment_statistics'] = $course->auto_release_students_can_view_assignment_statistics;
                    $auto_release['students_can_view_assignment_statistics_after'] = $course->auto_release_students_can_view_assignment_statistics_after;
                }
            }
            if ($auto_release) {
                if ($imported_assignment->assessment_type === 'real time') {
                    $auto_release['show_scores'] = null;
                    $auto_release['show_scores_after'] = null;
                }
                $auto_release['type_id'] = $imported_assignment->id;
                $auto_release['type'] = 'assignment';
                AutoRelease::updateOrCreate(['type' => 'assignment', 'type_id' => $imported_assignment->id], $auto_release);
            }

            $assignment->saveAssignmentTimingAndGroup($imported_assignment);

            if ($level === 'properties_and_questions') {
                $reset_discuss_it_settings_to_default = +$request->reset_discuss_it_settings_to_default === 1;
                $reset_clicker_settings_to_default = +$request->reset_clicker_settings_to_default === 1;
                $remove_open_ended_questions_in_real_time_assignment = +$request->remove_open_ended_questions_in_real_time_assignment === 1;
                $assignmentSyncQuestion->importAssignmentQuestionsAndLearningTrees($assignment->id, $imported_assignment->id, $reset_discuss_it_settings_to_default, $reset_clicker_settings_to_default, $remove_open_ended_questions_in_real_time_assignment);
                $case_study_notes = CaseStudyNote::where('assignment_id', $assignment->id)->get();
                foreach ($case_study_notes as $case_study_note) {
                    $imported_case_study_note = $case_study_note->replicate()->fill([
                        'assignment_id' => $imported_assignment->id
                    ]);
                    $imported_case_study_note->save();
                }
            }

            if ($course->lms_course_id) {
                $lmsApi = new LmsAPI();
                $imported_assignment_arr = $imported_assignment->toArray();
                $imported_assignment_arr = $imported_assignment->getIsoUnlockAtDueAt($imported_assignment_arr);
                $lms_result = $lmsApi->createAssignment($course->getLtiRegistration(),
                    $course->user_id, $course->lms_course_id, $imported_assignment_arr);
                if ($lms_result['type'] === 'error') {
                    $response['message'] = 'Error creating this assignment on your LMS: ' . $lms_result['message'];
                    return $response;
                } else {
                    $imported_assignment->lms_assignment_id = $lms_result['message']->id;
                    $imported_assignment->save();
                }
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = ($level === 'properties_and_questions')
                ? "<strong>$imported_assignment->name</strong> and its questions have been imported.</br></br>Don't forget to change the dates associated with this assignment."
                : "<strong>$imported_assignment->name</strong> has been imported without its questions.</br></br>Don't forget to change the dates associated with this assignment.";

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error importing the assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getImportableAssignmentsByUser(Request $request,
                                            Course  $course): array
    {

        $response['type'] = 'error';

        try {

            $assignments_by_user = DB::table('assignments')
                ->join('courses', 'assignments.course_id', '=', 'courses.id')
                ->select(DB::raw('assignments.id AS assignment_id, courses.name AS course_name, assignments.name AS assignment_name'))
                ->where('courses.user_id', $request->user()->id)
                ->where('courses.id', '<>', $course->id)//don't get them for this course
                ->get();
            $assignments = [];

            foreach ($assignments_by_user as $key => $value) {
                $assignments[] = "$value->course_name --- $value->assignment_name";
            }
            $response['all_assignments'] = $assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting your assignments.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourse $betaCourse
     * @param BetaAssignment $betaAssignment
     * @param Section $section
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function createAssignmentFromTemplate(Request                $request,
                                          Assignment             $assignment,
                                          AssignmentSyncQuestion $assignmentSyncQuestion,
                                          BetaCourse             $betaCourse,
                                          BetaAssignment         $betaAssignment,
                                          Section                $section,
                                          AutoRelease            $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('createFromTemplate', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $beta_courses_exist = DB::table('beta_courses')->where('alpha_course_id', $assignment->course->id)->first();
            if ($request->level === 'properties_and_questions' && $beta_courses_exist) {
                $response['message'] = 'Since this is an Alpha course, please select "Just Properties".';
                return $response;
            }
            DB::beginTransaction();
            $assignment = Assignment::find($assignment->id);

            $assignment->lms_resource_link_id = null;
            foreach ($assignment->course->assignments as $current_assignment) {
                if ($current_assignment->order > $assignment->order) {
                    $current_assignment->order++;
                    $current_assignment->save();
                }
            }
            $new_assignment = $assignment->replicate();
            $new_assignment->show_scores = 0;
            $new_assignment->solutions_released = 0;
            $new_assignment->students_can_view_assignment_statistics = 0;
            $new_assignment->name = $new_assignment->name . " copy";
            $new_assignment->order++;
            $new_assignment->save();
            $auto_release = $autoRelease->where('type', 'assignment')->where('type_id', $assignment->id)->first();
            if ($auto_release) {
                $new_auto_release = $auto_release->replicate();
                $new_auto_release->type_id = $new_assignment->id;
                $new_auto_release->save();
            }
            if ($request->level === 'properties_and_questions') {
                $reset_discuss_it_settings_to_default = +$request->reset_discuss_it_settings_to_default === 1;
                $reset_clicker_settings_to_default = +$request->reset_clicker_settings_to_default === 1;
                $remove_open_ended_questions_in_real_time_assignment = +$request->remove_open_ended_questions_in_real_time_assignment === 1;
                $assignmentSyncQuestion->importAssignmentQuestionsAndLearningTrees($assignment->id, $new_assignment->id, $reset_discuss_it_settings_to_default, $reset_clicker_settings_to_default, $remove_open_ended_questions_in_real_time_assignment);
            }


            if ((int)$request->assign_to_groups === 1) {
                $this->copyAssignTos($assignment, $new_assignment);

            } else {
                $assignment->saveAssignmentTimingAndGroup($new_assignment);
            }
            if ($request->level !== 'properties_and_questions') {
                $assign_tos[0] = (array)DB::table('assign_to_timings')
                    ->where('assignment_id', $new_assignment->id)
                    ->first();

                foreach (['available_from', 'due', 'final_submission_deadline'] as $key) {
                    $assign_tos[0]["{$key}_date"] = $assign_tos[0][$key] ? $this->getDateFromSqlTimestamp($assign_tos[0][$key]) : null;
                    $assign_tos[0]["{$key}_time"] = $assign_tos[0][$key] ? $this->getTimeFromSqlTimestamp($assign_tos[0][$key]) : null;
                    unset($assign_tos[0][$key]);
                }

                $betaAssignment->addBetaAssignments($new_assignment->course, $betaCourse, $new_assignment, $section, $assign_tos, $request->user());
            }

            DB::commit();
            $response['message'] = "<strong>$new_assignment->name</strong> is using the same template as <strong>$assignment->name</strong>. Don't forget to add questions and update the assignment's dates.";
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating an assignment from $assignment->name.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function solutionsReleased(Request                $request,
                               Assignment             $assignment,
                               AutoRelease            $autoRelease,
                               AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('releaseSolutions', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $solutionsReleased = $assignment->solutions_released;
            DB::beginTransaction();
            $assignment->update(['solutions_released' => !$solutionsReleased]);
            $auto_release_deactivated = $autoRelease->deactivateFromAssignment($assignment->id, 'solutions_released', $request->deactivate_auto_release);
            $solutions_released = !$solutionsReleased ? 'released' : 'hidden';
            $response['type'] = !$solutionsReleased ? 'success' : 'info';

            $response['message'] = "The solutions have been <strong>{$solutions_released}</strong>.  ";
            if (!$solutionsReleased) {
                $response['message'] .= $this->getActiveExtensionMessage($assignment, 'solution');
                $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                    ->update(['release_solution_when_question_is_closed' => 1]);
            }
            $response['message'] .= $assignment->addAutoReleaseMessage($auto_release_deactivated);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error releasing the solutions to <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function showQuestionTitles(Assignment $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showQuestionTitles', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $shown = $assignment->question_titles_shown;
            Log::info($assignment->id);
            Log::info($shown);
            Log::info(!$shown);
            $assignment->update(['question_titles_shown' => !$shown]);
            $response['type'] = !$shown ? 'success' : 'info';
            $shown = !$shown ? 'can' : 'cannot';
            $response['message'] = "Your students <strong>$shown</strong> see the question titles in this assignment.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating whether your students can see <strong>$assignment->name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function showAssignment(Request     $request,
                            Assignment  $assignment,
                            AutoRelease $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showAssignment', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $shown = $assignment->shown;
        if ($assignment->number_of_randomized_assessments && !$shown) {
            if ($assignment->questions->count() <= $assignment->number_of_randomized_assessments) {
                $response['message'] = "Before you can show this assignment, please make sure that the number of chosen assessments ({$assignment->questions->count()}) is greater than the number of randomized assessments ({$assignment->number_of_randomized_assessments}).";
                return $response;
            }
        }
        try {
            DB::beginTransaction();
            $assignment->update(['shown' => !$shown]);
            $auto_release_deactivated = $autoRelease->deactivateFromAssignment($assignment->id, 'shown', $request->deactivate_auto_release);
            $course = $assignment->course;
            $lmsApi = new LmsAPI();
            if ($assignment->course->lms_course_id) {
                $lms_result = $lmsApi->updateAssignment(
                    $course->getLtiRegistration(),
                    $course->user_id,
                    $course->lms_course_id,
                    $assignment->lms_assignment_id,
                    ['shown' => !$shown]);
                if ($lms_result['type'] === 'error') {
                    throw new Exception('Error updating this assignment on your LMS: ' . $lms_result['message']);
                }
            }
            $response['type'] = !$shown ? 'success' : 'info';
            $shown = !$shown ? 'can' : 'cannot';
            $response['message'] = "Your students <strong>{$shown}</strong> see this assignment.";
            $response['message'] .= $assignment->addAutoReleaseMessage($auto_release_deactivated);

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating whether your students can see <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param int $gradersCanSeeStudentNames
     * @return array
     * @throws Exception
     */
    public
    function gradersCanSeeStudentNames(Assignment $assignment, int $gradersCanSeeStudentNames): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('gradersCanSeeStudentNames', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $assignment->update(['graders_can_see_student_names' => !$gradersCanSeeStudentNames]);
            $response['type'] = !$gradersCanSeeStudentNames ? 'success' : 'info';
            $graders_can_see_student_names = !$gradersCanSeeStudentNames ? 'can' : 'cannot';
            $response['message'] = "Graders <strong>$graders_can_see_student_names</strong> see their students' names.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error changing the option of graders being able to view their students' names <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function questionUrlView(Assignment $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('questionUrlView', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $question_url_view = $assignment->question_url_view === 'assignment' ? 'question' : 'assignment';
            $assignment->update(['question_url_view' => $question_url_view]);
            $response['type'] = 'info';
            $response['question_url_view'] = $question_url_view;
            $response['message'] = "The question URL view has been updated.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the question URL view for <strong>$assignment->name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function showScores(Request     $request,
                        Assignment  $assignment,
                        AutoRelease $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showScores', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $showScores = $assignment->show_scores;
            DB::beginTransaction();
            $assignment->update(['show_scores' => !$showScores]);
            $auto_release_deactivated = $autoRelease->deactivateFromAssignment($assignment->id, 'show_scores', $request->deactivate_auto_release);
            $response['type'] = !$showScores ? 'success' : 'info';
            $scores_released = !$showScores ? 'can' : 'cannot';
            $lmsApi = new LmsAPI();
            $course = $assignment->course;
            if ($course->lms_course_id) {
                $lms_result = $lmsApi->updateAssignment(
                    $course->getLtiRegistration(),
                    $course->user_id,
                    $course->lms_course_id,
                    $assignment->lms_assignment_id,
                    ['show_scores' => !$showScores]);
                if ($lms_result['type'] === 'error') {
                    throw new Exception('Error updating this assignment on your LMS: ' . $lms_result['message']);
                }
            }
            $response['message'] = "Your students <strong>$scores_released</strong> view their scores.  ";
            if (!$showScores) {
                $response['message'] .= $this->getActiveExtensionMessage($assignment, 'score');
            }
            $response['message'] .= $assignment->addAutoReleaseMessage($auto_release_deactivated);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error releasing the solutions to <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param string $type
     * @return string
     */
    public
    function getActiveExtensionMessage(Assignment $assignment, string $type): string
    {

        if ($assignment->extensions->isNotEmpty()) {
            foreach ($assignment->extensions as $key => $value) {
                if (time() < strtotime($value->extension)) {
                    $type_message = ($type === 'score') ? "other students' scores and grader comments" : "the solutions";
                    return "<br><br>Please note that at least one of your students has an active extension and they can potentially view {$type_message}.";
                }
            }
        }
        return '';
    }

    public
    function showPointsPerQuestion(Assignment $assignment, int $showPointsPerQuestion)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showPointsPerQuestion', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $assignment->update(['show_points_per_question' => !$showPointsPerQuestion]);
            $response['type'] = !$showPointsPerQuestion ? 'success' : 'info';
            $points_per_question = !$showPointsPerQuestion ? 'can' : 'cannot';
            $response['message'] = "Your students <strong>{$points_per_question}</strong> view the points per question.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error releasing the solutions to <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function showAssignmentStatistics(Request     $request,
                                      Assignment  $assignment,
                                      AutoRelease $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showAssignmentStatistics', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $showAssignmentStatistics = $assignment->students_can_view_assignment_statistics;
            $assignment->update(['students_can_view_assignment_statistics' => !$showAssignmentStatistics]);
            $auto_release_deactivated = $autoRelease->deactivateFromAssignment($assignment->id, 'students_can_view_assignment_statistics', $request->deactivate_auto_release);
            $response['type'] = !$showAssignmentStatistics ? 'success' : 'info';
            $scores_released = !$showAssignmentStatistics ? 'can' : 'cannot';
            $response['message'] = "Your students <strong>{$scores_released}</strong> view the assignment statistics.";
            $response['message'] .= $assignment->addAutoReleaseMessage($auto_release_deactivated);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error showing/hiding the assignment statistics for <strong>{$assignment->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Course $course
     * @param Extension $extension
     * @param Score $Score
     * @param Submission $Submission
     * @param Solution $Solution
     * @param AssignmentGroup $AssignmentGroup
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function index(Course          $course,
                   Extension       $extension,
                   Score           $Score, Submission $Submission,
                   Solution        $Solution,
                   AssignmentGroup $AssignmentGroup,
                   Assignment      $assignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        return $assignment->getAssignmentsByCourse($course, $extension, $Score, $Submission, $Solution, $AssignmentGroup);
    }


    public
    function addAssignmentGroupWeight(Assignment $assignment, int $assignment_group_id, AssignmentGroupWeight $assignmentGroupWeight)
    {
        $assignment_group_weight_exists = AssignmentGroupWeight::where('course_id', $assignment->course->id)
            ->where('assignment_group_id', $assignment->assignment_group_id)
            ->get()
            ->isNotEmpty();

        if (!$assignment_group_weight_exists) {
            $assignmentGroupWeight->assignment_group_id = $assignment_group_id;
            $assignmentGroupWeight->course_id = $assignment->course->id;
            $assignmentGroupWeight->assignment_group_weight = 0;
            $assignmentGroupWeight->save();
        }
    }


    /**
     * @param StoreAssignmentProperties $request
     * @param Assignment $assignment
     * @param AssignmentGroupWeight $assignmentGroupWeight
     * @param Section $section
     * @param User $user
     * @param BetaCourse $betaCourse
     * @param BetaAssignment $betaAssignment
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */

    public
    function store(StoreAssignmentProperties $request,
                   Assignment                $assignment,
                   AssignmentGroupWeight     $assignmentGroupWeight,
                   Section                   $section,
                   User                      $user,
                   BetaCourse                $betaCourse,
                   BetaAssignment            $betaAssignment,
                   AutoRelease               $autoRelease): array
    {
        $response['type'] = 'error';
        $course = Course::find(['course_id' => $request->input('course_id')])->first();
        $formative = $course->formative || $request->formative;

        $authorized = Gate::inspect('createCourseAssignment', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {
            $data = $request->validated();
            $assign_tos = $this->reformatAssignToTimes($request->assign_tos);

            if ($request->user()->role === 5 || $formative) {

                $assignment_json = '{"public_description":null,"private_description":null,"assessment_type":"real time","number_of_allowed_attempts":"1","number_of_allowed_attempts_penalty":null,"can_view_hint":0,"hint_penalty":null,"algorithmic":0,"min_number_of_minutes_in_exposition_node":null,"assignment_group_id":1,"source":"a","instructions":"","number_of_randomized_assessments":null,"external_source_points":null,"scoring_type":"p","points_per_question":"number of points","default_completion_scoring_mode":null,"default_points_per_question":"10.00","total_points":null,"default_clicker_time_to_submit":null,"show_points_per_question":1,"file_upload_mode":null,"default_open_ended_submission_type":"0","default_open_ended_text_editor":null,"late_policy":"not accepted","late_deduction_percent":null,"late_deduction_application_period":"once","shown":1,"show_scores":1,"solutions_released":0,"solutions_availability":"automatic","graders_can_see_student_names":1,"students_can_view_assignment_statistics":0,"include_in_weighted_average":1,"notifications":1,"course_id":512,"lms_resource_link_id":null,"textbook_url":null}';
                $data = json_decode($assignment_json, 1);
                $data['name'] = $request->name;
                if ($formative) {
                    $data['number_of_allowed_attempts'] = 'unlimited';
                }
                $data['formative'] = $formative;
                $data['total_points'] = $formative ? 0 : 100;
                $data['public_description'] = $request->public_description;
                $data['private_description'] = $request->private_description;
                $data['course_id'] = $course->id;
                $data['algorithmic'] = $request->algorithmic;
                $data['order'] = $assignment->getNewAssignmentOrder($course);
                $assignment = Assignment::create($data);
                $date = date("Y-m-d");
                $datetime = new DateTime('tomorrow');
                $tomorrow = $datetime->format('Y-m-d');
                $assign_tos = '[{"groups":[{"value":{"course_id":' . $course->id . '},"text":"Everybody"}],"selectedGroup":null,"available_from_date":"' . $date . '","available_from_time":"9:00 AM","due_date":"' . $tomorrow . '","due_time":"9:00 AM"}]';
                $assign_tos = json_decode($assign_tos, true);
                $this->addAssignTos($assignment, $assign_tos, $section, $request->user());
                $course = $assignment->course;
                if ($course->lms_course_id) {
                    $lmsApi = new LmsAPI();
                    //for a formative course I won't add the due dates/times
                    $lms_result = $lmsApi->createAssignment($course->getLtiRegistration(),
                        $course->user_id, $course->lms_course_id, $data);
                    if ($lms_result['type'] === 'error') {
                        $response['message'] = 'Error creating this assignment on your LMS: ' . $lms_result['message'];
                        return $response;
                    } else {
                        $assignment->lms_assignment_id = $lms_result['message']->id;
                        $assignment->save();
                    }
                }

            } else {

                $repeated_groups = $this->groupsMustNotRepeat($assign_tos);
                if ($repeated_groups) {
                    $response['message'] = $repeated_groups;
                    return $response;
                }
                if ($course->alpha && $request->points_per_question === 'question weight') {
                    $response['message'] = "Alpha courses cannot determine question points by weight.";
                    return $response;
                }
                DB::beginTransaction();

                $assignment_info = $this->getAssignmentProperties($data, $request);
                $assignment_info['lms_grade_passback'] = $course->lms ? $request->lms_grade_passback : null;
                $assignment_info['name'] = $data['name'];
                $assignment_info['course_id'] = $course->id;
                $assignment_info['order'] = $assignment->getNewAssignmentOrder($course);
                $assignment_info['shown'] = 0;
                $assignment = Assignment::create($assignment_info);
                $autoRelease->handleUpdateOrCreate($request->all(), 'assignment', $assignment->id, $request->assessment_type);

                $this->addAssignTos($assignment, $assign_tos, $section, $request->user());
                $assignment->updateShownBasedOnAutoRelease($request->auto_release_shown);

                $betaAssignment->addBetaAssignments($course, $betaCourse, $assignment, $section, $assign_tos, $request->user());
                $this->addAssignmentGroupWeight($assignment, $data['assignment_group_id'], $assignmentGroupWeight);
                $beta_assignments = $betaAssignment->where('alpha_assignment_id', $assignment->id)->get();
                $assignments = [$assignment];
                foreach ($beta_assignments as $beta_assignment) {
                    $assignments[] = Assignment::find($beta_assignment->id);
                }
                foreach ($assignments as $assignment) {
                    $course = $assignment->course;
                    if ($course->lms_course_id) {
                        $lmsApi = new LmsAPI();
                        $data = $assignment->getIsoUnlockAtDueAt($data);
                        $lms_result = $lmsApi->createAssignment($course->getLtiRegistration(),
                            $course->user_id, $course->lms_course_id, $data);
                        if ($lms_result['type'] === 'error') {
                            $response['message'] = 'Error creating this assignment on your LMS: ' . $lms_result['message'];
                            return $response;
                        } else {
                            $assignment->lms_assignment_id = $lms_result['message']->id;
                            $assignment->save();
                        }
                    }
                }
                DB::commit();
            }
            $response['type'] = 'success';
            $response['message'] = "The assignment <strong>{$data['name']}</strong> has been created.";
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating <strong>{$data['name']}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public
    function copyAssignTos(Assignment $assignment, Assignment $new_assignment)
    {

        foreach ($assignment->assignToTimings as $assignToTiming) {
            $newAssignToTiming = new AssignToTiming();
            $newAssignToTiming->assignment_id = $new_assignment->id;
            $newAssignToTiming->available_from = $assignToTiming->available_from;
            $newAssignToTiming->due = $assignToTiming->due;
            $newAssignToTiming->final_submission_deadline = $assignToTiming->final_submission_deadline;
            $newAssignToTiming->save();
            $assign_to_groups = $assignToTiming->assignToGroups;
            foreach ($assign_to_groups as $assign_to_group) {
                $newAssignToGroup = new AssignToGroup();
                $newAssignToGroup->group = $assign_to_group->group;
                $newAssignToGroup->group_id = $assign_to_group->group_id;
                $newAssignToGroup->assign_to_timing_id = $newAssignToTiming->id;
                $newAssignToGroup->save();
            }
            foreach ($assignToTiming->assignToUsers as $assignToUser) {
                $newAssignToUser = new AssignToUser();
                $newAssignToUser->assign_to_timing_id = $newAssignToTiming->id;
                $newAssignToUser->user_id = $assignToUser->user_id;
                $newAssignToUser->save();
            }
        }


    }

    public
    function removeDuplicateUsers($enrolled_users, $assigned_users)
    {
        foreach ($enrolled_users as $key => $enrolled_user) {
            if (in_array($enrolled_user->user_id, $assigned_users)) {
                $enrolled_users->forget($key);
            }
        }
        return $enrolled_users;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Score $score
     * @param SubmissionFile $submissionFile
     * @return array
     * @throws Exception
     */
    public
    function viewQuestionsInfo(Request        $request,
                               Assignment     $assignment,
                               Score          $score,
                               SubmissionFile $submissionFile): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            $response['is_lms'] = (bool)$assignment->course->lms;//if no access, need this to determine whether to show the time zones
            return $response;
        }
        $is_fake_student = $request->user()->fake_student;

        try {
            $assignment = Assignment::find($assignment->id);
            if ($assignment->course->lms
                && !$assignment->course->lms_only_entry
                && !DB::table('lti_assignments_and_grades_urls')->where('assignment_id', $assignment->id)->exists()) {
                $response['message'] = 'no assignment and grades url';
                return $response;
            }
            $can_view_assignment_statistics = $request->user()->role === 2 || ($request->user()->role === 3 && $assignment->students_can_view_assignment_statistics);
            $response['assignment'] = [
                'question_view' => $request->hasCookie('question_view') ? $request->cookie('question_view') : 'basic',
                'name' => $assignment->name,
                'algorithmic' => $assignment->algorithmic,
                'mastery_retake_enabled' => (bool)$assignment->mastery_retake_enabled,
                'is_anonymous_user' => $request->user()->email === 'anonymous' && $assignment->course->user_id !== $request->user()->id,
                'assessment_type' => $assignment->assessment_type,
                'formative' => $assignment->formative,
                'flashcard_settings' => json_decode($assignment->flashcard_settings),
                'number_of_allowed_attempts' => $assignment->number_of_allowed_attempts,
                'number_of_allowed_attempts_penalty' => $assignment->number_of_allowed_attempts_penalty,
                'can_view_hint' => $assignment->can_view_hint,
                'can_submit_work' => $assignment->can_submit_work,
                'submitted_work_format' => $assignment->submitted_work_format ? json_decode($assignment->submitted_work_format) : [],
                'submitted_work_policy' => $assignment->submitted_work_policy,
                'hint_penalty' => $assignment->hint_penalty,
                'file_upload_mode' => $assignment->file_upload_mode,
                'has_submissions_or_file_submissions' => $assignment->hasNonFakeStudentFileOrQuestionSubmissions(),
                'time_left' => Auth::user()->role === 3 ? $this->getTimeLeft($assignment) : '',
                'late_policy' => $assignment->late_policy,
                'past_due' => Auth::user()->role === 3 ? time() > strtotime($assignment->assignToTimingByUser('due')) : '',
                'available' => !(Auth::user()->role === 3 && !$is_fake_student) || time() > strtotime($assignment->assignToTimingByUser('available_from')),
                'available_on' => (Auth::user()->role === 3 && !$is_fake_student) ? $this->convertUTCMysqlFormattedDateToLocalDateAndTime($assignment->assignToTimingByUser('available_from'), Auth::user()->time_zone) : '',
                'total_points' => $this->getTotalPoints($assignment),
                'points_per_question' => $assignment->points_per_question,
                'source' => $assignment->source,
                'default_clicker_time_to_submit' => $assignment->default_clicker_time_to_submit,
                'submission_files' => $assignment->submission_files,
                'show_points_per_question' => $assignment->show_points_per_question,
                'solutions_released' => $assignment->solutions_released,
                'show_scores' => $assignment->show_scores,
                'question_url_view' => $assignment->question_url_view,
                'shown' => !(Auth::user()->role === 3 && !$is_fake_student) || Helper::isAnonymousUser() || $assignment->shown,
                'scoring_type' => $assignment->scoring_type,
                'students_can_view_assignment_statistics' => $assignment->students_can_view_assignment_statistics,
                'scores' => $can_view_assignment_statistics
                    ? $score->where('assignment_id', $assignment->id)->get()->pluck('score')
                    : [],
                'beta_assignments_exist' => $assignment->betaAssignments() !== [],
                'is_beta_assignment' => $assignment->isBetaAssignment(),
                'is_formative_course' => (bool)$assignment->course->formative,
                'is_lms' => (bool)$assignment->course->lms,
                'question_numbers_shown_in_iframe' => (bool)$assignment->course->question_numbers_shown_in_iframe,
                'lti_launch_exists' => Auth::user()->role === 3 && !$is_fake_student && $assignment->ltiLaunchExists(Auth::user()),
                'can_contact_grader' => !DB::table('contact_grader_overrides')
                    ->where('course_id', $assignment->course->id)
                    ->where('user_id', -1)->exists()];
            if (Auth::user()->role === 3) {
                $response['assignment']['can_contact_instructor_auto_graded'] = $assignment->can_contact_instructor_auto_graded;
                $response['assignment']['full_pdf_url'] = '';
                $submission_file = $submissionFile->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->where('type', 'a')
                    ->first();
                if ($submission_file) {
                    $response['assignment']['full_pdf_url'] = $this->getTemporaryUrl($assignment->id, $submission_file->submission);
                }

            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getQuestionsInfo(Assignment $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('getQuestionsInfo', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignment = Assignment::find($assignment->id);
            $response['assignment'] = [
                'name' => $assignment->name,
                'has_submissions' => $assignment->hasNonFakeStudentFileOrQuestionSubmissions(),
                'submission_files' => $assignment->submission_files,
                'assessment_type' => $assignment->assessment_type,
                'formative' => $assignment->course->formative || $assignment->formative
            ];
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment.  Please try again or contact us for assistance.";

        }
        return $response;
    }


    /*
    * Display the specified resource
    *
    * @param Assignment $assignment
    * @return Assignment
    */
    public
    function scoresInfo(Assignment $assignment, Score $score)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('scoresInfo', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignment = Assignment::find($assignment->id);
            $response['scores'] = $score->where('assignment_id', $assignment->id)->get()->pluck('score');
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Solution $Solution
     * @param Question $Question
     * @param Webwork $webwork
     * @return array
     * @throws Exception
     */
    public
    function getInfoForGrading(Request    $request,
                               Assignment $assignment,
                               Solution   $Solution,
                               Question   $Question,
                               Webwork    $webwork): array
    {

        $response['type'] = 'error';
        try {
            $assignment = Assignment::find($assignment->id);
            $sections = [];
            switch ($request->user()->role) {

                case(2):
                    $sections = $assignment->course->sections;
                    break;
                case(4):
                    $sections = $assignment->course->graderSections();
                    $access_level_override = $assignment->graders()
                        ->where('assignment_grader_access.user_id', $request->user()->id)
                        ->first();
                    if ($access_level_override) {
                        $access_level = $access_level_override->pivot->access_level;
                        if ($access_level === 0) {
                            $sections = [];
                        }
                        if ($access_level === 1) {
                            $sections = $assignment->course->sections;
                        }
                    }
                    break;
            }


            $response['sections'] = [];
            $response['questions'] = [];
            foreach ($sections as $section) {
                $response['sections'][] = ['name' => $section->name, 'id' => $section->id];
            }

            $assignment_questions_where_student_can_upload_file = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->orderBy('order')
                ->get();

            $questions_by_id = [];
            $question_ids = $assignment->questions->pluck('id')->toArray();
            $uploaded_solutions_by_question_id = $Solution->getUploadedSolutionsByQuestionId($assignment, $question_ids);
            $questions = DB::table('questions')->whereIn('id', $question_ids)->get();

            $rubrics_by_question_id = [];
            foreach ($questions as $question) {
                $questions_by_id[$question->id] = $question;
            }
            foreach ($assignment_questions_where_student_can_upload_file as $value) {
                $rubrics_by_question_id[$value->question_id] = $value->custom_rubric ?: $questions_by_id[$question->id]->rubric;
            }

            $dom = new DOMDocument();
            foreach ($assignment_questions_where_student_can_upload_file as $question) {
                $question_id = $question->question_id;
                $solution['solution_file_url'] = $uploaded_solutions_by_question_id[$question_id]['solution_file_url'] ?? false;
                $solution['solution_text'] = $uploaded_solutions_by_question_id[$question_id]['solution_text'] ?? false;
                $solution['solution_type'] = null;
                if ($in_code_solution = $webwork->inCodeSolution($questions_by_id[$question_id])) {
                    $questions_by_id[$question_id]->solution_html = $in_code_solution;
                }
                $solution['solution_html'] = $Question->addTimeToS3Files($questions_by_id[$question_id]->solution_html, $dom);
                if (!$solution['solution_html']) {
                    $solution['solution_html'] = $questions_by_id[$question_id]->answer_html;
                }
                if ($solution['solution_html']) {
                    $solution['solution_type'] = 'html';
                }
                if ($solution['solution_file_url']) {
                    $solution['solution_type'] = 'q';
                }
                $solution['qti_answer_json'] = '';
                if (!$solution['solution_html'] && $questions_by_id[$question_id]->qti_json) {
                    $solution['qti_answer_json'] = $Question->formatQtiJson('answer_json', $questions_by_id[$question_id]->qti_json, [], true);
                }
                $response['questions'][] = ['text' => "$question->order",
                    'value' => $question->question_id,
                    'solution' => $solution,
                    'rubric' => $rubrics_by_question_id[$question->question_id]];
            }
            $response['assignment'] = [
                'name' => $assignment->name,
                'scoring_type' => $assignment->scoring_type,
                'late_policy' => $assignment->late_policy,
                'late_deduction_percent' => $assignment->late_deduction_percent,
                'late_deduction_application_period' => $assignment->late_deduction_application_period,
                'rubric_categories' => $assignment->rubricCategories
            ];
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    public
    function groupsMustNotRepeat($assign_tos)
    {
        $used_groups = [];
        $message = null;
        foreach ($assign_tos as $assign_to) {
            foreach ($assign_to['groups'] as $group) {
                if (in_array($group, $used_groups)) {
                    $message = "{$group['text']} was chosen twice as an assign to.";
                }
                $used_groups[] = $group;
            }
        }
        return $message;
    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public function getCourseNameFromAssignment(Assignment $assignment)
    {
        try {
            $response['type'] = 'error';
            $response['course_name'] = $assignment->course->name;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AssignmentGroup $assignmentGroup
     * @param SubmissionFile $submissionFile
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function getAssignmentSummary(Request                $request,
                                  Assignment             $assignment,
                                  AssignmentGroup        $assignmentGroup,
                                  SubmissionFile         $submissionFile,
                                  AssignmentSyncQuestion $assignmentSyncQuestion,
                                  Question               $question): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('getAssignmentSummary', $assignment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $role = $request->user()->role;
        try {
            $assignment = Assignment::find($assignment->id);
            $can_view_assignment_statistics = in_array($role, [2, 4])
                || ($role === 3 && $assignment->students_can_view_assignment_statistics);
            $response['assignment'] = $assignment->attributesToArray();
            $response['assignment']['instructions'] = $response['assignment']['instructions'] ? $question->addTimeToS3Files($response['assignment']['instructions'], new DOMDocument(), false) : '';
            $formatted_items = [
                'assignment_groups' => $assignmentGroup->assignmentGroupsByCourse($assignment->course->id),
                'total_points' => Helper::removeZerosAfterDecimal(round($this->getTotalPoints($assignment), 2)),
                'can_view_assignment_statistics' => $can_view_assignment_statistics,
                'number_of_questions' => count($assignment->questions),
                'number_of_randomized_questions_chosen' => $assignment->number_of_randomized_assessments
                    ?: "N/A"
            ];
            if (auth()->user()->role === 3) {
                $extension = DB::table('extensions')
                    ->select('extension')
                    ->where('assignment_id', $assignment->id)
                    ->where('user_id', auth()->user()->id)
                    ->first('extension');
                if ($assignment->assessment_type === 'clicker') {
                    $formatted_items['course_name'] = $assignment->course->name;
                    $instructor_user_id = $assignment->course->user_id;
                    $instructor = User::find($instructor_user_id);
                    $formatted_items['instructor_name'] = "$instructor->first_name $instructor->last_name";
                }
                $formatted_items['is_instructor_logged_in_as_student'] = auth()->user()->instructor_user_id;
                $formatted_items['completed_all_assignment_questions'] = $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment);
                $formatted_items['full_pdf_url'] = $submissionFile->getFullPdfUrl($assignment);
                $assign_to_timing = $assignment->assignToTimingByUser();
                $formatted_items['formatted_late_policy'] = $this->formatLatePolicy($assignment, $assign_to_timing);
                $formatted_items['final_submission_deadline'] = $final_submission_deadline = ($assignment->late_policy !== 'not accepted') && ($assign_to_timing !== null)
                    ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($assign_to_timing->final_submission_deadline, Auth::user()->time_zone, 'n/j/y g:ia')
                    : '';
                $formatted_items['past_due'] = time() > strtotime($assign_to_timing->due);
                $formatted_items['extension'] = $extension ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($extension->extension, Auth::user()->time_zone, 'n/j/y g:ia') : null;
                $formatted_items['due'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($assign_to_timing->due, Auth::user()->time_zone);
                $formatted_items['formatted_due'] = $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($assign_to_timing->due, Auth::user()->time_zone, 'n/j/y g:ia');
                $formatted_items['available_on'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($assign_to_timing->available_from, Auth::user()->time_zone);

            } else {
                $autoRelease = new AutoRelease();
                $auto_release_keys = $autoRelease->keys();
                $auto_releases = AutoRelease::where('type', 'assignment')->where('type_id', $assignment->id)->first();
                foreach ($auto_release_keys as $auto_release_key) {
                    $formatted_items["auto_release_$auto_release_key"] = $auto_releases ? $auto_releases->{$auto_release_key} : null;
                    $activated = $auto_release_key . "_activated";
                    $formatted_items["auto_release_activated_$auto_release_key"] = $formatted_items["auto_release_$auto_release_key"]
                        ? $auto_releases->{$activated}
                        : 0;

                    if ($auto_release_key !== 'shown') {
                        $after_key = $auto_release_key . "_after";
                        $formatted_items["auto_release_{$auto_release_key}_after"] = $auto_releases ? $auto_releases->{$after_key} : null;
                    }
                }
                $question_exists_not_owned_by_the_instructor = DB::table('assignment_question')
                    ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                    ->where('question_editor_user_id', '<>', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->first();
                $lms = $assignment->course->lms;
                $formatted_items['lms'] = $lms;
                $formatted_items['lms_api'] = (bool)$assignment->course->lms_api_id;
                $formatted_items['is_formative_course'] = (bool)$assignment->course->formative;
                $formatted_items['owns_all_questions'] = !$question_exists_not_owned_by_the_instructor;
                $formatted_items['formative'] = (bool)$assignment->formative;
                $formatted_items['is_beta_assignment'] = $assignment->isBetaAssignment();
                $formatted_items['is_alpha_course'] = (bool)$assignment->course->alpha;
                $formatted_items['can_submit_work'] = $assignment->can_submit_work;
                $formatted_items['course_end_date'] = $assignment->course->end_date;
                $formatted_items['course_start_date'] = $assignment->course->start_date;
                $formatted_items['anonymous_users'] = $assignment->course->anonymous_users;
                $formatted_items['assign_tos'] = $assignment->assignToGroups();
                $num_open = $num_closed = $num_upcoming = $num_assign_tos = $num_late = 0;
                foreach ($formatted_items['assign_tos'] as $assign_to_key => $assign_to) {

                    $available_from = $assign_to['available_from'];
                    $due = $assign_to['due'];
                    $formatted_items['formatted_late_policy'] = $this->formatLatePolicy($assignment, null);
                    $final_submission_deadline = $assign_to['final_submission_deadline'];
                    $status = $assignment->getStatus($available_from, $due, $final_submission_deadline);

                    switch ($status) {
                        case('Open'):
                            $num_open++;
                            break;
                        case('Closed'):
                            $num_closed++;
                            break;
                        case ('Upcoming'):
                            $num_upcoming++;
                            break;
                        case('Late'):
                            $num_late++;
                    }
                    $num_assign_tos++;

                    $formatted_items['assign_tos'][$assign_to_key]['status'] = $assignment->getStatus($available_from, $due, $final_submission_deadline);
                    $formatted_items['assign_tos'][$assign_to_key]['available_from_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($available_from, Auth::user()->time_zone);
                    $formatted_items['assign_tos'][$assign_to_key]['available_from_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($available_from, Auth::user()->time_zone);
                    $formatted_items['assign_tos'][$assign_to_key]['final_submission_deadline_date'] = $final_submission_deadline ? $this->convertUTCMysqlFormattedDateToLocalDate($final_submission_deadline, Auth::user()->time_zone) : null;
                    $formatted_items['assign_tos'][$assign_to_key]['final_submission_deadline_time'] = $final_submission_deadline ? $this->convertUTCMysqlFormattedDateToLocalTime($final_submission_deadline, Auth::user()->time_zone) : null;
                    $formatted_items['assign_tos'][$assign_to_key]['due_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($due, Auth::user()->time_zone);
                    $formatted_items['assign_tos'][$assign_to_key]['due_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($due, Auth::user()->time_zone);
                }
                $formatted_items['overall_status'] = $assignment->getOverallStatus($num_assign_tos, $num_open, $num_closed, $num_upcoming, $num_late);

            }

            foreach ($formatted_items as $key => $value) {
                $response['assignment'][$key] = $value;
            }

            $response['type'] = 'success';


        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment.  Please try again or contact us for assistance.";

        }

        return $response;
    }

    /**
     * @param $assignment
     * @param $assign_to_timing
     * @return string
     */
    public
    function formatLatePolicy($assignment, $assign_to_timing): string
    {
        //$assign_to_timing will be appropriate for students
        $late_policy = '';
        $final_submission_deadline = ($assignment->late_policy !== 'not accepted') && ($assign_to_timing !== null)
            ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($assign_to_timing->final_submission_deadline, Auth::user()->time_zone, 'n/j/y g:ia')
            : '';
        switch ($assignment->late_policy) {
            case('not accepted'):
                $late_policy = "No late submissions are accepted.";
                break;
            case('marked late'):
                $late_policy = "Late submissions are marked late.  It is up to the instructor's discretion whether to apply a late penalty.";
                break;
            case('deduction'):
                if ($assignment->late_deduction_application_period === 'once') {
                    $late_policy = "A deduction of {$assignment->late_deduction_percent}% is applied once to any late submission.  ";
                } else {
                    $late_policy = "A deduction of {$assignment->late_deduction_percent}% is applied every {$assignment->late_deduction_application_period} to any late submission.  ";
                }
                $late_policy .= "This penalty is applied to the latest submission up until the final submission deadline.";
                break;
        }
        if (($assignment->late_policy !== 'not accepted') && ($assign_to_timing !== null)) {
            $late_policy .= "  Students cannot submit responses later than $final_submission_deadline.";
        }

        return $late_policy;
    }

    public
    function getTimeLeft(Assignment $assignment)
    {
        $Extension = new Extension();
        $extensions_by_user = $Extension->getUserExtensionsByAssignment(Auth::user());
        $due = $extensions_by_user[$assignment->id] ?? $assignment->assignToTimingByUser('due');
        $now = Carbon::now();
        return max($now->diffInMilliseconds(Carbon::parse($due), false), 0);

    }

    public
    function getTotalPoints(Assignment $assignment)
    {
        $total_points = $assignment->number_of_randomized_assessments
            ? $assignment->number_of_randomized_assessments * $assignment->default_points_per_question
            : DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->sum('points');
        return Helper::removeZerosAfterDecimal(round($total_points, 4));
    }


    /**
     * @param StoreAssignmentProperties $request
     * @param Assignment $assignment
     * @param AssignmentGroupWeight $assignmentGroupWeight
     * @param Section $section
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function update(StoreAssignmentProperties $request,
                    Assignment                $assignment,
                    AssignmentGroupWeight     $assignmentGroupWeight,
                    Section                   $section,
                    AssignmentSyncQuestion    $assignmentSyncQuestion,
                    AutoRelease               $autoRelease): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {

            $data = $request->validated();
            DB::beginTransaction();
            if ($request->user()->role === 5) {
                $assignment->name = $data['name'];
                $assignment->public_description = $request->public_description;
                $assignment->private_description = $request->private_description;
                $assignment->save();
            } else {
                if ($assignment->assessment_type !== $request->assessment_type) {
                    $message = $this->validAssessmentTypeSwitch($assignment, $request->assessment_type);
                    if ($message) {
                        $response['message'] = $message;
                        $response['timeout'] = 12000;
                        return $response;
                    }
                }


                $assign_tos = $this->reformatAssignToTimes($request->assign_tos);
                $repeated_groups = $this->groupsMustNotRepeat($assign_tos);
                if ($repeated_groups) {
                    $response['message'] = $repeated_groups;
                    return $response;
                }

                $switching_scoring_type = ($request->scoring_type === 'c' && $assignment->scoring_type === 'p') || ($request->scoring_type === 'p' && $assignment->scoring_type === 'c');
                if ($switching_scoring_type && $assignment->hasNonFakeStudentFileOrQuestionSubmissions()) {
                    $response['message'] = "You can't switch the scoring type if there are student submissions.";
                    return $response;
                }

                if ($request->scoring_type === 'c' && $assignment->scoring_type === 'p') {

                    DB::table('assignment_question')
                        ->where('assignment_id', $assignment->id)
                        ->update(['completion_scoring_mode' => Helper::getCompletionScoringMode('c', $request->default_completion_scoring_mode, $request->completion_split_auto_graded_percentage)]);
                }
                $assignment_questions = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                    ->whereNotNull('forge_settings')
                    ->get();
                foreach ($assignment_questions as $assignment_question) {
                    $forge_settings = json_decode($assignment_question->forge_settings, true);
                    foreach ($forge_settings['drafts'] as $key => $draft) {
                        if ($draft['isFinal'] && $forge_settings['final_submission_locked']) {
                            $clean_assign_tos = [];
                            foreach ($assign_tos as $assign_to) {
                                $clean_assign_tos[] = $assignmentSyncQuestion->draftAssignTo($assign_to, request()->user()->time_zone);
                            }
                            $forge_settings['drafts'][$key]['assign_tos'] = $clean_assign_tos;
                            $forge_settings['drafts'][$key]['late_policy'] = $request->late_policy;
                            $forge_settings['drafts'][$key]['late_deduction_percent'] = $request->late_deduction_percent;
                            $forge_settings['drafts'][$key]['late_deduction_applied_once'] = $request->late_deduction_applied_once;
                            $forge_settings['drafts'][$key]['late_deduction_application_period'] = $request->late_deduction_period;
                        }
                    }
                    $forge_settings = json_encode($forge_settings);

                    $assignment_question->forge_settings = $forge_settings;
                    $assignment_question->save();
                }
                if ($request->scoring_type === 'p' && $assignment->scoring_type === 'c') {
                    DB::table('assignment_question')
                        ->where('assignment_id', $assignment->id)
                        ->update(['completion_scoring_mode' => null]);
                }

                if ($assignment->course->alpha && $request->points_per_question === 'question weight') {
                    $response['message'] = "Alpha courses cannot determine question points by weight.";
                    return $response;
                }
                $assignment->updateShownBasedOnAutoRelease($request->auto_release_shown);
                $original_assignment = $assignment;

                $assignments = $assignment->course->alpha
                    ? $assignment->addBetaAssignments()
                    : [$assignment];


                $data = $autoRelease->handleUpdateOrCreate($data, 'assignment', $assignment->id, $request->assessment_type);
                if ($assignment->points_per_question !== $request->points_per_question) {
                    $message = $this->validPointsPerQuestionSwitch($assignment);
                    if ($message) {
                        $response['message'] = $message;
                        return $response;
                    }
                    if (count($assignments) > 1) {
                        $response['message'] = "This is an Alpha assignment with tethered Beta assignments so you cannot switch the Points Per Question value.";
                        return $response;
                    }
                    $assignmentSyncQuestion->switchPointsPerQuestion($assignment, $request->total_points);
                }

                if ($assignment->points_per_question === 'question weight' && round($assignment->total_points, 4) !== round($request->total_points, 4)) {
                    if (count($assignments) > 1) {
                        $response['message'] = "This is an Alpha assignment with tethered Beta assignments so you cannot update the Total Points per Assignment.";
                        return $response;
                    }
                    $assignment->scaleColumnsWithNewTotalPoints($request->total_points);
                }


                foreach ($assignments as $assignment) {
                    if (!$assignment->isBetaAssignment()) {
                        //either the alpha assignment, so set these
                        //OR it's just a regular assignment so set these
                        $data_to_update = $this->getDataToUpdate($data, $request);
                        foreach ($data_to_update as $key => $value) {
                            $data[$key] = $value;
                        }
                    }
                    if ($assignment->isBetaAssignment()) {
                        //might be other things to unset.  But at the very least I was getting errors with these
                        //because I wasn't removing the percents
                        unset($data['hint_penalty']);
                        unset($data['number_of_allowed_attempts_penalty']);
                        $alpha_assignment_group_id = $data['assignment_group_id'];
                        $alpha_assignment_group = DB::table('assignment_groups')
                            ->where('id', $alpha_assignment_group_id)
                            ->first();
                        $beta_assignment_group = DB::table('assignment_groups')
                            ->where('assignment_group', $alpha_assignment_group->assignment_group)
                            ->where('course_id', $assignment->course->id)
                            ->first();
                        $default_assignment_group = DB::table('assignment_groups')
                            ->where('assignment_group', $alpha_assignment_group->assignment_group)
                            ->where('user_id', 0)
                            ->where('course_id', 0)
                            ->first();

                        if ($default_assignment_group) {
                            $data['assignment_group_id'] = $default_assignment_group->id;
                        } else if ($beta_assignment_group) {
                            $data['assignment_group_id'] = $beta_assignment_group->id;
                        } else {
                            $data['assignment_group_id'] = DB::table('assignment_groups')
                                ->insert(['assignment_group' => $alpha_assignment_group->assignment_group,
                                    'user_id' => $assignment->course->user_id,
                                    'course_id' => $assignment->course_id]);
                        }
                    }
                    $data['late_deduction_application_period'] = $this->getLateDeductionApplicationPeriod($request, $data);

                    //submissions exist so don't let them change the things below
                    if (isset($assign_tos)) {
                        unset($data['available_from_date']);
                        unset($data['available_from_time']);
                        unset($data['final_submission_deadline']);
                        unset($data['open_ended_response']);
                        foreach ($assign_tos as $key => $assign_to) {
                            unset($data['groups_' . $key]);
                            unset($data['due_' . $key]);
                            unset($data['final_submission_deadline_' . $key]);
                            unset($data['available_from_date_' . $key]);
                            unset($data['available_from_time_' . $key]);
                            unset($data['due_time_' . $key]);
                            unset($data['final_submission_deadline_date' . $key]);
                            unset($data['final_submission_deadline_time_' . $key]);
                        }
                    }
                    $assignment->update($data);
                    if (!$assignment->course->formative && !$assignment->formative) {
                        $this->addAssignmentGroupWeight($assignment, $data['assignment_group_id'], $assignmentGroupWeight);
                    }
                    if (isset($assign_tos)) {
                        $this->addAssignTos($assignment, $assign_tos, $section, $request->user());
                    }
                    unset($assign_tos);//should just be done for the alpha course if it is an alpha course
                }

                $course = $original_assignment->course;
                if ($course->lms_course_id) {
                    $lmsApi = new LmsAPI();
                    $data = $original_assignment->formative
                        ? $original_assignment->toArray()
                        : $original_assignment->getIsoUnlockAtDueAt($original_assignment->toArray());

                    $lms_result = $lmsApi->updateAssignment(
                        $course->getLtiRegistration(),
                        $course->user_id,
                        $course->lms_course_id,
                        $original_assignment->lms_assignment_id,
                        $data);
                    if ($lms_result['type'] === 'error') {
                        $response['message'] = 'Error updating this assignment on your LMS: ' . $lms_result['message'];
                        return $response;
                    }
                }
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The assignment <strong>{$data['name']}</strong> has been updated.";
        } catch
        (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating <strong>{$data['name']}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     *
     * Delete an assignment
     *
     * @param Assignment $assignment
     * @param AssignToTiming $assignToTiming
     * @param BetaAssignment $betaAssignment
     * @return array
     * @throws Exception
     */
    public
    function destroy(Assignment     $assignment,
                     AssignToTiming $assignToTiming,
                     BetaAssignment $betaAssignment): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('delete', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        //temporary fix for one assignment
        if ($assignment->id !== 1389 && $betaAssignment->where('alpha_assignment_id', $assignment->id)->first()) {
            $response['message'] = "You cannot delete an Alpha assignment with tethered Beta assignments.";
            return $response;
        }
        $beta_assignments = $betaAssignment->where('alpha_assignment_id', $assignment->id)->get();
        try {
            DB::beginTransaction();
            if ($beta_assignments->isNotEmpty()) {
                foreach ($beta_assignments as $beta_assignment) {
                    //right now this isn't allowed (see You cannot delete... above)
                    //However, if I manually untether, then it can be done.
                    //will be useful when I eventually give admin privileges to do this
                    //will also have to code up the untethering
                    $betaAssignment = Assignment::find($beta_assignment->id);
                    DB::table('beta_course_approvals')->where('beta_assignment_id', $betaAssignment->id)->delete();
                    DB::table('beta_assignments')->where('id', $betaAssignment->id)->delete();
                    $betaAssignment->removeAllAssociatedInformation($assignToTiming);
                    if ($betaAssignment->course->lms_course_id) {
                        $lmsApi = new LmsAPI();
                        $lms_result = $lmsApi->deleteAssignment($betaAssignment->course->getLtiRegistration(),
                            $betaAssignment->course->user_id,
                            $betaAssignment->course->lms_course_id,
                            $betaAssignment->lms_assignment_id);
                        try {
                            if ($lms_result['type'] === 'error' && strpos($lms_result['message'], 'The specified resource does not exist.') === false) {
                                throw new Exception("Tried removing a Beta assignment from an LMS and got an error: " . $lms_result['message'] . " with LMS course id {$betaAssignment->course->lms_course_id} and LMS assignment id { $betaAssignment->lms_assignment_id}");
                            }
                        } catch (Exception $e) {
                            $h = new Handler(app());
                            $h->report($e);

                        }
                    }
                }
            }
            if ($assignment->course->lms_course_id && $assignment->lms_assignment_id) {
                $lmsApi = new LmsAPI();
                $lms_result = $lmsApi->deleteAssignment($assignment->course->getLtiRegistration(),
                    $assignment->course->user_id,
                    $assignment->course->lms_course_id,
                    $assignment->lms_assignment_id);
                if ($lms_result['type'] === 'error' && strpos($lms_result['message'], 'The specified resource does not exist.') === false) {
                    $response['message'] = 'Error deleting this assignment on your LMS: ' . $lms_result['message'];
                    return $response;
                }
            }
            $assignment->removeAllAssociatedInformation($assignToTiming);
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The assignment <strong>$assignment->name</strong> has been deleted.";
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error removing <strong>$assignment->name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param Assignment $assignment
     * @return string
     */
    public
    function validPointsPerQuestionSwitch(Assignment $assignment): string
    {

        if ($assignment->course->alpha) {
            $message = $assignment->hasNonFakeStudentFileOrQuestionSubmissions($assignment->addBetaAssignmentIds())
                ? "This assignment is in an Alpha course with Beta course submissions so you can't change the way that points are computed."
                : '';

        } else {
            $message = $assignment->hasNonFakeStudentFileOrQuestionSubmissions()
                ? "This assignment already has submissions so you can't change the way that points are computed."
                : '';

        }
        return $message;
    }

    /**
     * @param Assignment $assignment
     * @param $new_assessment_type
     * @return string
     */
    public
    function validAssessmentTypeSwitch(Assignment $assignment, $new_assessment_type): string
    {
        $has_questions = count($assignment->questions) > 0;

        $message = '';
        if ($has_questions) {
            switch ($assignment->assessment_type) {
                case('learning tree'):
                    $message = "This assignment already has non-Learning Tree assessments in it.  If you would like to change the assessment type, please first remove those assessments.";
                    break;
                case('delayed'):
                    if ($new_assessment_type == 'clicker') {
                        $new_assessment_type = ucfirst($new_assessment_type);
                        foreach ($assignment->questions as $question) {
                            if (!$question->technology_iframe) {
                                $message = "If you would like to change this assignment to $new_assessment_type, all of your assessments must have an associated auto-graded component H5P or Webwork.  Please remove any assessments that don't have auto-graded component.";
                                break;
                            }
                        }
                        $open_ended_submissions = DB::table('assignment_question')
                            ->where('assignment_id', $assignment->id)
                            ->where('open_ended_submission_type', '<>', '0')
                            ->first();
                        if ($open_ended_submissions) {
                            $message = "If you would like to change this assignment to $new_assessment_type, please first remove any assessments that require an open-ended submission.";
                        }
                    }
                    if ($new_assessment_type === 'learning tree') {
                        $message = "You can't switch from a Delayed to a Learning Tree assessment type until you remove all current assessments.";
                    }
                    break;
                case('clicker'):
                case('real time'):
                    if ($new_assessment_type === 'learning tree') {
                        $message = "Please first remove all Learning Tree assessments before choosing this option.";
                    }
                    break;
            }
        }

        return $message;

    }

    /**
     * @param $assign_tos
     * @return array
     */
    function reformatAssignToTimes($assign_tos): array
    {
        foreach ($assign_tos as $key => $assign_to) {
            foreach (['available_from_time', 'due_time', 'final_submission_deadline_time'] as $time_key => $value) {
                if (isset($assign_tos[$key][$value])) {
                    $assign_tos[$key][$value] = DateTime::createFromFormat('g:i A', $assign_to[$value])->format('H:i:00');
                }
            }
        }
        return $assign_tos;
    }

}
