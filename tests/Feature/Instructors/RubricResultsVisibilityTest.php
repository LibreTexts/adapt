<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GET /api/rubric-points-breakdown/...: students only get their rubric results (static points or dynamic
 * applied items) once scores are released and the rubric is shown; instructors always do.
 */
class RubricResultsVisibilityTest extends TestCase
{
    private $user;
    private $student_user;
    private $course;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
    }

    private function useStaticRubric(bool $rubric_shown = true)
    {
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 10]],
            'rubric_shown' => $rubric_shown])]);
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'points_breakdown' => json_encode(['rubric_items' => [['title' => 'Correct ring', 'description' => '', 'points' => 7]]])]);
    }

    private function useDynamicRubric(bool $rubric_shown = true)
    {
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => $rubric_shown,
            'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 2],
                ['id' => 'b', 'title' => 'Correct arrow', 'description' => '', 'points' => 3]]])]);
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode(['b']),
            'adjustment' => 1,
            'rubric_version' => 1]);
    }

    private function releaseScores(bool $released)
    {
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => $released ? 1 : 0]);
    }

    private function getAs(User $viewer)
    {
        return $this->actingAs($viewer)
            ->getJson("/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student_user->id}")
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function a_student_does_not_see_static_rubric_points_before_scores_are_released()
    {
        $this->useStaticRubric();
        $this->releaseScores(false);
        $response = $this->getAs($this->student_user)->assertJson(['rubric_points_breakdown_exists' => false]);
        $this->assertEquals('', json_decode($response->json('rubric_points_breakdown'), true)['rubric_items'][0]['points']);
    }

    #[Test]
    public function a_student_sees_static_rubric_points_after_scores_are_released()
    {
        $this->useStaticRubric();
        $this->releaseScores(true);
        $response = $this->getAs($this->student_user)->assertJson(['rubric_points_breakdown_exists' => true]);
        $this->assertEquals(7, json_decode($response->json('rubric_points_breakdown'), true)['rubric_items'][0]['points']);
    }

    #[Test]
    public function a_student_does_not_see_rubric_points_when_the_rubric_is_not_shown()
    {
        $this->useStaticRubric(false);
        $this->releaseScores(true);
        $this->getAs($this->student_user)->assertJson(['rubric_points_breakdown_exists' => false, 'rubric_shown' => false]);
    }

    #[Test]
    public function the_instructor_always_sees_the_rubric_points()
    {
        $this->useStaticRubric();
        $this->releaseScores(false);
        $response = $this->getAs($this->user)->assertJson(['rubric_points_breakdown_exists' => true]);
        $this->assertEquals(7, json_decode($response->json('rubric_points_breakdown'), true)['rubric_items'][0]['points']);
    }

    #[Test]
    public function the_grader_view_gets_the_dynamic_rubric_state()
    {
        $this->useDynamicRubric();
        $this->releaseScores(false);
        $this->getAs($this->user)->assertJson(['is_dynamic' => true,
            'dynamic_rubric_state' => ['exists' => true, 'applied_item_ids' => ['b'], 'adjustment' => 1, 'needs_review' => false]]);
    }

    #[Test]
    public function a_student_does_not_see_applied_dynamic_items_before_scores_are_released()
    {
        $this->useDynamicRubric();
        $this->releaseScores(false);
        $response = $this->getAs($this->student_user)
            ->assertJson(['is_dynamic' => true, 'scores_released' => false, 'rubric_points_breakdown_exists' => false]);
        $this->assertEquals(['exists' => false, 'applied_item_ids' => [], 'adjustment' => 0, 'needs_review' => false],
            $response->json('dynamic_rubric_state'));
    }

    #[Test]
    public function a_student_sees_applied_dynamic_items_after_scores_are_released()
    {
        $this->useDynamicRubric();
        $this->releaseScores(true);
        $this->getAs($this->student_user)->assertJson(['is_dynamic' => true, 'scores_released' => true,
            'dynamic_rubric_state' => ['exists' => true, 'applied_item_ids' => ['b'], 'adjustment' => 1]]);
    }
}
