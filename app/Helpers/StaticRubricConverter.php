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
 * Converting an assignment question's dynamic rubric back to static scoring once students have dynamic scores.
 * Each criterion's points become its static weight (static scales weights to the question's points); a student gets
 * full scaled points on each criterion they had applied and 0 on the rest. Adjustments have no place in a static
 * breakdown. Feedback-only (0-point) criteria are removed, since static rubrics don't allow them. Students whose
 * score would change either keep it (flagged "Needs review") or are cleared, as the instructor chooses.
 */
class StaticRubricConverter
{
    const ONLY_INSTRUCTORS_MESSAGE = "Only instructors can convert a rubric to static scoring.";
    const ALREADY_STATIC_MESSAGE = "This rubric already uses static scoring. Please reload the page.";
    const NO_RUBRIC_MESSAGE = "This question doesn't have a rubric to convert.";
    const ONLY_ZERO_POINT_CRITERIA_MESSAGE = "Every criterion in this rubric is worth 0 points. Static rubrics need at least one criterion with points, so it can't be converted. Give a criterion some points first.";
    const CHANGED_MESSAGE = "Students' rubric scores or the rubric changed since you previewed the conversion. Please preview it again.";

    /**
     * @param array $dynamic_rubric
     * @return array ['rubric' => static rubric, 'kept' => [dynamic item id => static index], 'dropped' => titles]
     */
    public static function staticRubric(array $dynamic_rubric): array
    {
        $items = [];
        $kept = [];
        $dropped = [];
        foreach (array_values($dynamic_rubric['rubric_items'] ?? []) as $item) {
            if ((float)($item['points'] ?? 0) > 0) {
                $kept[(string)$item['id']] = count($items);
                $items[] = ['title' => (string)($item['title'] ?? ''),
                    'description' => (string)($item['description'] ?? ''),
                    'points' => (float)$item['points']];
            } else {
                $dropped[] = (string)($item['title'] ?? '');
            }
        }
        return ['rubric' => ['rubric_shown' => (bool)($dynamic_rubric['rubric_shown'] ?? false), 'rubric_items' => $items],
            'kept' => $kept,
            'dropped' => $dropped];
    }

    /**
     * Each criterion's full points in the static grader: its weight scaled to the points the rubric covers.
     *
     * @param array $static_rubric
     * @param float $rubric_points question points less the auto-graded score
     * @return array
     */
    public static function scaledMaxes(array $static_rubric, float $rubric_points): array
    {
        $total_weight = 0.0;
        foreach ($static_rubric['rubric_items'] as $item) {
            $total_weight += (float)$item['points'];
        }
        return array_map(function ($item) use ($total_weight, $rubric_points) {
            return $total_weight > 0 ? self::roundLikeTheGrader((float)$item['points'] * $rubric_points / $total_weight) : 0.0;
        }, $static_rubric['rubric_items']);
    }

    /**
     * Rounds to 2 decimals exactly as the static Open Grader does (roundToDecimalSigFig in helpers/Math.js:
     * Math.round(num * 100) / 100), so a converted criterion's points never show as over its maximum.
     *
     * @param float $number
     * @return float
     */
    public static function roundLikeTheGrader(float $number): float
    {
        return floor($number * 100 + 0.5) / 100;
    }

    /**
     * @param array $state from DynamicRubricEditor::currentState()
     * @return array ['type' => 'success', 'converted' => staticRubric()] or an error response
     */
    public static function convertible(array $state): array
    {
        if (!$state['assignment_question']) {
            return ['type' => 'error', 'message' => 'That question is not in this assignment.'];
        }
        if (!$state['rubric'] || empty($state['rubric']['rubric_items'])) {
            return ['type' => 'error', 'message' => self::NO_RUBRIC_MESSAGE];
        }
        if (!$state['is_dynamic']) {
            return ['type' => 'error', 'message' => self::ALREADY_STATIC_MESSAGE];
        }
        $converted = self::staticRubric($state['rubric']);
        if (!$converted['rubric']['rubric_items']) {
            return ['type' => 'error', 'message' => self::ONLY_ZERO_POINT_CRITERIA_MESSAGE];
        }
        return ['type' => 'success', 'converted' => $converted];
    }

