<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\DataShop;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Http\Requests\StoreSubmission;
use App\Question;
use App\Score;
use App\Submission;
use App\UnconfirmedSubmission;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnconfirmedSubmissionController extends Controller
{
    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param UnconfirmedSubmission $unconfirmedSubmission
     * @return array
     * @throws Exception
     */
    public function show(Request               $request,
                         Assignment            $assignment,
                         Question              $question,
                         UnconfirmedSubmission $unconfirmedSubmission,
                         Submission            $submission): array
    {
        $response['type'] = 'error';
        try {
            $unconfirmed_submission = $unconfirmedSubmission->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $request->user()->id)
                ->first();
            if (!$unconfirmed_submission) {
                $response['message'] = "There is no existing submission to confirm.";
                return $response;
            }
            $formatted_unconfirmed_submission = [];
            $submission_info = json_decode($unconfirmed_submission->submission, 1);
            $has_html = false;
            if ($submission_info && isset($submission_info['submission'])
                && isset($submission_info['submission']['score'])
                && isset($submission_info['submission']['score']['answers'])) {
                foreach ($submission_info['submission']['score']['answers'] as $value) {
                    if (isset($value['preview_latex_string'])) {
                        $has_html = $submission->hasHtml($value['preview_latex_string']);
                        $formatted_submission = $has_html ? $value['preview_latex_string'] : '\(' . $value['preview_latex_string'] . '\)';
                    } elseif (isset($value['original_student_ans'])) {
                        $has_html = $submission->hasHtml($value['original_student_ans']);
                        $formatted_submission = $has_html ? $value['original_student_ans'] : '\(' . $value['original_student_ans'] . '\)';
                    } else {
                        $formatted_submission = 'Nothing submitted.';
                    }
                    $formatted_unconfirmed_submission[] = $formatted_submission;
                }
            }
            $response['has_html'] = $has_html;
            $response['unconfirmed_submission'] = $formatted_unconfirmed_submission;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve your unconfirmed submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public function storeSubmission(Request $request, Assignment $assignment, Question $question, UnconfirmedSubmission $unconfirmedSubmission): array
    {
        $response['type'] = 'error';
        $unconfirmed_submission = $unconfirmedSubmission->where('user_id', $request->user()->id)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first();

        try {
            if (!$unconfirmed_submission) {
                $response['message'] = "We were not able to find that unconfirmed submission.  Please try again or contact us for assistance.";
                return $response;
            }
            $unconfirmed_submission = $unconfirmed_submission->toArray();
            $stored_request = json_decode($unconfirmed_submission['submission'], true);
            $unconfirmed_submission['submission'] = $stored_request['submission'];
            $unconfirmed_submission['mastery_attempt_id'] = $stored_request['mastery_attempt_id'] ?? null;
            $unconfirmed_submission['technology'] = 'webwork';
            $Submission = new Submission();
            return $Submission->store(new StoreSubmission($unconfirmed_submission),
                new Submission(),
                new Assignment(),
                new Score(),
                new DataShop(),
                new AssignmentSyncQuestion());
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save this submission.  Please try again or contact us for assistance.";
        }
        return $response;

    }
}
