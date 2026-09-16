<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveCascadeFromAssignToTimingStartsTable extends Migration
{
    /**
     * Drops the onDelete('cascade') added in the original migration and
     * replaces it with a plain foreign key (default RESTRICT behavior).
     * Cleanup of assign_to_timing_starts rows now happens explicitly at the
     * application level in AssignmentProperties::addAssignTos(), right next
     * to where AssignToGroup/AssignToUser are already deleted the same way -
     * this is a deliberate move away from ON DELETE CASCADE, which isn't
     * used anywhere else in this codebase's schema.
     *
     * NOTE: this only rewrites the foreign key on assign_to_timing_id - the
     * user_id foreign key's cascade is untouched, since a deleted user
     * legitimately should take their own timing-start rows with them.
     */
    public function up()
    {
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            $table->dropForeign(['assign_to_timing_id']);
        });
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            $table->foreign('assign_to_timing_id')
                ->references('id')->on('assign_to_timings');
        });
    }

    public function down()
    {
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            $table->dropForeign(['assign_to_timing_id']);
        });
        Schema::table('assign_to_timing_starts', function (Blueprint $table) {
            $table->foreign('assign_to_timing_id')
                ->references('id')->on('assign_to_timings')
                ->onDelete('cascade');
        });
    }
}
