<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscussItChainIdIndexToAssignmentQuestion extends Migration
{
    /**
     * A plain index (no foreign key): the code always clears discuss_it_chain_id before deleting a chain,
     * and adding a foreign key to assignment_question forces MySQL to copy the whole table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assignment_question', function (Blueprint $table) {
            $table->index('discuss_it_chain_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('assignment_question', function (Blueprint $table) {
            $table->dropIndex(['discuss_it_chain_id']);
        });
    }
}
