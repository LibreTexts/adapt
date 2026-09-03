<?php

namespace App\Policies;

use App\Course;
use App\Helpers\Helper;
use App\NonUpdatedQuestionRevision;
use App\PendingQuestionRevision;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class NonUpdatedQuestionRevisionPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @param NonUpdatedQuestionRevision $nonUpdatedQuestionRevision
     * @param Course $course
     * @return Response
     */
    public function getNonUpdatedQuestionRevisionsByCourse(User $user,
                                                           NonUpdatedQuestionRevision $nonUpdatedQuestionRevision,
                                                           Course $course): Response
    {

        return $course->ownsCourseOrIsCoInstructor($user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to get the non-updated question revisions for this course.');

    }

    /**
     * @param User $user
     * @param NonUpdatedQuestionRevision $nonUpdatedQuestionRevision
     * @param Course $course
     * @param bool $changesAreTopical
     * @return Response
     */
    public function updateToLatestQuestionRevisionsByCourse(User $user,
                                                            NonUpdatedQuestionRevision $nonUpdatedQuestionRevision,
                                                            Course $course,
                                                            bool $changesAreTopical = false): Response
    {
        if (Helper::isAdmin()) {
            return $changesAreTopical
                ? Response::allow()
                : Response::deny('You must confirm that the changes are topical.');
        }

        return $course->realStudentsWhoCanSubmit()->isEmpty()
            ? Response::allow()
            : Response::deny('You are not allowed to update the course questions to the latest revision since there are students enrolled.');


    }
}
