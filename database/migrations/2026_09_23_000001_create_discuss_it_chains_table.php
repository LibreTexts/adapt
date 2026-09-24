<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiscussItChainsTable extends Migration
{
    /**
     * A "chain" links (daisy-chains) the same Discuss-it question across assignments in one course.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discuss_it_chains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('question_id');
            $table->timestamps();

            //at most one chain per question per course
            $table->unique(['course_id', 'question_id']);
            $table->index('question_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('discuss_it_chains');
    }
}
