<?php

namespace Tests\Feature\QTI;

use App\SavedQuestionsFolder;
use App\Traits\Test;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use function factory;

class FillInTheBlankTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
        $this->directory = 'test directory';
        $this->filename = 'some filename';

        $this->qti_question_info = [
            "question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "some title",
            "author" => "Instructor Kean",
            "tags" => [],
            "technology" => "qti",
            "technology_id" => null,
            "non_technology_text" => null,
            "text_question" => null,
            "a11y_auto_graded_question_id" => null,
            "answer_html" => null,
            "solution_html" => null,
            "notes" => null,
            "hint" => null,
            "license"=>"publicdomain",
            "license_version" => null,
            "qti_item_body" => "<div><p><span>Roses are <u><\/u>, violets are <u><\/u><\/span><\/p><\/div>",
            "qti_json" => '{"responseDeclaration":{"correctResponse":[{"value":"Red","matchingType":"exact","caseSensitive":"no"},{"value":"Blue","matchingType":"exact","caseSensitive":"no"}]},"itemBody":{"textEntryInteraction":"<div><p><span>Roses are <u><\/u>, violets are <u><\/u><\/span><\/p><\/div>"},"questionType":"fill_in_the_blank"}'
        ];
        $this->qti_job_id = DB::table('qti_jobs')->insertGetId([
            'user_id' => $this->user->id,
            'qti_source' => 'v2.2',
            'public' => 1,
            'folder_id' => factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id])->id,
            'license' => 'Public domain',
            'qti_directory' => $this->directory]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function at_least_one_response()
    {
        $bad_item_body = "Nothing underlined here.";
        $this->qti_question_info['qti_item_body'] = $bad_item_body;
        $qti_array = json_decode($this->qti_question_info['qti_json'], true);
        $qti_array['itemBody']['textEntryInteraction'] = $bad_item_body;
        $this->qti_question_info['qti_json'] = json_encode($qti_array);
        $this->actingAs($this->user)->postJson("/api/questions",
            $this->qti_question_info)
            ->assertJsonValidationErrors('qti_item_body');
    }
}
