<?php

namespace App\Policies;

use App\Assignment;
use App\Helpers\Helper;
use App\Traits\CommonPolicies;
use App\User;
use App\LearningTree;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class LearningTreePolicy
{
    use CommonPolicies;

    /**
     * Authorizes fetching a Learning Tree's assignment-locked snapshot -
     * used by the canvas whenever the tree is loaded inside an assignment
     * (learning_trees_editor.vue's getLearningTreeLearningTreeId()), so
     * students and instructors alike see the structure as it was when
     * assigned, not live edits made since. Deliberately checks that the
     * assignment_question_learning_tree link row exists, rather than
     * comparing the tree's *live* root_node_question_id to the
     * assignment's current questions - that comparison assumes the live
     * tree and the assignment are already in sync, which is exactly what's
     * allowed to be false here (a drifted root node is one of the two
     * things that makes learningTreeNeedsUpdate() true in the first
     * place).
     *
     * @param User $user
     * @param LearningTree $learningTree
     * @param Assignment $assignment
     * @return Response
     */
    public function viewAssignmentSnapshot(User $user, LearningTree $learningTree, Assignment $assignment): Response
    {
        $is_student_in_course = $assignment->course->enrollments->contains('user_id', $user->id);
        $is_instructor_of_course = (int)$assignment->course->user_id === $user->id;

        if (!$is_student_in_course && !$is_instructor_of_course) {
            return Response::deny('You are not allowed to view this Learning Tree.');
        }

        $is_assigned_here = DB::table('assignment_question_learning_tree')
            ->join('assignment_question', 'assignment_question_learning_tree.assignment_question_id', '=', 'assignment_question.id')
            ->where('assignment_question.assignment_id', $assignment->id)
            ->where('assignment_question_learning_tree.learning_tree_id', $learningTree->id)
            ->exists();

        if (!$is_assigned_here) {
            return Response::deny('That Learning Tree is not part of this assignment.');
        }
        return Response::allow();
    }

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
