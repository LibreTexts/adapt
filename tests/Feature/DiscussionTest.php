<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\QuestionMediaUpload;
use App\Section;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionTest extends TestCase
{
    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create();
        DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'discuss_it_settings' => '{"students_can_edit_comments":"1","students_can_delete_comments":"1","min_number_of_initiated_discussion_threads":"2","min_number_of_replies":"1","min_number_of_initiate_or_reply_in_threads":"1","min_number_of_comments":"1","min_number_of_words":"4","min_length_of_audio_video":"5 seconds","auto_grade":1,"completion_criteria":1,"response_modes":["text", "audio", "video"]}',
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
        $this->questionMediaUpload = QuestionMediaUpload::create([
            'question_id' => $this->question->id,
            'original_filename' => 'some name',
            'size' => 200,
            's3_key' => 'some key',
            'transcript' => 'sdfdsf',
            'status' => 'completed'
        ]);
        $this->discussion_id = DB::table('discussions')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'media_upload_id' => $this->questionMediaUpload->id,
            'user_id' => $this->user->id]);
    }

    /** @test */
    public function non_enrolled_student_cannot_get_discussion_group()
    {

        $new_user = factory(User::class)->create(['role'=>3]);
        $this->actingAs($new_user)
            ->getJson("/api/discussion-groups/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => "You are not allowed to get the discussion group information."]);

    }

    /** @test */
    public function must_be_in_course_to_view_discussions()
    {
        $new_user = factory(User::class)->create();
        $this->actingAs($new_user)
            ->getJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}")
            ->assertJson(['message' => "You are not allowed to view the discussions for this question."]);

    }

    /** @test */
    public function must_be_in_course_to_create_discussion()
    {
        $new_student_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_student_user)
            ->postJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/1/0/1", ['type' => 'text', 'text' => 'sdfdsf'])
            ->assertJson(['message' => "No responses will be saved since you were not assigned to this assignment."]);

        $new_instructor_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_instructor_user)
            ->postJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/1/0/1", ['type' => 'text', 'text' => 'sdfdsf'])
            ->assertJson(['message' => "You are not allowed to create a discussion for that assignment."]);

    }

    /** @test */
    public function text_is_required_for_creating_text_discussion()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/1/0/1", ['type' => 'text'])
            ->assertJsonValidationErrors('text');
    }
}
