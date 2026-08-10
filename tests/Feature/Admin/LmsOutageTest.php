<?php

namespace Tests\Feature;

use App\Course;
use App\LmsOutageCourse;
use App\LtiRegistration;
use App\Mail\LmsOutageDisabled;
use App\Mail\LmsOutageEnabled;
use App\School;
use App\Traits\Test;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LmsOutageTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();

        // Matches your app's actual admin check (Helper::isAdmin()),
        // which is based on user id/email rather than role.
        $this->admin_user = factory(User::class)->create(['id' => 1, 'email' => 'me@me.com']); //Admin

        $this->instructor = factory(User::class)->create(['first_name' => 'Jamie']);
        $this->instructor->role = 2;

        $this->canvas_school = factory(School::class)->create(['name' => 'Canvas Test School ' . uniqid()]);
        $this->canvas_registration = factory(LtiRegistration::class)->create([
            'iss' => 'https://canvas.instructure.com'
        ]);
        DB::table('lti_schools')->insert([
            'school_id' => $this->canvas_school->id,
            'lti_registration_id' => $this->canvas_registration->id
        ]);

        $this->blackboard_school = factory(School::class)->create(['name' => 'Blackboard Test School ' . uniqid()]);
        $this->blackboard_registration = factory(LtiRegistration::class)->create([
            'iss' => 'https://blackboard.example.com'
        ]);
        DB::table('lti_schools')->insert([
            'school_id' => $this->blackboard_school->id,
            'lti_registration_id' => $this->blackboard_registration->id
        ]);

        $this->canvas_course = factory(Course::class)->create([
            'user_id' => $this->instructor->id,
            'school_id' => $this->canvas_school->id,
            'lms' => 1,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(30)
        ]);
    }

    /** @test */
    public function non_admin_cannot_get_lms_outage_status()
    {
        $this->actingAs($this->instructor)->getJson('/api/lms-outage/status')
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function admin_can_get_lms_outage_status()
    {
        $response = $this->actingAs($this->admin_user)->getJson('/api/lms-outage/status')
            ->assertJson(['type' => 'success'])
            ->json();

        $canvas = collect($response['lms_types'])->firstWhere('lms_type_key', 'canvas');
        $this->assertNotNull($canvas);
        $this->assertEquals(1, $canvas['currently_linked_count']);
        $this->assertEquals(0, $canvas['currently_off_count']);
    }

    /** @test */
    public function non_admin_cannot_turn_off_lms()
    {
        $this->actingAs($this->instructor)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'error']);

        $this->assertEquals(1, $this->canvas_course->fresh()->lms);
    }

    /** @test */
    public function admin_can_turn_off_active_canvas_courses()
    {
        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $this->assertEquals(0, $this->canvas_course->fresh()->lms);

        $this->assertDatabaseHas('lms_outage_courses', [
            'course_id' => $this->canvas_course->id,
            'school_id' => $this->canvas_school->id,
            'lti_registration_id' => $this->canvas_registration->id,
            'lms_type' => 'Canvas',
            'turned_on_at' => null
        ]);

        Mail::assertQueued(LmsOutageDisabled::class, function ($mail) {
            return $mail->hasTo($this->instructor->email);
        });
    }

    /** @test */
    public function turning_off_canvas_does_not_affect_blackboard_courses()
    {
        $blackboard_instructor = factory(User::class)->create();
        $blackboard_course = factory(Course::class)->create([
            'user_id' => $blackboard_instructor->id,
            'school_id' => $this->blackboard_school->id,
            'lms' => 1,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(30)
        ]);

        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $this->assertEquals(0, $this->canvas_course->fresh()->lms);
        $this->assertEquals(1, $blackboard_course->fresh()->lms);

        Mail::assertNotQueued(LmsOutageDisabled::class, function ($mail) use ($blackboard_instructor) {
            return $mail->hasTo($blackboard_instructor->email);
        });
    }

    /** @test */
    public function concluded_courses_are_not_turned_off()
    {
        $concluded_course = factory(Course::class)->create([
            'user_id' => $this->instructor->id,
            'school_id' => $this->canvas_school->id,
            'lms' => 1,
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(5)
        ]);

        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $this->assertEquals(1, $concluded_course->fresh()->lms);
        $this->assertDatabaseMissing('lms_outage_courses', [
            'course_id' => $concluded_course->id
        ]);
    }

    /** @test */
    public function turning_off_with_no_active_linked_courses_returns_info()
    {
        $this->canvas_course->lms = 0;
        $this->canvas_course->save();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'info']);
    }

    /** @test */
    public function turning_off_an_unsupported_lms_type_returns_error()
    {
        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/moodle/turn-off')
            ->assertJson(['type' => 'error']);
    }

    /** @test */
    public function admin_can_turn_canvas_back_on()
    {
        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-on')
            ->assertJson(['type' => 'success']);

        $this->assertEquals(1, $this->canvas_course->fresh()->lms);

        $outage_record = LmsOutageCourse::where('course_id', $this->canvas_course->id)->first();
        $this->assertNotNull($outage_record->turned_on_at);

        Mail::assertQueued(LmsOutageEnabled::class, function ($mail) {
            return $mail->hasTo($this->instructor->email);
        });
    }

    /** @test */
    public function non_admin_cannot_turn_lms_back_on()
    {
        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $this->actingAs($this->instructor)->postJson('/api/lms-outage/canvas/turn-on')
            ->assertJson(['type' => 'error']);

        $this->assertEquals(0, $this->canvas_course->fresh()->lms);
    }

    /** @test */
    public function turning_on_with_nothing_currently_off_returns_info()
    {
        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-on')
            ->assertJson(['type' => 'info']);
    }

    /** @test */
    public function status_reflects_currently_off_count_after_turning_off()
    {
        Mail::fake();

        $this->actingAs($this->admin_user)->postJson('/api/lms-outage/canvas/turn-off')
            ->assertJson(['type' => 'success']);

        $response = $this->actingAs($this->admin_user)->getJson('/api/lms-outage/status')
            ->assertJson(['type' => 'success'])
            ->json();

        $canvas = collect($response['lms_types'])->firstWhere('lms_type_key', 'canvas');
        $this->assertEquals(0, $canvas['currently_linked_count']);
        $this->assertEquals(1, $canvas['currently_off_count']);
    }
}
