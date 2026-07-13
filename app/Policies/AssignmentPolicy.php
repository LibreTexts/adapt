<?php

namespace App\Policies;

use App\Assignment;
use App\Course;
use App\Exceptions\Handler;
use App\GradingStyle;
use App\Helpers\Helper;
use App\Score;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use \App\Traits\CommonPolicies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssignmentPolicy
{
    use HandlesAuthorization;
    use CommonPolicies;

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function contactInstructorToReleaseAssignment(User $user, Assignment $assignment){
        return    $assignment->course->enrollments->contains('user_id', $user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to contact this instructor.');


    }
    public function shiftDates(User $user, Assignment $assignment, array $assignment_ids): Response
    {
        $assignments_not_owned = DB::table('assignments')
            ->join('courses', 'assignments.course_id', '=', 'courses.id')
            ->whereIn('assignments.id', $assignment_ids)
            ->where('user_id', '<>', $user->id)
            ->count();
        return $assignments_not_owned === 0
            ? Response::allow()
            : Response::deny('You are not allowed to shift the dates since you do not own all of these assignments.');

    }


    public function getReviewHistoryByAssignment(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to get the review histories for this assignment.');

    }

    public function getDates(User $user, Assignment $assignment, Course $course): Response
    {
        return $course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to get the dates since you do not own this course.');

    }


    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function validateNotWeightedPointsPerQuestionWithSubmissions(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to validate whether this is an assignment with weighted points per question that has a submission.');

    }

    public function unlinkFromLMS(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to unsync this assignment from your LMS.');

    }


    public function getClickerAssignmentsForEnrolledAndOpenCourses(User $user, Assignment $assignment): Response
    {
        return +$user->role === 3
            ? Response::allow()
            : Response::deny('You are not allowed to get all of the assignments for enrolled and open courses.');

    }


    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function linkToLMS(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to link this assignment to your LMS.');

    }


    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function unlinkLti(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to unlink this assignment from your LMS.');

    }

    public function getRubricCategories(User $user, Assignment $assignment): Response
    {
        return $this->isOwnerOrGrader($assignment, $user)
            ? Response::allow()
            : Response::deny('You are not allowed to retrieve the rubrics for this assignment.');

    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function questionUrlView(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to update the question URL view for this assignment.');

    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function updateCommonQuestionText(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to update the common question text for this assignment.');

    }

    public function showCommonQuestionText(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to get the common question text for this assignment.');

    }


    public function linkAssignmentToLMS(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to link this assignment.');


    }

    public function order(User $user, Assignment $assignment, Course $course)
    {

        return $course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to re-order the assignments in that course.');

    }


    /**
     * Determine whether the user can view the assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function view(User $user, Assignment $assignment)
    {
        $has_access = $this->canView($user, $assignment);
        return $has_access
            ? Response::allow()
            : Response::deny('You are not allowed to access this assignment.');
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function getQuestionsWithCourseLevelUsageInfo(User $user, Assignment $assignment)

    {
        $has_access = (int)$assignment->course->user_id = $user->id || ($user->role === 2 && $assignment->course->public);
        return $has_access
            ? Response::allow()
            : Response::deny('You are not allowed to access these assignment questions.');
    }

    function canView(User $user, Assignment $assignment)
    {
        $has_access = false;
        switch ($user->role) {
            case(2):
                $has_access = (($assignment->course->public || Helper::isCommonsCourse($assignment->course)) && Helper::hasAnonymousUserSession())
                    || $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(3):
                $has_access = ($assignment->course->anonymous_users && Helper::isAnonymousUser())
                    || $assignment->course->enrollments->contains('user_id', $user->id);
                if (!$has_access) {
                    try {
                        $contents = "User: $user->id Assignment: $assignment->id, Course: {$assignment->course->id}";
                        $date_time = Carbon::now('America/Los_Angeles');
                        // Storage::disk('s3')->put("logs/$date_time", $contents, ['StorageClass' => 'STANDARD_IA']);
                    } catch (Exception $e) {
                        $h = new Handler(app());
                        $h->report($e);
                    }
                }
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
            case(5):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
        }
        return $has_access;
    }

    /**
     * Determine whether the user can update the assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return mixed
     */
    public function getQuestionsInfo(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to get questions for this assignment.');
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function downloadUsersForAssignmentOverride(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to download the users for this assignment.');
    }

    /**
     * Determine whether the user can update the assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return mixed
     */
    public function update(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to update this assignment.');
    }

    /**
     * Determine whether the user can update the assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return mixed
     */
    public function createFromTemplate(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny("You are not allowed to create an assignment from this template.");
    }


    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function showAssignment(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to toggle whether students can view an assignment.');
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function showQuestionTitles(User $user, Assignment $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to toggle whether students can view question titles.');
    }

    public function releaseSolutions(User $user, Assignment $assignment)
    {
        $has_access = false;
        switch ($user->role) {
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
        }
        if (!$has_access) {
            $message = 'You are not allowed to show/hide solutions.';
        }
        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }

    public function showAssignmentStatistics(User $user, Assignment $assignment)
    {
        $has_access = false;
        switch ($user->role) {
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
        }

        return $has_access
            ? Response::allow()
            : Response::deny('You are not allowed to show/hide assignment statistics.');
    }


    public function scoresAccess(User $user, Assignment $assignment)
    {
        switch ($user->role) {
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(3):
                $has_access = $assignment->course->enrollments->contains('user_id', $user->id) && $assignment->students_can_view_assignment_statistics;
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
            default:
                $has_access = false;
        }
        return $has_access;
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function getAssignmentSummary(User $user, Assignment $assignment): Response
    {
        $has_access = $this->canView($user, $assignment);
        if ($user->role ===3 && !$assignment->shown && !$user->fake_student){
            $has_access = false;
        }
        return $has_access
            ? Response::allow()
            : Response::deny('You are not allowed to retrieve this summary.');

    }

    public function scoresInfo(User $user, Assignment $assignment)
    {

        return $this->scoresAccess($user, $assignment)
            ? Response::allow()
            : Response::deny('You are not allowed to get these scores.');

    }


    public function gradersCanSeeStudentNames(User $user, Assignment $assignment)
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny("You are not allowed to switch whether graders can view their students' names.");
    }

    public function showScores(User $user, Assignment $assignment)
    {
        $has_access = false;
        switch ($user->role) {
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
        }

        if (!$has_access) {
            $message = 'You are not allowed to show/hide scores.';
        }

        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }

    public function showPointsPerQuestion(User $user, Assignment $assignment)
    {
        $has_access = false;
        switch ($user->role) {
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(4):
                $has_access = $assignment->course->isGrader();
                break;
        }
        if (!$has_access) {
            $message = 'You are not allowed to show/hide the points per question.';
        }

        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }


    /**
     * Determine whether the user can delete the assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return Response
     */
    public function delete(User $user, Assignment $assignment)
    {
        //added (int) because wasn't working in the test
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to delete this assignment.');
    }


}
