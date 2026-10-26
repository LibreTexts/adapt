<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentFile;
use App\AssignmentLevelOverride;
use App\AssignmentSyncQuestion;
use App\DiscussionComment;
use App\Exceptions\Handler;
use App\QuestionLevelOverride;
use App\QuestionMediaUpload;
use App\Score;
use App\User;
use \Exception;
use App\Extension;
use App\Question;
use App\SubmissionFile;
use App\Traits\LatePolicy;
use App\Traits\S3;
use App\Traits\GeneralSubmissionPolicy;
use App\Traits\SubmissionFiles;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Log;
use App\Traits\DateFormatter;
use Illuminate\View\View;

class SubmissionAudioController extends Controller
{
    use DateFormatter;
    use LatePolicy;
    use S3;
    use GeneralSubmissionPolicy;
    use SubmissionFiles;

    /**
     * @param int $assignment_id
     * @param string $s3_key
     * @param int $is_phone
     * @return Application|Factory|View
     */
    public
    function mediaPlayer(int $assignment_id, string $s3_key, int $is_phone)
    {

        $path = "assignments/$assignment_id/$s3_key";
        $temporary_url = Storage::disk('s3')->temporaryUrl($path, Carbon::now()->addDays(7));
        if (!Storage::disk('s3')->exists($path)) {
            return view('media_player_error', ['message' => "The file $path was not found on the server."]);
        }
        return view('media_player', [
            'type' => 'audio',
            'temporary_url' => $temporary_url,
            'vtt_file' => '',
            'start_time' => 0,
            'is_phone' => $is_phone]);
    }

    public function logError(Request $request)
    {
        Log::error(print_r($request->all()));

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param int $is_phone
     * @param string $submission
     * @param SubmissionFile $submissionFile
     * @param Extension $extension
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public function store(Request                $request,
                          Assignment             $assignment,
                          Question               $question,
                          int                    $is_phone,
                          SubmissionFile         $submissionFile,
                          Extension              $extension,
                          AssignmentSyncQuestion $assignmentSyncQuestion,
                          string                 $submission = ''): array
    {
        $response['type'] = 'error';
        $assignment_id = $assignment->id;
        $question_id = $question->id;
        $user = $request->user();
        $user_id = $user->id;
        if ($is_phone && !$submission) {
            $response['message'] = "You are submitting with your phone but we cannot locate the file.";
            return $response;

        }

        if ($can_upload_response = $this->canSubmitBasedOnGeneralSubmissionPolicy($user, $assignment, $assignment_id, $question_id)) {
            if ($can_upload_response['type'] === 'error') {
                $questionLevelOverride = new QuestionLevelOverride();
                $assignmentLevelOverride = new AssignmentLevelOverride();
                $has_question_level_override = $questionLevelOverride->hasOpenEndedOverride($assignment_id, $question_id, $assignmentLevelOverride);
                if (!$has_question_level_override) {
                    $response['message'] = $can_upload_response['message'];
                    return $response;
                }
            }
        }

        try {
            $max_number_of_uploads_allowed = 15;//number allowed per question/assignment
            $latest_submission = DB::table('submission_files')
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->where('user_id', $user_id)
                ->select('upload_count')
                ->first();

            $upload_count = is_null($latest_submission) ? 0 : $latest_submission->upload_count;


            if ($upload_count + 1 > $max_number_of_uploads_allowed) {
                $response['message'] = 'You have exceeded the number of times that you can re-upload a file submission.';
                return $response;

            }

            if (!$is_phone) {
                $submission = $request->file('audio')->store("assignments/$assignment->id", 'local');
                $submissionContents = Storage::disk('local')->get($submission);
                Storage::disk('s3')->put($submission, $submissionContents, ['StorageClass' => 'STANDARD_IA']);
                $mime_type = Storage::disk('s3')->mimeType($submission);
                if ($mime_type !== 'audio/mpeg') {
                    $response['message'] = "Your file has a mime type of $mime_type which is not an accepted mime type.";
                    return $response;
                }
            }
            $submission_file_data = ['type' => 'audio',
                'submission' => basename($submission),
                'original_filename' => '',
                'file_feedback' => null,
                'text_feedback' => null,
                'date_graded' => null,
                'score' => null,
                'upload_count' => $upload_count + 1,
                'date_submitted' => Carbon::now()];
            DB::beginTransaction();
            $submissionFile->updateOrCreate(
                ['user_id' => $user_id,
                    'assignment_id' => $assignment_id,
                    'question_id' => $question_id],
                $submission_file_data
            );
            //they may have used the regular file uploader instead of the audio uploader so let's remove the regular one
            $submissionFile->where('user_id', $user_id)
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->where('type', 'q')
                ->delete();
            $score = $this->updateScoreIfCompletedScoringType($assignment, $question_id);
            DB::commit();
            $response['date_submitted'] = $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime(date('Y-m-d H:i:s'), Auth::user()->time_zone, 'M d, Y g:i:s a');
            $response['submission_file_url'] = $this->getTemporaryUrl($assignment_id, basename($submission));
            $response['score'] = $score === null ? null : $score;
            $response['message'] = "Your audio submission has been saved.";
            $response['completed_all_assignment_questions'] = $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment);
            $response['late_file_submission'] = $this->isLateSubmission($extension, $assignment, Carbon::now());


            if (($upload_count >= $max_number_of_uploads_allowed - 3)) {
                $response['message'] .= "  You may resubmit " . ($max_number_of_uploads_allowed - (1 + $upload_count)) . " more times.";
            }
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save this audio submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    public function storeAudioFeedback(Request $request, User $user, Assignment $assignment, Question $question, AssignmentFile $assignmentFile)
    {

        $response['type'] = 'error';
        $assignment_id = $assignment->id;
        $question_id = $question->id;
        $student_user_id = $user->id;


        $authorized = Gate::inspect('uploadAudioFeedback', [$assignmentFile, $user->find($student_user_id), $assignment->find($assignment_id)]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {


            //save locally and to S3
            $audioFeedback = $request->file('audio')->store("assignments/$assignment_id", 'local');
            $feedbackContents = Storage::disk('local')->get($audioFeedback);
            Storage::disk('s3')->put($audioFeedback, $feedbackContents, ['StorageClass' => 'STANDARD_IA']);

            DB::table('submission_files')
                ->where('user_id', $student_user_id)
                ->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->update(['file_feedback' => basename($audioFeedback)]);

            $response['type'] = 'success';
            $response['message'] = 'Your audio feedback has been saved.';
            $response['file_feedback_url'] = $this->getTemporaryUrl($assignment_id, basename($audioFeedback));
            $response['file_feedback_type'] = 'audio';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your audio feedback.  Please try again or contact us for assistance.";
        }
        return $response;
    }

}
