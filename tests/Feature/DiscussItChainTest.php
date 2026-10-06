<?php

namespace Tests\Feature;

use App\Assignment;
use App\BetaAssignment;
use App\Course;
use App\DiscussionComment;
use App\DiscussionGroup;
use App\DiscussItChain;
use App\Enrollment;
use App\Helpers\Helper;
use App\Question;
use App\QuestionMediaUpload;
use App\Section;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Linked ("daisy-chained") Discuss-it questions: the same question in several assignments of one course,
 * sharing one discussion, with completion judged per assignment.
 */
class DiscussItChainTest extends TestCase
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
        $this->question = factory(Question::class)->create([
            'technology' => 'qti',
            'qti_json_type' => 'discuss_it',
            'qti_json' => json_encode(['questionType' => 'discuss_it',
                'prompt' => '<p>Discuss this.</p>',
                'media_uploads' => [],
                'jsonType' => 'question_json'])]);
        $this->questionMediaUpload = QuestionMediaUpload::create([
            'question_id' => $this->question->id,
            'original_filename' => 'some name',
            'size' => 200,
            's3_key' => 'some key',
            'transcript' => 'sdfdsf',
            'status' => 'completed'
        ]);

        $this->assignment_1 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignment_2 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignment_3 = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        foreach ([$this->assignment_1, $this->assignment_2, $this->assignment_3] as $assignment) {
            $this->_addQuestionToAssignment($assignment);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function _addQuestionToAssignment(Assignment $assignment, int $number_of_groups = 1): void
    {
        DB::table('assignment_question')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'discuss_it_settings' => json_encode([
                'students_can_edit_comments' => '1',
                'students_can_delete_comments' => '1',
                'min_number_of_initiated_discussion_threads' => '0',
                'min_number_of_initiate_or_reply_in_threads' => '0',
                'min_number_of_replies' => '0',
                'min_number_of_comments' => '1',
                'min_number_of_words' => '1',
                'min_length_of_audio_video' => '5 seconds',
                'response_modes' => ['text', 'audio', 'video'],
                'auto_grade' => 1,
                'completion_criteria' => 1,
                'number_of_groups' => $number_of_groups]),
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 'file'
        ]);
    }

    private function _setNumberOfGroups(Assignment $assignment, int $number_of_groups): void
    {
        $discuss_it_settings = json_decode(DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->value('discuss_it_settings'), true);
        $discuss_it_settings['number_of_groups'] = $number_of_groups;
        DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['discuss_it_settings' => json_encode($discuss_it_settings)]);
    }

    private function _startDiscussion(Assignment $assignment, User $user, int $group = 1): int
    {
        return DB::table('discussions')->insertGetId([
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'media_upload_id' => $this->questionMediaUpload->id,
            'user_id' => $user->id,
            'group' => $group]);
    }

    private function _comment(int $discussion_id, Assignment $posted_in_assignment, User $user): DiscussionComment
    {
        return DiscussionComment::create([
            'discussion_id' => $discussion_id,
            'posted_in_assignment_id' => $posted_in_assignment->id,
            'user_id' => $user->id,
            'text' => 'some comment text',
            'satisfied_requirement' => 1]);
    }

    private function _putInGroup(Assignment $assignment, User $user, int $group): void
    {
        DB::table('discussion_groups')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $user->id,
            'group' => $group]);
    }

    private function _link(Assignment $assignment, array $assignments_to_link_with)
    {
        return $this->actingAs($this->user)
            ->postJson("/api/assignments/{$assignment->id}/question/{$this->question->id}/discuss-it-link",
                ['assignment_ids' => array_map(function ($item) {
                    return $item->id;
                }, $assignments_to_link_with)]);
    }

    private function _createRevision(int $revision_number): int
    {
        //a revision is a snapshot of the question, so copy every question column the revisions table also has
        $question = (array)DB::table('questions')->where('id', $this->question->id)->first();
        $revision = [];
        foreach (Schema::getColumnListing('question_revisions') as $column) {
            if (!in_array($column, ['id', 'created_at', 'updated_at']) && array_key_exists($column, $question)) {
                $revision[$column] = $question[$column];
            }
        }
        $revision['revision_number'] = $revision_number;
        $revision['action'] = 'edit';
        $revision['question_id'] = $this->question->id;
        $revision['created_at'] = now();
        $revision['updated_at'] = now();
        return DB::table('question_revisions')->insertGetId($revision);
    }

    private function _setRevision(Assignment $assignment, int $question_revision_id): void
    {
        DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['question_revision_id' => $question_revision_id]);
    }

    private function _chainId(Assignment $assignment): ?int
    {
        return DiscussItChain::chainId($assignment->id, $this->question->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Linking and unlinking
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function non_owner_cannot_link_or_unlink()
    {
        $new_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_user)
            ->postJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link",
                ['assignment_ids' => [$this->assignment_1->id]])
            ->assertJson(['message' => "You are not allowed to update the discuss-it settings for that question."]);
        $this->actingAs($new_user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['message' => "You are not allowed to update the discuss-it settings for that question."]);
    }

    /** @test */
    public function owner_can_link_assignments_in_the_same_course()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])
            ->assertJson(['type' => 'success']);
        $this->assertNotNull($this->_chainId($this->assignment_1));
        $this->assertEquals($this->_chainId($this->assignment_1), $this->_chainId($this->assignment_2));
        $this->assertNull($this->_chainId($this->assignment_3));
    }

    /** @test */
    public function cannot_link_with_an_assignment_in_another_course()
    {
        $other_course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $other_assignment = factory(Assignment::class)->create(['course_id' => $other_course->id]);
        $this->_addQuestionToAssignment($other_assignment);
        $this->_link($this->assignment_2, [$other_assignment])
            ->assertJson(['type' => 'error',
                'message' => 'Only assignments in this course that contain this Discuss-it question can be linked.']);
        $this->assertNull($this->_chainId($this->assignment_2));
    }

    /** @test */
    public function there_is_at_most_one_link_per_question_per_course()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        //linking a third assignment with either member joins the existing link
        $this->_link($this->assignment_3, [$this->assignment_2])->assertJson(['type' => 'success']);
        $chain_id = $this->_chainId($this->assignment_1);
        $this->assertEquals($chain_id, $this->_chainId($this->assignment_3));
        $this->assertEquals(1, DiscussItChain::where('course_id', $this->course->id)
            ->where('question_id', $this->question->id)
            ->count());
    }

    /** @test */
    public function owner_can_unlink_before_students_comment_and_a_link_of_one_is_removed()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'success']);
        $this->assertNull($this->_chainId($this->assignment_1));
        $this->assertNull($this->_chainId($this->assignment_2));
        $this->assertEquals(0, DiscussItChain::where('course_id', $this->course->id)->count());
    }

    /** @test */
    public function can_unlink_when_students_commented_only_in_the_other_linked_assignments()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'success']);
        $this->assertNull($this->_chainId($this->assignment_2));
        $this->assertDatabaseHas('discussion_comments', ['discussion_id' => $discussion_id,
            'posted_in_assignment_id' => $this->assignment_1->id]);
    }

    /** @test */
    public function cannot_unlink_after_real_students_comment_in_this_assignment()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        //a reply made in assignment 2 on a thread started in assignment 1
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'error',
                'message' => 'Students have already commented on this question in this assignment, so it can no longer be unlinked.']);
        $this->assertNotNull($this->_chainId($this->assignment_2));
    }

    /** @test */
    public function cannot_unlink_when_students_elsewhere_replied_to_a_thread_started_here()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->user);
        $this->_comment($discussion_id, $this->assignment_2, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function the_settings_modal_says_whether_this_assignment_can_be_unlinked()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_comment($this->_startDiscussion($this->assignment_1, $this->student_user), $this->assignment_1, $this->student_user);
        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-settings")
            ->assertJson(['discuss_it_links' => ['can_unlink' => true, 'unlink_blocked_reason' => null]]);
        $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment_1->id}/question/{$this->question->id}/discuss-it-settings")
            ->assertJson(['discuss_it_links' => ['can_unlink' => false]]);
    }

    /** @test */
    public function fake_student_comments_do_not_block_unlinking()
    {
        $fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $fake_student);
        $this->_comment($discussion_id, $this->assignment_1, $fake_student);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'success']);
    }

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function linking_gives_the_new_assignment_the_same_number_of_groups()
    {
        $this->_setNumberOfGroups($this->assignment_1, 3);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->assertEquals(3, DiscussItChain::numberOfGroups($this->assignment_2->id, $this->question->id));
    }

    /** @test */
    public function when_only_one_assignment_has_student_comments_its_number_of_groups_is_kept()
    {
        $this->_setNumberOfGroups($this->assignment_1, 3);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        //nothing is linked yet, so the assignment whose students already commented decides the number of groups
        $this->_link($this->assignment_2, [$this->assignment_1])
            ->assertJson(['type' => 'success']);
        $this->assertEquals(1, DiscussItChain::numberOfGroups($this->assignment_1->id, $this->question->id));
        $this->assertEquals(1, DiscussItChain::numberOfGroups($this->assignment_2->id, $this->question->id));
    }

    /** @test */
    public function cannot_join_a_link_when_students_already_commented_with_a_different_number_of_groups()
    {
        $this->_setNumberOfGroups($this->assignment_1, 3);
        $this->_link($this->assignment_3, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        $this->_link($this->assignment_2, [$this->assignment_1])
            ->assertJson(['type' => 'error',
                'message' => "Linked assignments must use the same number of groups (3). Students have already commented in {$this->assignment_2->name}, so it cannot be changed there."]);
        $this->assertNull($this->_chainId($this->assignment_2));
    }

    /** @test */
    public function a_student_who_has_not_commented_is_moved_into_their_existing_group()
    {
        $this->_setNumberOfGroups($this->assignment_1, 2);
        $this->_setNumberOfGroups($this->assignment_2, 2);
        $this->_putInGroup($this->assignment_1, $this->student_user, 2);
        $this->_putInGroup($this->assignment_2, $this->student_user, 1);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $groups = DB::table('discussion_groups')
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->student_user->id)
            ->pluck('group')
            ->unique()
            ->values()
            ->toArray();
        $this->assertEquals([2], $groups);
    }

    /** @test */
    public function cannot_link_when_a_student_commented_in_two_different_groups()
    {
        $this->_setNumberOfGroups($this->assignment_1, 2);
        $this->_setNumberOfGroups($this->assignment_2, 2);
        $this->_putInGroup($this->assignment_1, $this->student_user, 2);
        $this->_putInGroup($this->assignment_2, $this->student_user, 1);
        $this->_comment($this->_startDiscussion($this->assignment_1, $this->student_user, 2), $this->assignment_1, $this->student_user);
        $this->_comment($this->_startDiscussion($this->assignment_2, $this->student_user, 1), $this->assignment_2, $this->student_user);
        $this->_link($this->assignment_2, [$this->assignment_1])
            ->assertJson(['type' => 'error',
                'message' => "1 student is in different groups in these assignments and has already commented in both groups. Linking would hide some of their comments from them, so these assignments can't be linked."]);
    }

    /*
    |--------------------------------------------------------------------------
    | Shared discussion, per-assignment completion
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function linked_assignments_show_each_others_discussions()
    {
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);
        $url = "/api/discussions/assignment/{$this->assignment_2->id}/question/{$this->question->id}/media-upload/{$this->questionMediaUpload->id}";

        $response = $this->actingAs($this->user)->getJson($url);
        $this->assertEmpty($response->json('discussions'));

        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $response = $this->actingAs($this->user)->getJson($url);
        $this->assertCount(1, $response->json('discussions'));
        $this->assertEquals($this->assignment_1->id, $response->json('discussions.0.comments.0.assignment_id'));
    }

    /** @test */
    public function completion_only_counts_comments_made_in_that_assignment()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        //a reply made while working in assignment 2, on the thread started in assignment 1
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);

        $discussionComment = new DiscussionComment();
        $this->assertEquals(1, $discussionComment->numberOfCommentsThatSatisfiedTheRequirements($this->assignment_1->id, $this->question->id, $this->student_user->id));
        $this->assertEquals(2, $discussionComment->numberOfCommentsThatSatisfiedTheRequirements($this->assignment_2->id, $this->question->id, $this->student_user->id));
    }

    /** @test */
    public function comments_record_the_assignment_they_were_made_in_when_not_given()
    {
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $discussion_comment = DiscussionComment::create([
            'discussion_id' => $discussion_id,
            'user_id' => $this->student_user->id,
            'text' => 'some comment text']);
        $this->assertEquals($this->assignment_1->id, $discussion_comment->fresh()->posted_in_assignment_id);
    }

    /** @test */
    public function can_reply_to_a_thread_from_a_linked_assignment_but_not_an_unlinked_one()
    {
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);
        $url = "/api/discussions/assignment/{$this->assignment_2->id}/question/{$this->question->id}/{$this->questionMediaUpload->id}/$discussion_id/1";

        $this->actingAs($this->user)
            ->postJson($url, ['type' => 'text', 'text' => 'a reply from the second assignment'])
            ->assertJson(['type' => 'error', 'message' => 'That discussion thread is not part of this assignment.']);

        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->actingAs($this->user)
            ->postJson($url, ['type' => 'text', 'text' => 'a reply from the second assignment'])
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('discussion_comments', [
            'discussion_id' => $discussion_id,
            'posted_in_assignment_id' => $this->assignment_2->id]);
    }

    /** @test */
    public function student_cannot_edit_or_delete_a_comment_made_in_another_linked_assignment()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_comment = $this->_comment($this->_startDiscussion($this->assignment_1, $this->student_user), $this->assignment_1, $this->student_user);

        $this->actingAs($this->student_user)
            ->deleteJson("/api/discussion-comments/{$discussion_comment->id}?viewing_assignment_id={$this->assignment_2->id}")
            ->assertJson(['type' => 'error',
                'message' => 'This comment was made in another assignment, so you can only delete it from that assignment.']);
        $this->actingAs($this->student_user)
            ->patchJson("/api/discussion-comments/{$discussion_comment->id}", [
                'type' => 'text',
                'text' => 'edited text',
                'viewing_assignment_id' => $this->assignment_2->id])
            ->assertJson(['type' => 'error',
                'message' => 'This comment was made in another assignment, so you can only edit it from that assignment.']);
        //older pages don't send the assignment they're in; that's refused for linked questions
        $this->actingAs($this->student_user)
            ->deleteJson("/api/discussion-comments/{$discussion_comment->id}")
            ->assertJson(['type' => 'error',
                'message' => 'This comment was made in another assignment, so you can only delete it from that assignment.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Removing, deleting, revisions
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function cannot_remove_a_linked_question_after_students_comment_in_this_assignment()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_comment($this->_startDiscussion($this->assignment_2, $this->student_user), $this->assignment_2, $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You cannot remove this question since it is linked in other assignments and students have already commented on it in this assignment. Their comments here would need to be removed first.']);
    }

    /** @test */
    public function can_remove_a_linked_question_when_students_commented_only_in_the_other_linked_assignments()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/questions/{$this->question->id}")
            ->assertJson(['type' => 'info']);
        $this->assertDatabaseMissing('assignment_question', ['assignment_id' => $this->assignment_2->id,
            'question_id' => $this->question->id]);
        $this->assertNull($this->_chainId($this->assignment_1));
        $this->assertDatabaseHas('discussion_comments', ['discussion_id' => $discussion_id]);
    }

    /** @test */
    public function deleting_an_assignment_keeps_comments_made_elsewhere_on_threads_started_elsewhere()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        //nothing would be lost, so no confirmation is needed
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}")
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseMissing('assignments', ['id' => $this->assignment_2->id]);
        $this->assertDatabaseHas('discussion_comments', ['discussion_id' => $discussion_id,
            'user_id' => $this->student_user->id]);
    }

    /** @test */
    public function revision_status_lists_linked_assignments_and_blocks_updates_after_students_comment()
    {
        $url = "/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link-revision-status";
        $this->actingAs($this->user)->getJson($url)
            ->assertJson(['type' => 'success', 'is_linked' => false, 'can_update_revision' => true]);

        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->getJson($url)
            ->assertJson(['type' => 'success',
                'is_linked' => true,
                'can_update_revision' => true,
                'other_linked_assignment_names' => [$this->assignment_1->name]]);

        $this->_comment($this->_startDiscussion($this->assignment_1, $this->student_user), $this->assignment_1, $this->student_user);
        $this->actingAs($this->user)->getJson($url)
            ->assertJson(['type' => 'success', 'is_linked' => true, 'can_update_revision' => false]);
    }

    /*
    |--------------------------------------------------------------------------
    | Adding questions and copying assignments
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function link_options_list_the_other_assignments_with_the_question()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $response = $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment_3->id}/discuss-it-link-options",
                ['question_ids' => [$this->question->id]])
            ->assertJson(['type' => 'success']);
        $this->assertCount(1, $response->json('discuss_it_link_options'));
        $link_option = $response->json('discuss_it_link_options.0');
        $this->assertEquals($this->question->id, $link_option['question_id']);
        $this->assertTrue($link_option['chain_exists']);
        $this->assertEqualsCanonicalizing([$this->assignment_1->id, $this->assignment_2->id],
            array_column($link_option['linked_assignments'], 'id'));
        $this->assertEmpty($link_option['unlinked_assignments']);
    }

    /** @test */
    public function non_owner_cannot_get_link_options()
    {
        $new_user = factory(User::class)->create(['role' => 2]);
        $this->actingAs($new_user)
            ->postJson("/api/assignments/{$this->assignment_3->id}/discuss-it-link-options",
                ['question_ids' => [$this->question->id]])
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function creating_an_assignment_from_a_template_links_its_discuss_it_questions()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment_1->id}/create-assignment-from-template", [
                'level' => 'properties_and_questions',
                'assign_to_groups' => 1,
                'reset_discuss_it_settings_to_default' => 0,
                'link_discuss_it_questions' => 1])
            ->assertJson(['type' => 'success']);
        $new_assignment = Assignment::where('course_id', $this->course->id)
            ->where('name', $this->assignment_1->name . ' copy')
            ->orderBy('id', 'desc')
            ->first();
        $this->assertNotNull($this->_chainId($this->assignment_1));
        $this->assertEquals($this->_chainId($this->assignment_1), $this->_chainId($new_assignment));
    }

    /** @test */
    public function creating_an_assignment_from_a_template_can_leave_discuss_it_questions_unlinked()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment_1->id}/create-assignment-from-template", [
                'level' => 'properties_and_questions',
                'assign_to_groups' => 1,
                'reset_discuss_it_settings_to_default' => 0,
                'link_discuss_it_questions' => 0])
            ->assertJson(['type' => 'success']);
        $this->assertNull($this->_chainId($this->assignment_1));
    }

    /*
    |--------------------------------------------------------------------------
    | Revisions and groups across a link
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function linking_moves_the_new_assignment_to_the_linked_revision()
    {
        $revision_1 = $this->_createRevision(1);
        $revision_2 = $this->_createRevision(2);
        $this->_setRevision($this->assignment_1, $revision_1);
        $this->_setRevision($this->assignment_2, $revision_2);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->assertEquals($revision_1, DiscussItChain::questionRevisionId($this->assignment_2->id, $this->question->id));
    }

    /** @test */
    public function when_only_one_assignment_has_student_comments_its_revision_is_kept()
    {
        $revision_1 = $this->_createRevision(1);
        $revision_2 = $this->_createRevision(2);
        $this->_setRevision($this->assignment_1, $revision_1);
        $this->_setRevision($this->assignment_2, $revision_2);
        $this->_comment($this->_startDiscussion($this->assignment_2, $this->student_user), $this->assignment_2, $this->student_user);
        //nothing is linked yet, so the assignment whose students already commented decides the revision
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->assertEquals($revision_2, DiscussItChain::questionRevisionId($this->assignment_1->id, $this->question->id));
        $this->assertEquals($revision_2, DiscussItChain::questionRevisionId($this->assignment_2->id, $this->question->id));
    }

    /** @test */
    public function cannot_join_a_link_when_students_commented_on_a_different_revision()
    {
        $revision_1 = $this->_createRevision(1);
        $revision_2 = $this->_createRevision(2);
        $this->_setRevision($this->assignment_1, $revision_1);
        $this->_setRevision($this->assignment_3, $revision_1);
        $this->_link($this->assignment_3, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_setRevision($this->assignment_2, $revision_2);
        $this->_comment($this->_startDiscussion($this->assignment_2, $this->student_user), $this->assignment_2, $this->student_user);
        $this->_link($this->assignment_2, [$this->assignment_1])
            ->assertJson(['type' => 'error',
                'message' => "Linked assignments must use the same question revision (revision 1). Students have already commented in {$this->assignment_2->name}, so it cannot be changed there."]);
        $this->assertEquals($revision_2, DiscussItChain::questionRevisionId($this->assignment_2->id, $this->question->id));
        $this->assertNull($this->_chainId($this->assignment_2));
    }

    /** @test */
    public function a_student_keeps_the_same_group_in_every_linked_assignment()
    {
        $this->_setNumberOfGroups($this->assignment_1, 3);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_putInGroup($this->assignment_1, $this->student_user, 3);
        $discussionGroup = new DiscussionGroup();
        $this->assertEquals(3, $discussionGroup->store($this->assignment_2->id, $this->question->id, $this->student_user->id));
        //no new row is needed: the group comes from the linked assignment
        $this->assertDatabaseMissing('discussion_groups', [
            'assignment_id' => $this->assignment_2->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Alpha/Beta courses
    |--------------------------------------------------------------------------
    */

    private function _createBetaCourse(): array
    {
        $this->course->alpha = 1;
        $this->course->save();
        $beta_instructor = factory(User::class)->create(['role' => 2]);
        $beta_course = factory(Course::class)->create(['user_id' => $beta_instructor->id]);
        DB::table('beta_courses')->insert(['id' => $beta_course->id, 'alpha_course_id' => $this->course->id]);
        $beta_assignments = [];
        foreach ([$this->assignment_1, $this->assignment_2] as $alpha_assignment) {
            $beta_assignment = factory(Assignment::class)->create(['course_id' => $beta_course->id]);
            BetaAssignment::create(['id' => $beta_assignment->id, 'alpha_assignment_id' => $alpha_assignment->id]);
            $this->_addQuestionToAssignment($beta_assignment);
            $beta_assignments[] = $beta_assignment;
        }
        return [$beta_instructor, $beta_course, $beta_assignments];
    }

    /** @test */
    public function beta_courses_get_the_same_links_as_the_alpha_course()
    {
        [, , $beta_assignments] = $this->_createBetaCourse();
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->assertNotNull($this->_chainId($beta_assignments[0]));
        $this->assertEquals($this->_chainId($beta_assignments[0]), $this->_chainId($beta_assignments[1]));
        $this->assertNotEquals($this->_chainId($this->assignment_1), $this->_chainId($beta_assignments[0]));

        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'success']);
        $this->assertNull($this->_chainId($beta_assignments[0]));
        $this->assertNull($this->_chainId($beta_assignments[1]));
    }

    /** @test */
    public function beta_instructors_cannot_link_or_unlink()
    {
        [$beta_instructor, , $beta_assignments] = $this->_createBetaCourse();
        $this->actingAs($beta_instructor)
            ->postJson("/api/assignments/{$beta_assignments[1]->id}/question/{$this->question->id}/discuss-it-link",
                ['assignment_ids' => [$beta_assignments[0]->id]])
            ->assertJson(['type' => 'error',
                'message' => 'Links for Discuss-it questions in Beta courses are managed by the Alpha course.']);
    }

    /** @test */
    public function alpha_cannot_unlink_after_beta_students_comment_in_its_copy()
    {
        [, , $beta_assignments] = $this->_createBetaCourse();
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        //beta_assignments[1] is the Beta copy of assignment 2
        $this->_comment($this->_startDiscussion($beta_assignments[1], $this->student_user), $beta_assignments[1], $this->student_user);
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment_2->id}/question/{$this->question->id}/discuss-it-link")
            ->assertJson(['type' => 'error',
                'message' => 'Students in a tethered Beta course have already commented on this question in their copy of this assignment, so it can no longer be unlinked.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Copying courses
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function a_course_copy_keeps_the_links_and_starts_with_one_group()
    {
        $this->_setNumberOfGroups($this->assignment_1, 3);
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);

        $new_course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $new_assignment_1 = factory(Assignment::class)->create(['course_id' => $new_course->id]);
        $new_assignment_2 = factory(Assignment::class)->create(['course_id' => $new_course->id]);
        $new_assignment_3 = factory(Assignment::class)->create(['course_id' => $new_course->id]);
        foreach ([$new_assignment_1, $new_assignment_2, $new_assignment_3] as $new_assignment) {
            $this->_addQuestionToAssignment($new_assignment, 3);
        }
        DiscussItChain::copyChainsToCourse([
            $this->assignment_1->id => $new_assignment_1->id,
            $this->assignment_2->id => $new_assignment_2->id,
            $this->assignment_3->id => $new_assignment_3->id], $new_course->id);
        DiscussItChain::resetToOneGroupForCourse($new_course->id);

        $this->assertNotNull($this->_chainId($new_assignment_1));
        $this->assertEquals($this->_chainId($new_assignment_1), $this->_chainId($new_assignment_2));
        $this->assertNotEquals($this->_chainId($this->assignment_1), $this->_chainId($new_assignment_1));
        $this->assertNull($this->_chainId($new_assignment_3));
        foreach ([$new_assignment_1, $new_assignment_2, $new_assignment_3] as $new_assignment) {
            $this->assertEquals(1, DiscussItChain::numberOfGroups($new_assignment->id, $this->question->id));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Turning off new threads and replies
    |--------------------------------------------------------------------------
    */

    private function _setDiscussItSettings(Assignment $assignment, array $settings): void
    {
        $discuss_it_settings = json_decode(DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->value('discuss_it_settings'), true);
        DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['discuss_it_settings' => json_encode(array_merge($discuss_it_settings, $settings))]);
    }

    /** @test */
    public function new_threads_and_replies_are_allowed_when_the_settings_are_missing()
    {
        //settings saved before the options existed
        $this->assertNull(Helper::discussItStudentCommentBlockedMessage(['students_can_edit_comments' => '1'], true));
        $this->assertNull(Helper::discussItStudentCommentBlockedMessage(['students_can_edit_comments' => '1'], false));
        $this->assertNull(Helper::discussItStudentCommentBlockedMessage(null, true));
    }

    /** @test */
    public function students_are_blocked_only_from_what_was_turned_off()
    {
        $no_new_threads = ['students_can_start_threads' => '0', 'students_can_reply_to_threads' => '1'];
        $this->assertEquals('Your instructor has turned off starting new threads for this question in this assignment.',
            Helper::discussItStudentCommentBlockedMessage($no_new_threads, true));
        $this->assertNull(Helper::discussItStudentCommentBlockedMessage($no_new_threads, false));

        $no_replies = (object)['students_can_start_threads' => '1', 'students_can_reply_to_threads' => '0'];
        $this->assertNull(Helper::discussItStudentCommentBlockedMessage($no_replies, true));
        $this->assertEquals('Your instructor has turned off replying to threads for this question in this assignment.',
            Helper::discussItStudentCommentBlockedMessage($no_replies, false));
    }

    /** @test */
    public function turning_off_new_threads_while_threads_must_be_initiated_is_a_participation_warning()
    {
        $conflicts = Helper::discussItParticipationConflicts(['completion_criteria' => '1',
            'students_can_start_threads' => '0',
            'students_can_reply_to_threads' => '1',
            'min_number_of_initiated_discussion_threads' => '2',
            'min_number_of_replies' => '1',
            'min_number_of_initiate_or_reply_in_threads' => '1']);
        $this->assertEquals(['students_can_start_threads'], array_keys($conflicts));
    }

    /** @test */
    public function turning_off_replies_while_replies_are_required_is_a_participation_warning()
    {
        $conflicts = Helper::discussItParticipationConflicts(['completion_criteria' => '1',
            'students_can_start_threads' => '1',
            'students_can_reply_to_threads' => '0',
            'min_number_of_initiated_discussion_threads' => '0',
            'min_number_of_replies' => '1']);
        $this->assertEquals(['students_can_reply_to_threads'], array_keys($conflicts));
    }

    /** @test */
    public function participating_in_threads_can_still_be_met_by_whichever_is_allowed()
    {
        foreach (['students_can_start_threads', 'students_can_reply_to_threads'] as $turned_off) {
            $settings = ['completion_criteria' => '1',
                'students_can_start_threads' => '1',
                'students_can_reply_to_threads' => '1',
                'min_number_of_initiated_discussion_threads' => '0',
                'min_number_of_replies' => '0',
                'min_number_of_initiate_or_reply_in_threads' => '3'];
            $settings[$turned_off] = '0';
            $this->assertEquals([], Helper::discussItParticipationConflicts($settings));
        }
    }

    /** @test */
    public function turning_off_both_while_any_participation_is_required_is_a_participation_warning()
    {
        $settings = ['completion_criteria' => '1',
            'students_can_start_threads' => '0',
            'students_can_reply_to_threads' => '0',
            'min_number_of_initiated_discussion_threads' => '0',
            'min_number_of_replies' => '0',
            'min_number_of_initiate_or_reply_in_threads' => '0',
            'min_number_of_comments' => '1'];
        $this->assertEquals(['students_can_reply_to_threads'], array_keys(Helper::discussItParticipationConflicts($settings)));

        $settings['min_number_of_comments'] = '0';
        $this->assertEquals([], Helper::discussItParticipationConflicts($settings));
    }

    /** @test */
    public function completion_criteria_that_are_off_give_no_participation_warnings()
    {
        $this->assertEquals([], Helper::discussItParticipationConflicts(['completion_criteria' => '0',
            'students_can_start_threads' => '0',
            'students_can_reply_to_threads' => '0',
            'min_number_of_initiated_discussion_threads' => '2',
            'min_number_of_replies' => '2']));
    }

    /** @test */
    public function instructors_can_still_start_threads_and_reply_when_students_cannot()
    {
        $this->_setDiscussItSettings($this->assignment_1, ['students_can_start_threads' => '0',
            'students_can_reply_to_threads' => '0']);
        $base_url = "/api/discussions/assignment/{$this->assignment_1->id}/question/{$this->question->id}/{$this->questionMediaUpload->id}";
        $this->actingAs($this->user)
            ->postJson("$base_url/0/1", ['type' => 'text', 'text' => 'a new thread from the instructor'])
            ->assertJson(['type' => 'success']);
        $discussion_id = DB::table('discussions')->where('assignment_id', $this->assignment_1->id)->value('id');
        $this->actingAs($this->user)
            ->postJson("$base_url/$discussion_id/1", ['type' => 'text', 'text' => 'a reply from the instructor'])
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function new_discuss_it_questions_allow_new_threads_and_replies_by_default()
    {
        $default_settings = json_decode(Helper::defaultDiscussItSettings(), true);
        $this->assertEquals('1', $default_settings['students_can_start_threads']);
        $this->assertEquals('1', $default_settings['students_can_reply_to_threads']);
    }

    private function _saveDiscussItSettings(Assignment $assignment, array $settings)
    {
        return $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$assignment->id}/question/{$this->question->id}/discuss-it-settings",
                array_merge([
                    'response_modes' => ['text'],
                    'number_of_groups' => 1,
                    'students_can_edit_comments' => '1',
                    'students_can_delete_comments' => '0',
                    'students_can_start_threads' => '1',
                    'students_can_reply_to_threads' => '1',
                    'min_number_of_initiated_discussion_threads' => '1',
                    'min_number_of_replies' => '0',
                    'min_number_of_initiate_or_reply_in_threads' => '0',
                    'min_number_of_words' => '1',
                    'auto_grade' => '0',
                    'completion_criteria' => '1'], $settings));
    }

    /** @test */
    public function closing_new_threads_while_they_are_required_asks_for_confirmation_before_saving()
    {
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_start_threads' => '0',
            'min_number_of_words' => '9'])
            ->assertJson(['type' => 'confirm',
                'participation_warnings' => ["Students can't start new threads, but the completion criteria require them to start 1 thread."]]);
        $this->assertNotEquals('9', $this->_storedSettings($this->assignment_1)['min_number_of_words']);
    }

    /** @test */
    public function closing_new_threads_while_they_are_required_saves_once_confirmed()
    {
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_start_threads' => '0',
            'confirm_participation_warnings' => true])
            ->assertJson(['type' => 'success']);
        $settings = $this->_storedSettings($this->assignment_1);
        $this->assertEquals('0', $settings['students_can_start_threads']);
        $this->assertArrayNotHasKey('confirm_participation_warnings', $settings);
    }

    /** @test */
    public function closing_replies_while_they_are_required_asks_for_confirmation_before_saving()
    {
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0',
            'min_number_of_replies' => '1'])
            ->assertJson(['type' => 'confirm',
                'participation_warnings' => ["Students can't reply to threads, but the completion criteria require them to reply 1 time."]]);
        $this->assertEquals('1', $this->_storedSettings($this->assignment_1)['students_can_reply_to_threads'] ?? '1');
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0',
            'min_number_of_replies' => '1',
            'confirm_participation_warnings' => true])
            ->assertJson(['type' => 'success']);
        $this->assertEquals('0', $this->_storedSettings($this->assignment_1)['students_can_reply_to_threads']);
    }

    /** @test */
    public function settings_students_can_meet_save_without_confirmation()
    {
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0',
            'min_number_of_replies' => '0'])
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function saving_settings_stores_whether_students_can_start_threads_and_reply()
    {
        $this->_saveDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0'])
            ->assertJson(['type' => 'success']);
        $discuss_it_settings = json_decode(DB::table('assignment_question')
            ->where('assignment_id', $this->assignment_1->id)
            ->where('question_id', $this->question->id)
            ->value('discuss_it_settings'), true);
        $this->assertEquals('1', $discuss_it_settings['students_can_start_threads']);
        $this->assertEquals('0', $discuss_it_settings['students_can_reply_to_threads']);
    }

    /** @test */
    public function older_clients_that_do_not_send_the_new_settings_leave_them_allowed()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment_1->id}/question/{$this->question->id}/discuss-it-settings", [
                'response_modes' => ['text'],
                'number_of_groups' => 1,
                'students_can_edit_comments' => '1',
                'students_can_delete_comments' => '0',
                'min_number_of_initiated_discussion_threads' => '1',
                'min_number_of_replies' => '0',
                'min_number_of_initiate_or_reply_in_threads' => '0',
                'min_number_of_words' => '1',
                'auto_grade' => '0',
                'completion_criteria' => '1'])
            ->assertJson(['type' => 'success']);
        $discuss_it_settings = json_decode(DB::table('assignment_question')
            ->where('assignment_id', $this->assignment_1->id)
            ->where('question_id', $this->question->id)
            ->value('discuss_it_settings'), true);
        $this->assertEquals('1', $discuss_it_settings['students_can_start_threads']);
        $this->assertEquals('1', $discuss_it_settings['students_can_reply_to_threads']);
    }

    /**
     * Formative assignments pass the general submission policy without assign-to timings,
     * so the student reaches the new-thread/reply checks.
     */
    private function _makeFormative(Assignment $assignment): void
    {
        DB::table('assignments')->where('id', $assignment->id)->update(['formative' => 1]);
    }

    private function _studentPost(Assignment $assignment, int $discussion_id, string $text)
    {
        return $this->actingAs($this->student_user)
            ->postJson("/api/discussions/assignment/{$assignment->id}/question/{$this->question->id}/{$this->questionMediaUpload->id}/$discussion_id/0",
                ['type' => 'text', 'text' => $text]);
    }

    /** @test */
    public function student_cannot_start_a_thread_when_new_threads_are_turned_off_but_can_still_reply()
    {
        $this->_makeFormative($this->assignment_1);
        $this->_setDiscussItSettings($this->assignment_1, ['students_can_start_threads' => '0']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);

        $this->_studentPost($this->assignment_1, 0, 'a new thread')
            ->assertJson(['type' => 'error',
                'message' => 'Your instructor has turned off starting new threads for this question in this assignment.']);
        $this->assertEquals(1, DB::table('discussions')->where('assignment_id', $this->assignment_1->id)->count());

        $this->_studentPost($this->assignment_1, $discussion_id, 'a reply')
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('discussion_comments', [
            'discussion_id' => $discussion_id,
            'user_id' => $this->student_user->id,
            'posted_in_assignment_id' => $this->assignment_1->id]);
    }

    /** @test */
    public function student_cannot_reply_when_replies_are_turned_off_but_can_still_start_a_thread()
    {
        $this->_makeFormative($this->assignment_1);
        $this->_setDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);

        $this->_studentPost($this->assignment_1, $discussion_id, 'a reply')
            ->assertJson(['type' => 'error',
                'message' => 'Your instructor has turned off replying to threads for this question in this assignment.']);
        $this->assertDatabaseMissing('discussion_comments', ['user_id' => $this->student_user->id]);

        $this->_studentPost($this->assignment_1, 0, 'a new thread')
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('discussions', [
            'assignment_id' => $this->assignment_1->id,
            'user_id' => $this->student_user->id]);
    }

    /** @test */
    public function turning_off_replies_in_one_linked_assignment_does_not_affect_the_others()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_makeFormative($this->assignment_1);
        $this->_makeFormative($this->assignment_2);
        $this->_setDiscussItSettings($this->assignment_1, ['students_can_reply_to_threads' => '0']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->user);
        $this->_comment($discussion_id, $this->assignment_1, $this->user);

        $this->_studentPost($this->assignment_1, $discussion_id, 'a reply in the closed assignment')
            ->assertJson(['type' => 'error']);
        //the same thread, from the linked assignment where replies are still open
        $this->_studentPost($this->assignment_2, $discussion_id, 'a reply in the open assignment')
            ->assertJson(['type' => 'success']);
        $this->assertDatabaseHas('discussion_comments', [
            'discussion_id' => $discussion_id,
            'user_id' => $this->student_user->id,
            'posted_in_assignment_id' => $this->assignment_2->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Copying and applying settings across linked assignments
    |--------------------------------------------------------------------------
    */

    private function _storedSettings(Assignment $assignment): array
    {
        return json_decode(DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $this->question->id)
            ->value('discuss_it_settings'), true);
    }

    /** @test */
    public function settings_can_be_applied_to_linked_assignments()
    {
        $this->_link($this->assignment_1, [$this->assignment_2, $this->assignment_3])->assertJson(['type' => 'success']);
        $this->_saveDiscussItSettings($this->assignment_1, ['min_number_of_words' => '7',
            'apply_to_assignment_ids' => [$this->assignment_2->id, $this->assignment_3->id]])
            ->assertJson(['type' => 'success']);
        foreach ([$this->assignment_1, $this->assignment_2, $this->assignment_3] as $assignment) {
            $settings = $this->_storedSettings($assignment);
            $this->assertEquals('7', $settings['min_number_of_words']);
            $this->assertArrayNotHasKey('apply_to_assignment_ids', $settings);
        }
    }

    /** @test */
    public function applying_settings_keeps_each_assignments_new_thread_and_reply_options()
    {
        $this->_link($this->assignment_1, [$this->assignment_2])->assertJson(['type' => 'success']);
        $this->_setDiscussItSettings($this->assignment_2, ['students_can_start_threads' => '1',
            'students_can_reply_to_threads' => '0',
            'min_number_of_replies' => '0']);
        $this->_saveDiscussItSettings($this->assignment_1, ['apply_to_assignment_ids' => [$this->assignment_2->id]])
            ->assertJson(['type' => 'success']);
        $this->assertEquals('0', $this->_storedSettings($this->assignment_2)['students_can_reply_to_threads']);
    }

    /** @test */
    public function applying_settings_students_could_not_meet_in_a_linked_assignment_asks_for_confirmation()
    {
        $this->_link($this->assignment_1, [$this->assignment_2])->assertJson(['type' => 'success']);
        //assignment 2 has replies turned off (closed); assignment 1 is about to require a reply
        $this->_setDiscussItSettings($this->assignment_2, ['students_can_reply_to_threads' => '0',
            'min_number_of_replies' => '0']);
        $settings = ['min_number_of_replies' => '1',
            'min_number_of_initiated_discussion_threads' => '1',
            'min_number_of_words' => '9',
            'apply_to_assignment_ids' => [$this->assignment_2->id]];
        $this->_saveDiscussItSettings($this->assignment_1, $settings)
            ->assertJson(['type' => 'confirm',
                'participation_warnings' => ["In {$this->assignment_2->name}: Students can't reply to threads, but the completion criteria require them to reply 1 time."]]);
        $this->assertNotEquals('9', $this->_storedSettings($this->assignment_1)['min_number_of_words']);
        $this->assertNotEquals('9', $this->_storedSettings($this->assignment_2)['min_number_of_words']);

        $this->_saveDiscussItSettings($this->assignment_1, array_merge($settings, ['confirm_participation_warnings' => true]))
            ->assertJson(['type' => 'success']);
        $this->assertEquals('9', $this->_storedSettings($this->assignment_1)['min_number_of_words']);
        $this->assertEquals('9', $this->_storedSettings($this->assignment_2)['min_number_of_words']);
        //assignment 2 stays closed to replies
        $this->assertEquals('0', $this->_storedSettings($this->assignment_2)['students_can_reply_to_threads']);
    }

    /** @test */
    public function settings_cannot_be_applied_where_real_students_have_commented_and_nothing_is_saved()
    {
        $this->_link($this->assignment_1, [$this->assignment_2, $this->assignment_3])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_3, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_3, $this->student_user);
        $this->_saveDiscussItSettings($this->assignment_1, ['min_number_of_words' => '9',
            'apply_to_assignment_ids' => [$this->assignment_2->id, $this->assignment_3->id]])
            ->assertJson(['type' => 'error']);
        foreach ([$this->assignment_1, $this->assignment_2, $this->assignment_3] as $assignment) {
            $this->assertNotEquals('9', $this->_storedSettings($assignment)['min_number_of_words']);
        }
    }

    /** @test */
    public function comments_in_the_assignment_being_saved_do_not_block_applying_to_the_others()
    {
        $this->_link($this->assignment_1, [$this->assignment_2])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        $this->_saveDiscussItSettings($this->assignment_1, ['min_number_of_words' => '9',
            'apply_to_assignment_ids' => [$this->assignment_2->id]])
            ->assertJson(['type' => 'success']);
        $this->assertEquals('9', $this->_storedSettings($this->assignment_2)['min_number_of_words']);
    }

    /** @test */
    public function fake_student_comments_do_not_block_applying_settings()
    {
        $this->_link($this->assignment_1, [$this->assignment_2])->assertJson(['type' => 'success']);
        $fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $fake_student);
        $this->_comment($discussion_id, $this->assignment_2, $fake_student);
        $this->_saveDiscussItSettings($this->assignment_1, ['min_number_of_words' => '9',
            'apply_to_assignment_ids' => [$this->assignment_2->id]])
            ->assertJson(['type' => 'success']);
        $this->assertEquals('9', $this->_storedSettings($this->assignment_2)['min_number_of_words']);
    }

    /** @test */
    public function settings_cannot_be_applied_to_assignments_that_are_not_linked()
    {
        $this->_link($this->assignment_1, [$this->assignment_2])->assertJson(['type' => 'success']);
        $this->_saveDiscussItSettings($this->assignment_1, ['min_number_of_words' => '9',
            'apply_to_assignment_ids' => [$this->assignment_3->id]])
            ->assertJson(['type' => 'error',
                'message' => 'The settings can only be applied to assignments where this question is linked.']);
        $this->assertNotEquals('9', $this->_storedSettings($this->assignment_3)['min_number_of_words']);
    }

    /** @test */
    public function the_settings_modal_gets_each_linked_assignments_settings_and_whether_students_commented()
    {
        $this->_link($this->assignment_1, [$this->assignment_2, $this->assignment_3])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_3, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_3, $this->student_user);
        $linked = $this->actingAs($this->user)
            ->getJson("/api/assignments/{$this->assignment_1->id}/question/{$this->question->id}/discuss-it-settings")
            ->assertJson(['type' => 'success'])
            ->json('linked_discuss_it_settings');
        $has_real_student_comments_by_id = [];
        foreach ($linked as $item) {
            $this->assertIsArray($item['discuss_it_settings']);
            $has_real_student_comments_by_id[$item['id']] = $item['has_real_student_comments'];
        }
        $this->assertEquals([$this->assignment_2->id => false, $this->assignment_3->id => true], $has_real_student_comments_by_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Deleting an assignment with linked Discuss-it comments
    |--------------------------------------------------------------------------
    */

    private function _deleteCheck(Assignment $assignment)
    {
        return $this->actingAs($this->user)
            ->getJson("/api/assignments/{$assignment->id}/linked-discuss-it-delete-check");
    }

    private function _deleteAssignment(Assignment $assignment, int $confirmed_number_of_comments = 0)
    {
        return $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$assignment->id}?confirmed_number_of_linked_discuss_it_comments=$confirmed_number_of_comments");
    }

    /**
     * One new thread earns completion credit (a Discuss-it score) in this assignment; or one reply, if $by_replying.
     */
    private function _scoreOnOneComment(Assignment $assignment, bool $by_replying = false): void
    {
        $this->_makeFormative($assignment);
        $this->_setDiscussItSettings($assignment, ['auto_grade' => '1',
            'completion_criteria' => '1',
            'min_number_of_initiated_discussion_threads' => $by_replying ? '0' : '1',
            'min_number_of_replies' => $by_replying ? '1' : '0',
            'min_number_of_initiate_or_reply_in_threads' => '0',
            'min_number_of_comments' => '0',
            'min_number_of_words' => '1',
            'min_length_of_audio_video' => '',
            'students_can_start_threads' => '1',
            'students_can_reply_to_threads' => '1']);
    }

    private function _assertScored(Assignment $assignment, User $user): void
    {
        $this->assertDatabaseHas('submission_files', ['assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $user->id]);
    }

    /** @test */
    public function delete_check_counts_only_the_comments_that_would_be_removed()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $other_student = factory(User::class)->create(['role' => 3]);
        //a thread started in assignment 2, with a reply made in assignment 1: both removed
        $removed_discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($removed_discussion_id, $this->assignment_2, $this->student_user);
        $this->_comment($removed_discussion_id, $this->assignment_1, $other_student);
        //a thread started and commented on in assignment 1: kept
        $kept_discussion_id = $this->_startDiscussion($this->assignment_1, $other_student);
        $this->_comment($kept_discussion_id, $this->assignment_1, $other_student);

        $check = $this->_deleteCheck($this->assignment_2)
            ->assertJson(['type' => 'success',
                'number_of_comments' => 2,
                'number_of_students' => 2,
                'can_delete' => true])
            ->json();
        $this->assertEqualsCanonicalizing([$this->assignment_1->name, $this->assignment_2->name], $check['assignment_names']);
    }

    /** @test */
    public function deleting_without_confirming_the_removed_comments_is_refused()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        $this->_deleteAssignment($this->assignment_2)
            ->assertJson(['type' => 'error']);
        $this->assertDatabaseHas('assignments', ['id' => $this->assignment_2->id]);
        $this->assertDatabaseHas('discussion_comments', ['discussion_id' => $discussion_id]);
    }

    /** @test */
    public function confirmed_deletion_removes_the_comments_and_returns_the_students_emails()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $removed_discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($removed_discussion_id, $this->assignment_2, $this->student_user);
        $this->_comment($removed_discussion_id, $this->assignment_1, $this->student_user);
        $kept_discussion_id = $this->_startDiscussion($this->assignment_1, $this->student_user);
        $this->_comment($kept_discussion_id, $this->assignment_1, $this->student_user);

        $this->_deleteAssignment($this->assignment_2, 2)
            ->assertJson(['type' => 'success',
                'student_emails_associated_with_removed_comments' => [$this->student_user->email]]);
        $this->assertDatabaseMissing('discussion_comments', ['discussion_id' => $removed_discussion_id]);
        $this->assertDatabaseMissing('discussions', ['id' => $removed_discussion_id]);
        $this->assertDatabaseHas('discussion_comments', ['discussion_id' => $kept_discussion_id]);
    }

    /** @test */
    public function deletion_is_refused_if_students_commented_after_the_instructor_confirmed()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->student_user);
        $this->_comment($discussion_id, $this->assignment_2, $this->student_user);
        $this->_deleteCheck($this->assignment_2)->assertJson(['number_of_comments' => 1]);
        //another comment arrives before the instructor clicks delete
        $this->_comment($discussion_id, $this->assignment_1, $this->student_user);
        $this->_deleteAssignment($this->assignment_2, 1)
            ->assertJson(['type' => 'error',
                'message' => "Student comments on this assignment's linked Discuss-it questions have changed since you opened this window, so nothing was deleted.  Please try again."]);
        $this->assertDatabaseHas('assignments', ['id' => $this->assignment_2->id]);
    }

    /** @test */
    public function cannot_delete_once_comments_that_would_be_removed_have_been_scored()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_scoreOnOneComment($this->assignment_2);
        $this->_studentPost($this->assignment_2, 0, 'a new thread that earns completion credit')
            ->assertJson(['type' => 'success']);
        $this->_assertScored($this->assignment_2, $this->student_user);

        $message = "You cannot delete this assignment.  Deleting it would remove student comments on its linked Discuss-it questions, and some of those comments have already been scored in: {$this->assignment_2->name}.";
        $this->_deleteCheck($this->assignment_2)
            ->assertJson(['type' => 'success', 'can_delete' => false, 'cannot_delete_message' => $message]);
        //even when the instructor confirms the comments
        $this->_deleteAssignment($this->assignment_2, 1)
            ->assertJson(['type' => 'error', 'message' => $message]);
        $this->assertDatabaseHas('assignments', ['id' => $this->assignment_2->id]);
    }

    /** @test */
    public function a_scored_reply_in_another_assignment_to_a_thread_started_here_blocks_deletion()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $this->_makeFormative($this->assignment_2);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $this->user);
        $this->_comment($discussion_id, $this->assignment_2, $this->user);
        //the reply is made (and scored) in assignment 1, on a thread started in assignment 2
        $this->_scoreOnOneComment($this->assignment_1, true);
        $this->_studentPost($this->assignment_1, $discussion_id, 'a reply that earns completion credit')
            ->assertJson(['type' => 'success']);
        $this->_assertScored($this->assignment_1, $this->student_user);

        $this->_deleteCheck($this->assignment_2)
            ->assertJson(['can_delete' => false,
                'cannot_delete_message' => "You cannot delete this assignment.  Deleting it would remove student comments on its linked Discuss-it questions, and some of those comments have already been scored in: {$this->assignment_1->name}."]);
    }

    /** @test */
    public function scores_that_do_not_depend_on_removed_comments_do_not_block_deletion()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        //scored in assignment 1 on a thread started in assignment 1: unaffected by deleting assignment 2
        $this->_scoreOnOneComment($this->assignment_1);
        $this->_studentPost($this->assignment_1, 0, 'a new thread that earns completion credit')
            ->assertJson(['type' => 'success']);
        $this->_assertScored($this->assignment_1, $this->student_user);

        $this->_deleteCheck($this->assignment_2)
            ->assertJson(['number_of_comments' => 0, 'can_delete' => true]);
        $this->_deleteAssignment($this->assignment_2)
            ->assertJson(['type' => 'success']);
        $this->_assertScored($this->assignment_1, $this->student_user);
    }

    /** @test */
    public function fake_student_comments_do_not_need_confirming()
    {
        $this->_link($this->assignment_2, [$this->assignment_1])->assertJson(['type' => 'success']);
        $fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        $discussion_id = $this->_startDiscussion($this->assignment_2, $fake_student);
        $this->_comment($discussion_id, $this->assignment_2, $fake_student);
        $this->_deleteCheck($this->assignment_2)
            ->assertJson(['number_of_comments' => 0, 'can_delete' => true]);
        $this->_deleteAssignment($this->assignment_2)
            ->assertJson(['type' => 'success']);
    }

    /** @test */
    public function non_owner_cannot_see_the_delete_check()
    {
        $this->actingAs(factory(User::class)->create(['role' => 2]))
            ->getJson("/api/assignments/{$this->assignment_2->id}/linked-discuss-it-delete-check")
            ->assertJson(['type' => 'error']);
    }
}
