<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Course;
use App\DiscussionComment;
use App\Enrollment;
use App\Question;
use App\QuestionMediaUpload;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionCommentTest extends TestCase
{
    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id,'public'=>0]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create();
        $assignment_question_id = DB::table('assignment_question')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'discuss_it_settings' => '{"students_can_edit_comments":"1","students_can_delete_comments":"1","min_number_of_initiated_discussion_threads":"2","min_number_of_replies":"1","min_number_of_initiate_or_reply_in_threads":"1","min_number_of_comments":"1","min_number_of_words":"4","min_length_of_audio_video":"5 seconds","auto_grade":1,"completion_criteria":1,"response_modes":["text", "audio", "video"]}',
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
        $this->assignment_question = AssignmentSyncQuestion::find($assignment_question_id);
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
        $this->discussion_comment = DiscussionComment::create(['discussion_id' => $this->discussion_id,
            'user_id' => $this->student_user->id,
            'text' => 'sdfdsfds']);
        $this->discussion_comment_info = ['type' => 'text', 'text' => 'sdfsdfds'];

    }

    private function _updateDiscussItSettings(string $key)
    {
        $discuss_it_settings = json_decode($this->assignment_question->discuss_it_settings);
        $discuss_it_settings->{$key} = 0;
        $discuss_it_settings = json_encode($discuss_it_settings);
        $this->assignment_question->discuss_it_settings = $discuss_it_settings;
        $this->assignment_question->save();
    }

    /** @test */
    public function non_owner_cannot_update_caption()
    {
        $this->actingAs($this->student_user)
            ->patchJson("/api/question-media/{$this->discussion_comment->id}/caption/1",['model' => 'DiscussionComment'])
            ->assertJson(['message' => "You are not allowed to update this transcript."]);
    }

    /** @test */
    public function can_check_if_audio_video_satisfied_requirements_only_if_satisfy_general_submission_policy_or_it_is_instructor_assignment()
    {
        $this->actingAs($this->student_user)
            ->patchJson("/api/discussion-comments/assignment/{$this->assignment->id}/question/{$this->question->id}/audio-video-satisfied-file-requirements")
            ->assertJson(['message' => "No responses will be saved since you were not assigned to this assignment."]);

        $new_user = factory(User::class)->create();
        $new_course = factory(Course::class)->create(['user_id' => $new_user->id]);
        $new_assignment = factory(Assignment::class)->create(['course_id' => $new_course->id]);

        $this->actingAs($this->user)
            ->patchJson("/api/discussion-comments/assignment/{$new_assignment->id}/question/{$this->question->id}/audio-video-satisfied-file-requirements")
            ->assertJson(['message' => "You may not store audio/video discussion comments."]);
    }


    /** @test */
    public function can_post_audio_only_if_satisfy_general_submission_policy_or_it_is_instructor_assignment()
    {
        $this->actingAs($this->student_user)
            ->postJson("/api/discussion-comments/assignment/{$this->assignment->id}/question/{$this->question->id}/audio")
            ->assertJson(['message' => "No responses will be saved since you were not assigned to this assignment."]);

        $new_user = factory(User::class)->create();
        $new_course = factory(Course::class)->create(['user_id' => $new_user->id]);
        $new_assignment = factory(Assignment::class)->create(['course_id' => $new_course->id]);

        $this->actingAs($this->user)
            ->postJson("/api/discussion-comments/assignment/{$new_assignment->id}/question/{$this->question->id}/audio")
            ->assertJson(['message' => "You may not store audio/video discussion comments."]);
    }

    /** @test */
    public function non_owner_or_not_instructor_cannot_get_if_criteria_satisfied()
    {

        $new_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_user)
            ->getJson("/api/discussion-comments/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->user->id}/satisfied")
            ->assertJson(['message' => "You are not allowed to view whether the requirements have been satisfied."]);


        $new_student_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($this->student_user)
            ->getJson("/api/discussion-comments/assignment/{$this->assignment->id}/question/{$this->question->id}/user/$new_student_user->id/satisfied")
            ->assertJson(['message' => "You are not allowed to view whether the requirements have been satisfied."]);

    }


    /** @test */
    public function non_owner_or_not_instructor_cannot_check_deleting_will_make_requirements_not_satisfied()
    {

        $new_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_user)
            ->getJson("/api/discussion-comments/{$this->discussion_comment->id}/deleting-will-make-requirements-not-satisfied")
            ->assertJson(['message' => "You are not allowed to check whether deleting this comment will make the requirements not satisfied."]);


        $new_student_user = factory(User::class)->create(['role' => 3]);
        $this->actingAs($this->student_user)
            ->getJson("/api/discussion-comments/{$this->discussion_comment->id}/deleting-will-make-requirements-not-satisfied")
            ->assertJson(['message' => "You are not allowed to check whether deleting this comment will make the requirements not satisfied."]);

    }




    /** @test */
    public function instructor_cannot_edit_or_delete_discussion_comment_if_not_their_assignment()
    {

        $new_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_user)
            ->deleteJson("/api/discussion-comments/{$this->discussion_comment->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to delete this comment.']);
        $this->actingAs($new_user)
            ->patchJson("/api/discussion-comments/{$this->discussion_comment->id}", $this->discussion_comment_info)
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to edit this comment.']);
    }

    /** @test */
    public function student_cannot_edit_or_delete_discussion_comment_if_not_set_in_settings()
    {
        $this->_updateDiscussItSettings('students_can_delete_comments');
        $this->_updateDiscussItSettings('students_can_edit_comments');
        $this->actingAs($this->student_user)
            ->deleteJson("/api/discussion-comments/{$this->discussion_comment->id}")
            ->assertJson(['type' => 'error',
                'message' => "Your instructor's settings indicate you may not delete your comments."]);
        $this->actingAs($this->student_user)
            ->patchJson("/api/discussion-comments/{$this->discussion_comment->id}", $this->discussion_comment_info)
            ->assertJson(['type' => 'error',
                'message' => "Your instructor's settings indicate you may not edit your comments."]);
    }

    /** @test */
    public function student_cannot_edit_or_delete_discussion_comment_if_instructor_graded_it()
    {
        SubmissionFile::create(['assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'discuss_it',
            'grader_id' => $this->user->id,
            'original_filename' => '',
            'submission' => 'some.pdf',
            'date_submitted' => Carbon::now()]);
        $this->actingAs($this->student_user)
            ->deleteJson("/api/discussion-comments/{$this->discussion_comment->id}")
            ->assertJson(['type' => 'error',
                'message' => "You cannot delete this comment since it was already graded."]);
        $this->actingAs($this->student_user)
            ->patchJson("/api/discussion-comments/{$this->discussion_comment->id}", $this->discussion_comment_info)
            ->assertJson(['type' => 'error',
                'message' => "You cannot edit this comment since it was already graded."]);

    }

    /** @test */
    public function student_cannot_edit_or_delete_discussion_comment_based_on_general_submission_policy()
    {
        DB::table('enrollments')->delete();
        $this->actingAs($this->student_user)
            ->deleteJson("/api/discussion-comments/{$this->discussion_comment->id}")
            ->assertJson(['type' => 'error',
                'message' => "No responses will be saved since you were not assigned to this assignment."]);
        $this->actingAs($this->student_user)
            ->patchJson("/api/discussion-comments/{$this->discussion_comment->id}", $this->discussion_comment_info)
            ->assertJson(['type' => 'error',
                'message' => "No responses will be saved since you were not assigned to this assignment."]);


    }

    /** @test */
    public function number_of_participated_threads_counts_distinct_threads_not_total_comments()
    {
        // the seeded comment satisfies the requirement
        $this->discussion_comment->satisfied_requirement = 1;
        $this->discussion_comment->save();

        // the same student posts a second satisfying comment in the SAME thread
        DiscussionComment::create([
            'discussion_id' => $this->discussion_id,
            'user_id' => $this->student_user->id,
            'text' => 'a second satisfying comment in the same thread',
            'satisfied_requirement' => 1
        ]);

        $discussionComment = new DiscussionComment();
        $number_of_threads = $discussionComment->numberOfInitiateOrReplyInThreadsThatSatisfiedTheRequirements(
            $this->assignment->id,
            $this->question->id,
            $this->student_user->id
        );

        // two satisfying comments, but only one distinct thread participated in
        $this->assertEquals(1, $number_of_threads);
    }

    /** @test */
    public function number_of_participated_threads_counts_each_distinct_thread_once()
    {
        $this->discussion_comment->satisfied_requirement = 1;
        $this->discussion_comment->save();

        $second_discussion_id = DB::table('discussions')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'media_upload_id' => $this->questionMediaUpload->id,
            'user_id' => $this->user->id]);

        DiscussionComment::create([
            'discussion_id' => $second_discussion_id,
            'user_id' => $this->student_user->id,
            'text' => 'a satisfying reply in a second thread',
            'satisfied_requirement' => 1
        ]);

        $discussionComment = new DiscussionComment();
        $number_of_threads = $discussionComment->numberOfInitiateOrReplyInThreadsThatSatisfiedTheRequirements(
            $this->assignment->id,
            $this->question->id,
            $this->student_user->id
        );

        $this->assertEquals(2, $number_of_threads);
    }

    /** @test */
    public function satisfied_requirements_response_includes_satisfied_min_number_of_comments_requirement()
    {
        // the fixture's discuss_it_settings has min_number_of_comments = 1, and the
        // seeded comment does not yet satisfy the per-comment requirement (word
        // count/audio length), so the student has 0 satisfying comments.
        $discussionComment = new DiscussionComment();
        $result = $discussionComment->satisfiedRequirements(
            $this->assignment,
            $this->question->id,
            $this->student_user->id,
            $this->assignment_question
        );

        // satisfiedRequirements() computes this value internally and folds it into
        // satisfied_all_requirements, but previously never exposed it in the
        // response array -- the front-end had no correct signal to render the
        // "Submit at least N comment(s)" checkmark from.
        $this->assertArrayHasKey('satisfied_min_number_of_comments_requirement', $result);
        $this->assertFalse($result['satisfied_min_number_of_comments_requirement']);
        $this->assertEquals(0, $result['number_of_comments_submitted']);

        // once the student has a comment that satisfies the per-comment
        // requirement, the flag should flip to true
        $this->discussion_comment->satisfied_requirement = 1;
        $this->discussion_comment->save();

        $result = $discussionComment->satisfiedRequirements(
            $this->assignment,
            $this->question->id,
            $this->student_user->id,
            $this->assignment_question
        );

        $this->assertTrue($result['satisfied_min_number_of_comments_requirement']);
        $this->assertEquals(1, $result['number_of_comments_submitted']);
    }


}
