<?php

namespace App\Helpers;

use App\Assignment;
use App\Mail\RubricScoreChanged;
use App\Question;
use App\Score;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Deleting the overriding rubric, or switching between the question's rubric and the overriding rubric, when students
 * have been graded with the dynamic rubric in effect. Their rubric results can't carry over, so with the instructor's
 * confirmation the rubric results are removed and their open-ended scores are cleared (ungraded; feedback kept).
 * Students with a score override keep it.
 */
class DynamicRubricRemover
{
    const ONLY_INSTRUCTORS_MESSAGE = "Only instructors can delete or switch a rubric that students have been graded with.";
    const CHANGED_MESSAGE = "Students' rubric scores changed since this was opened. Please try again.";
    const ACTIONS = ['delete_override', 'switch_rubric'];

    /**
     * Whether this rubric needs the "clear scores" route, and how many students it would clear.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     */
    public static function preview(Assignment $assignment, Question $question): array
    {
        $state = DynamicRubricEditor::currentState($assignment, $question);
        if (!$state['assignment_question']) {
            return ['type' => 'error', 'message' => 'That question is not in this assignment.'];
        }
        $required = $state['is_dynamic'] && DynamicRubricEditor::hasDynamicScores($assignment->id, $question->id);
        if (!$required) {
            return ['type' => 'success', 'required' => false];
        }
        $plan = self::plan($assignment, $question, false);
        return ['type' => 'success',
            'required' => true,
            'number_to_clear' => $plan['number_to_clear'],
            'scores_released' => (bool)$assignment->show_scores];
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param bool $lock_for_update
     * @return array ['students' => [...], 'number_to_clear' => int]
     */
    private static function plan(Assignment $assignment, Question $question, bool $lock_for_update): array
    {
        $query = DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id);
        if ($lock_for_update) {
            $query = $query->lockForUpdate();
        }
        $breakdowns = $query->get();
        $user_ids = $breakdowns->pluck('user_id')->toArray();
        $users_by_id = User::whereIn('id', $user_ids)->get()->keyBy('id');
        $submission_files_by_user_id = DB::table('submission_files')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->where('type', '<>', 'a')
            ->whereIn('user_id', $user_ids)
            ->get()
            ->keyBy('user_id');
        $auto_graded_scores_by_user_id = DB::table('submissions')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->whereIn('user_id', $user_ids)
            ->pluck('score', 'user_id')
            ->toArray();
        $override_user_ids = DB::table('submission_score_overrides')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->whereIn('user_id', $user_ids)
            ->pluck('user_id')
            ->toArray();
        $students = [];
        $number_to_clear = 0;
        foreach ($breakdowns as $breakdown) {
            $user = $users_by_id[$breakdown->user_id] ?? null;
            $submission_file = $submission_files_by_user_id[$breakdown->user_id] ?? null;
            $clear_score = $submission_file && $submission_file->score !== null
                && !in_array($breakdown->user_id, $override_user_ids);
            $is_fake = $user ? (bool)$user->fake_student : false;
            if ($clear_score && !$is_fake) {
                $number_to_clear++;
            }
            $old_rubric_score = $submission_file && $submission_file->score !== null ? round((float)$submission_file->score, 2) : null;
            $students[] = ['user_id' => (int)$breakdown->user_id,
                'email' => $user ? $user->email : null,
                'first_name' => $user ? $user->first_name : '',
                'is_fake' => $is_fake,
                'clear_score' => $clear_score,
                'applied_item_ids' => json_decode($breakdown->applied_item_ids ?? '[]', true) ?: [],
                'adjustment' => (float)$breakdown->adjustment,
                'old_rubric_score' => $old_rubric_score,
                'old_question_score' => round((float)($auto_graded_scores_by_user_id[$breakdown->user_id] ?? 0) + (float)$old_rubric_score, 2)];
        }
        return ['students' => $students, 'number_to_clear' => $number_to_clear];
    }

