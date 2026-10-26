<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class RubricHelper
{
    /**
     * The rubric that applies to a question in an assignment, using the question revision the
     * assignment is on (the same revision the assignment page shows), then the override rule below.
     *
     * @param object|null $assignment_question a row from assignment_question (DB::table or Eloquent)
     * @param string|null $latest_question_rubric questions.rubric, used when the assignment has no revision
     * @return string|null
     */
    public static function assignmentQuestionRubric($assignment_question, ?string $latest_question_rubric): ?string
    {
        return self::effectiveRubric($assignment_question,
            self::questionRubricForRevision($assignment_question, $latest_question_rubric));
    }

    /**
     * The question's own rubric as of the revision the assignment uses.
     * Mirrors Question::updateWithQuestionRevision(): with no revision, the latest questions.rubric is used.
     *
     * @param object|null $assignment_question
     * @param string|null $latest_question_rubric
     * @return string|null
     */
    public static function questionRubricForRevision($assignment_question, ?string $latest_question_rubric): ?string
    {
        if (!$assignment_question || !$assignment_question->question_revision_id) {
            return $latest_question_rubric;
        }
        $question_revision = DB::table('question_revisions')
            ->where('id', $assignment_question->question_revision_id)
            ->select('rubric')
            ->first();
        return $question_revision ? $question_revision->rubric : $latest_question_rubric;
    }

    /**
     * The single rule for which JSON rubric applies to an assignment question.
     *
     * use_existing_rubric = 1 or null  -> the question's own (default) rubric
     * use_existing_rubric = 0          -> the assignment's overriding rubric (custom_rubric),
     *                                     falling back to the question's rubric if no override is saved
     *
     * Every place that shows or grades with the rubric (assignment page, Open Grader,
     * student rubric breakdown) must use this so they never disagree.
     *
     * @param object|null $assignment_question a row from assignment_question (DB::table or Eloquent)
     * @param string|null $question_rubric the question's rubric JSON (for the revision the assignment uses)
     * @return string|null
     */
    public static function effectiveRubric($assignment_question, ?string $question_rubric): ?string
    {
        if (!$assignment_question) {
            return $question_rubric;
        }
        $use_existing_rubric = $assignment_question->use_existing_rubric;
        $custom_rubric = $assignment_question->custom_rubric;
        $uses_existing_rubric = $use_existing_rubric === null || (int)$use_existing_rubric === 1;
        if ($uses_existing_rubric || !$custom_rubric) {
            return $question_rubric;
        }
        return $custom_rubric;
    }
}
