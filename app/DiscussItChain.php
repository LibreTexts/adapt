<?php

namespace App;

use App\Exceptions\Handler;
use App\Helpers\Helper;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Links ("daisy-chains") the same Discuss-it question across assignments in one course.
 *
 * - Every comment in the chain shows in every linked assignment.
 * - Completion is judged per assignment: only comments posted while working in that assignment count
 *   (see discussion_comments.posted_in_assignment_id).
 * - A student keeps the same group across the whole chain.
 * - Every assignment in the chain uses the same question revision.
 * - There is at most one chain per question per course.
 */
class DiscussItChain extends Model
{
    protected $guarded = [];

    /*
    |--------------------------------------------------------------------------
    | Look-ups
    |--------------------------------------------------------------------------
    */

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @return int|null
     */
    public static function chainId(int $assignment_id, int $question_id): ?int
    {
        $chain_id = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->value('discuss_it_chain_id');
        return $chain_id ? (int)$chain_id : null;
    }

    /**
     * @param int $chain_id
     * @return array
     */
    public static function memberAssignmentIds(int $chain_id): array
    {
        return DB::table('assignment_question')
            ->where('discuss_it_chain_id', $chain_id)
            ->pluck('assignment_id')
            ->map(function ($id) {
                return (int)$id;
            })
            ->toArray();
    }

    /**
     * Every assignment whose discussions should show for this assignment/question (the assignment itself if unlinked)
     *
     * @param int $assignment_id
     * @param int $question_id
     * @return array
     */
    public static function linkedAssignmentIds(int $assignment_id, int $question_id): array
    {
        $chain_id = self::chainId($assignment_id, $question_id);
        if (!$chain_id) {
            return [$assignment_id];
        }
        $assignment_ids = self::memberAssignmentIds($chain_id);
        if (!in_array($assignment_id, $assignment_ids)) {
            $assignment_ids[] = $assignment_id;
        }
        return array_values(array_unique($assignment_ids));
    }

    /**
     * @param array $assignment_ids
     * @param int $question_id
     * @return bool
     */
    public static function realStudentCommentsExist(array $assignment_ids, int $question_id): bool
    {
        if (!$assignment_ids) {
            return false;
        }
        return DB::table('discussion_comments')
            ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->join('users', 'discussion_comments.user_id', '=', 'users.id')
            ->whereIn('discussion_comments.posted_in_assignment_id', $assignment_ids)
            ->where('discussions.question_id', $question_id)
            ->where('users.role', 3)
            ->where('users.fake_student', 0)
            ->exists();
    }