    /**
     * Removes the rubric results, clears the scores and then deletes the override or switches the rubric.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param string $action delete_override|switch_rubric
     * @param int $expected_number_to_clear what the instructor confirmed
     * @param User $editor
     * @param bool $email_students
     * @param string $base_url
     * @return array
     * @throws Exception
     */
    public static function apply(Assignment $assignment,
                                 Question   $question,
                                 string     $action,
                                 int        $expected_number_to_clear,
                                 User       $editor,
                                 bool       $email_students,
                                 string     $base_url): array
    {
        DB::beginTransaction();
        try {
            $state = DynamicRubricEditor::currentState($assignment, $question, true);
            if (!$state['assignment_question']) {
                DB::rollback();
                return ['type' => 'error', 'message' => 'That question is not in this assignment.'];
            }
            if ($action === 'delete_override' && !$state['assignment_question']->custom_rubric) {
                DB::rollback();
                return ['type' => 'error', 'message' => 'This question has no overriding rubric to delete. Please reload the page.'];
            }
            $plan = self::plan($assignment, $question, true);
            if ($plan['number_to_clear'] !== $expected_number_to_clear) {
                DB::rollback();
                return ['type' => 'error', 'message' => self::CHANGED_MESSAGE];
            }
            $published_version = DynamicRubricEditor::nextVersion($assignment->id, $question->id, $state);
            $now = Carbon::now();
            $score = new Score();
            $cleared = [];
            foreach ($plan['students'] as $student) {
                if ($student['clear_score']) {
                    DB::table('submission_files')
                        ->where('assignment_id', $assignment->id)
                        ->where('question_id', $question->id)
                        ->where('user_id', $student['user_id'])
                        ->where('type', '<>', 'a')
                        ->update(['score' => null, 'date_graded' => null, 'grader_id' => null, 'updated_at' => $now]);
                    $score->updateAssignmentScore($student['user_id'], $assignment->id, false);
                    if (!$student['is_fake']) {
                        $cleared[] = $student;
                    }
                }
                DB::table('rubric_score_logs')->insert([
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'student_user_id' => $student['user_id'],
                    'grader_id' => $editor->id,
                    'before' => json_encode(['applied_item_ids' => $student['applied_item_ids'],
                        'adjustment' => $student['adjustment'],
                        'score' => $student['old_rubric_score']]),
                    'after' => json_encode(['cleared' => $student['clear_score'], 'score' => $student['clear_score'] ? null : $student['old_rubric_score']]),
                    'reason' => $action === 'delete_override' ? 'cleared_override_deleted' : 'cleared_rubric_switched',
                    'created_at' => $now,
                    'updated_at' => $now]);
            }
            DB::table('rubric_points_breakdowns')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->delete();

            $use_existing_rubric = $state['assignment_question']->use_existing_rubric;
            if ($action === 'delete_override') {
                $update = ['custom_rubric' => null, 'use_existing_rubric' => 1];
                $switched_to = 'question';
            } else {
                //the switch toggles between the question's rubric and the overriding rubric, as before
                $new_use_existing_rubric = ($use_existing_rubric === null || (int)$use_existing_rubric === 1) ? 0 : 1;
                $update = ['use_existing_rubric' => $new_use_existing_rubric];
                $switched_to = $new_use_existing_rubric ? 'question' : 'override';
            }
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update($update);
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $new_rubric = RubricHelper::assignmentQuestionRubric($assignment_question, $question->rubric);
            DB::table('rubric_change_logs')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => $editor->id,
                'action' => $action === 'delete_override' ? 'delete_override_clear' : 'switch_rubric_clear',
                'before' => json_encode($state['rubric']),
                'after' => json_encode(array_merge(DynamicRubric::decode($new_rubric) ?: ['rubric_items' => []],
                    ['version' => $published_version, 'switched_to' => $switched_to])),
                'students_rescored' => count($cleared),
                'created_at' => $now,
                'updated_at' => $now]);
            $lms = (bool)$assignment->course->lms;
            if ($lms && $cleared) {
                DB::table('passback_by_assignments')->insert([
                    'assignment_id' => $assignment->id,
                    'status' => 'manual_pending',
                    'created_at' => $now,
                    'updated_at' => $now]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        $emailed = 0;
        if ($email_students && $assignment->show_scores && $cleared) {
            $question_title = $assignment_question->custom_question_title
                ?: ($question->title ?: "Question #$assignment_question->order");
            $link = rtrim($base_url, '/') . "/students/assignments/$assignment->id/summary";
            $question_points = Helper::removeZerosAfterDecimal(round((float)$assignment_question->points, 2));
            foreach ($cleared as $student) {
                if (!$student['email']) {
                    continue;
                }
                Mail::to($student['email'])->queue(new RubricScoreChanged(
                    $student['first_name'],
                    $assignment->name,
                    $question_title,
                    (string)Helper::removeZerosAfterDecimal($student['old_question_score']),
                    '',
                    (string)$question_points,
                    $link,
                    true));
                $emailed++;
            }
        }
        DynamicRubricEditor::publishRubricUpdated($assignment->id, $question->id, $published_version);

        $message = $action === 'delete_override'
            ? 'The overriding rubric has been deleted.'
            : ($switched_to === 'question'
                ? "This question now uses the question's rubric."
                : 'This question now uses the overriding rubric.');
        if ($cleared) {
            $message .= count($cleared) === 1
                ? " 1 student's score was cleared so they can be regraded."
                : ' ' . count($cleared) . " students' scores were cleared so they can be regraded.";
            if ($lms) {
                $message .= ' Please allow 5-10 minutes for the new scores to appear in your LMS.';
            }
        }
        if ($emailed) {
            $message .= $emailed === 1 ? ' 1 student was emailed.' : " $emailed students were emailed.";
        }
        return ['type' => 'success',
            'message' => $message,
            'rubric' => $new_rubric,
            'use_existing_rubric' => (bool)(int)$assignment_question->use_existing_rubric,
            'overriding_rubric' => (bool)$assignment_question->custom_rubric,
            'number_of_students_cleared' => count($cleared),
            'number_of_students_emailed' => $emailed];
    }
}
