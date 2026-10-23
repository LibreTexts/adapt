<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\CannedResponse;
use App\Grader;
use App\Question;
use App\Score;
use App\Section;
use App\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\User;
use App\Course;
use App\Assignment;
use App\Enrollment;
use App\SubmissionFile;

class GradingTest extends TestCase
{

    private $student_user;
    private $user;
    private $user_2;
    private $student_user_2;
    private $course;
    private $assignment;
    private $grader_user;
    private $section;
    private $question;
    private $score_data;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_2 = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->student_user->save();

        $this->student_user_2 = factory(User::class)->create();
        $this->student_user_2->role = 3;
        $this->student_user_2->save();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->grader_user = factory(User::class)->create();
        $this->grader_user->role = 4;
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);

        $this->assignment_file = factory(SubmissionFile::class)->create(['type' => 'a', 'user_id' => $this->student_user->id, 'assignment_id' => $this->assignment->id]);

        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        $this->score_data = [
            'type' => 'question',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'question_submission_score' => 10,
            'file_submission_score' => 0,
            'text_feedback_editor' => 'plain'];
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        $this->question_file = factory(SubmissionFile::class)->create([
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id
        ]);
        factory(CannedResponse::class)->create(['user_id' => $this->user->id]);

        $data = [
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()];
        SubmissionFile::create($data);
        $this->getFilesFromS3Data = ['open_ended_submission_type' => 'text'];
    }

    #[Test]
    public function can_get_assignment_files_if_owner()
    {

        $this->actingAs($this->user)->getJson("/api/grading/{$this->assignment->id}/{$this->question->id}/{$this->section->id}/all_students")
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function can_store_text_feedback_if_owner()
    {
        $this->actingAs($this->user)->postJson("/api/grading",
            [
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'user_id' => $this->student_user->id,
                'text_feedback_editor' => 'plain',
                'file_submission_score' => 0,
                'question_submission_score' => 0,
                'textFeedback' => 'Some text feedback'
            ]
        )
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function cannot_get_assignment_files_if_not_owner()
    {
        $this->actingAs($this->user_2)->getJson("/api/grading/{$this->assignment->id}/{$this->question->id}/{$this->section->id}/all_students")
            ->assertJson([
                'type' => 'error',
                'message' => 'You are not allowed to access these submissions for grading.'
            ]);

    }

    #[Test]
    public function assignments_of_scoring_type_p_and_submission_files_at_the_question_level_will_use_min_of_the_points_per_question_compared_to_the_sum_of_the_question_and_file_points()
    {

        $question_score = 5;

        $file_submission_score = 2.0;

        Submission::create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'some other submission',
            'score' => $question_score,
            'answered_correctly_at_least_once' => 0,
            'submission_count' => 1]);

        //Now submit a question_file score
        $this->actingAs($this->user)->postJson("/api/grading", [
            'type' => 'question',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'file_submission_score' => $file_submission_score,
            'question_submission_score' => $question_score,
            'text_feedback_editor' => 'plain'])
            ->assertJson(['type' => 'success']);

        $score = DB::table('scores')->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->first();

        $this->assertEquals((float)$score->score, $question_score + $file_submission_score);
    }

    #[Test]
    public function assignments_of_scoring_type_p_and_submission_files_at_the_question_level_cannot_submit_a_score_greater_than_the_total_number_of_points_in_the_question()
    {

        $question_score = 5;

        $file_submission_score = 30;
        DB::table('assignment_question')
            ->where('question_id', $this->question->id)
            ->where('assignment_id', $this->assignment->id)
            ->first();

        Submission::create([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'some other submission',
            'score' => $question_score,
            'answered_correctly_at_least_once' => 0,
            'submission_count' => 1]);

        //Now submit a question_file score
        $this->actingAs($this->user)->postJson("/api/grading", [
            'type' => 'question',
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'file_submission_score' => $file_submission_score,
            'question_submission_score' => $question_score,
            'text_feedback_editor' => 'plain'])
            ->assertJson(['message' => "The total of your Auto-Graded Score and Open-Ended Submission score can't be greater than the total number of points for this question."]);
    }

    #[Test]
    public function owner_or_grader_can_submit_score()
    {

        $this->actingAs($this->grader_user)->postJson("/api/grading", $this->score_data)
            ->assertJson(['type' => 'success']);

        $this->actingAs($this->user)->postJson("/api/grading", $this->score_data)
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function non_owner_can_not_submit_score()
    {

        $this->actingAs($this->user_2)->postJson("/api/grading", $this->score_data)
            ->assertJson(['message' => 'You are not allowed to provide a score for this assignment.']);

    }

    #[Test]
    public function question_submission_score_must_be_valid()
    {

        $this->score_data['question_submission_score'] = 'a';
        $this->actingAs($this->grader_user)->postJson("/api/grading", $this->score_data)
            ->assertJsonValidationErrors('question_submission_score');

    }

    #[Test]
    public function file_submission_score_must_be_valid()
    {

        $this->score_data['file_submission_score'] = 'a';
        $this->actingAs($this->grader_user)->postJson("/api/grading", $this->score_data)
            ->assertJsonValidationErrors('file_submission_score');

    }

    #[Test]
    public function owner_can_override_scores()
    {
        $score = new Score();
        $score->user_id = $this->student_user->id;
        $score->assignment_id = $this->assignment->id;
        $score->score = 10;
        $score->save();

        $data['overrideScores'] = [['user_id' => $this->student_user->id, 'override_score' => 2]];
        $this->actingAs($this->user)->patchJson("/api/scores/override-scores/{$this->assignment->id}", $data);
        $score = new Score();
        $this->assertEquals(2, $score->where('user_id', $this->student_user->id)->first()->score);
    }

    #[Test]
    public function override_scores_does_not_affect_other_students()
    {
        $score = new Score();
        $score->user_id = $this->student_user_2->id;
        $score->assignment_id = $this->assignment->id;
        $score->score = 10;
        $score->save();

        $data['overrideScores'] = [['user_id' => $this->student_user->id, 'override_score' => 2]];
        $this->actingAs($this->user)->patchJson("/api/scores/override-scores/{$this->assignment->id}", $data);
        $score = new Score();
        $this->assertEquals(10, $score->where('user_id', $this->student_user_2->id)->first()->score);
    }

    #[Test]
    public function non_owner_cannot_override_scores()
    {
        $data['overrideScores'] = [['user_id' => $this->student_user->id, 'override_score' => 2]];
        $this->actingAs($this->user_2)->patchJson("/api/scores/override-scores/{$this->assignment->id}", $data)
            ->assertJson(['message' => "You can't override the scores since this is not one of your assignments."]);

    }

    #[Test]
    public function students_must_be_enrolled_in_course_to_resetrides()
    {
        $data['overrideScores'] = [['user_id' => 1, 'override_score' => 2]];
        $this->actingAs($this->user)->patchJson("/api/scores/override-scores/{$this->assignment->id}", $data)
            ->assertJson(['message' => "You can only override scores if the students are enrolled in your course."]);
    }

    #[Test]
    public function non_owner_cannot_get_assignment_options()
    {
        $this->actingAs($this->user_2)->getJson("/api/assignments/options/{$this->course->id}")
            ->assertJson(['message' => 'You are not allowed to download the assignment options.']);

    }

    #[Test]
    public function an_owner_can_get_assignment_options()
    {
        $this->actingAs($this->user)->getJson("/api/assignments/options/{$this->course->id}")
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function owner_can_get_s3_files()
    {
        $response['files'] = ['submission' => 'fake_1.pdf'];
        $this->actingAs($this->user)->postJson("/api/submission-files/get-files-from-s3/{$this->assignment->id}/{$this->question->id}/{$this->student_user->id}", $this->getFilesFromS3Data)
            ->assertJson($response);

    }

    #[Test]
    public function grader_can_get_s3_files()
    {
        $response['files'] = ['submission' => 'fake_1.pdf'];
        $this->actingAs($this->grader_user)->postJson("/api/submission-files/get-files-from-s3/{$this->assignment->id}/{$this->question->id}/{$this->student_user->id}", $this->getFilesFromS3Data)
            ->assertJson($response);

    }

    #[Test]
    public function student_can_get_their_own_s3_files()
    {
        $response['files'] = ['submission' => 'fake_1.pdf'];
        $this->actingAs($this->student_user)->postJson("/api/submission-files/get-files-from-s3/{$this->assignment->id}/{$this->question->id}/{$this->student_user->id}", $this->getFilesFromS3Data)
            ->assertJson($response);

    }

    #[Test]
    public function student_cannot_get_someone_elses_s3_files()
    {

        $this->actingAs($this->student_user_2)->postJson("/api/submission-files/get-files-from-s3/{$this->assignment->id}/{$this->question->id}/{$this->student_user->id}", $this->getFilesFromS3Data)
            ->assertJson(['message' => 'You are not allowed to view that submission file.']);

    }

    #[Test]
    public function student_cannot_get_canned_responses()
    {
        $this->actingAs($this->student_user)->getJson("/api/canned-responses")
            ->assertJson(['message' => 'You are not allowed to get canned responses.']);

    }

    #[Test]
    public function instructor_can_get_canned_responses()
    {

        $this->actingAs($this->user)->getJson("/api/canned-responses")
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function non_grader_instructor_cannot_store_canned_responses()
    {
        $this->actingAs($this->student_user)->postJson("/api/canned-responses", ['canned_response' => 'blah blah'])
            ->assertJson(['message' => 'You are not allowed to store a canned response.']);

    }

    #[Test]
    public function grader_instructor_can_store_canned_responses()
    {
        $this->actingAs($this->user)->postJson("/api/canned-responses", ['canned_response' => 'blah blah'])
            ->assertJson(['message' => 'Your canned response has been saved.']);

    }

    #[Test]
    public function canned_responses_should_be_unique_by_user()
    {
        $this->actingAs($this->user)->postJson("/api/canned-responses", ['canned_response' => 'some canned response'])
            ->assertJsonValidationErrors(['canned_response']);

    }

    #[Test]
    public function canned_responses_should_be_non_empty()
    {
        $this->actingAs($this->user)->postJson("/api/canned-responses", ['canned_response' => ''])
            ->assertJsonValidationErrors(['canned_response']);

    }

    #[Test]
    public function can_download_assignment_file_if_owner()
    {
        $this->markTestIncomplete(
            'Not sure how to test'
        );

    }

    #[Test]
    public function can_get_temporary_url_from_request_if_owner()
    {
        $this->actingAs($this->user)->postJson("/api/submission-files/get-temporary-url-from-request",
            [
                'assignment_id' => $this->assignment->id,
                'file' => $this->assignment_file->submission
            ]
        )
            ->assertJson(['type' => 'success']);

    }

    #[Test]
    public function cannot_get_temporary_url_if_not_owner()
    {
        $this->actingAs($this->user_2)->postJson("/api/submission-files/get-temporary-url-from-request",
            [
                'assignment_id' => $this->assignment->id,
                'file' => $this->assignment_file->submission
            ]
        )
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to create a temporary URL.']);

    }
}
