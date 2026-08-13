<?php

namespace App\Http\Controllers;

use App\Analytics;
use App\Assignment;
use App\AssignmentGroup;
use App\AssignmentGroupWeight;
use App\AssignmentSyncQuestion;
use App\AssignToTiming;
use App\AssignToUser;
use App\AutoRelease;
use App\BetaAssignment;
use App\BetaCourse;
use App\BetaCourseApproval;
use App\CoInstructor;
use App\ContactGraderOverride;
use App\Course;
use App\CourseOrder;
use App\Custom\LTIDatabase;
use App\Discussion;
use App\Enrollment;
use App\Exceptions\Handler;
use App\FinalGrade;
use App\Http\Requests\AutoReleaseRequest;
use App\Http\Requests\DestroyCourse;
use App\Http\Requests\ImportCourseRequest;
use App\Http\Requests\LinkToLMSRequest;
use App\Http\Requests\ResetCourse;
use App\Http\Requests\StoreCourse;
use App\Jobs\DeleteAssignmentDirectoryFromS3;
use App\Jobs\ProcessImportCourse;
use App\Jobs\ProcessResetCourse;
use App\LmsAPI;
use App\LmsOutageCourse;
use App\LtiNamesAndRolesUrl;
use App\LtiRegistration;
use App\Section;
use App\Traits\DateFormatter;
use App\User;
use App\WhitelistedDomain;
use App\School;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Exceptions\InvalidSignatureException;
use MiladRahimi\Jwt\Exceptions\InvalidTokenException;
use MiladRahimi\Jwt\Exceptions\JsonDecodingException;
use MiladRahimi\Jwt\Exceptions\SigningException;
use MiladRahimi\Jwt\Exceptions\ValidationException;
use MiladRahimi\Jwt\Parser;
use Overrides\IMSGlobal\LTI\LTI_Names_Roles_Provisioning_Service;
use Overrides\IMSGlobal\LTI\LTI_Service_Connector;

class CourseController extends Controller
{

    use DateFormatter;

    /**
     * @param Request $request
     * @param Course $course
     * @param User $user
     * @param CoInstructor $coInstructor
     * @param CourseOrder $courseOrder
     * @param ContactGraderOverride $contactGraderOverride
     * @return array
     * @throws Exception
     */
    public function changeMainInstructor(Request               $request,
                                         Course                $course,
                                         User                  $user,
                                         CoInstructor          $coInstructor,
                                         CourseOrder           $courseOrder,
                                         ContactGraderOverride $contactGraderOverride): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('changeMainInstructor', $course);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $course->user_id = $user->id;
            $course->save();
            $coInstructor->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->delete();
            switch ($request->role_after_transfer) {
                case('become a co-instructor'):
                    $coInstructor = new CoInstructor();
                    $coInstructor->user_id = $request->user()->id;
                    $coInstructor->course_id = $course->id;
                    $coInstructor->status = 'accepted';
                    $coInstructor->save();
                    break;
                case('leave the course'):
                    $courseOrder->where('user_id', $request->user()->id)
                        ->where('course_id', $course->id)
                        ->delete();
                    if ($contactGraderOverride->where('user_id', $request->user()->id)
                        ->where('course_id', $course->id)->exists()) {
                        $contactGraderOverride->where('user_id', $user->id)
                            ->where('course_id', $course->id)
                            ->update(['user_id' => $user->id]);
                    }
            }

