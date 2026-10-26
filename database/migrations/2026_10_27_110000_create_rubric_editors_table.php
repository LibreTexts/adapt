<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRubricEditorsTable extends Migration
{
    /**
     * Graders the instructor has allowed to edit dynamic rubrics in this course ("Can edit rubric").
     * Course level because graders rows are per section.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rubric_editors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('course_id')->references('id')->on('courses');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique(['course_id', 'user_id']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rubric_editors');
    }
}
