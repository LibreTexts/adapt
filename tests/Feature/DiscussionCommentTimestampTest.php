<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Discussion;
use App\DiscussionComment;
use App\Enrollment;
use App\Helpers\Helper;
use App\Question;
use App\QuestionMediaUpload;
use App\Section;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Replies that point to a moment (a "timestamp") in an audio/video comment in the same thread.  One per reply.
 */
class DiscussionCommentTimestampTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id, 'public' => 0]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create();
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'discuss_it_settings' => '{"students_can_edit_comments":"1","students_can_delete_comments":"1","min_number_of_initiated_discussion_threads":"0","min_number_of_replies":"0","min_number_of_initiate_or_reply_in_threads":"0","min_number_of_comments":"1","min_number_of_words":"1","min_length_of_audio_video":"5 seconds","auto_grade":0,"completion_criteria":1,"response_modes":["text", "audio", "video"]}',
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
        $this->discussion_id = $this->_startDiscussion();
        $this->video_comment = $this->_comment($this->discussion_id, $this->student_user, ['file' => 'some-video.webm', 'recording_type' => 'video']);
        $this->audio_comment = $this->_comment($this->discussion_id, $this->student_user, ['file' => 'some-audio.mp3', 'recording_type' => 'audio']);
        $this->text_comment = $this->_comment($this->discussion_id, $this->student_user, ['text' => 'a text comment']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function _startDiscussion(): int
    {
        return DB::table('discussions')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'media_upload_id' => $this->questionMediaUpload->id,
            'user_id' => $this->user->id]);
    }

    private function _comment(int $discussion_id, User $user, array $attributes): DiscussionComment
    {
        return DiscussionComment::create(array_merge([
            'discussion_id' => $discussion_id,
            'posted_in_assignment_id' => $this->assignment->id,
            'user_id' => $user->id], $attributes));
    }

    private function _replyUrl(int $discussion_id): string
    {
        return "/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/{$this->questionMediaUpload->id}/$discussion_id/1";
    }

    private function _reply(array $timestamps, ?int $discussion_id = null)
    {
        return $this->actingAs($this->user)
            ->postJson($this->_replyUrl($discussion_id ?? $this->discussion_id), [
                'type' => 'text',
                'text' => 'a reply about the video',
                'timestamps' => $timestamps]);
    }

    /**
     * Deleting comments with files also deletes them from S3; the timestamp cleanup doesn't depend on files.
     */
    private function _withoutFiles(): void
    {
        DB::table('discussion_comments')->update(['file' => null]);
    }

    private function _addTimestamp(DiscussionComment $reply, DiscussionComment $referenced, float $seconds, int $order = 0): int
    {
        return DB::table('discussion_comment_timestamps')->insertGetId([
            'discussion_comment_id' => $reply->id,
            'referenced_discussion_comment_id' => $referenced->id,
            'seconds' => $seconds,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Posting a reply with timestamps
    |--------------------------------------------------------------------------
    */
    #[Test]
    public function a_reply_can_point_to_a_moment_in_a_video_comment_in_its_thread()
    {
        $this->_reply([['discussion_comment_id' => $this->video_comment->id, 'seconds' => 83.456]])
            ->assertJson(['type' => 'success']);

        $reply_id = DiscussionComment::where('text', 'a reply about the video')->value('id');
        $this->assertDatabaseHas('discussion_comment_timestamps', [
            'discussion_comment_id' => $reply_id,
            'referenced_discussion_comment_id' => $this->video_comment->id,
            'seconds' => 83.46]);
    }

    #[Test]
    public function a_reply_can_point_to_a_moment_in_an_audio_comment_in_its_thread()
    {
        $this->_reply([['discussion_comment_id' => $this->audio_comment->id, 'seconds' => 12]])
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('discussion_comment_timestamps', [
            'referenced_discussion_comment_id' => $this->audio_comment->id,
            'seconds' => 12]);
    }

    #[Test]
    public function a_reply_can_only_have_one_timestamp()
    {
        $this->_reply([
            ['discussion_comment_id' => $this->video_comment->id, 'seconds' => 10],
            ['discussion_comment_id' => $this->audio_comment->id, 'seconds' => 20]])
            ->assertJson(['type' => 'error',
                'message' => 'A reply can only have one timestamp.']);
        $this->assertDatabaseMissing('discussion_comments', ['text' => 'a reply about the video']);
        $this->assertEquals(0, DB::table('discussion_comment_timestamps')->count());
    }

    #[Test]
    public function a_reply_without_timestamps_still_works()
    {
        $this->actingAs($this->user)
            ->postJson($this->_replyUrl($this->discussion_id), ['type' => 'text', 'text' => 'a plain reply'])
            ->assertJson(['type' => 'success']);
        $this->assertEquals(0, DB::table('discussion_comment_timestamps')->count());
    }

    #[Test]
    public function timestamps_can_only_point_to_audio_or_video_comments()
    {
        $this->_reply([['discussion_comment_id' => $this->text_comment->id, 'seconds' => 5]])
            ->assertJson(['type' => 'error',
                'message' => 'Timestamps can only point to audio or video comments.']);
        $this->assertDatabaseMissing('discussion_comments', ['text' => 'a reply about the video']);
    }

    #[Test]
    public function timestamps_can_only_point_to_comments_in_the_same_thread()
    {
        $other_discussion_id = $this->_startDiscussion();
        $other_video_comment = $this->_comment($other_discussion_id, $this->student_user, ['file' => 'other-video.webm', 'recording_type' => 'video']);
        $this->_reply([['discussion_comment_id' => $other_video_comment->id, 'seconds' => 5]])
            ->assertJson(['type' => 'error',
                'message' => 'Timestamps can only point to comments in the same thread.']);
    }

    #[Test]
    public function timestamps_cannot_point_to_a_comment_that_no_longer_exists()
    {
        $this->_reply([['discussion_comment_id' => 999999999, 'seconds' => 5]])
            ->assertJson(['type' => 'error',
                'message' => 'One of the comments you added a timestamp for no longer exists.']);
    }

    #[Test]
    public function a_new_thread_cannot_have_timestamps()
    {
        $this->_reply([['discussion_comment_id' => $this->video_comment->id, 'seconds' => 5]], 0)
            ->assertJson(['type' => 'error',
                'message' => 'Timestamps can only be added to replies in an existing thread.']);
    }

    #[Test]
    public function timestamps_must_have_a_comment_and_a_non_negative_time()
    {
        $this->_reply([['seconds' => -1]])
            ->assertJsonValidationErrors(['timestamps.0.discussion_comment_id', 'timestamps.0.seconds']);
    }

    /*
    |--------------------------------------------------------------------------
    | Editing
    |--------------------------------------------------------------------------
    */
    #[Test]
    public function editing_a_reply_can_remove_its_timestamp()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $timestamp_id = $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->actingAs($this->user)
            ->patchJson("/api/discussion-comments/{$reply->id}", [
                'type' => 'text',
                'text' => 'an edited reply',
                'viewing_assignment_id' => $this->assignment->id,
                'keep_timestamp_ids' => []])
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('discussion_comment_timestamps', ['id' => $timestamp_id]);
    }

    #[Test]
    public function editing_a_reply_can_keep_its_timestamp()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $timestamp_id = $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->actingAs($this->user)
            ->patchJson("/api/discussion-comments/{$reply->id}", [
                'type' => 'text',
                'text' => 'an edited reply',
                'viewing_assignment_id' => $this->assignment->id,
                'keep_timestamp_ids' => [$timestamp_id]])
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('discussion_comment_timestamps', ['id' => $timestamp_id]);
    }

    #[Test]
    public function editing_a_reply_without_saying_which_timestamps_to_keep_leaves_them_alone()
    {
        //re-recording an audio/video reply doesn't send keep_timestamp_ids
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $timestamp_id = $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->actingAs($this->user)
            ->patchJson("/api/discussion-comments/{$reply->id}", [
                'type' => 'text',
                'text' => 'an edited reply',
                'viewing_assignment_id' => $this->assignment->id])
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('discussion_comment_timestamps', ['id' => $timestamp_id]);
    }

    #[Test]
    public function editing_cannot_keep_timestamps_from_another_reply()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $other_reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'another reply']);
        $other_timestamp_id = $this->_addTimestamp($other_reply, $this->video_comment, 10);

        $this->actingAs($this->user)
            ->patchJson("/api/discussion-comments/{$reply->id}", [
                'type' => 'text',
                'text' => 'an edited reply',
                'viewing_assignment_id' => $this->assignment->id,
                'keep_timestamp_ids' => [$other_timestamp_id]])
            ->assertJson(['type' => 'success']);

        //it still belongs to the other reply and nothing was moved onto this one
        $this->assertDatabaseHas('discussion_comment_timestamps', ['id' => $other_timestamp_id,
            'discussion_comment_id' => $other_reply->id]);
        $this->assertDatabaseMissing('discussion_comment_timestamps', ['discussion_comment_id' => $reply->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Deleting (cleanup is done in the application; there are no foreign keys)
    |--------------------------------------------------------------------------
    */
    #[Test]
    public function deleting_a_reply_deletes_its_timestamps()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->actingAs($this->user)
            ->deleteJson("/api/discussion-comments/{$reply->id}?viewing_assignment_id={$this->assignment->id}")
            ->assertJson(['type' => 'info']);

        $this->assertDatabaseMissing('discussion_comment_timestamps', ['discussion_comment_id' => $reply->id]);
    }

    #[Test]
    public function deleting_a_video_comment_deletes_the_timestamps_that_point_to_it()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);
        $other_reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply about the audio']);
        $audio_timestamp_id = $this->_addTimestamp($other_reply, $this->audio_comment, 20);

        $this->actingAs($this->user)
            ->deleteJson("/api/discussion-comments/{$this->video_comment->id}?viewing_assignment_id={$this->assignment->id}")
            ->assertJson(['type' => 'info']);

        $this->assertDatabaseMissing('discussion_comment_timestamps', ['referenced_discussion_comment_id' => $this->video_comment->id]);
        //the reply is kept, and so are timestamps pointing to other comments
        $this->assertDatabaseHas('discussion_comments', ['id' => $reply->id]);
        $this->assertDatabaseHas('discussion_comment_timestamps', ['id' => $audio_timestamp_id]);
    }

    #[Test]
    public function removing_all_student_submissions_deletes_the_timestamps()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->_withoutFiles();
        Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($this->assignment->id, $this->question->id);

        $this->assertEquals(0, DB::table('discussion_comment_timestamps')->count());
    }

    #[Test]
    public function deleting_the_assignment_discussions_deletes_the_timestamps()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);

        $this->_withoutFiles();
        (new Discussion())->deleteByAssignment($this->assignment);

        $this->assertEquals(0, DB::table('discussion_comment_timestamps')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Showing timestamps
    |--------------------------------------------------------------------------
    */
    #[Test]
    public function discussions_include_each_replys_timestamp()
    {
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);

        $response = $this->actingAs($this->user)
            ->getJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/media-upload/{$this->questionMediaUpload->id}")
            ->assertJson(['type' => 'success']);

        $reply_json = collect($response->json('discussions.0.comments'))->firstWhere('id', $reply->id);
        $this->assertEquals($this->discussion_id, $reply_json['discussion_id']);
        $this->assertCount(1, $reply_json['timestamps']);
        $this->assertEquals($this->video_comment->id, $reply_json['timestamps'][0]['referenced_discussion_comment_id']);
        $this->assertEquals(10, $reply_json['timestamps'][0]['seconds']);
        $this->assertEquals("{$this->student_user->first_name} {$this->student_user->last_name}", $reply_json['timestamps'][0]['referenced_created_by_name']);
    }

    #[Test]
    public function the_grading_list_includes_timestamps()
    {
        $reply = $this->_comment($this->discussion_id, $this->student_user, ['text' => 'a student reply']);
        $this->_addTimestamp($reply, $this->video_comment, 10);

        $result = (new Discussion())->getByAssignmentQuestionMediaUploadId($this->assignment, $this->question, $this->questionMediaUpload->id);

        $student_comments = collect($result['discussions_by_user_id'])->firstWhere('user_id', $this->student_user->id)['comments'];
        $reply_json = collect($student_comments)->firstWhere('id', $reply->id);
        $this->assertCount(1, $reply_json['timestamps']);
        $this->assertEquals($this->video_comment->id, $reply_json['timestamps'][0]['referenced_discussion_comment_id']);
    }

    #[Test]
    public function timestamps_pointing_to_a_comment_that_no_longer_exists_are_not_shown()
    {
        //safety net in case a deletion ever bypasses the model event
        $reply = $this->_comment($this->discussion_id, $this->user, ['text' => 'a reply']);
        DB::table('discussion_comment_timestamps')->insert([
            'discussion_comment_id' => $reply->id,
            'referenced_discussion_comment_id' => 999999999,
            'seconds' => 10,
            'order' => 0,
            'created_at' => now(),
            'updated_at' => now()]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/discussions/assignment/{$this->assignment->id}/question/{$this->question->id}/media-upload/{$this->questionMediaUpload->id}");

        $reply_json = collect($response->json('discussions.0.comments'))->firstWhere('id', $reply->id);
        $this->assertEmpty($reply_json['timestamps']);
    }
}
