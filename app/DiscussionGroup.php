<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DiscussionGroup extends Model
{
    protected $guarded = [];

    /**
     * Returns the student's group, assigning one if needed.  When the question is linked across assignments
     * (see DiscussItChain), the student keeps the same group in every linked assignment.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param int $user_id
     * @return mixed
     */
    public function store(int $assignment_id, int $question_id, int $user_id)
    {
        $linked_assignment_ids = DiscussItChain::linkedAssignmentIds($assignment_id, $question_id);
        $discussion_group = DB::table('discussion_groups')
            ->whereIn('assignment_id', $linked_assignment_ids)
            ->where('question_id', $question_id)
            ->where('user_id', $user_id)
            ->orderBy('id')
            ->first();
        if (!$discussion_group) {
            $group = DiscussionGroup::select('group')
                ->whereIn('assignment_id', $linked_assignment_ids)
                ->where('question_id', $question_id)
                ->groupBy('group')
                ->orderByRaw('COUNT(*) ASC')
                ->limit(1)
                ->value('group');
            if (!$group) {
                $group = 1;
            }
            $discussion_group = new DiscussionGroup();
            $discussion_group->assignment_id = $assignment_id;
            $discussion_group->question_id = $question_id;
            $discussion_group->user_id = $user_id;
            $discussion_group->group = $group;
            $discussion_group->save();
        } else {
            $group = $discussion_group->group;
        }
        return $group;
    }

    /**
     * One row per group under the instructor so that every group shows up when finding the least populated group.
     *
     * @param int $assignment_id
     * @param int $question_id
     * @param int $number_of_groups
     * @param int $instructor_user_id
     * @return void
     */
    public function seedGroups(int $assignment_id, int $question_id, int $number_of_groups, int $instructor_user_id): void
    {
        DB::table('discussion_groups')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->delete();

        for ($i = 1; $i <= $number_of_groups; $i++) {
            DiscussionGroup::create(['assignment_id' => $assignment_id,
                'question_id' => $question_id,
                'user_id' => $instructor_user_id,
                'group' => $i]);
        }
    }

}
