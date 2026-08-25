<?php

namespace App\Policies;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\JWE;
use App\Question;
use App\Submission;
use App\User;
use App\Webwork;
use Exception;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebworkPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @return Response
     */
    public function templates(User $user): Response
    {

        return $user->role !== 3
            ? Response::allow()
            : Response::deny('You are not allowed to get the weBWork templates.');

    }

    /**
     * @param User $user
     * @return Response
     */
    public function hint(User $user): Response
    {
        // Students must go through ShownHintController's confirm/penalty flow.
        // This endpoint is only for previewing/authoring contexts (instructors,
        // graders, admins, fake students), where there's no assignment/penalty
        // context to speak of.
        return $user->role !== 3 || $user->fake_student
            ? Response::allow()
            : Response::deny('You are not allowed to access this hint directly.');
    }

    /**
     * @param User $user
     * @param Webwork $webwork
     * @param string $problemJWT
     * @return Response
     */
    public function solution(User $user, Webwork $webwork, string $problemJWT): Response
    {

        if ($user->role !== 3 || $user->fake_student) {
            return Response::allow();
        }
        $jwe = new JWE();
        $claims = json_decode($jwe->decrypt($problemJWT, 'webwork'), 1);
        if (!isset($claims['adapt'])) {
            return Response::deny('No access: You are missing the ADAPT claim in the solution JWT.');
        }

        $adapt = $claims['adapt'];

        if (!isset($adapt['assignment_id'], $adapt['question_id'])) {
            return Response::deny(
                'No access: You need both an assignment ID and question ID in the problem JWT in order to view the solution.'
            );
        }

        $assignment = Assignment::find($adapt['assignment_id']);
        $question = Question::find($adapt['question_id']);

        if (!$assignment || !$question) {
            return Response::deny('No access: Invalid assignment or question.');
        }

        $submission = Submission::where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->where('user_id', $user->id)
            ->first();

        $show_real_time_solution = (new AssignmentSyncQuestion())->showRealTimeSolution(
            $assignment,
            new Submission(),
            $submission,
            $question
        );

        $gave_up = DB::table('can_give_ups')
            ->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->where('status', 'gave up')
            ->exists();

        if (!$show_real_time_solution && !$gave_up && !$assignment->solutions_released) {
            return Response::deny('No access: You do not have access to this weBWork solution.');
        }

        return Response::allow();
    }
}
