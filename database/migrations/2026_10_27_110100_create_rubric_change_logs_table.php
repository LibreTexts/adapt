<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRubricChangeLogsTable extends Migration
{
    /**
     * One row per change to an assignment question's dynamic rubric.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rubric_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after');
            $table->unsignedInteger('students_rescored')->default(0);
            $table->timestamps();
            $table->index(['assignment_id', 'question_id']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rubric_change_logs');
    }
}
