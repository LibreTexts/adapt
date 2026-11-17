<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\Traits\Test as TestHelpers;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use function factory;

/**
 * Regression tests for clicker time-left values.
 *
 * After the Carbon 3 upgrade, $end->diffInMilliseconds($start) became signed and
 * returned a negative number for open clicker questions, which hid the student's
 * Submit button (the front end requires timeLeft > 0).
 */
class ClickerTimeLeftTest extends TestCase
{
    use TestHelpers;

    private $user;
    private $student_user;
    private $course;
    private $section;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->student_user->save();

        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id, 'solutions_released' => 0]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->question = factory(Question::class)->create(['page_id' => 1]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => 0
        ]);
    }

    private function setClickerWindow(Carbon $start, Carbon $end): void
    {
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update(['clicker_start' => $start, 'clicker_end' => $end]);
    }

    private function getStudentClickerTimeLeft()
    {
        $response = $this->actingAs($this->student_user)
            ->getJson("/api/assignments/{$this->assignment->id}/questions/view", $this->headers())
            ->assertJson(['type' => 'success'])
            ->json();
        $question = collect($response['questions'])->firstWhere('id', $this->question->id);
        $this->assertNotNull($question, 'Question not returned in the questions view.');
        return $question['clicker_time_left'];
    }

    #[Test]
    public function open_clicker_question_has_positive_time_left_on_page_load()
    {
        $this->setClickerWindow(Carbon::now(), Carbon::now()->addSeconds(30));

        $time_left = $this->getStudentClickerTimeLeft();

        $this->assertGreaterThan(0, $time_left);
        $this->assertLessThanOrEqual(30000, $time_left);
    }

    #[Test]
    public function closed_clicker_question_has_zero_time_left_on_page_load()
    {
        $this->setClickerWindow(Carbon::now()->subMinutes(2), Carbon::now()->subMinute());

        $this->assertEquals(0, $this->getStudentClickerTimeLeft());
    }

    #[Test]
    public function unstarted_clicker_question_has_zero_time_left_on_page_load()
    {
        $this->assertEquals(0, $this->getStudentClickerTimeLeft());
    }

    #[Test]
    public function starting_a_clicker_question_gives_students_positive_time_left()
    {
        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/start-clicker-assessment",
                ['time_to_submit' => '30 seconds'])
            ->assertJson(['type' => 'success']);

        $time_left = $this->getStudentClickerTimeLeft();

        $this->assertGreaterThan(0, $time_left);
        // 30 seconds plus the 5 seconds of padding added on start.
        $this->assertLessThanOrEqual(35000, $time_left);
    }

    #[Test]
    public function restarting_the_clicker_timer_returns_positive_time_left()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/restart-timer",
                ['time_to_submit' => '30 seconds'])
            ->assertJson(['type' => 'success'])
            ->json();

        $this->assertEquals(30000, $response['time_left']);
    }

    #[Test]
    public function adding_time_to_a_clicker_returns_positive_time_left()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/add-time",
                ['time_to_add' => 15, 'time_left' => 10])
            ->assertJson(['type' => 'success'])
            ->json();

        $this->assertEquals(25000, $response['time_left']);
    }
}
