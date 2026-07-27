<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubjectChapterSectionToLearningTreesTable extends Migration
{
    public function up()
    {
        Schema::table('learning_trees', function (Blueprint $table) {
            // Matches questions.question_subject_id/question_chapter_id/
            // question_section_id exactly: varchar(191), nullable, indexed.
            // These are NOT foreign keys on the questions table either (no
            // cascade there), so we don't add one here - deletion is handled
            // in application code (see QuestionSubjectController::destroy(),
            // QuestionChapterController::destroy() for the existing pattern
            // to mirror on the Learning Tree side).
            $table->string('question_subject_id', 191)->nullable()->index();
            $table->string('question_chapter_id', 191)->nullable()->index();
            $table->string('question_section_id', 191)->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('learning_trees', function (Blueprint $table) {
            $table->dropColumn(['question_subject_id', 'question_chapter_id', 'question_section_id']);
        });
    }
}
