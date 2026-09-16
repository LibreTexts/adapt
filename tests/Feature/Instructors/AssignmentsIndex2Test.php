<?php

namespace Tests\Feature\Instructors;

use App\AssignToGroup;
use App\AssignToTiming;
use App\AssignToUser;
use App\BetaAssignment;
use App\BetaCourse;
use App\Course;
use App\Enrollment;
use App\FinalGrade;
use App\Grader;
use App\LearningTree;
use App\Question;
use App\RandomizedAssignmentQuestion;
use App\Section;
use App\SubmissionFile;
use App\User;
use App\Assignment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

class AssignmentsIndex2Test extends TestCase
{
    use Test;

    /**Still must test the stuff with the correct/completed and number**/
    /** Should test that only an instructor can create an assignment... */
    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->student_user = factory(User::class)->create(['role' => 3]);


        $this->student_user_2 = factory(User::class)->create(['role' => 3]);

        $this->student_user_ids = [$this->student_user->id, $this->student_user_2->id];
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->assignment = factory(Assignment::class)
            ->create(['course_id' => $this->course->id, 'name' => 'Assignment 1']);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id);


        $this->assignment_3 = factory(Assignment::class)
            ->create([
                'course_id' => $this->course->id,
                'order' => 2,
                'name' => 'assignment 2'
            ]);
        $this->question = factory(Question::class)->create(['page_id' => 9977362]);
        $this->original_assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);


        $this->learning_tree = factory(LearningTree::class)->create(['user_id' => $this->user->id]);
        $this->original_assignment_question_learning_tree_id = DB::table('assignment_question_learning_tree')->insertGetId([
            'assignment_question_id' => $this->original_assignment_question_id,
            'learning_tree_id' => $this->learning_tree->id,
            'number_of_successful_paths_for_a_reset' => 1
        ]);

        $this->course_3 = factory(Course::class)->create(['user_id' => $this->user->id]);


        $this->user_2 = factory(User::class)->create();
        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user_2->id]);
        $this->assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course_2->id]);

        $this->grader_user = factory(User::class)->create();
        $this->grader_user->role = 4;
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);
        $finalGrade = new FinalGrade();

        FinalGrade::create(['course_id' => $this->course->id,
            'letter_grades' => $finalGrade->defaultLetterGrades()]);


        $this->assign_tos = [
            [
                'groups' => [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']],
                'available_from' => '2020-06-10 09:00:00',
                'available_from_date' => '2020-06-10',
                'available_from_time' => '9:00 AM',
                'due' => '2020-06-12 09:00:00',
                'due_date' => '2020-06-12',
                'due_time' => '9:00 AM',
                'final_submission_deadline' => '2021-06-12 09:00:00',
                'final_submission_deadline_date' => '2021-06-12',
                'final_submission_deadline_time' => '9:00 AM',
            ]
        ];
        $this->assignment_info = ['course_id' => $this->course->id,
            'name' => 'First Assignment',
            'assign_tos' => $this->assign_tos,
            'scoring_type' => 'p',
            'source' => 'a',
            'points_per_question' => 'number of points',
            'default_points_per_question' => 2,
            'students_can_view_assignment_statistics' => 0,
            'include_in_weighted_average' => 1,
            'late_policy' => 'not accepted',
            'can_submit_work' => 0,
            'can_contact_instructor_auto_graded' => 'never',
            'assessment_type' => 'delayed',
            'default_open_ended_submission_type' => 'file',
            'instructions' => 'Some instructions',
            "number_of_randomized_assessments" => null,
            'algorithmic' => 0,
            'can_view_hint' => 0,
            'notifications' => 1,
            'assignment_group_id' => 1,
            'formative' => 0,
            'file_upload_mode' => 'both'];

        foreach ($this->assign_tos[0]['groups'] as $key => $group) {
            $group_info = ["groups_$key" => ['Everybody'],
                "due_$key" => '2020-06-12 09:00:00',
                "due_date_$key" => '2020-06-12',
                "due_time_$key" => '9:00 AM',
                "available_from_$key" => '2020-06-10',
                "available_from_date_$key" => '2020-06-12',
                "available_from_time_$key" => '9:00 AM',
                "final_submission_deadline_date_$key" => '2021-06-12',
                "final_submission_deadline_time_$key" => '9:00 AM'];
            foreach ($group_info as $info_key => $info_value) {
                $this->assignment_info[$info_key] = $info_value;
            }
        }

        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        Enrollment::create(['course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->student_user->id]);

        $this->student_user_2 = factory(User::class)->create();
        $this->student_user_2->role = 3;
        $this->section_1 = $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        Enrollment::create(['course_id' => $this->course->id,
            'section_id' => $this->section_1->id,
            'user_id' => $this->student_user_2->id]);

    }

    /** @test */
    public
    function lms_course_needs_the_lms_grade_passback_option()
    {
        $this->course_2->lms = 1;
        $this->course_2->save();
        $this->assignment->course_id = $this->course_2->id;
        $this->assignment->save();
        $this->course_2->public = 0;
        User::where('id', $this->course_2->user_id)->update(['email' => 'commons@libretexts.org']);
        $this->course_2->save();
        $this->actingAs($this->user)->postJson(
            "/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}", [
            'level' => 'properties_and_questions'
        ])->assertJson(['message' => 'Since this course is an LMS course, please choose an option for the LMS grade passback.']);

    }

    /** @test */
    public
    function non_owner_of_assignment_can_import_it_to_their_course_if_public()
    {
        $this->assignment->course_id = $this->course_2->id;
        $this->assignment->save();
        $this->course_2->public = 1;
        $this->course_2->save();
        $this->actingAs($this->user)->postJson(
            "/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}", [
            'level' => 'properties_and_questions'
        ])->assertJson(['type' => 'success']);
    }


    /** @test */
    public function cannot_change_points_per_question_if_submissions_exist()
    {
        $this->student_user->role = 3;
        $this->student_user->save();
        $this->assignment->points_per_question = "number of points";
        $this->assignment->save();
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['open_ended_submission_type' => 'file']);

        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'submission' => 'some.pdf',
            'date_submitted' => Carbon::now()]);
        $this->assignment_info['points_per_question'] = "question weight";

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignment_info)
            ->assertJson(['message' => "This assignment already has submissions so you can't change the way that points are computed."]);
    }

    /** @test */
    public
    function owner_of_assignment_cannot_create_it_from_template_if_alpha_course_and_properties_and_questions()
    {
        $this->course->alpha = 1;
        BetaCourse::insert(['alpha_course_id' => $this->course->id, 'id' => $this->course_2->id]);
        $this->course->save();
        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/create-assignment-from-template", ['level' => 'properties_and_questions'])
            ->assertJson(['message' => 'Since this is an Alpha course, please select "Just Properties".']);
    }


    /** @test */
    public
    function the_correct_user_is_assigned_an_assignment_with_sections()
    {
        DB::table('assign_to_users')->delete();
        $assignment_info = $this->assignment_info;
        $groups = [['value' => ['section_id' => $this->section->id], 'text' => $this->section->name]];
        $assignment_info = $this->createAssignTosFromGroups($assignment_info, $groups);
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJson(['type' => 'success']);
        $this->assertEquals(1, AssignToUser::all()->count(), 'Only one of the two users has been assigned');

    }

    /** @test */
    public
    function non_owner_of_assignment_cannot_import_it_to_their_course_if_not_public()
    {
        $this->assignment->course_id = $this->course_2->id;
        $this->assignment->save();
        $this->course_2->public = 0;
        $this->course_2->save();
        $this->actingAs($this->user)->postJson(
            "/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}", [
            'level' => 'properties_and_questions'
        ])->assertJson(['message' => 'You can only import assignments from your own courses, the Commons, or public courses.']);

    }

    /** @test */
    public
    function non_owner_of_assignment_can_import_it_to_their_course_if_from_the_commons_even_if_not_public()
    {
        $this->assignment->course_id = $this->course_2->id;
        $this->assignment->save();
        $this->course_2->public = 0;
        User::where('id', $this->course_2->user_id)->update(['email' => 'commons@libretexts.org']);
        $this->course_2->save();
        $this->actingAs($this->user)->postJson(
            "/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}", [
            'level' => 'properties_and_questions'
        ])->assertJson(['type' => 'success']);

    }


    /** @test */

    public
    function owner_of_assignment_can_import_properties_and_questions()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/import/{$this->assignment->id}/to/{$this->course_3->id}",
            ['level' => 'properties_and_questions']);
        $imported_assignment = $this->course_3->assignments->first();
        $imported_assignment_question = DB::table('assignment_question')->where('assignment_id', $imported_assignment->id)->first();
        $original_assignment_question = DB::table('assignment_question')->where('id', $this->original_assignment_question_id)->first();

        $this->assertEquals($original_assignment_question->question_id, $imported_assignment_question->question_id);


    }

    /** @test */

    public
    function owner_of_assignment_can_import_properties_and_learning_trees()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/import/{$this->assignment->id}/to/{$this->course_3->id}",
            ['level' => 'properties_and_questions']);
        $imported_assignment = $this->course_3->assignments->first();
        $imported_assignment_question_id = DB::table('assignment_question')
            ->where('assignment_id', $imported_assignment->id)
            ->first()
            ->id;


        $original_assignment_question_id = DB::table('assignment_question')
            ->where('id', $this->original_assignment_question_id)
            ->first()
            ->id;

        $imported_assignment_question_learning_tree_id = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $imported_assignment_question_id)
            ->first()
            ->learning_tree_id;

        $original_assignment_question_learning_tree_id = DB::table('assignment_question_learning_tree')
            ->where('assignment_question_id', $original_assignment_question_id)
            ->first()
            ->learning_tree_id;

        $this->assertEquals($original_assignment_question_learning_tree_id, $imported_assignment_question_learning_tree_id);
    }


    /** @test */

    public
    function owner_of_assignment_can_import_just_properties()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}",
            ['level' => 'properties_and_not_questions'])
            ->assertJson(['message' => "<strong>{$this->assignment->name} Import</strong> has been imported without its questions.</br></br>Don't forget to change the dates associated with this assignment."]);
    }

    /** @test */

    public
    function importing_must_include_a_valid_level()
    {
        $this->actingAs($this->user)->postJson("/api/assignments/import/{$this->assignment->id}/to/{$this->course->id}",
            ['level' => 'some fake level'])
            ->assertJson(['message' => "You should either choose 'properties and questions' or 'properties and not questions'."]);
    }


    /** @test */

    public
    function non_owner_of_assignment_cannot_import_it_to_their_course()
    {

        $this->actingAs($this->user)->postJson(
            "/api/assignments/import/{$this->assignment->id}/to/{$this->course_2->id}", [
            'level' => 'properties and questions'
        ])->assertJson(['message' => 'You are not allowed to import assignments to this course.']);
    }


    /** @test */

    public function real_time_must_have_valid_number_of_attempts()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['number_of_allowed_attempts'] = 'bogus';
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('number_of_allowed_attempts');

    }

    /** @test */

    public function real_time_must_have_valid_number_of_attempts_based_on_penalty()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['number_of_allowed_attempts'] = '3';
        $assignment_info['number_of_allowed_attempts_penalty'] = '80';//results in 160%
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('number_of_allowed_attempts_penalty');
    }

    /** @test */

    public function hint_value_must_be_valid()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['can_view_hint'] = '';
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('can_view_hint');
    }

    /** @test */

    public function hint_penalty_must_be_valid()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['can_view_hint'] = 1;
        $assignment_info['hint_penalty'] = 500;
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('hint_penalty');
    }

    /** @test */

    public function real_time_must_have_valid_solutions_availability()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['number_of_allowed_attempts'] = '3';
        $assignment_info['number_of_allowed_attempts_penalty'] = '10';
        $assignment_info['solutions_availability'] = 'bogus availability';
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('solutions_availability');
    }

    /** @test */

    public function real_time_with_at_least_one_attempt_must_have_valid_percent()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = 'real time';
        $assignment_info['number_of_allowed_attempts'] = '2';
        $assignment_info['number_of_allowed_attempts_penalty'] = '-10';
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors('number_of_allowed_attempts_penalty');

    }


    /**  @test */

    public function cannot_change_from_individual_to_compiled_or_vice_versa_if_there_are_any_submissions()
    {

        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['open_ended_submission_type' => 'file']);

        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'text',
            'original_filename' => '',
            'submission' => 'some.pdf',
            'date_submitted' => Carbon::now()]);

        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment->id}/validate-can-switch-to-or-from-compiled-pdf")
            ->assertJson(['message' => "Since students have already submitted responses, you can't switch this option."]);

    }


    /**  @test */

    public function cannot_change_from_individual_to_compiled_if_non_file_open_ended_assessment_exists()
    {

        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['open_ended_submission_type' => 'audio']);

        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment->id}/validate-can-switch-to-compiled-pdf")
            ->assertJson(['message' => 'If you would like to use the compiled PDF feature, please update your assessments so that they are all of type "file" or "none".']);

    }

    /** @test * */

    public function cannot_change_the_number_of_randomized_assessments_if_a_student_has_randomizations()
    {
        $randomizedAssignmentQuestion = new RandomizedAssignmentQuestion ();
        $randomizedAssignmentQuestion->user_id = $this->student_user->id;
        $randomizedAssignmentQuestion->question_id = $this->question->id;
        $randomizedAssignmentQuestion->assignment_id = $this->assignment->id;
        $randomizedAssignmentQuestion->save();

        $this->assignment_info['randomizations'] = 1;
        $this->assignment_info['number_of_randomized_assessments'] = 5;
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignment_info)
            ->assertJsonValidationErrors('number_of_randomized_assessments');


    }

    /** @test */

    public function assignment_names_must_be_unique_within_a_course()
    {
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors('name');
    }


    /** @test */

    public function number_of_randomized_assessments_must_be_a_valid_number()
    {
        $this->assignment_info['randomizations'] = 1;
        $this->assignment_info['number_of_randomized_assessments'] = -3;
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors('number_of_randomized_assessments');
    }

    /** @test */
    public
    function owner_of_assignment_can_create_it_from_template_and_copy_assign_to_groups()
    {

        DB::table('assign_to_users')->delete();
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);


        $assignment_id = DB::table('assignments')->select('id')
            ->orderBy('id', 'desc')
            ->first()
            ->id;

        $assign_to_timing_ids = AssignToTiming::where('assignment_id', $assignment_id)->pluck('id')->toArray();
        $num_assign_to_users = AssignToUser::whereIn('assign_to_timing_id', $assign_to_timing_ids)->get()->count();

        $this->actingAs($this->user)->postJson("/api/assignments/$assignment_id/create-assignment-from-template",
            ['assign_to_groups' => 1])
            ->assertJson(['message' => "<strong>{$this->assignment_info['name']} copy</strong> is using the same template as <strong>{$this->assignment_info['name']}</strong>. Don't forget to add questions and update the assignment's dates."]);

        $this->assertequals(2 * $num_assign_to_users,
            AssignToUser::all()->count(),
            'The number of assign to users should double since they were copied.');
    }


    /** @test */

    public function cannot_repeat_assign_tos()
    {
        $assignment_info = $this->assignment_info;
        $groups = [['value' => ['section_id' => $this->section->id], 'text' => $this->section->name],
            ['value' => ['section_id' => $this->section->id], 'text' => $this->section->name]];
        $assignment_info = $this->createAssignTosFromGroups($assignment_info, $groups);

        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJson(['message' => "{$this->section->name} was chosen twice as an assign to."]);

    }

    /** @test */
    public
    function the_correct_user_is_assigned_an_assignment_with_everybody()
    {
        $student_user_ids = [$this->student_user->id, $this->student_user_2->id];
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);
        $this->assertEquals(count($student_user_ids), AssignToUser::whereIn('user_id', $student_user_ids)->get()->count());
    }


    /** @test */
    public
    function the_correct_user_is_assigned_an_assignment_with_different_sections()
    {
        foreach ($this->student_user_ids as $student_user_id) {
            Enrollment::create(['course_id' => $this->course->id,
                'section_id' => $this->section->id,
                'user_id' => $student_user_id]);
        }
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);
        $this->assertEquals(count($this->student_user_ids), AssignToUser::whereIn('user_id', $this->student_user_ids)->get()->count());
    }


    /** @test */

    public
    function non_owner_cannot_reorder_assignments()
    {
        $this->actingAs($this->user_2)->patchJson("/api/assignments/{$this->course->id}/order", [
            'ordered_assignments' => [$this->assignment_2->id, $this->assignment->id]
        ])->assertJson(['message' => 'You are not allowed to re-order the assignments in that course.']);
    }

    /** @test */

    public
    function owner_can_reorder_assignments()
    {
        //dd($this->assignment->order . ' ' . $this->assignment_2->order);
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->course->id}/order", [
            'ordered_assignments' => [$this->assignment_3->id, $this->assignment->id]
        ]);
        $assignments = DB::table('assignments')->where('course_id', $this->course->id)
            ->get()
            ->sortBy('order')
            ->pluck('id')
            ->toArray();
        $this->assertEquals([$this->assignment_3->id, $this->assignment->id], $assignments);

    }


    /** @test */
    public
    function non_owner_of_assignment_cannot_create_it_from_template()
    {

        $this->actingAs($this->user_2)->postJson("/api/assignments/{$this->assignment->id}/create-assignment-from-template")
            ->assertJson(['message' => 'You are not allowed to create an assignment from this template.']);
    }

    /** @test */
    public
    function owner_of_assignment_can_create_it_from_template()
    {

        $this->actingAs($this->user)->postJson("/api/assignments/{$this->assignment->id}/create-assignment-from-template")
            ->assertJson(['message' => "<strong>{$this->assignment->name} copy</strong> is using the same template as <strong>{$this->assignment->name}</strong>. Don't forget to add questions and update the assignment's dates."]);
    }


    /** @test */
    public
    function must_include_a_valid_default_open_ended_submission_type()
    {

        $this->assignment_info['default_open_ended_submission_type'] = "7";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['default_open_ended_submission_type']);
    }


    /** @test */

    public
    function complete_incomplete_scoring_type_cannot_be_learning_trees_assessment_types()
    {
        $this->assignment_info['scoring_type'] = 'c';
        $this->assignment_info['assessment_type'] = 'learning tree';
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors('scoring_type');
    }


    /** @test */

    public
    function final_submission_deadline_must_be_valid()
    {


    }

    /** @test */

    public
    function for_real_time_assessments_students_must_be_shown_scores()
    {
//todo

    }

    /** @test */

    public
    function for_real_time_assessments_students_must_be_shown_solutions()
    {
//todo

    }

    /** @test */

    public
    function for_real_time_assessments_students_must_be_shown_question_points()
    {
//todo

    }


    /** @test */

    public
    function submission_count_percent_decrease_must_be_valid()
    {
//todo

    }


    /** @test */
    public
    function can_only_toggle_show_scores_for_a_delayed_assignment()
    {
//todo

    }

    /** @test */
    public
    function can_only_toggle_show_points_per_question_for_a_delayed_assignment()
    {
//todo

    }

    /** @test */

    public
    function a_valid_late_policy_is_submitted()
    {
//todo
    }

    /** @test */
    public
    function a_valid_late_percent_is_submitted()
    {

        //todo
    }

    /** @test */
    public
    function a_valid_late_deduction_application_period_is_submitted()
    {
        //test once as well as with a time
        //todo

    }


    /** @test */
    public
    function non_owner_cannot_toggle_show_points_per_question()
    {
        $this->actingAs($this->user_2)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-points-per-question/1")
            ->assertJson(['message' => 'You are not allowed to show/hide the points per question.']);
    }

    /** @test */
    public
    function owner_can_toggle_show_points_per_question()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-points-per-question/1")
            ->assertJson(['message' => 'Your students <strong>cannot</strong> view the points per question.']);
    }

    public
    function can_update_an_assignment_if_you_are_the_owner()
    {
        $this->assignment_info['name'] = 'Some new name';
        $this->actingAs($this->user)->patchJson("/api/assignments/{$this->assignment->id}",
            $this->assignment_info)
            ->assertJson(['type' => 'success']);
    }


    /** @test */
    public
    function can_create_an_assignment()
    {
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);

    }

    /** @test */

    public
    function must_be_of_a_valid_source()
    {
        $this->assignment_info['source'] = "";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['source']);


    }

    /** @test */
    public
    function must_include_an_assignment_name()
    {
        $this->assignment_info['name'] = "";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['name']);

    }

    /** @test */
    public
    function must_include_valid_available_on_date()
    {

        $this->assignment_info['available_from_date_0'] = "not a date";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['available_from_date_0']);

    }


    /** @test */
    public
    function must_include_valid_default_points_per_question()
    {

        $this->assignment_info['default_points_per_question'] = "";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['default_points_per_question']);


        $this->assignment_info['default_points_per_question'] = "10000";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['default_points_per_question']);

        $this->assignment_info['default_points_per_question'] = "-3";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['default_points_per_question']);
    }


    /** @test */
    public
    function must_include_valid_due_date()
    {
        $this->assignment_info['due_date_0'] = "not a date";
        $this->assignment_info['due_0'] = "not a date";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['due_0']);
    }


    /** @test */
    public
    function must_include_valid_due_time()
    {
        $this->assignment_info['due_time_0'] = "not a time";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['due_time_0']);
    }

    /** @test */
    public
    function due_date_must_be_after_available_date()
    {
        $this->assignment_info['due_0'] = "1982-06-06";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['due_0']);
    }

    /** @test */
    public
    function must_include_valid_available_from_time()
    {

        $this->assignment_info['available_from_time_0'] = "not a time";
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJsonValidationErrors(['available_from_time_0']);
    }

    /** @test */
    public function can_create_an_assignment_with_a_time_limit()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['time_limit_0'] = '2 minutes';
        $assignment_info['assign_tos'][0]['time_limit'] = '2 minutes';

        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJson(['type' => 'success']);

        $assignment_id = DB::table('assignments')->select('id')->orderBy('id', 'desc')->first()->id;
        $assign_to_timing = AssignToTiming::where('assignment_id', $assignment_id)->first();
        $this->assertEquals('2 minutes', $assign_to_timing->time_limit);
    }

    /** @test */
    public function time_limit_is_optional_when_creating_an_assignment()
    {
        // $this->assignment_info never sets a time_limit_0 key.
        $this->actingAs($this->user)->postJson("/api/assignments", $this->assignment_info)
            ->assertJson(['type' => 'success']);

        $assignment_id = DB::table('assignments')->select('id')->orderBy('id', 'desc')->first()->id;
        $assign_to_timing = AssignToTiming::where('assignment_id', $assignment_id)->first();
        $this->assertNull($assign_to_timing->time_limit);
    }

    /** @test */
    public function invalid_time_limit_fails_validation()
    {
        // NOTE: written against the assumption that IsValidPeriodOfTime rejects
        // a string CarbonInterval::make() can't parse, matching how it's used
        // for late_deduction_application_period/default_clicker_time_to_submit
        // elsewhere. Confirm/adjust once run against the real rule.
        $assignment_info = $this->assignment_info;
        $assignment_info['time_limit_0'] = 'not a real duration';

        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJsonValidationErrors(['time_limit_0']);
    }

    /** @test */
    public function each_assign_to_group_can_have_its_own_time_limit()
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assign_tos'] = [
            [
                'groups' => [['value' => ['section_id' => $this->section->id], 'text' => $this->section->name]],
                'available_from' => '2020-06-10 09:00:00',
                'available_from_date' => '2020-06-10',
                'available_from_time' => '9:00 AM',
                'due' => '2020-06-12 09:00:00',
                'due_date' => '2020-06-12',
                'due_time' => '9:00 AM',
                'time_limit' => '1 hour',
            ],
            [
                'groups' => [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']],
                'available_from' => '2020-06-10 09:00:00',
                'available_from_date' => '2020-06-10',
                'available_from_time' => '9:00 AM',
                'due' => '2020-06-12 09:00:00',
                'due_date' => '2020-06-12',
                'due_time' => '9:00 AM',
            ],
        ];
        $assignment_info['groups_0'] = [['value' => ['section_id' => $this->section->id], 'text' => $this->section->name]];
        $assignment_info['available_from_date_0'] = '2020-06-10';
        $assignment_info['available_from_time_0'] = '9:00 AM';
        $assignment_info['available_from_0'] = '2020-06-10';
        $assignment_info['due_date_0'] = '2020-06-12';
        $assignment_info['due_time_0'] = '9:00 AM';
        $assignment_info['due_0'] = '2020-06-12 09:00:00';
        $assignment_info['time_limit_0'] = '1 hour';

        $assignment_info['groups_1'] = [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']];
        $assignment_info['available_from_date_1'] = '2020-06-10';
        $assignment_info['available_from_time_1'] = '9:00 AM';
        $assignment_info['available_from_1'] = '2020-06-10';
        $assignment_info['due_date_1'] = '2020-06-12';
        $assignment_info['due_time_1'] = '9:00 AM';
        $assignment_info['due_1'] = '2020-06-12 09:00:00';

        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJson(['type' => 'success']);

        $assignment_id = DB::table('assignments')->select('id')->orderBy('id', 'desc')->first()->id;
        $assign_to_timings = AssignToTiming::where('assignment_id', $assignment_id)->orderBy('id')->get();
        $this->assertCount(2, $assign_to_timings);
        $this->assertEquals('1 hour', $assign_to_timings[0]->time_limit);
        $this->assertNull($assign_to_timings[1]->time_limit);
    }


}
