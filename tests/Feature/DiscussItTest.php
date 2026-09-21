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
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;
use MiladRahimi\Jwt\Parser;
use Tests\TestCase;

class DiscussItTest extends TestCase
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
    public function can_only_update_text_if_the_resource_is_owned()
    {
        $new_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_user)
            ->patchJson("/api/question-media/text", [
                'text' => 'sdfs',
                'description' => 'wefwef',
                's3_key' => 'some key'])
            ->assertJson(['message' => "You are not allowed to update the text for this question media upload."]);
    }

    /** @test */
    public function text_and_description_are_required()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/question-media/text")
            ->assertJsonValidationErrors(['text', 'description']);
    }

    /** @test */
    public function only_instructors_can_do_this()
    {
        $new_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_user)
            ->postJson("/api/question-media/text", [
                'text' => 'sdfs',
                'description' => 'wefwef'])
            ->assertJson(['message' => 'You are not allowed to store text as a question media upload.']);
    }

    /** @test */
    public function non_student_cannot_get_discuss_it_questions()
    {
        $new_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/discuss-it")
            ->assertJson(['message' => "You are not allowed to get the discuss-it questions for that assignment."]);
    }

    /** @test */
    public function cannot_get_discuss_it_settings_if_not_student_or_instructor()
    {
        $new_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_user)
            ->getJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/discuss-it-settings")
            ->assertJson(['message' => "You are not allowed to get the discuss-it settings for that question."]);
    }

    /** @test */
    public function cannot_update_discuss_it_settings_if_not_owner()
    {
        $info = ["students_can_edit_comments" => 1,
            "students_can_delete_comments" => 1,
            "min_number_of_initiated_discussion_threads" => 1,
            "min_number_of_initiate_or_reply_in_threads" => 1,
            "min_number_of_replies" => 1,
            "min_number_of_words" => 1,
            'min_length_of_audio_video' => '2 minutes',
            "response_modes" => ["text", "audio", "video"],
            'auto_grade' => 1,
            "completion_criteria" => 1,
            'language' => 'en'];
        $new_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($new_user)
            ->patchJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/discuss-it-settings", $info)
            ->assertJson(['message' => "You are not allowed to update the discuss-it settings for that question."]);
    }

    /** @test */
    public function data_must_be_valid()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/discuss-it-settings", [])
            ->assertJsonValidationErrors([
                'students_can_edit_comments',
                'students_can_delete_comments',
                "min_number_of_initiated_discussion_threads",
                "min_number_of_initiate_or_reply_in_threads",
                "min_number_of_replies",
                "response_modes",
                "completion_criteria",
                'auto_grade']);
    }

    /** @test */
    public function min_number_of_replies_can_be_independent_of_the_other_completion_criteria()
    {
        // min_number_of_replies (2) is intentionally lower than both
        // min_number_of_initiated_discussion_threads (3) and
        // min_number_of_initiate_or_reply_in_threads (5). This is valid: each is
        // scored from a disjoint set of comments (see
        // DiscussionComment::numberOfRepliesThatSatisfiedTheRequirements, which
        // excludes the comment that initiated a thread), so none of these
        // minimums needs to be greater than or equal to another.
        $info = ["students_can_edit_comments" => 1,
            "students_can_delete_comments" => 1,
            "min_number_of_initiated_discussion_threads" => 3,
            "min_number_of_initiate_or_reply_in_threads" => 5,
            "min_number_of_replies" => 2,
            "min_number_of_words" => 1,
            'min_length_of_audio_video' => '2 minutes',
            "response_modes" => ["text", "audio", "video"],
            'auto_grade' => 1,
            "completion_criteria" => 1,
            'language' => 'en'];
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/discuss-it-settings", $info)
            ->assertJsonMissingValidationErrors(['min_number_of_replies']);
    }

    /** @test */
    public function min_number_of_replies_must_still_be_a_non_negative_integer()
    {
        $info = ["students_can_edit_comments" => 1,
            "students_can_delete_comments" => 1,
            "min_number_of_initiated_discussion_threads" => 1,
            "min_number_of_initiate_or_reply_in_threads" => 1,
            "min_number_of_replies" => -1,
            "min_number_of_words" => 1,
            'min_length_of_audio_video' => '2 minutes',
            "response_modes" => ["text", "audio", "video"],
            'auto_grade' => 1,
            "completion_criteria" => 1,
            'language' => 'en'];
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/question/{$this->question->id}/discuss-it-settings", $info)
            ->assertJsonValidationErrors(['min_number_of_replies']);
    }

}
