<?php

namespace Tests\Feature;


use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\GraderAccessCode;
use App\School;
use App\Section;
use App\Traits\Test;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CoursesIndexTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->school = factory(School::class)->create();


        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        Enrollment::create(['course_id' => $this->course->id,
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->section_2 = factory(Section::class)->create(['course_id' => $this->course_2->id]);

        $this->course_3 = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section_3 = factory(Section::class)->create(['course_id' => $this->course_3->id]);

        $this->grader_user = factory(User::class)->create();
        $this->grader_user->role = 4;
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section_2->id]);
        GraderAccessCode::create(['section_id' => $this->section_3->id, 'access_code' => 'sdfsdOlwf']);

    }


    /** @test */
    public function linking_to_lms_will_show_if_in_different_account()
    {
        $lms_course_id = 'adsfs';
        $new_course = factory(Course::class)->create([
            'user_id' => $this->user_2->id, 'lms_course_id' => $lms_course_id]);
        $this->actingAs($this->user)
            ->patchJson("/api/courses/{$this->course->id}/link-to-lms", ['lms_course_id' => $lms_course_id])
            ->assertJson(['type' => 'info',
                'message' => "The LMS course is already linked to your ADAPT course $new_course->name. Please log into your account with email address {$this->user_2->email} and unlink the course."]);
    }


    /** @test */
    public function non_admin_cannot_update_disciplines()
    {
        $this->actingAs($this->user_2)
            ->patchJson("/api/courses/{$this->course->id}/update-discipline")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to update the discipline for this course.']);
    }


    /** @test */
    public function cannot_get_the_mini_summary_if_you_do_not_have_an_analytics_token()
    {
        $this->actingAs($this->user_2)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer bad-token'])
            ->getJson("/api/courses/mini-summary")
            ->assertJson(['type' => 'error',
                'message' => 'There was an error getting the course mini-summary.  Please try again or contact us for assistance.']);
    }

    /** @test */
    public function cannot_update_a_course_if_you_are_not_the_owner()
    {
        //create two users
        $this->actingAs($this->user_2)->patchJson("/api/courses/{$this->course->id}", [
            'name' => 'Some New Course',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'school' => $this->school->name,
            'term' => 'some term',
            'crn' => 'some crn',
            'lms' => 0,
            'whitelisted_domains' => ['someDomain.org']
        ])->assertJson(['type' => 'error', 'message' => 'You are not allowed to update this course.']);


    }

    /** @test */
    public function if_shifting_dates_due_time_and_date_must_be_valid()
    {
        $this->actingAs($this->user)->postJson("/api/courses/import/{$this->course_2->id}",
            ['action' => 'clone',
                'shift_dates' => 1,
                'due_date' => 'blah',
                'due_time' => 'sdfsdfdsf'])
            ->assertJsonValidationErrors(['due_time', 'due_date']);
    }

    /** @test */
    public function owner_can_order_courses()
    {

        $course_2 = factory(Course::class)->create(['user_id' => $this->user->id]);
        foreach ([$this->course->id, $course_2->id] as $key => $course_id) {
            DB::table('course_orders')->insert(['user_id' => $this->user->id, 'order' => $key + 1, 'course_id' => $course_id]);
        }
        $this->actingAs($this->user)->patchJson("/api/course-orders", ['ordered_courses' => [$course_2->id, $this->course->id]])
            ->assertJson(['message' => 'Your courses have been re-ordered.']);
        $this->assertDatabaseHas('course_orders', ['course_id' => $this->course->id, 'order' => 2]);
    }

    /** @test */
    public function non_owner_cannot_order_courses()
    {

        $this->actingAs($this->user)->patchJson("/api/course-orders", ['ordered_courses' => [$this->course->id, $this->course_2->id]])
            ->assertJson(['message' => 'You are not allowed to re-order a course that is not yours.']);
    }

    /** @test */
    public function cannot_import_non_public_course()
    {
        $this->course_2->public = 0;
        $this->course_2->save();
        $this->actingAs($this->user)->postJson("/api/courses/import/{$this->course_2->id}")
            ->assertJson(['message' => 'You are not allowed to import that course.']);
    }

    /** @test */
    public function non_instructor_cannot_import_a_course()
    {
        $this->actingAs($this->grader_user)->postJson("/api/courses/import/{$this->course_2->id}")
            ->assertJson(['message' => 'You are not allowed to import that course.']);

    }

    /** @test */
    public function import_action_must_be_copy_or_import()
    {
        $this->actingAs($this->user)->postJson("/api/courses/import/{$this->course_2->id}", ['action' => 'bogus action'])
            ->assertJson(['message' => "bogus action should either be to import or clone."]);
    }


    /** @test */
    public function instructor_can_import_a_course()
    {

        $this->actingAs($this->user)->postJson("/api/courses/import/{$this->course_2->id}", ['action' => 'import'])
            ->assertJson(['type' => "info"]);

    }

    /** @test */
    public function can_copy_your_own_course()
    {
        $this->actingAs($this->user)->postJson("/api/courses/import/{$this->course->id}", ['action' => 'clone'])
            ->assertJson(['type' => 'info']);

    }

    /** @test */
    public function a_non_owner_cannot_toggle_showing_a_course()
    {
        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course_2->id}/show-course/1")
            ->assertJson(['message' => 'You are not allowed to show/hide this course.']);

    }

    /** @test */
    public function an_owner_can_toggle_showing_a_course()
    {
        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course->id}/show-course/1")
            ->assertJson(['message' => 'Your students <strong>cannot</strong> view this course.  In addition, all course access codes have been revoked.']);
    }


    /** @test */
    public function grader_access_code_must_be_valid()
    {

        $this->actingAs($this->user)->postJson("/api/graders",
            ['access_code' => 'some access_code'])
            ->assertJsonValidationErrors(['access_code']);

    }

    /** @test */
    public function only_graders_can_add_themselves_to_courses()
    {

        $this->actingAs($this->user)->postJson("/api/graders",
            ['access_code' => 'sdfsdOlwf'])
            ->assertJson(['message' => 'You are not allowed to add yourself to a course.']);

    }

    /** @test */
    public function a_grader_with_a_valid_access_code_can_add_themselves_to_a_course()
    {

        $this->course_3->name = "New Grader Course";
        $this->course_3->save();
        $this->actingAs($this->grader_user)->postJson("/api/graders",
            ['access_code' => 'sdfsdOlwf'])
            ->assertJson(['message' => 'You have been added as a grader to <strong>New Grader Course - Section 1</strong>.']);

    }


    /** @test */
    public function owner_can_refresh_course_access_code()
    {

    }

    /** @test */
    public function non_owner_cannot_refresh_course_access_code()
    {

    }

    /** @test */
    public function owner_can_remove_a_grader_from_a_course()
    {

    }

    /** @test */
    public function non_owner_cannot_remove_a_grader_from_a_course()
    {

    }

    /** @test */
    public function user_cannot_email_grader_invitation_without_a_valid_email()
    {
        $this->actingAs($this->user)->postJson("/api/invitations/grader",
            ['course_id' => $this->course->id,
                'email' => 'some bad email'])
            ->assertJsonValidationErrors(['email']);
    }


    /** @test */
    public function user_cannot_email_grader_invitation_if_not_owner()
    {
        $this->actingAs($this->user_2)->postJson("/api/invitations/grader",
            ['course_id' => $this->course->id,
                'email' => 'some@email.com'])
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to invite graders to this course.']);
    }


    /** @test */
    public function user_can_email_grader_invitation_if_owner()
    {
        $this->markTestIncomplete(
            'Need to learn how to mock the Mail class'
        );


    }

    /** @test */
    public function grader_can_get_courses_for_which_they_grade()
    {


        $this->actingAs($this->grader_user)->getJson("/api/courses")
            ->assertJson(['courses' => [['name' => 'First Course'], ['name' => 'First Course']]]);

    }


    /** @test */
    public function can_get_your_courses()
    {
        $this->actingAs($this->user)->getJson("/api/courses")
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function cannot_get_courses_if_student()

    {
        $this->user->role = 3;
        $this->actingAs($this->user)->getJson("/api/courses")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to view courses.']);

    }

    /** @test */

    public function correctly_handles_different_timezones()
    {

    }

    /** @test */
    public function can_delete_a_course_if_you_are_the_owner()
    {

        $this->actingAs($this->user)->deleteJson("/api/courses/{$this->course->id}")
            ->assertJson(['message' => "The course <strong>{$this->course->name}</strong> has been deleted."]);

    }

    /** @test */
    public function cannot_delete_a_course_if_you_are_not_the_owner()
    {


        $course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->actingAs($this->user)->deleteJson("/api/courses/$course_2->id")
            ->assertJson(['type' => 'error', 'message' => 'You are not allowed to delete this course.']);


    }


    /** @test */
    public function can_create_a_course()
    {

        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => 'Some New Course',
            'school' => $this->school->name,
            'section' => 'Some New Section',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'term' => 'Some term',
            'crn' => 'Some CRN',
            'lms' => 0,
            'whitelisted_domains' => ['someDomain.com'],
            'public' => 1,
            'alpha' => 0,
            'anonymous_users' => 0
        ])->assertJson(['type' => 'success']);
    }


    /** @test */
    public function term_field_is_required()
    {
        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course->id}", [
            'name' => 'Some New Course',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'crn' => 'some crn',
        ])->assertJsonValidationErrors(['term']);
    }

    /** @test */
    public function can_update_a_course_if_you_are_the_owner()
    {

        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course->id}", [
            'name' => 'Some New Course',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'term' => 'some term',
            'lms' => 0,
            'school' => $this->school->name,
            'whitelisted_domains' => ['someDomain.com'],
            'crn' => 'some crn'
        ])->assertJson(['type' => 'success']);
    }


    /** @test */
    public function must_include_a_course_name()
    {
        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => '',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'term' => 'some term',
            'crn' => 'some crn'
        ])->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function textbook_url_must_be_valid()
    {
        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => 'some really great name',
            'start_date' => '2020-06-10',
            'end_date' => '2021-06-10',
            'term' => 'some term',
            'crn' => 'some crn',
            'textbook_url' => 'not really a URL'
        ])->assertJsonValidationErrors('textbook_url');
    }

    /** @test */
    public function must_include_valid_start_date()
    {
        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => 'Some course',
            'start_date' => 'blah blah',
            'end_date' => '2021-06-10',
            'term' => 'some term',
            'crn' => 'some crn'
        ])->assertJsonValidationErrors(['start_date']);

    }

    /** @test */
    public function must_include_valid_end_date()
    {
        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => 'Some course',
            'start_date' => '2021-06-10',
            'end_date' => 'blah blah',
            'term' => 'some term',
            'crn' => 'some crn'
        ])->assertJsonValidationErrors(['end_date']);

    }

    /** @test */
    public function end_date_must_be_after_start_date()
    {
        $this->actingAs($this->user)->postJson('/api/courses', [
            'name' => 'Some course',
            'start_date' => '2021-06-10',
            'end_date' => '2021-06-09',
            'term' => 'some term',
            'crn' => 'some crn'
        ])->assertJsonValidationErrors(['end_date']);

    }


}
