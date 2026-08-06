<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionRevisionPropagationsTable extends Migration
{
    /**
     * One row per "propagate" revision action - who did it, and exactly
     * which rows it touched: assignment_question_ids for ordinary (non-tree)
     * uses of the question, and assignment_question_learning_tree_ids for
     * every Learning Tree snapshot node it patched. Both point directly at
     * the rows actually mutated rather than derived assignment/tree ids, so
     * the assignment (and, for the tree case, the Learning Tree) is always
     * reachable by joining back to assignment_question /
     * assignment_question_learning_tree - never stale, never ambiguous.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('question_revision_propagations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_revision_id');
            $table->unsignedBigInteger('user_id');
            $table->json('assignment_question_ids')->nullable();
            $table->json('assignment_question_learning_tree_ids')->nullable();
            $table->timestamps();

            $table->foreign('question_revision_id')->references('id')->on('question_revisions')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('question_revision_propagations');
    }
}
