<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasteryAssignmentAttemptsTable extends Migration
{
    /**
     * Create the persistent snapshot and lifecycle record for each assignment attempt.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mastery_assignment_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('attempt_number');
            $table->string('status', 16)->default('in_progress');
            $table->json('question_ids');
            $table->json('variant_identifiers')->nullable();
            $table->json('question_results')->nullable();
            $table->decimal('score', 12, 4)->nullable();
            $table->decimal('possible_score', 12, 4)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id', 'attempt_number'], 'mastery_attempt_number_unique');
            $table->index(['assignment_id', 'user_id', 'status'], 'mastery_attempt_status_index');
            $table->foreign('assignment_id')->references('id')->on('assignments');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Remove whole-assignment attempt history.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mastery_assignment_attempts');
    }
}
