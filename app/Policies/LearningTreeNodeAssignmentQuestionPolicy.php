<?php

namespace App\Policies;

use App\Assignment;
use App\LearningTree;
use App\LearningTreeNodeAssignmentQuestion;
use App\Question;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LearningTreeNodeAssignmentQuestionPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @param LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion
     * @param int $assignment_id
     * @param int $root_node_question_id
     * @param Question $nodeQuestion
     * @return Response
     */
    public function show(User                               $user,
                         LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion,
                         int                                $assignment_id,
                         LearningTree                       $learningTree,
                         Question                           $nodeQuestion): Response
    {
        $common_learning_node_access = $this->_commonLearningNodeAccess($user, $assignment_id, $learningTree, $nodeQuestion);
        $has_access = $common_learning_node_access['has_access'];
        $message = $common_learning_node_access['message'];
        return $has_access
            ? Response::allow()
            : Response::deny($message);

    }

    /**
     * @param User $user
     * @param LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion
     * @param int $assignment_id
     * @param LearningTree $learningTree
     * @param Question $nodeQuestion
     * @return Response
     */
    public function giveCreditForCompletion(User                               $user,
                                            LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion,
                                            int                                $assignment_id,
                                            LearningTree                       $learningTree,
                                            Question                           $nodeQuestion): Response
    {

        $common_learning_node_access = $this->_commonLearningNodeAccess($user, $assignment_id, $learningTree, $nodeQuestion);
        $has_access = $common_learning_node_access['has_access'];
        $message = $common_learning_node_access['message'];

        if ($nodeQuestion->technology !== 'text' && $nodeQuestion->assessment_type !== 'exposition') {
            $has_access = false;
            $message = "The question should either be text-based or an exposition question.";
        }
        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }

    /**
     * @param User $user
     * @param LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion
     * @param int $assignment_id
     * @param LearningTree $learningTree
     * @param Question $nodeQuestion
     * @return Response
     */
    public function logVisit(User                               $user,
                             LearningTreeNodeAssignmentQuestion $learningTreeNodeAssignmentQuestion,
                             int                                $assignment_id,
                             LearningTree                       $learningTree,
                             Question                           $nodeQuestion): Response
    {

        $common_learning_node_access = $this->_commonLearningNodeAccess($user, $assignment_id, $learningTree, $nodeQuestion);
        $has_access = $common_learning_node_access['has_access'];
        $message = $common_learning_node_access['message'];

        return $has_access
            ? Response::allow()
            : Response::deny($message);
    }


    /**
     * @param User $user
     * @param int $assignment_id
     * @param LearningTree $learningTree
     * @param Question $nodeQuestion
     * @return array
     */
    private function _commonLearningNodeAccess(User $user, int $assignment_id, LearningTree $learningTree, Question $nodeQuestion): array
    {
        $has_access = true;
        $message = '';
        $assignment = Assignment::find($assignment_id);
        $is_student_in_course = $assignment->course->enrollments->contains('user_id', $user->id);
        $is_instructor_of_course = $assignment->course->user_id === $user->id;
        $question_in_assignment = in_array($learningTree->root_node_question_id, $assignment->questions->pluck('id')->toArray());

        if (!$is_student_in_course && !$is_instructor_of_course) {
            $has_access = false;
            $message = "You are not a student in this course.";
        }

        if ($has_access && !$question_in_assignment) {
            $has_access = false;
            $message = "That is not a question in the assignment.";
        }
        if ($has_access && !in_array($nodeQuestion->id, $learningTree->questionIds())) {
            $has_access = false;
            $message = "That is not a question node in the learning tree.";
        }
        return compact('has_access', 'message');
    }
}
