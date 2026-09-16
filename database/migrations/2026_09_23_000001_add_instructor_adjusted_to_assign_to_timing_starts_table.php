<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInstructorAdjustedToAssignToTimingStartsTable extends Migration
{
    public function up()
    {
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            // True whenever an instructor's addTime/setTime action has ever
            // touched this row - including starting it fresh on a student's
            // behalf, or extending/resetting an already-running clock.
            // Without this, a timer an instructor started for a student
            // looks identical to one the student started themselves (both
            // have a real started_at/expires_at) - which is exactly what
            // made an instructor's own test override look like a mystery
            // when reviewing the Active Timers list later.
            $table->boolean('instructor_adjusted')->default(false)->after('time_limit_override');
        });
    }

    public function down()
    {
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            $table->dropColumn('instructor_adjusted');
        });
    }
}
