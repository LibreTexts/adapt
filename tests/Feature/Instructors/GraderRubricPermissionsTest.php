<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\Helpers\RubricEditPermission;
use App\Helpers\StaticRubricConverter;
use App\Helpers\StudentAnonymizer;
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
 * The options an instructor can give a grader on top of "Can edit rubric", and hidden student names.
 * The rubric is dynamic: A = 4, B = 6 on a 10-point question. The student has B plus a +2 adjustment, so converting
 * to static would change their score.
 */
class GraderRubricPermissionsTest extends TestCase
{
    private $instructor;
    private $course;
    private $assignment;
    private $question;
    private $grader_user;
    private $student;

    public function setup(): void
    {
        parent::setUp();
        $this->instructor = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->instructor->id]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        DB::table('assignments')->where('id', $this->assignment->id)
            ->update(['show_scores' => 1, 'graders_can_see_student_names' => 0]);
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 0]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10,
            'use_existing_rubric' => 0,
            'custom_rubric' => json_encode(['scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true,
                'rubric_items' => [
                    ['id' => 'a', 'title' => 'Criterion A', 'description' => '', 'points' => 4],
                    ['id' => 'b', 'title' => 'Criterion B', 'description' => '', 'points' => 6]]])
        ]);
        $this->grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $section->id]);

        $this->student = factory(User::class)->create(['role' => 3]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        SubmissionFile::create([
            'type' => 'q',
            'user_id' => $this->student->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => 'fake_1.pdf',
            'original_filename' => 'orig_fake_1.pdf',
            'score' => 8,
            'date_submitted' => Carbon::now()->subDay(),
            'date_graded' => Carbon::now()]);
        DB::table('rubric_points_breakdowns')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student->id,
            'points_breakdown' => json_encode(['scoring_style' => 'dynamic', 'rubric_items' => []]),
            'applied_item_ids' => json_encode(['b']),
            'adjustment' => 2,
            'needs_review' => 0,
            'rubric_version' => 1,
            'created_at' => now(),
            'updated_at' => now()]);
    }

    private function allow(array $permissions = [])
    {
        DB::table('rubric_editors')->insert(array_merge(
            ['course_id' => $this->course->id, 'user_id' => $this->grader_user->id], $permissions));
    }

    private function url(string $suffix = ''): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function editorsUrl(): string
    {
        return "/api/courses/{$this->course->id}/rubric-editors/{$this->grader_user->id}";
    }

    private function removeStudentScores()
    {
        DB::table('rubric_points_breakdowns')->delete();
    }

    private function useStaticRubric(?array $rubric_shown_override = null)
    {
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->update(['custom_rubric' => json_encode(array_merge(['rubric_shown' => true, 'rubric_items' => [
                ['title' => 'Criterion A', 'description' => '', 'points' => 10]]], $rubric_shown_override ?? []))]);
    }

    private function proposal(array $overrides = []): array
    {
        return array_merge(['base_version' => 0, 'rubric_shown' => true,
            'rubric_items' => [['title' => 'Criterion A', 'description' => '', 'points' => 10]]], $overrides);
    }

    #[Test]
    public function every_option_starts_off_and_instructors_have_them_all()
    {
        $this->allow();
        $this->assertEquals(array_fill_keys(RubricEditPermission::PERMISSION_COLUMNS, false),
            $this->actingAs($this->grader_user)->getJson($this->url())->json('rubric_permissions'));
        $this->assertEquals(array_fill_keys(RubricEditPermission::PERMISSION_COLUMNS, true),
            $this->actingAs($this->instructor)->getJson($this->url())->json('rubric_permissions'));
    }

    #[Test]
    public function the_scoring_style_option_lets_a_grader_choose_dynamic_when_nobody_has_scores()
    {
        $this->removeStudentScores();
        $this->useStaticRubric();
        $this->allow();
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);

        DB::table('rubric_editors')->update(['can_change_scoring_style' => 1]);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function the_visibility_option_controls_rubric_shown()
    {
        $this->allow();
        $items = [['id' => 'a', 'title' => 'Criterion A', 'description' => '', 'points' => 4],
            ['id' => 'b', 'title' => 'Criterion B', 'description' => '', 'points' => 6]];
        $request = ['base_version' => 1, 'rubric_shown' => false, 'rubric_items' => $items];
        $this->actingAs($this->grader_user)->patchJson($this->url(), $request)
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_RUBRIC_SHOWN_MESSAGE]);

        DB::table('rubric_editors')->update(['can_change_rubric_shown' => 1]);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $request)
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function the_scoring_style_option_alone_cannot_convert_a_graded_rubric()
    {
        $this->allow(['can_change_scoring_style' => 1]);
        $this->actingAs($this->grader_user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'error', 'message' => StaticRubricConverter::ONLY_INSTRUCTORS_MESSAGE]);
    }

    #[Test]
    public function the_convert_option_includes_choosing_the_scoring_style()
    {
        $this->allow(['can_convert_graded_rubrics' => 1]);
        $this->actingAs($this->grader_user)->getJson($this->url())
            ->assertJson(['rubric_permissions' => ['can_change_scoring_style' => true, 'can_convert_graded_rubrics' => true]]);
    }

    #[Test]
    public function a_grader_never_sees_a_real_name_in_a_conversion_preview_when_names_are_hidden()
    {
        $this->allow(['can_convert_graded_rubrics' => 1]);
        $response = $this->actingAs($this->grader_user)->postJson($this->url('/convert-to-static-preview'))
            ->assertJson(['type' => 'success']);
        $preview = $response->json('preview');
        $this->assertTrue($preview['names_hidden']);
        $this->assertEquals(StudentAnonymizer::fullName($this->assignment->id, $this->student->id),
            $preview['students_would_change'][0]['name']);
        $this->assertStringNotContainsString($this->student->email, $response->getContent());
        $this->assertStringNotContainsString("{$this->student->first_name} {$this->student->last_name}", $response->getContent());
    }

    #[Test]
    public function the_instructor_still_sees_real_names_in_a_conversion_preview()
    {
        $preview = $this->actingAs($this->instructor)->postJson($this->url('/convert-to-static-preview'))->json('preview');
        $this->assertFalse($preview['names_hidden']);
        $this->assertEquals("{$this->student->first_name} {$this->student->last_name}", $preview['students_would_change'][0]['name']);
    }

    #[Test]
    public function a_grader_can_convert_and_keep_a_score_without_seeing_the_name()
    {
        $this->allow(['can_convert_graded_rubrics' => 1]);
        $fingerprint = $this->actingAs($this->grader_user)->postJson($this->url('/convert-to-static-preview'))->json('fingerprint');
        $this->actingAs($this->grader_user)->patchJson($this->url('/convert-to-static'), ['choice' => 'keep', 'fingerprint' => $fingerprint])
            ->assertJson(['type' => 'success', 'number_of_students_flagged' => 1]);
        $this->assertEquals(1, (int)DB::table('rubric_points_breakdowns')->first()->needs_review);
        $this->assertEquals(8, (float)DB::table('submission_files')->first()->score);
    }

    #[Test]
    public function a_grader_who_converts_a_rubric_may_email_students()
    {
        Mail::fake();
        $this->allow(['can_convert_graded_rubrics' => 1]);
        $fingerprint = $this->actingAs($this->grader_user)->postJson($this->url('/convert-to-static-preview'))->json('fingerprint');
        $this->actingAs($this->grader_user)->patchJson($this->url('/convert-to-static'),
            ['choice' => 'clear', 'email_students' => true, 'fingerprint' => $fingerprint])
            ->assertJson(['type' => 'success', 'number_of_students_emailed' => 1]);
        Mail::assertQueued(RubricScoreChanged::class, 1);
    }

    private function removeRubric()
    {
        $this->removeStudentScores();
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)
            ->update(['custom_rubric' => null, 'use_existing_rubric' => 1]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => null]);
    }

    #[Test]
    public function a_grader_who_cannot_choose_adds_static_rubrics_by_default()
    {
        $this->removeRubric();
        $this->allow();
        $this->actingAs($this->grader_user)->getJson($this->url())->assertJson(['default_scoring_style' => 'static']);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
        $this->saveStaticRubric('New title')->assertJson(['type' => 'success']);
    }

    #[Test]
    public function the_instructor_can_make_a_graders_new_rubrics_dynamic()
    {
        $this->removeRubric();
        $this->allow();
        $this->actingAs($this->instructor)->patchJson($this->editorsUrl(), ['can_edit' => true, 'default_scoring_style' => 'dynamic'])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->grader_user)->getJson($this->url())->assertJson(['default_scoring_style' => 'dynamic']);
        $this->saveStaticRubric('New title')
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'success']);
    }

    #[Test]
    public function the_default_style_does_not_let_a_grader_change_an_existing_rubric()
    {
        $this->removeStudentScores();
        $this->useStaticRubric();
        $this->allow(['default_scoring_style' => 'dynamic']);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_SCORING_STYLE_MESSAGE]);
    }

    #[Test]
    public function stand_in_names_are_saved_and_unique_within_an_assignment()
    {
        $user_ids = factory(User::class, 60)->create(['role' => 3])->pluck('id')->toArray();
        $names = StudentAnonymizer::names($this->assignment->id, $user_ids);
        $this->assertCount(60, array_unique(array_column($names, 'name')));
        $this->assertEquals($names, StudentAnonymizer::names($this->assignment->id, $user_ids));
        $this->assertEquals($names[$user_ids[0]]['name'], StudentAnonymizer::fullName($this->assignment->id, $user_ids[0]));
    }

    #[Test]
    public function the_database_refuses_a_duplicate_stand_in_name()
    {
        $name = ['assignment_id' => $this->assignment->id, 'first_name' => 'Pat', 'last_name' => 'Lee'];
        DB::table('student_stand_in_names')->insert(array_merge($name, ['user_id' => $this->student->id]));
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('student_stand_in_names')->insert(array_merge($name, ['user_id' => $this->instructor->id]));
    }

    private function saveStaticRubric(string $title)
    {
        return $this->actingAs($this->grader_user)->patchJson(
            "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-custom-rubric",
            ['custom_rubric' => json_encode(['rubric_shown' => true, 'rubric_items' => [
                ['title' => $title, 'description' => '', 'points' => 10]]])]);
    }

    #[Test]
    public function changing_a_graded_static_rubrics_criteria_needs_its_own_option()
    {
        $this->useStaticRubric();
        $this->allow();
        $this->saveStaticRubric('New title')
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_STATIC_SCORES_MESSAGE]);
        $this->assertEquals(1, DB::table('rubric_points_breakdowns')->count());

        DB::table('rubric_editors')->update(['can_edit_graded_static_rubrics' => 1]);
        $this->saveStaticRubric('New title')->assertJson(['type' => 'success']);
        $this->assertEquals(0, DB::table('rubric_points_breakdowns')->count());
    }

    #[Test]
    public function a_static_rubric_nobody_was_graded_with_needs_no_extra_option()
    {
        $this->removeStudentScores();
        $this->useStaticRubric();
        $this->allow();
        $this->saveStaticRubric('New title')->assertJson(['type' => 'success']);
    }

    #[Test]
    public function an_instructor_sets_options_and_turning_editing_off_removes_them()
    {
        $this->actingAs($this->instructor)->patchJson($this->editorsUrl(), ['can_edit' => true])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->instructor)->patchJson($this->editorsUrl(),
            ['can_edit' => true, 'permissions' => ['can_change_rubric_shown' => true, 'not_a_column' => true]])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->instructor)->getJson("/api/courses/{$this->course->id}/rubric-editors")
            ->assertJson(['rubric_editor_permissions' => [$this->grader_user->id => [
                'can_change_rubric_shown' => true, 'can_change_scoring_style' => false]]]);

        $this->actingAs($this->instructor)->patchJson($this->editorsUrl(), ['can_edit' => false]);
        $this->assertEquals(0, DB::table('rubric_editors')->count());
    }

    #[Test]
    public function a_grader_cannot_give_themselves_options()
    {
        $this->allow();
        $this->actingAs($this->grader_user)->patchJson($this->editorsUrl(),
            ['can_edit' => true, 'permissions' => ['can_convert_graded_rubrics' => true]])
            ->assertJson(['type' => 'error']);
        $this->assertEquals(0, (int)DB::table('rubric_editors')->first()->can_convert_graded_rubrics);
    }
}
