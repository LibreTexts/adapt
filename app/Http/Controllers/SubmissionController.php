<?php

namespace App\Http\Controllers;

use App\AssignmentLevelOverride;
use App\AssignmentQuestionLearningTree;
use App\AssignmentSyncQuestion;
use App\DataShop;
use App\Discussion;
use App\DiscussionComment;
use App\Exceptions\Handler;
use App\Forge;
use App\Helpers\Helper;
use App\Http\Requests\UpdateScoresRequest;
use App\LearningTree;
use App\LearningTreeNodeSubmission;
use App\QuestionLevelOverride;
use App\QuestionRevision;
use App\SubmissionHistory;
use App\Webwork;
use Carbon\Carbon;
use Exception;
use App\Submission;
use App\Score;
use App\Assignment;
use App\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

use App\Http\Requests\StoreSubmission;
use App\Traits\GeneralSubmissionPolicy;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmissionController extends Controller
{
    use GeneralSubmissionPolicy;

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param Submission $submission
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public function deleteByAssignmentAndQuestion(Assignment             $assignment,
                                                  Question               $question,
                                                  Submission             $submission,
                                                  AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        /**
         * @param Request $request
         * @param Assignment $assignment
         * @param Question $question
         * @param Submission $submission
         * @return array
         * @throws Exception
         */
        try {

            $response['type'] = 'error';
            $authorized = Gate::inspect('deleteByAssignmentAndQuestion', [$submission, $assignment, $question->id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
                $response['message'] = "You cannot remove this question since there are already submissions and this assignment computes points using question weights.";
                return $response;
            }
            DB::beginTransaction();
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "All submissions to this question have been removed.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete the submissions to this question.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    /**
     * @param Request $request
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Submission $submission
     * @return array
     * @throws Exception
     */
    public function submissionExistsInCurrentCourseByOwnerAndQuestion(Request                $request,
                                                                      Question               $question,
                                                                      AssignmentSyncQuestion $assignmentSyncQuestion,
                                                                      Assignment             $assignment,
                                                                      Submission             $submission): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('submissionExistsInCurrentCourseByOwnerAndQuestion', $submission);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignment_ids_with_the_question = $assignmentSyncQuestion->getAssignmentIdsWithTheQuestion($question);
            //get courses with assignment_ids with the question ---- but not open


            $open_assignment_ids = $assignment->getOpenAssignmentIdsFromSubsetOfAssignmentIds($assignment_ids_with_the_question);
            $open_assignment_ids_in_owner_course = $assignment->openAssignmentIdsInOwnerCourse($request->user(), $open_assignment_ids);
            $response['submissions_exist'] = $this->_submissionsExistByAssignmentIds($open_assignment_ids_in_owner_course);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = $e->getMessage();
        }
        return $response;

    }

    /**
     * @param array $open_assignment_ids_in_owner_course
     * @return bool
     */
    private function _submissionsExistByAssignmentIds(array $open_assignment_ids_in_owner_course): bool
    {
        foreach (['submissions', 'submission_files'] as $table) {
            if (DB::table($table)
                ->join('users', "$table.user_id", '=', 'users.id')
                ->whereIn('assignment_id', $open_assignment_ids_in_owner_course)
                ->where('fake_student', 0)
                ->where('formative_student', 0)
                ->where('role', 3)
                ->exists()) {
                return true;
            }
            if (DB::table('discussions')
                ->join('discussion_comments', 'discussions.id', '=', 'discussion_comments.discussion_id')
                ->join('users', "discussion_comments.user_id", '=', 'users.id')
                ->whereIn('assignment_id', $open_assignment_ids_in_owner_course)
                ->where('fake_student', 0)
                ->where('formative_student', 0)
                ->where('role', 3)
                ->exists()) {
                return true;
            }
            return false;
        }


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param Submission $Submission
     * @return array
     * @throws Exception
     */
    public function submissionArray(Request    $request,
                                    Assignment $assignment,
                                    Question   $question,
                                    Submission $Submission): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('submissionArray', [$Submission, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $submission_history_id = $request->submission_history_id;
            $user_id = $request->user_id;
            $submission = $Submission->where('user_id', $user_id)
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            if (!$submission) {
                $response['type'] = 'success';
                $response['submission_array'] = [];
                return $response;
            }

            if ($submission_history_id) {
                $submission = SubmissionHistory::find($submission_history_id);
            }
            $submission_array = (new Submission())->getSubmissionArray($assignment, $question, $submission, false);
            $response['type'] = 'success';
            $response['submission_array'] = $submission_array;
            $response['show_correct_answer'] = isset($submission_array[0]) && isset($submission_array[0]['correct_ans']);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param int $is_forge
     * @param Submission $submission
     * @param AssignmentLevelOverride $assignmentLevelOverride
     * @param QuestionLevelOverride $questionLevelOverride
     * @param Forge $forge
     * @return array|void
     * @throws Exception
     */
    public function canSubmit(Request                 $request,
                              Assignment              $assignment,
                              Question                $question,
                              int                     $is_forge,
                              Submission              $submission,
                              AssignmentLevelOverride $assignmentLevelOverride,
                              QuestionLevelOverride   $questionLevelOverride,
                              Forge                   $forge)
    {
        try {
            $submission = $submission->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $request->user()->id)
                ->first();
            $user = $request->user();
            if (!$user->central_identity_id){
                $response = $user->updateCentralIdentityId();
                if ($response['type'] === 'error'){
                    $response['message'] = $response['message'];
                    return $response;
                }
            }
            if ($submission && $assignment->number_of_allowed_attempts !== 'unlimited'
                && (int)$submission->submission_count === (int)$assignment->number_of_allowed_attempts) {
                $response['type'] = 'error';
                $response['message'] = "You have submitted this question $submission->submission_count times and you are only allowed $assignment->number_of_allowed_attempts attempts so no additional submissions will be accepted.";
                return $response;
            }
            if ($questionLevelOverride->hasAutoGradedOverride($assignment->id, $question->id, $assignmentLevelOverride) ||
                $questionLevelOverride->hasOpenEndedOverride($assignment->id, $question->id, $assignmentLevelOverride)) {
                $response['type'] = 'success';
                return $response;
            }
            if (!$is_forge) {
                return $this->canSubmitBasedOnGeneralSubmissionPolicy($user, $assignment, $assignment->id, $question->id);
            } else {
                $assignment_question_forge_draft = DB::table('assignment_question')
                    ->join('assignment_question_forge_draft', 'assignment_question.id', '=', 'assignment_question_forge_draft.assignment_question_id')
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->first();
                if (!$assignment_question_forge_draft) {
                    $response['type'] = 'error';
                    $response['message'] = "No assignment question fore draft with assignment ID $assignment->id and question Id $question->id.";
                    return $response;
                }
                $forge_draft_id = $assignment_question_forge_draft->forge_draft_id;
                $central_identity_id = $user->fake_student || $user->formative_student ? (string)$user->id : $user->central_identity_id;
                $validation = $forge->validateForgeQuestionAccess($forge_draft_id, $central_identity_id);
                if ($validation['type'] === 'error') {
                    return $validation;
                }
                $result = $forge->getAssignToDataForForge($validation, $forge_draft_id, $central_identity_id, $forge);
                if ($result['type'] === 'error') {
                    return $result;
                }
                $response['type'] = $result['can_submit']['type'] === 'success' ? 'success' : 'error';
                if ($response['type'] === 'error') {
                    $response['message'] = $result['can_submit']['message'] ?? 'No responses will be saved since this Forge question cannot currently be submitted.';
                }
            }
            return $response;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }

    /**
     * @throws Throwable
     */
    public function updateScores(UpdateScoresRequest $request,
                                 Assignment          $assignment,
                                 Question            $question,
                                 Submission          $submission,
                                 Score               $score): array
    {
        return $score->handleUpdateScores($request, $assignment, $question, $submission);

    }


    /**
     * @param StoreSubmission $request
     * @param Assignment $Assignment
     * @param Score $score
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function store(StoreSubmission        $request,
                   Assignment             $Assignment,
                   Score                  $score,
                   AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        if ($request->is_learning_tree_node) {
            $learningTreeNodeSubmission = new LearningTreeNodeSubmission();
            $learning_tree = LearningTree::find($request->learning_tree_id);
            return $learningTreeNodeSubmission->store($request, new AssignmentQuestionLearningTree(), $learning_tree, new DataShop());
        } else {
            $Submission = new Submission();
            return $Submission->store($request,
                new Submission(),
                $Assignment,
                $score,
                new DataShop(),
                $assignmentSyncQuestion);

        }
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param Submission $submission
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function submissionChartData(Request                $request,
                                 Assignment             $assignment,
                                 Question               $question,
                                 Submission             $submission,
                                 AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $response['redirect_question'] = false;
        $response['pie_chart_data'] = [];
        $authorized = Gate::inspect('submissionChartData', [$submission, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $response_format = $request->response_format ? $request->response_format : '';
            $response = $submission->submissionChartData($assignment, $question, $response_format);
            $response['answer_shown'] = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)->first()->answer_shown;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error retrieving the submissions.  Please refresh the page and try again or contact us for assistance.";
        }

        return $response;
    }


    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param Submission $submission
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @return array
     * @throws Exception
     */
    public
    function resetSubmission(Request                        $request,
                             Assignment                     $assignment,
                             Question                       $question,
                             Submission                     $submission,
                             AssignmentQuestionLearningTree $assignmentQuestionLearningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('reset', [$submission, $assignment::find($assignment->id), $question->id]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $assignment_question_learning_tree = $assignmentQuestionLearningTree->getAssignmentQuestionLearningTreeByRootNodeQuestionId($assignment->id, $question->id);

        try {
            DB::beginTransaction();
            if ($assignment_question_learning_tree) {
                DB::table('learning_tree_node_submissions')
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->where('learning_tree_id', $assignment_question_learning_tree->learning_tree_id)
                    ->delete();
            }
            $tables = ['submissions',
                'h5p_activity_sets',
                'submission_files',
                'seeds',
                'can_give_ups',
                'shown_hints',
                'submission_histories',
                'submitted_works',
                'submitted_work_pending_scores'];
            foreach ($tables as $table) {
                DB::table($table)
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->delete();
            }
            if ($assignment->assessment_type === 'flashcard' && app()->environment('local')){
                DB::table('submissions')
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->delete();
            }
            $discussion_comments = DB::table('discussions')
                ->join('discussion_comments', 'discussion_comments.discussion_id', '=', 'discussions.id')
                ->where('discussion_comments.user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->select('discussion_comments.id')
                ->get();
            foreach ($discussion_comments as $discussion_comment) {
                DiscussionComment::find($discussion_comment->id)->delete();
            }
            $discussions = Discussion::where('user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->get();
            foreach ($discussions as $discussion) {
                if (!DB::table('discussion_comments')
                    ->where('discussion_id', $discussion->id)
                    ->first()) {
                    $discussion->delete();
                }
            }
            DB::commit();
            $response['message'] = $assignment->algorithmic
                ? "Resetting the submission and algorithmically generating a new question."
                : "Resetting the submission.";
            $response['type'] = 'info';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error resetting the submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }


}