    /**
     * How every student with a rubric breakdown converts. Saves nothing.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param array $state
     * @param array $converted from staticRubric()
     * @param bool $lock_for_update
     * @return array ['students' => full detail, 'preview' => what the browser sees, 'fingerprint' => string]
     */
    public static function plan(Assignment $assignment, Question $question, array $state, array $converted, bool $lock_for_update = false): array
    {
        $question_points = (float)$state['assignment_question']->points;
        $dynamic_rubric = $state['rubric'];
        $static_rubric = $converted['rubric'];
        $query = DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->orderBy('user_id');
        if ($lock_for_update) {
            $query = $query->lockForUpdate();
        }
        $breakdowns = $query->get();
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
        $fingerprint_data = [$dynamic_rubric, $question_points];
        foreach ($breakdowns as $breakdown) {
            $applied_item_ids = array_map('strval', json_decode($breakdown->applied_item_ids ?? '[]', true) ?: []);
            $adjustment = round((float)$breakdown->adjustment, 2);
            $auto_graded_score = (float)($auto_graded_scores_by_user_id[$breakdown->user_id] ?? 0);
            $rubric_points = DynamicRubric::cap($question_points, $auto_graded_score);
            $maxes = self::scaledMaxes($static_rubric, $rubric_points);
            $static_items = [];
            foreach ($static_rubric['rubric_items'] as $index => $item) {
                $static_items[] = ['title' => $item['title'],
                    'description' => $item['description'],
                    'points' => 0];
            }
            foreach ($applied_item_ids as $applied_item_id) {
                if (isset($converted['kept'][$applied_item_id])) {
                    $index = $converted['kept'][$applied_item_id];
                    $static_items[$index]['points'] = $maxes[$index];
                }
            }
            $breakdown_total = 0.0;
            foreach ($static_items as $static_item) {
                $breakdown_total += $static_item['points'];
            }
            $submission_file = $submission_files_by_user_id[$breakdown->user_id] ?? null;
            $has_score = $submission_file && $submission_file->score !== null;
            $old_rubric_score = $has_score ? round((float)$submission_file->score, 2) : null;
            $late_penalty_percent = min(max((float)($submission_file->applied_late_penalty ?? 0), 0.0), 100.0);
            $mapped_score = round(min($breakdown_total, $rubric_points) * (1 - $late_penalty_percent / 100), 2);
            $has_override = in_array($breakdown->user_id, $override_user_ids);
            if ($has_override) {
                $group = 'override';
            } else if (!$has_score) {
                $group = 'no_score';
            } else if (abs($mapped_score - $old_rubric_score)
                <= DynamicRubricConverter::roundingTolerance(count($static_items))) {
                $group = 'clean';
            } else {
                $group = 'change';
            }
            $user = $users_by_id[$breakdown->user_id] ?? null;
            $students[] = [
                'user_id' => (int)$breakdown->user_id,
                'name' => $user ? "$user->first_name $user->last_name" : '',
                'email' => $user ? $user->email : null,
                'first_name' => $user ? $user->first_name : '',
                'is_fake' => $user ? (bool)$user->fake_student : false,
                'group' => $group,
                'math' => self::math($dynamic_rubric, $converted, $applied_item_ids, $adjustment, $maxes, $rubric_points,
                    $breakdown_total, $old_rubric_score, $mapped_score, $late_penalty_percent, $auto_graded_score),
                'applied_item_ids' => $applied_item_ids,
                'adjustment' => $adjustment,
                'static_items' => $static_items,
                'old_rubric_score' => $old_rubric_score,
                'mapped_rubric_score' => $mapped_score,
                'reason' => $group === 'change' ? self::reason($adjustment, $breakdown_total, $rubric_points) : '',
                'auto_graded_score' => $auto_graded_score,
                'old_question_score' => round($auto_graded_score + (float)$old_rubric_score, 2),
                'mapped_question_score' => round($auto_graded_score + $mapped_score, 2)];
            $fingerprint_data[] = [(int)$breakdown->user_id, $applied_item_ids, $adjustment, $old_rubric_score,
                $late_penalty_percent, $auto_graded_score, $has_override];
        }

        $visible = function ($student) {
            return !$student['is_fake'];
        };
        $in_group = function (string $group) use ($students, $visible) {
            return array_values(array_filter($students, function ($student) use ($group, $visible) {
                return $student['group'] === $group && $visible($student);
            }));
        };
        $static_points = self::scaledMaxes($static_rubric, $question_points);
        $converted_items = [];
        foreach ($static_rubric['rubric_items'] as $index => $item) {
            $converted_items[] = ['title' => $item['title'], 'points' => $static_points[$index]];
        }
        return ['students' => $students,
            'fingerprint' => md5(json_encode($fingerprint_data)),
            'preview' => [
                'direction' => 'to_static',
                'converted_items' => $converted_items,
                'dropped_criteria' => $converted['dropped'],
                'number_clean' => count($in_group('clean')),
                'students_would_change' => array_map(function ($student) use ($question_points) {
                    return ['name' => $student['name'],
                        'old_score' => $student['old_question_score'],
                        'mapped_score' => $student['mapped_question_score'],
                        'reason' => $student['reason'],
                        'math' => $student['math'],
                        'question_points' => $question_points];
                }, $in_group('change')),
                'students_not_affected_override' => array_map(function ($student) use ($question_points) {
                    return ['name' => $student['name'],
                        'old_score' => $student['old_question_score'],
                        'question_points' => $question_points];
                }, $in_group('override')),
                'question_points' => $question_points,
                'scores_released' => (bool)$assignment->show_scores]];
    }

