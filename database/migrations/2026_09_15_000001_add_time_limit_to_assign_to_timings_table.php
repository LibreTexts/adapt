<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeLimitToAssignToTimingsTable extends Migration
{
    /**
     * time_limit is stored the same way the existing "period of time" fields
     * are (default_clicker_time_to_submit, late_deduction_application_period) -
     * a string parseable by CarbonInterval::make(), e.g. "PT1H" or "1 hour".
     * Null means "no personal time limit for this assign-to group".
     */
    public function up()
    {
        Schema::table('assign_to_timings', function (Blueprint $table) {
            $table->string('time_limit')->nullable()->after('final_submission_deadline');
        });
    }

    public function down()
    {
        Schema::table('assign_to_timings', function (Blueprint $table) {
            $table->dropColumn('time_limit');
        });
    }
}
