<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Course;
use App\DataShop;
use App\Enrollment;
use App\Exceptions\MasteryRetakeConflictException;
use App\Http\Requests\StoreAssignmentProperties;
use App\Http\Requests\StoreSubmission;
use App\MasteryAssignmentAttempt;
use App\Question;
use App\Score;
use App\Section;
use App\Services\MasteryAssignmentAttemptService;
use App\Services\MasteryRetakeEligibility;
use App\Submission;
use App\Traits\Test;
use App\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Napp\Xray\Middleware\RequestTracing;
use Tests\TestCase;

class MasteryAssignmentAttemptTest extends TestCase
{
    use Test;

    private $assignment;
    private $course;
    private $instructor;
    private $student;
    private $questions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(RequestTracing::class);

        $this->instructor = factory(User::class)->create(['role' => 2]);
        $this->student = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->instructor->id]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'assessment_type' => 'real time',
            'scoring_type' => 'p',
            'algorithmic' => 1,
            'number_of_allowed_attempts' => '1',
            'number_of_allowed_attempts_penalty' => 0,
            'number_of_randomized_assessments' => null,
            'can_submit_work' => 0,
            'hint_penalty' => 0,
            'late_policy' => 'not accepted',
            'mastery_retake_enabled' => 1,
            'mastery_number_of_allowed_attempts' => 'unlimited'
        ]);

        $this->questions = collect([
            factory(Question::class)->create(['technology' => 'webwork']),
            factory(Question::class)->create(['technology' => 'webwork'])
        ]);
        foreach ($this->questions as $index => $question) {
            DB::table('assignment_question')->insert([
                'assignment_id' => $this->assignment->id,
                'question_id' => $question->id,
                'points' => 5,
                'order' => $index + 1,
                'open_ended_submission_type' => '0'
            ]);
        }
        $this->assignUserToAssignment(
            $this->assignment->id,
            'course',
            $this->course->id,
            $this->student->id
        );
    }

    /** @test */
    public function eligibility_accepts_supported_questions_and_rejects_degenerate_points()
    {
        $eligibility = app(MasteryRetakeEligibility::class);
        $this->assertTrue($eligibility->evaluate($this->assignment)->eligible());

        $this->questions[0]->technology = 'imathas';
        $this->questions[0]->save();
        $this->assertTrue($eligibility->evaluate($this->assignment)->eligible());

        $this->questions[0]->technology = 'qti';
        $this->questions[0]->save();
        $this->assertTrue($eligibility->evaluate($this->assignment)->eligible());

        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->questions[1]->id)
            ->update(['points' => 0]);

        $reason_codes = collect($eligibility->evaluate($this->assignment)->reasons())->pluck('code');
        $this->assertTrue($reason_codes->contains('invalid_question_points'));
    }

    /** @test */
    public function eligibility_rejects_unsupported_questions_and_anonymous_courses()
    {
        $eligibility = app(MasteryRetakeEligibility::class);

        $this->questions[0]->technology = 'h5p';
        $this->questions[0]->save();
        $reason_codes = collect($eligibility->evaluate($this->assignment)->reasons())->pluck('code');
        $this->assertTrue($reason_codes->contains('unsupported_question'));

        $this->questions[0]->technology = 'webwork';
        $this->questions[0]->save();
        $this->course->anonymous_users = 1;
        $this->course->save();
        $this->assignment->unsetRelation('course');
        $anonymous_reason = collect($eligibility->evaluate($this->assignment)->reasons())
            ->firstWhere('code', 'anonymous_users');
        $this->assertSame(
            'Whole-assignment attempts are not available in courses that allow anonymous users.',
            $anonymous_reason['message']
        );
    }

    /** @test */
    public function a_native_assignment_can_start_another_attempt_with_the_same_problem_versions()
    {
        $this->assignment->algorithmic = 0;
        $this->assignment->save();
        $fixed_variant = json_encode(['correct', 'incorrect']);
        foreach ($this->questions as $question) {
            $question->technology = 'qti';
            $question->qti_json_type = 'question_json';
            $question->qti_json = json_encode([
                'questionType' => 'multiple_choice',
                'randomizeOrder' => 'no',
                'simpleChoice' => [
                    ['identifier' => 'correct', 'correctResponse' => true],
                    ['identifier' => 'incorrect', 'correctResponse' => false]
                ]
            ]);
            $question->save();
        }
        $attempt = MasteryAssignmentAttempt::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => MasteryAssignmentAttempt::STATUS_COMPLETED,
            'question_ids' => $this->questions->pluck('id')->toArray(),
            'variant_identifiers' => $this->questions->pluck('id')->mapWithKeys(function ($question_id) use ($fixed_variant) {
                return [$question_id => $fixed_variant];
            })->toArray(),
            'question_results' => [],
            'score' => 5,
            'possible_score' => 10,
            'completed_at' => now()
        ]);

        $next = app(MasteryAssignmentAttemptService::class)
            ->startNextAttempt($this->assignment, $this->student, $attempt->id)['attempt'];

        $this->assertSame(2, $next->attempt_number);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $next->status);
        foreach ($next->variant_identifiers as $variant) {
            $this->assertSame($fixed_variant, $variant);
        }
    }

    /** @test */
    public function the_final_response_completes_an_authoritative_attempt_snapshot_and_updates_the_grade()
    {
        $service = app(MasteryAssignmentAttemptService::class);
        $question_ids = $this->questions->pluck('id')->toArray();
        $attempt = $service->getOrCreateForLaunch($this->assignment, $this->student, $question_ids);
        $this->insertSeed($this->questions[0], 101);
        $this->insertSeed($this->questions[1], 202);

        $this->actingAs($this->student);
        $partial = $this->storeWebworkSubmission($this->questions[0], $attempt, 1);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $partial['mastery_attempt']['status']);
        $this->assertDatabaseMissing('scores', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id
        ]);

        $completed = $this->storeWebworkSubmission($this->questions[1], $attempt, 0);

        $snapshot = $attempt->fresh();
        $this->assertSame(MasteryAssignmentAttempt::STATUS_COMPLETED, $snapshot->status);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_COMPLETED, $completed['mastery_attempt']['status']);
        $this->assertSame($question_ids, $snapshot->question_ids);
        $this->assertNotEmpty($snapshot->question_results[0]['submission']);
        $this->assertSame('101', (string)$snapshot->question_results[0]['variant_identifier']);
        $this->assertEquals(5, $snapshot->score);
        $this->assertEquals(10, $snapshot->possible_score);
        $this->assertDatabaseHas('scores', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'score' => 5
        ]);

        $stale = $this->storeWebworkSubmission($this->questions[0], $attempt, 1);
        $this->assertSame(409, $stale['status']);
        $this->assertSame('stale_attempt', $stale['reason']);
    }

    /** @test */
    public function a_duplicate_question_submission_is_rejected_while_the_attempt_remains_in_progress()
    {
        $service = app(MasteryAssignmentAttemptService::class);
        $attempt = $service->getOrCreateForLaunch(
            $this->assignment,
            $this->student,
            $this->questions->pluck('id')->toArray()
        );

        $this->actingAs($this->student);
        $first = $this->storeWebworkSubmission($this->questions[0], $attempt, 1);
        $duplicate = $this->storeWebworkSubmission($this->questions[0], $attempt, 0);

        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $first['mastery_attempt']['status']);
        $this->assertSame(409, $duplicate['status']);
        $this->assertSame('duplicate_submission', $duplicate['reason']);
        $this->assertDatabaseHas('submissions', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'user_id' => $this->student->id,
            'score' => 5,
            'submission_count' => 1
        ]);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $attempt->fresh()->status);
    }

    /** @test */
    public function a_retake_replaces_state_is_idempotent_and_retains_the_best_completed_score()
    {
        $service = app(MasteryAssignmentAttemptService::class);
        $question_ids = $this->questions->pluck('id')->toArray();
        $previous = MasteryAssignmentAttempt::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => MasteryAssignmentAttempt::STATUS_COMPLETED,
            'question_ids' => $question_ids,
            'variant_identifiers' => [
                $this->questions[0]->id => 100000,
                $this->questions[1]->id => 100000
            ],
            'question_results' => [],
            'score' => 8,
            'possible_score' => 10,
            'completed_at' => now()
        ]);
        foreach ($this->questions as $question) {
            $this->insertSeed($question, 100000);
            $this->insertSubmission($question, 'old response', 4, false);
        }
        DB::table('unconfirmed_submissions')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'user_id' => $this->student->id,
            'submission' => '{}',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        DB::table('submission_confirmations')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'user_id' => $this->student->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $started = $service->startNextAttempt($this->assignment, $this->student, $previous->id);
        $new_attempt = $started['attempt'];

        $this->assertFalse($started['already_started']);
        $this->assertSame(2, $new_attempt->attempt_number);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $new_attempt->status);
        $this->assertCount(2, $new_attempt->variant_identifiers);
        foreach ($new_attempt->variant_identifiers as $variant) {
            $this->assertNotSame('100000', (string)$variant);
        }
        $this->assertSame(0, DB::table('submissions')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student->id)
            ->count());
        $this->assertSame(2, DB::table('seeds')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student->id)
            ->count());
        $this->assertSame(0, DB::table('unconfirmed_submissions')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student->id)
            ->count());
        $this->assertSame(0, DB::table('submission_confirmations')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student->id)
            ->count());

        $repeated = $service->startNextAttempt($this->assignment, $this->student, $previous->id);
        $this->assertTrue($repeated['already_started']);
        $this->assertSame($new_attempt->id, $repeated['attempt']->id);

        (new Score())->updateAssignmentScore($this->student->id, $this->assignment->id, false);
        $this->assertDatabaseHas('scores', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'score' => 8
        ]);

        $this->expectException(MasteryRetakeConflictException::class);
        $service->validateSubmission($this->assignment, $this->student, $previous->id);
    }

    /** @test */
    public function an_ordinary_assignment_keeps_the_existing_partial_score_behavior()
    {
        $this->assignment->mastery_retake_enabled = 0;
        $this->assignment->save();
        $this->insertSubmission($this->questions[0], 'legacy response', 5, true);

        (new Score())->updateAssignmentScore($this->student->id, $this->assignment->id, false);

        $this->assertDatabaseHas('scores', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'score' => 5
        ]);
        $this->assertSame(0, MasteryAssignmentAttempt::where('assignment_id', $this->assignment->id)->count());
    }

    /** @test */
    public function an_all_correct_attempt_can_be_restarted_when_assignment_attempts_are_unlimited()
    {
        $service = app(MasteryAssignmentAttemptService::class);
        $attempt = $service->getOrCreateForLaunch(
            $this->assignment,
            $this->student,
            $this->questions->pluck('id')->toArray()
        );
        foreach ($this->questions as $index => $question) {
            $this->insertSeed($question, 300 + $index);
            $this->insertSubmission($question, "correct response {$index}", 5, true);
        }

        $completed = $service->completeIfReady($this->assignment, $this->student, $attempt);

        $this->assertTrue($completed['completed']);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_MASTERED, $completed['attempt']->status);
        $this->assertTrue($service->payload($completed['attempt'])['can_retake']);

        $retake = $service->startNextAttempt($this->assignment, $this->student, $attempt->id);
        $this->assertSame(2, $retake['attempt']->attempt_number);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_IN_PROGRESS, $retake['attempt']->status);
    }

    /** @test */
    public function a_finite_assignment_attempt_limit_blocks_another_retake()
    {
        $this->assignment->mastery_number_of_allowed_attempts = '2';
        $this->assignment->save();
        $attempt = MasteryAssignmentAttempt::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 2,
            'status' => MasteryAssignmentAttempt::STATUS_MASTERED,
            'question_ids' => $this->questions->pluck('id')->toArray(),
            'variant_identifiers' => [],
            'question_results' => [],
            'score' => 10,
            'possible_score' => 10,
            'completed_at' => now()
        ]);

        $service = app(MasteryAssignmentAttemptService::class);
        $this->assertFalse($service->payload($attempt)['can_retake']);

        try {
            $service->startNextAttempt($this->assignment, $this->student, $attempt->id);
            $this->fail('The configured assignment-attempt limit should prevent another retake.');
        } catch (MasteryRetakeConflictException $e) {
            $this->assertSame('attempt_limit_reached', $e->reason());
        }
    }

    /** @test */
    public function a_later_lower_completed_attempt_does_not_reduce_the_best_score()
    {
        $question_ids = $this->questions->pluck('id')->toArray();
        foreach ([1 => 8, 2 => 3] as $attempt_number => $attempt_score) {
            MasteryAssignmentAttempt::create([
                'assignment_id' => $this->assignment->id,
                'user_id' => $this->student->id,
                'attempt_number' => $attempt_number,
                'status' => MasteryAssignmentAttempt::STATUS_COMPLETED,
                'question_ids' => $question_ids,
                'variant_identifiers' => [],
                'question_results' => [],
                'score' => $attempt_score,
                'possible_score' => 10,
                'completed_at' => now()
            ]);
        }

        (new Score())->updateAssignmentScore($this->student->id, $this->assignment->id, false);

        $this->assertDatabaseHas('scores', [
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'score' => 8
        ]);
    }

    /** @test */
    public function instructors_can_preview_without_a_mastery_attempt_identifier()
    {
        $this->actingAs($this->instructor);
        $response = $this->storeWebworkSubmission($this->questions[0], null, 1);

        $this->assertSame('success', $response['type']);
        $this->assertArrayNotHasKey('reason', $response);
    }

    /** @test */
    public function only_an_enrolled_student_can_start_the_next_attempt()
    {
        $previous = $this->completedAttempt();

        $this->actingAs($this->student)
            ->postJson("/api/assignments/{$this->assignment->id}/mastery-attempts", [
                'previous_attempt_id' => $previous->id
            ])
            ->assertStatus(200)
            ->assertJson(['type' => 'success']);

        $outsider = factory(User::class)->create(['role' => 3]);
        $this->actingAs($outsider)
            ->postJson("/api/assignments/{$this->assignment->id}/mastery-attempts", [
                'previous_attempt_id' => $previous->id
            ])
            ->assertStatus(403)
            ->assertJson(['reason' => 'not_authorized']);
    }

    /** @test */
    public function mastery_cannot_be_enabled_after_ordinary_student_work_exists()
    {
        $this->assignment->mastery_retake_enabled = 0;
        $this->assignment->save();
        $this->insertSubmission($this->questions[0], 'legacy response', 5, true);

        $request = StoreAssignmentProperties::create('/', 'PATCH', [
            'mastery_retake_enabled' => 1
        ]);
        $route = new Route(['PATCH'], '/', []);
        $route->bind($request);
        $route->setParameter('assignment', $this->assignment);
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        $validator = Validator::make($request->all(), []);
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Whole-assignment attempts cannot be enabled after student work exists.',
            $validator->errors()->first('mastery_retake_enabled')
        );
    }

    /** @test */
    public function completed_attempts_can_be_reopened_after_assignment_eligibility_changes()
    {
        $attempt = $this->completedAttempt();
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->questions[0]->id)
            ->update(['points' => 0]);

        $reopened = app(MasteryAssignmentAttemptService::class)->getOrCreateForLaunch(
            $this->assignment,
            $this->student,
            $this->questions->pluck('id')->toArray()
        );

        $this->assertSame($attempt->id, $reopened->id);
        $this->assertSame(MasteryAssignmentAttempt::STATUS_COMPLETED, $reopened->status);
    }

    /** @test */
    public function question_points_can_be_changed_before_a_real_student_attempt_starts()
    {
        $this->actingAs($this->instructor)
            ->patchJson(
                "/api/assignments/{$this->assignment->id}/questions/{$this->questions[0]->id}/update-points",
                ['points' => 20]
            )
            ->assertJson(['type' => 'success']);

        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'points' => 20
        ]);
    }

    /** @test */
    public function question_points_are_locked_after_a_real_student_attempt_starts()
    {
        $this->completedAttempt();

        $this->actingAs($this->instructor)
            ->patchJson(
                "/api/assignments/{$this->assignment->id}/questions/{$this->questions[0]->id}/update-points",
                ['points' => 20]
            )
            ->assertJson([
                'message' => 'This cannot be updated since students have already submitted responses to this assignment.'
            ]);

        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'points' => 5
        ]);
    }

    /** @test */
    public function question_response_settings_are_locked_after_a_real_student_attempt_starts()
    {
        $this->completedAttempt();

        $this->actingAs($this->instructor)
            ->patchJson(
                "/api/assignments/{$this->assignment->id}/questions/{$this->questions[0]->id}/update-open-ended-submission-type",
                ['open_ended_submission_type' => 'file']
            )
            ->assertJson([
                'message' => 'Question response settings cannot be changed after a student has started a whole-assignment attempt.'
            ]);

        $this->assertDatabaseHas('assignment_question', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->questions[0]->id,
            'open_ended_submission_type' => '0'
        ]);
    }

    /** @test */
    public function a_fake_student_gets_a_new_initial_attempt_after_question_membership_changes()
    {
        $fake_student = factory(User::class)->create([
            'role' => 3,
            'fake_student' => 1
        ]);
        $service = app(MasteryAssignmentAttemptService::class);
        $first = $service->getOrCreateForLaunch(
            $this->assignment,
            $fake_student,
            $this->questions->pluck('id')->toArray()
        );
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->questions[1]->id)
            ->delete();

        $replacement = $service->getOrCreateForLaunch(
            $this->assignment,
            $fake_student,
            [$this->questions[0]->id]
        );

        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame(1, $replacement->attempt_number);
        $this->assertSame([$this->questions[0]->id], $replacement->question_ids);
    }

    private function completedAttempt(): MasteryAssignmentAttempt
    {
        return MasteryAssignmentAttempt::create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => MasteryAssignmentAttempt::STATUS_COMPLETED,
            'question_ids' => $this->questions->pluck('id')->toArray(),
            'variant_identifiers' => $this->questions->pluck('id')->mapWithKeys(function ($question_id) {
                return [$question_id => 100000];
            })->toArray(),
            'question_results' => [],
            'score' => 5,
            'possible_score' => 10,
            'completed_at' => now()
        ]);
    }

    private function storeWebworkSubmission(Question $question, ?MasteryAssignmentAttempt $attempt, float $score): array
    {
        $request = StoreSubmission::create('/', 'POST', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'technology' => 'webwork',
            'mastery_attempt_id' => $attempt ? $attempt->id : null,
            'submission' => (object)[
                'score' => (object)[
                    'result' => $score,
                    'answers' => [
                        ['score' => $score, 'weight' => 100]
                    ]
                ]
            ]
        ]);
        $route = new Route(['POST'], '/', []);
        $route->bind($request);
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });
        $request->setUserResolver(function () {
            return $this->student;
        });
        app()->instance('request', $request);

        return (new Submission())->store(
            $request,
            new Submission(),
            new Assignment(),
            new Score(),
            new DataShop(),
            new AssignmentSyncQuestion()
        );
    }

    private function insertSubmission(Question $question, string $response, float $score, bool $correct): void
    {
        Submission::create([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'user_id' => $this->student->id,
            'submission' => $response,
            'score' => $score,
            'submission_count' => 1,
            'answered_correctly_at_least_once' => $correct
        ]);
    }

    private function insertSeed(Question $question, int $seed): void
    {
        DB::table('seeds')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $question->id,
            'user_id' => $this->student->id,
            'seed' => $seed,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
