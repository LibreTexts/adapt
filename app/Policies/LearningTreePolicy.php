<?php

namespace App\Policies;

use App\Helpers\Helper;
use App\Traits\CommonPolicies;
use App\User;
use App\LearningTree;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LearningTreePolicy
{
    use CommonPolicies;

    /**
     * @param User $user
     * @return Response
     */
    public function store(User $user): Response
    {
        return ((int) $user->role === 2)
            ? Response::allow()
            : Response::deny('You are not allowed to save Learning Trees.');

    }

    public function getAll(User $user): Response
    {
        return ((int) $user->role === 2)
            ? Response::allow()
            : Response::deny('You are not allowed to get all Learning Trees.');

    }

    public function clone(User $user): Response
    {
        return ((int) $user->role === 2)
            ? Response::allow()
            : Response::deny('You are not allowed to clone Learning Trees.');

    }

    public function update(User $user, LearningTree $learningTree): Response
    {
        return ((int) $learningTree->user_id === $user->id || Helper::isAdmin())
            ? Response::allow()
            : Response::deny('You are not allowed to update this Learning Tree.');

    }

    public function updateNode(User $user, LearningTree $learningTree): Response
    {
        return ((int) $learningTree->user_id === $user->id || Helper::isAdmin())
            ? Response::allow()
            : Response::deny('You are not allowed to update this node.');

    }

    public function createLearningTreeFromTemplate(User $user, LearningTree $learningTree): Response
    {

        return ((int) $learningTree->user_id === $user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to create a template from this Learning Tree.');

    }

    public function destroy(User $user, LearningTree $learningTree): Response
    {
        return ((int) $learningTree->user_id === $user->id)
            ? Response::allow()
            : Response::deny('You are not allowed to delete this Learning Tree.');

    }

    public function index(User $user): Response
    {
        return ((int) $user->role === 2)
            ? Response::allow()
            : Response::deny('You are not allowed to view the Learning Trees.');

    }
}