    /**
     * How a student's dynamic score turns into a static breakdown, criterion by criterion, for "Show the math".
     *
     * @return array
     */
    private static function math(array  $dynamic_rubric, array $converted, array $applied_item_ids, float $adjustment,
                                 array  $maxes, float $rubric_points, float $breakdown_total, ?float $old_rubric_score,
                                 float  $mapped_score, float $late_penalty_percent, float $auto_graded_score): array
    {
        $total_weight = 0.0;
        foreach ($converted['rubric']['rubric_items'] as $item) {
            $total_weight += (float)$item['points'];
        }
        $criteria = [];
        $criteria_total = 0.0;
        foreach ($dynamic_rubric['rubric_items'] ?? [] as $item) {
            $id = (string)($item['id'] ?? '');
            $applied = in_array($id, $applied_item_ids, true);
            $now_points = $applied ? (float)$item['points'] : 0.0;
            $criteria_total += $now_points;
            $kept_index = $converted['kept'][$id] ?? null;
            $criteria[] = ['title' => (string)($item['title'] ?? ''),
                'applied' => $applied,
                'now_points' => round($now_points, 2),
                'removed' => $kept_index === null,
                'static_points' => $kept_index === null ? null : ($applied ? $maxes[$kept_index] : 0.0)];
        }
        return ['criteria' => $criteria,
            'adjustment' => $adjustment,
            'now_raw' => round($criteria_total + $adjustment, 2),
            'now_total' => $old_rubric_score,
            'scale_from' => round($total_weight, 2),
            'scale_to' => round($rubric_points, 2),
            'scale' => $total_weight > 0 ? round($rubric_points / $total_weight, 3) : 0,
            'breakdown_total' => round($breakdown_total, 2),
            'converted_total' => $mapped_score,
            'late_penalty_percent' => $late_penalty_percent,
            'auto_graded_score' => $auto_graded_score];
    }

