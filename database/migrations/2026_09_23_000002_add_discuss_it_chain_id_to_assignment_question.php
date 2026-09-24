<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscussItChainIdToAssignmentQuestion extends Migration
{
    /**
     * No ->after(): adding a nullable column at the end of the table lets MySQL 8 do it instantly
     * instead of rebuilding assignment_question.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assignment_question', function (Blueprint $table) {
            $table->unsignedBigInteger('discuss_it_chain_id')->nullable();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('assignment_question', function (Blueprint $table) {
            $table->dropColumn('discuss_it_chain_id');
        });
    }
}
