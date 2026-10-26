<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\HeadGrader;
use App\Helpers\DynamicRubricEditor;
use App\Helpers\RubricEditPermission;
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
 * Editing a dynamic rubric: permissions, preview, apply (rescoring), guards, history, email and LMS.
 */
class DynamicRubricEditingTest extends TestCase
{
    private $user;
    private $student_a;
    private $student_b;
    private $grader_user;
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
            ->update(['show_scores' => 0, 'graders_can_see_student_names' => 1]);
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 0]);
        $this->question = factory(Question::class)->create(['page_id' => 12361]);
        $this->rubric_items = [
            ['id' => 'a', 'title' => 'Correct six-membered ring', 'description' => '', 'points' => 2],
            ['id' => 'b', 'title' => 'Correct arrow', 'description' => '', 'points' => 2],
            ['id' => 'c', 'title' => 'Correct intermediate', 'description' => '', 'points' => 3]];
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => json_encode([
            'scoring_style' => 'dynamic', 'version' => 1, 'rubric_shown' => true, 'rubric_items' => $this->rubric_items])]);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'open_ended_submission_type' => 'file',
            'order' => 1,
            'points' => 10
        ]);
        $this->student_a = $this->enrollStudentWithSubmission();
        $this->student_b = $this->enrollStudentWithSubmission();

        $this->grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $this->grader_user->id, 'section_id' => $this->section->id]);

        //student A: items a + c = 5; student B: item b = 2 (graded through the normal save)
        $this->grade($this->student_a, ['a', 'c']);
        $this->grade($this->student_b, ['b']);
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

    private function grade(User $student, array $applied_item_ids, int $rubric_version = 1)
    {
        return $this->actingAs($this->user)->postJson('/api/grading', [
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $student->id,
            'text_feedback_editor' => 'plain',
            'question_submission_score' => 0,
            'textFeedback' => '',
            'dynamic_rubric' => ['applied_item_ids' => $applied_item_ids, 'adjustment' => 0, 'rubric_version' => $rubric_version]]);
    }

    private function url(string $suffix = ''): string
    {
        return "/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/dynamic-rubric$suffix";
    }

    private function proposal(array $item_changes = [], array $extra = []): array
    {
        $items = $this->rubric_items;
        foreach ($item_changes as $index => $change) {
            if ($change === null) {
                unset($items[$index]);
            } else {
                $items[$index] = array_merge($items[$index] ?? [], $change);
            }
        }
        return array_merge(['rubric_items' => array_values($items), 'rubric_shown' => true, 'base_version' => 1], $extra);
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

    private function appliedItemIds(User $student): array
    {
        return json_decode(DB::table('rubric_points_breakdowns')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->where('user_id', $student->id)
            ->first()
            ->applied_item_ids, true);
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
    public function preview_shows_who_changes_and_saves_nothing()
    {
        $response = $this->actingAs($this->user)->postJson($this->url('/preview'), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success'])
            ->json('preview');
        $this->assertCount(1, $response['students_changed']);
        $this->assertEquals($this->student_a->id, $response['students_changed'][0]['user_id']);
        $this->assertEquals(5, $response['students_changed'][0]['old_score']);
        $this->assertEquals(6, $response['students_changed'][0]['new_score']);
        $this->assertEquals(1, $response['number_of_students_rescored']);
        $this->assertFalse($response['names_hidden']);
        //nothing saved
        $this->assertEquals(5, $this->rubricScore($this->student_a));
        $this->assertEquals(1, $this->currentRubric()['version']);
    }

    #[Test]
    public function applying_an_edit_rescores_only_the_students_who_have_that_item()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success', 'number_of_students_rescored' => 1]);
        $this->assertEquals(6, $this->rubricScore($this->student_a));
        $this->assertEquals(2, $this->rubricScore($this->student_b));
        $this->assertEquals(6, (float)DB::table('scores')
            ->where('assignment_id', $this->assignment->id)
            ->where('user_id', $this->student_a->id)
            ->first()->score);
        $this->assertEquals(2, $this->currentRubric()['version']);

        $log = DB::table('rubric_score_logs')
            ->where('student_user_id', $this->student_a->id)
            ->where('reason', 'rescored_by_rubric_edit')
            ->first();
        $this->assertEquals(5, json_decode($log->before, true)['score']);
        $this->assertEquals(6, json_decode($log->after, true)['score']);
        $change_log = DB::table('rubric_change_logs')->where('assignment_id', $this->assignment->id)->first();
        $this->assertEquals('edit', $change_log->action);
        $this->assertEquals(1, $change_log->students_rescored);
    }

    #[Test]
    public function deleting_an_item_removes_it_from_students_and_rescores_them()
    {
        $preview = $this->actingAs($this->user)->postJson($this->url('/preview'), $this->proposal([2 => null]))
            ->json('preview');
        $this->assertEquals([['id' => 'c', 'title' => 'Correct intermediate', 'students' => 1]], $preview['deleted_items']);

        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([2 => null]))
            ->assertJson(['type' => 'success']);
        $this->assertEquals(['a'], $this->appliedItemIds($this->student_a));
        $this->assertEquals(2, $this->rubricScore($this->student_a));
    }

    #[Test]
    public function a_new_item_gets_an_id_and_changes_no_scores()
    {
        $this->actingAs($this->user)->patchJson($this->url(),
            $this->proposal([3 => ['title' => 'Five-membered ring instead', 'description' => '', 'points' => 1]]))
            ->assertJson(['type' => 'success', 'number_of_students_rescored' => 0]);
        $new_item = $this->currentRubric()['rubric_items'][3];
        $this->assertNotEmpty($new_item['id']);
        $this->assertNotContains($new_item['id'], ['a', 'b', 'c']);
        $this->assertEquals(5, $this->rubricScore($this->student_a));
    }

    #[Test]
    public function an_edit_based_on_an_old_version_is_refused()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]], ['base_version' => 0]))
            ->assertJson(['type' => 'error', 'message' => DynamicRubricEditor::CONFLICT_MESSAGE]);
    }

    #[Test]
    public function rubric_items_are_validated()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([1 => ['title' => 'correct six-membered ring', 'points' => -1]]))
            ->assertJson(['type' => 'error',
                'errors' => ['Item 2: this title already exists.',
                    'Item 2: the points should be a number that is at least 0.']]);
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([], ['rubric_items' => []]))
            ->assertJson(['type' => 'error', 'message' => 'Add at least one rubric item.']);
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['id' => 'not-an-item']]))
            ->assertJson(['type' => 'error', 'errors' => ['Item 1 no longer exists in the rubric. Please reload the page.']]);
    }

    #[Test]
    public function saving_without_changes_saves_nothing()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal())
            ->assertJson(['type' => 'info', 'message' => 'There are no changes to save.']);
        $this->assertEquals(0, DB::table('rubric_change_logs')->count());
    }

    #[Test]
    public function a_grade_saved_against_the_old_rubric_version_is_refused_after_an_edit()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success']);
        $this->grade($this->student_b, ['a'], 1)
            ->assertJson(['type' => 'error',
                'message' => 'The rubric was changed by someone else. Please reload the page before saving.']);
        $this->grade($this->student_b, ['a'], 2)
            ->assertJson(['type' => 'success']);
        $this->assertEquals(3, $this->rubricScore($this->student_b));
    }

    #[Test]
    public function another_instructor_cannot_edit_the_rubric()
    {
        $other_instructor = factory(User::class)->create();
        $this->actingAs($other_instructor)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::NOT_ALLOWED_MESSAGE]);
    }

    #[Test]
    public function a_grader_without_permission_is_told_what_to_do()
    {
        $this->actingAs($this->grader_user)->postJson($this->url('/preview'), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::GRADER_MESSAGE]);
        $this->actingAs($this->grader_user)->getJson($this->url())
            ->assertJson(['type' => 'success', 'can_edit' => false, 'can_edit_message' => RubricEditPermission::GRADER_MESSAGE]);
    }

    #[Test]
    public function a_head_grader_without_permission_gets_the_head_grader_message()
    {
        HeadGrader::create(['course_id' => $this->course->id, 'user_id' => $this->grader_user->id]);
        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'error', 'message' => RubricEditPermission::HEAD_GRADER_MESSAGE]);
    }

    #[Test]
    public function a_grader_given_permission_can_edit_and_names_follow_the_assignment_setting()
    {
        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course->id}/rubric-editors/{$this->grader_user->id}", ['can_edit' => true])
            ->assertJson(['type' => 'success']);
        $this->actingAs($this->user)->getJson("/api/courses/{$this->course->id}/rubric-editors")
            ->assertJson(['type' => 'success', 'rubric_editor_user_ids' => [$this->grader_user->id]]);

        DB::table('assignments')->where('id', $this->assignment->id)->update(['graders_can_see_student_names' => 0]);
        $preview = $this->actingAs($this->grader_user)->postJson($this->url('/preview'), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success'])
            ->json('preview');
        $this->assertTrue($preview['names_hidden']);
        $this->assertEquals(DynamicRubricEditor::NAMES_HIDDEN_MESSAGE, $preview['names_hidden_message']);
        $this->assertEquals('Student 1', $preview['students_changed'][0]['name']);
        $this->assertNull($preview['students_changed'][0]['user_id']);

        $this->actingAs($this->grader_user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success']);
        $this->assertEquals(6, $this->rubricScore($this->student_a));
    }

    #[Test]
    public function only_instructors_can_change_who_edits_rubrics_and_only_for_graders()
    {
        $this->actingAs($this->grader_user)->patchJson("/api/courses/{$this->course->id}/rubric-editors/{$this->grader_user->id}", ['can_edit' => true])
            ->assertJson(['type' => 'error']);
        $this->actingAs($this->user)->patchJson("/api/courses/{$this->course->id}/rubric-editors/{$this->student_a->id}", ['can_edit' => true])
            ->assertJson(['type' => 'error']);
        $this->assertEquals(0, DB::table('rubric_editors')->count());
    }

    #[Test]
    public function static_actions_that_would_delete_dynamic_scores_are_refused()
    {
        $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/delete-custom-rubric")
            ->assertJson(['type' => 'error', 'message' => DynamicRubricEditor::HAS_DYNAMIC_SCORES_MESSAGE]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/use-existing-rubric")
            ->assertJson(['type' => 'error', 'message' => DynamicRubricEditor::HAS_DYNAMIC_SCORES_MESSAGE]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/update-custom-rubric",
                ['custom_rubric' => json_encode(['rubric_items' => [['title' => 'x', 'description' => '', 'points' => 1]], 'rubric_shown' => true])])
            ->assertJson(['type' => 'error', 'message' => DynamicRubricEditor::HAS_DYNAMIC_SCORES_MESSAGE]);
        $this->assertCount(2, DB::table('rubric_points_breakdowns')->where('assignment_id', $this->assignment->id)->get());
    }

    #[Test]
    public function students_are_emailed_only_when_asked_and_scores_are_released()
    {
        Mail::fake();
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]], ['email_students' => true]))
            ->assertJson(['type' => 'success', 'number_of_students_emailed' => 0]);
        Mail::assertNothingQueued();

        DB::table('assignments')->where('id', $this->assignment->id)->update(['show_scores' => 1]);
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 4]], ['base_version' => 2, 'email_students' => true]))
            ->assertJson(['type' => 'success', 'number_of_students_emailed' => 1]);
        Mail::assertQueued(RubricScoreChanged::class, function ($mail) {
            return $mail->hasTo($this->student_a->email) && $mail->old_score === '6' && $mail->new_score === '7';
        });
        Mail::assertQueued(RubricScoreChanged::class, 1);
    }

    #[Test]
    public function students_with_a_score_override_are_listed_as_not_affected_and_keep_their_scores()
    {
        //student A has an instructor override on this question; B does not
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->student_a->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => '8',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()]);
        //item b is worth 2 -> 4: only B has it; A has a and c
        $proposal = $this->proposal([0 => ['points' => 3], 1 => ['points' => 4]]);

        $preview = $this->actingAs($this->user)->postJson($this->url('/preview'), $proposal)
            ->assertJson(['type' => 'success'])
            ->json('preview');
        $this->assertCount(1, $preview['students_not_affected_override']);
        $this->assertEquals($this->student_a->id, $preview['students_not_affected_override'][0]['user_id']);
        $this->assertEquals(5, $preview['students_not_affected_override'][0]['old_score']);
        $this->assertEquals(5, $preview['students_not_affected_override'][0]['new_score']);
        $this->assertCount(1, $preview['students_changed']);
        $this->assertEquals($this->student_b->id, $preview['students_changed'][0]['user_id']);

        $this->actingAs($this->user)->patchJson($this->url(), $proposal)
            ->assertJson(['type' => 'success', 'number_of_students_rescored' => 1]);
        $this->assertEquals(5, $this->rubricScore($this->student_a));
        $this->assertEquals(4, $this->rubricScore($this->student_b));
        $this->assertEquals('8', DB::table('submission_score_overrides')
            ->where('user_id', $this->student_a->id)
            ->where('assignment_id', $this->assignment->id)
            ->first()->score);
        $this->assertFalse(DB::table('rubric_score_logs')
            ->where('student_user_id', $this->student_a->id)
            ->where('reason', 'rescored_by_rubric_edit')
            ->exists());
    }

    #[Test]
    public function the_score_changed_email_renders_with_the_scores_and_link()
    {
        $html = (new RubricScoreChanged('Ada',
            'Organic Chemistry HW 3',
            'Question #2',
            '6',
            '7',
            '10',
            'https://adapt.libretexts.org/students/assignments/1/summary'))->render();
        $this->assertStringContainsString('Score Updated', $html);
        $this->assertStringContainsString('Hi Ada,', $html);
        $this->assertStringContainsString('<strong>Question #2</strong>', $html);
        $this->assertStringContainsString('<strong>Organic Chemistry HW 3</strong>', $html);
        $this->assertStringContainsString('<strong>6</strong> to <strong>7</strong> out of 10', $html);
        $this->assertStringContainsString('https://adapt.libretexts.org/students/assignments/1/summary', $html);
    }

    #[Test]
    public function lms_courses_get_a_bulk_passback_after_rescoring()
    {
        DB::table('courses')->where('id', $this->course->id)->update(['lms' => 1]);
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success']);
        $this->assertTrue(DB::table('passback_by_assignments')
            ->where('assignment_id', $this->assignment->id)
            ->where('status', 'manual_pending')
            ->exists());
    }

    #[Test]
    public function history_is_for_instructors_and_latest_change_is_for_graders_too()
    {
        $this->actingAs($this->user)->patchJson($this->url(), $this->proposal([0 => ['points' => 3]]))
            ->assertJson(['type' => 'success']);

        $history = $this->actingAs($this->user)->getJson($this->url('/history'))
            ->assertJson(['type' => 'success'])
            ->json('history');
        $this->assertCount(1, $history);
        $this->assertEquals(1, $history[0]['students_rescored']);
        $this->assertEquals(2, $history[0]['after']['version']);

        $student_history = $this->actingAs($this->user)->getJson($this->url("/history/user/{$this->student_a->id}"))
            ->assertJson(['type' => 'success'])
            ->json('history');
        $this->assertEquals('rescored_by_rubric_edit', $student_history[0]['reason']);
        $this->assertEquals('graded', $student_history[1]['reason']);

        $this->actingAs($this->grader_user)->getJson($this->url('/history'))
            ->assertJson(['type' => 'error', 'message' => 'Only instructors can view the rubric history.']);
        $this->actingAs($this->grader_user)->getJson($this->url('/latest-change'))
            ->assertJson(['type' => 'success',
                'latest_change' => ['name' => "{$this->user->first_name} {$this->user->last_name}", 'version' => 2]]);
    }
}
