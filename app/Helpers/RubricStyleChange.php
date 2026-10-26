<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

/**
 * When a question's author switches its default rubric between static and dynamic scoring, every assignment that
 * follows the question's rubric (no override of its own) and already has rubric scores first gets a copy of the
 * rubric it uses now as its own override. Its grades stay exactly as they are.
 */
class RubricStyleChange
{
    /**
     * @param int $question_id
     * @param string|null $old_question_rubric questions.rubric before the save
     * @param string|null $new_question_rubric questions.rubric after the save
     * @return int number of assignment questions given their own copy
     */
    public static function keepAssignmentRubricsBeforeStyleChange(int $question_id, ?string $old_question_rubric, ?string $new_question_rubric): int
    {
        if (!$old_question_rubric || !$new_question_rubric
            || DynamicRubric::isDynamic($old_question_rubric) === DynamicRubric::isDynamic($new_question_rubric)) {
            return 0;
        }
        $assignment_questions = DB::table('assignment_question')
            ->where('question_id', $question_id)
            ->get();
        $number_kept = 0;
        foreach ($assignment_questions as $assignment_question) {
            $uses_override = $assignment_question->use_existing_rubric !== null
                && (int)$assignment_question->use_existing_rubric === 0
                && $assignment_question->custom_rubric;
            if ($uses_override) {
                continue;
            }
            $has_scores = DB::table('rubric_points_breakdowns')
                ->where('assignment_id', $assignment_question->assignment_id)
                ->where('question_id', $question_id)
                ->exists();
            if (!$has_scores) {
                continue;
            }
            //the rubric this assignment grades with today (its revision's rubric, or the question's current one)
            $rubric_in_use = RubricHelper::questionRubricForRevision($assignment_question, $old_question_rubric);
            if (!$rubric_in_use) {
                continue;
            }
            DB::table('assignment_question')
                ->where('id', $assignment_question->id)
                ->update(['custom_rubric' => $rubric_in_use, 'use_existing_rubric' => 0]);
            $number_kept++;
        }
        return $number_kept;
    }
}
