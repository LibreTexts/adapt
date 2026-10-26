<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRubricScoreLogsTable extends Migration
{
    /**
     * One row each time a student's dynamic rubric score changes: when graded, or when a rubric edit rescores them.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rubric_score_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('student_user_id');
            $table->unsignedBigInteger('grader_id');
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 40);
            $table->timestamps();
            $table->index(['assignment_id', 'question_id']);
            $table->index('student_user_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rubric_score_logs');
    }
}
