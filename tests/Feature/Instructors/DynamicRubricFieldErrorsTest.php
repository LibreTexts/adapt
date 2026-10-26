<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Question;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dynamic rubric validation errors come back per criterion and field, so the editor can show each under its field.
 */
class DynamicRubricFieldErrorsTest extends TestCase
{
    private $user;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $course->id]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
    }

    private function preview(array $rubric_items)
    {
        return $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric/preview",
                ['base_version' => 0, 'rubric_shown' => true, 'rubric_items' => $rubric_items]);
    }

    #[Test]
    public function each_error_is_returned_under_its_criterion_and_field()
    {
        $item_errors = $this->preview([
            ['title' => 'Correct ring', 'description' => '', 'points' => 4],
            ['title' => 'Correct ring', 'description' => str_repeat('x', 5001), 'points' => 'lots'],
            ['title' => '', 'description' => '', 'points' => 2]])
            ->assertJson(['type' => 'error', 'general_errors' => []])
            ->json('item_errors');
        $this->assertEquals(['title' => 'passes', 'description' => 'passes', 'points' => 'passes'], $item_errors[0]);
        $this->assertEquals(['title' => 'This title already exists.',
            'description' => 'The description can be at most 5000 characters.',
            'points' => 'The points should be a number that is at least 0.'], $item_errors[1]);
        $this->assertEquals('A title is required.', $item_errors[2]['title']);
    }

    #[Test]
    public function a_valid_rubric_has_no_item_errors()
    {
        $this->preview([['title' => 'Correct ring', 'description' => '', 'points' => 4]])
            ->assertJson(['type' => 'success'])
            ->assertJsonMissingPath('item_errors');
    }
}
