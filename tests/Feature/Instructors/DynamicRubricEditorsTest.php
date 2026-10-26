<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Helpers\DynamicRubric;
use App\Helpers\RubricStyleChange;
use App\Question;
use App\Rules\IsValidRubricItems;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 3: scoring style in the rubric editors, templates, question rubrics and the style-change protection.
 */
class DynamicRubricEditorsTest extends TestCase
{
    private $user;
    private $course;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
    }

    private function itemsAreValid(array $rubric_items, string $scoring_style): bool
    {
        return Validator::make(['rubric_items' => $rubric_items],
            ['rubric_items' => ['required', new IsValidRubricItems($scoring_style)]])->passes();
    }

    private function addToNewAssignment(array $assignment_question_values = [])
    {
        $assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        DB::table('assignment_question')->insert(array_merge([
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ], $assignment_question_values));
        return $assignment;
    }

    private function assignmentQuestion(Assignment $assignment)
    {
        return DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->first();
    }

    #[Test]
    public function dynamic_rubrics_allow_zero_point_items_and_static_rubrics_still_do_not()
    {
        $items = [['title' => 'Comment only', 'description' => '', 'points' => 0]];
        $this->assertTrue($this->itemsAreValid($items, 'dynamic'));
        $this->assertFalse($this->itemsAreValid($items, 'static'));
        $this->assertFalse($this->itemsAreValid([['title' => 'Negative', 'description' => '', 'points' => -1]], 'dynamic'));
        $this->assertFalse($this->itemsAreValid([['title' => 'No points', 'description' => '', 'points' => '']], 'dynamic'));
    }

    #[Test]
    public function validate_rubric_items_endpoint_follows_the_scoring_style()
    {
        $items = [['title' => 'Comment only', 'description' => '', 'points' => 0]];
        $this->actingAs($this->user)->postJson('/api/rubric-templates/validate-rubric-items',
            ['save_as_template' => false, 'rubric_items' => $items, 'rubric_shown' => true, 'scoring_style' => 'dynamic'])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->postJson('/api/rubric-templates/validate-rubric-items',
            ['save_as_template' => false, 'rubric_items' => $items, 'rubric_shown' => true, 'scoring_style' => 'static'])
            ->assertStatus(422);
    }

    #[Test]
    public function the_old_name_classic_is_still_accepted_and_treated_as_static()
    {
        //a page loaded before the classic -> static rename may still send 'classic'
        $this->actingAs($this->user)->postJson('/api/rubric-templates/validate-rubric-items',
            ['save_as_template' => false,
                'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]],
                'rubric_shown' => true,
                'scoring_style' => 'classic'])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->postJson('/api/rubric-templates/validate-rubric-items',
            ['save_as_template' => false,
                'rubric_items' => [['title' => 'Comment only', 'description' => '', 'points' => 0]],
                'rubric_shown' => true,
                'scoring_style' => 'classic'])
            ->assertStatus(422);
        $this->actingAs($this->user)->postJson('/api/rubric-templates/validate-rubric-items',
            ['save_as_template' => false,
                'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]],
                'rubric_shown' => true,
                'scoring_style' => 'something-else'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_dynamic_template_keeps_its_scoring_style_but_not_item_ids()
    {
        $this->actingAs($this->user)->postJson('/api/rubric-templates', [
            'name' => 'Ring mechanisms',
            'description' => 'Organic chemistry',
            'save_as_template' => true,
            'rubric_shown' => true,
            'scoring_style' => 'dynamic',
            'rubric_items' => [
                ['id' => 'abc12345', 'title' => 'Correct ring', 'description' => '', 'points' => 2],
                ['title' => 'Comment only', 'description' => '', 'points' => 0]]])
            ->assertJson(['type' => 'success']);
        $template_rubric = json_decode(DB::table('rubric_templates')->where('name', 'Ring mechanisms')->first()->rubric, true);
        $this->assertEquals('dynamic', $template_rubric['scoring_style']);
        $this->assertArrayNotHasKey('id', $template_rubric['rubric_items'][0]);
        $this->assertCount(2, $template_rubric['rubric_items']);
    }

    #[Test]
    public function a_static_template_is_saved_as_before()
    {
        $this->actingAs($this->user)->postJson('/api/rubric-templates', [
            'name' => 'Static template',
            'description' => 'Static',
            'save_as_template' => true,
            'rubric_shown' => true,
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]])
            ->assertJson(['type' => 'success']);
        $template_rubric = json_decode(DB::table('rubric_templates')->where('name', 'Static template')->first()->rubric, true);
        $this->assertEquals(['rubric_shown' => true,
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]], $template_rubric);
    }

    #[Test]
    public function a_question_rubric_keeps_item_ids_across_saves_and_the_version_goes_up()
    {
        $first = DynamicRubric::prepareQuestionRubric([
            ['title' => 'Correct ring', 'description' => '', 'points' => 2],
            ['title' => 'Correct arrow', 'description' => '', 'points' => 2]], true, null);
        $this->assertEquals(1, $first['version']);
        $first_ids = DynamicRubric::itemIds($first);
        $this->assertCount(2, array_unique($first_ids));

        $second = DynamicRubric::prepareQuestionRubric([
            ['id' => $first_ids[1], 'title' => 'Correct arrow', 'description' => '', 'points' => 3],
            ['id' => 'made-up-id', 'title' => 'New item', 'description' => '', 'points' => 1]], true, json_encode($first));
        $this->assertEquals(2, $second['version']);
        $this->assertEquals($first_ids[1], $second['rubric_items'][0]['id']);
        $this->assertNotEquals('made-up-id', $second['rubric_items'][1]['id']);
        $this->assertNotContains($second['rubric_items'][1]['id'], $first_ids);
    }

    #[Test]
    public function switching_a_question_rubric_style_keeps_the_old_rubric_for_assignments_already_graded_with_it()
    {
        $static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]], 'rubric_shown' => true]);
        $dynamic_rubric = json_encode(DynamicRubric::prepareQuestionRubric(
            [['title' => 'Correct ring', 'description' => '', 'points' => 2]], true, null));
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => $static_rubric]);

        $graded_assignment = $this->addToNewAssignment();
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $graded_assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->user->id,
            'points_breakdown' => json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]]])]);
        $ungraded_assignment = $this->addToNewAssignment();
        $own_override = json_encode(['rubric_items' => [['title' => 'Own item', 'description' => '', 'points' => 5]], 'rubric_shown' => true]);
        $override_assignment = $this->addToNewAssignment(['custom_rubric' => $own_override, 'use_existing_rubric' => 0]);

        $this->assertEquals(1, RubricStyleChange::keepAssignmentRubricsBeforeStyleChange($this->question->id, $static_rubric, $dynamic_rubric));

        $graded = $this->assignmentQuestion($graded_assignment);
        $this->assertEquals(0, (int)$graded->use_existing_rubric);
        $this->assertEquals(json_decode($static_rubric, true), json_decode($graded->custom_rubric, true));
        $this->assertNull($this->assignmentQuestion($ungraded_assignment)->custom_rubric);
        $this->assertEquals(json_decode($own_override, true), json_decode($this->assignmentQuestion($override_assignment)->custom_rubric, true));
    }

    #[Test]
    public function an_edit_that_keeps_the_same_style_changes_no_assignment()
    {
        $static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2]], 'rubric_shown' => true]);
        $edited_static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 3]], 'rubric_shown' => true]);
        $graded_assignment = $this->addToNewAssignment();
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $graded_assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->user->id,
            'points_breakdown' => json_encode(['rubric_items' => []])]);
        $this->assertEquals(0, RubricStyleChange::keepAssignmentRubricsBeforeStyleChange($this->question->id, $static_rubric, $edited_static_rubric));
        $this->assertNull($this->assignmentQuestion($graded_assignment)->custom_rubric);
    }

    #[Test]
    public function the_static_custom_rubric_save_refuses_a_dynamic_rubric()
    {
        $assignment = $this->addToNewAssignment();
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$assignment->id}/questions/{$this->question->id}/update-custom-rubric",
                ['custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true,
                    'rubric_items' => [['id' => 'a', 'title' => 'x', 'description' => '', 'points' => 1]]])])
            ->assertJson(['type' => 'error', 'message' => 'Dynamic rubrics are saved with the dynamic rubric editor.']);
    }

    #[Test]
    public function an_assignment_can_create_a_dynamic_rubric_through_the_dynamic_editor()
    {
        $assignment = $this->addToNewAssignment();
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$assignment->id}/questions/{$this->question->id}/dynamic-rubric", [
                'base_version' => 0,
                'rubric_shown' => true,
                'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 2],
                    ['title' => 'Comment only', 'description' => '', 'points' => 0]]])
            ->assertJson(['type' => 'success', 'number_of_students_rescored' => 0]);
        $rubric = json_decode($this->assignmentQuestion($assignment)->custom_rubric, true);
        $this->assertEquals('dynamic', $rubric['scoring_style']);
        $this->assertEquals(1, $rubric['version']);
        $this->assertCount(2, DynamicRubric::itemIds($rubric));
    }
}
