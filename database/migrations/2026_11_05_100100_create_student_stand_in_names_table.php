<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentStandInNamesTable extends Migration
{
    /**
     * The name a grader sees for a student when real names are hidden. The unique keys give each student one name per
     * assignment and stop two students in an assignment from sharing a name.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_stand_in_names', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->timestamps();
            $table->foreign('assignment_id')->references('id')->on('assignments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['assignment_id', 'user_id']);
            $table->unique(['assignment_id', 'first_name', 'last_name'], 'student_stand_in_names_unique_name');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_stand_in_names');
    }
}
