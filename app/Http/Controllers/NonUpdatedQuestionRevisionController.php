<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Course;
use App\Exceptions\Handler;
use App\Jobs\ProcessUpdateAllQuestionRevisions;
use App\NonUpdatedQuestionRevision;
use App\PendingQuestionRevision;
use App\Question;
use App\QuestionRevision;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class NonUpdatedQuestionRevisionController extends Controller
{
    /**
     * @param Request $request
     * @param Course $course
     * @param NonUpdatedQuestionRevision $nonUpdatedQuestionRevision
     * @param QuestionRevision $questionRevision
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param PendingQuestionRevision $pendingQuestionRevision
     * @return array
     * @throws Exception
     */
    public function updateToLatestQuestionRevisionsByCourse(Request                    $request,
                                                            Course                     $course,
                                                            NonUpdatedQuestionRevision $nonUpdatedQuestionRevision,
                                                            QuestionRevision           $questionRevision,
                                                            AssignmentSyncQuestion     $assignmentSyncQuestion,
                                                            PendingQuestionRevision    $pendingQuestionRevision): array
    {
        $response['type'] = 'error';
        $changes_are_topical = $request->boolean('changes_are_topical');
        $authorized = Gate::inspect('updateToLatestQuestionRevisionsByCourse', [$nonUpdatedQuestionRevision, $course, $changes_are_topical]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            if (app()->environment() === 'testing') {
                return $nonUpdatedQuestionRevision->updateToLatestQuestionRevisionByCourse($course, $questionRevision, $assignmentSyncQuestion, $pendingQuestionRevision, $changes_are_topical);
            } else {
                ProcessUpdateAllQuestionRevisions::dispatch($course, $changes_are_topical);
                $response['type'] = 'info';
                $response['message'] = "Processing...please be patient.";
            }
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the question revisions for this course.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Course $course
     * @param NonUpdatedQuestionRevision $nonUpdatedQuestionRevision
     * @param QuestionRevision $questionRevision
     * @return array
     * @throws Exception
     */
    public function getNonUpdatedAssignmentQuestionsByCourse(Course                     $course,
                                                             NonUpdatedQuestionRevision $nonUpdatedQuestionRevision,
                                                             QuestionRevision           $questionRevision): array
    {


        $response['type'] = 'error';
        $authorized = Gate::inspect('getNonUpdatedQuestionRevisionsByCourse', [$nonUpdatedQuestionRevision, $course]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['non_updated_question_revisions'] = $nonUpdatedQuestionRevision->nonUpdatedAssignmentQuestionsByCourse($course, $questionRevision);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the non-updated assignment questions for this course.  Please try again or contact us for assistance.";
        }
        return $response;
    }
}
