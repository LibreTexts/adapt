<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Discussion;
use App\DiscussionComment;
use App\DiscussionGroup;
use App\Exceptions\Handler;
use App\Http\Requests\StoreDiscussionRequest;
use App\Jobs\InitConvertToMP4;
use App\Jobs\InitProcessTranscribe;
use App\Question;
use App\QuestionMediaUpload;
use App\Score;
use App\Submission;
use App\SubmissionFile;
use App\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DiscussionController extends Controller
{


    /**
     * @param StoreDiscussionRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param int $group
     * @param string $media_upload_id
     * @param int $discussion_id
     * @param Discussion $discussion
     * @param DiscussionGroup $discussionGroup
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Submission $submission
     * @param SubmissionFile $submissionFile
     * @param Score $score
     * @return array
     * @throws Exception
     */
    public function store(StoreDiscussionRequest $request,
                          Assignment             $assignment,
                          Question               $question,
                          string                 $media_upload_id,
                          int                    $discussion_id,
                          int                    $group,
                          Discussion             $discussion,
                          DiscussionGroup        $discussionGroup,
                          AssignmentSyncQuestion $assignmentSyncQuestion,
                          Submission             $submission,
                          SubmissionFile         $submissionFile,
                          Score                  $score
    ): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('store', [$discussion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $type = $request->type;
            $recording_type = $request->recording_type ? $request->recording_type : null;
            $pasted_comment = $request->pasted_comment ? 1 : 0;
            DB::beginTransaction();
            if (!$discussion_id) {
                if ($request->user()->role === 3) {
                    $group = $discussionGroup->store($assignment->id, $question->id, $request->user()->id);
                }
                $discussion = new Discussion();
                $discussion->assignment_id = $assignment->id;
                $discussion->question_id = $question->id;
                $discussion->user_id = $request->user()->id;
                $discussion->media_upload_id = $media_upload_id;
                $discussion->group = $group;
                $discussion->save();
            } else {
                $discussion = Discussion::find($discussion_id);
            }
            $discuss_it_settings = json_decode($assignmentSyncQuestion->discussItSettings($assignment->id, $question->id));
            $discussionComment = new DiscussionComment();
            $discussionComment->discussion_id = $discussion->id;
            $discussionComment->user_id = $request->user()->id;
            $discussionComment->{$type} = $data[$type];
            $discussionComment->pasted_comment = $pasted_comment;
            $discussionComment->recording_type = $recording_type;

            $satisfied_requirement = $discussionComment->satisfiedRequirement($type, $discuss_it_settings, $request);

            $discussionComment->satisfied_requirement = $satisfied_requirement;
            $discussionComment->save();
            if ($type === 'file') {
                InitProcessTranscribe::dispatch($data['file'], 'discussion_comment');
            }
            if ($recording_type === 'video' && str_contains($data['file'], '.webm')) {
                InitConvertToMP4::dispatch($discussionComment->id, $assignment->id);
            }
            $discussion_comment_submission_results = $discussionComment->satisfiedRequirements($assignment, $question->id, $request->user()->id, $assignmentSyncQuestion);
            $discussionComment->updateScore($discuss_it_settings,
                $discussion_comment_submission_results,
                $assignmentSyncQuestion,
                $request->user()->id,
                $assignment,
                $question,
                $submission,
                $submissionFile,
                $score);

            DB::commit();
            $response['type'] = 'success';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error creating this new discussion.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param string $media_upload_id
     * @param Discussion $discussion
     * @return array
     * @throws Exception
     */
    public
    function getByAssignmentQuestionMediaUploadId(
        Assignment $assignment,
        Question   $question,
        string     $media_upload_id,
        Discussion $discussion): array
    {
        try {
            $response['type'] = 'error';
            $page = 1;

            $discussions = $discussion->getByAssignmentQuestionMediaUploadId($assignment, $question, $media_upload_id)['discussions'];
            if (!in_array($assignment->id, [207943, 94618]) && $assignment->course->public && request()->user()->id !== $assignment->course->user_id) {
                $discussions = [];
            }
            $response['page'] = $page;
            $response['discussions'] = $discussions;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting your discussions.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param Discussion $discussion
     * @param User $user
     * @return array
     * @throws Exception
     */
    public
    function show(Assignment $assignment,
                  Question   $question,
                  Discussion $discussion,
                  User       $user): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('show', [$discussion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $enrolled_student_ids = $assignment->course->enrolledUsersWithFakeStudent->pluck('id')->toArray();
            $enrolled_students = $user->whereIn('id', $enrolled_student_ids)->get();
            $discussions = $discussion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->whereIn('user_id', $enrolled_students)
                ->get();
            //foreach ($enrolled_students )
            $response['discussions'] = $discussions;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the discussions.  Please try again or contact us for assistance.";
        }
        return $response;
    }

}