    /**
     * Why a student's score wouldn't carry over, in words for the preview.
     *
     * @param float $adjustment
     * @param float $breakdown_total
     * @param float $rubric_points
     * @return string
     */
    private static function reason(float $adjustment, float $breakdown_total, float $rubric_points): string
    {
        $reasons = [];
        if (abs($adjustment) >= 0.005) {
            $formatted = Helper::removeZerosAfterDecimal(round(abs($adjustment), 2));
            $reasons[] = ($adjustment > 0 ? "Had an adjustment of +$formatted" : "Had an adjustment of -$formatted")
                . " (static rubrics have no adjustment)";
        }
        if (!$reasons) {
            $reasons[] = $breakdown_total > $rubric_points + 0.005
                ? "Was capped at the question's points"
                : "Criteria are rescaled to the question's points";
        }
        return implode('; ', $reasons);
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     */
    public static function preview(Assignment $assignment, Question $question): array
    {
        $state = DynamicRubricEditor::currentState($assignment, $question);
        $convertible = self::convertible($state);
        if ($convertible['type'] !== 'success') {
            return $convertible;
        }
        $plan = self::plan($assignment, $question, $state, $convertible['converted']);
        return ['type' => 'success', 'preview' => $plan['preview'], 'fingerprint' => $plan['fingerprint']];
    }

    /**
     * Converts the rubric and every student's breakdown in one transaction.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param string $choice keep|clear: for students whose score would change
     * @param string $fingerprint from preview()
     * @param User $editor
     * @param bool $email_students
     * @param string $base_url
     * @return array
     * @throws Exception
     */
    public static function apply(Assignment $assignment,
                                 Question   $question,
                                 string     $choice,
                                 string     $fingerprint,
                                 User       $editor,
                                 bool       $email_students,
                                 string     $base_url): array
    {
        DB::beginTransaction();
        try {
            $state = DynamicRubricEditor::currentState($assignment, $question, true);
            $convertible = self::convertible($state);
            if ($convertible['type'] !== 'success') {
                DB::rollback();
                return $convertible;
            }
            $converted = $convertible['converted'];
            $plan = self::plan($assignment, $question, $state, $converted, true);
            if (!hash_equals($plan['fingerprint'], $fingerprint)) {
                DB::rollback();
                return ['type' => 'error', 'message' => self::CHANGED_MESSAGE];
            }
            //tells open Open Graders the rubric changed; continues the dynamic version numbers
            $published_version = DynamicRubricEditor::nextVersion($assignment->id, $question->id, $state);

            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_rubric' => json_encode($converted['rubric']), 'use_existing_rubric' => 0]);

            $score = new Score();
            $cleared = [];
            $number_flagged = 0;
            $now = Carbon::now();
            foreach ($plan['students'] as $student) {
                $clear = $student['group'] === 'change' && $choice === 'clear';
                $breakdown_query = DB::table('rubric_points_breakdowns')
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->where('user_id', $student['user_id']);
                if ($clear) {
                    $breakdown_query->delete();
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
                    $after = ['cleared' => true, 'score' => null];
                } else {
                    $needs_review = $student['group'] === 'change';
                    if ($needs_review && !$student['is_fake']) {
                        $number_flagged++;
                    }
                    $breakdown_query->update([
                        'points_breakdown' => json_encode(['rubric_items' => $student['static_items']]),
                        'applied_item_ids' => null,
                        'adjustment' => 0,
                        'needs_review' => $needs_review ? 1 : 0,
                        'rubric_version' => null,
                        'updated_at' => $now]);
                    $after = ['static_rubric_items' => $student['static_items'],
                        'score' => $student['old_rubric_score'],
                        'needs_review' => $needs_review];
                }
                DB::table('rubric_score_logs')->insert([
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'student_user_id' => $student['user_id'],
                    'grader_id' => $editor->id,
                    'before' => json_encode(['applied_item_ids' => $student['applied_item_ids'],
                        'adjustment' => $student['adjustment'],
                        'score' => $student['old_rubric_score']]),
                    'after' => json_encode($after),
                    'reason' => 'converted_to_static',
                    'created_at' => $now,
                    'updated_at' => $now]);
            }
            DB::table('rubric_change_logs')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => $editor->id,
                'action' => 'convert_to_static',
                'before' => json_encode($state['rubric']),
                'after' => json_encode(array_merge($converted['rubric'], ['version' => $published_version])),
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
            $question_title = $state['assignment_question']->custom_question_title
                ?: ($question->title ?: "Question #{$state['assignment_question']->order}");
            $link = rtrim($base_url, '/') . "/students/assignments/$assignment->id/summary";
            $question_points = Helper::removeZerosAfterDecimal(round((float)$state['assignment_question']->points, 2));
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

        $message = 'The rubric now uses static scoring.';
        if ($converted['dropped']) {
            $message .= count($converted['dropped']) === 1
                ? ' 1 feedback-only criterion was removed.'
                : ' ' . count($converted['dropped']) . ' feedback-only criteria were removed.';
        }
        if ($number_flagged) {
            $message .= $number_flagged === 1
                ? ' 1 student kept their score and is marked "Needs review".'
                : " $number_flagged students kept their scores and are marked \"Needs review\".";
        }
        if ($cleared) {
            $message .= count($cleared) === 1
                ? ' 1 student\'s score was cleared so they can be regraded.'
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
            'rubric' => $converted['rubric'],
            'published_version' => $published_version,
            'number_of_students_flagged' => $number_flagged,
            'number_of_students_cleared' => count($cleared),
            'number_of_students_emailed' => $emailed];
    }
}
