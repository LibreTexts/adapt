<?php

namespace App\Policies;

use App\FrameworkItemSyncLearningTree;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FrameworkItemSyncLearningTreePolicy
{
    use HandlesAuthorization;

    public function getLearningTreesByDescriptor(User $user): Response
    {
        return in_array($user->role, [2, 4, 5])
            ? Response::allow()
            : Response::deny('You are not allowed to get the descriptors for that learning tree.');

    }

    /**
     * @param User $user
     * @return Response
     */
    public function getFrameworkItemsByLearningTree(User $user): Response
    {
        return in_array($user->role, [2, 4, 5])
            ? Response::allow()
            : Response::deny('You are not allowed to get the framework alignments for the learning tree.');

    }
}
