<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds custom-size and native-dimension columns to question_media_uploads.
 *
 * width/height: the instructor's optional override (null = fill the question
 * area, current/default behavior).
 * native_width/native_height: the video file's real intrinsic dimensions,
 * detected client-side and cached here so we don't have to re-probe the
 * file every time the question is edited.
 *
 * Confirmed against QuestionController@store: QuestionMediaUpload rows are
 * built field-by-field there, so these columns are required for width/
 * height/native_width/native_height to persist at all.
 */
class AddDimensionsToQuestionMediaUploadsTable extends Migration
{
    public function up()
    {
        Schema::table('question_media_uploads', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('s3_key');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->unsignedInteger('native_width')->nullable()->after('height');
            $table->unsignedInteger('native_height')->nullable()->after('native_width');
        });
    }

    public function down()
    {
        Schema::table('question_media_uploads', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'native_width', 'native_height']);
        });
    }
};
