<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssignToTimingStartsTable extends Migration
{
    public function up()
    {
        Schema::create('assign_to_timing_starts', function (Blueprint $table) {
            // bigIncrements/unsignedBigInteger to match assign_to_timings.id,
            // which is bigint(20) unsigned - a plain increments()/
            // unsignedInteger() pair here would fail the FK constraint below.
            $table->bigIncrements('id');
            $table->unsignedBigInteger('assign_to_timing_id');
            $table->unsignedBigInteger('user_id');

            // Null until the student clicks "Start."
            $table->timestamp('started_at')->nullable();

            // Computed once at start (and recomputed whenever an instructor
            // adds/sets time). This is what canSubmitBasedOnGeneralSubmissionPolicy
            // checks against - never recompute available_from/due/time_limit
            // on the fly for this, or a later edit to the assignment's time_limit
            // could silently change an already-running student's clock.
            $table->timestamp('expires_at')->nullable();

            // Per-student override of assign_to_timings.time_limit, set by an
            // instructor "set time" action. Null = use the group default.
            $table->string('time_limit_override')->nullable();

            // Set true the moment an instructor's add-time/set-time action
            // pushes expires_at past the assign-to group's due date, so
            // instructor-facing views (roster, grading) can flag it. This is
            // the "make sure they're aware" persistence - the immediate
            // warning still happens client-side at the moment of the action.
            $table->boolean('extended_past_due')->default(false);

            $table->timestamps();

            $table->foreign('assign_to_timing_id')
                ->references('id')->on('assign_to_timings')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            // One clock per student per assign-to group.
            $table->unique(['assign_to_timing_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('assign_to_timing_starts');
    }
}
