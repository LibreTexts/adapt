<?php

namespace App;

use App\Exceptions\Handler;
use App\Jobs\DeleteAssignmentDirectoryFromS3;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class Course extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @return Collection
     */
    public function coInstructors(): Collection
    {
        return DB::table('co_instructors')
            ->join('users', 'co_instructors.user_id', '=', 'users.id')
            ->select('users.id', 'users.email', DB::raw('CONCAT(first_name, " ", last_name) AS name'), 'status')
            ->where('course_id', $this->id)
            ->get();
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public function isCoInstructor(int $user_id): bool
    {
        return DB::table('co_instructors')
            ->where('course_id', $this->id)
            ->where('user_id', $user_id)
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public function ownsCourseOrIsCoInstructor(int $user_id): bool
    {
        if ($this->user_id === $user_id) {
            return true;
        }
        if (DB::table('co_instructors')->where('course_id', $this->id)
            ->where('user_id', $user_id)
            ->where('status', 'accepted')
            ->exists()) {
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    public function reset(): array
    {
        $assignToTiming = new AssignToTiming();
        DB::beginTransaction();
        $fake_student = DB::table('enrollments')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('course_id', $this->id)
            ->where('fake_student', 1)
            ->first();

        $assignments = $this->assignments;
        $assignment_ids = [];
        foreach ($assignments as $assignment) {
            $assignment_ids[] = $assignment->id;
            $default_timing = $assignToTiming->where('assignment_id', $assignment->id)->first();
            $assignToTiming->deleteTimingsGroupsUsers($assignment);
            $assign_to_timing_id = $assignment->saveAssignmentTimingAndGroup($assignment, $default_timing);
            $assignToUser = new AssignToUser();
            $assignToUser->assign_to_timing_id = $assign_to_timing_id;
            $assignToUser->user_id = $fake_student->user_id;
            $assignToUser->save();
        }

        DB::table('submissions')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('submission_files')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('mastery_assignment_attempts')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('scores')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('cutups')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('seeds')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('assignment_question_time_on_tasks')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('compiled_pdf_overrides')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('question_level_overrides')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('assignment_level_overrides')->whereIn('assignment_id', $assignment_ids)->delete();
        //reset all of the LMS stuff
        DB::table('lti_grade_passbacks')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('lti_launches')->whereIn('assignment_id', $assignment_ids)->delete();
        DB::table('assignments')->whereIn('id', $assignment_ids)
            ->update(['lms_resource_link_id' => null]);
        $discussions = Discussion::whereIn('assignment_id', $assignment_ids)->get();
        foreach ($discussions as $discussion) {
            DB::table('discussion_groups')->where('assignment_id', $discussion->assignment_id)->delete();

            $discussion_comments = DiscussionComment::where('discussion_id', $discussion->id)->get();
            foreach ($discussion_comments as $discussion_comment) {
                $file = $discussion_comment->file;
                if ($file) {
                    foreach ([$file, pathinfo($file, PATHINFO_FILENAME) . '.vtt'] as $filename) {
                        $s3_key = "uploads/question-media/$filename";
                        if (Storage::disk('s3')->exists($s3_key)) {
                            Storage::disk('s3')->delete($s3_key);
                        }
                    }
                }
                $discussion_comment->delete();
            }
            $discussion->delete();
        }

        $this->extensions()->delete();
        $this->extraCredits()->delete();
        DB::table('enrollments')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('course_id', $this->id)
            ->where('fake_student', 0)
            ->delete();
        DB::table('pending_course_invitations')->where('course_id', $this->id)->delete();
        foreach ($assignments as $assignment) {
            DeleteAssignmentDirectoryFromS3::dispatch($assignment->id);
        }
        DB::commit();

        $response['type'] = 'success';
        $response['message'] = "All students from <strong>$this->name</strong> have been unenrolled and their data removed.";
        return $response;
    }

    /**
     * @return array
     */
    function assignmentIdsWithSubmissionsOrFileSubmissions(): array
    {
        $final_assignment_ids = [];
        $course_assignment_ids = $this->assignments->pluck('id')->toArray();
        $real_students_who_can_submit = $this->realStudentsWhoCanSubmit()->pluck('user_id')->toArray();


        $assignments_with_submissions = DB::table('submissions')
            ->whereIn('user_id', $real_students_who_can_submit)
            ->select('assignment_id')
            ->groupBy('assignment_id')
            ->get()
            ->pluck('assignment_id');

        foreach ($assignments_with_submissions as $assignment_with_submission) {
            if (in_array($assignment_with_submission, $course_assignment_ids)) {
                $final_assignment_ids[] = $assignment_with_submission;
            }
        }
        $assignments_with_submission_files = DB::table('submission_files')
            ->whereIn('user_id', $real_students_who_can_submit)
            ->select('assignment_id')
            ->groupBy('assignment_id')
            ->get()
            ->pluck('assignment_id')
            ->toArray();
        foreach ($assignments_with_submission_files as $assignment_with_submission_file) {
            if (in_array($assignment_with_submission_file, $course_assignment_ids)) {
                $final_assignment_ids[] = $assignment_with_submission_file;
            }
        }
        $questions_with_discussion_submissions = DB::table('discussions')
            ->whereIn('user_id', $real_students_who_can_submit)
            ->select('assignment_id')
            ->groupBy('assignment_id')
            ->get()
            ->pluck('assignment_id')
            ->toArray();
        foreach ($questions_with_discussion_submissions as $questions_with_discussion_submission) {
            if (in_array($questions_with_discussion_submission, $course_assignment_ids)) {
                $final_assignment_ids[] = $questions_with_discussion_submission;
            }
        }


        return array_unique($final_assignment_ids);
    }

    /**
     * @return Collection
     */
    public
    function betaCoursesInfo(): Collection
    {
        return DB::table('beta_courses')
            ->join('courses', 'beta_courses.id', '=', 'courses.id')
            ->join('users', 'courses.user_id', '=', 'users.id')
            ->where('alpha_course_id', $this->id)
            ->select('courses.name',
                DB::raw("CONCAT(users.first_name, ' ',users.last_name) AS user_name"),
                'users.email'
            )
            ->get();

    }

    /**
     * @return bool
     */
    public
    function isBetaCourse(): bool
    {
        return DB::table('beta_courses')->where('id', $this->id)->first() !== null;

    }


    public
    function betaAssignmentIds(): array
    {
        $beta_assignment_ids = [];
        $beta_assignments = DB::table('assignments')
            ->join('beta_assignments', 'assignments.id', '=', 'beta_assignments.id')
            ->where('assignments.course_id', $this->id)
            ->get();

        if ($beta_assignments) {
            foreach ($beta_assignments as $beta_assignment) {
                $beta_assignment_ids[] = $beta_assignment->id;
            }
        }
        return $beta_assignment_ids;
    }

    public
    function school()
    {
        return $this->belongsTo('App\School');
    }

    public
    function extraCredits()
    {
        return $this->hasMany('App\ExtraCredit');
    }

    public
    function headGrader()
    {
        return $this->hasOne('App\HeadGrader');
    }

    public
    function sections()
    {
        return $this->hasMany('App\Section');
    }

    public
    function graderNotifications()
    {
        return $this->hasOne('App\GraderNotification');
    }

    /**
     * @param User $user
     * @param array $request
     * @return array
     * @throws Exception
     */
    public
    function import(User  $user,
                    array $request): array
    {
        $request = (object)$request;
        $school = new School();
        $betaCourse = new BetaCourse;
        $assignmentGroup = new AssignmentGroup();
        $assignmentGroupWeight = new AssignmentGroupWeight();
        $assignmentSyncQuestion = new AssignmentSyncQuestion();
        $enrollment = new Enrollment();
        $finalGrade = new FinalGrade();
        $section = new Section();
        $last_school_info = $school->getLastSchool($user);
        $response['type'] = 'error';
        try {
            DB::beginTransaction();
            $imported_course = $this->replicate();
            $action = $request->action === 'import' ? "Import" : "Copy";
            $imported_course->name = "$imported_course->name " . $action;
            $imported_course->start_date = Carbon::now()->startOfDay();
            $imported_course->end_date = Carbon::now()->startOfDay()->addMonths(3);
            $imported_course->shown = 0;
            $imported_course->public = 0;
            $imported_course->alpha = 0;
            $imported_course->lms = 0;
            $imported_course->lms_course_id = null;
            $imported_course->anonymous_users = 0;
            $imported_course->school_id = $last_school_info['school_id'];
            $imported_course->show_z_scores = 0;
            $imported_course->students_can_view_weighted_average = 0;
            $imported_course->user_id = $user->id;
            $imported_course->order = 0;
            $imported_course->save();
            $course_id = $imported_course->id;
            $courseOrder = new CourseOrder();
            $courseOrder->user_id = $user->id;
            $courseOrder->order = 0;
            $courseOrder->course_id = $course_id;
            $courseOrder->save();
            $whitelistedDomain = new WhitelistedDomain();
            $whitelistedDomain->whitelisted_domain = $whitelistedDomain->getWhitelistedDomainFromEmail($user->email);
            $whitelistedDomain->course_id = $course_id;
            if ($request->import_as_beta) {
                $betaCourse->id = $imported_course->id;
                $betaCourse->alpha_course_id = $this->id;
                $betaCourse->save();
            }

            $whitelistedDomain->save();
            $minutes_diff = 0;

            if (property_exists($request, 'shift_dates') && $request->shift_dates && $this->assignments->isNotEmpty()) {

                $first_assignment = $this->assignments[0];

                $carbon_time = Carbon::createFromFormat('h:i A', $request->due_time)
                    ->format('H:i:00');

                $new_due = $request->due_date . ' ' . $carbon_time;
                $first_assignment_timing = DB::table('assign_to_timings')
                    ->where('assignment_id', $first_assignment->id)
                    ->first();
                $old_due = $first_assignment_timing->due;

                $date1 = Carbon::createFromFormat('Y-m-d H:i:s', $old_due, 'UTC');
                $date2 = Carbon::createFromFormat('Y-m-d H:i:s', $new_due, $user->time_zone);
                $minutes_diff = $date1->diffInMinutes($date2);

            }
//auto-releases does not exist for public courses
            if (property_exists($request, 'auto_release') && $request->auto_releases === 'use existing') {
                $assignment_ids = $this->assignments->pluck('id')->toArray();
                $auto_releases = AutoRelease::whereIn('type_id', $assignment_ids)->where('type', 'assignment')->get();
                $auto_releases_by_assignment_id = [];
                foreach ($auto_releases as $auto_release) {
                    $auto_releases_by_assignment_id[$auto_release->type_id] = $auto_release;
                }
            } else {
                $autoRelease = new AutoRelease();
                $auto_release_keys = $autoRelease->keys();
                foreach ($auto_release_keys as $key) {
                    $course_auto_release_key = "auto_release_$key";
                    $imported_course->{$course_auto_release_key} = null;
                }
                $imported_course->save();
            }
            foreach ($this->assignments as $assignment) {
                $imported_assignment = $this->cloneAssignment($assignmentGroup, $imported_course, $assignment, $assignmentGroupWeight, $this);
                if ($request->import_as_beta) {
                    BetaAssignment::create([
                        'id' => $imported_assignment->id,
                        'alpha_assignment_id' => $assignment->id
                    ]);
                }
                if (isset($auto_releases_by_assignment_id[$assignment->id])) {
                    $new_auto_release = $auto_releases_by_assignment_id[$assignment->id]->replicate();
                    $new_auto_release->type_id = $imported_assignment->id;
                    $new_auto_release->save();
                }
                $default_timing = DB::table('assign_to_timings')
                    ->join('assign_to_groups', 'assign_to_timings.id', '=', 'assign_to_groups.assign_to_timing_id')
                    ->where('assignment_id', $assignment->id)
                    ->first();
                foreach (['available_from', 'due', 'final_submission_deadline'] as $time) {
                    if ($default_timing->{$time}) {
                        $carbon_time = Carbon::createFromFormat('Y-m-d H:i:s', $default_timing->{$time});
                        $default_timing->{$time} = $carbon_time->addMinutes($minutes_diff)->format('Y-m-d H:i:s');
                    }
                }

                $assignment->saveAssignmentTimingAndGroup($imported_assignment, $default_timing);
                $reset_discuss_it_settings_to_default = property_exists($request, 'reset_discuss_it_settings_to_default') ? +$request->reset_discuss_it_settings_to_default : false;
                $reset_clicker_settings_to_default = property_exists($request, 'reset_clicker_settings_to_default') ? +$request->reset_clicker_settings_to_default : false;
                $remove_open_ended_questions_from_real_time_assignments = property_exists($request, 'remove_open_ended_questions_from_real_time_assignments') ? +$request->remove_open_ended_questions_from_real_time_assignments : false;

                $assignmentSyncQuestion->importAssignmentQuestionsAndLearningTrees($assignment->id, $imported_assignment->id, $reset_discuss_it_settings_to_default, $reset_clicker_settings_to_default, $remove_open_ended_questions_from_real_time_assignments);
            }

            $this->prepareNewCourse($user, $section, $imported_course, $this, $enrollment, $finalGrade, $courseOrder);
            $fake_user = DB::table('enrollments')
                ->join('users', 'enrollments.user_id', '=', 'users.id')
                ->where('course_id', $imported_course->id)
                ->where('fake_student', 1)
                ->first();

            $assign_to_timings = DB::table('assign_to_timings')
                ->whereIn('assignment_id', $imported_course->assignments->pluck('id')->toArray())
                ->get();
            foreach ($assign_to_timings as $assign_to_timing) {
                $assignToUser = new AssignToUser();
                $assignToUser->assign_to_timing_id = $assign_to_timing->id;
                $assignToUser->user_id = $fake_user->id;
                $assignToUser->save();
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "<strong>$imported_course->name</strong> has been created.  Please change the individual assignment due dates to match your new course or go to Course Properties->Edit Asignment Dates to take advantage of the bulk editing tool.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating the $request->action.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * @param User $user
     * @param Section $section
     * @param Course $new_course
     * @param Course $course
     * @param Enrollment $enrollment
     * @param FinalGrade $finalGrade
     * @param CourseOrder $courseOrder
     * @return void
     */
    public
    function prepareNewCourse(User        $user,
                              Section     $section,
                              Course      $new_course,
                              Course      $course,
                              Enrollment  $enrollment,
                              FinalGrade  $finalGrade,
                              CourseOrder $courseOrder)
    {
        $section->name = 'Main';
        $section->course_id = $new_course->id;
        $section->crn = "To be determined";
        $section->save();
        $course->enrollFakeStudent($new_course->id, $section->id, $enrollment);
        $finalGrade->setDefaultLetterGrades($new_course->id);
        $courseOrder->reorderAllCourses($user);

    }

    public
    function getLtiRegistration()
    {
        return DB::table('lti_schools')
            ->join('lti_registrations', 'lti_registrations.id', '=', 'lti_schools.lti_registration_id')
            ->where('school_id', $this->school_id)
            ->first();
    }

    /**
     * @return HasManyThrough
     */
    public
    function scores()
    {
        return $this->hasManyThrough('App\Score', 'App\Assignment');
    }

    /**
     * @throws Exception
     */
    public
    function concludedCourses(string $operator_text, int $num_days): Collection
    {

        $concluded_courses = DB::table('courses')
            ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->select('courses.id',
                'courses.name',
                'courses.user_id',
                'courses.end_date')
            ->where('users.fake_student', 0);
        switch ($operator_text) {
            case('more-than'):
                $concluded_courses = $concluded_courses->where('end_date', '<', Carbon::now()->subDays($num_days));
                break;
            case('equals'):
                $concluded_courses = $concluded_courses->where(DB::raw('DATE(`end_date`)'), '=', Carbon::now()->subDays($num_days)->toDateString());
                break;
            default:
                throw new Exception ("$operator_text is not a valid operator.");
        }
        $concluded_courses = $concluded_courses
            ->groupBy('courses.id')
            ->orderBy('end_date', 'desc')
            ->get();
        $course_ids = [];
        foreach ($concluded_courses as $course_info) {
            $course_ids[] = $course_info->id;
        }
        $course_infos = DB::table('courses')
            ->join('users', 'courses.user_id', '=', 'users.id')
            ->select('courses.id',
                'users.email',
                'first_name',
                DB::raw('CONCAT(first_name, " " , last_name) AS instructor'))
            ->whereIn('courses.id', $course_ids)
            ->get();
        $courses = [];
        foreach ($course_infos as $course_info) {
            $courses[$course_info->id] = $course_info;
        }
        foreach ($concluded_courses as $key => $concluded_course) {
            if ($courses[$concluded_course->id]->email === 'adapt@libretexts.org') {
                unset($concluded_courses[$key]);
            } else {
                $concluded_courses[$key]->email = $courses[$concluded_course->id]->email;
                $concluded_courses[$key]->first_name = $courses[$concluded_course->id]->first_name;
                $concluded_courses[$key]->instructor = $courses[$concluded_course->id]->instructor;
            }
        }
        return $concluded_courses->values();
    }


    /**
     * @return Collection
     */
    public
    function assignmentGroups(): Collection
    {
        $default_assignment_groups = AssignmentGroup::where('user_id', 0)->select()->get();
        $course_assignment_groups = AssignmentGroup::where('user_id', Auth::user()->id)
            ->where('course_id', $this->id)
            ->select()
            ->get();
        $assignmentGroup = new AssignmentGroup();
        return $assignmentGroup->combine($default_assignment_groups, $course_assignment_groups);

    }

    public
    function assignmentGroupWeights()
    {

        $assignment_group_ids = DB::table('assignments')
            ->select('assignment_group_id')
            ->where('course_id', $this->id)
            ->groupBy('assignment_group_id')
            ->select('assignment_group_id')
            ->pluck('assignment_group_id')
            ->toArray();

        return DB::table('assignment_group_weights')
            ->join('assignment_groups', 'assignment_group_weights.assignment_group_id', '=', 'assignment_groups.id')
            ->whereIn('assignment_group_id', $assignment_group_ids)
            ->where('assignment_group_weights.course_id', $this->id)
            ->groupBy('assignment_group_id', 'assignment_group_weights.assignment_group_weight')
            ->select('assignment_group_id AS id', 'assignment_groups.assignment_group', 'assignment_group_weights.assignment_group_weight')
            ->get();

    }

    /**
     * @return Collection
     */
    public
    function realStudentsWhoCanSubmit(): Collection
    {

        return DB::table('enrollments')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('formative_student', 0)
            ->where('course_id', $this->id)
            ->get();
    }


    public
    function enrolledUsers()
    {

        return $this->hasManyThrough('App\User',
            'App\Enrollment',
            'course_id', //foreign key on enrollments table
            'id', //foreign key on users table
            'id', //local key in courses table
            'user_id')
            ->where('fake_student', 0)
            ->where('formative_student', 0)
            ->orderBy('enrollments.id'); //local key in enrollments table
    }


    /**
     * @return array
     */
    public
    function sectionEnrollmentsByUser()
    {
        $enrolled_user_ids = $this->enrolledUsers->pluck('id')->toArray();
        $enrollments = DB::table('enrollments')
            ->join('sections', 'enrollments.section_id', '=', 'sections.id')
            ->where('enrollments.course_id', $this->id)
            ->whereIn('enrollments.user_id', $enrolled_user_ids)
            ->select('user_id', 'sections.name', 'sections.crn')
            ->get();
        $enrolled_users_by_section = [];
        foreach ($enrollments as $enrollment) {
            $enrolled_users_by_section[$enrollment->user_id] = [
                'crn' => $enrollment->crn,
                'course_section' => "$this->name - $enrollment->name"
            ];
        }
        return $enrolled_users_by_section;
    }

    public
    function enrolledUsersWithFakeStudent()
    {

        return $this->hasManyThrough('App\User',
            'App\Enrollment',
            'course_id', //foreign key on enrollments table
            'id', //foreign key on users table
            'id', //local key in courses table
            'user_id')
            ->orderBy('enrollments.id'); //local key in enrollments table
    }

    public
    function extensions()
    {
        return $this->hasManyThrough('App\Extension',
            'App\Assignment',
            'course_id', //foreign key on assignments table
            'assignment_id', //foreign key on extensions table
            'id', //local key in courses table
            'id'); //local key in assignments table
    }

    public
    function assignments()
    {
        return Auth::user() && Auth::user()->role === 3
            ? $this->hasMany('App\Assignment')
            : $this->hasMany('App\Assignment')->orderBy('order');
    }


    public
    function enrollments()
    {
        return $this->hasMany('App\Enrollment');
    }

    public
    function fakeStudent()
    {
        $fake_student_user_id = DB::table('enrollments')->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('course_id', $this->id)
            ->where('fake_student', 1)
            ->select('users.id')
            ->pluck('id')
            ->first();
        return User::find($fake_student_user_id);
    }

    public
    function fakeStudentIds()
    {
        return DB::table('enrollments')->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('course_id', $this->id)
            ->where('fake_student', 1)
            ->select('users.id')
            ->get()
            ->pluck('id')
            ->toArray();
    }


    public
    function finalGrades()
    {
        return $this->hasOne('App\FinalGrade');
    }

    public
    function graderSections($user = null)
    {
        $user = ($user === null) ? Auth::user() : $user;
        return DB::table('graders')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->where('sections.course_id', $this->id)
            ->where('graders.user_id', $user->id)
            ->select('sections.*')
            ->orderBy('sections.name')
            ->get();


    }

    /**
     * @param int $user_id
     * @return array
     */
    public
    function accessbileAssignmentsByGrader(int $user_id): array
    {


        $cannot_access_assignments = DB::table('assignment_grader_access')
            ->whereIn('assignment_id', $this->assignments->pluck('id')->toArray())
            ->where('user_id', $user_id)
            ->where('access_level', 0)
            ->select('assignment_id')
            ->get();
        $cannot_access_assignment_ids = [];
        foreach ($cannot_access_assignments as $cannot_access_assignment) {
            $cannot_access_assignment_ids[] = $cannot_access_assignment->assignment_id;
        }
        $accessible_assignment_ids = [];

        foreach ($this->assignments as $assignment) {

            $accessible_assignment_ids[$assignment->id] = !in_array($assignment->id, $cannot_access_assignment_ids);
        }
        return $accessible_assignment_ids;

    }

    public
    function contactGraderOverride()
    {
        $contact_grader_override = DB::table('contact_grader_overrides')
            ->where('course_id', $this->id)
            ->first();
        return $contact_grader_override ? $contact_grader_override->user_id : null;
    }

    public
    function graders()
    {

        return DB::table('graders')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->join('users', 'graders.user_id', '=', 'users.id')
            ->where('sections.course_id', $this->id)
            ->select('users.id')
            ->groupBy('id')
            ->get();

    }

    public
    function graderInfo()
    {

        $grader_info = DB::table('graders')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->join('users', 'graders.user_id', '=', 'users.id')
            ->where('sections.course_id', $this->id)
            ->select('users.id AS user_id',
                DB::raw("CONCAT(users.first_name, ' ',users.last_name) AS user_name"),
                'email',
                'sections.name AS section_name',
                'sections.id as section_id')
            ->get();
        $graders = [];
        foreach ($grader_info as $grader) {
            if (!isset($graders[$grader->user_id])) {
                $graders[$grader->user_id]['user_id'] = $grader->user_id;
                $graders[$grader->user_id]['sections'] = [];
                $graders[$grader->user_id]['name'] = $grader->user_name;
                $graders[$grader->user_id]['email'] = $grader->email;
            }
            $graders[$grader->user_id]['sections'] [$grader->section_id] = $grader->section_name;
        }
        usort($graders, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return array_values($graders);
    }


    /**
     * @param int $course_id
     * @param int $section_id
     * @param Enrollment $enrollment
     * @return Enrollment
     */
    public
    function enrollFakeStudent(int $course_id, int $section_id, Enrollment $enrollment): Enrollment
    {
        $fake_student = new User();
        $course = Course::find($course_id);
        $fake_student->last_name = 'Student';
        $fake_student->first_name = 'Fake';
        $fake_student->time_zone = User::find($course->user_id)->time_zone;
        $fake_student->fake_student = 1;
        $fake_student->role = 3;
        $fake_student->save();

        //enroll the fake student
        $enrollment->user_id = $fake_student->id;
        $enrollment->section_id = $section_id;
        $enrollment->course_id = $course_id;
        $enrollment->save();
        return $enrollment;


    }

    public
    function isGrader()
    {
        $graders = DB::table('graders')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->where('sections.course_id', $this->id)
            ->select('user_id')
            ->get()
            ->pluck('user_id')
            ->toArray();
        return (in_array(Auth::user()->id, $graders));
    }

    public
    function assignTosByAssignmentAndUser()
    {
        $assigned_assignments = DB::table('assignments')
            ->join('assign_to_timings', 'assignments.id', '=', 'assign_to_timings.assignment_id')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->where('assignments.course_id', $this->id)
            ->select('assignments.id AS assignment_id', 'assign_to_users.user_id AS user_id')
            ->get();
        $assigned_assignments_by_assignment_and_user_id = [];
        foreach ($assigned_assignments as $assignment) {
            $assigned_assignments_by_assignment_and_user_id[$assignment->assignment_id][] = $assignment->user_id;
        }
        return $assigned_assignments_by_assignment_and_user_id;
    }

    public
    function assignedToAssignmentsByUser()
    {
        $assigned_assignments = DB::table('assignments')
            ->join('assign_to_timings', 'assignments.id', '=', 'assign_to_timings.assignment_id')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->where('assignments.course_id', $this->id)
            ->where('assign_to_users.user_id', auth()->user()->id)
            ->get();
        $assigned_assignments_by_id = [];
        foreach ($assigned_assignments as $assignment) {
            $assigned_assignments_by_id[$assignment->assignment_id] = $assignment;
        }

        return $assigned_assignments_by_id;
    }

    /**
     * @return string
     */
    public
    function bulkUploadAllowed(): string
    {
        $beta_courses = DB::table('courses')
            ->join('beta_courses', 'courses.id', '=', 'beta_courses.alpha_course_id')
            ->where('courses.id', $this->id)
            ->select('courses.name as name')
            ->get();
        if ($beta_courses->isNotEmpty()) {
            return "Bulk upload is not possible for Alpha courses which already have Beta courses.  You can always make a copy of the course and upload these questions to the copied course.";
        }

        $course_enrollments = DB::table('courses')
            ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('courses.id', $this->id)
            ->where('fake_student', 0)
            ->where('courses.user_id', $this->user_id)
            ->select('courses.name as name')
            ->get();
        if ($course_enrollments->isNotEmpty()) {
            return "Bulk upload is only possible for courses without any enrollments.  Please make a copy of the course and upload these questions to the copied course.";
        }
        return '';
    }

    /**
     * @return Collection
     */
    function publicCourses(): Collection
    {
        $commons_user = DB::table('users')->where('email', 'commons@libretexts.org')->first();
        $commons_courses = DB::table('courses')
            ->where('user_id', $commons_user->id)
            ->where('public', 1)
            ->get('courses.id AS course_id')
            ->pluck('course_id')
            ->toArray();

        $public_courses_with_at_least_one_assignment = DB::table('courses')
            ->join('assignments', 'courses.id', '=', 'assignments.course_id')
            ->where('public', 1)
            ->where('courses.user_id', '<>', $commons_user->id)
            ->select('courses.id AS course_id')
            ->groupBy('course_id')
            ->get()
            ->pluck('course_id')
            ->toArray();
        $public_courses_with_at_least_one_question = DB::table('assignment_question')
            ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
            ->whereIn('assignments.course_id', $public_courses_with_at_least_one_assignment)
            ->select('course_id', DB::raw("COUNT(question_id)"))
            ->groupBy('course_id')
            ->havingRaw("COUNT(question_id) > 0")
            ->get()
            ->pluck('course_id')
            ->toArray();

        return DB::table('courses')
            ->leftJoin('disciplines', 'courses.discipline_id', '=', 'disciplines.id')
            ->join('users', 'courses.user_id', '=', 'users.id')
            ->join('schools', 'courses.school_id', '=', 'schools.id')
            ->whereIn('courses.id', $public_courses_with_at_least_one_question)
            ->orWhereIn('courses.id', $commons_courses)
            ->select('courses.id',
                'courses.formative',
                'courses.name AS name',
                'schools.name AS school',
                'disciplines.id AS discipline_id',
                'disciplines.name AS discipline_name',
                'term',
                'alpha',
                DB::raw('CONCAT(first_name, " " , last_name) AS instructor'),
                DB::raw("EXISTS (
            SELECT 1
            FROM assignments
            WHERE assignments.course_id = courses.id
              AND assignments.formative = 1
        ) AS has_at_least_one_formative_assignment"))
            ->orderBy('discipline_name')
            ->orderBy('name')
            ->get();
    }


    public
    function getCourses($user)
    {

        switch ($user->role) {
            case(6):
                return DB::table('tester_courses')
                    ->join('courses', 'tester_courses.course_id', '=', 'courses.id')
                    ->join('users', 'courses.user_id', '=', 'users.id')
                    ->where('tester_courses.user_id', $user->id)
                    ->select('courses.id',
                        'term',
                        'start_date',
                        'end_date',
                        'courses.name',
                        DB::raw('CONCAT(first_name, " ", last_name) AS instructor'))
                    ->get();
            case(5):
            case(2):
                return DB::table('courses')
                    ->select(
                        'courses.*',
                        'course_orders.order as course_order',
                        DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS main_instructor_name"),
                        DB::raw("beta_courses.id IS NOT NULL AS is_beta_course"),
                        DB::raw("EXISTS (
            SELECT 1
            FROM co_instructors
            WHERE co_instructors.course_id = courses.id
              AND co_instructors.user_id = {$user->id}
              AND co_instructors.status = 'accepted'
        ) AS is_co_instructor"),
                        DB::raw("EXISTS (
                SELECT 1
                FROM assignments
                WHERE assignments.course_id = courses.id
                  AND assignments.formative = 1
            ) AS has_at_least_one_formative_assignment")
                    )
                    ->join('course_orders', function ($join) use ($user) {
                        $join->on('course_orders.course_id', '=', 'courses.id')
                            ->where('course_orders.user_id', '=', $user->id);
                    })
                    ->leftJoin('beta_courses', 'courses.id', '=', 'beta_courses.id')
                    ->leftJoin('users', 'users.id', '=', 'courses.user_id') // Join to get main instructor
                    ->where(function ($query) use ($user) {
                        $query->where('courses.user_id', $user->id)
                            ->orWhereExists(function ($subquery) use ($user) {
                                $subquery->select(DB::raw(1))
                                    ->from('co_instructors')
                                    ->whereColumn('co_instructors.course_id', 'courses.id')
                                    ->where('co_instructors.user_id', $user->id)
                                    ->where('co_instructors.status', 'accepted');
                            });
                    })
                    ->orderBy('course_orders.order')
                    ->get();


            case(4):
                $sections = DB::table('graders')
                    ->join('sections', 'section_id', '=', 'sections.id')
                    ->where('user_id', $user->id)
                    ->get()
                    ->pluck('section_id');

                $course_section_info = DB::table('courses')
                    ->join('sections', 'courses.id', '=', 'sections.course_id')
                    ->select('courses.id AS id',
                        DB::raw('courses.id AS course_id'),
                        'start_date',
                        'end_date',
                        DB::raw('courses.name AS course_name'),
                        DB::raw('sections.name AS section_name')
                    )
                    ->whereIn('sections.id', $sections)->orderBy('start_date', 'desc')
                    ->get();

                $course_sections = [];
                foreach ($course_section_info as $course_section) {
                    if (!isset($course_sections[$course_section->course_id])) {
                        $course_sections[$course_section->course_id]['id'] = $course_section->course_id;
                        $course_sections[$course_section->course_id]['name'] = $course_section->course_name;
                        $course_sections[$course_section->course_id]['start_date'] = $course_section->start_date;
                        $course_sections[$course_section->course_id]['end_date'] = $course_section->end_date;
                        $course_sections[$course_section->course_id]['sections'] = [];
                    }
                    $course_sections[$course_section->course_id]['sections'][] = $course_section->section_name;
                }

                foreach ($course_sections as $key => $course_section) {
                    $course_sections[$key]['sections'] = implode(', ', $course_section['sections']);
                }
                $course_sections = array_values($course_sections);
                return collect($course_sections);
            default:
                return [];
        }
    }

    /**
     * @param AssignmentGroup $assignmentGroup
     * @param Course $cloned_course
     * @param $assignment
     * @param AssignmentGroupWeight $assignmentGroupWeight
     * @param Course $course
     * @return mixed
     */
    public
    function cloneAssignment(AssignmentGroup $assignmentGroup, Course $cloned_course, $assignment, AssignmentGroupWeight $assignmentGroupWeight, Course $course)
    {
        $cloned_assignment_group_id = $assignmentGroup->importAssignmentGroupToCourse($cloned_course, $assignment);
        $assignmentGroupWeight->importAssignmentGroupWeightToCourse($course, $cloned_course, $cloned_assignment_group_id, false);
        $cloned_assignment = $assignment->replicate();
        $cloned_assignment->course_id = $cloned_course->id;
        $cloned_assignment->shown = 0;
        $cloned_assignment->solutions_released = 0;
        $cloned_assignment->show_scores = 0;

        $cloned_assignment->students_can_view_assignment_statistics = 0;
        $cloned_assignment->assignment_group_id = $cloned_assignment_group_id;
        $cloned_assignment->lms_resource_link_id = null;
        $cloned_assignment->save();
        $case_study_notes = CaseStudyNote::where('assignment_id', $assignment->id)->get();
        foreach ($case_study_notes as $case_study_note) {
            $cloned_case_study_note = $case_study_note->replicate()->fill([
                'assignment_id' => $cloned_assignment->id
            ]);
            $cloned_case_study_note->save();
        }
        return $cloned_assignment;
    }

}
