<?php

namespace Tests\Feature\Instructors;


use App\Assignment;
use App\AssignmentSyncQuestion;
use App\BetaCourse;
use App\Course;
use App\Enrollment;
use App\Question;
use App\QuestionRevision;
use App\SavedQuestionsFolder;
use App\Section;
use App\Submission;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NonUpdatedRevisionsTest extends TestCase
{

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create(['email' => 'me@me.com']);
        //create a student and enroll in the class
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->question = factory(Question::class)->create(['page_id' => 17652]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);
        $this->question_revision = factory(QuestionRevision::class)->create(['action' => 'notify',
            'question_id' => $this->question->id]);
        DB::table('pending_question_revisions')
            ->insert(['assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'question_revision_id' => $this->question_revision->id]);

    }

    /** @test */
    public function when_updating_a_single_question_must_confirm_about_student_submissions()
    {

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/update-to-latest-revision",
                ['pending_question_revision_id' => 10])
            ->assertJson(['message' => "You must confirm that you understand that student submissions will be removed."]);

    }

    /** @test */
    public function cannot_auto_update_beta_course()
    {
        $alpha_course = factory(Course::class)->create(['user_id' => $this->user->id]);
        BetaCourse::create(['id' => $this->course->id, 'alpha_course_id' => $alpha_course->id]);
        $this->actingAs($this->user)
            ->patchJson("/api/courses/{$this->course->id}/auto-update-question-revisions")
            ->assertJson(['message' => "You cannot change the auto-update option since this is a beta course."]);
    }


    /** @test */
    public function correctly_updates_all_non_update_questions_to_the_latest_revision_in_the_course_including_the_revision_id_score_and_removes_submissions()
    {
        // Non-admin path: only here are submissions/scores still touched automatically.
        // An admin request now must confirm changes_are_topical, which guarantees
        // submissions are left untouched (see the topical test below).
        $this->user->email = 'nonadmin@hotmail.com';
        $this->user->save();

        $original_assignment_score = 30;
        $question_score = 5;
        DB::table('scores')
            ->insert(['assignment_id' => $this->assignment->id,
                'user_id' => $this->student_user->id,
                'score' => $original_assignment_score]);
        Submission::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'score' => $question_score,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => 'some submission']);
        $this->assertDatabaseCount('submissions', 1);

        //from the course in question --- so there are now 2 of them
        $this->assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question_2 = factory(Question::class)->create(['page_id' => 17653]);
        $question_2_revision = factory(QuestionRevision::class)->create(['action' => 'notify',
            'question_id' => $this->question_2->id]);
        DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question_2->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);


        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment_3 = factory(Assignment::class)->create(['course_id' => $this->course_2->id]);
        $this->question_3 = factory(Question::class)->create(['page_id' => 17654]);
        factory(QuestionRevision::class)->create(['action' => 'notify',
            'question_id' => $this->question_3->id]);
        DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment_3->id,
            'question_id' => $this->question_3->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);

        $this->actingAs($this->user)
            ->patch("/api/non-updated-question-revisions/update-to-latest/course/{$this->course->id}")
            ->assertJson(['type' => 'success', 'message' => 'The question has been updated to the latest revision. There were no student submissions which needed to be removed.']);
        //first 2 in the course
        $this->assertDatabaseHas('assignment_question', ['question_id' => $this->question->id, 'question_revision_id' => $this->question_revision->id]);
        $this->assertDatabaseHas('assignment_question', ['question_id' => $this->question_2->id, 'question_revision_id' => $question_2_revision->id]);
        //last not in the course
        $this->assertDatabaseHas('assignment_question', ['question_id' => $this->question_3->id, 'question_revision_id' => null]);

        //re-computes the score correctly
        $score = DB::table('scores')->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->first();
        $this->assertEquals($original_assignment_score - $question_score, $score->score);

        //removes the submission
        $this->assertDatabaseCount('submissions', 0);
    }

    /** @test */
    public function admin_confirming_topical_updates_the_revision_but_leaves_submissions_and_scores_untouched()
    {
        $original_assignment_score = 30;
        $question_score = 5;
        DB::table('scores')
            ->insert(['assignment_id' => $this->assignment->id,
                'user_id' => $this->student_user->id,
                'score' => $original_assignment_score]);
        Submission::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'score' => $question_score,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => false,
            'submission' => 'some submission']);
        $this->assertDatabaseCount('submissions', 1);

        $this->actingAs($this->user)
            ->patch("/api/non-updated-question-revisions/update-to-latest/course/{$this->course->id}", ['changes_are_topical' => true])
            ->assertJson(['type' => 'success', 'message' => 'The question has been updated to the latest revision. Since the changes were confirmed as topical, student submissions were not removed.']);

        // the revision is still applied
        $this->assertDatabaseHas('assignment_question', ['question_id' => $this->question->id, 'question_revision_id' => $this->question_revision->id]);

        // but submissions and scores are left alone
        $this->assertDatabaseCount('submissions', 1);
        $score = DB::table('scores')->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_user->id)
            ->first();
        $this->assertEquals($original_assignment_score, $score->score);
    }


    /** @test */
    public function if_admin_must_confirm_changes_are_topical()
    {

        $this->actingAs($this->user)
            ->patch("/api/non-updated-question-revisions/update-to-latest/course/{$this->course->id}")
            ->assertJson(['message' => "You must confirm that the changes are topical."]);
    }

    /** @test */
    public function can_update_to_latest_if_students_in_course_if_admin()
    {
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);

        $this->actingAs($this->user)
            ->patch("/api/non-updated-question-revisions/update-to-latest/course/{$this->course->id}", ['changes_are_topical' => true])
            ->assertJson(['message' => "The question has been updated to the latest revision. Since the changes were confirmed as topical, student submissions were not removed."]);
    }

    /** @test */
    public function cannot_update_all_questions_to_latest_if_students_in_course()
    {
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->user->email = 'nonadmin@hotmail.com';
        $this->user->save();
        $this->actingAs($this->user)
            ->patchJson("/api/non-updated-question-revisions/update-to-latest/course/{$this->course->id}")
            ->assertJson(['message' => "You are not allowed to update the course questions to the latest revision since there are students enrolled."]);
    }


    /** @test */
    public function nobody_can_be_enrolled_if_turning_on_auto_update()
    {
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->actingAs($this->user)
            ->patchJson("/api/courses/{$this->course->id}/auto-update-question-revisions")
            ->assertJson(['message' => "There are students enrolled in this course so you can't auto-update the question revisions."]);

    }

    /** @test */
    public function only_valid_users_can_auto_update()
    {
        $this->actingAs($this->student_user)
            ->patchJson("/api/courses/{$this->course->id}/auto-update-question-revisions")
            ->assertJson(['message' => 'You are not allowed to auto-update question revisions for this course.']);

    }


}
