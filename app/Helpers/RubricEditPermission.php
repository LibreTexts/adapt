<?php

namespace App\Helpers;

use App\Assignment;
use App\Course;
use App\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may edit an assignment question's dynamic rubric, and who may grade with it.
 */
class RubricEditPermission
{
    const GRADER_MESSAGE = "You don't have permission to edit this rubric. Ask your instructor to turn on 'Can edit rubric' for you in Course Properties → Graders.";
    const HEAD_GRADER_MESSAGE = "As head grader, you still need rubric-editing permission. Ask your instructor to turn on 'Can edit rubric' for you in Course Properties → Graders.";
    const NOT_ALLOWED_MESSAGE = "You are not allowed to edit this rubric.";
    const GRADER_SCORING_STYLE_MESSAGE = "Only instructors can change a rubric's scoring style.";
    const GRADER_RUBRIC_SHOWN_MESSAGE = "Only instructors can change whether students see the rubric.";

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return array ['allowed' => bool, 'message' => string]
     */
    public static function check(User $user, Assignment $assignment): array
    {
        $course = $assignment->course;
        if ($course->ownsCourseOrIsCoInstructor($user->id)) {
            return ['allowed' => true, 'message' => ''];
        }
        if ((int)$user->role !== 4 || !self::canGradeAssignment($user, $assignment)) {
            return ['allowed' => false, 'message' => self::NOT_ALLOWED_MESSAGE];
        }
        if (self::isRubricEditor($course, $user->id)) {
            return ['allowed' => true, 'message' => ''];
        }
        return ['allowed' => false,
            'message' => self::isHeadGrader($course, $user->id) ? self::HEAD_GRADER_MESSAGE : self::GRADER_MESSAGE];
    }

    /**
     * Graders with "Can edit rubric" may change the criteria, but not the scoring style or whether students see the rubric.
     * A new rubric from a grader is static and shown to students (the defaults).
     *
     * @param User $user
     * @param Assignment $assignment
     * @param array $state from DynamicRubricEditor::currentState()
     * @param bool $proposed_dynamic
     * @param bool|null $proposed_rubric_shown null: not sent (unchanged)
     * @return string the reason it's refused, or '' when allowed
     */
    public static function graderSettingsError(User $user, Assignment $assignment, array $state, bool $proposed_dynamic, ?bool $proposed_rubric_shown): string
    {
        if ($assignment->course->ownsCourseOrIsCoInstructor($user->id)) {
            return '';
        }
        $current_dynamic = $state['rubric'] ? (bool)$state['is_dynamic'] : false;
        if ($proposed_dynamic !== $current_dynamic) {
            return self::GRADER_SCORING_STYLE_MESSAGE;
        }
        $current_rubric_shown = $state['rubric'] ? (bool)($state['rubric']['rubric_shown'] ?? false) : true;
        if ($proposed_rubric_shown !== null && $proposed_rubric_shown !== $current_rubric_shown) {
            return self::GRADER_RUBRIC_SHOWN_MESSAGE;
        }
        return '';
    }

    /**
     * Instructors (owner or co-instructor) and graders of the course who haven't been blocked from this assignment.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return bool
     */
    public static function canGradeAssignment(User $user, Assignment $assignment): bool
    {
        $course = $assignment->course;
        if ($course->ownsCourseOrIsCoInstructor($user->id)) {
            return true;
        }
        if ((int)$user->role !== 4 || !self::isGraderOfCourse($course, $user->id)) {
            return false;
        }
        $accessible_assignment_ids = $course->accessbileAssignmentsByGrader($user->id);
        return !empty($accessible_assignment_ids[$assignment->id]);
    }

    /**
     * @param Course $course
     * @param int $user_id
     * @return bool
     */
    public static function isGraderOfCourse(Course $course, int $user_id): bool
    {
        return DB::table('graders')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->where('sections.course_id', $course->id)
            ->where('graders.user_id', $user_id)
            ->exists();
    }

    /**
     * @param Course $course
     * @param int $user_id
     * @return bool
     */
    public static function isRubricEditor(Course $course, int $user_id): bool
    {
        return DB::table('rubric_editors')
            ->where('course_id', $course->id)
            ->where('user_id', $user_id)
            ->exists();
    }

    /**
     * @param Course $course
     * @param int $user_id
     * @return bool
     */
    public static function isHeadGrader(Course $course, int $user_id): bool
    {
        return DB::table('head_graders')
            ->where('course_id', $course->id)
            ->where('user_id', $user_id)
            ->exists();
    }
}
