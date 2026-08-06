<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLearningTreeToAssignmentQuestionLearningTree extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assignment_question_learning_tree', function (Blueprint $table) {
            $table->json('learning_tree')->after('learning_tree_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assignment_question_learning_tree', function (Blueprint $table) {
          $table->dropColumn('learning_tree');
        });
    }
}
