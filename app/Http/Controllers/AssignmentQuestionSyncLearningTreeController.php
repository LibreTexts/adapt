<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignmentSyncQuestion;
use App\BetaCourseApproval;
use App\Exceptions\Handler;
use App\LearningTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use \Exception;

class AssignmentQuestionSyncLearningTreeController extends Controller
{
    /**
     * @param Assignment $assignment
     * @param LearningTree $learningTree
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @return array
     * @throws Exception
     */
    public function store(Assignment                     $assignment,
                          LearningTree                   $learningTree,
                          AssignmentSyncQuestion         $assignmentSyncQuestion,
                          BetaCourseApproval             $betaCourseApproval,
                          AssignmentQuestionLearningTree $assignmentQuestionLearningTree): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('add', [$assignmentSyncQuestion, $assignment]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            DB::beginTransaction();
            $response = $assignmentQuestionLearningTree->addToAssignment(
                $assignment,
                $learningTree,
                $assignmentSyncQuestion,
                $betaCourseApproval
            );
            if ($response['type'] === 'error') {
                DB::rollback();
                return $response;
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error adding the Learning Tree to the assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }
}
