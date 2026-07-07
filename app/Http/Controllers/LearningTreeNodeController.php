<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\Exceptions\Handler;
use App\Http\Requests\UpdateNode;
use App\LearningTree;
use App\LearningTreeAnalytics;
use App\LearningTreeNode;
use App\LearningTreeNodeDescription;
use App\LearningTreeReset;
use App\Question;
use App\Score;
use App\Submission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LearningTreeNodeController extends Controller
{
    /**
     * @param UpdateNode $request
     * @param LearningTree $learningTree
     * @return array
     * @throws Exception
     */
    public function updateNode(UpdateNode   $request,
                               LearningTree $learningTree): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('updateNode', $learningTree);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';
        if ((int)($request->original_question_id) !== (int)$request->question_id) {
            $submission_exists = Submission::where('question_id', $learningTree->root_node_question_id)->exists();
            if ($submission_exists) {
                $response['message'] = "A submission exists for this Learning Tree so you cannot alter it.";
                return $response;
            }
        }
        try {
            $data = $request->validated();


            $validated_node = $this->validateLearningTreeNode($data['question_id']);
            $question = DB::table('questions')->where('id', $data['question_id'])->first();
            if (!$question) {
                $response['message'] = "No question exists with an ID of {$data['question_id']}.";
                return $response;
            }
            if ($validated_node['type'] === 'error') {
                $response['message'] = $validated_node['message'];
                return $response;
            }
            if ($validated_node['body'] === '') {
                $response['message'] = "Are you sure that's a valid page id?  We're not finding any content on that page.";
                return $response;
            }

            DB::beginTransaction();

            LearningTreeNodeDescription::updateOrCreate(
                ['learning_tree_id' => $learningTree->id,
                    'user_id' => $request->user()->id,
                    'question_id' => $request->question_id],
                ['title' => $request->title,
                    'notes' => $request->notes,
                    'description' => $request->node_description]
            );

            if (!$request->is_root_node) {
                $learning_tree_node_learning_outcome = DB::table('learning_tree_node_learning_outcome')
                    ->where('user_id', $request->user()->id)
                    ->where('learning_tree_id', $learningTree->id)
                    ->where('question_id', $question->id)
                    ->first();
                if ($request->learning_outcome) {
                    $learning_outcome = DB::table('learning_outcomes')
                        ->where('id', $request->learning_outcome)
                        ->first();
                    if (!$learning_outcome) {
                        throw new Exception ("$request->learning_outcome is not a valid learning outcome ID.");
                    }

                    if (!$learning_tree_node_learning_outcome) {
                        DB::table('learning_tree_node_learning_outcome')->insert([
                            'user_id' => $request->user()->id,
                            'learning_tree_id' => $learningTree->id,
                            'question_id' => $question->id,
                            'learning_outcome_id' => $learning_outcome->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } else {
                        DB::table('learning_tree_node_learning_outcome')
                            ->where('id', $learning_tree_node_learning_outcome->id)
                            ->update(['learning_outcome_id' => $learning_outcome->id, 'updated_at' => now()]);
                    }
                } else {
                    if ($learning_tree_node_learning_outcome) {
                        DB::table('learning_tree_node_learning_outcome')
                            ->where('id', $learning_tree_node_learning_outcome->id)
                            ->delete();
                    }

                }
            }

            $response['title'] = $request->title;
            $response['type'] = 'success';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the node: {$e->getMessage()}";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param LearningTreeNode $learningTreeNode
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @param LearningTreeReset $learningTreeReset
     * @return array
     * @throws Exception
     */
    public function resetRootNodeSubmission(Request                        $request,
                                            Assignment                     $assignment,
                                            Question                       $question,
                                            LearningTreeNode               $learningTreeNode,
                                            AssignmentQuestionLearningTree $assignmentQuestionLearningTree,
                                            LearningTreeReset              $learningTreeReset): array
    {
        try {
            $response['type'] = 'error';


            $authorized = Gate::inspect('resetRootNodeSubmission', [$learningTreeNode, $assignment, $assignment->id, $question->id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $can_reset_root_node_submission = $learningTreeReset->canResetRootNodeSubmission($assignment, $question, request()->user());
            if (!$can_reset_root_node_submission) {
                $response['type'] = 'info';
                $response['message'] = 'Since this assignment is past due, you cannot reset the original submission.';
                return $response;
            }

            $submission = Submission::where('user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $assignment_question_learning_tree = $assignmentQuestionLearningTree->getAssignmentQuestionLearningTreeByRootNodeQuestionId($assignment->id, $question->id);

            $learningTreeReset = LearningTreeReset::where('user_id', $request->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('learning_tree_id', $assignment_question_learning_tree->learning_tree_id)
                ->first();
            $number_resets_available = 0;
            DB::beginTransaction();
            if ($learningTreeReset) {
                $number_resets_available = max($learningTreeReset->number_resets_available - 1, 0);
                $learningTreeReset->number_resets_available = $number_resets_available;
                $learningTreeReset->save();
            }
            if ($submission) {
                $assignment_score = Score::where('assignment_id', $assignment->id)
                    ->where('user_id', $request->user()->id)
                    ->select('id', 'score')
                    ->first();
                if ($assignment_score) {
                    $assignment_score->score = $assignment_score->score - $submission->score;
                    $assignment_score->save();
                }
                $submission->delete();
                DB::table('seeds')
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->delete();
            }
            $assignment_course_info = $assignment->assignmentCourseInfo();
            LearningTreeAnalytics::create([
                'course_name' => $assignment_course_info->course_name,
                'assignment_name' => $assignment_course_info->assignment_name,
                'instructor' => $assignment_course_info->instructor,
                'user_id' => $request->user()->id,
                'learning_tree_id' => $assignment_question_learning_tree->learning_tree_id,
                'assignment_id' => $assignment->id,
                'question_id' => 0,
                'root_node' => 0,
                'action' => 'reset submission',
                'response' => ''
            ]);


            $response['type'] = 'info';
            $response['message'] = 'The submission has been reset and you may resubmit.';
            $response['number_resets_available'] = $number_resets_available;
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error removing the root node submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function getMetaInfo(Request      $request,
                                LearningTree $learning_tree,
                                int          $question_id): array
    {

        $response['type'] = 'error';
        $question = DB::table('questions')->where('id', $question_id)->first();
        if (!$question) {
            $response['message'] = "A question with question ID '$question_id' does not exist.";
            return $response;
        }
        try {
            $last_learning_outcome = DB::table('learning_tree_node_learning_outcome')
                ->join('learning_outcomes',
                    'learning_tree_node_learning_outcome.learning_outcome_id', '=', 'learning_outcomes.id')
                ->where('user_id', $request->user()->id)
                ->select('subject')
                ->orderBy('learning_tree_node_learning_outcome.id', 'desc')
                ->first();


            $learning_outcome = DB::table('learning_tree_node_learning_outcome')
                ->join('learning_outcomes',
                    'learning_tree_node_learning_outcome.learning_outcome_id', '=', 'learning_outcomes.id')
                ->where('user_id', $request->user()->id)
                ->where('learning_tree_id', $learning_tree->id)
                ->where('question_id', $question->id)
                ->first();

            //check to see if they have one

            $learning_tree_node_description = DB::table('learning_tree_node_descriptions')
                ->where('learning_tree_id', $learning_tree->id)
                ->where('question_id', $question->id)
                ->first();

            $response['description'] = $learning_tree_node_description && $learning_tree_node_description->description ? $learning_tree_node_description->description : 'None Available';
            $response['subject'] = $learning_outcome ? $learning_outcome->subject : ($last_learning_outcome ? $last_learning_outcome->subject : null);
            $response['learning_outcome'] = $learning_outcome ? ['id' => $learning_outcome->id, 'label' => $learning_outcome->description] : '';
            $response['title'] = $learning_tree_node_description && $learning_tree_node_description->title ? $learning_tree_node_description->title : $question->title;
            $response['notes'] = $learning_tree_node_description && $learning_tree_node_description->notes ? $learning_tree_node_description->notes : '';
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the node meta info.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param int $questionId
     * @return array
     * @throws Exception
     */
    public function validateLearningTreeNode(int $questionId): array
    {

        $response['type'] = 'error';
        try {
            $question = Question::where('id', $questionId)->first();
            if (!$question) {
                $response['message'] = "We were not able to validate this Learning Tree node.  Please double check your question id or contact us for assistance.";
                return $response;
            }
            $response['body'] = 'not sure what do to here';
            $response['title'] = $question->title;

            $response['type'] = 'success';
        } catch (Exception $e) {
            //some other error besides forbidden
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to validate this Learning Tree node.  Please double check your question id or contact us for assistance.";
            return $response;

        }
        return $response;
    }

}
