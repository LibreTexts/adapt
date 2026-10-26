<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RepairUseExistingRubricOnAssignmentQuestion extends Migration
{
    /**
     * Deleting an overriding rubric used to clear custom_rubric but leave use_existing_rubric = 0.
     * The assignment page then showed no rubric while the Open Grader used the question's rubric.
     * Point those rows back at the question's rubric so every page agrees.
     * (Production check on 2026-10-26: 4 rows, none with rubric_points_breakdowns.)
     *
     * @return void
     */
    public function up()
    {
        DB::table('assignment_question')
            ->whereNull('custom_rubric')
            ->where('use_existing_rubric', 0)
            ->whereIn('question_id', function ($query) {
                $query->select('id')
                    ->from('questions')
                    ->whereNotNull('rubric');
            })
            ->update(['use_existing_rubric' => 1]);
    }

    /**
     * Not reversible: the old value was inconsistent data, and nothing records which rows changed.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