            DB::commit();
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error changing the main instructor.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     *
     */
    public function updateDiscipline(Request $request, Course $course)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateDiscipline', $course);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            if ($request->discipline_id === null) {
                $course->discipline_id = null;
                $course->save();
                $response['message'] = "The discipline has been removed from $course->name.";
                $response['type'] = 'info';
            } else if (DB::table('disciplines')->where('id', $request->discipline_id)) {
                $course->discipline_id = $request->discipline_id;
                $course->save();
                $response['message'] = "The discipline for $course->name has been updated.";
                $response['type'] = 'success';
            } else {
                $response['message'] = "That is not a valid discipline.";
            }
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the discipline.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Analytics $analytics
     * @return array
     * @throws InvalidSignatureException
     * @throws InvalidTokenException
     * @throws JsonDecodingException
     * @throws SigningException
     * @throws ValidationException|Exception
     */
    public function showMiniSummary(Request $request, Analytics $analytics): array
    {
        $response['type'] = 'error';

        try {
            $claims = $analytics->hasAccess($request);
            $course_id = $claims['course_id'];
            $course = Course::find($course_id);
            $response['mini-summary'] = $course ? collect($course->only(['name', 'user_id', 'start_date', 'end_date', 'textbook_url']))
                ->merge(['letter_grades_released' => $course->finalGrades->letter_grades_released])
                ->toArray() : [];
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the course mini-summary.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Course $course
     * @param Assignment $assignment
     * @return array
     */
    public function getAssignmentStatusesByCourse(Request $request, Course $course, Assignment $assignment): array
    {

        $assign_to_timings = DB::table('assignments')
            ->join('assign_to_timings', 'assignments.id', '=', 'assign_to_timings.assignment_id')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->whereIn('assignment_id', $course->assignments->pluck('id')->toArray())
            ->where('user_id', $request->user()->id)
            ->select('assign_to_timings.*', 'assignments.id', 'assignments.assessment_type')
            ->get();


        foreach ($assign_to_timings as &$assign_to_timing) {
            if ($assign_to_timing->assessment_type == 'clicker') {
                $assign_to_timing->status = 'N/A';
            } else {
                $assign_to_timing->status = $assignment->getStatus($assign_to_timing->available_from, $assign_to_timing->due, $assign_to_timing->final_submission_deadline);
            }
        }
        $assignment_ids = $course->assignments->pluck('id');
        $assignment_level_overrides = DB::table('assignment_level_overrides')
            ->join('users', 'assignment_level_overrides.user_id', '=', 'users.id')
            ->whereIn('assignment_id', $assignment_ids)
            ->where('user_id', $request->user()->id)
            ->get()
            ->pluck('assignment_id')
            ->toArray();
        $response['assignment_level_overrides'] = $assignment_level_overrides;
        $response['assignment_statuses'] = $assign_to_timings;
        $response['type'] = 'success';
        return $response;


    }


    /**
     * @param AutoReleaseRequest $request
     * @param Course $course
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public function autoRelease(AutoReleaseRequest $request,
                                Course             $course,
                                AutoRelease        $autoRelease): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('autoRelease', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $apply_to = $request->apply_to;
        unset($request->apply_to);
        if (!in_array($apply_to, ['all', 'future'])) {
            $response['message'] = $apply_to . " is not a valid 'apply to'.";
            return $response;
        }
        try {
            $request->validated();
            DB::beginTransaction();
            $data = $request->all();
            unset($data['apply_to']);
            $course->update($data);
            if ($apply_to === 'all') {
                $assignments = $course->assignments;
                $assignment_ids = $assignments->pluck('id')->toArray();
                $autoRelease->where('type', 'assignment')->whereIn('type_id', $assignment_ids)->delete();
                $columns = DB::select("SHOW COLUMNS FROM courses");

                $auto_release_columns = array_map(function ($column) {
                    return $column->Field;
                }, array_filter($columns, function ($column) {
                    return strpos($column->Field, 'auto_release') === 0;
                }));
                foreach ($assignments as $assignment) {
                    $autoRelease = new AutoRelease();
                    $autoRelease->type = 'assignment';
                    $autoRelease->type_id = $assignment->id;
                    foreach ($auto_release_columns as $column) {
                        $value = $request->{$column};
                        $column = str_replace('auto_release_', '', $column);
                        if ($assignment->late_policy === 'not accepted' && strpos($column, 'after') !== false) {
                            $autoRelease->{$column} = 'due date';
                        } else {
                            $autoRelease->{$column} = $value;
                        }
                    }
                    if ($assignment->assesment_type === 'real time' && $assignment->solutions_availability === 'manual') {
                        $autoRelease->solutions_released = null;
                        $autoRelease->solutions_released_after = null;
                    }

                    $autoRelease->save();
                }
            }
            DB::commit();
            $response['type'] = 'success';
            $message = $apply_to === 'all' ? 'The auto-releases have been updated for all assignments in the course.'
                : 'The auto-releases for new assignments will use these default settings.';
            $response['message'] = $message;
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error setting the default auto-release.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function resyncFromLMS(Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('unlinkFromLMS', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            DB::beginTransaction();

            DB::table('assignments')
                ->where('course_id', $course->id)
                ->update(['lms_resource_link_id' => null, 'lms_assignment_id' => null]);
            $lti_registration = $course->getLtiRegistration();
            $lmsApi = new LmsAPI();
            $result = $lmsApi->getAssignments($lti_registration, $course->user_id, $course->lms_course_id);
            if ($result['type'] === 'error') {
                throw new Exception("Could not get LMS course assignments: {$result['message']} for $lti_registration->id");
            }
            $lms_assignments = [];
            foreach ($result['message'] as $lms_assignment) {
                $lms_assignments[] = ['id' => $lms_assignment->id, 'name' => $lms_assignment->name];

            }
            $adapt_assignments = [];
            foreach ($course->assignments as $assignment) {
                $adapt_assignments[] = ['id' => $assignment->id, 'name' => $assignment->name];
            }
            $resync_results = [];
            foreach ($lms_assignments as $lms_assignment) {
                $adapt_assignment = $this->_lmsAssignmentIsAdaptAssignment($lms_assignment['name'], $adapt_assignments);
                $resynced = false;
                if ($adapt_assignment) {
                    Assignment::where('id', $adapt_assignment['id'])->update(['lms_assignment_id' => $lms_assignment['id']]);
                    $resynced = true;
                }
                $resync_results[] = ['canvas_assignment' => $lms_assignment['name'],
                    'adapt_assignment' => $adapt_assignment['name'] ?? 'N/A',
                    'resynced' => $resynced];
            }

            DB::commit();
            $response['resync_results'] = $resync_results;
            $response['type'] = 'success';

        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error resyncing this course to your LMS.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param string $lms_assignment_name
     * @param array $adapt_assignments
     * @return array
     */
    private function _lmsAssignmentIsAdaptAssignment(string $lms_assignment_name, array $adapt_assignments): array
    {
        foreach ($adapt_assignments as $adapt_assignment) {
            if ($lms_assignment_name === $adapt_assignment['name'] . " (ADAPT)") {
                return $adapt_assignment;
            }
        }
        return [];
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function unlinkFromLMS(Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('unlinkFromLMS', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $course->lms_course_id = null;
            $course->save();
            DB::table('assignments')
                ->where('course_id', $course->id)
                ->update(['lms_resource_link_id' => null, 'lms_assignment_id' => null]);
            $assignment_ids = $course->assignments->pluck('id')->toArray();
            DB::table('lti_assignments_and_grades_urls')
                ->whereIn('assignment_id', $assignment_ids)
                ->delete();
            DB::table('lti_names_and_roles_urls')
                ->where('course_id', $course->id)
                ->delete();
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "Your ADAPT course has been successfully unlinked from your LMS.";
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error linking this course to your LMS.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param LinkToLMSRequest $request
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function linkToLMS(LinkToLMSRequest $request,
                              Course           $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('linkToLMS', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        if (app()->environment('testing')) {
            $data['lms_course_id'] = $request->lms_course_id;
        } else {
            $data = $request->validated();
        }
        $lms_course_id = $data['lms_course_id'];
        $already_linked = $course->where('lms_course_id', $data['lms_course_id'])->first();
        if ($already_linked) {
            $already_linked_user_email = '';
            if ($already_linked->user_id !== $request->user_id) {
                $already_linked_user_email = User::find($already_linked->user_id)->email;
            }
            $response['type'] = 'info';
            $response['message'] = "The LMS course is already linked to your ADAPT course $already_linked->name.";
            if ($already_linked_user_email) {
                $response['message'] .= " Please log into your account with email address $already_linked_user_email and unlink the course.";
            }
            return $response;
        }
        try {
            DB::beginTransaction();
            $lti_registration = $course->getLtiRegistration();
            $lmsApi = new LmsAPI();
            $result = $lmsApi->getAssignments($lti_registration, $course->user_id, $data['lms_course_id']);
            if ($result['type'] === 'error') {
                throw new Exception("Could not get LMS course assignments: {$result['message']} for $lti_registration->id");
            }
            $unlinked_assignments = [];
            if ($result['message']) {
                $unlinked_assignments = $result['message'];
            }
            $course->lms_course_id = $lms_course_id;
            $course->save();
            $lti_names_and_roles_url = LtiNamesAndRolesUrl::where('course_id', $course->id)->first();
            if (!$lti_names_and_roles_url) {
                $lti_names_and_roles_url = new LtiNamesAndRolesUrl();
                $lti_names_and_roles_url->course_id = $course->id;
            }
            $url = "$lti_registration->auth_server/api/lti/courses/$lms_course_id/names_and_roles";
            $lti_names_and_roles_url->url = $url;
            $lti_names_and_roles_url->save();

            $response['type'] = 'success';
            $response['unlinked_assignments'] = $unlinked_assignments;
            $response['message'] = "Your ADAPT course has been successfully linked to your LMS.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error linking this course to your LMS.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function autoUpdateQuestionRevisions(Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('autoUpdateQuestionRevisions', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $auto_update_question_revisions = !$course->auto_update_question_revisions;
            $course->auto_update_question_revisions = $auto_update_question_revisions;
            $course->save();
            if ($course->alpha) {
                $beta_courses = BetaCourse::where('alpha_course_id', $course->id)->get();
                foreach ($beta_courses as $beta_course) {
                    DB::table('courses')->where('id', $beta_course->id)
                        ->update(['auto_update_question_revisions' => $auto_update_question_revisions]);
                }
            }
            $response['type'] = $course->auto_update_question_revisions ? 'success' : 'info';
            $message = $course->auto_update_question_revisions ? 'will' : 'will not';
            $response['message'] = "Question revisions $message be auto-updated.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the auto-update property.  Please try again or contact us for assistance.";
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
    function getNonBetaCoursesAndAssignments(Request $request, Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getNonBetaCoursesAndAssignments', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $beta_courses = DB::table('beta_courses')->get('id')->pluck('id')->toArray();
            $courses_and_assignments = DB::table('courses')->join('assignments', 'courses.id', '=', 'assignments.course_id')
                ->select('courses.name AS course_name', 'courses.id AS course_id', 'assignments.name AS assignment_name', 'assignments.id AS assignment_id')
                ->where('courses.user_id', $request->user()->id)
                ->whereNotIn('courses.id', $beta_courses)
                ->orderBy('course_name')
                ->orderBy('assignments.order')
                ->get();
            $course_ids = [];
            $courses = [];
            $assignments = [];
            foreach ($courses_and_assignments as $value) {
                if (!in_array($value->course_id, $course_ids)) {
                    $courses[] = ['course_id' => $value->course_id, 'course_name' => $value->course_name];
                    $assignments[$value->course_id] = [];
                    $assignments[$value->course_id]['course_id'] = $value->course_id;
                    $assignments[$value->course_id]['assignments'] = [];
                    $course_ids[] = $value->course_id;
                }
                $assignments[$value->course_id]['assignments'][] = ['assignment_id' => $value->assignment_id, 'assignment_name' => $value->assignment_name];
            }
            $response['type'] = 'success';
            $response['courses'] = $courses;
            $response['assignments'] = array_values($assignments);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting your courses and assignments.";
        }
        return $response;

    }

    public
    function getCommonsCoursesAndAssignmentsByCourse(Course $course): array
    {
        $response['type'] = 'error';

        try {
            $commons_user = User::where('email', 'commons@libretexts.org')->first();
            $commons_courses_and_assignments = DB::table('assignments')
                ->join('courses', 'assignments.course_id', '=', 'courses.id')
                ->select('courses.id AS course_id',
                    'courses.name AS course_name',
                    'courses.public_description AS course_description',
                    'courses.anonymous_users AS anonymous_users',
                    'assignments.id AS assignment_id',
                    'assignments.public_description AS assignment_description',
                    'assignments.name AS assignment_name',
                  )
                ->where('courses.user_id', $commons_user->id)
                ->orderBy('course_name')
                ->orderBy('assignment_name')
                ->get();
            $commons_courses_and_assignments_by_course = [];
            $course_ids = [];
            foreach ($commons_courses_and_assignments as $value) {
                if (!in_array($value->course_id, $course_ids)) {
                    $commons_courses_and_assignments_by_course[$value->course_id] = [
                        'course_id' => $value->course_id,
                        'course_description' => $value->course_description,
                        'course_name' => $value->course_name,
                        'anonymous_users' => $value->anonymous_users,
                        'assignments' => [['id' => $value->assignment_id, 'name' => $value->assignment_name, 'description' => $value->assignment_description]]
                    ];
                } else {
                    $commons_courses_and_assignments_by_course[$value->course_id]['assignments'][] = ['id' => $value->assignment_id, 'name' => $value->assignment_name, 'description' => $value->assignment_description];
                }
                $course_ids[] = $value->course_id;
            }
            $commons_courses_and_assignments_by_course = array_values($commons_courses_and_assignments_by_course);
            $response['commons_courses_and_assignments_by_course'] = $commons_courses_and_assignments_by_course;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the Commons courses and assignments.";
        }
        return $response;
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getCommonsCoursesAndAssignments(Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getCommonsCoursesAndAssignments', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $commons_user = User::where('email', 'commons@libretexts.org')->first();
            $commons_courses_and_assignments = DB::table('assignments')
                ->join('courses', 'assignments.course_id', '=', 'courses.id')
                ->select('assignments.id AS assignment_id',
                    'courses.id AS course_id',
                    'assignments.name AS assignment_name',
                    'courses.name AS course_name')
                ->where('courses.user_id', $commons_user->id)
                ->orderBy('course_name')
                ->orderBy('assignment_name')
                ->get();
            $response['commons_courses_and_assignments'] = $commons_courses_and_assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the Commons courses and assignments.";
        }
        return $response;


    }


    /**
     * @param ResetCourse $request
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function reset(ResetCourse $request,
                   Course      $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('reset', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $request->validated();
        try {
            if (app()->environment('testing')) {
                return $course->reset();
            } else {
                ProcessResetCourse::dispatch($course);
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error removing all students from <strong>$course->name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getAllCourses(Course $course): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('getAllCourses', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $response['courses'] = DB::table('courses')
                ->join('users', 'courses.user_id', '=', 'users.id')
                ->select('courses.id AS value', DB::raw('CONCAT(courses.name, " --- " ,first_name, " " , last_name) AS label'))
                ->orderBy('label')
                ->get();

            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not get all courses.';
        }
        return $response;


    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function open(Course $course): array
    {

        $response['type'] = 'error';
        try {

            $response['open_courses'] = $course->where('anonymous_users', 1)->get();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not determine whether you can log into the this course as an anonymous user.';
        }
        return $response;


    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function canLogIntoCourseAsAnonymousUser(Course $course): array
    {


        try {
            $response['can_log_into_course_as_anonymous_user'] = (boolean)$course->anonymous_users;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $response['type'] = 'error';
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not determine whether you can log into the this course as an anonymous user.';
        }
        return $response;


    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function hasH5PQuestions(Course $course)
    {

        $response['type'] = 'error';
        try {
            $h5p_questions_exist = DB::table('assignment_question')
                ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
                ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                ->where('assignments.course_id', $course->id)
                ->where('questions.technology', 'h5p')
                ->get()
                ->isNotEmpty();
            $response['h5p_questions_exist'] = $h5p_questions_exist;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not determine whether this course has H5P questions.';
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
    function canLogInAsAnonymousUser(Request $request, Assignment $assignment): array
    {
        try {
            $response['type'] = 'error';
            $landing_page = $request->session()->get('landing_page');
            if ($landing_page) {
                $landing_page_array = explode('/', $landing_page);
                /*      0 => ""
                        1 => "assignments"
                        2 => "298"
                        3 => "questions"
                        4 => "view"
                        5 => "98505"
                */
                $anonymous_users = false;
                if (count($landing_page_array) === 6
                    && $landing_page_array[1] === 'assignments'
                    && is_numeric($landing_page_array[2])
                    && $landing_page_array[3] === 'questions'
                    && $landing_page_array[4] === 'view'
                    && is_numeric($landing_page_array[5])) {
                    $course = DB::table('assignments')
                        ->join('courses', 'assignments.course_id', '=', 'courses.id')
                        ->where('assignments.id', $landing_page_array[2])
                        ->first();
                    $anonymous_users = (boolean)$course->anonymous_users;
                }
                $response['type'] = 'success';
                $response['anonymous_users'] = $anonymous_users;
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'We could not determine whether this course allowed anonymous users.';
        }
        return $response;

    }

    /**
     * @return array
     * @throws Exception
     */
    public
    function getAnonymousUserCourses(): array
    {
        try {
            $response['enrollments'] = DB::table('courses')
                ->where('courses.anonymous_users', 1)
                ->where('courses.shown', 1)
                ->select('id',
                    'courses.name AS course_section_name',
                    'public_description')
                ->get();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the courses with anonymous users.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function updateIFrameProperties(Request $request, Course $course)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateIFrameProperties', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $item = $request->item;
            if (!in_array($item, ['attribution', 'assignment', 'submission', 'question_numbers'])) {
                $response['message'] = "$item is not a valid iframe property.";
                return $response;
            }
            $action = $request->action;
            if (!in_array($action, ['show', 'hide'])) {
                $response['message'] = "$action isn't a valid action.";
                return $response;
            }
            $value = ($action === 'show') ? 1 : 0;
            $assignments = DB::table('assignments')->where('course_id', $course->id)->get('id');
            $message = "This course has no assignments.";
            $action_message = ($action === 'show') ? 'shown' : 'hidden';
            $type = "info";
            if ($item === 'question_numbers') {
                DB::table('courses')
                    ->where('id', $course->id)
                    ->update(['question_numbers_shown_in_iframe' => !$course->question_numbers_shown_in_iframe]);
                $type = ($action === 'show') ? 'success' : 'info';
                $message = "The question numbers will now be $action_message when embedded in an iframe.";
            } else {
                if ($assignments) {
                    $assignment_ids = $assignments->pluck('id');
                    DB::table('assignment_question')
                        ->whereIn('assignment_id', $assignment_ids)
                        ->update(["{$item}_information_shown_in_iframe" => $value]);
                    $type = ($action === 'show') ? 'success' : 'info';
                    $message = "The $item information will now be $action_message when embedded in an iframe.";
                }
            }
            $response['message'] = $message;
            $response['type'] = $type;
            return $response;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to update the iframe properties for your course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @return array
     * @throws Exception
     */
    public
    function getOpenCourses(): array
    {
        $response['type'] = 'error';
        try {
            $commons_user = User::where('email', 'commons@libretexts.org')->first();
            $open_courses = DB::table('courses')
                ->where('courses.user_id', $commons_user->id)
                ->where('shown', 1)
                ->where('anonymous_users', 1)
                ->select('id',
                    'courses.name AS name',
                    'courses.public_description AS description',
                    DB::raw('CONCAT("' . config('app.url') . '/courses/",id,"/anonymous") AS url'))
                ->get();
            $response['open_courses'] = $open_courses;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to see get the courses from the Commons.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @return array
     * @throws Exception
     */
    public
    function getCommonsCourses(): array
    {
        $response['type'] = 'error';
        try {
            $commons_user = User::where('email', 'commons@libretexts.org')->first();
            $commons_courses = DB::table('courses')
                ->join('users', 'courses.user_id', '=', 'users.id')
                ->leftJoin('disciplines', 'courses.discipline_id', '=', 'disciplines.id')
                ->join('schools', 'courses.school_id', '=', 'schools.id')
                ->where('courses.user_id', $commons_user->id)
                ->where('shown', 1)
                ->select(
                    'courses.id',
                    'courses.name AS name',
                    'courses.formative',
                    'disciplines.name AS discipline_name',
                    'disciplines.id AS discipline_id',
                    'courses.public_description AS description',
                    'schools.name AS school',
                    DB::raw("CONCAT(users.first_name, ' ',users.last_name) AS instructor"),
                    'alpha',
                    'anonymous_users',
                    DB::raw("EXISTS (
            SELECT 1
            FROM assignments
            WHERE assignments.course_id = courses.id
              AND assignments.formative = 1
        ) AS has_at_least_one_formative_assignment")
                )
                ->get();
            $response['commons_courses'] = $commons_courses;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to see get the courses from the Commons.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public
    function updateBetaApprovalNotifications(Course $course)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('updateBetaApprovalNotifications', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $course->beta_approval_notifications = !$course->beta_approval_notifications;
            $course->save();
            $message_text = $course->beta_approval_notifications ? "now" : "no longer";
            $response['type'] = 'info';
            $response['message'] = "You will $message_text receive daily email notifications of pending approvals.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to see whether this course is an Alpha course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function getBetaApprovalNotifications(Course $course)
    {

        $response['type'] = 'error';
        try {
            $response['beta_approval_notifications'] = $course->beta_approval_notifications;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to see whether this course is an Alpha course.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Course $course
     * @param AutoRelease $autoRelease
     * @return array
     * @throws Exception
     */
    public
    function getWarnings(Course $course, AutoRelease $autoRelease): array
    {
        try {
            $assignment_ids = $course->assignments->pluck('id')->toArray();
            $has_auto_releases = $autoRelease->where('type', 'assignment')->whereIn('type_id', $assignment_ids)->count() > 0;
            $response['alpha'] = $course->alpha;
            $response['formative'] = $course->formative;
            $response['has_auto_releases'] = $has_auto_releases;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get the course warnings.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function isAlpha(Course $course)
    {
        try {
            $response['alpha'] = $course->alpha;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to see whether this course is an Alpha course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param School $school
     * @return array
     * @throws Exception
     */
    public
    function getLastSchool(Request $request, School $school): array
    {

        $response['type'] = 'error';
        try {
            $last_school_info = $school->getLastSchool($request->user());
            $response['last_school_name'] = $last_school_info['school_name'];
            $response['last_school_id'] = $last_school_info['school_id'];
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get your last school.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Course $course
     * @param User|null $instructor
     * @return array
     * @throws Exception
     */
    public
    function getPublicCourses(Course $course, User $instructor = null): array
    {

        $response['type'] = 'error';
        try {
            $public_courses = [];
            switch ($instructor) {
                case(true):
                    $public_courses = $course->where('public', 1)
                        ->where('user_id', $instructor->id)
                        ->select(
                            'id',
                            'name',
                            'formative',
                            DB::raw("EXISTS (
            SELECT 1
            FROM assignments
            WHERE assignments.course_id = courses.id
              AND assignments.formative = 1
        ) AS has_at_least_one_formative_assignment")
                        )
                        ->orderBy('name')
                        ->get();
                    break;
                case(false):
                    $public_courses = $course->publicCourses();
                    break;

            }
            $response['public_courses'] = $public_courses;
            $response['type'] = 'success';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get the public courses.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public
    function getCoursesAndNonBetaAssignments(Request $request)
    {

        $response['type'] = 'error';
        $courses = [];
        $assignments = [];
        try {
            $results = DB::table('courses')
                ->join('assignments', 'courses.id', '=', 'assignments.course_id')
                ->leftJoin('beta_assignments', 'assignments.id', '=', 'beta_assignments.id')
                ->where('courses.user_id', $request->user()->id)
                ->select(DB::raw('courses.id AS course_id'),
                    DB::raw('courses.name AS course_name'),
                    'courses.start_date',
                    'courses.end_date',
                    'beta_assignments.id AS beta_assignment_id',
                    DB::raw('assignments.id AS assignment_id'),
                    DB::raw('assignments.name AS assignment_name'))
                ->orderBy('courses.start_date', 'desc')
                ->get();
            $course_ids = [];
            foreach ($results as $value) {
                $course_id = $value->course_id;
                if (!in_array($course_id, $course_ids)) {
                    $courses[] = ['value' => $course_id,
                        'text' => $value->course_name,
                        'start_date' => $value->start_date,
                        'end_date' => $value->end_date];
                    $course_ids[] = $course_id;
                }
                if (!$value->beta_assignment_id) {
                    $assignments[$course_id][] = ['value' => $value->assignment_id,
                        'text' => $value->assignment_name];
                }
            }

            $response['type'] = 'success';
            $response['courses'] = $courses;
            $response['assignments'] = $assignments;
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get your courses and assignments.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Enrollment $enrollment
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function getEnrolledInCoursesAndAssignments(Enrollment $enrollment, Course $course): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $enrollment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $enrollments = $enrollment->index();
        $enrolled_in_courses_and_assignments = [];
        foreach ($enrollments as $key => $enrollment) {
            $enrolled_in_courses_and_assignments[$key] = [];
            $enrolled_in_courses_and_assignments[$key]['course'] = $enrollment;
            $course = Course::find($enrollment->id);
            $enrolled_in_courses_and_assignments[$key]['assignments'] = $course->assignedToAssignmentsByUser();
        }

        try {
            $response['enrolled_in_courses_and_assignments'] = $enrolled_in_courses_and_assignments;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting your enrolled courses and assignments.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function getCoursesAndAssignments(Request $request): array
    {

        $response['type'] = 'error';
        $courses = [];
        $assignments = [];
        try {
            $results = DB::table('courses')
                ->join('assignments', 'courses.id', '=', 'assignments.course_id')
                ->where(function ($query) use ($request) {
                    $query->where('courses.user_id', $request->user()->id)
                        ->orWhereExists(function ($subquery) use ($request) {
                            $subquery->select(DB::raw(1))
                                ->from('co_instructors')
                                ->whereColumn('co_instructors.course_id', 'courses.id')
                                ->where('co_instructors.user_id', $request->user()->id)
                                ->where('co_instructors.status', 'accepted');
                        });
                })
                ->select(
                    DB::raw('courses.id AS course_id'),
                    DB::raw('courses.name AS course_name'),
                    'courses.lms',
                    'assignments.lms_resource_link_id',
                    'assignments.assessment_type',
                    DB::raw('assignments.id AS assignment_id'),
                    DB::raw('assignments.name AS assignment_name')
                )
                ->orderBy('courses.start_date', 'desc')
                ->get();

            $course_ids = [];
            foreach ($results as $value) {
                $course_id = $value->course_id;
                if (!in_array($course_id, $course_ids)) {
                    $courses[] = ['value' => $course_id,
                        'text' => $value->course_name,
                        'lms' => $value->lms];
                    $course_ids[] = $course_id;
                }
                $assignments[$course_id][] = ['value' => $value->assignment_id,
                    'text' => $value->assignment_name,
                    'lms_resource_link_id' => $value->lms_resource_link_id,
                    'assessment_type' => $value->assessment_type];
            }
            $courses_with_api_keys = DB::table('lti_registrations')
                ->join('lti_schools', 'lti_registrations.id', '=', 'lti_schools.lti_registration_id')
                ->join('courses', 'lti_schools.school_id', '=', 'courses.school_id')
                ->whereIn('courses.id', $course_ids)
                ->whereNotNull('lti_registrations.api_key')
                ->select('courses.id')
                ->get();
            $course_ids_with_api_key = [];
            foreach ($courses_with_api_keys as $course_with_api_key) {
                $course_ids_with_api_key[] = $course_with_api_key->id;
            }
            foreach ($courses as $key => $course) {
                $courses[$key]['has_api_key'] = in_array($course['value'], $course_ids_with_api_key);
            }
            $response['type'] = 'success';
            $response['courses'] = $courses;
            $response['assignments'] = $assignments;
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to get your courses and assignments.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param ImportCourseRequest $request
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function import(ImportCourseRequest $request,
                    Course              $course): array
    {

        $response['type'] = 'error';

        $authorized = Gate::inspect('import', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        if (!in_array($request->action, ['import', 'clone'])) {
            $response['message'] = "$request->action should either be to import or clone.";
            return $response;
        }
        $import_as_beta = (int)$request->import_as_beta;

        if ($import_as_beta && !$course->alpha) {
            $response['message'] = "You cannot $request->action this course as a Beta course since the original course is not an Alpha course.";
            return $response;
        }
        try {
            app()->environment() === 'testing'
                ? $course->import($request->user(), $request->all())
                : ProcessImportCourse::dispatch($course, $request->user(), $request->all());

            $response['type'] = 'info';
            $response['message'] = "Processing...please be patient.";

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating the $request->action.  Please try again or contact us for assistance.";
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
    function getImportable(Request $request, Course $course)
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('getImportable', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $instructor_courses = DB::table('courses')
                ->join('users', 'courses.user_id', '=', 'users.id')
                ->where('user_id', $request->user()->id)
                ->select('name',
                    DB::raw('CONCAT(first_name, " " , last_name) AS instructor'),
                    'courses.id',
                    'term')
                ->get();
            $public_courses = $course->publicCourses();

            $importable_courses = [];
            foreach ($instructor_courses as $course) {
                $importable_courses[] = $course;
            }
            foreach ($public_courses as $course) {
                $importable_courses[] = $course;
            }
            $formatted_importable_courses = [];
            $formatted_course_ids = [];
            $formatted_course_names = [];
            foreach ($importable_courses as $course) {
                $term = $course->term === 'N/A' ? '' : "($course->term)";
                if (!in_array($course->id, $formatted_course_ids)) {
                    $formatted_course = "$course->name $term --- $course->instructor";
                    if (in_array($formatted_course, $formatted_course_names)) {
                        $formatted_course = "$course->name $term --- $course->instructor";
                    }
                    $formatted_importable_courses[] = [
                        'course_id' => $course->id,
                        'formatted_course' => $formatted_course
                    ];
                    $formatted_course_ids[] = $course->id;
                    $formatted_course_names[] = $formatted_course;
                }

            }

            usort($formatted_importable_courses, function ($item1, $item2) {
                return $item1['formatted_course'] <=> $item2['formatted_course'];
            });

            $response['type'] = 'success';
            $response['importable_courses'] = $formatted_importable_courses;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving the importable courses.  Please try again or contact us for assistance.";
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
    function index(Request $request, Course $course)
    {

        $response['type'] = 'error';


        if ($request->session()->get('completed_sso_registration')) {
            \Log::info('Just finished registration.');
        }
        $authorized = Gate::inspect('viewAny', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['courses'] = $course->getCourses(auth()->user());
            $response['show_beta_course_dates_warning'] = !$request->hasCookie('show_beta_course_dates_warning');

            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving your courses.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Course $course
     * @param AssignmentGroupWeight $assignmentGroupWeight
     * @return array
     * @throws Exception
     */
    public
    function updateShowZScores(Request $request, Course $course, AssignmentGroupWeight $assignmentGroupWeight): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateShowZScores', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $response = $assignmentGroupWeight->validateCourseWeights($course);
        if ($response['type'] === 'error') {
            return $response;
        }
        try {

            $course->show_z_scores = !$request->show_z_scores;
            $course->save();

            $verb = $course->show_z_scores ? "can" : "cannot";
            $response['type'] = $course->show_z_scores ? 'success' : 'info';
            $response['message'] = "Students <strong>$verb</strong> view their z-scores.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the ability for students to view their z-scores.  Please try again or contact us for assistance.";
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
    function updateShowProgressReport(Request $request, Course $course): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateShowProgressReport', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {

            $course->show_progress_report = !$request->show_progress_report;
            $course->save();

            $verb = $course->show_progress_report ? "can" : "cannot";
            $response['type'] = $course->show_progress_report ? 'success' : 'info';
            $response['message'] = "Students <strong>$verb</strong> view their progress reports.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the ability for students to view their progress_reports.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public
    function showOpen(Course $course)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('viewOpen', $course);
        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $response['open_course'] = [
                'id' => $course->id,
                'name' => $course->name,
                'alpha' => $course->alpha];
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving this open course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function updateStudentsCanViewWeightedAverage(Request $request, Course $course, AssignmentGroupWeight $assignmentGroupWeight)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateStudentsCanViewWeightedAverage', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response = $assignmentGroupWeight->validateCourseWeights($course);
            if ($response['type'] === 'error') {
                return $response;
            }
            $course->students_can_view_weighted_average = !$request->students_can_view_weighted_average;
            $course->save();

            $verb = $course->students_can_view_weighted_average ? "can" : "cannot";
            $response['type'] = $course->students_can_view_weighted_average ? 'success' : 'info';
            $response['message'] = "Students <strong>$verb</strong> view their weighted averages.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the ability for students to view their weighted averages.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Course $course
     * @param WhitelistedDomain $whitelistedDomain
     * @param LTIDatabase $lti_database
     * @return array
     * @throws Exception
     */
    public
    function show(Course            $course,
                  WhitelistedDomain $whitelistedDomain,
                  LTIDatabase       $lti_database): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $course);
        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $question_exists_not_owned_by_the_instructor = DB::table('assignment_question')
                ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                ->whereIn('assignment_id', $course->assignments()->pluck('id')->toArray())
                ->where('question_editor_user_id', '<>', $course->user_id)
                ->first();

            $lms_courses = [];
            $lms_error = '';
            $course->lms_has_api_key = false;
            $course->lms_has_access_token = false;
            $course->non_active_users = [];
            $updated_canvas_api = ['points' => false, 'everybodys' => false];
            if (request()->user()->role === 2 && $course->lms) {
                $lti_names_and_roles_url = LtiNamesAndRolesUrl::where('course_id', $course->id)->first();
                /* The following code will be used to determine if anyone has dropped the course or not
                if ($lti_names_and_roles_url) {
                     $lti_registration = $course->getLtiRegistration();

                     $service_connector = new LTI_Service_Connector($lti_database->find_registration_by_client_id($lti_registration->client_id));
                     $names_and_roles = new LTI_Names_Roles_Provisioning_Service($service_connector,
                         ['context_memberships_url' => $lti_names_and_roles_url->url]);
                     $members = $names_and_roles->get_members();
                     $course_enrollments = DB::table('courses')
                         ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
                         ->join('users', 'enrollments.user_id', '=', 'users.id')
                         ->where('courses.id', $course->id)
                         ->where('fake_student', 0)
                         ->select('users.*')
                         ->get();
                     foreach ($course_enrollments as $course_enrollment) {
                         if ($course_enrollment->lms_user_id) {
                             $course_enrollments_by_lms_user_id[$course_enrollment->lms_user_id] = $course_enrollment;

                         }
                         foreach ($members as $member) {
                             //not in LMS but in the ADAPT course
                             if (in_array($member['status'], ['Inactive', 'Deleted'])
                                 && isset($course_enrollments_by_lms_user_id[$member->user_id])) {
                                 $course->non_active_users[] = $member;
                             }
                         }
                     }
                 }  */
                $school = School::find($course->school_id);
                $course->lms_has_access_token = DB::table('lms_access_tokens')
                    ->where('user_id', request()->user()->id)
                    ->where('school_id', $course->school_id)
                    ->first();

                $course->lms_has_access_token = $course->lms_has_access_token !== null;
                $lti_registration = $school->LTIRegistration();
                $course->is_brightspace = $lti_registration && LtiRegistration::isBrightSpace($lti_registration->iss);
                $course->is_canvas = $lti_registration && strpos($lti_registration->iss, 'instructure') !== false;
                $course->lms_has_api_key = $lti_registration && $lti_registration->api_key;

                if ((!app()->environment('local') || $lti_registration->auth_server === 'http://canvas.docker') && $course->lms_has_api_key) {
                    if ($course->lms_has_access_token) {
                        $canvas_updates = DB::table('canvas_updates')->where('course_id', $course->id)->first();
                        if ($canvas_updates) {
                            $updated_canvas_api['points'] = $canvas_updates->updated_points;
                            $updated_canvas_api['everybodys'] = $canvas_updates->updated_everybodys;


                        }
                        $linked_lms_courses = Course::where('user_id', $course->user_id)
                            ->whereNotNull('lms_course_id')
                            ->get();
                        $linked_lms_course_ids = [];
                        if ($linked_lms_courses) {
                            foreach ($linked_lms_courses as $linked_lms_course) {
                                $linked_lms_course_ids[] = $linked_lms_course->lms_course_id;
                            }
                        }
                        if (!$course->lms_course_id) {
                            $lmsApi = new LmsAPI();
                            $lms_courses = $lmsApi->getCourses($lti_registration, $course->user_id);
                            if ($lms_courses['type'] === 'error') {
                                $lms_error = $lms_courses['message'];
                            } else {
                                $all_lms_courses = $lms_courses['courses'];
                                $lms_courses = [];
                                foreach ($all_lms_courses as $lms_course) {
                                    if (!in_array($lms_course->id, $linked_lms_course_ids)) {
                                        $lms_courses[] = $lms_course;
                                    }
                                }
                            }
                        }
                    }
                }
            }


            $response['course'] = [
                'id' => $course->id,
                'school' => $course->school->name,
                'discipline' => $course->discipline_id,
                'name' => $course->name,
                'co_instructors' => $course->coInstructors(),
                'is_brightspace' => $course->is_brightspace,
                'is_canvas' => $course->is_canvas,
                'public_description' => $course->public_description,
                'private_description' => $course->private_description,
                'textbook_url' => $course->textbook_url,
                'term' => $course->term,
                'students_can_view_weighted_average' => $course->students_can_view_weighted_average,
                'letter_grades_released' => $course->finalGrades->letter_grades_released,
                'sections' => $course->sections,
                'show_z_scores' => $course->show_z_scores,
                'show_z_scores' => $course->show_z_scores,
                'graders' => $course->graderInfo(),
                'start_date' => $course->start_date,
                'end_date' => $course->end_date,
                'formatted_end_date' => Carbon::parse($course->end_date, 'UTC')
                    ->setTimezone(request()->user()->time_zone)
                    ->translatedFormat('F j, Y'),
                'public' => $course->public,
                'auto_release_shown' => $course->auto_release_shown,
                'auto_release_show_scores' => $course->auto_release_show_scores,
                'auto_release_show_scores_after' => $course->auto_release_show_scores_after,
                'auto_release_solutions_released' => $course->auto_release_solutions_released,
                'auto_release_solutions_released_after' => $course->auto_release_solutions_released_after,
                'auto_release_students_can_view_assignment_statistics' => $course->auto_release_students_can_view_assignment_statistics,
                'auto_release_students_can_view_assignment_statistics_after' => $course->auto_release_students_can_view_assignment_statistics_after,
                'enrolled_users' => $course->realStudentsWhoCanSubmit()->isNotEmpty(),
                'auto_update_question_revisions' => $course->auto_update_question_revisions,
                'lms' => $course->lms,
                'lms_outage' => LmsOutageCourse::where('course_id', $course->id)->whereNull('turned_on_at')->exists(),
                'lms_error' => $lms_error,
                'lms_course_id' => $course->lms_course_id,
                'lms_only_entry' => $course->lms_only_entry,
                'adapt_enrollment_notification_date' => $course->adapt_enrollment_notification_date,
                'lms_has_api_key' => $course->lms_has_api_key,
                'lms_has_access_token' => $course->lms_has_access_token,
                'lms_courses' => $lms_courses,
                'question_numbers_shown_in_iframe' => (bool)$course->question_numbers_shown_in_iframe,
                'show_progress_report' => $course->show_progress_report,
                'owns_all_questions' => !$question_exists_not_owned_by_the_instructor,
                'alpha' => $course->alpha,
                'anonymous_users' => $course->anonymous_users,
                'formative' => $course->formative,
                'contact_grader_override' => $course->contactGraderOverride(),
                'is_beta_course' => $course->isBetaCourse(),
                'beta_courses_info' => $course->betaCoursesInfo(),
                'updated_canvas_api' => $updated_canvas_api,
                'whitelisted_domains' => $whitelistedDomain
                    ->where('course_id', $course->id)
                    ->select('whitelisted_domain')
                    ->pluck('whitelisted_domain')
                    ->toArray()];
            if (!app()->environment('local') && $course->lms_course_id) {
                $lmsApi = new LmsAPI();
                $lms_result = $lmsApi->getCourse($course->getLtiRegistration(),
                    $course->user_id, $course->lms_course_id);

                if ($lms_result['type'] === 'error') {
                    $response['course']['lms_error'] = $lms_result['message'];
                } else {
                    $response['course']['lms_course_name'] = $lms_result['message']->name;
                    $response['course']['lms_course_url'] = $lmsApi->getCourseUrl($course->getLtiRegistration()->iss, $course->lms_course_id);
                }
            }
            $response['type'] = 'success';

        } catch
        (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving your course.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Course $course
     * @param int $shown
     * @return array
     * @throws Exception
     */
    public
    function showCourse(Course $course, int $shown): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('showCourse', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $course->shown = !$shown;
            $course->save();

            $response['type'] = !$shown ? 'success' : 'info';
            $shown_message = !$shown ? 'can' : 'cannot';
            $is_commons_user = Auth::user()->email === 'commons@libretexts.org';
            $access_code_message = !$shown || $is_commons_user ? '' : '  In addition, all course access codes have been revoked.';
            $people = $is_commons_user ? "Visitors to the Commons " : "Your students";
            $response['message'] = "$people <strong>{$shown_message}</strong> view this course.{$access_code_message}";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error showing <strong>{$course->name}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param StoreCourse $request
     * @param Course $course
     * @param Enrollment $enrollment
     * @param FinalGrade $finalGrade
     * @param Section $section
     * @param School $school
     * @param CourseOrder $courseOrder
     * @return array
     * @throws Exception
     */

    public
    function store(StoreCourse $request,
                   Course      $course,
                   Enrollment  $enrollment,
                   FinalGrade  $finalGrade,
                   Section     $section,
                   School      $school,
                   CourseOrder $courseOrder): array
    {
        //todo: check the validation rules
        $response['type'] = 'error';
        $authorized = Gate::inspect('create', $course);

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }
        $is_instructor = $request->user()->role === 2;
        $whitelisted_domains = [];
        try {
            $data = $request->validated();
            DB::beginTransaction();
            if (!$is_instructor) {
                $data['start_date'] = date("Y-m-d");
                $datetime = new DateTime('+3 months');
                $data['end_date'] = $datetime->format("Y-m-d");
                $data['crn'] = 'N/A';
                $data['section'] = 'N/A';
                $data['term'] = 'N/A';
                $data['alpha'] = 0;
                $data['anonymous_users'] = 0;
            }
            $data['user_id'] = $request->user()->id;
            $data['school_id'] = $is_instructor ? $this->getSchoolIdFromRequest($request, $school) : 1;
            $formative = isset($data['formative']) && $data['formative'];
            if ($formative) {
                $data['start_date'] = $data['end_date'] = date('Y-m-d', time());
                $data['lms'] = 0;
                $data['alpha'] = 0;
            } else {
                if ($is_instructor) {
                    $whitelisted_domains = $request->whitelisted_domains;
                    unset($data['whitelisted_domains']);
                }
            }
            $data['start_date'] = $this->convertLocalMysqlFormattedDateToUTC($data['start_date'] . '00:00:00', auth()->user()->time_zone);
            $data['end_date'] = $this->convertLocalMysqlFormattedDateToUTC($data['end_date'] . '00:00:00', auth()->user()->time_zone);

            $data['adapt_enrollment_notification_date'] = $this->_adaptEnrollmentNotificationDate($request, $data);
            $data['shown'] = 0;
            $data['public_description'] = $request->public_description;
            $data['private_description'] = $request->private_description;
            $data['order'] = 0;
            //create the main section
            $section->name = $formative ? 'Default' : $data['section'];
            $section->crn = $formative ? '' : $data['crn'];
            unset($data['section']);
            unset($data['crn']);
            unset($data['school']);

            //create the course

            if ($request->discipline > 0) {
                $data['discipline_id'] = $request->discipline;
            }
            $new_course = $course->create($data);
            $courseOrder = new CourseOrder();
            $courseOrder->course_id = $new_course->id;
            $courseOrder->user_id = $request->user()->id;
            $courseOrder->order = 0;
            $courseOrder->save();
            $section->course_id = $new_course->id;
            $section->save();
            if ($is_instructor && !$formative) {
                foreach ($whitelisted_domains as $whitelisted_domain) {
                    $whiteListedDomain = new WhitelistedDomain();
                    $whiteListedDomain->course_id = $new_course->id;
                    $whiteListedDomain->whitelisted_domain = $whitelisted_domain;
                    $whiteListedDomain->save();
                }
            }

            $course->enrollFakeStudent($new_course->id, $section->id, $enrollment);
            $finalGrade->setDefaultLetterGrades($new_course->id);

            $courseOrder->reOrderAllCourses(Auth::user());
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The course <strong>$request->name</strong> has been created.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating <strong>$request->name</strong>.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    public
    function getSchoolIdFromRequest(Request $request, School $school)
    {

        return $request->school
            ? $school->where('name', $request->school)->first()->id
            : $school->first()->id;
    }

    /**
     *
     * Update the specified resource in storage.
     *
     *
     * @param StoreCourse $request
     * @param Course $course
     * @param School $school
     * @param BetaCourse $betaCourse
     * @return array
     * @throws Exception
     */
    public
    function update(StoreCourse $request,
                    Course      $course,
                    School      $school,
                    BetaCourse  $betaCourse): array
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('update', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $is_beta_course = $betaCourse->where('id', $course->id)->first();
        if ($message = $this->failsTetherCourseValidation($request, $course, $is_beta_course)) {
            $response['message'] = $message;
            return $response;
        }
        try {
            $data = $request->validated();
            DB::beginTransaction();
            $data['public_description'] = $request->public_description;
            $data['private_description'] = $request->private_description;
            if ($request->discipline > 0) {
                $data['discipline_id'] = $request->discipline;
            }
            if ($request->user()->role === 2) {
                $data['adapt_enrollment_notification_date'] = $this->_adaptEnrollmentNotificationDate($request, $data);
                $lms_grade_passback = $data['lms'] ? 'automatic' : null;
                Assignment::where('course_id', $course->id)->update(['lms_grade_passback' => $lms_grade_passback]);
                $data['school_id'] = $this->getSchoolIdFromRequest($request, $school);
                if (!$request->formative) {
                    $data['start_date'] = $this->convertLocalMysqlFormattedDateToUTC($data['start_date'], $request->user()->time_zone);
                    $data['end_date'] = $this->convertLocalMysqlFormattedDateToUTC($data['end_date'], $request->user()->time_zone);
                    $whitelisted_domains = $request->whitelisted_domains ?? [];
                    unset($data['whitelisted_domains']);
                    DB::table('whitelisted_domains')->where('course_id', $course->id)->delete();
                    foreach ($whitelisted_domains as $whitelisted_domain) {
                        $whitelistedDomain = new WhitelistedDomain();
                        $whitelistedDomain->course_id = $course->id;
                        $whitelistedDomain->whitelisted_domain = $whitelisted_domain;
                        $whitelistedDomain->save();
                    }
                }
                $data['textbook_url'] = $request->textbook_url;
                if ($is_beta_course && $request->untether_beta_course) {
                    $betaCourse->untether($course);
                }
                unset($data['school']);
            }
            $course->update($data);
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The course <strong>$course->name</strong> has been updated.";
            $response['is_beta_course'] = $betaCourse->where('id', $course->id)->first();
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating <strong>$course->name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param $request
     * @param $course
     * @param $is_beta_course
     * @return string
     */
    public
    function failsTetherCourseValidation($request, $course, $is_beta_course): string
    {
        $message = '';
        $at_least_one_beta_course_exists = BetaCourse::where('alpha_course_id', $course->id)->first();
        if ($course->alpha && (int)$request->alpha === 0 && $at_least_one_beta_course_exists) {
            $message = "You are trying to change an Alpha course into a non-Alpha course but Beta courses are currently tethered to this course.";
        }
        if ((int)$request->alpha === 1 && $is_beta_course) {
            $message = "You can't change a Beta course into an Alpha course.";
        }
        return $message;
    }


    /**
     * @param DestroyCourse $request
     * @param Course $course
     * @param BetaCourse $betaCourse
     * @param Discussion $discussion
     * @return array
     * @throws Exception
     */
    public
    function destroy(DestroyCourse $request,
                     Course        $course,
                     BetaCourse    $betaCourse,
                     Discussion    $discussion): array

    {


        $response['type'] = 'error';

        $authorized = Gate::inspect('delete', $course);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $request->validated();
        if (BetaCourse::where('alpha_course_id', $course->id)->first()) {
            $response['message'] = "You cannot delete an Alpha course with tethered Beta courses.";
            return $response;
        }

        try {
            DB::beginTransaction();
            $assignment_ids = $course->assignments->pluck('id')->toArray();
            foreach ($assignment_ids as $assignment_id) {
                $folderPath = "submitted-work/$assignment_id";
                $files = Storage::disk('s3')->files($folderPath);
                Storage::disk('s3')->delete($files);
            }
            $assignment_question_ids = DB::table('assignment_question')
                ->whereIn('assignment_id', $assignment_ids)
                ->get()
                ->pluck('id');

            DB::table('learning_tree_node_submissions')->whereIn('assignment_id', $assignment_ids)->delete();
            DB::table('assignment_question_learning_tree')
                ->whereIn('assignment_question_id', $assignment_question_ids)
                ->delete();

            DB::table('assignment_question_forge_draft')
                ->whereIn('assignment_question_id', $assignment_question_ids)
                ->delete();


            $tables = ['assignment_question',
                'submissions',
                'submission_files',
                'scores',
                'can_give_ups',
                'cutups',
                'seeds',
                'case_study_notes',
                'randomized_assignment_questions',
                'assignment_grader_access',
                'question_level_overrides',
                'compiled_pdf_overrides',
                'assignment_level_overrides',
                'learning_tree_time_lefts',
                'learning_tree_successful_branches',
                'learning_tree_resets',
                'learning_tree_node_seeds',
                'lti_launches',
                'lti_grade_passbacks',
                'remediation_submissions',
                'assignment_question_time_on_tasks',
                'shown_hints',
                'review_histories',
                'shown_hints',
                'unconfirmed_submissions',
                'submission_histories',
                'submission_confirmations',
                'pending_question_revisions',
                'release_assignment_contacted_instructors',
                'submission_score_overrides',
                'rubric_points_breakdowns',
                'maximum_number_of_allowed_attempts_notifications',
                'submitted_works',
                'submitted_work_pending_scores',
                'mastery_assignment_attempts',
                'h5p_activity_sets'];
            foreach ($tables as $table) {
                DB::table($table)->whereIn('assignment_id', $assignment_ids)->delete();
            }
            DB::table('forge_assignment_questions')
                ->whereIn('adapt_assignment_id', $assignment_ids)
                ->delete();

            $assign_to_timing_ids = AssignToTiming::whereIn('assignment_id', $assignment_ids)->get()->pluck('id')->toArray();

            DB::table('assign_to_groups')
                ->whereIn('assign_to_timing_id', $assign_to_timing_ids)
                ->delete();
            DB::table('assign_to_users')
                ->whereIn('assign_to_timing_id', $assign_to_timing_ids)
                ->delete();
            DB::table('assign_to_timings')
                ->whereIn('id', $assign_to_timing_ids)
                ->delete();

            DB::table('beta_assignments')
                ->whereIn('id', $assignment_ids)
                ->delete();
            DB::table('beta_course_approvals')
                ->whereIn('beta_assignment_id', $assignment_ids)
                ->delete();
            foreach ($course->assignments as $assignment) {
                DeleteAssignmentDirectoryFromS3::dispatch($assignment->id);//queue this?
                $discussion->deleteByAssignment($assignment);
            }
            DB::table('co_instructors')
                ->where('course_id', $course->id)
                ->delete();
            DB::table('course_orders')
                ->where('course_id', $course->id)
                ->delete();
            DB::table('pending_course_invitations')
                ->where('course_id', $course->id)
                ->delete();
            DB::table('grader_notifications')
                ->where('course_id', $course->id)
                ->delete();

            DB::table('lti_names_and_roles')
                ->where('course_id', $course->id)
                ->delete();

            DB::table('lti_names_and_roles_urls')
                ->where('course_id', $course->id)
                ->delete();


            $course->extensions()->delete();

            $course->assignments()->delete();


            AssignmentGroupWeight::where('course_id', $course->id)->delete();
            AssignmentGroup::where('course_id', $course->id)->where('user_id', Auth::user()->id)->delete();//get rid of the custom assignment groups
            $course->enrollments()->delete();
            $course->finalGrades()->delete();
            $betaCourse->where('id', $course->id)->delete();
            DB::table('analytics_dashboards')->where('course_id', $course->id)->delete();

            DB::table('contact_grader_overrides')->where('course_id', $course->id)->delete();
            DB::table('whitelisted_domains')->where('course_id', $course->id)->delete();
            foreach ($course->sections as $section) {
                DB::table('grader_access_codes')->where('section_id', $section->id)->delete();
                $section->graders()->delete();
                $section->delete();
            }
            $course->delete();
            DB::commit();
            Cache::forget("unlinked_assignments_by_course_$course->id");
            $response['type'] = 'info';
            $response['message'] = "The course <strong>$course->name</strong> has been deleted.";
        } catch
        (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error removing <strong>$course->name</strong>.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * @param Course $course
     * @param string $operator_text
     * @param int $num_days
     * @return array
     * @throws Exception
     */
    public
    function getCoursesToReset(Course $course, string $operator_text, int $num_days): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getConcludedCourses', $course);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['type'] = 'success';
            $response['courses_to_reset'] = $course->concludedCourses($operator_text, $num_days);

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the hundred day courses.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param LmsAPI $lmsAPI
     * @param Course $course
     * @return void
     * @throws Exception
     */
    public
    function updateLMSAssignmentDates(LmsAPI $lmsAPI, Course $course)
    {
        foreach ($course->assignments as $assignment) {
            try {
                $lms_result = $lmsAPI->updateAssignment(
                    $course->getLtiRegistration(),
                    $course->user_id,
                    $course->lms_course_id,
                    $assignment->lms_assignment_id,
                    $assignment->getIsoUnlockAtDueAt([]));
                if ($lms_result['type'] === 'error') {
                    throw new Exception("Error updating assignment $assignment->id on  LMS: " . $lms_result['message']);
                }
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
            }
        }
    }

    /**
     * @param $request
     * @param $data
     * @return string|null
     */
    private function _adaptEnrollmentNotificationDate($request, $data): ?string
    {
        return $request->lms && +$request->lms_only_entry === 0 && isset($data['adapt_enrollment_notification_date'])
            ? $this->convertLocalMysqlFormattedDateToUTC($data['adapt_enrollment_notification_date'], auth()->user()->time_zone)
            :
            null;
    }

}
