<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Grader;
use App\Question;
use App\SubmissionFile;
use App\SubmissionFileConversion;
use App\SubmissionFilePage;
use App\SubmissionPageAnnotation;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Grader markup (arrows, freehand, text) on the page images of a student's file submission.
 */
class SubmissionPageAnnotationController extends Controller
{
    const MAX_STATE_BYTES = 1000000;
    const MAX_MARKERS = 500;

    /**
     * All of a grader's markup for one student's submission to one question, keyed by page.
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param User $studentUser
     * @param SubmissionFile $submissionFile
     * @param Grader $grader
     * @return array
     * @throws Exception
     */
    public function index(Assignment     $assignment,
                          Question       $question,
                          User           $studentUser,
                          SubmissionFile $submissionFile,
                          Grader         $grader): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getFilesFromS3', [$submissionFile, $assignment, $studentUser, $grader]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        // students see markup like the rest of their feedback: their own, once scores are released
        $user = request()->user();
        if ((int)$user->role === 3
            && ((int)$user->id !== (int)$studentUser->id || !$this->_scoresReleased($assignment))) {
            $response['message'] = 'Your grader\'s markup will be available once scores are released.';
            return $response;
        }
        try {
            $response['annotations'] = $this->_annotationsByPage($assignment->id, $question->id, $studentUser->id);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve the markup for this submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * The logged-in student's own submission pages for one question, with the grader's markup on them.
     * Only once scores are released, the same rule as their other feedback.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param SubmissionFile $submissionFile
     * @return array
     * @throws Exception
     */
    public function mine(Request        $request,
                         Assignment     $assignment,
                         Question       $question,
                         SubmissionFile $submissionFile): array
    {
        $response['type'] = 'error';
        $user = $request->user();
        if (!$assignment->course->enrollments->contains('user_id', $user->id)) {
            $response['message'] = 'You are not allowed to view this submission.';
            return $response;
        }
        if (!$this->_scoresReleased($assignment)) {
            $response['message'] = "Your grader's markup will be available once scores are released.";
            return $response;
        }
        try {
            $response['pages'] = [];
            $response['annotations'] = (object)[];
            $submission_file = $submissionFile->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $user->id)
                ->first();
            if ($submission_file) {
                $annotations = $this->_annotationsByPage($assignment->id, $question->id, $user->id);
                //a page that was only turned isn't markup to show the student
                $marked_up = array_filter((array)$annotations, function ($state) {
                    return !empty(((array)$state)['markers']);
                });
                if ($marked_up) {
                    $response['pages'] = SubmissionFilePage::forSubmission($assignment->id,
                        $submission_file->submission,
                        $submission_file->page,
                        $submission_file->end_page)['pages'];
                    $response['annotations'] = $annotations;
                }
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve the markup on your submission.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @return bool
     */
    private function _scoresReleased(Assignment $assignment): bool
    {
        return (bool)(int)$assignment->show_scores;
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $student_user_id
     * @return object page => marker.js state; an object even when empty so the front end can look pages up
     */
    private function _annotationsByPage(int $assignment_id, int $question_id, int $student_user_id): object
    {
        $annotations = [];
        SubmissionPageAnnotation::where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('user_id', $student_user_id)
            ->get()
            ->each(function ($annotation) use (&$annotations) {
                $annotations[$annotation->page] = $annotation->state;
            });
        return (object)$annotations;
    }

    /**
     * Saves the markup and rotation of one page, or removes the row when there's neither.
     *
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param User $studentUser
     * @param int $page
     * @param SubmissionFile $submissionFile
     * @param Grader $grader
     * @return array
     * @throws Exception
     */
    public function update(Request        $request,
                           Assignment     $assignment,
                           Question       $question,
                           User           $studentUser,
                           int            $page,
                           SubmissionFile $submissionFile,
                           Grader         $grader): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getFilesFromS3', [$submissionFile, $assignment, $studentUser, $grader]);
        if (!in_array((int)$request->user()->role, [2, 4]) || !$authorized->allowed()) {
            $response['message'] = 'You are not allowed to mark up this submission.';
            return $response;
        }

        $state = $request->input('state');
        $state_error = $this->_stateError($state);
        if ($state_error) {
            $response['message'] = $state_error;
            return $response;
        }

        try {
            $submission_file = $submissionFile->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $studentUser->id)
                ->first();
            if (!$this->_pageBelongsToSubmission($assignment->id, $submission_file, $page)) {
                $response['message'] = "Page $page isn't part of this student's submission for this question.";
                return $response;
            }

            $key = ['assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => $studentUser->id,
                'page' => $page];
            if (!count($state['markers']) && empty($state['rotation'])) {
                SubmissionPageAnnotation::where($key)->delete();
                $response['message'] = "The markup on Page $page has been removed.";
            } else {
                SubmissionPageAnnotation::updateOrCreate($key,
                    ['state' => $state, 'grader_id' => $request->user()->id]);
                $response['message'] = count($state['markers'])
                    ? "Your markup on Page $page has been saved."
                    : "The rotation of Page $page has been saved.";
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your markup.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * A light check that this looks like marker.js state, and isn't huge.
     *
     * @param mixed $state
     * @return string|null
     */
    private function _stateError($state): ?string
    {
        if (!is_array($state)
            || !isset($state['width'], $state['height'], $state['markers'])
            || !is_numeric($state['width']) || $state['width'] <= 0
            || !is_numeric($state['height']) || $state['height'] <= 0
            || !is_array($state['markers'])) {
            return 'The markup could not be read.  Please try again.';
        }
        if (isset($state['rotation']) && !in_array((int)$state['rotation'], [0, 90, 180, 270], true)) {
            return 'The rotation could not be read.  Please try again.';
        }
        if (count($state['markers']) > self::MAX_MARKERS) {
            return 'That page has too many marks to save (the limit is ' . self::MAX_MARKERS . ').';
        }
        if (strlen(json_encode($state)) > self::MAX_STATE_BYTES) {
            return 'That markup is too large to save.  Try removing some of the freehand drawing.';
        }
        return null;
    }

    /**
     * @param int $assignment_id
     * @param SubmissionFile|null $submission_file
     * @param int $page
     * @return bool
     */
    private function _pageBelongsToSubmission(int $assignment_id, ?SubmissionFile $submission_file, int $page): bool
    {
        if (!$submission_file || !$submission_file->submission) {
            return false;
        }
        $conversion = SubmissionFileConversion::where('assignment_id', $assignment_id)
            ->where('submission', $submission_file->submission)
            ->where('status', SubmissionFileConversion::COMPLETED)
            ->first();
        if (!$conversion) {
            return false;
        }
        $range = SubmissionFilePage::pageRange($submission_file->page, $submission_file->end_page, $conversion->number_of_pages);
        return $range && $page >= $range[0] && $page <= $range[1];
    }
}
