<?php

namespace App\Helpers;

use App\Assignment;
use App\Course;
use App\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may edit an assignment question's rubric, what else they may change, and who may grade with it.
 */
class RubricEditPermission
{
    const GRADER_MESSAGE = "You don't have permission to edit this rubric. Ask your instructor to turn on 'Can edit rubric' for you in Course Properties → Graders.";
    const HEAD_GRADER_MESSAGE = "As head grader, you still need rubric-editing permission. Ask your instructor to turn on 'Can edit rubric' for you in Course Properties → Graders.";
    const NOT_ALLOWED_MESSAGE = "You are not allowed to edit this rubric.";
    const GRADER_SCORING_STYLE_MESSAGE = "You don't have permission to change a rubric's scoring style. Ask your instructor to allow it in Course Properties → Graders.";
    const GRADER_RUBRIC_SHOWN_MESSAGE = "You don't have permission to change whether students see the rubric. Ask your instructor to allow it in Course Properties → Graders.";
    const GRADER_STATIC_SCORES_MESSAGE = "Students have already been graded with this rubric, and changing its criteria would delete their rubric point breakdowns. Ask your instructor to turn on 'Edit graded static rubrics' for you in Course Properties → Graders.";

    //optional abilities on top of "Can edit rubric"; each is a boolean column on rubric_editors
    const PERMISSION_COLUMNS = ['can_change_scoring_style',
        'can_convert_graded_rubrics',
        'can_change_rubric_shown',
        'can_edit_graded_static_rubrics'];

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
     * What this user may do beyond editing the criteria. Instructors may do everything; a grader needs
     * "Can edit rubric" plus each option. Converting a graded rubric includes choosing the scoring style.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return array column => bool, for every column in PERMISSION_COLUMNS
     */
    public static function permissions(User $user, Assignment $assignment): array
    {
        $course = $assignment->course;
        if ($course->ownsCourseOrIsCoInstructor($user->id)) {
            return array_fill_keys(self::PERMISSION_COLUMNS, true);
        }
        $permissions = array_fill_keys(self::PERMISSION_COLUMNS, false);
        if (!self::check($user, $assignment)['allowed']) {
            return $permissions;
        }
        $rubric_editor = DB::table('rubric_editors')
            ->where('course_id', $course->id)
            ->where('user_id', $user->id)
            ->first();
        foreach (self::PERMISSION_COLUMNS as $column) {
            $permissions[$column] = (bool)($rubric_editor->$column ?? false);
        }
        $permissions['can_change_scoring_style'] = $permissions['can_change_scoring_style']
            || $permissions['can_convert_graded_rubrics'];
        return $permissions;
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return bool
     */
    public static function canConvertGradedRubrics(User $user, Assignment $assignment): bool
    {
        return self::permissions($user, $assignment)['can_convert_graded_rubrics'];
    }

    /**
     * The scoring style of rubrics this grader adds when they can't choose it. Set by the instructor.
     *
     * @param User $user
     * @param Assignment $assignment
     * @return string static|dynamic
     */
    public static function defaultScoringStyle(User $user, Assignment $assignment): string
    {
        $default_scoring_style = DB::table('rubric_editors')
            ->where('course_id', $assignment->course->id)
            ->where('user_id', $user->id)
            ->value('default_scoring_style');
        return $default_scoring_style === 'dynamic' ? 'dynamic' : 'static';
    }

    /**
     * @param User $user
     * @param Assignment $assignment
     * @return bool
     */
    public static function canEditGradedStaticRubrics(User $user, Assignment $assignment): bool
    {
        return self::permissions($user, $assignment)['can_edit_graded_static_rubrics'];
    }

    /**
     * Every grader's saved options in this course, for Course Properties → Graders.
     *
     * @param Course $course
     * @return array user id => [column => bool]
     */
    public static function courseEditorPermissions(Course $course): array
    {
        $permissions_by_user_id = [];
        foreach (DB::table('rubric_editors')->where('course_id', $course->id)->get() as $rubric_editor) {
            $permissions = [];
            foreach (self::PERMISSION_COLUMNS as $column) {
                $permissions[$column] = (bool)$rubric_editor->$column;
            }
            $permissions['default_scoring_style'] = $rubric_editor->default_scoring_style === 'dynamic' ? 'dynamic' : 'static';
            $permissions_by_user_id[(int)$rubric_editor->user_id] = $permissions;
        }
        return $permissions_by_user_id;
    }

    /**
     * Keeps only the known options from a request, so nothing else can be written to the table.
     *
     * @param mixed $requested column => value
     * @return array column => 0|1
     */
    public static function permissionColumnValues($requested): array
    {
        if (!is_array($requested)) {
            return [];
        }
        $values = [];
        foreach (self::PERMISSION_COLUMNS as $column) {
            if (array_key_exists($column, $requested)) {
                $values[$column] = filter_var($requested[$column], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        return $values;
    }

    /**
     * Graders with "Can edit rubric" may change the criteria. Changing the scoring style or whether students see
     * the rubric takes the matching option. A grader who can't choose the style adds rubrics in the style the
     * instructor set for them.
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
        $permissions = self::permissions($user, $assignment);
        $current_dynamic = $state['rubric'] ? (bool)$state['is_dynamic'] : false;
        if (!$permissions['can_change_scoring_style']) {
            $required_dynamic = $state['rubric']
                ? $current_dynamic
                : self::defaultScoringStyle($user, $assignment) === 'dynamic';
            if ($proposed_dynamic !== $required_dynamic) {
                return self::GRADER_SCORING_STYLE_MESSAGE;
            }
        }
        $current_rubric_shown = $state['rubric'] ? (bool)($state['rubric']['rubric_shown'] ?? false) : true;
        if ($proposed_rubric_shown !== null && $proposed_rubric_shown !== $current_rubric_shown
            && !$permissions['can_change_rubric_shown']) {
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
