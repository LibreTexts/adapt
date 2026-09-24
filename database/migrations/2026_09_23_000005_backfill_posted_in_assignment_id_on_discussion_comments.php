<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillPostedInAssignmentIdOnDiscussionComments extends Migration
{
    /**
     * Before linking existed, a comment always belonged to the assignment of its thread.
     * Done in id ranges so no single UPDATE locks the table for long.
     *
     * @return void
     */
    public function up()
    {
        $chunk_size = 5000;
        $max_id = (int)DB::table('discussion_comments')->max('id');
        for ($start_id = 1; $start_id <= $max_id; $start_id += $chunk_size) {
            $end_id = $start_id + $chunk_size - 1;
            DB::update('UPDATE discussion_comments
                             JOIN discussions ON discussion_comments.discussion_id = discussions.id
                             SET discussion_comments.posted_in_assignment_id = discussions.assignment_id
                             WHERE discussion_comments.id BETWEEN ? AND ?
                             AND discussion_comments.posted_in_assignment_id IS NULL',
                [$start_id, $end_id]);
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        DB::table('discussion_comments')->update(['posted_in_assignment_id' => null]);
    }
}
