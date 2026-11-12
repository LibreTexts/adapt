<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Question;
use App\Submission;
use App\UnconfirmedSubmission;
use App\User;
use Tests\TestCase;
use function factory;

class UnconfirmedSubmissionTest extends TestCase
{
    private $student;
    private $other_student;
    private $instructor;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();

        $this->instructor = factory(User::class)->create(['role' => 2]);
        $this->student = factory(User::class)->create(['role' => 3, 'fake_student' => 0]);
        $this->other_student = factory(User::class)->create(['role' => 3, 'fake_student' => 0]);

        $course = factory(Course::class)->create(['user_id' => $this->instructor->id]);

        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $course->id,
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'number_of_allowed_attempts' => '3',
        ]);

        $this->question = factory(Question::class)->create([
            'technology' => 'webwork',
            'page_id' => 23482671,
        ]);
    }

    /**
     * Mirrors what JWTController::processAnswerJWT() stores: the whole request,
     * with the WeBWorK answerJWT under 'submission'.
     */
    private function createUnconfirmedSubmission(User $user, ?array $answers = null): UnconfirmedSubmission
    {
        $answers = $answers ?? [
            'AnSwEr0001' => ['original_student_ans' => '42', 'preview_latex_string' => '42', 'score' => 0],
        ];
        return UnconfirmedSubmission::create([
            'user_id' => $user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => json_encode([
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'technology' => 'webwork',
                'submission' => ['score' => ['result' => 0, 'answers' => $answers]],
            ]),
        ]);
    }

    private function createSubmission(int $submission_count): Submission
    {
        return factory(Submission::class)->create([
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => json_encode(['score' => ['result' => 0, 'answers' => [['score' => 0]]]]),
            'show_solution' => 0,
            'answered_correctly_at_least_once' => 0,
            'score' => 0,
            'submission_count' => $submission_count,
        ]);
    }

    private function showUrl(): string
    {
        return "/api/unconfirmed-submissions/assignment/{$this->assignment->id}/question/{$this->question->id}";
    }

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------
    #[Test]
    public function webwork_question_with_limited_attempts_requires_confirmation_and_reports_remaining_attempts()
    {
        $this->createSubmission(1);
        $this->createUnconfirmedSubmission($this->student);

        $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'type' => 'success',
                'requires_confirmation' => true,
                'submission_count' => 1,
                'number_of_remaining_attempts' => 2,
            ]);
    }

    #[Test]
    public function last_attempt_reports_one_remaining_attempt()
    {
        $this->createSubmission(2);
        $this->createUnconfirmedSubmission($this->student);

        $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'requires_confirmation' => true,
                'number_of_remaining_attempts' => 1,
            ]);
    }

    #[Test]
    public function first_attempt_reports_all_attempts_remaining()
    {
        $this->createUnconfirmedSubmission($this->student);

        $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'requires_confirmation' => true,
                'submission_count' => 0,
                'number_of_remaining_attempts' => 3,
            ]);
    }

    #[Test]
    public function unlimited_attempts_do_not_require_confirmation()
    {
        $this->assignment->update(['number_of_allowed_attempts' => 'unlimited']);
        $this->createSubmission(5);
        $this->createUnconfirmedSubmission($this->student);

        $response = $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'type' => 'success',
                'requires_confirmation' => false,
            ]);
        $this->assertNull($response->json('number_of_remaining_attempts'));
    }

    #[Test]
    public function delayed_assignments_do_not_require_confirmation_even_with_a_numeric_attempt_limit()
    {
        $this->assignment->update(['assessment_type' => 'delayed', 'number_of_allowed_attempts' => '1']);
        $this->createSubmission(1);
        $this->createUnconfirmedSubmission($this->student);

        $response = $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'type' => 'success',
                'requires_confirmation' => false,
            ]);
        $this->assertNull($response->json('number_of_remaining_attempts'));
    }

    #[Test]
    public function learning_tree_assignment_with_limited_attempts_requires_confirmation()
    {
        $this->assignment->update(['assessment_type' => 'learning tree']);
        $this->createSubmission(1);
        $this->createUnconfirmedSubmission($this->student);

        $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'requires_confirmation' => true,
                'number_of_remaining_attempts' => 2,
            ]);
    }

    #[Test]
    public function has_html_is_not_reset_by_a_later_plain_text_answer()
    {
        $this->createUnconfirmedSubmission($this->student, [
            'AnSwEr0001' => ['original_student_ans' => '<b>x</b>', 'score' => 0],
            'AnSwEr0002' => ['original_student_ans' => '7', 'score' => 0],
        ]);

        $response = $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson(['type' => 'success', 'has_html' => true]);

        $this->assertEquals(['<b>x</b>', '\(7\)'], $response->json('unconfirmed_submission'));
    }

    #[Test]
    public function student_cannot_see_another_students_unconfirmed_submission()
    {
        $this->createUnconfirmedSubmission($this->other_student);

        $this->actingAs($this->student)
            ->getJson($this->showUrl())
            ->assertJson([
                'type' => 'error',
                'message' => 'There is no existing submission to confirm.',
            ]);
    }

    // -------------------------------------------------------------------------
    // DESTROY
    // -------------------------------------------------------------------------
    #[Test]
    public function cancelling_removes_only_the_students_own_unconfirmed_submission()
    {
        $this->createUnconfirmedSubmission($this->student);
        $this->createUnconfirmedSubmission($this->other_student);

        $this->actingAs($this->student)
            ->deleteJson($this->showUrl())
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseMissing('unconfirmed_submissions', [
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
        ]);
        $this->assertDatabaseHas('unconfirmed_submissions', [
            'user_id' => $this->other_student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_cancel_an_unconfirmed_submission()
    {
        $this->createUnconfirmedSubmission($this->student);

        $this->deleteJson($this->showUrl())->assertUnauthorized();

        $this->assertDatabaseHas('unconfirmed_submissions', ['user_id' => $this->student->id]);
    }

    // -------------------------------------------------------------------------
    // STORE SUBMISSION
    // -------------------------------------------------------------------------
    #[Test]
    public function confirming_without_a_pending_submission_returns_an_error()
    {
        $this->actingAs($this->student)
            ->postJson("{$this->showUrl()}/store-submission")
            ->assertJson([
                'type' => 'error',
                'message' => 'We were not able to find that unconfirmed submission.  Please try again or contact us for assistance.',
            ]);

        $this->assertDatabaseMissing('submissions', [
            'user_id' => $this->student->id,
            'question_id' => $this->question->id,
        ]);
    }
}
