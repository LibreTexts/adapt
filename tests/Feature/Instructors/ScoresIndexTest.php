<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignmentGroupWeight;
use App\AssignToTiming;
use App\AssignToUser;
use App\Course;
use App\Enrollment;
use App\FinalGrade;
use App\Grader;
use App\Score;
use App\Section;
use App\User;
use App\ExtraCredit;
use App\Extension;
use App\Traits\Test;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoresIndexTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;

        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section_2 = factory(Section::class)->create(['course_id' => $this->course_2->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        //enroll a student in that course
        $assignToTiming = new AssignToTiming();
        $assignToTiming->assignment_id = $this->assignment->id;
        $assignToTiming->available_from = Carbon::now();
        $assignToTiming->due = Carbon::now()->addDay();
        $assignToTiming->save();

        $assignToUser = new AssignToUser();
        $assignToUser->assign_to_timing_id = $assignToTiming->id;
        $assignToUser->user_id = $this->student_user->id;

        $assignToUser->save();

        $this->student_user_2 = factory(User::class)->create();//not enrolled
        $this->student_user->role = 3;

        $this->grader_user = factory(User::class)->create();
        $this->grader_user->role = 4;
        $this->grader = Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);

        $finalGrade = new FinalGrade();

        FinalGrade::create(['course_id' => $this->course->id,
            'letter_grades' => $finalGrade->defaultLetterGrades()]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
public function correctly_computes_the_final_score_with_randomized_assessment_assignments(){
    AssignmentGroupWeight::create([
        'course_id' => $this->course->id,
        'assignment_group_id' => 1,
        'assignment_group_weight' => 100
    ]);

    $this->assignment->default_points_per_question = 10;
    $this->assignment->number_of_randomized_assessments = 1;
    $this->assignment->save();

    DB::table('assignment_question')->insert([
        'assignment_id' => $this->assignment->id,
        'question_id' => $this->question()->id,
        'order' => 1,
        'open_ended_submission_type' => 'none',
        'points' => 10
    ]);

    DB::table('assignment_question')->insert([
        'assignment_id' => $this->assignment->id,
        'question_id' => $this->question()->id,
        'order' => 1,
        'open_ended_submission_type' => 'none',
        'points' => 10
    ]);

    Score::create([
        'user_id' => $this->student_user->id,
        'assignment_id' => $this->assignment->id,
        'open_ended_submission_type' => 'none',
        'score' => 8
    ]);

    $assignments = Assignment::all();
    foreach ($assignments as $assignment) {
        $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
    }

    $response = $this->actingAs($this->user)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0");

    //only 1 assessment so 10 points.  And, the student got 8 out of 10
    $this->assertEquals('80%', array_values($response['table']['rows'][0])[2]);

}
    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_final_scores_if_not_all_assignments_are_included_in_the_final_score()
    {

        //3 assignments with 2 different weights
        //don't include the first assignment in the scoring
        $this->assignment->include_in_weighted_average = 0;
        $this->assignment->save();
        $this->createAssignmentGroupWeightsAndAssignments();
        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
        }

        $response = $this->actingAs($this->user)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0");
        $weighted_score_assignment_id = $response->baseResponse->original['weighted_score_assignment_id'];
        $this->assertEquals('49.17%', $response->baseResponse->original['table']['rows'][0][$weighted_score_assignment_id]);//see computation above

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_update_assignment_score_if_grader_in_section()
    {
        $this->actingAs($this->grader_user)->patchJson("/api/scores/{$this->assignment->id}/{$this->student_user->id}",
            [
                'score' => 3
            ])
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_update_assignment_score_if_not_grader_in_section()
    {
        $this->grader->section_id = $this->section_2->id;
        $this->grader->save();

        $this->actingAs($this->grader_user)->patchJson("/api/scores/{$this->assignment->id}/{$this->student_user->id}",
            [
                'score' => 3
            ])
            ->assertJson(['message' => 'You are not allowed to update this score.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_is_warned_when_creating_an_extension_if_scores_are_shown()
    {
        $this->createExtensionForTesting();
        $this->assignment->show_scores = 1;
        $this->assignment->save();
        $this->actingAs($this->user)->getJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}")
            ->assertJson(['extension_warning' => 'Before providing an extension please note that:  The assignment scores have been released.  ']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_is_warned_when_creating_an_extension_if_solutions_are_released()
    {
        $this->createExtensionForTesting();
        $this->assignment->solutions_released = 1;
        $this->assignment->save();
        $this->actingAs($this->user)->getJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}")
            ->assertJson(['extension_warning' => 'Before providing an extension please note that:  The assignment solutions are available.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function instructors_can_log_in_as_students_in_their_courses()
    {
        $this->actingAs($this->user)->postJson('/api/user/login-as-student-in-course',
            [
                'course_id' => $this->course->id,
                'student_user_id' => $this->student_user->id
            ])
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function instructors_cannot_log_in_as_students_not_in_their_courses()
    {
        $this->actingAs($this->user)->postJson('/api/user/login-as-student-in-course',
            [
                'course_id' => $this->course->id,
                'student_user_id' => $this->student_user_2->id
            ])
            ->assertJson(['message' => 'You are not allowed to log in as this student.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function graders_can_log_in_as_students_in_their_courses()
    {
        $this->actingAs($this->grader_user)->postJson('/api/user/login-as-student-in-course',
            [
                'course_id' => $this->course->id,
                'student_user_id' => $this->student_user->id
            ])
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function if_a_student_is_not_in_the_course_a_user_cannot_get_their_extra_credit()
    {

        $this->actingAs($this->user)->getJson("/api/extra-credit/{$this->course->id}/{$this->student_user_2->id}")
            ->assertJson(['message' => 'You are not allowed to view this student\'s extra credit.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function if_a_student_is_in_the_course_a_user_can_get_their_extra_credit()
    {
        ExtraCredit::create(['course_id' => $this->course->id,
            'user_id' => $this->student_user->id,
            'extra_credit' => 10]);
        $this->actingAs($this->user)->getJson("/api/extra-credit/{$this->course->id}/{$this->student_user->id}")
            ->assertJson(['extra_credit' => '10.00']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function if_a_student_is_not_in_the_course_a_user_cannot_udpate_extra_credit()
    {

        $this->actingAs($this->user)->postJson("/api/extra-credit", [
            'course_id' => $this->course->id,
            'student_user_id' => $this->student_user_2->id,
            'extra_credit' => 5])
            ->assertJson(['message' => 'You are not allowed to give this student extra credit.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function if_a_student_is_in_the_course_a_user_can_udpate_extra_credit()
    {
        $this->actingAs($this->user)->postJson("/api/extra-credit", [
            'course_id' => $this->course->id,
            'student_user_id' => $this->student_user->id,
            'extra_credit' => 5])
            ->assertJson(['message' => 'The student has been given extra credit.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_extra_credit_amount_must_be_valid()
    {
        $this->actingAs($this->user)->postJson("/api/extra-credit", [
            'course_id' => $this->course->id,
            'student_user_id' => $this->student_user->id,
            'extra_credit' => -5])
            ->assertJsonValidationErrors(['extra_credit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_final_scores()
    {

        //4 assignments with 2 different weights
        $without_extra_credit = 51.11;
        $extra_credit = 10;
        $this->createAssignmentGroupWeightsAndAssignments();

        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
        }

        ExtraCredit::create(['course_id' => $this->course->id,
            'user_id' => $this->student_user->id,
            'extra_credit' => $extra_credit]);
        $response = $this->actingAs($this->user)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0");
        $weighted_score_assignment_id = $response->baseResponse->original['weighted_score_assignment_id'];
        $this->assertEquals(($without_extra_credit + $extra_credit) . '%', $response->baseResponse->original['table']['rows'][0][$weighted_score_assignment_id]);//see computation above

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_final_scores_with_extra_credit()
    {

        //4 assignments with 2 different weights
        $this->createAssignmentGroupWeightsAndAssignments();
        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
        }
        $response = $this->actingAs($this->user)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0");
        $weighted_score_assignment_id = $response->baseResponse->original['weighted_score_assignment_id'];
        $this->assertEquals('51.11%', $response->baseResponse->original['table']['rows'][0][$weighted_score_assignment_id]);//see computation above

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_update_assignment_score_if_owner()
    {
        $this->actingAs($this->user)->patchJson("/api/scores/{$this->assignment->id}/{$this->student_user->id}",
            [
                'score' => 3
            ])
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_update_assignment_score_if_not_owner()
    {
        $this->actingAs($this->user_2)->patchJson("/api/scores/{$this->assignment->id}/{$this->student_user->id}",
            [
                'score' => 3
            ])
            ->assertJson([
                'type' => 'error',
                'message' => 'You are not allowed to update this score.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_update_or_add_extension_if_owner()
    {
        $this->actingAs($this->user)->postJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}",
            [
                'extension_date' => '2027-09-02',
                'extension_time' => '09:00:00'
            ])
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function extension_date_cannot_be_in_the_past()
    {
        $this->actingAs($this->user)->postJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}",
            [
                'extension_date' => '2019-09-02',
                'extension_time' => '09:00:00'
            ])
            ->assertJsonValidationErrors('extension_date');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function extension_time_must_be_a_time()
    {
        $this->actingAs($this->user)->postJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}",
            [
                'extension_date' => '2029-09-02',
                'extension_time' => 'not a time'
            ])
            ->assertJsonValidationErrors('extension_time');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_update_or_add_extension_if_not_owner()
    {
        $this->actingAs($this->user_2)->postJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}",
            [
                'extension_date' => '2029-09-02',
                'extension_time' => '09:00:00'
            ])
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to create an extension for this student/assignment.']);

    }

    public function createExtensionForTesting()
    {
        //create an extension
        return factory(Extension::class)->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student_user->id
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_extension_if_owner()
    {
        $this->createExtensionForTesting();
        $this->actingAs($this->user)->getJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}")
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_get_extension_for_student_if_not_owner()
    {
        $this->createExtensionForTesting();
        $this->actingAs($this->user_2)->getJson("/api/extensions/{$this->assignment->id}/{$this->student_user->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to view this extension.']);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_course_scores_if_owner()
    {
        $this->actingAs($this->user)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0")
            ->assertJson(['hasAssignments' => true]);//for the fake student
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_get_course_scores_if_not_owner()
    {

        $this->actingAs($this->user_2)->getJson("/api/scores/{$this->course->id}/{$this->section->id}/0")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to view these scores.']);//for the fake student

    }
}
