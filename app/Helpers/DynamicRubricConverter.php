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
 * Converting an assignment question's static rubric to dynamic scoring once students have static rubric scores
 * (design §8). Each criterion becomes its scaled static points; a criterion a student got full points on is applied,
 * 0 points is not applied. Students whose score would change either keep their score (an adjustment, flagged
 * "Needs review") or are cleared (ungraded), as the instructor chooses.
 */
class DynamicRubricConverter
{
    const ONLY_INSTRUCTORS_MESSAGE = "Only instructors can convert a rubric to dynamic scoring.";
    const ALREADY_DYNAMIC_MESSAGE = "This rubric already uses dynamic scoring. Please reload the page.";
    const NO_RUBRIC_MESSAGE = "This question doesn't have a rubric to convert.";
    const NO_POINTS_MESSAGE = "The rubric's criteria don't have any points, so it can't be converted.";
    const CHANGED_MESSAGE = "Students' rubric scores or the rubric changed since you previewed the conversion. Please preview it again.";
    const FULL_POINTS_TOLERANCE = 0.01;

    /**
     * The dynamic rubric: each criterion is worth its scaled static points (weight × question points ÷ total weight).
     *
     * @param array $static_rubric
     * @param float $question_points
     * @return array|null null when the criteria have no points
     */
    public static function convertedRubric(array $static_rubric, float $question_points, int $version = 1): ?array
    {
        $items = array_values($static_rubric['rubric_items'] ?? []);
        $total_weight = 0.0;
        foreach ($items as $item) {
            $total_weight += is_numeric($item['points'] ?? null) ? (float)$item['points'] : 0.0;
        }
        if (!$items || $total_weight <= 0) {
            return null;
        }
        $ids = [];
        $converted_items = [];
        foreach ($items as $item) {
            $id = DynamicRubric::newItemId($ids);
            $ids[] = $id;
            $weight = is_numeric($item['points'] ?? null) ? (float)$item['points'] : 0.0;
            $converted_items[] = ['id' => $id,
                'title' => (string)($item['title'] ?? ''),
                'description' => (string)($item['description'] ?? ''),
                'points' => round($weight * $question_points / $total_weight, 2)];
        }
        return ['scoring_style' => 'dynamic',
            'version' => $version,
            'rubric_shown' => (bool)($static_rubric['rubric_shown'] ?? false),
            'rubric_items' => $converted_items];
    }

