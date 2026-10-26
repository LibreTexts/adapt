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
use Illuminate\Support\Str;

/**
 * Editing an assignment question's dynamic rubric: validating the proposed rubric, working out the impact
 * on every graded student (preview) and saving it with every affected score recalculated (apply).
 */
class DynamicRubricEditor
{
    const CONFLICT_MESSAGE = "The rubric was changed by someone else. Please reload the page.";
    const NAMES_HIDDEN_MESSAGE = "Student names are hidden because 'Graders can see student names' is turned off for this assignment.";
    const HAS_DYNAMIC_SCORES_MESSAGE = "Students have already been graded with this dynamic rubric, so this would wipe out their rubric scores. Change it with Edit Rubric instead: edits recalculate their scores, and choosing Static converts them to static scoring after showing you a preview.";

    /**
     * Whether any student has been graded with a dynamic rubric on this assignment question.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @return bool
     */
    public static function hasDynamicScores(int $assignment_id, int $question_id): bool
    {
        return DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->whereNotNull('applied_item_ids')
            ->exists();
    }

    /**
     * True when the rubric now in effect is dynamic and students have been graded with it.
     * Used to stop the static-rubric actions (edit/delete override, switch rubric source) that delete every breakdown.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    public static function dynamicRubricHasScores(Assignment $assignment, Question $question): bool
    {
        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first();
        return DynamicRubric::isDynamic(RubricHelper::assignmentQuestionRubric($assignment_question, $question->rubric))
            && self::hasDynamicScores($assignment->id, $question->id);
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param bool $lock_for_update
     * @return array ['assignment_question' => object|null, 'rubric' => array|null, 'is_dynamic' => bool, 'version' => int]
     */
    public static function currentState(Assignment $assignment, Question $question, bool $lock_for_update = false): array
    {
        $query = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id);
        if ($lock_for_update) {
            $query = $query->lockForUpdate();
        }
        $assignment_question = $query->first();
        $rubric = DynamicRubric::decode(RubricHelper::assignmentQuestionRubric($assignment_question, $question->rubric));
        $is_dynamic = DynamicRubric::isDynamic($rubric);
        return ['assignment_question' => $assignment_question,
            'rubric' => $rubric,
            'is_dynamic' => $is_dynamic,
            'version' => $is_dynamic ? DynamicRubric::version($rubric) : 0];
    }

    /**
     * Validates the proposed rubric and builds the new rubric JSON (new items get ids, version goes up by 1).
     *
     * @param array $input rubric_items: [{id?, title, description?, points}], rubric_shown, base_version
     * @param array $state from currentState()
     * @param int $assignment_id
     * @param int $question_id
     * @return array ['type' => 'success', 'rubric' => array] or ['type' => 'error', 'message' => string, 'errors' => array]
     */
    public static function buildProposedRubric(array $input, array $state, int $assignment_id, int $question_id): array
    {
        if (!$state['assignment_question']) {
            return ['type' => 'error', 'message' => 'That question is not in this assignment.', 'errors' => []];
        }
        if (!$state['is_dynamic']
            && DB::table('rubric_points_breakdowns')
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->exists()) {
            return ['type' => 'error',
                'message' => "Students already have static rubric scores for this question, so it needs to be converted to dynamic scoring. Please reload the page and save it again to see the conversion preview.",
                'errors' => []];
        }
        if (!isset($input['base_version']) || filter_var($input['base_version'], FILTER_VALIDATE_INT) === false
            || (int)$input['base_version'] !== $state['version']) {
            return ['type' => 'error', 'message' => self::CONFLICT_MESSAGE, 'errors' => [], 'conflict' => true];
        }
        $proposed_items = $input['rubric_items'] ?? null;
        if (!is_array($proposed_items) || !count($proposed_items)) {
            return ['type' => 'error', 'message' => 'Add at least one rubric item.', 'errors' => []];
        }

        $current_item_ids = $state['is_dynamic'] ? DynamicRubric::itemIds($state['rubric']) : [];
        $errors = [];
        //per criterion and field, shaped like IsValidRubricItems so the editor can show each error under its field
        $item_errors = [];
        $has_field_errors = false;
        $general_errors = [];
        $titles = [];
        $used_ids = [];
        $new_items = [];
        foreach (array_values($proposed_items) as $index => $item) {
            $item_number = $index + 1;
            $item_errors[$index] = ['title' => 'passes', 'description' => 'passes', 'points' => 'passes'];
            $title = is_array($item) && isset($item['title']) ? trim((string)$item['title']) : '';
            $description = is_array($item) && isset($item['description']) ? trim((string)$item['description']) : '';
            $points = is_array($item) ? ($item['points'] ?? null) : null;
            //ids only mean something when the current rubric is dynamic; ids left on a static rubric are ignored
            $id = $state['is_dynamic'] && is_array($item) && isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : null;

            $title_error = '';
            if ($title === '') {
                $title_error = 'A title is required.';
            } else if (mb_strlen($title) > 255) {
                $title_error = 'The title can be at most 255 characters.';
            } else if (in_array(mb_strtolower($title), $titles)) {
                $title_error = 'This title already exists.';
            }
            $titles[] = mb_strtolower($title);
            $description_error = mb_strlen($description) > 5000 ? 'The description can be at most 5000 characters.' : '';
            $points_error = $points === null || $points === '' || !is_numeric($points) || (float)$points < 0 || (float)$points > 10000
                ? 'The points should be a number that is at least 0.'
                : '';
            foreach (['title' => $title_error, 'description' => $description_error, 'points' => $points_error] as $field => $field_error) {
                if ($field_error) {
                    $item_errors[$index][$field] = $field_error;
                    $has_field_errors = true;
                    $errors[] = "Item $item_number: " . lcfirst($field_error);
                }
            }
            if ($id !== null && (!in_array($id, $current_item_ids, true) || in_array($id, $used_ids, true))) {
                $general_errors[] = "Item $item_number no longer exists in the rubric. Please reload the page.";
                $errors[] = end($general_errors);
            }
            if ($id !== null) {
                $used_ids[] = $id;
            }
            $new_items[] = ['id' => $id,
                'title' => $title,
                'description' => $description,
                'points' => is_numeric($points) ? round((float)$points, 2) : 0];
        }
        if ($errors) {
            return ['type' => 'error',
                'message' => 'Please fix the rubric items.',
                'errors' => $errors,
                'item_errors' => $has_field_errors ? $item_errors : null,
                'general_errors' => $general_errors];
        }
        foreach ($new_items as $key => $item) {
            if ($item['id'] === null) {
                do {
                    $new_id = Str::lower(Str::random(8));
                } while (in_array($new_id, $used_ids, true) || in_array($new_id, $current_item_ids, true));
                $used_ids[] = $new_id;
                $new_items[$key]['id'] = $new_id;
            }
        }
        $rubric_shown = array_key_exists('rubric_shown', $input)
            ? (bool)$input['rubric_shown']
            : (bool)($state['rubric']['rubric_shown'] ?? false);
        return ['type' => 'success',
            'rubric' => ['scoring_style' => 'dynamic',
                'version' => self::nextVersion($assignment_id, $question_id, $state),
                'rubric_shown' => $rubric_shown,
                'rubric_items' => $new_items]];
    }

    /**
     * The version for the next dynamic rubric on this assignment question. It continues from the highest version the
     * question has had here, so a rubric that goes dynamic -> static -> dynamic never reuses a version number.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param array $state from currentState()
     * @return int
     */
    public static function nextVersion(int $assignment_id, int $question_id, array $state): int
    {
        $highest = (int)$state['version'];
        $logged_rubrics = DB::table('rubric_change_logs')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->pluck('after');
        foreach ($logged_rubrics as $logged_rubric) {
            $highest = max($highest, (int)(json_decode($logged_rubric, true)['version'] ?? 0));
        }
        return $highest + 1;
    }

    /**
     * Works out what the proposed rubric does to every student graded with the current one. Writes nothing.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param array $state from currentState()
     * @param array $new_rubric
     * @param User $viewer used to decide whether student names are shown
     * @return array
     */
    public static function impact(Assignment $assignment, Question $question, array $state, array $new_rubric, User $viewer): array
    {
        $assignment_question = $state['assignment_question'];
        $current_rubric = $state['is_dynamic'] ? $state['rubric'] : ['rubric_items' => []];
        $new_item_ids = DynamicRubric::itemIds($new_rubric);
        $question_points = (float)$assignment_question->points;

        $breakdowns = DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->whereNotNull('applied_item_ids')
            ->get();
        $user_ids = $breakdowns->pluck('user_id')->toArray();
        $users_by_id = User::whereIn('id', $user_ids)->get()->keyBy('id');
        $auto_graded_scores_by_user_id = DB::table('submissions')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->whereIn('user_id', $user_ids)
            ->pluck('score', 'user_id')
            ->toArray();
        $submission_files_by_user_id = DB::table('submission_files')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->where('type', '<>', 'a')
            ->whereIn('user_id', $user_ids)
            ->get()
            ->keyBy('user_id');
        $override_user_ids = DB::table('submission_score_overrides')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->whereIn('user_id', $user_ids)
            ->pluck('user_id')
            ->toArray();

        $students = [];
        $deleted_item_counts = [];
        foreach ($current_rubric['rubric_items'] ?? [] as $item) {
            if (isset($item['id']) && !in_array((string)$item['id'], $new_item_ids, true)) {
                $deleted_item_counts[(string)$item['id']] = ['id' => (string)$item['id'], 'title' => $item['title'] ?? '', 'students' => 0];
            }
        }
        foreach ($breakdowns as $breakdown) {
            $applied_item_ids = array_map('strval', json_decode($breakdown->applied_item_ids, true) ?: []);
            foreach ($applied_item_ids as $applied_item_id) {
                if (isset($deleted_item_counts[$applied_item_id])) {
                    $deleted_item_counts[$applied_item_id]['students']++;
                }
            }
            $new_applied_item_ids = array_values(array_intersect($applied_item_ids, $new_item_ids));
            $auto_graded_score = (float)($auto_graded_scores_by_user_id[$breakdown->user_id] ?? 0);
            $submission_file = $submission_files_by_user_id[$breakdown->user_id] ?? null;
            $old_rubric_score = $submission_file && $submission_file->score !== null ? round((float)$submission_file->score, 2) : 0.0;
            $late_penalty_percent = $submission_file ? $submission_file->applied_late_penalty : null;
            $new_rubric_score = DynamicRubric::score($new_rubric,
                $new_applied_item_ids,
                $breakdown->adjustment,
                DynamicRubric::cap($question_points, $auto_graded_score),
                $late_penalty_percent)['final'];
            $user = $users_by_id[$breakdown->user_id] ?? null;
            $has_override = in_array($breakdown->user_id, $override_user_ids);
            $students[] = [
                'user_id' => (int)$breakdown->user_id,
                'name' => $user ? "$user->first_name $user->last_name" : '',
                'email' => $user ? $user->email : null,
                'first_name' => $user ? $user->first_name : '',
                'is_fake' => $user ? (bool)$user->fake_student : false,
                'has_override' => $has_override,
                'applied_item_ids' => $applied_item_ids,
                'new_applied_item_ids' => $new_applied_item_ids,
                'old_rubric_score' => $old_rubric_score,
                'new_rubric_score' => $has_override ? $old_rubric_score : $new_rubric_score,
                'old_question_score' => round($auto_graded_score + $old_rubric_score, 2),
                'new_question_score' => round($auto_graded_score + ($has_override ? $old_rubric_score : $new_rubric_score), 2),
                'changed' => !$has_override && abs($new_rubric_score - $old_rubric_score) >= 0.005];
        }

        $names_hidden = (int)$viewer->role === 4 && !$assignment->graders_can_see_student_names;
        $changed = array_values(array_filter($students, function ($student) {
            return $student['changed'] && !$student['is_fake'];
        }));
        usort($changed, function ($a, $b) {
            return abs($b['new_question_score'] - $b['old_question_score']) <=> abs($a['new_question_score'] - $a['old_question_score']);
        });
        $overrides = array_values(array_filter($students, function ($student) {
            return $student['has_override'] && !$student['is_fake'];
        }));
        $label_number = 0;
        $public_student = function ($student) use ($names_hidden, &$label_number, $question_points) {
            $label_number++;
            return ['user_id' => $names_hidden ? null : $student['user_id'],
                'name' => $names_hidden ? "Student $label_number" : $student['name'],
                'old_score' => $student['old_question_score'],
                'new_score' => $student['new_question_score'],
                'question_points' => $question_points];
        };
        $students_changed = array_map($public_student, $changed);
        $students_not_affected = array_map($public_student, $overrides);

        return ['students' => $students, //full detail, used by apply(); never returned to the browser
            'preview' => [
                'students_changed' => $students_changed,
                'students_not_affected_override' => $students_not_affected,
                'deleted_items' => array_values($deleted_item_counts),
                'number_of_students_rescored' => count($students_changed),
                'question_points' => $question_points,
                'scores_released' => (bool)$assignment->show_scores,
                'names_hidden' => $names_hidden,
                'names_hidden_message' => $names_hidden ? self::NAMES_HIDDEN_MESSAGE : '']];
    }

    /**
     * Saves the new rubric and recalculates every affected student, all in one transaction.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param array $input
     * @param User $editor
     * @param bool $email_students
     * @param string $base_url used for the link in the student email
     * @return array
     * @throws Exception
     */
    public static function apply(Assignment $assignment,
                                 Question   $question,
                                 array      $input,
                                 User       $editor,
                                 bool       $email_students,
                                 string     $base_url): array
    {
        DB::beginTransaction();
        try {
            $state = self::currentState($assignment, $question, true);
            $proposed = self::buildProposedRubric($input, $state, $assignment->id, $question->id);
            if ($proposed['type'] !== 'success') {
                DB::rollback();
                return $proposed;
            }
            $new_rubric = $proposed['rubric'];
            if ($state['is_dynamic']
                && $state['rubric']['rubric_items'] == array_values($new_rubric['rubric_items'])
                && (bool)($state['rubric']['rubric_shown'] ?? false) === $new_rubric['rubric_shown']) {
                DB::rollback();
                return ['type' => 'info', 'message' => 'There are no changes to save.'];
            }
            $impact = self::impact($assignment, $question, $state, $new_rubric, $editor);

            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_rubric' => json_encode($new_rubric), 'use_existing_rubric' => 0]);

            $score = new Score();
            $number_rescored = 0;
            foreach ($impact['students'] as $student) {
                DB::table('rubric_points_breakdowns')
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->where('user_id', $student['user_id'])
                    ->update(['applied_item_ids' => json_encode($student['new_applied_item_ids']),
                        'rubric_version' => $new_rubric['version'],
                        'updated_at' => Carbon::now()]);
                if (!$student['changed']) {
                    continue;
                }
                DB::table('submission_files')
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->where('user_id', $student['user_id'])
                    ->where('type', '<>', 'a')
                    ->update(['score' => $student['new_rubric_score'], 'updated_at' => Carbon::now()]);
                $score->updateAssignmentScore($student['user_id'], $assignment->id, false);
                DB::table('rubric_score_logs')->insert([
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'student_user_id' => $student['user_id'],
                    'grader_id' => $editor->id,
                    'before' => json_encode(['applied_item_ids' => $student['applied_item_ids'],
                        'score' => $student['old_rubric_score']]),
                    'after' => json_encode(['applied_item_ids' => $student['new_applied_item_ids'],
                        'score' => $student['new_rubric_score']]),
                    'reason' => 'rescored_by_rubric_edit',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()]);
                if (!$student['is_fake']) {
                    $number_rescored++;
                }
            }
            DB::table('rubric_change_logs')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => $editor->id,
                'action' => 'edit',
                'before' => $state['is_dynamic'] ? json_encode($state['rubric']) : null,
                'after' => json_encode($new_rubric),
                'students_rescored' => $number_rescored,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()]);
            $lms = (bool)$assignment->course->lms;
            if ($lms && $number_rescored) {
                //same bulk path as Score::handleUpdateScores: passback:manualPendingScores sends the new totals
                DB::table('passback_by_assignments')->insert([
                    'assignment_id' => $assignment->id,
                    'status' => 'manual_pending',
                    'created_at' => now(),
                    'updated_at' => now()]);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }

        $emailed = 0;
        if ($email_students && $assignment->show_scores) {
            $emailed = self::emailStudents($assignment, $question, $state['assignment_question'], $impact['students'], $base_url);
        }
        self::publishRubricUpdated($assignment->id, $question->id, $new_rubric['version']);

        $message = 'The rubric has been updated.';
        if ($number_rescored) {
            $message .= $number_rescored === 1
                ? ' 1 student\'s score was recalculated.'
                : " $number_rescored students' scores were recalculated.";
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
            'number_of_students_rescored' => $number_rescored,
            'number_of_students_emailed' => $emailed];
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param object $assignment_question
     * @param array $students
     * @param string $base_url
     * @return int number of emails queued
     */
    private static function emailStudents(Assignment $assignment, Question $question, $assignment_question, array $students, string $base_url): int
    {
        $question_title = $assignment_question->custom_question_title
            ?: ($question->title ?: "Question #$assignment_question->order");
        $link = rtrim($base_url, '/') . "/students/assignments/$assignment->id/summary";
        $question_points = Helper::removeZerosAfterDecimal(round((float)$assignment_question->points, 2));
        $emailed = 0;
        foreach ($students as $student) {
            if (!$student['changed'] || $student['is_fake'] || !$student['email']) {
                continue;
            }
            Mail::to($student['email'])->queue(new RubricScoreChanged(
                $student['first_name'],
                $assignment->name,
                $question_title,
                (string)Helper::removeZerosAfterDecimal($student['old_question_score']),
                (string)Helper::removeZerosAfterDecimal($student['new_question_score']),
                (string)$question_points,
                $link));
            $emailed++;
        }
        return $emailed;
    }

    /**
     * Tells open Open Grader pages that the rubric changed. Carries no student data (see design §6.3).
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param int $version
     * @return void
     */
    public static function publishRubricUpdated(int $assignment_id, int $question_id, int $version)
    {
        if (app()->environment() === 'testing') {
            return;
        }
        try {
            Helper::centrifuge()->publish("rubric-updated-$assignment_id-$question_id", [
                'assignment_id' => $assignment_id,
                'question_id' => $question_id,
                'version' => $version]);
        } catch (Exception $e) {
            //the rubric is already saved; a missed live update only means other graders see it on their next save
            $h = new \App\Exceptions\Handler(app());
            $h->report($e);
        }
    }
}
