<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLatePenaltyRecalculationsTable extends Migration
{
    /**
     * Audit trail for assignment:recalculateLatePenalties. One row per score the command changed because the
     * due date moved. record_table/record_id point at the row whose score changed (submissions,
     * submitted_work_pending_scores or submission_files), and old_score is kept so any change can be rolled back.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('late_penalty_recalculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('question_id');
            $table->string('record_table', 40);
            $table->unsignedBigInteger('record_id');
            $table->dateTime('submitted_at');
            $table->dateTime('due');
            $table->unsignedSmallInteger('old_penalty_percent');
            $table->unsignedSmallInteger('new_penalty_percent');
            $table->decimal('old_score', 12, 4);
            $table->decimal('new_score', 12, 4);
            $table->timestamps();

            $table->index(['assignment_id', 'user_id']);
            $table->foreign('assignment_id')->references('id')->on('assignments');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('late_penalty_recalculations');
    }
}
