<?php

namespace Tests\Feature\Instructors;

use App\AssignmentGroupWeight;
use App\Course;
use App\Grader;
use App\Section;
use App\User;
use App\Assignment;
use Tests\TestCase;

use App\Traits\Test;

class AssignmentsIndex1Test extends TestCase
{

    use Test;

    /**Still must test the stuff with the correct/completed and number**/
    /** Should test that only an instructor can create an assignment... */
    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->user_2 = factory(User::class)->create();
        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course_2->id]);

        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;

        $this->grader_user = factory(User::class)->create();
        $this->grader_user->role = 4;
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->assignment_info = ['course_id' => $this->course->id,
            'name' => 'First Assignment',
            'available_from_date' => '2020-06-10',
            'available_from_time' => '09:00:00',
            'available_from' => '2020-06-10 09:00:00',
            'due_date' => '2020-06-12',
            'due_time' => '09:00:00',
            'due' => '2020-06-12 09:00:00',
            'scoring_type' => 'p',
            'source' => 'a',
            'default_points_per_question' => 2,
            'students_can_view_assignment_statistics' => 0,
            'include_in_weighted_average' => 1,
            'submission_files' => 'a',
            'instructions' => 'Some instructions',
            'assignment_group_id' => 1];

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_cannot_release_letter_grades_if_assignment_weights_are_not_100()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/final-grades/{$this->course->id}/release-letter-grades/1")
            ->assertJson(['message' => 'Please first update your Assignment Group Weights so that the total weighting is equal to 100.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_release_letter_grades()
    {

        AssignmentGroupWeight::create(['course_id' => $this->course->id,
            'assignment_group_id' => $this->assignment_info['assignment_group_id'],
            'assignment_group_weight' => 100]);
        $this->actingAs($this->user)
            ->patchJson("/api/final-grades/{$this->course->id}/release-letter-grades/1")
            ->assertJson(['message' => 'The letter grades <strong>are not</strong> released.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_toggle_show_question_titles()
    {
        $this->actingAs($this->user_2)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-question-titles")
            ->assertJson(['message' => 'You are not allowed to toggle whether students can view question titles.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_toggle_question_url_view()
    {
        $this->actingAs($this->user_2)
            ->patchJson("/api/assignments/{$this->assignment->id}/question-url-view")
            ->assertJson(['message' => 'You are not allowed to update the question URL view for this assignment.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function nonowner_cannot_toggle_graders_seeing_student_names()
    {
        $this->actingAs($this->user_2)
            ->patchJson("/api/assignments/{$this->assignment->id}/graders-can-see-student-names/1")
            ->assertJson(['message' => "You are not allowed to switch whether graders can view their students' names."]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_cannot_toggle_graders_seeing_student_names()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/graders-can-see-student-names/1")
            ->assertJson(['message' => "Graders <strong>cannot</strong> see their students' names."]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function nonowner_cannot_create_new_assignment_group()
    {

        $this->actingAs($this->user_2)
            ->postJson("/api/assignmentGroups/{$this->course->id}", ['assignment_group' => 'some group'])
            ->assertJson(['message' => 'You are not allowed to create an assignment group for this course.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_new_assignment_group()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignmentGroups/{$this->course->id}", ['assignment_group' => 'some group'])
            ->assertJson(['message' => '<strong>some group</strong> has been added as an assignment group.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function assignment_group_must_not_be_empty()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignmentGroups/{$this->course->id}", ['assignment_group' => ''])
            ->assertJsonValidationErrors(['assignment_group']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_your_assignments()
    {
        $expected['assignments'][0]['name'] = 'First Assignment';
        $this->actingAs($this->user)->getJson("/api/assignments/courses/{$this->course->id}")
            ->assertJson($expected);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function must_submit_a_valid_scoring_type()
    {
        $this->markTestIncomplete(
            'check if c or p'
        );

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_submit_scoring_type_completed()
    {
        $assignment_info = $this->assignment_info;
        unset($assignment_info['default_points_per_question']);
        $this->markTestIncomplete(
            'submit without the default points'
        );

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_get_assignments_if_you_are_a_student()
    {
        $this->user->role = 3;
        $this->actingAs($this->user)->getJson("/api/assignments/courses/{$this->course->id}")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to access this course.']);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_delete_an_assignment_if_you_are_the_owner()
    {
        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment->id}")
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_an_assignment_if_you_are_not_the_owner()
    {
        $this->actingAs($this->user)->deleteJson("/api/assignments/{$this->assignment_2->id}")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to delete this assignment.']);

    }
}
