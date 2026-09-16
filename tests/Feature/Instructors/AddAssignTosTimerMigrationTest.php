<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\Section;
use App\Traits\AssignmentProperties;
use App\Traits\DateFormatter;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

/**
 * Covers the snapshot/migrate logic inside
 * AssignmentProperties::addAssignTos() that carries a student's in-progress
 * timer (an assign_to_timing_starts row) forward onto the new
 * assign_to_timing row when a position's dates/time_limit/assigned groups
 * are unchanged, and wipes it when they change. This had no test coverage
 * at all, and is exactly the responsibility the 2026_09_17 "remove cascade"
 * migration moved from the database onto this method.
 *
 * Calls addAssignTos() directly (via an anonymous class using the trait)
 * rather than through the full PATCH /api/assignments/{id} endpoint, to
 * exercise this logic in isolation from StoreAssignmentProperties' unrelated
 * validation. The instructor's time_zone is fixed to UTC so the
 * available_from/due strings below round-trip through formatDateFromRequest()
 * unchanged, keeping the "unchanged position" comparison meaningful.
 */
class AddAssignTosTimerMigrationTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create(['time_zone' => 'UTC']);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->student_user = factory(User::class)->create(['role' => 3]);
        Enrollment::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->student_user->id,
        ]);

        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'late_policy' => 'not accepted',
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assign_to_timing->available_from = '2026-01-01 09:00:00';
        $this->assign_to_timing->due = '2026-06-01 09:00:00';
        $this->assign_to_timing->time_limit = '10 minutes';
        $this->assign_to_timing->save();

        DB::table('assign_to_timing_starts')->insert([
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $this->student_user->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->harness = new class {
            use AssignmentProperties, DateFormatter;
        };

        // Matches the position above exactly - dates, time_limit, and group
        // are all identical to what's already stored.
        $this->unchanged_assign_to = [
            'available_from_date' => '2026-01-01',
            'available_from_time' => '9:00 AM',
            'due_date' => '2026-06-01',
            'due_time' => '9:00 AM',
            'final_submission_deadline_date' => null,
            'final_submission_deadline_time' => null,
            'time_limit' => '10 minutes',
            'groups' => [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']],
        ];
    }

    /** @test */
    public function resaving_a_position_unchanged_carries_the_running_clock_forward()
    {
        $this->harness->addAssignTos($this->assignment, [$this->unchanged_assign_to], $this->section, $this->user);

        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        // Rows are always rebuilt positionally, even when nothing changed.
        $this->assertNotEquals($this->assign_to_timing->id, $new_assign_to_timing->id);

        $this->assertDatabaseHas('assign_to_timing_starts', [
            'assign_to_timing_id' => $new_assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
        ]);
    }

    /** @test */
    public function changing_the_due_date_wipes_the_running_clock()
    {
        $changed = $this->unchanged_assign_to;
        $changed['due_date'] = Carbon::parse($changed['due_date'])->addDay()->format('Y-m-d');

        $this->harness->addAssignTos($this->assignment, [$changed], $this->section, $this->user);

        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $new_assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $this->assign_to_timing->id,
        ]);
    }

    /** @test */
    public function changing_the_time_limit_wipes_the_running_clock()
    {
        $changed = $this->unchanged_assign_to;
        $changed['time_limit'] = '30 minutes';

        $this->harness->addAssignTos($this->assignment, [$changed], $this->section, $this->user);

        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $new_assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
    }

    /** @test */
    public function changing_who_is_assigned_wipes_the_running_clock()
    {
        // Same dates and time_limit, but now targets the section directly
        // instead of the whole course - a real change in who this position
        // covers, even though the student is still (incidentally) in scope.
        $changed = $this->unchanged_assign_to;
        $changed['groups'] = [['value' => ['section_id' => $this->section->id], 'text' => $this->section->name]];

        $this->harness->addAssignTos($this->assignment, [$changed], $this->section, $this->user);

        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assertDatabaseMissing('assign_to_timing_starts', [
            'assign_to_timing_id' => $new_assign_to_timing->id,
            'user_id' => $this->student_user->id,
        ]);
    }
}