    /**
     * How every student with a static breakdown converts. Saves nothing.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param array $state from DynamicRubricEditor::currentState()
     * @param array $converted from convertedRubric()
     * @param bool $lock_for_update
     * @return array ['students' => full detail, 'preview' => what the browser sees, 'fingerprint' => string]
     */
    public static function plan(Assignment $assignment, Question $question, array $state, array $converted, bool $lock_for_update = false): array
    {
        $question_points = (float)$state['assignment_question']->points;
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

        $converted_items = $converted['rubric_items'];
        $students = [];
        $fingerprint_data = [$state['rubric'], $question_points];
        foreach ($breakdowns as $breakdown) {
            $static_items = array_values(json_decode($breakdown->points_breakdown, true)['rubric_items'] ?? []);
            $mapping = self::mapStaticItems($static_items, $converted_items);
            $auto_graded_score = (float)($auto_graded_scores_by_user_id[$breakdown->user_id] ?? 0);
            $cap = DynamicRubric::cap($question_points, $auto_graded_score);
            $submission_file = $submission_files_by_user_id[$breakdown->user_id] ?? null;
            $has_score = $submission_file && $submission_file->score !== null;
            $old_rubric_score = $has_score ? round((float)$submission_file->score, 2) : null;
            $late_penalty_percent = $submission_file ? $submission_file->applied_late_penalty : null;
            $mapped_score = DynamicRubric::score($converted, $mapping['applied_item_ids'], 0, $cap, $late_penalty_percent)['final'];
            $has_override = in_array($breakdown->user_id, $override_user_ids);
            if ($has_override) {
                $group = 'override';
            } else if (!$has_score) {
                $group = 'no_score'; //e.g. a student who dropped: converted quietly, nothing to keep or clear
            } else if (!$mapping['partial']
                && abs($mapped_score - $old_rubric_score) <= self::roundingTolerance(count($converted_items))) {
                //exact, or only off by the rounding of each criterion to 2 decimals (e.g. thirds of 10 points):
                //a tiny adjustment keeps the score and there's nothing to review
                $group = 'clean';
            } else {
                $group = 'change';
            }
            $keep_adjustment = $has_score
                ? self::keepScoreAdjustment($converted, $mapping['applied_item_ids'], $old_rubric_score, $cap, $late_penalty_percent)
                : 0.0;
            $user = $users_by_id[$breakdown->user_id] ?? null;
            $students[] = [
                'user_id' => (int)$breakdown->user_id,
                'name' => $user ? "$user->first_name $user->last_name" : '',
                'email' => $user ? $user->email : null,
                'first_name' => $user ? $user->first_name : '',
                'is_fake' => $user ? (bool)$user->fake_student : false,
                'group' => $group,
                'math' => self::math($static_items, $converted_items, $mapping['applied_item_ids'], $keep_adjustment,
                    $old_rubric_score, $mapped_score, $late_penalty_percent, $auto_graded_score),
                'static_items' => $static_items,
                'applied_item_ids' => $mapping['applied_item_ids'],
                'partial_criteria' => $mapping['partial_criteria'],
                'old_rubric_score' => $old_rubric_score,
                'mapped_rubric_score' => $mapped_score,
                'keep_adjustment' => $keep_adjustment,
                'auto_graded_score' => $auto_graded_score,
                'old_question_score' => round($auto_graded_score + (float)$old_rubric_score, 2),
                'mapped_question_score' => round($auto_graded_score + $mapped_score, 2)];
            $fingerprint_data[] = [(int)$breakdown->user_id, $static_items, $old_rubric_score, $late_penalty_percent, $auto_graded_score, $has_override];
        }

        $visible = function ($student) {
            return !$student['is_fake'];
        };
        $would_change = array_values(array_filter($students, function ($student) use ($visible) {
            return $student['group'] === 'change' && $visible($student);
        }));
        $overrides = array_values(array_filter($students, function ($student) use ($visible) {
            return $student['group'] === 'override' && $visible($student);
        }));
        $clean = array_filter($students, function ($student) use ($visible) {
            return $student['group'] === 'clean' && $visible($student);
        });
        return ['students' => $students,
            'fingerprint' => md5(json_encode($fingerprint_data)),
            'preview' => [
                'converted_items' => array_map(function ($item) {
                    return ['title' => $item['title'], 'points' => $item['points']];
                }, $converted_items),
                'number_clean' => count($clean),
                'students_would_change' => array_map(function ($student) use ($question_points) {
                    return ['name' => $student['name'],
                        'old_score' => $student['old_question_score'],
                        'mapped_score' => $student['mapped_question_score'],
                        'partial_criteria' => $student['partial_criteria'],
                        'math' => $student['math'],
                        'question_points' => $question_points];
                }, $would_change),
                'students_not_affected_override' => array_map(function ($student) use ($question_points) {
                    return ['name' => $student['name'],
                        'old_score' => $student['old_question_score'],
                        'question_points' => $question_points];
                }, $overrides),
                'question_points' => $question_points,
                'scores_released' => (bool)$assignment->show_scores]];
    }

