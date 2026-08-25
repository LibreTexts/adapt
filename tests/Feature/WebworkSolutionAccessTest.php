<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\JWE;
use App\Question;
use App\Submission;
use App\User;
use App\Webwork;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use function factory;

class WebworkSolutionAccessTest extends TestCase
{
    private $student;
    private $instructor;
    private $assignment;
    private $question;
    private $jwe;

    public function setup(): void
    {
        parent::setUp();

        $this->jwe = new JWE();

        $this->instructor = factory(User::class)->create(['role' => 2]);
        $this->student = factory(User::class)->create(['role' => 3, 'fake_student' => 0]);

        $course = factory(Course::class)->create(['user_id' => $this->instructor->id]);

        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $course->id,
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'solutions_availability' => 'automatic',
            'number_of_allowed_attempts' => 'unlimited',
            'solutions_released' => 0,
        ]);

        $this->question = factory(Question::class)->create([
            'technology' => 'webwork',
            'page_id' => 23482671,
        ]);
    }

    private function createProblemJWT(User $user): string
    {
        $secret = $this->jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);

        $token = \JWTAuth::claims([
            'adapt' => [
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'technology' => 'webwork',
            ],
            'webwork' => [],
            'imathas' => [],
            'h5p' => [],
        ])->fromUser($this->student);

        $problemJWT = $this->jwe->encrypt($token, 'webwork');
        \JWTAuth::getJWTProvider()->setSecret(config('myconfig.jwt_secret'));

        return $problemJWT;
    }

    private function createSubmission(array $overrides = []): Submission
    {
        $defaultSubmission = json_encode([
            'score' => [
                'result' => 0,
                'answers' => [
                    ['score' => 0],
                    ['score' => 0],
                ]
            ]
        ]);

        return factory(Submission::class)->create(array_merge([
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $defaultSubmission,
            'show_solution' => 0,
            'answered_correctly_at_least_once' => 0,
            'score' => 0,
            'submission_count' => 1,
        ], $overrides));
    }
    private function giveUp(): void
    {
        DB::table('can_give_ups')->insert([
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'status' => 'gave up',
        ]);
    }

    private function assertAccessGranted(string $problemJWT, User $user): void
    {
        $this->actingAs($user)
            ->getJson("/api/webwork/solution/$problemJWT")
            ->assertJsonMissing(['message' => 'No access: You do not have access to this weBWork solution.']);
    }

    private function assertAccessDenied(string $problemJWT, User $user, string $message): void
    {
        $response = $this->actingAs($user)
            ->getJson("/api/webwork/solution/$problemJWT");

        $this->assertEquals('error', $response->json('type'));
        $this->assertEquals(
            "No access: $message",
            $response->json('message')
        );
    }

    /** @test */
    public function student_without_show_solution_flag_is_denied_on_unlimited_attempts()
    {
        $this->assignment->update(['number_of_allowed_attempts' => 'unlimited']);
        $this->createSubmission(['show_solution' => 0]);
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessDenied($problemJWT, $this->student, 'You do not have access to this weBWork solution.');
    }

    /** @test */
    public function missing_assignment_id_denies_access()
    {
        $secret = $this->jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);
        $token = \JWTAuth::claims([
            'adapt' => ['question_id' => $this->question->id],
            'webwork' => [],
        ])->fromUser($this->student);
        $problemJWT = $this->jwe->encrypt($token, 'webwork');

        $this->assertAccessDenied($problemJWT, $this->student, 'You need both an assignment ID and question ID in the problem JWT in order to view the solution.');
    }


    /** @test */
    public function missing_adapt_claim_denies_access()
    {
        $secret = $this->jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);
        $token = \JWTAuth::fromUser($this->student, ['webwork' => []]);
        $problemJWT = $this->jwe->encrypt($token, 'webwork');
        $this->assertAccessDenied($problemJWT, $this->student, 'You are missing the ADAPT claim in the solution JWT.');
    }



    // --- Non-student access ---

    /** @test */
    public function non_role_3_user_can_view_solution()
    {
        $problemJWT = $this->createProblemJWT($this->instructor);
        $this->assertAccessGranted($problemJWT, $this->instructor);
    }

    /** @test */
    public function fake_student_can_view_solution()
    {
        $fakeStudent = factory(User::class)->create(['role' => 3, 'fake_student' => 1]);
        $problemJWT = $this->createProblemJWT($fakeStudent);
        $this->assertAccessGranted($problemJWT, $fakeStudent);
    }

    // --- JWT claim validation ---


    /** @test */
    public function missing_question_id_denies_access()
    {
        $secret = $this->jwe->getSecret('webwork');
        \JWTAuth::getJWTProvider()->setSecret($secret);
        $token = \JWTAuth::claims([
            'adapt' => ['assignment_id' => $this->assignment->id],
            'webwork' => [],
        ])->fromUser($this->student);
        $problemJWT = $this->jwe->encrypt($token, 'webwork');
        \JWTAuth::getJWTProvider()->setSecret(config('myconfig.jwt_secret'));

        $this->assertAccessDenied($problemJWT, $this->student, 'You need both an assignment ID and question ID in the problem JWT in order to view the solution.');
    }

    // --- showRealTimeSolution paths ---

    /** @test */
    public function student_with_no_submission_is_denied()
    {
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessDenied($problemJWT, $this->student, 'You do not have access to this weBWork solution.');
    }

    /** @test */
    public function student_with_show_solution_flag_can_view_on_unlimited_attempts()
    {
        $this->assignment->update(['number_of_allowed_attempts' => 'unlimited']);
        $this->createSubmission(['show_solution' => 1]);
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessGranted($problemJWT, $this->student);
    }



    /** @test */
    public function student_who_exhausted_attempts_can_view_solution()
    {
        $this->assignment->update(['number_of_allowed_attempts' => 3]);
        $this->createSubmission(['submission_count' => 3]);
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessGranted($problemJWT, $this->student);
    }

    /** @test */
    public function student_who_has_not_exhausted_attempts_is_denied()
    {
        $this->assignment->update(['number_of_allowed_attempts' => 3]);
        $this->createSubmission(['submission_count' => 2]);
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessDenied($problemJWT, $this->student, 'You do not have access to this weBWork solution.');
    }

    // --- Give up / solutions released ---

    /** @test */
    public function student_who_gave_up_can_view_solution()
    {
        $this->createSubmission();
        $this->giveUp();
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessGranted($problemJWT, $this->student);
    }

    /** @test */
    public function student_can_view_solution_when_solutions_released()
    {
        $this->assignment->update(['solutions_released' => 1]);
        $this->createSubmission();
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessGranted($problemJWT, $this->student);
    }

    /** @test */
    public function student_denied_when_no_real_time_assessment_type()
    {
        $this->assignment->update(['assessment_type' => 'delayed']);
        $this->createSubmission();
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessDenied($problemJWT, $this->student, 'You do not have access to this weBWork solution.');
    }

    /** @test */
    public function student_denied_when_solutions_availability_not_automatic()
    {
        $this->assignment->update(['solutions_availability' => 'manual']);
        $this->createSubmission();
        $problemJWT = $this->createProblemJWT($this->student);
        $this->assertAccessDenied($problemJWT, $this->student, 'You do not have access to this weBWork solution.');
    }
}
