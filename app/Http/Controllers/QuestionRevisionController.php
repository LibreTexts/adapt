<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Http\Requests\EmailStudentsWithSubmissionsRequest;
use App\Question;
use App\QuestionRevision;
use App\Traits\DateFormatter;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuestionRevisionController extends Controller
{
    use DateFormatter;

    /**
     * @param QuestionRevision $questionRevision
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function getUpdateRevisionInfo(QuestionRevision $questionRevision, Assignment $assignment, Question $question): array
    {
        $response['type'] = 'error';
        try {

            $authorized = Gate::inspect('getUpdateRevisionInfo', $questionRevision);
            if (!$authorized->allowed()) {
                $response['message'] = "You are not allowed to get the revision for this question.";
                return $response;
            }
            $updates = [];
            foreach ($questionRevision as $key => $value) {
                if (!in_array($key, ['created_at', 'updated_at', 'id', 'revision_number', 'action', 'reason_for_edit'])) {
                    if ($questionRevision->{$key} !== $question->{$key}) {
                        $updates[$key] = ['current' => $questionRevision->{$key}, 'updated' => $question->{$key}];
                    }
                }
            }

            $response['type'] = 'success';
            $response['updates'] = $updates;
            $response['reason_for_edit'] = $questionRevision->reason_for_edit;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the information needed to update the question revision. Please try again.";
        }
        return $response;
    }

    /**
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function getRevisionsByQuestion(Question $question): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('update', [$question, $question->folder_id]);
            if (!$authorized->allowed()) {
                $response['message'] = 'You are not allowed to get the revisions for this question.';
                return $response;
            }
            $rubric_categories_revision_info = DB::table('rubric_categories')
                ->where('question_id', $question->id)
                ->orderBy('order')
                ->get();
            $rubric_categories_revision_info_by_question_revision_id = [];
            foreach ($rubric_categories_revision_info as $report_revision_info) {
                if (!isset($rubric_categories_revision_info_by_question_revision_id[$report_revision_info->question_revision_id])) {
                    $rubric_categories_revision_info_by_question_revision_id[$report_revision_info->question_revision_id] = [];
                }
                $rubric_categories_revision_info_by_question_revision_id[$report_revision_info->question_revision_id][] = $report_revision_info;
            }
            $revision_info = DB::table('question_revisions')
                ->join('users', 'question_revisions.question_editor_user_id', '=', 'users.id')
                ->where('question_id', $question->id)
                ->orderBy('revision_number', 'desc')
                ->select('question_revisions.*',
                    DB::raw('CONCAT(first_name, " " , last_name) AS question_editor'))
                ->get();
            $question_media_uploads = DB::table('question_media_uploads')
                ->where('question_id', $question->id)
                ->orderBy('order')
                ->get();
            $question_media_uploads_by_question_revision_id = [];
            foreach ($question_media_uploads as $question_media_upload) {
                if (!isset($question_media_uploads_by_question_revision_id[$question_media_upload->question_revision_id])) {
                    $question_media_uploads_by_question_revision_id[$question_media_upload->question_revision_id] = [];
                }
                $question_media_uploads_by_question_revision_id[$question_media_upload->question_revision_id][] = $question_media_upload;
            }

            $revisions = [];
            foreach ($revision_info as $key => $revision) {
                $additional_text = '';
                if ($key === 0) {
                    $additional_text = ' (Current)';
                }
                if ($key === count($revision_info) - 1) {
                    $additional_text = ' (Original)';
                }
                $text = $revision->revision_number . ' --- ';
                $text .= Carbon::parse($revision->updated_at)->timezone(request()->user()->time_zone)->format('n/j/y g:i:s A');
                $text .= ' --- ' . $revision->question_editor;
                $text .= $additional_text;
                $revision->text = $text;
                $revision->rubric_categories = $rubric_categories_revision_info_by_question_revision_id[$revision->id] ?? [];
                $revision->media_uploads = $question_media_uploads_by_question_revision_id[$revision->id] ?? [];
                $revisions[] = $revision;
            }
            $response['type'] = 'success';
            $response['revisions'] = $revisions;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the question revisions. Please try again.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param QuestionRevision $questionRevision
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function show(Request $request, QuestionRevision $questionRevision, Question $question): array
    {
        $response['type'] = 'error';
        try {

            $authorized = Gate::inspect('show', $questionRevision);
            if (!$authorized->allowed()) {
                $response['message'] = "You are not allowed to get the revision for this question.";
                return $response;
            }
            $question_revision_id = $questionRevision->id;
            $question_revision = $question->formatQuestionToEdit($request, $questionRevision, $questionRevision->question_id, $question_revision_id);
            $question_revision['id'] = $questionRevision->question_id;
            $question_revision['question_revision_id'] = $question_revision_id;

            $question_revision['tags'] = [];
            $response['type'] = 'success';
            $response['question_revision'] = $question_revision;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the question revision. Please try again.";

        }
        return $response;

    }

    /**
     * @param EmailStudentsWithSubmissionsRequest $request
     * @param QuestionRevision $questionRevision
     * @return array
     * @throws Exception
     */
    public function emailStudentsWithSubmissions(EmailStudentsWithSubmissionsRequest $request, QuestionRevision $questionRevision): array
    {
        $response['type'] = 'error';
        try {
            $assignment = Assignment::find($request->assignment_id);
            $authorized = Gate::inspect('emailStudentsWithSubmissions', [$questionRevision, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $instructor = User::find($request->user()->id);
            $students = DB::table('users')->whereIn('email', $request->emails)->get();
            $students_by_email = [];
            foreach ($students as $student) {
                $students_by_email[$student->email] = $student->first_name . ' ' . $student->last_name;
            }
            DB::beginTransaction();
            foreach ($students_by_email as $email => $student_name) {
                DB::table('student_emails_with_submissions')->insert([
                    'student_email' => $email,
                    'student_name' => $student_name,
                    'message' => $request->message,
                    'instructor_email' => $instructor->email,
                    'instructor_name' => $instructor->first_name . ' ' . $instructor->last_name,
                    'created_at' => now(),
                    'updated_at' => now()]);

            }
            $response['type'] = 'success';
            $response['message'] = "Your students will receive a notification by email in a minute or so.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error sending the students the emails. Please try again.";
        }
        return $response;

    }
}