    /**
     * How a student's static breakdown turns into dynamic criteria, criterion by criterion, for "Show the math".
     *
     * @return array
     */
    private static function math(array $static_items, array $converted_items, array $applied_item_ids, float $keep_adjustment,
                                 ?float $old_rubric_score, float $mapped_score, $late_penalty_percent, float $auto_graded_score): array
    {
        $criteria = [];
        $criteria_total = 0.0;
        foreach ($converted_items as $index => $item) {
            $given = $static_items[$index]['points'] ?? 0;
            $given = is_numeric($given) ? round((float)$given, 2) : 0.0;
            $applied = in_array($item['id'], $applied_item_ids, true);
            $dynamic_points = $applied ? (float)$item['points'] : 0.0;
            $criteria_total += $dynamic_points;
            $criteria[] = ['title' => $item['title'],
                'static_points' => $given,
                'full_points' => (float)$item['points'],
                'status' => $applied ? 'full' : ($given > 0 ? 'partial' : 'none'),
                'dynamic_points' => $dynamic_points];
        }
        return ['criteria' => $criteria,
            'now_total' => $old_rubric_score,
            'criteria_total' => round($criteria_total, 2),
            'converted_total' => $mapped_score,
            'keep_adjustment' => $keep_adjustment,
            'late_penalty_percent' => (float)($late_penalty_percent ?? 0),
            'auto_graded_score' => $auto_graded_score];
    }

    /**
     * The most a full-credit score can drift when each criterion is rounded to 2 decimals.
     *
     * @param int $number_of_criteria
     * @return float
     */
    public static function roundingTolerance(int $number_of_criteria): float
    {
        return 0.005 * max(1, $number_of_criteria) + 0.005;
    }

    /**
     * Matches the static breakdown to the converted criteria by position.
     *
     * @param array $static_items the student's static breakdown items (points given)
     * @param array $converted_items
     * @return array ['applied_item_ids' => array, 'partial' => bool, 'partial_criteria' => int]
     */
    public static function mapStaticItems(array $static_items, array $converted_items): array
    {
        $applied_item_ids = [];
        $partial_criteria = 0;
        foreach ($converted_items as $index => $converted_item) {
            $given = $static_items[$index]['points'] ?? null;
            if ($given === null || $given === '') {
                $given = 0;
            }
            if (!is_numeric($given)) {
                $partial_criteria++;
                continue;
            }
            $given = (float)$given;
            if (abs($given - (float)$converted_item['points']) <= self::FULL_POINTS_TOLERANCE + 0.000001) {
                $applied_item_ids[] = $converted_item['id'];
            } else if (abs($given) >= 0.005) {
                $partial_criteria++;
            }
        }
        //a breakdown with more criteria than the rubric (it changed after grading) can't be matched exactly
        $partial_criteria += max(0, count($static_items) - count($converted_items));
        return ['applied_item_ids' => $applied_item_ids,
            'partial' => $partial_criteria > 0,
            'partial_criteria' => $partial_criteria];
    }

    /**
     * The adjustment that keeps the student's old open-ended score with the mapped criteria.
     *
     * @param array $rubric
     * @param array $applied_item_ids
     * @param float $old_rubric_score
     * @param float $cap
     * @param mixed $late_penalty_percent
     * @return float
     */
    public static function keepScoreAdjustment(array $rubric, array $applied_item_ids, float $old_rubric_score, float $cap, $late_penalty_percent): float
    {
        $applied_points = DynamicRubric::score($rubric, $applied_item_ids, 0, INF, 0)['raw'];
        $penalty = min(max((float)($late_penalty_percent ?? 0), 0.0), 100.0);
        if ($penalty >= 100) {
            return 0.0;
        }
        $needed_raw = $old_rubric_score / (1 - $penalty / 100);
        $adjustment = round($needed_raw - $applied_points, 2);
        //rounding can leave the score a cent off; nudge by a cent if that matches exactly
        foreach ([$adjustment, $adjustment - 0.01, $adjustment + 0.01] as $candidate) {
            $candidate = round($candidate, 2);
            if (abs(DynamicRubric::score($rubric, $applied_item_ids, $candidate, $cap, $late_penalty_percent)['final'] - $old_rubric_score) < 0.005) {
                return $candidate;
            }
        }
        return $adjustment;
    }

