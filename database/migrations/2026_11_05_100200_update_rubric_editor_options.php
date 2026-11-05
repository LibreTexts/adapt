<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateRubricEditorOptions extends Migration
{
    /**
     * The instructor sets the style of rubrics a grader adds when the grader can't choose it.
     * Graders who can edit a rubric no longer need a separate option to email students.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rubric_editors', function (Blueprint $table) {
            $table->string('default_scoring_style', 10)->default('static')->after('user_id');
            $table->dropColumn('can_email_students');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('rubric_editors', function (Blueprint $table) {
            $table->boolean('can_email_students')->default(0);
            $table->dropColumn('default_scoring_style');
        });
    }
}
