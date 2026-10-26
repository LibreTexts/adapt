<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\Helpers\DynamicRubricEditor;
use App\Helpers\StaticRubricConverter;
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
 * Stage 7: converting a dynamic rubric that students were graded with back to static scoring.
 * Criteria: A = 4, B = 6, Units = 0 (feedback only) on a 10-point question, at version 3.
 */
class StaticRubricConversionTest extends TestCase
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
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10,
            'use_existing_rubric' => 0,
            'custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 3, 'rubric_shown' => true,
                'rubric_items' => [
                    ['id' => 'a', 'title' => 'Criterion A', 'description' => 'first', 'points' => 4],
                    ['id' => 'b', 'title' => 'Criterion B', 'description' => '', 'points' => 6],
                    ['id' => 'u', 'title' => 'Check your units', 'description' => '', 'points' => 0]]])
        ]);
        $this->students['clean'] = $this->gradedStudent(['a', 'b'], 0, 10);
        $this->students['with_units'] = $this->gradedStudent(['a', 'u'], 0, 4);
        $this->students['adjusted'] = $this->gradedStudent(['b'], 2, 8);
        $this->students['override'] = $this->gradedStudent(['a'], 1, 5);
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->students['override']->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '9',
            'created_at' => now(),
            'updated_at' => now()]);
        $this->students['dropped'] = factory(User::class)->create(['role' => 3]);
        $this->insertBreakdown($this->students['dropped'], ['a'], 0);
    }

    private function insertBreakdown(User $student, array $applied_item_ids, float $adjustment)
    {
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $student->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode($applied_item_ids),
            'adjustment' => $adjustment,
            'needs_review' => 0,
            'rubric_version' => 3,
            'created_at' => now(),
            'updated_at' => now()]);
    }

    private function gradedStudent(array $applied_item_ids, float $adjustment, float $score): User
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
        $this->insertBreakdown($student, $applied_item_ids, $adjustment);
        return $student;
    }

    private function url(string $suffix): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function preview(): array
    {
        return $this->actingAs($this->user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'success'])
            ->json();
    }

    private function convert(string $choice, bool $email_students = false, ?string $fingerprint = null)
    {
        $fingerprint = $fingerprint ?? $this->preview()['fingerprint'];
        return $this->actingAs($this->user)->patchJson($this->url('/convert-to-static'),
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

    private function staticPoints(string $student): array
    {
        return array_map(function ($item) {
            return (float)$item['points'];
        }, json_decode($this->breakdown($student)->points_breakdown, true)['rubric_items']);
    }

    private function submissionFile(string $student)
    {
        return DB::table('submission_files')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $this->students[$student]->id)
            ->first();
    }

    private function assignmentRubric(): array
    {
        return json_decode(DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first()
            ->custom_rubric, true);
    }

    #[Test]
    public function the_preview_explains_what_changes_and_saves_nothing()
    {
        $preview = $this->preview()['preview'];
        $this->assertEquals('to_static', $preview['direction']);
        $this->assertEquals([['title' => 'Criterion A', 'points' => 4], ['title' => 'Criterion B', 'points' => 6]], $preview['converted_items']);
        $this->assertEquals(['Check your units'], $preview['dropped_criteria']);
        $this->assertEquals(2, $preview['number_clean']);
        $this->assertCount(1, $preview['students_would_change']);
        $adjusted = $preview['students_would_change'][0];
        $this->assertEquals(8, $adjusted['old_score']);
        $this->assertEquals(6, $adjusted['mapped_score']);
        $this->assertStringContainsString('adjustment of +2', $adjusted['reason']);
        $this->assertCount(1, $preview['students_not_affected_override']);
        $this->assertEquals('dynamic', $this->assignmentRubric()['scoring_style']);
    }

    #[Test]
    public function keeping_scores_writes_static_breakdowns_and_flags_the_changed_students()
    {
        $this->convert('keep')->assertJson(['type' => 'success', 'number_of_students_flagged' => 1, 'number_of_students_cleared' => 0]);
        $rubric = $this->assignmentRubric();
        $this->assertArrayNotHasKey('scoring_style', $rubric);
        $this->assertEquals([['title' => 'Criterion A', 'description' => 'first', 'points' => 4],
            ['title' => 'Criterion B', 'description' => '', 'points' => 6]], $rubric['rubric_items']);
        $this->assertTrue($rubric['rubric_shown']);

        $this->assertEquals([4, 6], $this->staticPoints('clean'));
        $this->assertEquals([4, 0], $this->staticPoints('with_units'));
        $this->assertEquals([0, 6], $this->staticPoints('adjusted'));
        $adjusted = $this->breakdown('adjusted');
        $this->assertNull($adjusted->applied_item_ids);
        $this->assertEquals(0, (float)$adjusted->adjustment);
        $this->assertEquals(1, (int)$adjusted->needs_review);
        $this->assertEquals(8, (float)$this->submissionFile('adjusted')->score);
        $this->assertEquals(0, (int)$this->breakdown('clean')->needs_review);
        $this->assertEquals(0, (int)$this->breakdown('override')->needs_review);
        $this->assertEquals([4, 0], $this->staticPoints('dropped'));

        $log = DB::table('rubric_change_logs')->first();
        $this->assertEquals('convert_to_static', $log->action);
        $this->assertEquals(4, json_decode($log->after, true)['version']);
        $this->assertFalse(DynamicRubricEditor::hasDynamicScores($this->assignment->id, $this->question->id));
    }

    #[Test]
    public function saving_a_flagged_static_student_clears_the_flag_even_without_changes()
    {
        $this->convert('keep');
        $this->actingAs($this->user)->postJson('/api/grading', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->students['adjusted']->id,
            'text_feedback_editor' => 'plain',
            'question_submission_score' => 0,
            'file_submission_score' => 8,
            'textFeedback' => 'Good start',
            'rubric_points_breakdown' => [
                ['title' => 'Criterion A', 'description' => 'first', 'points' => 0],
                ['title' => 'Criterion B', 'description' => '', 'points' => 6]]])
            ->assertJson(['type' => 'success']);
        $this->assertEquals(0, (int)$this->breakdown('adjusted')->needs_review);
    }

    #[Test]
    public function clearing_makes_the_changed_students_ungraded()
    {
        $this->convert('clear')->assertJson(['type' => 'success', 'number_of_students_cleared' => 1]);
        $this->assertNull($this->breakdown('adjusted'));
        $submission_file = $this->submissionFile('adjusted');
        $this->assertNull($submission_file->score);
        $this->assertNull($submission_file->date_graded);
        $this->assertEquals('Good start', $submission_file->text_feedback);
        $this->assertEquals(10, (float)$this->submissionFile('clean')->score);
    }

    #[Test]
    public function cleared_students_are_emailed_only_when_asked_and_scores_are_released()
    {
        Mail::fake();
        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->convert('clear', true)->assertJson(['type' => 'success', 'number_of_students_emailed' => 1]);
        Mail::assertQueued(RubricScoreChanged::class, 1);
    }

    #[Test]
    public function the_conversion_is_refused_when_things_changed_or_cant_be_converted()
    {
        $fingerprint = $this->preview()['fingerprint'];
        DB::table('rubric_points_breakdowns')->where('user_id', $this->students['clean']->id)->update(['adjustment' => 1]);
        $this->convert('keep', false, $fingerprint)
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::CHANGED_MESSAGE]);
        $this->assertEquals('dynamic', $this->assignmentRubric()['scoring_style']);

        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->update(['custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 3, 'rubric_shown' => true,
                'rubric_items' => [['id' => 'u', 'title' => 'Check your units', 'description' => '', 'points' => 0]]])]);
        $this->actingAs($this->user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::ONLY_ZERO_POINT_CRITERIA_MESSAGE]);
    }

    #[Test]
    public function only_instructors_can_convert_and_a_static_rubric_cant_be_converted_to_static()
    {
        $grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $grader_user->id, 'section_id' => $this->section->id]);
        DB::table('rubric_editors')->insert(['course_id' => $this->course->id, 'user_id' => $grader_user->id]);
        $this->actingAs($grader_user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::ONLY_INSTRUCTORS_MESSAGE]);
        $this->actingAs($grader_user)->patchJson($this->url('/convert-to-static'), ['choice' => 'keep', 'fingerprint' => 'x'])
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::ONLY_INSTRUCTORS_MESSAGE]);
        $this->convert('neither')->assertJson(['type' => 'error']);

        $this->convert('keep');
        $this->actingAs($this->user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::ALREADY_STATIC_MESSAGE]);
    }

    #[Test]
    public function converting_back_to_dynamic_continues_the_version_numbers_and_history_stays_available()
    {
        $this->convert('keep');
        $this->actingAs($this->user)->getJson($this->url(''))
            ->assertJson(['type' => 'success', 'is_dynamic' => false, 'has_history' => true]);
        $fingerprint = $this->actingAs($this->user)->postJson($this->url('/convert-preview'))
            ->assertJson(['type' => 'success'])
            ->json('fingerprint');
        $this->actingAs($this->user)->patchJson($this->url('/convert'), ['choice' => 'keep', 'fingerprint' => $fingerprint])
            ->assertJson(['type' => 'success']);
        $this->assertEquals(5, $this->assignmentRubric()['version']);
    }

    #[Test]
    public function full_points_are_rounded_exactly_like_the_static_grader()
    {
        //JavaScript: Math.round(1.005 * 100) / 100 = 1 (1.005 * 100 is 100.49999...)
        $this->assertEquals(1.0, StaticRubricConverter::roundLikeTheGrader(1.005));
        $this->assertEquals(4.29, StaticRubricConverter::roundLikeTheGrader(3 * 10 / 7));
        $this->assertEquals(3.33, StaticRubricConverter::roundLikeTheGrader(10 / 3));
        $this->assertEquals(2.5, StaticRubricConverter::roundLikeTheGrader(2.5));
    }

    #[Test]
    public function the_blocked_static_actions_explain_how_to_change_the_rubric()
    {
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/delete-custom-rubric")
            ->assertJson(['type' => 'error', 'message' => DynamicRubricEditor::HAS_DYNAMIC_SCORES_MESSAGE]);
        $this->assertStringContainsString('choosing Static converts', DynamicRubricEditor::HAS_DYNAMIC_SCORES_MESSAGE);
    }

    #[Test]
    public function the_preview_shows_the_math_for_each_listed_student()
    {
        $math = $this->preview()['preview']['students_would_change'][0]['math'];
        $this->assertEquals([
            ['title' => 'Criterion A', 'applied' => false, 'now_points' => 0, 'removed' => false, 'static_points' => 0],
            ['title' => 'Criterion B', 'applied' => true, 'now_points' => 6, 'removed' => false, 'static_points' => 6],
            ['title' => 'Check your units', 'applied' => false, 'now_points' => 0, 'removed' => true, 'static_points' => null]],
            $math['criteria']);
        $this->assertEquals(2, $math['adjustment']);
        $this->assertEquals(8, $math['now_raw']);
        $this->assertEquals(8, $math['now_total']);
        $this->assertEquals(6, $math['converted_total']);
        $this->assertEquals(10, $math['scale_from']);
        $this->assertEquals(10, $math['scale_to']);
        $this->assertEquals(1, $math['scale']);
    }
}
