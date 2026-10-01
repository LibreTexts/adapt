<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShowScoresReleasedAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('show_scores_released_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id')->unique();
            $table->unsignedTinyInteger('previous_show_scores');
            $table->unsignedTinyInteger('auto_release_changed')->default(0);
            $table->string('previous_auto_release_show_scores')->nullable();
            $table->string('previous_auto_release_show_scores_after')->nullable();
            $table->unsignedTinyInteger('previous_auto_release_show_scores_activated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('show_scores_released_assignments');
    }
}
