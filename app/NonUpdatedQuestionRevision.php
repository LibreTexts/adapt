<?php

namespace App;


use App\Helpers\Helper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NonUpdatedQuestionRevision extends Model
{
    /**
     * @param Course $course
     * @param QuestionRevision $questionRevision
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param PendingQuestionRevision $pendingQuestionRevision
     * @param bool $changesAreTopical When true (admin-confirmed), the revision is still applied but student
     *             submissions and scores are left untouched.
     * @return array
     */
    public function updateToLatestQuestionRevisionByCourse(Course                  $course,
                                                           QuestionRevision        $questionRevision,
                                                           AssignmentSyncQuestion  $assignmentSyncQuestion,
                                                           PendingQuestionRevision $pendingQuestionRevision,
                                                           bool                    $changesAreTopical = false): array
    {
        $non_updated_assignment_questions_by_course = $this->nonUpdatedAssignmentQuestionsByCourse($course, $questionRevision);
        $assignment_ids = [];
        $question_ids = [];
        foreach ($non_updated_assignment_questions_by_course as $non_updated_assignment_question) {
            $assignment_id = $non_updated_assignment_question->assignment_id;
            $question_id = $non_updated_assignment_question->question_id;
            $assignment_ids[] = $assignment_id;
            $question_ids[] = $question_id;
        }
        $assignment_ids = array_unique($assignment_ids);
        $question_ids = array_unique($question_ids);
        $questions = Question::whereIn('id', $question_ids)->get();
        $questions_by_question_id = [];
        foreach ($questions as $question) {
            $questions_by_question_id[$question->id] = $question;
        }
        $assignments_by_assignment_id = [];
        $assignments = Assignment::whereIn('id', $assignment_ids)->get();
        foreach ($assignments as $assignment) {
            $assignments_by_assignment_id[$assignment->id] = $assignment;
        }

        DB::beginTransaction();
        $removed_student_submissions = false;
        foreach ($non_updated_assignment_questions_by_course as $non_updated_assignment_question) {
            $assignment_id = $non_updated_assignment_question->assignment_id;
            $question_id = $non_updated_assignment_question->question_id;
            $assignment = $assignments_by_assignment_id[$assignment_id];
            $question = $questions_by_question_id[$question_id];
            $assignmentSyncQuestion->where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->update(['question_revision_id' => $non_updated_assignment_question->latest_question_revision_id,
                    'updated_at' => now()]);
            $pendingQuestionRevision->where('assignment_id', $assignment_id)->where('question_id', $question_id)->delete();
            if (!$changesAreTopical) {
                $removed_student_submissions = $assignmentSyncQuestion->questionHasSomeTypeOfRealStudentSubmission($assignment, $question);
                $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
                Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment_id, $question_id);
            }
        }
        DB::commit();
        $response['type'] = 'success';
        $response['message'] = 'The question has been updated to the latest revision.';
        if ($changesAreTopical) {
            $response['message'] .= ' Since the changes were confirmed as topical, student submissions were not removed.';
        } else {
            $response['message'] .= $removed_student_submissions ?
                ' In addition, student submissions were removed and scores were updated.'
                : ' There were no student submissions which needed to be removed.';
        }
        return $response;
    }

    /**
     * @param Course $course
     * @param QuestionRevision $questionRevision
     * @return array
     */
    public function nonUpdatedAssignmentQuestionsByCourse(Course $course, QuestionRevision $questionRevision): array
    {
        $assignment_ids = $course->assignments()->pluck('id')->toArray();
        $assignment_questions = DB::table('assignment_question')
            ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
            ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
            ->join('question_revisions', 'assignment_question.question_id', '=', 'question_revisions.question_id')
            ->where('question_revisions.id', '<>', 'assignment_question.question_revision_id')
            ->whereIn('assignment_id', $assignment_ids)
            ->select('assignment_question.id AS assignment_question_id',
                'assignment_question.custom_question_title',
                'assignment_question.order',
                'assignment_question.question_revision_id AS current_question_revision_id',
                'assignment_question.id AS assignment_question_id',
                'assignments.name AS assignment_name',
                'assignments.id AS assignment_id',
                'question_revisions.revision_number AS current_revision_number',
                'questions.id AS question_id',
                'questions.title')
            ->get();
        $question_ids = $assignment_questions->pluck('question_id')->toArray();

        $latest_question_revisions_by_question_id = $questionRevision->latestByQuestionId($question_ids);
        $non_updated_assignment_questions = [];
        $used_assignment_question_ids = [];
        foreach ($assignment_questions as $assignment_question) {
            if (!in_array($assignment_question->assignment_question_id, $used_assignment_question_ids)) {
                if ($assignment_question->current_question_revision_id !== $latest_question_revisions_by_question_id[$assignment_question->question_id]->id) {
                    $assignment_question->latest_question_revision_id = $latest_question_revisions_by_question_id[$assignment_question->question_id]->id;
                    $assignment_question->latest_revision_number = $latest_question_revisions_by_question_id[$assignment_question->question_id]->revision_number;
                    $non_updated_assignment_questions[] = $assignment_question;
                }
            }
            $used_assignment_question_ids[] = $assignment_question->assignment_question_id;
        }
        return $non_updated_assignment_questions;
    }
}
