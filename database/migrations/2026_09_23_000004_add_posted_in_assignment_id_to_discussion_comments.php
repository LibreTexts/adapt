<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPostedInAssignmentIdToDiscussionComments extends Migration
{
    /**
     * The assignment the student was working in when they made the comment.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('discussion_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('posted_in_assignment_id')->after('user_id')->nullable();
            $table->index('posted_in_assignment_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('discussion_comments', function (Blueprint $table) {
            $table->dropIndex(['posted_in_assignment_id']);
            $table->dropColumn('posted_in_assignment_id');
        });
    }
}
