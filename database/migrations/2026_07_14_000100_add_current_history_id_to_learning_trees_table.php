<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrentHistoryIdToLearningTreesTable extends Migration
{
    /**
     * EK: supports undo/redo without touching learning_tree_histories at
     * all. learning_tree_histories is already an append-only log (a new
     * row gets written on every real save via saveLearningTreeToHistory).
     * current_history_id is just a pointer into that log for this tree:
     *
     *   - undo moves the pointer to the previous row (by id) and copies
     *     that row's snapshot onto learning_trees.learning_tree
     *   - redo moves the pointer to the next row (by id) and does the same
     *   - a brand new edit always appends a new row and moves the pointer
     *     to it — which means any "future" rows past the old pointer are
     *     simply orphaned (never pointed at again), so redo naturally
     *     becomes unavailable without deleting or marking anything
     *
     * Nullable so existing trees (and any tree with no history yet) don't
     * need backfilling before this is usable; treated as "point at the
     * most recent history row" when null.
     */
    public function up()
    {
        Schema::table('learning_trees', function (Blueprint $table) {
            $table->unsignedBigInteger('current_history_id')->nullable()->after('learning_tree');
            $table->foreign('current_history_id')
                ->references('id')->on('learning_tree_histories')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('learning_trees', function (Blueprint $table) {
            $table->dropForeign(['current_history_id']);
            $table->dropColumn('current_history_id');
        });
    }
}
