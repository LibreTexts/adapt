<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\Traits\Test;
use App\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test as TestAttribute;
use Tests\TestCase;

/**
 * When a submission is saved, Submission::store must use the question's effective
 * "can submit work" setting (assignment setting + per-question override), not just
 * the assignment setting. Otherwise a question set to "students cannot submit work"
 * tells students to "Submit Work" and, under a "required" policy, moves the score
 * to pending where it can never be approved.
 */
class SubmittedWorkOverrideTest extends TestCase
{
    use Test;

    private $points = 10;
    private $correct_value = 'aaaa-correct';
    private $incorrect_value = 'bbbb-incorrect';

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);

        $this->question = factory(Question::class)->create([
            'qti_json' => json_encode([
                'questionType' => 'select_choice',
                'responseDeclaration' => ['correctResponse' => []],
                'itemBody' => '<p>Pick one: [blank1]</p>',
                'inline_choice_interactions' => [
                    'blank1' => [
                        ['value' => $this->correct_value, 'text' => 'right', 'correctResponse' => true],
                        ['value' => $this->incorrect_value, 'text' => 'wrong', 'correctResponse' => false]
                    ]
                ],
                'jsonType' => 'question_json'
            ])
        ]);
    }

    private function setUpSubmittedWork(int $assignment_can_submit_work, string $policy, ?int $question_override): void
    {
        $this->assignment->can_submit_work = $assignment_can_submit_work;
        $this->assignment->submitted_work_policy = $policy;
        $this->assignment->save();

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => $this->points,
            'order' => 1,
            'open_ended_submission_type' => 'none',
            'can_submit_work_override' => $question_override
        ]);
    }

    private function submitCorrectAnswer()
    {
        return $this->actingAs($this->student_user)->postJson('/api/submissions', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'technology' => 'qti',
            'submission' => json_encode([['identifier' => 'blank1', 'value' => $this->correct_value]])
        ])->assertJson(['type' => 'success']);
    }

    private function submissionScore(): float
    {
        return (float)DB::table('submissions')
            ->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->value('score');
    }

    private function pendingScoreExists(): bool
    {
        return DB::table('submitted_work_pending_scores')
            ->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->exists();
    }

    #[TestAttribute]
    public function override_off_with_manual_approval_keeps_score_and_does_not_ask_for_work()
    {
        $this->setUpSubmittedWork(1, 'required with manual approval', 0);
        $response = $this->submitCorrectAnswer();

        $this->assertStringNotContainsString('Submit Work', $response->json('message'));
        $this->assertFalse($this->pendingScoreExists());
        $this->assertEquals($this->points, $this->submissionScore());
    }

    #[TestAttribute]
    public function override_off_with_auto_approval_keeps_score_and_does_not_ask_for_work()
    {
        $this->setUpSubmittedWork(1, 'required with auto-approval', 0);
        $response = $this->submitCorrectAnswer();

        $this->assertStringNotContainsString('Submit Work', $response->json('message'));
        $this->assertFalse($this->pendingScoreExists());
        $this->assertEquals($this->points, $this->submissionScore());
    }

    #[TestAttribute]
    public function override_on_with_manual_approval_still_moves_score_to_pending()
    {
        $this->setUpSubmittedWork(1, 'required with manual approval', 1);
        $response = $this->submitCorrectAnswer();

        $this->assertStringContainsString('Submit Work', $response->json('message'));
        $this->assertTrue($this->pendingScoreExists());
        $this->assertEquals(0, $this->submissionScore());
        $this->assertEquals($this->points, (float)DB::table('submitted_work_pending_scores')
            ->where('user_id', $this->student_user->id)
            ->where('question_id', $this->question->id)
            ->value('score'));
    }

    #[TestAttribute]
    public function override_on_is_ignored_when_assignment_does_not_allow_submitted_work()
    {
        $this->setUpSubmittedWork(0, 'required with manual approval', 1);
        $response = $this->submitCorrectAnswer();

        $this->assertStringNotContainsString('Submit Work', $response->json('message'));
        $this->assertFalse($this->pendingScoreExists());
        $this->assertEquals($this->points, $this->submissionScore());
    }

    #[TestAttribute]
    public function no_override_falls_back_to_assignment_setting()
    {
        $this->setUpSubmittedWork(1, 'required with manual approval', null);
        $response = $this->submitCorrectAnswer();

        $this->assertStringContainsString('Submit Work', $response->json('message'));
        $this->assertTrue($this->pendingScoreExists());
        $this->assertEquals(0, $this->submissionScore());
    }

    #[TestAttribute]
    public function no_override_with_assignment_not_allowing_work_keeps_score()
    {
        $this->setUpSubmittedWork(0, 'required with manual approval', null);
        $response = $this->submitCorrectAnswer();

        $this->assertStringNotContainsString('Submit Work', $response->json('message'));
        $this->assertFalse($this->pendingScoreExists());
        $this->assertEquals($this->points, $this->submissionScore());
    }
}
