<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFrameworkItemLearningTreeTable extends Migration
{
    public function up()
    {
        // Mirrors framework_item_question exactly (same polymorphic-by-string-type shape:
        // framework_item_type is 'descriptor' or 'level', framework_item_id points into
        // framework_descriptors or framework_levels respectively).
        if (!Schema::hasTable('framework_item_learning_tree')) {
            Schema::create('framework_item_learning_tree', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('learning_tree_id');
                $table->unsignedBigInteger('framework_item_id');
                $table->string('framework_item_type');
                $table->timestamps();

                $table->foreign('learning_tree_id')->references('id')->on('learning_trees');
                $table->index(['framework_item_id', 'framework_item_type'], 'fw_item_learning_tree_item_id_type_index');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('framework_item_learning_tree');
    }
}
