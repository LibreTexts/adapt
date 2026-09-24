<?php

namespace App\Policies;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Discussion;
use App\DiscussionComment;
use App\DiscussItChain;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use App\Traits\GeneralSubmissionPolicy;

class DiscussionCommentPolicy
{
    use HandlesAuthorization;
    use GeneralSubmissionPolicy;


    /**
     * @param $discussionComment
     * @param $user
     * @param $assignment_id
     * @param $question_id
     * @param $action
     * @return array
     */
    private function _hasAccess($discussionComment, $user, $assignment_id, $question_id, $action, int $viewing_assignment_id = 0): array
    {
        $message = "You are not allowed to $action this comment.";
        switch ($user->role) {
            case(3):
                $has_access = true;
                $general_submission_policy = $this->canSubmitBasedOnGeneralSubmissionPolicy($user, Assignment::find($assignment_id), $assignment_id, $question_id);
                if ($discussionComment->user_id !== $user->id) {
                    $has_access = false;
                } else if ($this->_madeInAnotherLinkedAssignment($assignment_id, $question_id, $viewing_assignment_id)) {
                    $has_access = false;
                    $message = $action
                        ? "This comment was made in another assignment, so you can only $action it from that assignment."
                        : "This comment was made in another assignment.";
                } else if ($action && !(new AssignmentSyncQuestion())->discussItSetting($assignment_id, $question_id, "students_can_{$action}_comments")) {
                    $has_access = false;
                    $message = "Your instructor's settings indicate you may not $action your comments.";
                } else if (DB::table('submission_files')
                    ->where('assignment_id', $assignment_id)
                    ->where('question_id', $question_id)
                    ->where('user_id', $user->id)
                    ->whereNotNull('grader_id')
                    ->first()) {
                    $has_access = false;
                    $message = "You cannot $action this comment since it was already graded.";
                } else if ($general_submission_policy['type'] === 'error') {
                    $has_access = false;
                    $message = $general_submission_policy['message'];
                }
                break;
            case(2):
                $discussion = Discussion::find($discussionComment->discussion_id);
                $assignment = Assignment::find($discussion->assignment_id);
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            default:
                $has_access = false;
        }
        return compact('has_access', 'message');
    }

    /**
     * With linked (daisy-chained) questions, students see comments from several assignments but may only
     * edit/delete the ones made in the assignment they're working in.
     *
     * @param int $posted_in_assignment_id
     * @param int $question_id
     * @param int $viewing_assignment_id
     * @return bool
     */
    private function _madeInAnotherLinkedAssignment(int $posted_in_assignment_id, int $question_id, int $viewing_assignment_id): bool
    {
        if ($viewing_assignment_id) {
            return $viewing_assignment_id !== $posted_in_assignment_id;
        }
        //older clients don't say which assignment they're in; only allow it when the question isn't linked
        return (bool)DiscussItChain::chainId($posted_in_assignment_id, $question_id);
    }

    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @return Response
     */
    public function downloadTranscript(User $user, DiscussionComment $discussionComment): Response
    {
        $discussion = Discussion::find($discussionComment->discussion_id);
        $assignment = Assignment::find($discussion->assignment_id);
        $course = $assignment->course;
        switch ($user->role) {
            case(3):
                $has_access = DB::table('enrollments')->where('course_id', $course->id)
                    ->where('user_id', $user->id)
                    ->first();
                break;
            case(2):
                $has_access = $course->ownsCourseOrIsCoInstructor($user->id);
                break;
            default:
                $has_access = false;
        }
        return $has_access
            ? Response::allow()
            : Response::deny("You are not allowed to download this transcript.");

    }


    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @return Response
     */
    public function reProcessTranscript(User $user, DiscussionComment $discussionComment): Response
    {
        $discussion = Discussion::find($discussionComment->discussion_id);
        $assignment = Assignment::find($discussion->assignment_id);
        $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
        return $has_access
            ? Response::allow()
            : Response::deny("You are not allowed to re-process this transcript.");

    }
    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @return Response
     */
    public function updateCaption(User $user, DiscussionComment $discussionComment): Response
    {
        $discussion = Discussion::find($discussionComment->discussion_id);
        $assignment = Assignment::find($discussion->assignment_id);
        $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
        return $has_access
            ? Response::allow()
            : Response::deny("You are not allowed to update this transcript.");

    }

    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @param int $assignment_id
     * @param int $question_id
     * @return Response
     */
    public function storeAudioVideoDiscussionComment(User $user, DiscussionComment $discussionComment, int $assignment_id, int $question_id): Response
    {
        $has_access = true;
        $message = "You may not store audio/video discussion comments.";
        $assignment = Assignment::find($assignment_id);
        switch ($user->role) {
            case(3):
                $general_submission_policy = $this->canSubmitBasedOnGeneralSubmissionPolicy($user, $assignment, $assignment_id, $question_id);
                if ($general_submission_policy['type'] === 'error') {
                    $has_access = false;
                    $message = $general_submission_policy['message'];
                }
                break;
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
                break;
            default:
                $has_access = false;
        }
        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }

    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @param int $assignment_id
     * @param int $question_id
     * @param int $viewing_assignment_id
     * @return Response
     */
    public function destroy(User $user, DiscussionComment $discussionComment, int $assignment_id, int $question_id, int $viewing_assignment_id = 0): Response
    {
        $has_access_info = $this->_hasAccess($discussionComment, $user, $assignment_id, $question_id, 'delete', $viewing_assignment_id);
        return $has_access_info['has_access']
            ? Response::allow()
            : Response::deny($has_access_info['message']);

    }

    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @param int $assignment_id
     * @param int $question_id
     * @param int $viewing_assignment_id
     * @return Response
     */
    public function deletingWillMakeRequirementsNotSatisfied(User $user, DiscussionComment $discussionComment, int $assignment_id, int $question_id, int $viewing_assignment_id = 0): Response
    {
        $has_access_info = $this->_hasAccess($discussionComment, $user, $assignment_id, $question_id, '', $viewing_assignment_id);
        return $has_access_info['has_access']
            ? Response::allow()
            : Response::deny("You are not allowed to check whether deleting this comment will make the requirements not satisfied.");

    }


    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @param int $assignment_id
     * @param int $question_id
     * @param int $viewing_assignment_id
     * @return Response
     */
    public function update(User $user, DiscussionComment $discussionComment, int $assignment_id, int $question_id, int $viewing_assignment_id = 0): Response
    {
        $has_access_info = $this->_hasAccess($discussionComment, $user, $assignment_id, $question_id, 'edit', $viewing_assignment_id);
        return $has_access_info['has_access']
            ? Response::allow()
            : Response::deny($has_access_info['message']);

    }

    /**
     * @param User $user
     * @param DiscussionComment $discussionComment
     * @param int $assignment_id
     * @param int $student_user_id
     * @return Response
     */
    public function getSatisfiedRequirements(User              $user,
                                             DiscussionComment $discussionComment,
                                             int               $assignment_id,
                                             int               $student_user_id): Response
    {
        $has_access = false;
        $assignment = Assignment::find($assignment_id);
        switch ($user->role) {
            case(3):
                $has_access = $user->id === $student_user_id;
                break;
            case(2):
                $has_access = $assignment->course->ownsCourseOrIsCoInstructor($user->id) || $assignment->course->public;
                break;
        }
        return $has_access
            ? Response::allow()
            : Response::deny('You are not allowed to view whether the requirements have been satisfied.');
    }
}
