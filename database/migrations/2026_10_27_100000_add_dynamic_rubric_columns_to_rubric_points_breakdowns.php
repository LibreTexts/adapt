<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDynamicRubricColumnsToRubricPointsBreakdowns extends Migration
{
    /**
     * Columns used only by dynamic rubrics. Classic rubrics keep using points_breakdown.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rubric_points_breakdowns', function (Blueprint $table) {
            $table->json('applied_item_ids')->after('points_breakdown')->nullable();
            $table->decimal('adjustment', 8, 2)->after('applied_item_ids')->default(0);
            $table->boolean('needs_review')->after('adjustment')->default(0);
            $table->unsignedInteger('rubric_version')->after('needs_review')->nullable();
            $table->unsignedBigInteger('grader_id')->after('rubric_version')->nullable();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('rubric_points_breakdowns', function (Blueprint $table) {
            $table->dropColumn(['applied_item_ids', 'adjustment', 'needs_review', 'rubric_version', 'grader_id']);
        });
    }
}
