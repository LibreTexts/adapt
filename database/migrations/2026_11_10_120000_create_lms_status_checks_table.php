<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLmsStatusChecksTable extends Migration
{
    /**
     * One row per active LTI registration per run of check:lmsStatus. Every row written by the same run shares the
     * same checked_at, so a "run" is all rows with one checked_at value. Rows older than the retention window
     * (myconfig.lms_status_retention_hours) are pruned by the command itself.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lms_status_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lti_registration_id');
            $table->timestamp('checked_at');
            $table->string('status', 16); // up | down | auth_error
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error', 500)->nullable();

            $table->foreign('lti_registration_id')->references('id')->on('lti_registrations')->onDelete('cascade');
            $table->index(['lti_registration_id', 'checked_at']);
            $table->index('checked_at');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lms_status_checks');
    }
}
