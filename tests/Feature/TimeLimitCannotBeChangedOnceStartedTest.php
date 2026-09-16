<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\Rules\TimeLimitCannotBeChangedOnceStarted;
use App\Section;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

/**
 * App\Rules\TimeLimitCannotBeChangedOnceStarted had no dedicated test at
 * all despite being the thing that locks an assign-to group's time_limit
 * once a real student has started their personal clock for it. Exercised
 * directly against the rule's passes()/message() rather than through the
 * full assignment-update HTTP endpoint, since it's the rule's own logic -
 * which assign_to_timing it looks at, whose "started" state counts, when it
 * allows vs. blocks - that needs coverage here.
 */
class TimeLimitCannotBeChangedOnceStartedTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->student_user = factory(User::class)->create(['role' => 3]);
        Enrollment::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->student_user->id,
        ]);

        $this->fake_student = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        Enrollment::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section->id,
            'user_id' => $this->fake_student->id,
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->assign_to_timing = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $this->assign_to_timing->time_limit = '10 minutes';
        $this->assign_to_timing->save();
    }

    private function startTimer(User $user): void
    {
        DB::table('assign_to_timing_starts')->insert([
            'assign_to_timing_id' => $this->assign_to_timing->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function passes_when_no_assign_to_timing_exists_yet_at_this_position()
    {
        // A brand-new assign-to group being added has nothing to protect.
        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 5);
        $this->assertTrue($rule->passes('time_limit_5', 'anything'));
    }

    /** @test */
    public function passes_when_no_real_student_has_started_regardless_of_the_new_value()
    {
        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertTrue($rule->passes('time_limit_0', '99 minutes'));
    }

    /** @test */
    public function fails_when_a_real_student_has_started_and_the_value_changed()
    {
        $this->startTimer($this->student_user);

        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertFalse($rule->passes('time_limit_0', '99 minutes'));
    }

    /** @test */
    public function passes_when_a_real_student_has_started_but_the_value_is_unchanged()
    {
        $this->startTimer($this->student_user);

        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertTrue($rule->passes('time_limit_0', '10 minutes'));
    }

    /** @test */
    public function fails_when_removing_the_time_limit_entirely_after_a_real_student_started()
    {
        $this->startTimer($this->student_user);

        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertFalse($rule->passes('time_limit_0', null));
    }

    /** @test */
    public function passes_when_only_a_fake_student_has_started()
    {
        // fake_student rows are explicitly excluded from the "a real student
        // has started" check, same as elsewhere in this feature.
        $this->startTimer($this->fake_student);

        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertTrue($rule->passes('time_limit_0', '99 minutes'));
    }

    /** @test */
    public function message_explains_why_it_was_blocked()
    {
        $rule = new TimeLimitCannotBeChangedOnceStarted($this->assignment->id, 0);
        $this->assertEquals(
            'The time limit for this assign-to group cannot be changed since at least one student has already started their timer for this assignment.',
            $rule->message()
        );
    }
}
