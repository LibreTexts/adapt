<?php

namespace App\Rules;

use App\AssignToTiming;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the existing IsNotOpenOrNoSubmissions pattern (total_points gets
 * this same treatment once submissions exist) - here, an assign-to group's
 * time_limit specifically is locked once a real student has a started timer
 * for it. Dates and the assigned group/section/student set are deliberately
 * NOT covered - those can still be changed freely; doing so just means that
 * group's in-progress students lose their timer state (handled separately
 * in AssignmentProperties::addAssignTos() via the snapshot/migrate logic -
 * an unchanged position's timer state is preserved, a changed one isn't).
 * This rule is what stops time_limit itself from being one of those "changed
 * and therefore wiped" cases, since silently resetting someone's clock
 * mid-attempt is worse than the other config changes.
 */
class TimeLimitCannotBeChangedOnceStarted implements Rule
{
    /**
     * @var int
     */
    private $assignment_id;

    /**
     * @var int
     */
    private $key;

    /**
     * @param int $assignment_id
     * @param int $key the assign_tos array index this field corresponds to
     */
    public function __construct(int $assignment_id, int $key)
    {
        $this->assignment_id = $assignment_id;
        $this->key = $key;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Positional match to the existing assign_to_timings, same
        // assumption the rest of this form already relies on (due_$key,
        // available_from_$key, etc. are matched by index, not a stable id).
        $assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment_id)
            ->orderBy('id')
            ->get()
            ->values()
            ->get($this->key);

        if (!$assign_to_timing) {
            // New group being added - nothing to protect.
            return true;
        }

        $a_real_student_has_started = DB::table('assign_to_timing_starts')
            ->join('users', 'users.id', '=', 'assign_to_timing_starts.user_id')
            ->where('assign_to_timing_starts.assign_to_timing_id', $assign_to_timing->id)
            ->where('users.fake_student', 0)
            ->whereNotNull('assign_to_timing_starts.started_at')
            ->exists();

        if (!$a_real_student_has_started) {
            return true;
        }

        return (string)$assign_to_timing->time_limit === (string)$value;
    }

    /**
     * @return string
     */
    public function message(): string
    {
        return 'The time limit for this assign-to group cannot be changed since at least one student has already started their timer for this assignment.';
    }
}
