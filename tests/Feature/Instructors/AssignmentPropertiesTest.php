<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\Question;
use App\Section;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

class AssignmentPropertiesTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;

        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);


        $this->student_user_2 = factory(User::class)->create();
        $this->student_user_2->role = 3;

    }


    /** @test * */
    public function the_correct_formatted_late_policy_is_retrieved_for_not_accepted()
    {
        $this->assignment->late_policy = 'not accepted';
        $this->assignment->save();
        $response['assignment'] = ['formatted_late_policy' => "No late submissions are accepted."];
        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/summary")
            ->assertJson($response);

    }


    /** @test */

    public function user_cannot_change_to_external_if_a_question_already_exists()
    {
        $this->question = factory(Question::class)->create(['page_id' => 112356]);

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
        $this->assignment->source = 'a';
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/validate-assessment-type",
            ['source' => 'x'])->assertJson(['message' => "You can't switch to an external assignment until you remove all ADAPT questions from the assignment."]);
    }


    /** @test */

    public function user_cannot_change_to_a_non_learning_tree_from_learning_tree_if_learning_tree_exists()
    {
        $this->question = factory(Question::class)->create(['page_id' => 187376]);

        $assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);
        $this->learning_tree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        //create a student and enroll in the class
        DB::table('assignment_question_learning_tree')->insertGetId([
            'assignment_question_id' => $assignment_question_id,
            'learning_tree_id' => $this->learning_tree->id,
            'number_of_successful_paths_for_a_reset' => 1
        ]);
        $this->assignment->assessment_type = 'learning tree';
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/validate-assessment-type",
            ['assessment_type' => 'real time'])
            ->assertJson(['message' => "You can't switch to a real time assessment type since this is not a learning tree assignment."]);
    }


    /** @test */

    public function user_cannot_change_to_learning_tree_if_regular_question_exists()
    {
        $this->question = factory(Question::class)->create(['page_id' => 212361]);

        DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);

        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/validate-assessment-type",
            ['assessment_type' => 'learning tree'])
            ->assertJson(['message' => "You can't switch to a learning tree assessment type since this is not a learning tree assignment and you already have non-learning tree questions."]);
    }

    /** @test */
    public function time_limit_is_included_in_the_assignment_summary_for_the_instructor()
    {
        AssignToTiming::where('assignment_id', $this->assignment->id)->update(['time_limit' => '2 minutes']);

        $response['assignment']['assign_tos'][0]['time_limit'] = '2 minutes';
        $this->actingAs($this->user)->getJson("/api/assignments/{$this->assignment->id}/summary")
            ->assertJson($response);
    }

    /** @test */
    public function time_limit_is_included_when_listing_assignments_for_the_instructor()
    {
        AssignToTiming::where('assignment_id', $this->assignment->id)->update(['time_limit' => '90 seconds']);

        $expected['assignments'][0]['assign_tos'][0]['time_limit'] = '90 seconds';
        $this->actingAs($this->user)->getJson("/api/assignments/courses/{$this->course->id}")
            ->assertJson($expected);
    }


}
