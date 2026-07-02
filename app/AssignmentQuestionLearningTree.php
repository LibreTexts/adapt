<?php

namespace App;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignmentQuestionLearningTree extends Model
{
    /**
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     */
    public function addToAssignment(Assignment             $assignment,
                                    LearningTree           $learningTree,
                                    AssignmentSyncQuestion $assignmentSyncQuestion,
                                    BetaCourseApproval     $betaCourseApproval): array
    {
        $response['type'] = 'error';
        try {
            $question_id = $learningTree->root_node_question_id;
            $in_assignment = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question_id)
                ->get()
                ->isNotEmpty();
            if ($in_assignment) {
                $response['message'] = 'A Learning Tree with the same root node question already exists in the assignment.';
                return $response;
            }

            DB::table('assignment_question')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question_id,
                'order' => $assignmentSyncQuestion->getNewQuestionOrder($assignment),
                'points' => $assignment->points_per_question === 'number of points'
                    ? $assignment->default_points_per_question
                    : 0,
                'weight' => $assignment->points_per_question === 'number of points' ? null : 1,
                'open_ended_submission_type' => 0
            ]);
            $assignment_question_id = DB::getPdo()->lastInsertId();
            DB::table('assignment_question_learning_tree')->insert([
                'assignment_question_id' => $assignment_question_id,
                'learning_tree_id' => $learningTree->id,
                'number_of_successful_paths_for_a_reset' => $assignment->number_of_successful_paths_for_a_reset,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment);
            $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question_id, 'add', $learningTree->id);

            $response['type'] = 'success';
            $response['message'] = 'The Learning Tree has been added to the assignment.';
        } catch (Exception $e) {
            $response['message'] = "There was an error adding the Learning Tree to the assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function getAssignmentQuestionLearningTreeByRootNodeQuestionId(int $assignment_id, int $root_node_question_id)
    {
        return DB::table('assignment_question_learning_tree')
            ->join('assignment_question', 'assignment_question_learning_tree.assignment_question_id', '=', 'assignment_question.id')
            ->select('assignment_question_learning_tree.*')
            ->where('assignment_question.assignment_id', $assignment_id)
            ->where('assignment_question.question_id', $root_node_question_id)
            ->first();
    }

    /**
     * @throws Exception
     */
    public function getAssignmentQuestionLearningTreeByLearningTreeId(int $assignment_id, int $learning_tree_id)
    {
        $assignment_question_learning_tree = DB::table('assignment_question')
            ->join('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
            ->select('assignment_question_learning_tree.*')
            ->where('assignment_question.assignment_id', $assignment_id)
            ->where('assignment_question_learning_tree.learning_tree_id', $learning_tree_id)
            ->first();
        if (!$assignment_question_learning_tree) {
            throw new Exception ("Assignment question with assignment id $assignment_id and learning tree id $learning_tree_id does not exist.");
        }
        return $assignment_question_learning_tree;
    }
}
