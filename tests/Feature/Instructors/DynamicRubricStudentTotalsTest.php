<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Question;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The student's dynamic rubric view gets a total that matches their score: cap, late penalty and overrides.
 */
class DynamicRubricStudentTotalsTest extends TestCase
{
    private $user;
    private $student_user;
    private $assignment;
    private $question;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $section = factory(Section::class)->create(['course_id' => $course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $section->id,
            'course_id' => $course->id
        ]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true,
            'rubric_items' => [['id' => 'a', 'title' => 'Correct ring', 'description' => '', 'points' => 4],
                ['id' => 'b', 'title' => 'Correct arrow', 'description' => '', 'points' => 6]]])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        SubmissionFile::create([
            'type' => 'q',
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'date_submitted' => Carbon::now()]);
    }

    private function grade(array $applied_item_ids, float $adjustment)
    {
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode($applied_item_ids),
            'adjustment' => $adjustment,
            'rubric_version' => 1]);
    }

    private function score(): ?array
    {
        return $this->actingAs($this->student_user)
            ->getJson("/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student_user->id}")
            ->assertJson(['type' => 'success'])
            ->json('dynamic_rubric_state.score');
    }

    #[Test]
    public function the_total_adds_the_criteria_and_the_adjustment()
    {
        $this->grade(['b'], 1);
        $this->assertEquals(['raw' => 7, 'final' => 7, 'out_of' => 10, 'capped' => false,
            'late_penalty_percent' => 0, 'has_override' => false], $this->score());
    }

    #[Test]
    public function the_total_is_capped_at_the_question_points()
    {
        $this->grade(['a', 'b'], 2);
        $score = $this->score();
        $this->assertEquals(10, $score['final']);
        $this->assertTrue($score['capped']);
    }

    #[Test]
    public function the_total_includes_the_late_penalty_and_flags_an_override()
    {
        $this->grade(['b'], 0);
        DB::table('submission_files')->where('user_id', $this->student_user->id)->update(['applied_late_penalty' => 10]);
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '9',
            'created_at' => now(),
            'updated_at' => now()]);
        $score = $this->score();
        $this->assertEquals(5.4, $score['final']);
        $this->assertEquals(10, $score['late_penalty_percent']);
        $this->assertTrue($score['has_override']);
    }

    #[Test]
    public function no_total_is_sent_before_scores_are_released()
    {
        $this->grade(['b'], 1);
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 0]);
        $this->assertNull($this->score());
    }
}
