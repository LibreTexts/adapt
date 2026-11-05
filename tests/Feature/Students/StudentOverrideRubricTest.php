<?php

namespace Tests\Feature\Students;

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
 * A student whose score on a question was overridden sees the override, not the rubric.
 */
class StudentOverrideRubricTest extends TestCase
{
    private $student;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $instructor = factory(User::class)->create();
        $course = factory(Course::class)->create(['user_id' => $instructor->id]);
        $section = factory(Section::class)->create(['course_id' => $course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10,
            'use_existing_rubric' => 0,
            'custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true,
                'rubric_items' => [['id' => 'a', 'title' => 'Criterion A', 'description' => '', 'points' => 10]]])
        ]);
        $this->student = factory(User::class)->create(['role' => 3]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student->id,
            'section_id' => $section->id,
            'course_id' => $course->id
        ]);
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode(['a']),
            'adjustment' => 0,
            'needs_review' => 0,
            'rubric_version' => 1,
            'created_at' => now(),
            'updated_at' => now()]);
    }

    private function url(): string
    {
        return "/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student->id}";
    }

    #[Test]
    public function a_student_sees_the_rubric_without_an_override()
    {
        $this->actingAs($this->student)->getJson($this->url())
            ->assertJson(['type' => 'success', 'rubric_shown' => true, 'dynamic_rubric_state' => ['exists' => true]]);
    }

    #[Test]
    public function a_student_with_an_override_does_not_see_the_rubric()
    {
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '7',
            'created_at' => now(),
            'updated_at' => now()]);
        $this->actingAs($this->student)->getJson($this->url())
            ->assertJson(['type' => 'success', 'rubric_shown' => false, 'dynamic_rubric_state' => ['exists' => false]]);
    }
}
