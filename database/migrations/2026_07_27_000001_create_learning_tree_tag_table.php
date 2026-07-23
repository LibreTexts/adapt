<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLearningTreeTagTable extends Migration
{
    public function up()
    {
        // Mirrors question_tag exactly, reusing the existing global `tags` table.
        if (!Schema::hasTable('learning_tree_tag')) {
            Schema::create('learning_tree_tag', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learning_tree_id');
                $table->unsignedBigInteger('tag_id');
                $table->timestamps();

                $table->foreign('learning_tree_id')->references('id')->on('learning_trees');
                $table->foreign('tag_id')->references('id')->on('tags');
                $table->unique(['learning_tree_id', 'tag_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('learning_tree_tag');
    }
}
