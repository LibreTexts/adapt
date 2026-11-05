<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermissionsToRubricEditorsTable extends Migration
{
    /**
     * Options an instructor can give a grader on top of "Can edit rubric". All start off.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rubric_editors', function (Blueprint $table) {
            $table->boolean('can_change_scoring_style')->default(0)->after('user_id');
            $table->boolean('can_convert_graded_rubrics')->default(0)->after('can_change_scoring_style');
            $table->boolean('can_change_rubric_shown')->default(0)->after('can_convert_graded_rubrics');
            $table->boolean('can_email_students')->default(0)->after('can_change_rubric_shown');
            $table->boolean('can_edit_graded_static_rubrics')->default(0)->after('can_email_students');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('rubric_editors', function (Blueprint $table) {
            $table->dropColumn(['can_change_scoring_style',
                'can_convert_graded_rubrics',
                'can_change_rubric_shown',
                'can_email_students',
                'can_edit_graded_static_rubrics']);
        });
    }
}
