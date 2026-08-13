<?php


namespace App\Traits;


use App\Assignment;
use App\AssignToGroup;
use App\AssignToTiming;
use App\AssignToUser;
use App\Helpers\Helper;
use App\Http\Requests\StoreAssignmentProperties;
use App\Section;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait AssignmentProperties

{
    /**
     * @param string $assessment_type
     * @param array $data
     * @return mixed|null
     */
    public function getNumberOfRandomizedAssessments($assessment_type, array $data)
    {
        if ($assessment_type === 'clicker') {
            return null;
        }
        return $data['number_of_randomized_assessments'] ?? null;
    }

    public function getPointsPerQuestion($data)
    {
        return $data['source'] === 'a' ? $data['points_per_question'] : null;
    }


    /**
     * @param Request $request
     * @return int|mixed
     */
    public function getAlgorithmic(Request $request)
    {
        return $request->source === 'a' ? $request->algorithmic : 0;

    }


    /**
     * @param $request
     * @return mixed|null
     */
    public function getSolutionsAvailability($request)
    {
        return $request->assessment_type === 'real time' ? $request->solutions_availability : null;
    }


    /**
     * @param $request
     * @return array|string|string[]|null
     */
    public function getHintPenalty($request)
    {

        return (int)$request->can_view_hint === 1
            ? str_replace('%', '', $request->hint_penalty)
            : 0;
    }

    /**
     * @param $request
     * @return mixed|null
     */
    public function getNumberOfAllowedAttempts($request)
    {
        return in_array($request->assessment_type, ['real time', 'learning tree', 'clicker']) ? $request->number_of_allowed_attempts : null;
    }

    /**
     * @param $request
     * @return array|string|string[]|null
     */
    public function getNumberOfAllowedAttemptsPenalty($request)
    {

        return in_array($request->assessment_type, ['real time', 'learning tree']) && $request->scoring_type === 'p' && (int)$request->number_of_allowed_attempts !== 1
            ? str_replace('%', '', $request->number_of_allowed_attempts_penalty)
            : 0;
    }

    /**
     * @param array $data
     * @return mixed|string|null
     */
    function getDefaultPointsPerQuestion(array $data)
    {
        return $data['source'] === 'a' && $data['points_per_question'] === 'number of points'
            ? Helper::removeZerosAfterDecimal($data['default_points_per_question'])
            : null;
    }

    /**
     * @param array $data
     * @return mixed|null
     */
    function getTotalAssignmentPoints(array $data)
    {
        return $data['source'] === 'a' && $data['points_per_question'] === 'question weight'
            ? $data['total_points']
            : null;
    }

    /**
     * @param $assessment_type
     * @param $data
     * @return mixed|null
     */
    function getDefaultClickerTimeToSubmit($assessment_type, $data)
    {
        return $assessment_type === 'clicker'
            ? $data['default_clicker_time_to_submit']
            : null;

    }

    /**
     * @param $request
     * @param $data
     * @return array|string|string[]|null
     */
    public
    function getDefaultOpenEndedTextEditor($request, $data)
    {
        if ($request->assessment_type !== 'delayed') {
            return null;
        } elseif ($data['file_upload_mode'] === 'combined_pdf') {
            return null;
        } elseif (strpos($data['default_open_ended_submission_type'], 'text') !== false) {
            return str_replace(' text', '', $data['default_open_ended_submission_type']);
        } else {
            return null;
        }


    }

    /**
     * @param Request $request
     * @param array $data
     * @return int|mixed
     */
    public
    function getDefaultOpenEndedSubmissionType(Request $request, array $data)
    {
        if ($request->source === 'x' || $request->assessment_type !== 'delayed') {
            return 0;
        } elseif (strpos($data['default_open_ended_submission_type'], 'text') !== false) {
            return 'text';
        } else {
            return $data['default_open_ended_submission_type'];
        }
    }

    public
    function getLateDeductionApplicationPeriod(StoreAssignmentProperties $request, array $data)
    {
        if ($request->late_deduction_applied_once) {
            return 'once';
        }
        return $data['late_deduction_application_period'] ?? null;
    }

    /**
     * @param $data
     * @param $request
     * @return array
     */
    public function getAssignmentProperties($data, $request): array
    {
        return [
            'public_description' => $request->public_description,
            'private_description' => $request->private_description,
            'source' => $data['source'],
            'assessment_type' => $data['source'] === 'a' ? $request->assessment_type : 'delayed',
            'formative' => $request->formative,
            'number_of_allowed_attempts' => $this->getNumberOfAllowedAttempts($request),
            'number_of_allowed_attempts_penalty' => $this->getNumberOfAllowedAttemptsPenalty($request),
            'can_view_hint' =>$request->can_view_hint,
            'can_submit_work' =>$request->can_submit_work,
            'submitted_work_format' =>+$request->can_submit_work ? json_encode($request->submitted_work_format) : null,
            'hint_penalty' => $this->getHintPenalty($request),
            'algorithmic' => $this->getAlgorithmic($request),
            'mastery_retake_enabled' => (int)$request->mastery_retake_enabled,
            'mastery_number_of_allowed_attempts' => $request->mastery_number_of_allowed_attempts,
            'solutions_availability' => $this->getSolutionsAvailability($request),
            'flashcard_settings' => $request->assessment_type === 'flashcard' ? json_encode($request->flashcard_settings) : null,
            // learning tree
            'min_number_of_minutes_in_exposition_node' => $request->min_number_of_minutes_in_exposition_node,
            'reset_node_after_incorrect_attempt' => $request->reset_node_after_incorrect_attempt,
            'number_of_successful_paths_for_a_reset' => $request->number_of_successful_paths_for_a_reset,
            // end learning tree
            'instructions' => $request->instructions ?: '',
            'number_of_randomized_assessments' => $this->getNumberOfRandomizedAssessments($request->assessment_type, $data),
            'external_source_points' => $data['source'] === 'x' ? $data['external_source_points'] : null,
            'assignment_group_id' => $data['assignment_group_id'],
            'points_per_question' => $this->getPointsPerQuestion($data),
            'default_points_per_question' => $this->getDefaultPointsPerQuestion($data),
            'total_points' => $this->getTotalAssignmentPoints($data),
            'default_clicker_time_to_submit' => $this->getDefaultClickerTimeToSubmit($request->assessment_type, $data),
            'scoring_type' => $data['scoring_type'],
            'default_completion_scoring_mode' => Helper::getCompletionScoringMode($data['scoring_type'], $request->default_completion_scoring_mode, $request->completion_split_auto_graded_percentage),
            'file_upload_mode' => $request->assessment_type === 'delayed' ? $data['file_upload_mode'] : null,
            'default_open_ended_submission_type' => $this->getDefaultOpenEndedSubmissionType($request, $data),
            'default_open_ended_text_editor' => $this->getDefaultOpenEndedTextEditor($request, $data),
            'late_policy' => $data['late_policy'],
            'show_scores' => ($data['source'] === 'x' || ($data['source'] === 'a' && $request->assessment_type === 'delayed')) ? 0 : 1,
            'solutions_released' => 0,
            'show_points_per_question' => ($data['source'] === 'x' || $request->assessment_type === 'delayed') ? 0 : 1,
            'late_deduction_percent' => $data['late_deduction_percent'] ?? null,
            'late_deduction_application_period' => $this->getLateDeductionApplicationPeriod($request, $data),
            'include_in_weighted_average' => $data['include_in_weighted_average'],
            'textbook_url' => $data['textbook_url'] ?? null,
            'notifications' => $data['notifications']
        ];
    }

    /**
     * @param $data
     * @param $request
     * @return mixed
     */
    public function getDataToUpdate($data, $request)
    {
        $data['number_of_allowed_attempts'] = $this->getNumberOfAllowedAttempts($request);
        $data['mastery_retake_enabled'] = (int)$request->mastery_retake_enabled;
        $data['mastery_number_of_allowed_attempts'] = $request->mastery_number_of_allowed_attempts;
        $data['number_of_allowed_attempts_penalty'] = $this->getNumberOfAllowedAttemptsPenalty($request);
        $data['can_view_hint'] = $request->can_view_hint;
        $data['hint_penalty'] = $this->getHintPenalty($request);
        $data['public_description'] = $request->public_description;
        $data['private_description'] = $request->private_description;
        $data['assessment_type'] = ($request->assessment_type && $request->source === 'a') ? $request->assessment_type : '';
        $data['instructions'] = $request->instructions ?: '';
        $default_open_ended_text_editor = $this->getDefaultOpenEndedTextEditor($request, $data);//do it this way because I reset the data
        $data['default_open_ended_text_editor'] = $default_open_ended_text_editor;
        $data['default_open_ended_submission_type'] = $this->getDefaultOpenEndedSubmissionType($request, $data);
        $data['default_clicker_time_to_submit'] = $this->getDefaultClickerTimeToSubmit($request->assessment_type, $data);
        $data['number_of_randomized_assessments'] = $this->getNumberOfRandomizedAssessments($request->assessment_type, $data);
        $data['file_upload_mode'] = $request->assessment_type === 'delayed' ? $data['file_upload_mode'] : null;
        $data['points_per_question'] = $this->getPointsPerQuestion($data);

        //learning tree
        $data['min_number_of_minutes_in_exposition_node'] = $request->min_number_of_minutes_in_exposition_node;
        $data['reset_node_after_incorrect_attempt'] = $request->reset_node_after_incorrect_attempt;
        $data['number_of_successful_paths_for_a_reset'] = $request->number_of_successful_paths_for_a_reset;

        //end learning tree

        $data['default_points_per_question'] = $this->getDefaultPointsPerQuestion($data);
        $data['total_points'] = $this->getTotalAssignmentPoints($data);

        $data['default_completion_scoring_mode'] = Helper::getCompletionScoringMode($request->scoring_type, $request->default_completion_scoring_mode, $request->completion_split_auto_graded_percentage);
        return $data;
    }

    public
    function addAssignTos(Assignment $assignment, array $assign_tos, Section $section, User $user)
    {


        $assign_to_timings = AssignToTiming::where('assignment_id', $assignment->id)->get();
        if ($assign_to_timings->isNotEmpty()) {
            //remove the old ones
            foreach ($assign_to_timings as $assign_to_timing) {
                AssignToGroup::where('assign_to_timing_id', $assign_to_timing->id)->delete();
                AssignToUser::where('assign_to_timing_id', $assign_to_timing->id)->delete();
                $assign_to_timing->delete();
            }
        }

        $assign_to_timings = [];

        foreach ($assign_tos as $assign_to) {
            $assignToTiming = new AssignToTiming();
            $assignToTiming->assignment_id = $assignment->id;
            $assignToTiming->available_from = $this->formatDateFromRequest($assign_to['available_from_date'], $assign_to['available_from_time'], $user);
            $assignToTiming->due = $this->formatDateFromRequest($assign_to['due_date'], $assign_to['due_time'], $user);
            $assignToTiming->final_submission_deadline = $assignment->late_policy !== 'not accepted'
                ? $this->formatDateFromRequest($assign_to['final_submission_deadline_date'], $assign_to['final_submission_deadline_time'], $user)
                : null;
            $assignToTiming->save();
            $assign_to_timings[] = $assignToTiming->id;
        }
        $assigned_users = [];
        $enrolled_users_by_course = $assignment->course->enrolledUsersWithFakeStudent->pluck('id')->toArray();
        foreach ($assign_tos as $key => $assign_to) {

            foreach ($assign_to['groups'] as $group) {
                if (isset($group['value']['user_id'])) {
                    $user_id = $group['value']['user_id'];
                    $this->saveAssignToGroup('user', $user_id, $assign_to_timings[$key]);
                    $this->saveAssignToUser($user_id, $assign_to_timings[$key]);
                    $assigned_users[] = $user_id;
                }
            }
        }

        foreach ($assign_tos as $key => $assign_to) {
            foreach ($assign_to['groups'] as $group) {
                if (isset($group['value']['section_id'])) {
                    $section_id = $group['value']['section_id'];
                    $assign_to_section = Section::find($section_id);
                    $this->saveAssignToGroup('section', $assign_to_section->id, $assign_to_timings[$key]);
                    $enrolled_users_by_section = $assign_to_section->enrolledUsers;
                    foreach ($enrolled_users_by_section as $enrolled_user) {
                        if (!in_array($enrolled_user->id, $assigned_users)) {
                            $this->saveAssignToUser($enrolled_user->id, $assign_to_timings[$key]);
                            $assigned_users[] = $enrolled_user->id;
                        }
                    }
                }
            }
        }

        foreach ($assign_tos as $key => $assign_to) {
            foreach ($assign_to['groups'] as $group) {
                if (isset($group['value']['course_id'])) {
                    $this->saveAssignToGroup('course', $assignment->course->id, $assign_to_timings[$key]);
                    foreach ($enrolled_users_by_course as $enrolled_user_id) {
                        if (!in_array($enrolled_user_id, $assigned_users)) {
                            $this->saveAssignToUser($enrolled_user_id, $assign_to_timings[$key]);
                            $assigned_users[] = $enrolled_user_id;
                        }
                    }
                }
            }
        }

    }

    function saveAssignToUser(int $user_id, int $assign_to_timing_id)
    {
        $assignToUser = new AssignToUser();
        $assignToUser->assign_to_timing_id = $assign_to_timing_id;
        $assignToUser->user_id = $user_id;
        $assignToUser->save();
    }

    function saveAssignToGroup(string $group, int $group_id, int $assign_to_timing_id)
    {
        $assignToGroup = new AssignToGroup();
        $assignToGroup->group_id = $group_id;
        $assignToGroup->group = $group;
        $assignToGroup->assign_to_timing_id = $assign_to_timing_id;
        $assignToGroup->save();
    }

    /**
     * @param $date
     * @param $time
     * @param $user
     * @return string
     */
    public
    function formatDateFromRequest($date, $time, $user): string
    {
        return $this->convertLocalMysqlFormattedDateToUTC("$date $time", $user->time_zone);
    }

}
