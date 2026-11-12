<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Helpers\DynamicRubric;
use App\Helpers\StaticRubricConverter;
use App\Question;
use App\Section;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dynamic rubrics whose checked items take points off full credit ("checked_items": "take_off").
 */
class DynamicRubricTakeOffPointsTest extends TestCase
{
    private $user;
    private $student_a;
    private $student_b;
    private $course;
    private $section;
    private $assignment;
    private $question;
    private $rubric_items;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)
            ->update(['show_scores' => 1, 'graders_can_see_student_names' => 1]);
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 0]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        $this->rubric_items = [
            ['id' => 'a', 'title' => 'Sign error', 'description' => '', 'points' => 2],
            ['id' => 'b', 'title' => 'Missing units', 'description' => '', 'points' => 1],
            ['id' => 'c', 'title' => 'No work shown', 'description' => '', 'points' => 3]];
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true, 'checked_items' => 'take_off',
            'rubric_items' => $this->rubric_items])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        $this->student_a = $this->enrollStudentWithSubmission();
        $this->student_b = $this->enrollStudentWithSubmission();
    }

    private function enrollStudentWithSubmission(): User
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
            'date_submitted' => Carbon::now()]);
        return $student;
    }

    private function grade(User $student, array $applied_item_ids, float $adjustment = 0, float $auto_graded_score = 0)
    {
        return $this->actingAs($this->user)->postJson('/api/grading', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $student->id,
            'text_feedback_editor' => 'plain',
            'question_submission_score' => $auto_graded_score,
            'textFeedback' => '',
            'dynamic_rubric' => ['applied_item_ids' => $applied_item_ids, 'adjustment' => $adjustment, 'rubric_version' => 1]]);
    }

    private function rubricScore(User $student): float
    {
        return (float)DB::table('submission_files')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $student->id)
            ->first()
            ->score;
    }

    private function url(string $suffix = ''): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function currentRubric(): array
    {
        return json_decode(DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first()
            ->custom_rubric, true);
    }

    #[Test]
    public function checked_items_take_points_off_full_credit()
    {
        $this->grade($this->student_a, ['a'])->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 8]]);
        $this->assertEquals(8, $this->rubricScore($this->student_a));
        $this->grade($this->student_b, ['a', 'c'], 1)->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 6]]);
        $this->assertEquals(6, $this->rubricScore($this->student_b));
    }

    #[Test]
    public function nothing_checked_is_full_credit()
    {
        $this->grade($this->student_a, [])->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 10]]);
    }

    #[Test]
    public function full_credit_are_the_question_points_minus_the_auto_graded_score()
    {
        $this->grade($this->student_a, ['a'], 0, 4)->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 4]]);
    }

    #[Test]
    public function the_score_never_goes_below_zero_or_above_full_credit()
    {
        $this->grade($this->student_a, ['a', 'b', 'c'], -5)
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['raw' => -1, 'final' => 0]]);
        $this->grade($this->student_b, [], 3)
            ->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['raw' => 13, 'final' => 10]]);
    }

    #[Test]
    public function no_credit_takes_off_full_credit_with_the_adjustment()
    {
        //what the Open Grader's "No Credit & Next" sends for a take-off rubric
        $this->grade($this->student_a, [], -10)->assertJson(['type' => 'success', 'dynamic_rubric_score' => ['final' => 0]]);
    }

    #[Test]
    public function without_a_limit_taking_off_points_works_out_like_adding()
    {
        $rubric = ['checked_items' => 'take_off', 'rubric_items' => $this->rubric_items];
        $this->assertEquals(2.0, DynamicRubric::score($rubric, ['a'], 0, INF, 0)['raw']);
        $this->assertEquals(8.0, DynamicRubric::score($rubric, ['a'], 0, 10, 0)['final']);
        $this->assertEquals(2.0, DynamicRubric::score(['rubric_items' => $this->rubric_items], ['a'], 0, 10, 0)['final']);
    }

    #[Test]
    public function switching_to_add_points_previews_and_rescores_graded_students()
    {
        $this->grade($this->student_a, ['a']); //8 taking off; 2 adding
        $proposal = ['rubric_items' => $this->rubric_items, 'rubric_shown' => true, 'base_version' => 1, 'checked_items' => 'add'];
        $preview = $this->actingAs($this->user)->postJson($this->url('/preview'), $proposal)
            ->assertJson(['type' => 'success'])
            ->json('preview');
        $this->assertCount(1, $preview['students_changed']);
        $this->assertEquals(8, $preview['students_changed'][0]['old_score']);
        $this->assertEquals(2, $preview['students_changed'][0]['new_score']);
        $this->assertEquals(8, $this->rubricScore($this->student_a)); //nothing saved yet

        $this->actingAs($this->user)->patchJson($this->url(), $proposal)
            ->assertJson(['type' => 'success', 'number_of_students_rescored' => 1]);
        $this->assertEquals(2, $this->rubricScore($this->student_a));
        $this->assertArrayNotHasKey('checked_items', $this->currentRubric());
    }

    #[Test]
    public function the_same_setting_and_items_is_no_change()
    {
        $this->grade($this->student_a, ['a']);
        $this->actingAs($this->user)->patchJson($this->url(), ['rubric_items' => $this->rubric_items, 'rubric_shown' => true,
            'base_version' => 1, 'checked_items' => 'take_off'])
            ->assertJson(['type' => 'info', 'message' => 'There are no changes to save.']);
    }

    #[Test]
    public function leaving_the_setting_out_keeps_it()
    {
        $this->grade($this->student_a, ['a']);
        $items = $this->rubric_items;
        $items[0]['points'] = 3;
        $this->actingAs($this->user)->patchJson($this->url(), ['rubric_items' => $items, 'rubric_shown' => true, 'base_version' => 1])
            ->assertJson(['type' => 'success']);
        $this->assertEquals('take_off', $this->currentRubric()['checked_items']);
        $this->assertEquals(7, $this->rubricScore($this->student_a));
    }

    #[Test]
    public function an_unknown_setting_is_rejected()
    {
        $this->actingAs($this->user)->postJson($this->url('/preview'), ['rubric_items' => $this->rubric_items,
            'rubric_shown' => true, 'base_version' => 1, 'checked_items' => 'subtract'])
            ->assertJson(['type' => 'error', 'message' => 'Please choose whether checked items add points or take off points.']);
    }

    #[Test]
    public function a_take_off_rubric_cannot_be_converted_to_static()
    {
        $this->grade($this->student_a, ['a']);
        $this->actingAs($this->user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::TAKE_OFF_POINTS_MESSAGE]);
    }

    #[Test]
    public function the_student_total_starts_from_full_credit()
    {
        $this->grade($this->student_a, ['a', 'b']);
        $score = $this->actingAs($this->student_a)
            ->getJson("/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student_a->id}")
            ->assertJson(['type' => 'success'])
            ->json('dynamic_rubric_state.score');
        $this->assertEquals(7, $score['final']);
        $this->assertEquals(10, $score['out_of']);
    }

    #[Test]
    public function templates_keep_the_setting()
    {
        $this->actingAs($this->user)->postJson('/api/rubric-templates', [
            'name' => 'Deductions',
            'description' => 'Take off points',
            'save_as_template' => true,
            'rubric_shown' => true,
            'scoring_style' => 'dynamic',
            'checked_items' => 'take_off',
            'rubric_items' => [['title' => 'Sign error', 'description' => '', 'points' => 2]]])
            ->assertJson(['type' => 'success']);
        $rubric = json_decode(DB::table('rubric_templates')->where('name', 'Deductions')->first()->rubric, true);
        $this->assertEquals('dynamic', $rubric['scoring_style']);
        $this->assertEquals('take_off', $rubric['checked_items']);
    }

    #[Test]
    public function rubrics_that_add_points_are_saved_exactly_as_before()
    {
        $rubric = DynamicRubric::prepareQuestionRubric([['title' => 'Correct', 'points' => 2]], true, null);
        $this->assertArrayNotHasKey('checked_items', $rubric);
        $rubric = DynamicRubric::prepareQuestionRubric([['title' => 'Sign error', 'points' => 2]], true, null, 'take_off');
        $this->assertEquals('take_off', $rubric['checked_items']);
        $this->assertArrayNotHasKey('checked_items', DynamicRubric::templateRubric([['title' => 'Correct', 'points' => 2]], true, 'dynamic'));
        $this->assertArrayNotHasKey('checked_items', DynamicRubric::templateRubric([['title' => 'Correct', 'points' => 2]], true, 'static', 'take_off'));
    }
}
