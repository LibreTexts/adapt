<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\SavedQuestionsFolder;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Saving a question's default rubric through POST /api/questions and PATCH /api/questions/{question}
 * (the question payload is the native multiple choice one from QuestionsViewTest).
 */
class DynamicRubricQuestionSaveTest extends TestCase
{
    private $user;
    private $saved_questions_folder;

    public function setup(): void
    {
        parent::setUp();
        Mail::fake();
        $this->user = factory(User::class)->create();
        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
    }

    private function questionInfo(array $rubric_fields = []): array
    {
        return array_merge(["question_type" => "assessment",
            "folder_id" => $this->saved_questions_folder->id,
            "public" => "0",
            "title" => "some title",
            "author" => "Instructor Kean",
            "tags" => [],
            "technology" => "qti",
            'open_ended_submission_type' => '0',
            "technology_id" => null,
            "non_technology_text" => null,
            "text_question" => null,
            "a11y_auto_graded_question_id" => null,
            "answer_html" => null,
            "solution_html" => null,
            "notes" => null,
            "hint" => null,
            "license" => "publicdomain",
            "license_version" => null,
            "qti_randomize_order" => "yes",
            "qti_prompt" => "<p>The derivative of sin(x) is cos(x).</p>",
            "qti_correct_response" => "2455",
            "qti_simple_choice_0" => "cscx",
            "qti_simple_choice_1" => "cotx",
            "qti_json" => '{"prompt":"<div><p>What is the derivative of sin(x)?","simpleChoice":[{"identifier":"6516","value":"csc(x)","correctResponse":false},{"identifier":"2455","value":"cos(x)","correctResponse":true}],"questionType":"multiple_choice"}',
            'rubric_shown' => true,
            'rubric_template_save_option' => 'do not save as template'
        ], $rubric_fields);
    }

    private function createQuestion(array $rubric_fields): int
    {
        $this->actingAs($this->user)->postJson('/api/questions', $this->questionInfo($rubric_fields))
            ->assertJson(['type' => 'success']);
        return DB::table('questions')->orderBy('id', 'desc')->first()->id;
    }

    private function updateQuestion(int $question_id, array $rubric_fields)
    {
        return $this->actingAs($this->user)->patchJson("/api/questions/$question_id", array_merge(
            $this->questionInfo($rubric_fields),
            ['id' => $question_id,
                'revision_action' => 'notify',
                'reason_for_edit' => 'Updated the rubric',
                'automatically_update_revision' => 0]));
    }

    private function questionRubric(int $question_id): array
    {
        return json_decode(DB::table('questions')->where('id', $question_id)->first()->rubric, true);
    }

    #[Test]
    public function a_question_can_be_saved_with_a_dynamic_rubric_including_a_zero_point_item()
    {
        $question_id = $this->createQuestion([
            'rubric_scoring_style' => 'dynamic',
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2],
                ['title' => 'Comment only', 'description' => '', 'points' => 0]]]);
        $rubric = $this->questionRubric($question_id);
        $this->assertEquals('dynamic', $rubric['scoring_style']);
        $this->assertEquals(1, $rubric['version']);
        $this->assertCount(2, array_unique(array_column($rubric['rubric_items'], 'id')));
        $this->assertEquals(0, $rubric['rubric_items'][1]['points']);
    }

    #[Test]
    public function editing_a_dynamic_question_rubric_keeps_item_ids_and_raises_the_version()
    {
        $question_id = $this->createQuestion([
            'rubric_scoring_style' => 'dynamic',
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]]);
        $first_id = $this->questionRubric($question_id)['rubric_items'][0]['id'];

        $this->updateQuestion($question_id, [
            'rubric_scoring_style' => 'dynamic',
            'rubric_items' => [['id' => $first_id, 'title' => 'Correct ring', 'description' => '', 'points' => 3],
                ['title' => 'Correct arrow', 'description' => '', 'points' => 2]]])
            ->assertJson(['type' => 'success']);
        $rubric = $this->questionRubric($question_id);
        $this->assertEquals(2, $rubric['version']);
        $this->assertEquals($first_id, $rubric['rubric_items'][0]['id']);
        $this->assertEquals(3, $rubric['rubric_items'][0]['points']);
        $this->assertNotEquals($first_id, $rubric['rubric_items'][1]['id']);
    }

    #[Test]
    public function a_static_question_rubric_is_saved_as_before_and_still_rejects_zero_points()
    {
        $question_id = $this->createQuestion([
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]]);
        $this->assertEquals(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]],
            'rubric_shown' => true], $this->questionRubric($question_id));

        $this->actingAs($this->user)->postJson('/api/questions', $this->questionInfo([
            'rubric_items' => [['title' => 'Comment only', 'description' => '', 'points' => 0]]]))
            ->assertStatus(422);
    }

    #[Test]
    public function saving_a_dynamic_question_rubric_as_a_template_keeps_the_style_but_not_the_ids()
    {
        $this->createQuestion([
            'rubric_scoring_style' => 'dynamic',
            'rubric_template_save_option' => 'save as new template',
            'rubric_name' => 'Ring template',
            'rubric_description' => 'From a question',
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]]);
        $template_rubric = json_decode(DB::table('rubric_templates')->where('name', 'Ring template')->first()->rubric, true);
        $this->assertEquals('dynamic', $template_rubric['scoring_style']);
        $this->assertArrayNotHasKey('id', $template_rubric['rubric_items'][0]);
    }

    #[Test]
    public function switching_a_graded_question_rubric_to_dynamic_keeps_the_static_rubric_for_that_assignment()
    {
        $static_items = [['title' => 'Correct ring', 'description' => '', 'points' => 2]];
        $question_id = $this->createQuestion(['rubric_items' => $static_items]);
        $static_rubric = $this->questionRubric($question_id);

        $course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $assignment = factory(Assignment::class)->create(['course_id' => $course->id]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $question_id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $question_id,
            'user_id' => $this->user->id,
            'points_breakdown' => json_encode(['rubric_items' => $static_items])]);

        $this->updateQuestion($question_id, [
            'rubric_scoring_style' => 'dynamic',
            'rubric_items' => $static_items])
            ->assertJson(['type' => 'success']);

        $this->assertEquals('dynamic', $this->questionRubric($question_id)['scoring_style']);
        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question_id)
            ->first();
        $this->assertEquals(0, (int)$assignment_question->use_existing_rubric);
        $this->assertEquals($static_rubric, json_decode($assignment_question->custom_rubric, true));
    }
}
