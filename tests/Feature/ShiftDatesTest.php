<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Section;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

class ShiftDatesTest extends TestCase
{
    use Test;

    public function setup(): void
    {

        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->user_1 = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'shown' => 0,
            'show_scores' => 0,
            'solutions_released' => 0,
            'students_can_view_assignment_statistics' => 0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_get_dates()
    {
        $this->actingAs($this->user_1)
            ->getJson("/api/assignments/{$this->course->id}/dates")
            ->assertJson(['message' => "You are not allowed to get the dates since you do not own this course."]);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_shift_dates()
    {
        $this->actingAs($this->user_1)
            ->postJson("/api/assignments/{$this->course->id}/shift-dates", ['assignment_ids' => [$this->assignment->id]])
            ->assertJson(['message' => "You are not allowed to shift the dates since you do not own all of these assignments."]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shift_by_must_be_valid()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->course->id}/shift-dates",
                ['shift_by' => 'ooga',
                    'assignment_ids' => [$this->assignment->id]
                ])
            ->assertJsonValidationErrors('shift_by');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function zero_shift_by_is_not_valid()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->course->id}/shift-dates",
                ['shift_by' => '0 minutes',
                    'assignment_ids' => [$this->assignment->id]
                ])
            ->assertJsonValidationErrors('shift_by');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dates_are_correctly_shifted()
    {
        $this->assertDatesShiftedBy('1 day');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dates_are_correctly_shifted_backwards()
    {
        $this->assertDatesShiftedBy('-1 day');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function first_available_on_with_an_earlier_date_shifts_dates_backwards()
    {
        //"first available on" sends a computed (here negative) shift_by in minutes
        $this->assertDatesShiftedBy('-53339 minutes', 'first available on');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shift_by_is_ignored_when_setting_a_single_common_date()
    {
        $this->enrollStudentAndAssign();
        $original_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->course->id}/shift-dates",
                ['shift_by' => '-53339 minutes',
                    'offset_difference' => 0,
                    'shifting_method' => 'assignment date property',
                    'assignment_date_property' => 'available_from',
                    'change_date_form' => ['date' => '2027-01-01', 'time' => '12:00 AM'],
                    'assignment_ids' => [$this->assignment->id]
                ])
            ->assertJson(['type' => 'success']);
        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $expected_available_from = Carbon::createFromFormat('Y-m-d g:i A', '2027-01-01 12:00 AM', $this->user->time_zone)
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
        $this->assertEquals($expected_available_from, $new_assign_to_timing->available_from);
        //the other dates are left alone
        $this->assertEquals($original_assign_to_timing->due, $new_assign_to_timing->due);
        $this->assertEquals($original_assign_to_timing->final_submission_deadline, $new_assign_to_timing->final_submission_deadline);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_rejects_an_invalid_period_of_time()
    {
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/preview-shift-dates",
                ['shift_by' => 'ooga',
                    'shifting_method' => 'period of time',
                    'chosen_assignments' => []
                ])
            ->assertJson(['type' => 'error',
                'message' => 'ooga is not a valid period of time.']);
    }

    private function enrollStudentAndAssign(): void
    {
        $student_user = factory(User::class)->create(['role' => 3]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        DB::table('enrollments')->insert([
            'user_id' => $student_user->id,
            'course_id' => $this->course->id,
            'section_id' => $section->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $student_user->id);
    }

    private function assertDatesShiftedBy(string $shift_by, string $shifting_method = 'period of time'): void
    {
        $this->enrollStudentAndAssign();
        $original_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->course->id}/shift-dates",
                ['shift_by' => $shift_by,
                    'offset_difference' => 0,
                    'shifting_method' => $shifting_method,
                    'assignment_ids' => [$this->assignment->id]
                ])
            ->assertJson(['type' => 'success']);
        $new_assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();

        foreach (['available_from', 'due', 'final_submission_deadline'] as $key) {
            if ($original_assign_to_timing->{$key}) {
                $date = CarbonImmutable::parse($original_assign_to_timing->{$key});
                $this->assertEquals($date->add($shift_by)->toDateTimeString(), $new_assign_to_timing->{$key});
            }
        }
    }
}
