<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Assignment;
use App\Course;
use App\Helpers\RubricHelper;
use App\Question;
use App\QuestionRevision;
use App\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EffectiveRubricTest extends TestCase
{
    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->question = factory(Question::class)->create(['page_id' => 24241]);
        $this->student_user = factory(User::class)->create(['role' => 3]);

        $this->question_rubric = json_encode([
            'rubric_items' => [['title' => 'Question rubric item', 'description' => '', 'points' => 2]],
            'rubric_shown' => true]);
        $this->custom_rubric = json_encode([
            'rubric_items' => [['title' => 'Overriding rubric item', 'description' => '', 'points' => 3]],
            'rubric_shown' => true]);
        DB::table('questions')->where('id', $this->question->id)->update(['rubric' => $this->question_rubric]);

        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0'
        ]);
    }

    private function setAssignmentQuestion(array $values)
    {
        DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->update($values);
    }

    private function rubricItemTitleFromBreakdownEndpoint(): string
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student_user->id}")
            ->assertJson(['type' => 'success']);
        return json_decode($response->json('rubric_points_breakdown'), 1)['rubric_items'][0]['title'];
    }

    #[Test]
    public function helper_uses_question_rubric_when_switch_is_on_even_if_an_override_is_saved()
    {
        $row = (object)['use_existing_rubric' => 1, 'custom_rubric' => $this->custom_rubric];
        $this->assertEquals($this->question_rubric, RubricHelper::effectiveRubric($row, $this->question_rubric));
    }

    #[Test]
    public function helper_uses_question_rubric_when_switch_is_null()
    {
        $row = (object)['use_existing_rubric' => null, 'custom_rubric' => $this->custom_rubric];
        $this->assertEquals($this->question_rubric, RubricHelper::effectiveRubric($row, $this->question_rubric));
    }

    #[Test]
    public function helper_uses_override_when_switch_is_off_and_override_exists()
    {
        $row = (object)['use_existing_rubric' => 0, 'custom_rubric' => $this->custom_rubric];
        $this->assertEquals($this->custom_rubric, RubricHelper::effectiveRubric($row, $this->question_rubric));
    }

    #[Test]
    public function helper_falls_back_to_question_rubric_when_switch_is_off_but_no_override_exists()
    {
        $row = (object)['use_existing_rubric' => 0, 'custom_rubric' => null];
        $this->assertEquals($this->question_rubric, RubricHelper::effectiveRubric($row, $this->question_rubric));
    }

    /**
     * Creates a revision the same way QuestionController does for a question's first revision,
     * gives it its own rubric, and points the assignment question at it.
     */
    private function pointAssignmentQuestionAtRevisionWithRubric(?string $revision_rubric)
    {
        $question_revision = Question::find($this->question->id)->toArray();
        $question_revision['revision_number'] = 0;
        $question_revision['question_id'] = $this->question->id;
        $question_revision['action'] = 'none';
        unset($question_revision['id']);
        $questionRevision = QuestionRevision::create($question_revision);
        DB::table('question_revisions')->where('id', $questionRevision->id)->update(['rubric' => $revision_rubric]);
        $this->setAssignmentQuestion(['question_revision_id' => $questionRevision->id]);
    }

    #[Test]
    public function helper_uses_latest_question_rubric_when_assignment_has_no_revision()
    {
        $row = (object)['use_existing_rubric' => 1, 'custom_rubric' => null, 'question_revision_id' => null];
        $this->assertEquals($this->question_rubric, RubricHelper::assignmentQuestionRubric($row, $this->question_rubric));
    }

    #[Test]
    public function rubric_breakdown_uses_the_revision_rubric_not_the_latest()
    {
        $revision_rubric = json_encode([
            'rubric_items' => [['title' => 'Revision rubric item', 'description' => '', 'points' => 4]],
            'rubric_shown' => true]);
        $this->pointAssignmentQuestionAtRevisionWithRubric($revision_rubric);
        $this->assertEquals('Revision rubric item', $this->rubricItemTitleFromBreakdownEndpoint());
    }

    #[Test]
    public function rubric_breakdown_has_no_rubric_when_the_revision_has_none()
    {
        $this->pointAssignmentQuestionAtRevisionWithRubric(null);
        $this->actingAs($this->user)
            ->getJson("/api/rubric-points-breakdown/assignment/{$this->assignment->id}/question/{$this->question->id}/user/{$this->student_user->id}")
            ->assertJson(['type' => 'success',
                'rubric_points_breakdown' => [],
                'rubric_points_breakdown_exists' => false]);
    }

    #[Test]
    public function override_still_wins_over_the_revision_rubric_when_selected()
    {
        $this->pointAssignmentQuestionAtRevisionWithRubric(null);
        $this->setAssignmentQuestion(['custom_rubric' => $this->custom_rubric, 'use_existing_rubric' => 0]);
        $this->assertEquals('Overriding rubric item', $this->rubricItemTitleFromBreakdownEndpoint());
    }

    #[Test]
    public function rubric_breakdown_uses_question_rubric_when_switched_back_to_existing()
    {
        $this->setAssignmentQuestion(['custom_rubric' => $this->custom_rubric, 'use_existing_rubric' => 1]);
        $this->assertEquals('Question rubric item', $this->rubricItemTitleFromBreakdownEndpoint());
    }

    #[Test]
    public function rubric_breakdown_uses_override_when_override_is_selected()
    {
        $this->setAssignmentQuestion(['custom_rubric' => $this->custom_rubric, 'use_existing_rubric' => 0]);
        $this->assertEquals('Overriding rubric item', $this->rubricItemTitleFromBreakdownEndpoint());
    }

    #[Test]
    public function deleting_the_override_switches_back_to_existing_rubric()
    {
        $this->setAssignmentQuestion(['custom_rubric' => $this->custom_rubric, 'use_existing_rubric' => 0]);
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/assignments/{$this->assignment->id}/questions/{$this->question->id}/delete-custom-rubric")
            ->assertJson(['type' => 'info',
                'use_existing_rubric' => true]);
        // MySQL JSON columns re-format the text (spacing, key order), so compare the decoded rubric
        $this->assertEquals(json_decode($this->question_rubric, true), json_decode($response->json('rubric'), true));

        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $this->assignment->id)
            ->where('question_id', $this->question->id)
            ->first();
        $this->assertNull($assignment_question->custom_rubric);
        $this->assertEquals(1, (int)$assignment_question->use_existing_rubric);
        $this->assertEquals('Question rubric item', $this->rubricItemTitleFromBreakdownEndpoint());
    }
}
