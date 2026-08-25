<?php

namespace App\Policies;

use App\Assignment;

use App\AssignmentSyncQuestion;
use App\Helpers\Helper;
use App\SubmissionFile;
use App\User;
use App\Question;
use App\Traits\CommonPolicies;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class AssignmentSyncQuestionPolicy
{
    use HandlesAuthorization;
    use CommonPolicies;

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return Response
     */
    public function checkForDiscussItQuestionsOverMultipleAssignmentQuestions(User                   $user,
                                                                              AssignmentSyncQuestion $assignmentSyncQuestion): Response
    {
        return in_array($user->role, [2, 4, 5])
            ? Response::allow()
            : Response::deny('You are not allowed to check for Discuss-it questions over multiple assignment questions.');

    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return Response
     */
    public function checkForDiscussitClickerOrOpenEndedQuestionsByCourseOrAssignment(User                   $user,
                                                                                     AssignmentSyncQuestion $assignmentSyncQuestion): Response
    {
        return in_array($user->role, [2, 4, 5])
            ? Response::allow()
            : Response::deny('You are not allowed to check for Discuss-it questions by course or assignment.');

    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function updateCanSubmitWorkOverride(User                   $user,
                                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                                Assignment             $assignment): Response
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed update the can submit work override for this question.');

    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function getDiscussItQuestionsByAssignment(User                   $user,
                                                      AssignmentSyncQuestion $assignmentSyncQuestion,
                                                      Assignment             $assignment): Response
    {

        $is_enrolled_student = in_array($user->id, $assignment->course->enrolledUsers->pluck('id')->toArray()) || $user->fake_student;
        return $is_enrolled_student
            ? Response::allow()
            : Response::deny('You are not allowed to get the discuss-it questions for that assignment.');


    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function updateUseExistingRubric(User                   $user,
                                            AssignmentSyncQuestion $assignmentSyncQuestion,
                                            Assignment             $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to update whether to use an overriding or existing rubric for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function removeOpenEndedQuestions(User                   $user,
                                             AssignmentSyncQuestion $assignmentSyncQuestion,
                                             Assignment             $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to check whether all solutions are released when the questions are closed.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function allSolutionsReleasedWhenClosed(User                   $user,
                                                   AssignmentSyncQuestion $assignmentSyncQuestion,
                                                   Assignment             $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to check whether all solutions are released when the questions are closed.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function deleteCustomRubric(User                   $user,
                                       AssignmentSyncQuestion $assignmentSyncQuestion,
                                       Assignment             $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to delete the custom rubric for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function updateCustomRubric(User                   $user,
                                       AssignmentSyncQuestion $assignmentSyncQuestion,
                                       Assignment             $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to update the custom rubric for that question.');
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    private function _questionInAssignment(Assignment $assignment, Question $question): bool
    {
        return in_array($question->id, $assignment->questions->pluck('id')->toArray());
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    private function _canAccessQuestionSettings(User $user, Assignment $assignment, Question $question): bool
    {
        $is_instructor = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
        $is_student = in_array($user->id, $assignment->course->enrolledUsers->pluck('id')->toArray())
            || $user->fake_student
            || (($assignment->formative || $assignment->course->formative) && $user->formative_student);

        return $this->_questionInAssignment($assignment, $question) && ($is_instructor || $is_student);
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    private function _canUpdateQuestionSettings(User $user, Assignment $assignment, Question $question): bool
    {
        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            && $this->_questionInAssignment($assignment, $question);
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function getDiscussItSettings(User                   $user,
                                         AssignmentSyncQuestion $assignmentSyncQuestion,
                                         Assignment             $assignment,
                                         Question               $question): Response
    {
        return $this->_canAccessQuestionSettings($user, $assignment, $question) || $assignment->course->public && $user->role === 2
            ? Response::allow()
            : Response::deny('You are not allowed to get the discuss-it settings for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function getFlashcardCardSettings(User                   $user,
                                             AssignmentSyncQuestion $assignmentSyncQuestion,
                                             Assignment             $assignment): Response
    {
        $is_instructor = $assignment->course->ownsCourseOrIsCoInstructor($user->id);
        $is_student = in_array($user->id, $assignment->course->enrolledUsers->pluck('id')->toArray())
            || $user->fake_student
            || ($assignment->formative && $user->formative_student);

        return $is_instructor || $is_student
            ? Response::allow()
            : Response::deny('You are not allowed to get the flashcard settings for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateDiscussItSettings(User                   $user,
                                            AssignmentSyncQuestion $assignmentSyncQuestion,
                                            Assignment             $assignment,
                                            Question               $question): Response
    {
        return $this->_canUpdateQuestionSettings($user, $assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to update the discuss-it settings for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateFlashcardCardSettings(User                   $user,
                                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                                Assignment             $assignment,
                                                Question               $question): Response
    {
        return $this->_canUpdateQuestionSettings($user, $assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to update the flashcard settings for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function getForgeSettings(User                   $user,
                                     AssignmentSyncQuestion $assignmentSyncQuestion,
                                     Assignment             $assignment,
                                     Question               $question): Response
    {
        return $this->_canAccessQuestionSettings($user, $assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to get the Forge settings for that question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateForgeSettings(User                   $user,
                                        AssignmentSyncQuestion $assignmentSyncQuestion,
                                        Assignment             $assignment,
                                        Question               $question): Response
    {
        return $this->_canUpdateQuestionSettings($user, $assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to update the Forge settings for that question.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateIFrameProperties(User                   $user,
                                           AssignmentSyncQuestion $assignmentSyncQuestion,
                                           Assignment             $assignment,
                                           Question               $question): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id) && in_array($question->id, $assignment->questions->pluck('id')->toArray())
            ? Response::allow()
            : Response::deny('You are not allowed to update the iframe properties for that question.');


    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateToLatestRevision(User                   $user,
                                           AssignmentSyncQuestion $assignmentSyncQuestion,
                                           Assignment             $assignment,
                                           Question               $question): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id) && in_array($question->id, $assignment->questions->pluck('id')->toArray())
            ? Response::allow()
            : Response::deny('You are not allowed to update to the latest revision for that question.');


    }

    public function updateCustomTitle(User                   $user,
                                      AssignmentSyncQuestion $assignmentSyncQuestion,
                                      Assignment             $assignment,
                                      Question               $question): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id) && in_array($question->id, $assignment->questions->pluck('id')->toArray())
            ? Response::allow()
            : Response::deny('You are not allowed to update the question title for that question.');


    }

    public function remixAssignmentWithChosenQuestions(User                   $user,
                                                       AssignmentSyncQuestion $assignmentSyncQuestion,
                                                       Assignment             $assignment)
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to remix that assignment.');


    }

    public function order(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment)
    {

        return (int)$assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to order the questions for this assignment.');

    }

    public function storeOpenEndedSubmissionDefaultText(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question)
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        $message = (!$assignment->questions->contains($question->id))
            ? "You can't add default text to that  question since it's not in the assignment."
            : 'You are not allowed to add default text to this assignment.';
        return $authorized
            ? Response::allow()
            : Response::deny($message);

    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function delete(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question)
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        $message = (!$assignment->questions->contains($question->id))
            ? "You can't remove that question since it's not in the assignment."
            : 'You are not allowed to remove a question from this assignment.';
        return $authorized
            ? Response::allow()
            : Response::deny($message);
    }


    public function setCurrentPage(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to set the current page.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function addTimeToClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to add time to this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function startClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to start this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateCustomResponseFormat(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to update the response format for this question.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function updateCustomClickerTimeToSubmit(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to update the time to submit for this clicker assessment.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function viewClickerSubmissions(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to start this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function openClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to open this clicker assessment.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function resetClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to reset this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function pauseClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to pause this clicker assessment.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function resumeClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to resume this clicker assessment.');
    }

    public function updateReleaseSolutionWhenQuestionIsClosed(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to show or not show the solution for this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function endClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to end this clicker assessment.');
    }


    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function restartTimerInClickerAssessment(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment, Question $question): Response
    {
        $authorized = $assignment->questions->contains($question->id) && ($assignment->course->ownsCourseOrIsCoInstructor($user->id));
        return $authorized
            ? Response::allow()
            : Response::deny('You are not allowed to restart the clicker timer.');
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function add(User                   $user,
                        AssignmentSyncQuestion $assignmentSyncQuestion,
                        Assignment             $assignment)
    {


        return ($assignment->course->ownsCourseOrIsCoInstructor($user->id))
            ? Response::allow()
            : Response::deny('You are not allowed to add a question to this assignment.');
    }

    public function update(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment)
    {
        $authorized = true;
        $message = '';

        /*** IMPORTANT: What will you do about the update points business with the randomized questions??? ***/
        if (($user->id !== ((int)$assignment->course->user_id))) {
            $message = "You are not allowed to update that resource.";
            $authorized = false;
        } else if ($assignment->course->alpha
            && $assignment->hasNonFakeStudentFileOrQuestionSubmissions($assignment->addBetaAssignmentIds())) {
            {
                $message = "There is at least one submission to this question in one of the Beta assignments so you can't change the points.";
                $authorized = false;
            }
        } else if ($assignment->isBetaAssignment()) {
            $message = "This is an assignment in a Beta course so you can't change the points.";
            $authorized = false;
        } else if ($assignment->hasNonFakeStudentFileOrQuestionSubmissions()) {
            $authorized = false;
            $message = "This cannot be updated since students have already submitted responses to this assignment.";
        }

        return $authorized
            ? Response::allow()
            : Response::deny($message);
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function hasNonScoredSubmissionFiles(User                   $user,
                                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                                Assignment             $assignment)
    {

        return $assignment->course->id
            ? Response::allow()
            : Response::deny("You are not allowed to check for non-scored submission files.");

    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @param SubmissionFile $submissionFile
     * @return Response
     */
    public function updateOpenEndedSubmissionType(User                   $user,
                                                  AssignmentSyncQuestion $assignmentSyncQuestion,
                                                  Assignment             $assignment,
                                                  Question               $question,
                                                  SubmissionFile         $submissionFile): Response
    {
        $message = '';
        $authorized = true;
        if (($user->id !== ((int)$assignment->course->user_id))) {
            $message = "You are not allowed to update the open-ended submission type.";
            $authorized = false;
        } else if ($assignment->course->alpha
            && $submissionFile->hasNonFakeStudentFileSubmissionsForAssignmentQuestion($assignment->addBetaAssignmentIds(), $question->id, true)) {
            {
                $message = "There is at least one graded submission to this question in either the Alpha assignment or one of the Beta assignments so you can't change the open-ended submission type.";
                $authorized = false;
            }
        } else if ($assignment->isBetaAssignment()) {
            $message = "This is an assignment in a Beta course so you can't change the open-ended submission type.";
            $authorized = false;
        } else if ($submissionFile->hasNonFakeStudentFileSubmissionsForAssignmentQuestion([$assignment->id], $question->id, true)) {
            $authorized = false;
            $message = "There is at least one graded submission to this question so you can't change the open-ended submission type.";
        }

        return $authorized
            ? Response::allow()
            : Response::deny($message);
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function updateClickerResultsReleased(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment): Response
    {

        return $assignment->course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny("You are not allowed to update the clicker status for this question.");
    }

    /**
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return Response
     */
    public function getRubricCategoriesByAssignmentAndQuestion(User $user, AssignmentSyncQuestion $assignmentSyncQuestion, Assignment $assignment): Response
    {
        return $this->isOwnerOrGrader($assignment, $user)
            ? Response::allow()
            : Response::deny("You are not allowed to get the rubric categories for that question in that assignment.");


    }

    // Add these methods to your AssignmentSyncQuestionPolicy

    /**
     * Determine if the user can get forge draft submissions
     *
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function getForgeDraftSubmissions(User                   $user,
                                             AssignmentSyncQuestion $assignmentSyncQuestion,
                                             Assignment             $assignment,
                                             Question               $question): Response
    {
        $is_instructor = $assignment->course->ownsCourseOrIsCoInstructor($user->id);

        return $is_instructor && $this->_questionInAssignment($assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to view submissions for this draft.');
    }

    /**
     * Determine if the user can delete a forge draft
     *
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @param Question $question
     * @return Response
     */
    public function destroyForgeDraft(User                   $user,
                                      AssignmentSyncQuestion $assignmentSyncQuestion,
                                      Assignment             $assignment,
                                      Question               $question): Response
    {
        $is_instructor = $assignment->course->ownsCourseOrIsCoInstructor($user->id);

        return $is_instructor && $this->_questionInAssignment($assignment, $question)
            ? Response::allow()
            : Response::deny('You are not allowed to delete this draft.');
    }


}
