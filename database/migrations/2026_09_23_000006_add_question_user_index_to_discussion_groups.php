<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQuestionUserIndexToDiscussionGroups extends Migration
{
    /**
     * Group look-ups now span every assignment in a chain.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('discussion_groups', function (Blueprint $table) {
            $table->index(['question_id', 'user_id', 'assignment_id'], 'discussion_groups_question_user_assignment_index');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('discussion_groups', function (Blueprint $table) {
            $table->dropIndex('discussion_groups_question_user_assignment_index');
        });
    }
}
