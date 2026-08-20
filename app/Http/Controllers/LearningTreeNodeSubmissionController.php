<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignmentSyncQuestion;
use App\Exceptions\Handler;
use App\JWE;
use App\LearningTree;
use App\LearningTreeAnalytics;
use App\LearningTreeNodeSeed;
use App\LearningTreeNodeSubmission;
use App\LearningTreeReset;
use App\Question;
use App\Submission;
use App\Traits\DateFormatter;
use App\Traits\IframeFormatter;
use App\Traits\Seed;
use DOMDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class LearningTreeNodeSubmissionController extends Controller
{
    use IframeFormatter;
    use DateFormatter;
    use Seed;

    /**
     * @param Request $request
     * @param LearningTreeNodeSubmission $learningTreeNodeSubmission
     * @param Submission $Submission
     * @param LearningTreeNodeSeed $learningTreeNodeSeed
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @return array
     * @throws Exception
     */
    public
    function show(Request                    $request,
                  LearningTreeNodeSubmission $learningTreeNodeSubmission,
                  Submission                 $Submission,
                  LearningTreeNodeSeed       $learningTreeNodeSeed,
                  AssignmentQuestionLearningTree $assignmentQuestionLearningTree): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('show', $learningTreeNodeSubmission);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $submission = $learningTreeNodeSubmission->submission;
            $learningTree = LearningTree::find($learningTreeNodeSubmission->learning_tree_id);
            $question = Question::find($learningTreeNodeSubmission->question_id);

            $assignment = Assignment::find($learningTreeNodeSubmission->assignment_id);

            // EK: lock this node back to whatever question_revision_id was
            // recorded in this assignment's tree snapshot, same as
            // LearningTreeNodeAssignmentQuestionController::show() - so a
            // previously-submitted node is re-displayed against the exact
            // revision the student actually answered, not whatever the
            // question looks like now.
            $assignment_question_learning_tree = null;
            try {
                $assignment_question_learning_tree = $assignmentQuestionLearningTree
                    ->getAssignmentQuestionLearningTreeByLearningTreeId($assignment->id, $learningTree->id);
            } catch (Exception $e) {
                //no matching row - nothing to lock to, so fall back to the live question below
            }
            $locked_question_revision_id = $assignmentQuestionLearningTree
                ->getLockedQuestionRevisionId($assignment_question_learning_tree, $question->id);
            if ($locked_question_revision_id) {
                $locked_question_revision = DB::table('question_revisions')->where('id', $locked_question_revision_id)->first();
                if ($locked_question_revision) {
                    $question = $question->updateWithQuestionRevision($locked_question_revision);
                }
            }

            $session_jwt = null;
            $decoded_submission = json_decode($submission, 1);
            if ($decoded_submission && isset($decoded_submission['sessionJWT'])) {
                $session_jwt = $decoded_submission['sessionJWT'];
            }

            $response_info = ['last_submitted' => $learningTreeNodeSubmission->updated_at,
                'submission_count' => $learningTreeNodeSubmission->submission_count,
                'session_jwt' => $session_jwt,
                'student_response' => $Submission->getStudentResponse($learningTreeNodeSubmission, $question->technology)];

            $seed = null;
            $qti_json = null;
            $qti_answer_json = null;
            $incorrectly_submitted_learning_tree_node_with_reseed_option = $submission
                && $assignment->reset_node_after_incorrect_attempt
                && !$learningTreeNodeSubmission->completed
                && (($question->technology === 'webwork' && str_contains((string)$question->webwork_code, 'random('))
                    || $question->technology === 'imathas');
            if (in_array($question->technology, ['webwork', 'imathas', 'qti'])) {
                if ($incorrectly_submitted_learning_tree_node_with_reseed_option) {
                    $seed = $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $question, true);
                    $learningTreeNodeSeed->where('user_id', $request->user()->id)
                        ->where('assignment_id', $assignment->id)
                        ->where('learning_tree_id', $learningTree->id)
                        ->where('question_id', $question->id)
                        ->update(['seed' => $seed]);
                    $session_jwt = null;

                } else {
                    $seed = $learningTreeNodeSeed->where('user_id', $request->user()->id)
                        ->where('assignment_id', $assignment->id)
                        ->where('learning_tree_id', $learningTree->id)
                        ->where('question_id', $question->id)
                        ->first()
                        ->seed;
                }
            }
            $submission_array = $Submission->getSubmissionArray($assignment, $question, $learningTreeNodeSubmission, true);
            if ($incorrectly_submitted_learning_tree_node_with_reseed_option) {
                $submission_array = [];
            }
            switch ($question->technology) {
                case('webwork'):
                    $extra_custom_claims['is_learning_tree_node'] = true;
                    $extra_custom_claims['learning_tree_id'] = $learningTreeNodeSubmission->learning_tree_id;
                    $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, true, new DOMDocument(), new JWE(), $extra_custom_claims);
                    $technology_iframe_src = $this->formatIframeSrc($question['technology_iframe'], rand(1, 1000), $technology_src_and_problemJWT['problemJWT'], $session_jwt);
                    break;
                case('imathas'):
                    $custom_claims = [];
                    $custom_claims['stuanswers'] = $Submission->getStudentResponse($learningTreeNodeSubmission, 'imathas');
                    $custom_claims['is_learning_tree_node'] = true;
                    $custom_claims['learning_tree_id'] = $learningTreeNodeSubmission->learning_tree_id;
                    $custom_claims['raw'] = json_decode($submission) ?
                        json_decode($learningTreeNodeSubmission->submission)->raw
                        : [];


                    $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, true, new DOMDocument(), new JWE(), $custom_claims);
                    $technology_iframe_src = $this->formatIframeSrc($question['technology_iframe'], rand(1, 1000), $technology_src_and_problemJWT['problemJWT'], $session_jwt);
                    break;
                case('h5p'):
                    $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, '', true, new DOMDocument(), new JWE());
                    $technology_iframe_src = $technology_src_and_problemJWT['technology_src'];
                    break;
                case('qti'):
                    $qti_json = $question->formatQtiJson('question_json', $question['qti_json'], $seed, true, $response_info['student_response']);

                    break;
                default:
                    throw new Exception("$question->technology is not yet handled in learning tree nodes");

            }

            $last_submitted = $response_info['last_submitted'] === 'N/A'
                ? 'N/A'
                : $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($response_info['last_submitted'],
                    $request->user()->time_zone, 'M d, Y g:i:s a');

            DB::beginTransaction();
            $earned_reset = false;
            $show_submission_message = $learningTreeNodeSubmission->show_submission_message;
            if ($learningTreeNodeSubmission->check_for_reset) {
                $learningTreeReset = LearningTreeReset::where('user_id', $learningTreeNodeSubmission->user_id)
                    ->where('assignment_id', $assignment->id)
                    ->where('learning_tree_id', $learningTreeNodeSubmission->learning_tree_id)
                    ->first();
                if (!$learningTreeReset) {
                    $learningTreeReset = new LearningTreeReset();
                    $learningTreeReset->user_id = $learningTreeNodeSubmission->user_id;
                    $learningTreeReset->assignment_id = $assignment->id;
                    $learningTreeReset->learning_tree_id = $learningTreeNodeSubmission->learning_tree_id;
                    $learningTreeReset->number_resets_available = 0;
                    $learningTreeReset->save();
                }
                $previously_completed_final_question_ids = LearningTreeNodeSubmission::where('user_id', $learningTreeNodeSubmission->user_id)
                    ->where('learning_tree_id', $learningTreeNodeSubmission->learning_tree_id)
                    ->where('assignment_id', $learningTreeNodeSubmission->id)
                    ->whereIn('question_id', $learningTree->finalQuestionIds())
                    ->where('proportion_correct', 1)
                    ->get()
                    ->pluck('question_id')
                    ->toArray();
                $assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                    ->where('question_id', $learningTree->root_node_question_id)
                    ->first();
                $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')->where('assignment_question_id', $assignment_question->id)
                    ->where('learning_tree_id', $learningTree->id)
                    ->first();

                $number_paths_completed = count($previously_completed_final_question_ids);
                $earned_reset = $number_paths_completed % $assignment_question_learning_tree->number_of_successful_paths_for_a_reset === 0;
                $number_resets_available = $earned_reset ? $learningTreeReset->number_resets_available + 1 : $learningTreeReset->number_resets_available;

                $learningTreeReset->number_resets_available = $number_resets_available;
                $learningTreeReset->save();
                $learningTreeNodeSubmission->check_for_reset = 0;
            }
            $learningTreeNodeSubmission->show_submission_message = 0;
            $learningTreeNodeSubmission->save();
            $message = $learningTreeNodeSubmission->completed ? "Your submission was correct. " : "Your submission was not correct.  ";
            $message .= $incorrectly_submitted_learning_tree_node_with_reseed_option ? 'You will be given a similar question to attempt.' : '';
            $message .= $earned_reset ? "You have earned a reset and can retry the root question for points." : '';

            $learning_tree_analytics_response = 'incorrect submission';
            if ($learningTreeNodeSubmission->completed) {
                $learning_tree_analytics_response = 'correct submission';
            }
            if ($earned_reset) {
                $learning_tree_analytics_response = 'earned reset';
            }
            if (!$request->user()->fake_student || app()->environment() === 'local') {
                $assignment_course_info = $assignment->assignmentCourseInfo();
                LearningTreeAnalytics::create([
                    'course_name'=>  $assignment_course_info->course_name,
                    'assignment_name'=>  $assignment_course_info->assignment_name,
                    'instructor'=>  $assignment_course_info->instructor,
                    'user_id' => $request->user()->id,
                    'learning_tree_id' => $learningTree->id,
                    'assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'root_node' => 0,
                    'action' => 'submit',
                    'response' => $learning_tree_analytics_response
                ]);
            }

            $response = [
                'message' => $message,
                'correct_submission' => $learningTreeNodeSubmission->completed,
                'earned_reset' => $earned_reset,
                'show_submission_message' => (bool)$show_submission_message,
                'completed' => $learningTreeNodeSubmission->completed,
                'learning_tree_node_submission_id' => $learningTreeNodeSubmission->id,
                'type' => 'success',
                'last_submitted' => $last_submitted,
                'student_response' => $response_info['student_response'],
                'submission_count' => $response_info['submission_count'],
                'session_jwt' => $response_info['session_jwt'],
                'qti_answer_json' => $qti_answer_json,
                'qti_json' => $qti_json,
                'technology_iframe_src' => $technology_iframe_src ?? null,
                'submission_array' => $submission_array,
            ];
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to update the the results from this learning node submission for viewing.  Please try again by refreshing the page or contact us for assistance.";
        }

        return $response;

    }
}