    /**
     * The rubric in effect must be static and have criteria with points.
     *
     * @param array $state
     * @return array|null ['type' => 'success', 'rubric' => converted] or an error response
     */
    public static function convertible(array $state): array
    {
        if (!$state['assignment_question']) {
            return ['type' => 'error', 'message' => 'That question is not in this assignment.'];
        }
        if ($state['is_dynamic']) {
            return ['type' => 'error', 'message' => self::ALREADY_DYNAMIC_MESSAGE];
        }
        if (!$state['rubric'] || empty($state['rubric']['rubric_items'])) {
            return ['type' => 'error', 'message' => self::NO_RUBRIC_MESSAGE];
        }
        $converted = self::convertedRubric($state['rubric'],
            (float)$state['assignment_question']->points,
            DynamicRubricEditor::nextVersion((int)$state['assignment_question']->assignment_id,
                (int)$state['assignment_question']->question_id,
                $state));
        if (!$converted) {
            return ['type' => 'error', 'message' => self::NO_POINTS_MESSAGE];
        }
        return ['type' => 'success', 'rubric' => $converted];
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
        $plan = self::plan($assignment, $question, $state, $convertible['rubric']);
        return ['type' => 'success', 'preview' => $plan['preview'], 'fingerprint' => $plan['fingerprint']];
    }

    /**
     * Converts the rubric and every student's breakdown in one transaction.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param string $choice keep|clear: for students whose score would change
     * @param string $fingerprint from preview(): refuses if anything changed since
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
            $converted = $convertible['rubric'];
            $plan = self::plan($assignment, $question, $state, $converted, true);
            if (!hash_equals($plan['fingerprint'], $fingerprint)) {
                DB::rollback();
                return ['type' => 'error', 'message' => self::CHANGED_MESSAGE];
            }

            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_rubric' => json_encode($converted), 'use_existing_rubric' => 0]);

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
                $after = null;
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
                    //keep the score; students without one ("no_score") have nothing to keep
                    $adjustment = $student['group'] === 'no_score' ? 0.0 : $student['keep_adjustment'];
                    $needs_review = $student['group'] === 'change';
                    if ($needs_review && !$student['is_fake']) {
                        $number_flagged++;
                    }
                    $breakdown_query->update([
                        'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
                        'applied_item_ids' => json_encode($student['applied_item_ids']),
                        'adjustment' => $adjustment,
                        'needs_review' => $needs_review ? 1 : 0,
                        'rubric_version' => $converted['version'],
                        'updated_at' => $now]);
                    $after = ['applied_item_ids' => $student['applied_item_ids'],
                        'adjustment' => $adjustment,
                        'score' => $student['old_rubric_score'],
                        'needs_review' => $needs_review];
                }
                DB::table('rubric_score_logs')->insert([
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'student_user_id' => $student['user_id'],
                    'grader_id' => $editor->id,
                    'before' => json_encode(['static_rubric_items' => $student['static_items'],
                        'score' => $student['old_rubric_score']]),
                    'after' => json_encode($after),
                    'reason' => 'converted_to_dynamic',
                    'created_at' => $now,
                    'updated_at' => $now]);
            }
            DB::table('rubric_change_logs')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => $editor->id,
                'action' => 'convert',
                'before' => json_encode($state['rubric']),
                'after' => json_encode($converted),
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
            $emailed = self::emailClearedStudents($assignment, $question, $state['assignment_question'], $cleared, $base_url);
        }
        DynamicRubricEditor::publishRubricUpdated($assignment->id, $question->id, $converted['version']);

        $message = 'The rubric now uses dynamic scoring.';
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
            'rubric' => $converted,
            'number_of_students_flagged' => $number_flagged,
            'number_of_students_cleared' => count($cleared),
            'number_of_students_emailed' => $emailed];
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param object $assignment_question
     * @param array $students
     * @param string $base_url
     * @return int
     */
    private static function emailClearedStudents(Assignment $assignment, Question $question, $assignment_question, array $students, string $base_url): int
    {
        $question_title = $assignment_question->custom_question_title
            ?: ($question->title ?: "Question #$assignment_question->order");
        $link = rtrim($base_url, '/') . "/students/assignments/$assignment->id/summary";
        $question_points = Helper::removeZerosAfterDecimal(round((float)$assignment_question->points, 2));
        $emailed = 0;
        foreach ($students as $student) {
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
        return $emailed;
    }
}
