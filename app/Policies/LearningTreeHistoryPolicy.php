<?php

namespace App\Policies;

use App\Helpers\Helper;
use App\LearningTree;
use App\LearningTreeHistory;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LearningTreeHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @param LearningTreeHistory $learningTreeHistory
     * @param LearningTree $learningTree
     * @return Response
     */
    public function updateLearningTreeFromHistory(User $user, LearningTreeHistory $learningTreeHistory, LearningTree $learningTree)
    {
        return ((int) $learningTree->user_id === $user->id || Helper::isAdmin())
            ? Response::allow()
            : Response::deny('You are not allowed to update this Learning Tree.');

    }
}
