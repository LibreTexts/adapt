<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\Helpers\DynamicRubric;
use App\Helpers\DynamicRubricConverter;
use App\Mail\RubricScoreChanged;
use App\Question;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Stage 6: converting a static rubric that students were scored with to dynamic scoring.
 * Criteria: A (weight 2) and B (weight 3) on a 10-point question convert to A = 4 and B = 6.
 */
class DynamicRubricConversionTest extends TestCase
{
    private $user;
    private $course;
    private $section;
    private $assignment;
    private $question;
    private $students = [];

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)
            ->update(['show_scores' => 0, 'graders_can_see_student_names' => 1]);
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 0]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'rubric_shown' => true,
            'rubric_items' => [
                ['title' => 'Criterion A', 'description' => 'first', 'points' => 2],
                ['title' => 'Criterion B', 'description' => '', 'points' => 3]]])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        $this->students['clean_full'] = $this->scoredStudent([4, 6], 10);
        $this->students['clean_a_only'] = $this->scoredStudent([4, 0], 4);
        $this->students['partial'] = $this->scoredStudent([2, 6], 8);
        $this->students['typed_score'] = $this->scoredStudent([4, 6], 9);
        $this->students['override'] = $this->scoredStudent([2, 0], 2);
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->students['override']->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '7',
            'created_at' => now(),
            'updated_at' => now()]);
        //a student who dropped: a breakdown but no submission file
        $this->students['dropped'] = factory(User::class)->create(['role' => 3]);
        $this->insertBreakdown($this->students['dropped'], [4, 3]);
    }

    private function insertBreakdown(User $student, array $points)
    {
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $student->id,
            'points_breakdown' => json_encode(['rubric_items' => [
                ['title' => 'Criterion A', 'description' => 'first', 'points' => $points[0]],
                ['title' => 'Criterion B', 'description' => '', 'points' => $points[1]]]]),
            'created_at' => now(),
            'updated_at' => now()]);
    }

    private function scoredStudent(array $points, float $score): User
    {
        $student = factory(User::class)->create(['role' => 3]);
        factory(Enrollment::class)->create([
            'user_id' => $student->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);
        SubmissionFile::create([
            'type' => 'q',
            'user_id' => $student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'score' => $score,
            'text_feedback' => 'Good start',
            'date_submitted' => Carbon::now()->subDay(),
            'date_graded' => Carbon::now()]);
        $this->insertBreakdown($student, $points);
        return $student;
    }

    private function url(string $suffix): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function preview(): array
    {
        return $this->actingAs($this->user)->postJson($this->url('/convert-preview'))
            ->assertJson(['type' => 'success'])
            ->json();
    }

    private function convert(string $choice, bool $email_students = false, ?string $fingerprint = null)
    {
        $fingerprint = $fingerprint ?? $this->preview()['fingerprint'];
        return $this->actingAs($this->user)->patchJson($this->url('/convert'),
            ['choice' => $choice, 'email_students' => $email_students, 'fingerprint' => $fingerprint]);
    }

    private function breakdown(string $student)
    {
        return DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->students[$student]->id)
            ->first();
    }

    private function submissionFile(string $student)
    {
        return DB::table('submission_files')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->students[$student]->id)
            ->first();
    }

    private function convertedRubric(): array
    {
        return json_decode(DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first()
            ->custom_rubric, true);
    }

    private function itemId(int $index): string
    {
        return $this->convertedRubric()['rubric_items'][$index]['id'];
    }

    #[Test]
    public function the_preview_sorts_students_and_saves_nothing()
    {
        $preview = $this->preview()['preview'];
        $this->assertEquals([['title' => 'Criterion A', 'points' => 4], ['title' => 'Criterion B', 'points' => 6]], $preview['converted_items']);
        $this->assertEquals(2, $preview['number_clean']);
        $would_change = collect($preview['students_would_change'])->keyBy('name');
        $this->assertCount(2, $would_change);
        $partial = $would_change["{$this->students['partial']->first_name} {$this->students['partial']->last_name}"];
        $this->assertEquals(8, $partial['old_score']);
        $this->assertEquals(6, $partial['mapped_score']);
        $this->assertEquals(1, $partial['partial_criteria']);
        $typed = $would_change["{$this->students['typed_score']->first_name} {$this->students['typed_score']->last_name}"];
        $this->assertEquals(9, $typed['old_score']);
        $this->assertEquals(10, $typed['mapped_score']);
        $this->assertCount(1, $preview['students_not_affected_override']);

        $this->assertNull(DB::table('assignment_question')->where('assignment_id', $this->assignment->id)->first()->custom_rubric);
        $this->assertNull($this->breakdown('partial')->applied_item_ids);
    }

    #[Test]
    public function keeping_scores_adds_an_adjustment_and_flags_the_students_for_review()
    {
        $this->convert('keep')->assertJson(['type' => 'success', 'number_of_students_flagged' => 2, 'number_of_students_cleared' => 0]);
        $rubric = $this->convertedRubric();
        $this->assertEquals('dynamic', $rubric['scoring_style']);
        $this->assertEquals(1, $rubric['version']);
        $this->assertTrue($rubric['rubric_shown']);
        $this->assertEquals('first', $rubric['rubric_items'][0]['description']);
        [$a, $b] = [$this->itemId(0), $this->itemId(1)];

        $partial = $this->breakdown('partial');
        $this->assertEquals([$b], json_decode($partial->applied_item_ids, true));
        $this->assertEquals(2, (float)$partial->adjustment);
        $this->assertEquals(1, (int)$partial->needs_review);
        $this->assertEquals(8, (float)$this->submissionFile('partial')->score);

        $typed = $this->breakdown('typed_score');
        $this->assertEquals([$a, $b], json_decode($typed->applied_item_ids, true));
        $this->assertEquals(-1, (float)$typed->adjustment);
        $this->assertEquals(1, (int)$typed->needs_review);

        $clean = $this->breakdown('clean_a_only');
        $this->assertEquals([$a], json_decode($clean->applied_item_ids, true));
        $this->assertEquals(0, (float)$clean->adjustment);
        $this->assertEquals(0, (int)$clean->needs_review);

        $override = $this->breakdown('override');
        $this->assertEquals([], json_decode($override->applied_item_ids, true));
        $this->assertEquals(2, (float)$override->adjustment);
        $this->assertEquals(0, (int)$override->needs_review);

        $dropped = $this->breakdown('dropped');
        $this->assertEquals([$a], json_decode($dropped->applied_item_ids, true));
        $this->assertEquals(0, (int)$dropped->needs_review);

        $this->assertEquals('convert', DB::table('rubric_change_logs')->first()->action);
        $this->assertEquals(6, DB::table('rubric_score_logs')->where('reason', 'converted_to_dynamic')->count());
    }

    #[Test]
    public function saving_a_flagged_student_keeps_the_score_and_clears_the_flag()
    {
        $this->convert('keep');
        $this->actingAs($this->user)->postJson('/api/grading', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->students['partial']->id,
            'text_feedback_editor' => 'plain',
            'question_submission_score' => 0,
            'textFeedback' => 'Good start',
            'dynamic_rubric' => ['applied_item_ids' => [$this->itemId(1)], 'adjustment' => 2, 'rubric_version' => 1]])
            ->assertJson(['type' => 'success']);
        $this->assertEquals(0, (int)$this->breakdown('partial')->needs_review);
        $this->assertEquals(8, (float)$this->submissionFile('partial')->score);
    }

    #[Test]
    public function clearing_makes_the_changed_students_ungraded_and_keeps_their_feedback()
    {
        $this->convert('clear')->assertJson(['type' => 'success', 'number_of_students_flagged' => 0, 'number_of_students_cleared' => 2]);
        foreach (['partial', 'typed_score'] as $student) {
            $this->assertNull($this->breakdown($student));
            $submission_file = $this->submissionFile($student);
            $this->assertNull($submission_file->score);
            $this->assertNull($submission_file->date_graded);
            $this->assertEquals('Good start', $submission_file->text_feedback);
        }
        $this->assertEquals(0, (int)$this->breakdown('clean_full')->needs_review);
        $this->assertEquals(10, (float)$this->submissionFile('clean_full')->score);
        $this->assertNotNull($this->breakdown('override'));
        $this->assertEquals(2, DB::table('rubric_change_logs')->first()->students_rescored);
    }

    #[Test]
    public function cleared_students_are_emailed_only_when_asked_and_scores_are_released()
    {
        Mail::fake();
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->convert('clear', true)->assertJson(['type' => 'success', 'number_of_students_emailed' => 2]);
        Mail::assertQueued(RubricScoreChanged::class, 2);
        Mail::assertQueued(RubricScoreChanged::class, function ($mail) {
            return $mail->cleared === true;
        });
    }

    #[Test]
    public function the_cleared_email_renders()
    {
        $html = (new RubricScoreChanged('Ada', 'Lab 1', 'Mechanism', '8', '', '10', 'https://example.org', true))->render();
        $this->assertStringContainsString('was cleared', $html);
        $this->assertStringNotContainsString('changed from', $html);
    }

    #[Test]
    public function a_conversion_is_refused_if_scores_changed_since_the_preview()
    {
        $fingerprint = $this->preview()['fingerprint'];
        DB::table('submission_files')->where('user_id', $this->students['partial']->id)->update(['score' => 7]);
        $this->convert('keep', false, $fingerprint)
            ->assertJson(['type' => 'error', 'message' => DynamicRubricConverter::CHANGED_MESSAGE]);
        $this->assertNull(DB::table('assignment_question')->where('assignment_id', $this->assignment->id)->first()->custom_rubric);
    }

    #[Test]
    public function only_instructors_can_convert_and_a_choice_is_required()
    {
        $grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $grader_user->id, 'section_id' => $this->section->id]);
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $grader_user->id]);
        $this->actingAs($grader_user)->postJson($this->url('/convert-preview'))
            ->assertJson(['type' => 'error', 'message' => DynamicRubricConverter::ONLY_INSTRUCTORS_MESSAGE]);
        $this->actingAs($grader_user)->patchJson($this->url('/convert'), ['choice' => 'keep', 'fingerprint' => 'x'])
            ->assertJson(['type' => 'error', 'message' => DynamicRubricConverter::ONLY_INSTRUCTORS_MESSAGE]);
        $this->convert('neither')->assertJson(['type' => 'error']);
        $this->assertNull(DB::table('assignment_question')->where('assignment_id', $this->assignment->id)->first()->custom_rubric);
    }

    #[Test]
    public function a_dynamic_rubric_cannot_be_converted_again()
    {
        $this->convert('keep');
        $this->actingAs($this->user)->postJson($this->url('/convert-preview'))
            ->assertJson(['type' => 'error', 'message' => DynamicRubricConverter::ALREADY_DYNAMIC_MESSAGE]);
    }

    #[Test]
    public function full_credit_allows_for_rounding_and_late_penalties_are_kept()
    {
        $items = [['id' => 'a', 'points' => 4], ['id' => 'b', 'points' => 6]];
        $this->assertEquals(['a'], DynamicRubricConverter::mapStaticItems([['points' => 3.995], ['points' => '']], $items)['applied_item_ids']);
        $this->assertTrue(DynamicRubricConverter::mapStaticItems([['points' => 3.9], ['points' => 0]], $items)['partial']);

        //old score 7.2 with a 10% late penalty: B (6) plus an adjustment of 2 gives 8, less 10% = 7.2
        $rubric = ['scoring_style' => 'dynamic', 'rubric_items' => [['id' => 'a', 'points' => 4], ['id' => 'b', 'points' => 6]]];
        $adjustment = DynamicRubricConverter::keepScoreAdjustment($rubric, ['b'], 7.2, 10, 10);
        $this->assertEquals(2, $adjustment);
        $this->assertEquals(7.2, DynamicRubric::score($rubric, ['b'], $adjustment, 10, 10)['final']);

        //thirds of 10 points round to 3.33 each: full credit was 10, the criteria give 9.99
        $thirds = DynamicRubricConverter::convertedRubric(['rubric_items' => [['points' => 1], ['points' => 1], ['points' => 1]]], 10);
        $ids = DynamicRubric::itemIds($thirds);
        $this->assertEquals(9.99, DynamicRubric::score($thirds, $ids, 0, 10, null)['final']);
        $this->assertLessThanOrEqual(DynamicRubricConverter::roundingTolerance(3), 0.01);
        $this->assertEquals(0.01, DynamicRubricConverter::keepScoreAdjustment($thirds, $ids, 10, 10, null));
    }

    #[Test]
    public function the_preview_shows_the_math_for_each_listed_student()
    {
        $partial = collect($this->preview()['preview']['students_would_change'])
            ->firstWhere('name', "{$this->students['partial']->first_name} {$this->students['partial']->last_name}");
        $math = $partial['math'];
        $this->assertEquals([
            ['title' => 'Criterion A', 'static_points' => 2, 'full_points' => 4, 'status' => 'partial', 'dynamic_points' => 0],
            ['title' => 'Criterion B', 'static_points' => 6, 'full_points' => 6, 'status' => 'full', 'dynamic_points' => 6]],
            $math['criteria']);
        $this->assertEquals(8, $math['now_total']);
        $this->assertEquals(6, $math['criteria_total']);
        $this->assertEquals(6, $math['converted_total']);
        $this->assertEquals(2, $math['keep_adjustment']);
    }
}
