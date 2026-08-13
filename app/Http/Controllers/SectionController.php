<?php

namespace App\Http\Controllers;

use App\AssignmentGroup;
use App\AssignmentGroupWeight;
use App\AssignToUser;
use App\Course;
use App\Enrollment;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Http\Requests\StoreSection;
use App\Section;
use App\Submission;
use App\SubmissionFile;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SectionController extends Controller
{

    public function canCreateStudentAccessCodes(Request $request)
    {

        $response['type'] = 'error';
        try {
            $email = $request->user()->email;
            $response['can_create_student_access_codes'] = DB::table('no_student_access_codes')
                    ->where('email', $email)
                    ->first() === null;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting whether you can create student access codes.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    public function realEnrolledUsers(Request $request, Section $section)
    {
        $response['has_enrolled_users'] = true;
        try {
            $response['number_of_enrolled_users'] = count($section->enrolledUsers()->pluck('user_id'));
            $response['has_enrolled_users'] = $response['number_of_enrolled_users'] > 1;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the number of enrolled users.  Please try again or contact us for assistance.";

        }
        return $response;


    }

    public function destroy(Request        $request,
                            Section        $section,
                            Enrollment     $enrollment,
                            Submission     $submission,
                            SubmissionFile $submissionFile)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('destroy', $section);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $course = $section->course;

            if ((int)$course->sections[0]->id === (int)$section->id) {
                $response['message'] = "The first section cannot be removed.";
                return $response;
            }
            $section_name = $section->name;
            $enrolled_user_ids = $section->enrolledUsers()->pluck('user_id')->toArray();

            foreach ($course->assignments as $assignment) {
                $assignment->scores()->whereIn('user_id', $enrolled_user_ids)->delete();
                $assignment->seeds()->whereIn('user_id', $enrolled_user_ids)->delete();
                DB::table('mastery_assignment_attempts')
                    ->where('assignment_id', $assignment->id)
                    ->whereIn('user_id', $enrolled_user_ids)
                    ->delete();
                $submission->where('assignment_id', $assignment->id)
                    ->whereIn('user_id', $enrolled_user_ids)
                    ->delete();
                $submissionFile->where('assignment_id', $assignment->id)
                    ->whereIn('user_id', $enrolled_user_ids)
                    ->delete();
            }
            $enrollment->where('section_id', $section->id)->whereIn('user_id', $enrolled_user_ids)->delete();

            $section->graders()->delete();
            DB::table('grader_access_codes')->where('section_id', $section->id)->delete();
            DB::table('pending_course_invitations')->where('section_id', $section->id)->delete();
            $section->delete();
            DB::commit();
            $response['message'] = "<strong>$section_name</strong> has been deleted.";
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error deleting the section.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function index(Course $course): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('view', $course);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $sections = $course->sections;
            foreach ($sections as $section) {
                if (!$section->access_code) {
                    $section->access_code = Helper::createAccessCode();
                    $section->save();
                }
            }
            $response['sections'] = $course->sections;
            $response['course_start_date'] = $course->start_date;
            $response['course_end_date'] = $course->end_date;
            $response['is_lms'] = (bool) $course->lms;
            $response['is_lms_only_entry'] = (bool) $course->lms_only_entry;
            $response['type'] = 'success';

        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving your sections.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    public function refreshAccessCode(Request $request, Section $section)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('refreshAccessCode', [$section]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $section->access_code = Helper::createAccessCode();
            $section->save();
            $response['access_code'] = $section->access_code;
            $response['message'] = 'The access code has been refreshed.';
            $response['type'] = 'success';
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error refreshing the access code.  Please try again or contact us for assistance.";


        }
        return $response;
    }

    public function store(StoreSection $request,
                          Course       $course,
                          Section      $section,
                          Enrollment   $enrollment,
                          AssignToUser $assignToUser)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('store', [$section, $course]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $data = $request->validated();

        try {
            DB::beginTransaction();
            $section->name = $data['name'];
            $section->course_id = $course->id;
            $section->crn = $data['crn'];
            $section->access_code = Helper::createAccessCode();
            $section->save();
            $course->enrollFakeStudent($course->id, $section->id, $enrollment);
            $assignments = $course->assignments;
            $assignToUser->assignToUserForAssignments($assignments, $enrollment->user_id, $section->id);
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The section <strong>{$data['name']}</strong> has been created.";
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating <strong>{$data['name']}</strong>.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    public function update(StoreSection $request, Section $section)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $section);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $original_name = $section->name;
        $data = $request->validated();
        try {
            $section->name = $data['name'];
            $section->crn = $data['crn'];
            $section->save();
            $name_changed = $original_name !== $data['name'];
            $response['type'] = $name_changed ? 'success' : 'info';
            $response['message'] = $name_changed
                ? "The section <strong>$original_name</strong> has been changed to <strong>{$data['name']}</strong>."
                : "The new section name is the same as the old section name and has not been updated.";
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating <strong>$original_name</strong>.  Please try again or contact us for assistance.";
        }
        return $response;

    }

}