    /**
     * Alpha links are mirrored in the tethered Beta courses, so Beta students count too.
     *
     * @param array $assignment_ids
     * @return array
     */
    public static function withBetaAssignmentIds(array $assignment_ids): array
    {
        $beta_assignment_ids = DB::table('beta_assignments')
            ->whereIn('alpha_assignment_id', $assignment_ids)
            ->pluck('id')
            ->map(function ($id) {
                return (int)$id;
            })
            ->toArray();
        return array_values(array_unique(array_merge($assignment_ids, $beta_assignment_ids)));
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @return bool
     */
    public static function chainHasRealStudentComments(int $assignment_id, int $question_id): bool
    {
        $assignment_ids = self::withBetaAssignmentIds(self::linkedAssignmentIds($assignment_id, $question_id));
        return self::realStudentCommentsExist($assignment_ids, $question_id);
    }

    /**
     * Real-student comments that depend on these assignments: comments made in them, and comments made anywhere
     * on threads started in them.  Unlinking would hide these (a reply would lose its thread, or a thread its
     * replies), and removing the question would delete them (see Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion).
     * Comments made in other linked assignments on threads started there don't depend on them.
     *
     * @param array $assignment_ids
     * @param int $question_id
     * @return bool
     */
    public static function realStudentCommentsTiedToAssignments(array $assignment_ids, int $question_id): bool
    {
        if (!$assignment_ids) {
            return false;
        }
        return DB::table('discussion_comments')
            ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->join('users', 'discussion_comments.user_id', '=', 'users.id')
            ->where('discussions.question_id', $question_id)
            ->where('users.role', 3)
            ->where('users.fake_student', 0)
            ->where(function ($query) use ($assignment_ids) {
                $query->whereIn('discussion_comments.posted_in_assignment_id', $assignment_ids)
                    ->orWhereIn('discussions.assignment_id', $assignment_ids);
            })
            ->exists();
    }

    /**
     * Why this assignment's question can't be unlinked, or null if it can.  Only comments that depend on this
     * assignment (or on its Beta copies, which are unlinked along with it) count; students' work in the other
     * linked assignments stays where it is.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @return string|null
     */
    public static function unlinkBlockedReason(int $assignment_id, int $question_id): ?string
    {
        if (self::realStudentCommentsTiedToAssignments([$assignment_id], $question_id)) {
            return 'Students have already commented on this question in this assignment, so it can no longer be unlinked.';
        }
        $beta_assignment_ids = array_values(array_diff(self::withBetaAssignmentIds([$assignment_id]), [$assignment_id]));
        if (self::realStudentCommentsTiedToAssignments($beta_assignment_ids, $question_id)) {
            return 'Students in a tethered Beta course have already commented on this question in their copy of this assignment, so it can no longer be unlinked.';
        }
        return null;
    }

    /**
     * @param array $assignment_ids
     * @return array
     */
    public static function assignmentNamesById(array $assignment_ids): array
    {
        return DB::table('assignments')
            ->whereIn('id', $assignment_ids)
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Linked assignments that are shown and assigned to this student.
     *
     * @param array $assignment_ids
     * @param int $user_id
     * @return array
     */
    public static function assignmentIdsVisibleToStudent(array $assignment_ids, int $user_id): array
    {
        return DB::table('assignments')
            ->join('assign_to_timings', 'assignments.id', '=', 'assign_to_timings.assignment_id')
            ->join('assign_to_users', 'assign_to_timings.id', '=', 'assign_to_users.assign_to_timing_id')
            ->whereIn('assignments.id', $assignment_ids)
            ->where('assignments.shown', 1)
            ->where('assign_to_users.user_id', $user_id)
            ->pluck('assignments.id')
            ->map(function ($id) {
                return (int)$id;
            })
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * What the instructor sees when deciding whether to link.
     *
     * @param Assignment $assignment
     * @param int $question_id
     * @return array
     */
    public static function linkOptions(Assignment $assignment, int $question_id): array
    {
        $chain = self::where('course_id', $assignment->course_id)
            ->where('question_id', $question_id)
            ->first();
        $others = DB::table('assignment_question')
            ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
            ->where('assignments.course_id', $assignment->course_id)
            ->where('assignment_question.question_id', $question_id)
            ->where('assignment_question.assignment_id', '<>', $assignment->id)
            ->whereNotNull('assignment_question.discuss_it_settings')
            ->select('assignments.id', 'assignments.name', 'assignment_question.discuss_it_chain_id')
            ->orderBy('assignments.order')
            ->get();
        $linked_assignments = [];
        $unlinked_assignments = [];
        foreach ($others as $other) {
            $item = ['id' => $other->id, 'name' => $other->name];
            if ($chain && (int)$other->discuss_it_chain_id === $chain->id) {
                $linked_assignments[] = $item;
            } else {
                $unlinked_assignments[] = $item;
            }
        }
        $is_linked = $chain && self::chainId($assignment->id, $question_id) === $chain->id;
        $unlink_blocked_reason = $is_linked ? self::unlinkBlockedReason($assignment->id, $question_id) : null;
        return [
            'is_linked' => $is_linked,
            'chain_exists' => (bool)$chain,
            'linked_assignments' => $linked_assignments,
            'unlinked_assignments' => $unlinked_assignments,
            'can_unlink' => !$unlink_blocked_reason,
            'unlink_blocked_reason' => $unlink_blocked_reason
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Linking and unlinking
    |--------------------------------------------------------------------------
    */

    /**
     * Links the assignments together, joining the course's existing chain for this question if there is one.
     * Put the assignment whose group count should win (if there's no chain yet) first.
     *
     * @param int $course_id
     * @param int $question_id
     * @param array $assignment_ids
     * @param bool $mirror_to_beta
     * @return array
     * @throws Exception
     */
    public static function link(int $course_id, int $question_id, array $assignment_ids, bool $mirror_to_beta = true): array
    {
        $response['type'] = 'error';
        $assignment_ids = array_values(array_unique(array_map('intval', $assignment_ids)));
        $valid_assignment_ids = DB::table('assignment_question')
            ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
            ->where('assignments.course_id', $course_id)
            ->where('assignment_question.question_id', $question_id)
            ->whereNotNull('assignment_question.discuss_it_settings')
            ->whereIn('assignment_question.assignment_id', $assignment_ids)
            ->pluck('assignment_question.assignment_id')
            ->map(function ($id) {
                return (int)$id;
            })
            ->toArray();
        if (array_diff($assignment_ids, $valid_assignment_ids)) {
            $response['message'] = "Only assignments in this course that contain this Discuss-it question can be linked.";
            return $response;
        }

        $chain = self::where('course_id', $course_id)->where('question_id', $question_id)->first();
        $current_member_ids = $chain ? self::memberAssignmentIds($chain->id) : [];
        $new_member_ids = array_values(array_diff($assignment_ids, $current_member_ids));
        if (!$new_member_ids) {
            $response['type'] = 'success';
            $response['message'] = 'This question is already linked in these assignments.';
            return $response;
        }
        if (count(array_unique(array_merge($current_member_ids, $new_member_ids))) < 2) {
            $response['message'] = 'Please choose at least one other assignment to link this question in.';
            return $response;
        }

        //linked assignments must share the number of groups and the question revision
        $alignments = [];
        foreach (self::_sharedSettings() as $shared_setting) {
            $reference_info = self::_referenceValue($current_member_ids, $new_member_ids, $question_id, $shared_setting);
            if ($reference_info['type'] === 'error') {
                $response['message'] = $reference_info['message'];
                return $response;
            }
            $reference_value = $reference_info['value'];
            $cannot_change = [];
            foreach ($new_member_ids as $new_member_id) {
                if ($shared_setting['get']($new_member_id, $question_id) !== $reference_value) {
                    if (self::realStudentCommentsExist(self::withBetaAssignmentIds([$new_member_id]), $question_id)) {
                        $cannot_change[] = $new_member_id;
                    } else {
                        $alignments[] = [$shared_setting['set'], $new_member_id, $reference_value, $shared_setting['label']];
                    }
                }
            }
            if ($cannot_change) {
                $names = implode(', ', self::assignmentNamesById($cannot_change));
                $described_value = $shared_setting['describe']($reference_value);
                $response['message'] = "Linked assignments must use the same {$shared_setting['label']} ($described_value). Students have already commented in $names, so it cannot be changed there.";
                return $response;
            }
        }

        //a student keeps one group across the chain; assignments whose groups are about to be reseeded start over anyway
        $reseeded_ids = [];
        foreach ($alignments as $alignment) {
            if ($alignment[3] === 'number of groups') {
                $reseeded_ids[] = $alignment[1];
            }
        }
        $ordered_ids = array_values(array_diff(array_merge($current_member_ids, $new_member_ids), $reseeded_ids));
        $student_groups_info = self::_reconcileStudentGroups($ordered_ids, $question_id);
        if ($student_groups_info['type'] === 'error') {
            $response['message'] = $student_groups_info['message'];
            return $response;
        }

        if (!$chain) {
            $chain = self::create(['course_id' => $course_id, 'question_id' => $question_id]);
        }
        foreach ($alignments as $alignment) {
            [$set, $new_member_id, $reference_value] = $alignment;
            $set($new_member_id, $question_id, $reference_value);
        }
        foreach ($student_groups_info['group_by_discussion_group_id'] as $discussion_group_id => $group) {
            DB::table('discussion_groups')
                ->where('id', $discussion_group_id)
                ->update(['group' => $group, 'updated_at' => now()]);
        }
        foreach ($new_member_ids as $new_member_id) {
            self::seedGroupsIfMissing($new_member_id, $question_id);
        }
        DB::table('assignment_question')
            ->where('question_id', $question_id)
            ->whereIn('assignment_id', $new_member_ids)
            ->update(['discuss_it_chain_id' => $chain->id, 'updated_at' => now()]);

        if ($mirror_to_beta) {
            self::mirrorToBetaCourses($course_id, $question_id);
        }
        $response['type'] = 'success';
        $response['message'] = 'This question has been linked in the chosen assignments.';
        return $response;
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param bool $mirror_to_beta
     * @return array
     * @throws Exception
     */
    public static function unlink(int $assignment_id, int $question_id, bool $mirror_to_beta = true): array
    {
        $response['type'] = 'error';
        if (!self::chainId($assignment_id, $question_id)) {
            $response['message'] = 'This question is not linked to any other assignment.';
            return $response;
        }
        //checked now, not when the settings window was opened, so a comment made in the meantime still blocks it
        $unlink_blocked_reason = self::unlinkBlockedReason($assignment_id, $question_id);
        if ($unlink_blocked_reason) {
            $response['message'] = $unlink_blocked_reason;
            return $response;
        }
        $course_id = Assignment::find($assignment_id)->course_id;
        self::detach($assignment_id, $question_id);
        if ($mirror_to_beta) {
            self::mirrorToBetaCourses($course_id, $question_id);
        }
        $response['type'] = 'success';
        $response['message'] = 'This question has been unlinked from the other assignments.';
        return $response;
    }

    /**
     * Removes the assignment from its chain without any checks (callers check for student comments first).
     * A chain left with a single assignment is dissolved.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @return void
     */
    public static function detach(int $assignment_id, int $question_id): void
    {
        $chain_id = self::chainId($assignment_id, $question_id);
        if (!$chain_id) {
            return;
        }
        DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->update(['discuss_it_chain_id' => null]);
        self::_dissolveIfTooSmall($chain_id);
    }

    /**
     * Used when an assignment is deleted.
     *
     * @param int $assignment_id
     * @return void
     */
    public static function detachAssignment(int $assignment_id): void
    {
        $question_ids = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->whereNotNull('discuss_it_chain_id')
            ->pluck('question_id');
        foreach ($question_ids as $question_id) {
            self::detach($assignment_id, (int)$question_id);
        }
    }

    /**
     * Real-student comments on this assignment's linked Discuss-it questions that deleting the assignment would
     * remove: those made in this assignment, and those made in any linked assignment on threads started in this
     * one (see Discussion::deleteByAssignment).  Comments made elsewhere on threads started elsewhere are kept.
     *
     * If any of these comments count toward a score (a Discuss-it submission for that student, question and the
     * assignment the comment was made in), the assignment can't be deleted.
     *
     * @param int $assignment_id
     * @return array
     */
    public static function commentsRemovedByDeletingAssignment(int $assignment_id): array
    {
        $result = ['number_of_comments' => 0,
            'number_of_students' => 0,
            'student_emails' => [],
            'assignment_names' => [],
            'scored_assignment_names' => []];
        $question_ids = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->whereNotNull('discuss_it_chain_id')
            ->pluck('question_id')
            ->toArray();
        if (!$question_ids) {
            return $result;
        }
        $comments = DB::table('discussion_comments')
            ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->join('users', 'discussion_comments.user_id', '=', 'users.id')
            ->whereIn('discussions.question_id', $question_ids)
            ->where('users.role', 3)
            ->where('users.fake_student', 0)
            ->where(function ($query) use ($assignment_id) {
                $query->where('discussion_comments.posted_in_assignment_id', $assignment_id)
                    ->orWhere('discussions.assignment_id', $assignment_id);
            })
            ->select('discussion_comments.user_id',
                'discussion_comments.posted_in_assignment_id',
                'discussions.question_id',
                'users.email')
            ->get();
        if ($comments->isEmpty()) {
            return $result;
        }
        $result['number_of_comments'] = $comments->count();
        $result['number_of_students'] = $comments->pluck('user_id')->unique()->count();
        $result['student_emails'] = $comments->pluck('email')->unique()->sort()->values()->toArray();
        $assignment_ids = $comments->pluck('posted_in_assignment_id')->unique()->values()->toArray();
        $names_by_id = self::assignmentNamesById($assignment_ids);
        $result['assignment_names'] = array_values(array_intersect_key($names_by_id, array_flip($assignment_ids)));

        //any score: a Discuss-it submission (automatic completion credit or instructor grading)
        $scored_keys = DB::table('submission_files')
            ->whereIn('assignment_id', $assignment_ids)
            ->whereIn('question_id', $comments->pluck('question_id')->unique()->toArray())
            ->whereIn('user_id', $comments->pluck('user_id')->unique()->toArray())
            ->get(['assignment_id', 'question_id', 'user_id'])
            ->map(function ($submission_file) {
                return "$submission_file->assignment_id-$submission_file->question_id-$submission_file->user_id";
            })
            ->flip();
        $scored_assignment_ids = [];
        foreach ($comments as $comment) {
            if (isset($scored_keys["$comment->posted_in_assignment_id-$comment->question_id-$comment->user_id"])) {
                $scored_assignment_ids[$comment->posted_in_assignment_id] = true;
            }
        }
        $result['scored_assignment_names'] = array_values(array_intersect_key($names_by_id, $scored_assignment_ids));
        return $result;
    }

    /**
     * @param int $course_id
     * @return void
     */
    public static function deleteByCourse(int $course_id): void
    {
        $chain_ids = self::where('course_id', $course_id)->pluck('id')->toArray();
        if (!$chain_ids) {
            return;
        }
        DB::table('assignment_question')
            ->whereIn('discuss_it_chain_id', $chain_ids)
            ->update(['discuss_it_chain_id' => null]);
        self::whereIn('id', $chain_ids)->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Groups
    |--------------------------------------------------------------------------
    */

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @return int
     */
    public static function numberOfGroups(int $assignment_id, int $question_id): int
    {
        $discuss_it_settings = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->value('discuss_it_settings');
        $discuss_it_settings = $discuss_it_settings ? json_decode($discuss_it_settings) : null;
        return $discuss_it_settings && isset($discuss_it_settings->number_of_groups) && +$discuss_it_settings->number_of_groups
            ? (int)$discuss_it_settings->number_of_groups
            : 1;
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $number_of_groups
     * @return void
     */
    public static function setNumberOfGroups(int $assignment_id, int $question_id, int $number_of_groups): void
    {
        $discuss_it_settings = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->value('discuss_it_settings');
        $discuss_it_settings = $discuss_it_settings ? json_decode($discuss_it_settings, true) : [];
        $discuss_it_settings['number_of_groups'] = $number_of_groups;
        DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->update(['discuss_it_settings' => json_encode($discuss_it_settings)]);
        $instructor_user_id = Assignment::find($assignment_id)->course->user_id;
        (new DiscussionGroup())->seedGroups($assignment_id, $question_id, $number_of_groups, $instructor_user_id);
    }

    /**
     * A copied course has no students yet, so every Discuss-it question starts with a single group.
     * The instructor can add groups in the new course.
     *
     * @param int $course_id
     * @return void
     */
    public static function resetToOneGroupForCourse(int $course_id): void
    {
        $assignment_questions = DB::table('assignment_question')
            ->join('assignments', 'assignment_question.assignment_id', '=', 'assignments.id')
            ->where('assignments.course_id', $course_id)
            ->whereNotNull('assignment_question.discuss_it_settings')
            ->select('assignment_question.id', 'assignment_question.discuss_it_settings')
            ->get();
        foreach ($assignment_questions as $assignment_question) {
            $discuss_it_settings = json_decode($assignment_question->discuss_it_settings, true);
            if (!is_array($discuss_it_settings) || (isset($discuss_it_settings['number_of_groups']) && +$discuss_it_settings['number_of_groups'] === 1)) {
                continue;
            }
            $discuss_it_settings['number_of_groups'] = 1;
            DB::table('assignment_question')
                ->where('id', $assignment_question->id)
                ->update(['discuss_it_settings' => json_encode($discuss_it_settings)]);
        }
    }

    /**
     * Makes sure every group exists for this assignment question so that students are spread across the groups.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @return void
     */
    public static function seedGroupsIfMissing(int $assignment_id, int $question_id): void
    {
        $seeds_exist = DB::table('discussion_groups')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->exists();
        if (!$seeds_exist) {
            $instructor_user_id = Assignment::find($assignment_id)->course->user_id;
            (new DiscussionGroup())->seedGroups($assignment_id, $question_id, self::numberOfGroups($assignment_id, $question_id), $instructor_user_id);
        }
    }

    /**
     * Each student must end up in one group across the linked assignments.  Where a student has different groups:
     * - if they commented (as a real student) in only one of those groups, that group wins;
     * - if they haven't commented, the group from the earliest assignment in $ordered_assignment_ids wins;
     * - if they commented under two different groups, linking would hide some of their comments from them, so it's refused.
     *
     * @param array $ordered_assignment_ids existing members first
     * @param int $question_id
     * @return array
     */
    private static function _reconcileStudentGroups(array $ordered_assignment_ids, int $question_id): array
    {
        $response = ['type' => 'success', 'group_by_discussion_group_id' => []];
        if (count($ordered_assignment_ids) < 2) {
            return $response;
        }
        $student_group_rows = DB::table('discussion_groups')
            ->join('users', 'discussion_groups.user_id', '=', 'users.id')
            ->whereIn('discussion_groups.assignment_id', $ordered_assignment_ids)
            ->where('discussion_groups.question_id', $question_id)
            ->where('users.role', 3)
            ->select('discussion_groups.id', 'discussion_groups.assignment_id', 'discussion_groups.user_id',
                'discussion_groups.group', 'users.fake_student')
            ->orderBy('discussion_groups.id')
            ->get();
        $rows_by_user_id = [];
        foreach ($student_group_rows as $row) {
            $rows_by_user_id[$row->user_id][] = $row;
        }
        $assignment_order = array_flip($ordered_assignment_ids);
        $students_who_cannot_be_moved = 0;
        foreach ($rows_by_user_id as $user_id => $rows) {
            if (count(array_unique(array_map(function ($row) {
                    return (int)$row->group;
                }, $rows))) < 2) {
                continue;
            }
            //groups where a real student has already commented can't change
            $locked_groups = [];
            if (!$rows[0]->fake_student) {
                $commented_assignment_ids = DB::table('discussion_comments')
                    ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
                    ->where('discussion_comments.user_id', $user_id)
                    ->where('discussions.question_id', $question_id)
                    ->whereIn('discussion_comments.posted_in_assignment_id', $ordered_assignment_ids)
                    ->pluck('discussion_comments.posted_in_assignment_id')
                    ->map(function ($id) {
                        return (int)$id;
                    })
                    ->unique()
                    ->toArray();
                foreach ($rows as $row) {
                    if (in_array((int)$row->assignment_id, $commented_assignment_ids)) {
                        $locked_groups[] = (int)$row->group;
                    }
                }
                $locked_groups = array_values(array_unique($locked_groups));
            }
            if (count($locked_groups) > 1) {
                $students_who_cannot_be_moved++;
                continue;
            }
            if ($locked_groups) {
                $target_group = $locked_groups[0];
            } else {
                usort($rows, function ($a, $b) use ($assignment_order) {
                    return [$assignment_order[(int)$a->assignment_id], $a->id] <=> [$assignment_order[(int)$b->assignment_id], $b->id];
                });
                $target_group = (int)$rows[0]->group;
            }
            foreach ($rows as $row) {
                if ((int)$row->group !== $target_group) {
                    $response['group_by_discussion_group_id'][$row->id] = $target_group;
                }
            }
        }
        if ($students_who_cannot_be_moved) {
            $students = $students_who_cannot_be_moved === 1
                ? '1 student is in different groups in these assignments and has'
                : "$students_who_cannot_be_moved students are in different groups in these assignments and have";
            return ['type' => 'error',
                'message' => "$students already commented in both groups. Linking would hide some of their comments from them, so these assignments can't be linked."];
        }
        return $response;
    }

    /**
     * Settings every assignment in a chain must share.
     *
     * @return array[]
     */
    private static function _sharedSettings(): array
    {
        return [
            ['label' => 'number of groups',
                'get' => function (int $assignment_id, int $question_id) {
                    return self::numberOfGroups($assignment_id, $question_id);
                },
                'set' => function (int $assignment_id, int $question_id, $value) {
                    self::setNumberOfGroups($assignment_id, $question_id, (int)$value);
                },
                'describe' => function ($value) {
                    return (string)$value;
                }],
            ['label' => 'question revision',
                'get' => function (int $assignment_id, int $question_id) {
                    return self::questionRevisionId($assignment_id, $question_id);
                },
                'set' => function (int $assignment_id, int $question_id, $value) {
                    self::setQuestionRevisionId($assignment_id, $question_id, $value);
                },
                'describe' => function ($value) {
                    return self::describeQuestionRevision($value);
                }]
        ];
    }

    /**
     * The value the chain uses: an existing member's, else that of a new member whose students already commented,
     * else the first new member's.
     *
     * @param array $current_member_ids
     * @param array $new_member_ids
     * @param int $question_id
     * @param array $shared_setting
     * @return array
     */
    private static function _referenceValue(array $current_member_ids, array $new_member_ids, int $question_id, array $shared_setting): array
    {
        if ($current_member_ids) {
            return ['type' => 'success', 'value' => $shared_setting['get']($current_member_ids[0], $question_id)];
        }
        $values_with_comments = [];
        foreach ($new_member_ids as $new_member_id) {
            if (self::realStudentCommentsExist(self::withBetaAssignmentIds([$new_member_id]), $question_id)) {
                $values_with_comments[$new_member_id] = $shared_setting['get']($new_member_id, $question_id);
            }
        }
        if (count(array_unique(array_map('strval', $values_with_comments))) > 1) {
            $names = implode(', ', self::assignmentNamesById(array_keys($values_with_comments)));
            return ['type' => 'error',
                'message' => "Linked assignments must use the same {$shared_setting['label']}. Students have already commented in $names, which use different ones."];
        }
        $value = $values_with_comments
            ? array_values($values_with_comments)[0]
            : $shared_setting['get']($new_member_ids[0], $question_id);
        return ['type' => 'success', 'value' => $value];
    }

    /*
    |--------------------------------------------------------------------------
    | Question revisions
    |--------------------------------------------------------------------------
    */

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @return int|null
     */
    public static function questionRevisionId(int $assignment_id, int $question_id): ?int
    {
        $question_revision_id = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->value('question_revision_id');
        return $question_revision_id ? (int)$question_revision_id : null;
    }

    /**
     * Moves a (not yet commented on by students) assignment question to another revision,
     * clearing its submissions the same way "update to latest revision" does.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param int|null $question_revision_id
     * @return void
     */
    public static function setQuestionRevisionId(int $assignment_id, int $question_id, ?int $question_revision_id): void
    {
        DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->update(['question_revision_id' => $question_revision_id]);
        DB::table('pending_question_revisions')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('question_revision_id', $question_revision_id)
            ->delete();
        Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment_id, $question_id);
    }

    /**
     * @param int|null $question_revision_id
     * @return string
     */
    public static function describeQuestionRevision(?int $question_revision_id): string
    {
        if (!$question_revision_id) {
            return 'the original version';
        }
        $revision_number = DB::table('question_revisions')
            ->where('id', $question_revision_id)
            ->value('revision_number');
        return $revision_number ? "revision $revision_number" : "revision id $question_revision_id";
    }

    /**
     * @param int $chain_id
     * @return void
     */
    private static function _dissolveIfTooSmall(int $chain_id): void
    {
        $remaining_member_ids = self::memberAssignmentIds($chain_id);
        if (count($remaining_member_ids) < 2) {
            DB::table('assignment_question')
                ->where('discuss_it_chain_id', $chain_id)
                ->update(['discuss_it_chain_id' => null]);
            self::where('id', $chain_id)->delete();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Alpha/Beta courses and copies
    |--------------------------------------------------------------------------
    */

    /**
     * Beta courses get the same links as their Alpha course (with their own students, groups and comments).
     * Only Beta assignments that already contain the question are linked; the rest are linked when the
     * question is approved into them (see syncBetaAssignmentQuestion).
     *
     * @param int $alpha_course_id
     * @param int $question_id
     * @return void
     * @throws Exception
     */
    public static function mirrorToBetaCourses(int $alpha_course_id, int $question_id): void
    {
        $beta_course_ids = DB::table('beta_courses')
            ->where('alpha_course_id', $alpha_course_id)
            ->pluck('id');
        foreach ($beta_course_ids as $beta_course_id) {
            self::_mirrorToBetaCourse($alpha_course_id, (int)$beta_course_id, $question_id);
        }
    }

    /**
     * Called after a question lands in a Beta assignment.
     *
     * @param Assignment $beta_assignment
     * @param int $question_id
     * @return void
     * @throws Exception
     */
    public static function syncBetaAssignmentQuestion(Assignment $beta_assignment, int $question_id): void
    {
        $alpha_course_id = DB::table('beta_courses')
            ->where('id', $beta_assignment->course_id)
            ->value('alpha_course_id');
        if ($alpha_course_id) {
            self::_mirrorToBetaCourse((int)$alpha_course_id, (int)$beta_assignment->course_id, $question_id);
        }
    }

    /**
     * @param int $alpha_course_id
     * @param int $beta_course_id
     * @param int $question_id
     * @return void
     * @throws Exception
     */
    private static function _mirrorToBetaCourse(int $alpha_course_id, int $beta_course_id, int $question_id): void
    {
        $alpha_chain = self::where('course_id', $alpha_course_id)->where('question_id', $question_id)->first();
        $alpha_member_ids = $alpha_chain ? self::memberAssignmentIds($alpha_chain->id) : [];
        $desired_beta_ids = $alpha_member_ids
            ? DB::table('beta_assignments')
                ->join('assignments', 'beta_assignments.id', '=', 'assignments.id')
                ->join('assignment_question', 'beta_assignments.id', '=', 'assignment_question.assignment_id')
                ->where('assignments.course_id', $beta_course_id)
                ->where('assignment_question.question_id', $question_id)
                ->whereNotNull('assignment_question.discuss_it_settings')
                ->whereIn('beta_assignments.alpha_assignment_id', $alpha_member_ids)
                ->pluck('beta_assignments.id')
                ->map(function ($id) {
                    return (int)$id;
                })
                ->toArray()
            : [];

        $beta_chain = self::where('course_id', $beta_course_id)->where('question_id', $question_id)->first();
        $current_beta_ids = $beta_chain ? self::memberAssignmentIds($beta_chain->id) : [];

        foreach (array_diff($current_beta_ids, $desired_beta_ids) as $beta_assignment_id) {
            self::detach($beta_assignment_id, $question_id);
        }
        if (count($desired_beta_ids) >= 2) {
            //order so that an existing member's group count wins
            $ordered = array_values(array_unique(array_merge(array_intersect($current_beta_ids, $desired_beta_ids), $desired_beta_ids)));
            $link_response = self::link($beta_course_id, $question_id, $ordered, false);
            if ($link_response['type'] === 'error') {
                //Beta students already took part in a way that can't be reconciled (groups, revision);
                //leave this Beta course unlinked rather than hide students' comments from them
                $h = new Handler(app());
                $h->report(new Exception("Could not mirror the Discuss-it link for question $question_id into Beta course $beta_course_id: {$link_response['message']}"));
            }
        }
    }

    /**
     * @param int $course_id
     * @param int $question_id
     * @param array $assignment_ids
     * @return void
     */
    private static function _forceLink(int $course_id, int $question_id, array $assignment_ids): void
    {
        $chain = self::firstOrCreate(['course_id' => $course_id, 'question_id' => $question_id]);
        DB::table('assignment_question')
            ->where('question_id', $question_id)
            ->whereIn('assignment_id', $assignment_ids)
            ->update(['discuss_it_chain_id' => $chain->id, 'updated_at' => now()]);
    }

    /**
     * Rebuilds the chains of a copied/imported course inside the new course (no students, groups or comments).
     *
     * @param array $new_assignment_id_by_old_assignment_id
     * @param int $new_course_id
     * @return void
     */
    public static function copyChainsToCourse(array $new_assignment_id_by_old_assignment_id, int $new_course_id): void
    {
        if (!$new_assignment_id_by_old_assignment_id) {
            return;
        }
        $chained_assignment_questions = DB::table('assignment_question')
            ->whereIn('assignment_id', array_keys($new_assignment_id_by_old_assignment_id))
            ->whereNotNull('discuss_it_chain_id')
            ->select('assignment_id', 'question_id', 'discuss_it_chain_id')
            ->get();
        $old_assignment_ids_by_chain = [];
        foreach ($chained_assignment_questions as $chained_assignment_question) {
            $old_assignment_ids_by_chain[$chained_assignment_question->discuss_it_chain_id]['question_id'] = (int)$chained_assignment_question->question_id;
            $old_assignment_ids_by_chain[$chained_assignment_question->discuss_it_chain_id]['assignment_ids'][] = (int)$chained_assignment_question->assignment_id;
        }
        foreach ($old_assignment_ids_by_chain as $info) {
            $question_id = $info['question_id'];
            $new_assignment_ids = [];
            foreach ($info['assignment_ids'] as $old_assignment_id) {
                $new_assignment_ids[] = $new_assignment_id_by_old_assignment_id[$old_assignment_id];
            }
            //the question may have been left out of a copy (for example, drafts or open-ended real time questions)
            $new_assignment_ids = DB::table('assignment_question')
                ->whereIn('assignment_id', $new_assignment_ids)
                ->where('question_id', $question_id)
                ->pluck('assignment_id')
                ->toArray();
            if (count($new_assignment_ids) >= 2) {
                self::_forceLink($new_course_id, $question_id, $new_assignment_ids);
            }
        }
    }
}
