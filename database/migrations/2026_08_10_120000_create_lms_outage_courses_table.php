<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLmsOutageCoursesTable extends Migration
{
    /**
     * A row is created for a course every time an admin turns off LMS
     * access for it (courses.lms goes from 1 -> 0), as part of turning
     * off an entire LMS type (Canvas, Blackboard, or Brightspace) across
     * all schools - since each LMS type can go down independently of
     * the others. `turned_on_at` stays null while the course is still
     * affected, and gets stamped when the admin turns that LMS type back
     * on (courses.lms goes from 0 -> 1 again). `school_id` and
     * `lti_registration_id` are captured at the time of turning off for
     * traceability, and `lms_type` ('Canvas'/'Blackboard'/'Brightspace')
     * is what turning back on actually filters by.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lms_outage_courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('lti_registration_id')->nullable();
            $table->string('lms_type');
            $table->timestamp('turned_on_at')->nullable();

            $table->foreign('course_id')->references('id')->on('courses');
            $table->foreign('school_id')->references('id')->on('schools');
            $table->foreign('lti_registration_id')->references('id')->on('lti_registrations');
            $table->index('turned_on_at');
            $table->index('lms_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_outage_courses');
    }
}
