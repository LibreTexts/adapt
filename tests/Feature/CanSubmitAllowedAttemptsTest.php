<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\Submission;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use function factory;

/**
 * H5P activity-set questions create the parent submissions row with
 * submission_count = 0 (the real per-part counts live in h5p_activity_sets).
 * Assignments that aren't real time / learning tree / clicker store
 * number_of_allowed_attempts as NULL. (int) NULL === 0 used to lock the
 * question with "You have submitted this question 0 times and you are only
 * allowed 0 attempts".
 */
class CanSubmitAllowedAttemptsTest extends TestCase
{
    use \App\Traits\Test;

    private $instructor;
    private $student;
    private $course;
    private $question;

    public function setup(): void
    {
        parent::setUp();

        $this->instructor = factory(User::class)->create(['role' => 2]);
        $this->student = factory(User::class)->create([
            'role' => 3,
            'fake_student' => 0,
            'central_identity_id' => 'test-uuid-student',
        ]);

        $this->course = factory(Course::class)->create(['user_id' => $this->instructor->id]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id,
        ]);

        $this->question = factory(Question::class)->create([
            'technology' => 'h5p',
            'technology_id' => 1464,
            'page_id' => 98765431,
        ]);
    }

    private function createAssignment(array $attributes): Assignment
    {
        $assignment = factory(Assignment::class)->create(array_merge([
            'course_id' => $this->course->id,
            'solutions_released' => 0,
        ], $attributes));
        DB::table('assignment_question')->insert([
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0',
        ]);
        $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student->id);
        return $assignment;
    }

    private function createSubmission(Assignment $assignment, int $submission_count): Submission
    {
        return factory(Submission::class)->create([
            'user_id' => $this->student->id,
            'assignment_id' => $assignment->id,
            'question_id' => $this->question->id,
            'submission' => json_encode(['result' => ['score' => ['raw' => 12, 'max' => 12]]]),
            'show_solution' => 0,
            'answered_correctly_at_least_once' => 1,
            'score' => 10,
            'submission_count' => $submission_count,
        ]);
    }

    /**
     * tooManySubmissions() reads Request::user(), which actingAs() doesn't set
     * outside of an HTTP call, so point the current request at the user.
     */
    private function actingAsRequestUser(User $user): void
    {
        $this->actingAs($user);
        $this->app['request']->setUserResolver(fn() => $user);
    }

    private function canSubmitUrl(Assignment $assignment): string
    {
        return "/api/submissions/can-submit/assignment/{$assignment->id}/question/{$this->question->id}/is-forge/0";
    }

    #[Test]
    public function h5p_activity_set_submission_is_not_blocked_when_assignment_has_no_attempt_limit()
    {
        $assignment = $this->createAssignment([
            'assessment_type' => 'delayed',
            'number_of_allowed_attempts' => null,
        ]);
        // Mirrors Submission::processh5pActivitySet(), which creates the parent row with a count of 0.
        $this->createSubmission($assignment, 0);

        $response = $this->actingAs($this->student)->getJson($this->canSubmitUrl($assignment));

        $this->assertStringNotContainsString('you are only allowed', (string) $response->json('message'));
        $response->assertJson(['type' => 'success']);
    }

    #[Test]
    public function submission_is_still_blocked_when_attempt_limit_is_reached()
    {
        $assignment = $this->createAssignment([
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'number_of_allowed_attempts' => '1',
        ]);
        $this->createSubmission($assignment, 1);

        $this->actingAs($this->student)
            ->getJson($this->canSubmitUrl($assignment))
            ->assertJson([
                'type' => 'error',
                'message' => 'You have submitted this question 1 time and you are only allowed 1 attempt, so no additional submissions will be accepted.',
            ]);
    }

    #[Test]
    public function submission_is_not_blocked_with_unlimited_attempts()
    {
        $assignment = $this->createAssignment([
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'number_of_allowed_attempts' => 'unlimited',
        ]);
        $this->createSubmission($assignment, 5);

        $this->actingAs($this->student)
            ->getJson($this->canSubmitUrl($assignment))
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function too_many_submissions_is_false_when_assignment_has_no_attempt_limit()
    {
        $assignment = $this->createAssignment([
            'assessment_type' => 'delayed',
            'number_of_allowed_attempts' => null,
        ]);
        $submission = $this->createSubmission($assignment, 0);

        $this->actingAsRequestUser($this->student);
        $this->assertFalse((new Submission())->tooManySubmissions($assignment->fresh(), $submission));
    }

    #[Test]
    public function too_many_submissions_is_true_when_attempt_limit_is_reached()
    {
        $assignment = $this->createAssignment([
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'number_of_allowed_attempts' => '2',
        ]);
        $submission = $this->createSubmission($assignment, 2);

        $this->actingAsRequestUser($this->student);
        $this->assertTrue((new Submission())->tooManySubmissions($assignment->fresh(), $submission));
    }
}
