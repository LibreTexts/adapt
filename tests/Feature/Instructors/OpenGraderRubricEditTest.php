<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Grader;
use App\HeadGrader;
use App\Helpers\RubricEditPermission;
use App\Question;
use App\Section;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stage 5: editing a static rubric from the Open Grader. Graders need "Can edit rubric".
 */
class OpenGraderRubricEditTest extends TestCase
{
    private $user;
    private $grader_user;
    private $course;
    private $assignment;
    private $question;
    private $static_rubric;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'rubric_shown' => true, 'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]]])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        $this->grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $section->id]);
        $this->static_rubric = json_encode(['rubric_items' => [
            ['title' => 'Correct ring', 'description' => '', 'points' => 6],
            ['title' => 'Correct arrow', 'description' => '', 'points' => 4]], 'rubric_shown' => true]);
    }

    private function saveStaticRubric(User $user)
    {
        return $this->actingAs($user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-custom-rubric",
                ['custom_rubric' => $this->static_rubric]);
    }

    private function customRubric()
    {
        return DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first();
    }

    #[Test]
    public function a_grader_without_permission_cannot_save_a_static_rubric_and_is_told_what_to_do()
    {
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_MESSAGE]);
        $this->assertNull($this->customRubric()->custom_rubric);
    }

    #[Test]
    public function a_head_grader_without_permission_gets_the_head_grader_message()
    {
        HeadGrader::create(['course_id' => $this->course->id, 'user_id' => $this->grader_user->id]);
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::HEAD_GRADER_MESSAGE]);
    }

    #[Test]
    public function a_grader_given_permission_can_save_a_static_rubric()
    {
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $this->grader_user->id]);
        $this->saveStaticRubric($this->grader_user)->assertJson(['type' => 'success']);
        $assignment_question = $this->customRubric();
        $this->assertEquals(0, (int)$assignment_question->use_existing_rubric);
        $this->assertEquals(json_decode($this->static_rubric, true), json_decode($assignment_question->custom_rubric, true));
    }

    #[Test]
    public function the_show_endpoint_tells_the_open_grader_whether_a_grader_can_edit()
    {
        $this->actingAs($this->grader_user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric")
            ->assertJson(['type' => 'success', 'can_edit' => false, 'can_edit_message' => RubricEditPermission::GRADER_MESSAGE]);
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $this->grader_user->id]);
        $this->actingAs($this->grader_user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric")
            ->assertJson(['type' => 'success', 'can_edit' => true, 'is_dynamic' => false]);
    }

    #[Test]
    public function the_instructor_can_still_save_and_another_instructor_cannot()
    {
        $this->saveStaticRubric($this->user)->assertJson(['type' => 'success']);
        $this->saveStaticRubric(factory(User::class)->create())
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to update the custom rubric for that question.']);
    }

    #[Test]
    public function permission_in_another_course_does_not_count()
    {
        $other_course = factory(Course::class)->create(['user_id' => $this->user->id]);
        DB::table('rubric_editors')->insert(['course_id' => $other_course->id, 'user_id' => $this->grader_user->id]);
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_MESSAGE]);
    }
    #[Test]
    public function ids_left_on_a_static_rubric_do_not_block_switching_it_to_dynamic()
    {
        //a rubric switched from dynamic to static (before anyone was graded) may still carry the old item ids
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['use_existing_rubric' => 0, 'custom_rubric' => json_encode(['rubric_shown' => true, 'rubric_items' => [
                ['id' => 'oldid001', 'title' => 'Correct ring', 'description' => '', 'points' => 6],
                ['id' => 'oldid002', 'title' => 'Correct arrow', 'description' => '', 'points' => 4]]])]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric", [
                'base_version' => 0,
                'rubric_shown' => true,
                'rubric_items' => [
                    ['id' => 'oldid001', 'title' => 'Correct ring', 'description' => '', 'points' => 6],
                    ['id' => 'oldid002', 'title' => 'Correct arrow', 'description' => '', 'points' => 4]]])
            ->assertJson(['type' => 'success']);
        $rubric = json_decode($this->customRubric()->custom_rubric, true);
        $this->assertEquals('dynamic', $rubric['scoring_style']);
        $ids = array_column($rubric['rubric_items'], 'id');
        $this->assertCount(2, array_unique($ids));
        $this->assertEmpty(array_intersect($ids, ['oldid001', 'oldid002']));
    }
    private function allowGrader()
    {
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $this->grader_user->id]);
    }

    private function dynamicRubricUrl(string $suffix = ''): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function makeQuestionRubricDynamic()
    {
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true,
            'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 10]]])]);
    }

    #[Test]
    public function a_grader_cannot_change_the_scoring_style()
    {
        $this->allowGrader();
        //static -> dynamic
        $this->actingAs($this->grader_user)->postJson($this->dynamicRubricUrl('/preview'), [
            'base_version' => 0, 'rubric_shown' => true,
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]]])
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
        $this->actingAs($this->grader_user)->patchJson($this->dynamicRubricUrl(), [
            'base_version' => 0, 'rubric_shown' => true,
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]]])
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
        $this->assertNull($this->customRubric()->custom_rubric);

        //dynamic -> static
        $this->makeQuestionRubricDynamic();
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
        $this->assertNull($this->customRubric()->custom_rubric);
    }

    #[Test]
    public function a_grader_cannot_change_whether_students_see_the_rubric()
    {
        $this->allowGrader();
        $this->static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]],
            'rubric_shown' => false]);
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_RUBRIC_SHOWN_MESSAGE]);

        $this->makeQuestionRubricDynamic();
        $this->actingAs($this->grader_user)->patchJson($this->dynamicRubricUrl(), [
            'base_version' => 1, 'rubric_shown' => false,
            'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 10]]])
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_RUBRIC_SHOWN_MESSAGE]);
        //criteria changes are fine
        $this->actingAs($this->grader_user)->patchJson($this->dynamicRubricUrl(), [
            'base_version' => 1, 'rubric_shown' => true,
            'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 8]]])
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function a_grader_adding_a_rubric_gets_a_static_rubric_shown_to_students()
    {
        $this->allowGrader();
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => null]);
        $this->static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]],
            'rubric_shown' => false]);
        $this->saveStaticRubric($this->grader_user)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_RUBRIC_SHOWN_MESSAGE]);
        $this->static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]],
            'rubric_shown' => true]);
        $this->saveStaticRubric($this->grader_user)->assertJson(['type' => 'success']);
    }

    #[Test]
    public function the_instructor_can_still_change_the_style_and_visibility()
    {
        $this->static_rubric = json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]],
            'rubric_shown' => false]);
        $this->saveStaticRubric($this->user)->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->patchJson($this->dynamicRubricUrl(), [
            'base_version' => 0, 'rubric_shown' => true,
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]]])
            ->assertJson(['type' => 'success']);
    }
}
