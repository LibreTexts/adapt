<?php

namespace Tests\Feature\QTI;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\SavedQuestionsFolder;
use App\Section;
use App\Traits\Test;
use App\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test as TestAttribute;
use Tests\TestCase;

/**
 * Select-choice blanks whose text is not plain ASCII (IPA, accented letters, spaces).
 *
 * Background: Question::formatQtiJson runs itemBody through addTimeToS3Files (DOMDocument),
 * which re-encodes non-ASCII characters as numeric entities ("laŋlaŋ" -> "la&#331;la&#331;"),
 * while the keys of inline_choice_interactions stay raw UTF-8. The viewer now matches blanks
 * to keys after decoding entities, so these tests pin down what the backend must guarantee.
 */
class SelectChoiceNonAsciiTest extends TestCase
{
    use Test;

    private $points = 9;

    /** blank text => [correct value, distractor value] */
    private $blanks = [
        'laŋlaŋ' => ['c-1', 'd-1'],
        'fʊlfʊl' => ['c-2', 'd-2'],
        'oglɪoglɪ' => ['c-3', 'd-3'],
    ];

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->saved_questions_folder = factory(SavedQuestionsFolder::class)->create(['user_id' => $this->user->id, 'type' => 'my_questions']);
    }

    private function qtiArray(array $blanks): array
    {
        $item_body = '<ol>';
        $inline_choice_interactions = [];
        foreach ($blanks as $blank => [$correct, $distractor]) {
            $item_body .= "<li>[$blank]</li>";
            $inline_choice_interactions[$blank] = [
                ['value' => $correct, 'text' => $blank, 'correctResponse' => true],
                ['value' => $distractor, 'text' => "not $blank", 'correctResponse' => false],
            ];
        }
        $item_body .= '</ol>';
        return [
            'questionType' => 'select_choice',
            'responseDeclaration' => ['correctResponse' => []],
            'itemBody' => $item_body,
            'inline_choice_interactions' => $inline_choice_interactions,
            'jsonType' => 'question_json'
        ];
    }

    private function createQuestionInAssignment(array $blanks): Question
    {
        $question = factory(Question::class)->create(['qti_json' => json_encode($this->qtiArray($blanks))]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'points' => $this->points,
            'order' => 1,
            'open_ended_submission_type' => 'none'
        ]);
        return $question;
    }

    #[TestAttribute]
    public function non_ascii_identifiers_pass_select_choice_validation()
    {
        $blanks = $this->blanks + ['café' => ['c-4', 'd-4'], 'very black' => ['c-5', 'd-5']];
        $qti_array = $this->qtiArray($blanks);
        $question_info = [
            'question_type' => 'assessment',
            'folder_id' => $this->saved_questions_folder->id,
            'public' => '0',
            'title' => 'IPA select choice',
            'author' => 'Instructor Kean',
            'tags' => [],
            'technology' => 'qti',
            'technology_id' => null,
            'non_technology_text' => null,
            'text_question' => null,
            'a11y_auto_graded_question_id' => null,
            'answer_html' => null,
            'solution_html' => null,
            'notes' => null,
            'hint' => null,
            'license' => 'publicdomain',
            'license_version' => null,
            'qti_item_body' => $qti_array['itemBody'],
            'qti_json' => json_encode($qti_array),
        ];
        $field_names = ['qti_item_body'];
        foreach ($qti_array['inline_choice_interactions'] as $identifier => $choices) {
            $question_info["qti_select_choice_$identifier"] = $choices;
            $field_names[] = "qti_select_choice_$identifier";
        }

        $this->actingAs($this->user)
            ->postJson('/api/questions', $question_info)
            ->assertJsonMissingValidationErrors($field_names);
    }

    #[TestAttribute]
    public function non_ascii_identifiers_are_graded_correctly()
    {
        $question = $this->createQuestionInAssignment($this->blanks);
        $values = array_values($this->blanks);
        $identifiers = array_keys($this->blanks);

        // all correct => full credit
        $submission = [];
        foreach ($identifiers as $i => $identifier) {
            $submission[] = ['identifier' => $identifier, 'value' => $values[$i][0]];
        }
        $this->actingAs($this->student_user)->postJson('/api/submissions', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'technology' => 'qti',
            'submission' => json_encode($submission)
        ])->assertJson(['type' => 'success']);
        $this->assertEquals($this->points, (float)DB::table('submissions')->where('question_id', $question->id)->value('score'));

        // 2 of 3 correct => 2/3 credit
        $submission[2]['value'] = $values[2][1];
        $this->actingAs($this->student_user)->postJson('/api/submissions', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'technology' => 'qti',
            'submission' => json_encode($submission)
        ])->assertJson(['type' => 'success']);
        $this->assertEqualsWithDelta($this->points * 2 / 3, (float)DB::table('submissions')->where('question_id', $question->id)->value('score'), 0.0001);
    }

    #[TestAttribute]
    public function formatted_item_body_blanks_still_match_choice_keys_after_decoding()
    {
        $blanks = $this->blanks + ['café' => ['c-4', 'd-4'], 'very black' => ['c-5', 'd-5'], "don't" => ['c-6', 'd-6']];
        $qti_json = json_encode($this->qtiArray($blanks));

        // formatQtiJson reads request()->user(); outside an HTTP call that's null unless set here
        request()->setUserResolver(fn() => $this->user);
        $formatted = json_decode((new Question())->formatQtiJson('question_json', $qti_json, null, true), true);

        preg_match_all('/\[(.*?)\]/', $formatted['itemBody'], $matches);
        $this->assertCount(count($blanks), $matches[1], 'Every blank should survive formatting.');

        $keys = array_keys($formatted['inline_choice_interactions']);
        foreach ($matches[1] as $blank_in_body) {
            $decoded = html_entity_decode($blank_in_body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertContains($decoded, $keys,
                "Blank [$blank_in_body] (decoded: $decoded) has no matching inline_choice_interactions key.");
        }
    }
}
