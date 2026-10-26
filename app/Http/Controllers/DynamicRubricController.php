<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Course;
use App\Exceptions\Handler;
use App\Helpers\DynamicRubricConverter;
use App\Helpers\DynamicRubricEditor;
use App\Helpers\DynamicRubricRemover;
use App\Helpers\RubricEditPermission;
use App\Helpers\StaticRubricConverter;
use App\Question;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dynamic rubrics: editing with an impact preview, applying (rescoring), history and the "Can edit rubric" permission.
 */
class DynamicRubricController extends Controller
{
    /**
     * The rubric in effect, and whether the current user may edit it.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function show(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        try {
            if (!RubricEditPermission::canGradeAssignment($request->user(), $assignment)) {
                $response['message'] = 'You are not allowed to view the rubric for this assignment.';
                return $response;
            }
            $state = DynamicRubricEditor::currentState($assignment, $question);
            if (!$state['assignment_question']) {
                $response['message'] = 'That question is not in this assignment.';
                return $response;
            }
            $permission = RubricEditPermission::check($request->user(), $assignment);
            $response['rubric'] = $state['rubric'];
            $response['is_dynamic'] = $state['is_dynamic'];
            $response['version'] = $state['version'];
            $response['can_edit'] = $permission['allowed'];
            $response['can_edit_message'] = $permission['message'];
            //the History link stays once a rubric has been converted back to static
            $response['has_history'] = DB::table('rubric_change_logs')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->exists();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the rubric.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * What the proposed rubric would do to graded students. Saves nothing.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function preview(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        $permission = RubricEditPermission::check($request->user(), $assignment);
        if (!$permission['allowed']) {
            $response['message'] = $permission['message'];
            return $response;
        }
        $settings_error = RubricEditPermission::graderSettingsError($request->user(), $assignment,
            DynamicRubricEditor::currentState($assignment, $question), true,
            $request->has('rubric_shown') ? (bool)$request->rubric_shown : null);
        if ($settings_error) {
            $response['message'] = $settings_error;
            return $response;
        }
        try {
            $state = DynamicRubricEditor::currentState($assignment, $question);
            $proposed = DynamicRubricEditor::buildProposedRubric($request->all(), $state, $assignment->id, $question->id);
            if ($proposed['type'] !== 'success') {
                return $proposed;
            }
            $impact = DynamicRubricEditor::impact($assignment, $question, $state, $proposed['rubric'], $request->user());
            $response['preview'] = $impact['preview'];
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error previewing the rubric changes.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Saves the rubric and recalculates every affected student's score.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function update(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        $permission = RubricEditPermission::check($request->user(), $assignment);
        if (!$permission['allowed']) {
            $response['message'] = $permission['message'];
            return $response;
        }
        $settings_error = RubricEditPermission::graderSettingsError($request->user(), $assignment,
            DynamicRubricEditor::currentState($assignment, $question), true,
            $request->has('rubric_shown') ? (bool)$request->rubric_shown : null);
        if ($settings_error) {
            $response['message'] = $settings_error;
            return $response;
        }
        try {
            return DynamicRubricEditor::apply($assignment,
                $question,
                $request->all(),
                $request->user(),
                (bool)$request->email_students,
                $request->getSchemeAndHttpHost());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save the rubric, and no scores were changed.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * What converting the static rubric to dynamic would do to students with static rubric scores. Saves nothing.
     * Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function convertPreview(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = DynamicRubricConverter::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        try {
            return DynamicRubricConverter::preview($assignment, $question);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error previewing the conversion.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Converts the static rubric to dynamic. Students whose score would change keep it (flagged "Needs review")
     * or are cleared, as the instructor chooses. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function convert(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = DynamicRubricConverter::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        if (!in_array($request->choice, ['keep', 'clear'], true)) {
            $response['message'] = "Please choose whether to keep or clear the scores that would change.";
            return $response;
        }
        if (!is_string($request->fingerprint) || $request->fingerprint === '') {
            $response['message'] = DynamicRubricConverter::CHANGED_MESSAGE;
            return $response;
        }
        try {
            return DynamicRubricConverter::apply($assignment,
                $question,
                $request->choice,
                $request->fingerprint,
                $request->user(),
                (bool)$request->email_students,
                $request->getSchemeAndHttpHost());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to convert the rubric, and no scores were changed.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * What converting the dynamic rubric back to static would do to graded students. Saves nothing. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function convertToStaticPreview(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = StaticRubricConverter::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        try {
            return StaticRubricConverter::preview($assignment, $question);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error previewing the conversion.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Converts the dynamic rubric back to static. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function convertToStatic(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = StaticRubricConverter::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        if (!in_array($request->choice, ['keep', 'clear'], true)) {
            $response['message'] = "Please choose whether to keep or clear the scores that would change.";
            return $response;
        }
        if (!is_string($request->fingerprint) || $request->fingerprint === '') {
            $response['message'] = StaticRubricConverter::CHANGED_MESSAGE;
            return $response;
        }
        try {
            return StaticRubricConverter::apply($assignment,
                $question,
                $request->choice,
                $request->fingerprint,
                $request->user(),
                (bool)$request->email_students,
                $request->getSchemeAndHttpHost());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to convert the rubric, and no scores were changed.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Whether deleting the override or switching the rubric would wipe out dynamic rubric scores, and how many
     * students' scores it would clear. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function removeWithScoresPreview(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = DynamicRubricRemover::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        try {
            return DynamicRubricRemover::preview($assignment, $question);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error checking the students' rubric scores.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Deletes the overriding rubric or switches the rubric, clearing the scores of students graded with the dynamic
     * rubric (confirmed by the instructor). Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function removeWithScores(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = DynamicRubricRemover::ONLY_INSTRUCTORS_MESSAGE;
            return $response;
        }
        if (!in_array($request->action, DynamicRubricRemover::ACTIONS, true)
            || filter_var($request->expected_number_to_clear, FILTER_VALIDATE_INT) === false
            || !$request->confirmed) {
            $response['message'] = "Please confirm that you understand the students' scores will be cleared.";
            return $response;
        }
        try {
            return DynamicRubricRemover::apply($assignment,
                $question,
                $request->action,
                (int)$request->expected_number_to_clear,
                $request->user(),
                (bool)$request->email_students,
                $request->getSchemeAndHttpHost());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to change the rubric, and no scores were changed.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Who last changed the rubric and when. Used by the "rubric changed, reload" prompt.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function latestChange(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!RubricEditPermission::canGradeAssignment($request->user(), $assignment)) {
            $response['message'] = 'You are not allowed to view the rubric changes for this assignment.';
            return $response;
        }
        try {
            $latest_change = DB::table('rubric_change_logs')
                ->join('users', 'rubric_change_logs.user_id', '=', 'users.id')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->orderBy('rubric_change_logs.id', 'desc')
                ->select('users.first_name', 'users.last_name', 'rubric_change_logs.created_at', 'rubric_change_logs.after')
                ->first();
            $response['latest_change'] = $latest_change
                ? ['name' => "$latest_change->first_name $latest_change->last_name",
                    'time' => Carbon::parse($latest_change->created_at)->setTimezone($request->user()->time_zone)->format('g:i A'),
                    'version' => (int)(json_decode($latest_change->after, true)['version'] ?? 0)]
                : null;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the latest rubric change.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Rubric edit history. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function history(Request $request, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = 'Only instructors can view the rubric history.';
            return $response;
        }
        try {
            $response['history'] = DB::table('rubric_change_logs')
                ->join('users', 'rubric_change_logs.user_id', '=', 'users.id')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->orderBy('rubric_change_logs.id', 'desc')
                ->select('rubric_change_logs.id',
                    'rubric_change_logs.action',
                    'rubric_change_logs.before',
                    'rubric_change_logs.after',
                    'rubric_change_logs.students_rescored',
                    'rubric_change_logs.created_at',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS name"))
                ->get()
                ->map(function ($row) use ($request) {
                    return ['id' => $row->id,
                        'action' => $row->action,
                        'name' => $row->name,
                        'before' => json_decode($row->before, true),
                        'after' => json_decode($row->after, true),
                        'students_rescored' => (int)$row->students_rescored,
                        'changed_at' => Carbon::parse($row->created_at)->setTimezone($request->user()->time_zone)->format('F d, Y \a\t g:i A')];
                });
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the rubric history.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * One student's dynamic rubric score history. Instructors only.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function studentHistory(Request $request, Assignment $assignment, Question $question, User $user): array
    {
        $response['type'] = 'error';
        if (!$assignment->course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = 'Only instructors can view the rubric history.';
            return $response;
        }
        try {
            $response['history'] = DB::table('rubric_score_logs')
                ->join('users', 'rubric_score_logs.grader_id', '=', 'users.id')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('student_user_id', $user->id)
                ->orderBy('rubric_score_logs.id', 'desc')
                ->select('rubric_score_logs.before',
                    'rubric_score_logs.after',
                    'rubric_score_logs.reason',
                    'rubric_score_logs.created_at',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS name"))
                ->get()
                ->map(function ($row) use ($request) {
                    return ['name' => $row->name,
                        'reason' => $row->reason,
                        'before' => json_decode($row->before, true),
                        'after' => json_decode($row->after, true),
                        'changed_at' => Carbon::parse($row->created_at)->setTimezone($request->user()->time_zone)->format('F d, Y \a\t g:i A')];
                });
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the student's rubric history.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * The course's graders with their "Can edit rubric" setting. Instructors only.
     *
     * @param Request $request
     * @param Course $course
     * @return array
     * @throws Exception
     */
    public function rubricEditors(Request $request, Course $course): array
    {
        $response['type'] = 'error';
        if (!$course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = "You are not allowed to view who can edit rubrics in this course.";
            return $response;
        }
        try {
            $response['rubric_editor_user_ids'] = DB::table('rubric_editors')
                ->where('course_id', $course->id)
                ->pluck('user_id')
                ->map(function ($user_id) {
                    return (int)$user_id;
                })
                ->toArray();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the rubric editors.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * Turns "Can edit rubric" on or off for a grader. Instructors only.
     *
     * @param Request $request
     * @param Course $course
     * @param User $user
     * @return array
     * @throws Exception
     */
    public function updateRubricEditor(Request $request, Course $course, User $user): array
    {
        $response['type'] = 'error';
        if (!$course->ownsCourseOrIsCoInstructor($request->user()->id)) {
            $response['message'] = "You are not allowed to change who can edit rubrics in this course.";
            return $response;
        }
        if (!RubricEditPermission::isGraderOfCourse($course, $user->id)) {
            $response['message'] = "$user->first_name $user->last_name is not a grader in this course.";
            return $response;
        }
        try {
            if ($request->can_edit) {
                DB::table('rubric_editors')->updateOrInsert(
                    ['course_id' => $course->id, 'user_id' => $user->id],
                    ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                $response['message'] = "$user->first_name $user->last_name can now edit rubrics in this course.";
            } else {
                DB::table('rubric_editors')
                    ->where('course_id', $course->id)
                    ->where('user_id', $user->id)
                    ->delete();
                $response['message'] = "$user->first_name $user->last_name can no longer edit rubrics in this course.";
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the rubric editor.  Please try again or contact us for assistance.";
        }
        return $response;
    }
}
