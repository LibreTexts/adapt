<?php


namespace App\Traits;

use App\Assignment;
use App\Grader;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;


trait GeneralSubmissionPolicy


{
    public function canViewSubmittedFiles(User       $user,
                                          Assignment $assignment,
                                          User       $studentUser,
                                          Grader     $grader)
    {

        $is_student_in_course = $assignment->course->enrollments->contains('user_id', $studentUser->id);

        switch ($user->role) {
            case(2):
                $has_access = $is_student_in_course && $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            case(3):
                $has_access = $user->id === $studentUser->id;
                break;
            case(4):
                $section_id = $assignment->course->enrollments->where('user_id', $studentUser->id)->pluck('section_id')->first();
                $grader_sections = $grader->where('user_id', $user->id)->select('section_id')->get();

                $override_access = false;
                $access_level_override = $assignment->graders()
                    ->where('assignment_grader_access.user_id', $user->id)
                    ->first();
                if ($access_level_override) {
                    $override_access = $access_level_override->pivot->access_level;
                }

                $has_access = $is_student_in_course && ($override_access || $grader_sections->isNotEmpty() && in_array($section_id, $grader_sections->pluck('section_id')->toArray()));
                break;
            default:
                $has_access = false;

        }
        return $has_access;
    }

    /**
     * @param User $user
     * @param $assignment
     * @param int $assignment_id
     * @param int $question_id
     * @param string $level
     * @return array
     */
    public function canSubmitBasedOnGeneralSubmissionPolicy(User $user, $assignment, int $assignment_id, int $question_id, $level = 'question'): array
    {
        $response['type'] = 'error';
        $response['message'] = '';

        /** $db_question_updated_at = Question::find($question_id)->updated_at->timestamp;
         * if ((int) $db_question_updated_at !==  (int) (request()->cookie('loaded_question_updated_at'))) {
         * $response['message'] = 'It looks like this question has been updated!  Please refresh the page and re-submit.';
         * return $response;
         * }**/

        if ($assignment->course->ownsCourseOrIsCoInstructor($user->id)
            || $user->role === 5
            || $assignment->formative
            || $assignment->course->formative
            || ($assignment->course->anonymous_users && (Helper::isAnonymousUser() || Helper::hasAnonymousUserSession()))) {
            $response['type'] = 'success';
            return $response;
        }

        $assign_to_timing = $assignment->assignToTimingByUser('',$user->id);
        if (!$assign_to_timing) {
            $response['message'] = "No responses will be saved since you were not assigned to this assignment.";
            return $response;
        }
        $available_from = $assign_to_timing->available_from;
        $due = $assign_to_timing->due;

        if ($assignment->assessment_type === 'real time') {
            $submission = DB::table('submissions')
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->where('user_id', $user->id)
                ->first();
            $gave_up = DB::table('can_give_ups')
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->where('user_id', $user->id)
                ->where('status', 'gave up')
                ->first();
            if ($submission && $submission->show_solution) {
                $response['message'] = "The solution is already available so you cannot resubmit.";
                return $response;
            }
            if ($gave_up) {
                $response['message'] = "You've given up on this question so it cannot be resubmitted.";
                return $response;
            }
        }
        if ($assignment->assessment_type === 'clicker') {
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->select()
                ->first(['clicker_start', 'clicker_end']);
            if (!$assignment_question->clicker_start || (!(time() >= strtotime($assignment_question->clicker_start) && time() <= strtotime($assignment_question->clicker_end)))) {
                $response['message'] = "This question is currently not open for submission.";
                return $response;
            }
            if ($assignment_question->clicker_start && (time() >= strtotime($assignment_question->clicker_start) && time() <= strtotime($assignment_question->clicker_end))) {
                $response['type'] = "success";
                return $response;
            }

        }
        $question_in_assignment = $assignment->questions->contains($question_id);
        if (!$question_in_assignment) {
            foreach ($assignment->questions as $question) {
                if ($question->a11y_auto_graded_question_id === $question_id) {
                    $question_in_assignment = true;
                }
            }
        }
        if ($level === 'question' && !$question_in_assignment) {
            $response['message'] = 'No responses will be saved since that question is not in the assignment.';
            return $response;
        }

        if (!$assignment->course->enrollments->contains('user_id', $user->id)) {
            $response['message'] = 'No responses will be saved since the assignment is `not` part of your course.';
            return $response;
        }
        if ($user->instructor_user_id) {
            //logged in as student
            $response['type'] = 'success';
            return $response;
        }

        if (strtotime($available_from) > time()) {
            $response['message'] = 'No responses will be saved since this assignment is not yet available.';
            return $response;
        }

        $file_submission = DB::table('submission_files')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('user_id', $user->id)
            ->select('date_graded', 'type', 'grader_id')
            ->first();
        $submission_already_graded = false;
        if ($file_submission) {
            $submission_already_graded = $file_submission->type !== 'discuss_it'
                ? $file_submission->date_graded !== null && $assignment->scoring_type === 'p'
                : $file_submission->grader_id !== null;
        }

        if ($submission_already_graded) {
            $response['message'] = 'Your submission has already been graded and may not be re-submitted.';
            return $response;
        }

        $past_due = time() > strtotime($due);
        //check to see if the instructor accidentally released scores (which will have comments) or released solutions
        switch ($past_due) {
            case(false):
                if ($assignment->assessment_type === 'delayed') {
                    if ($assignment->show_scores) {
                        $response['message'] = 'No responses will be saved since the scores to this assignment have been released.';
                        return $response;
                    }
                }
                if (in_array($assignment->assessment_type, ['delayed', 'learning tree'])) {
                    if ($assignment->solutions_released) {
                        $response['message'] = 'No responses will be saved since the solutions to this assignment have been released.';
                        return $response;
                    }
                }
                break;
            case(true):
                if (DB::table('assignment_level_overrides')
                    ->where('assignment_id', $assignment_id)
                    ->where('user_id', $user->id)
                    ->exists()) {
                    $response['type'] = 'success';
                    return $response;
                }
                if ($assignment->late_policy === 'not accepted') {
                    $response['message'] = 'No responses will be saved since the due date for this assignment has passed.';
                    return $response;
                }
                if (in_array($assignment->late_policy, ['deduction', 'marked late'])) {
                    //now let's check the late policy deadline
                    //if past policy deadline
                    if (strtotime($assign_to_timing->final_submission_deadline) < time()) {
                        $response['message'] = 'No more late responses are being accepted.';
                        return $response;
                    }
                }
                break;
        }
        $response['type'] = 'success';
        return $response;
    }


}
