<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMasteryRetakeEnabledToAssignments extends Migration
{
    /**
     * Add the opt-in mastery mode and its assignment-attempt limit.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('mastery_retake_enabled')
                ->default(false)
                ->after('algorithmic');
            $table->string('mastery_number_of_allowed_attempts', 10)
                ->default('unlimited')
                ->after('mastery_retake_enabled');
        });

        Schema::table('assignment_templates', function (Blueprint $table) {
            $table->boolean('mastery_retake_enabled')
                ->default(false)
                ->after('algorithmic');
            $table->string('mastery_number_of_allowed_attempts', 10)
                ->default('unlimited')
                ->after('mastery_retake_enabled');
        });
    }

    /**
     * Remove the mastery assignment properties.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assignment_templates', function (Blueprint $table) {
            $table->dropColumn(['mastery_retake_enabled', 'mastery_number_of_allowed_attempts']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['mastery_retake_enabled', 'mastery_number_of_allowed_attempts']);
        });
    }
}
