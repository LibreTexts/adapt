<?php

namespace Tests\Feature;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignToTiming;
use App\Course;
use App\Enrollment;
use App\LearningTree;
use App\Question;
use App\QuestionRevision;
use App\Section;
use App\User;
use App\Traits\Test;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningTreeStructuralAutoUpdateTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'assessment_type' => 'learning tree']);
        $this->assignmentQuestionLearningTree = new AssignmentQuestionLearningTree();

        $this->learning_tree = factory(LearningTree::class)->create([
            'user_id' => $this->user->id,
            // Test::learningTree()'s fixture JSON has its root block (id: 0)
            // pointing at question_id "1" - kept in sync with the factory
            // column, same convention as LearningTreeUpdateToLatestRevisionTest.
            'root_node_question_id' => 1,
            'learning_tree' => $this->learningTree()]);

        // every question_id referenced in the fixture tree's blocks
        foreach ([1, 102438, 102439, 102441, 202, 2] as $question_id) {
            if (!Question::find($question_id)) {
                factory(Question::class)->create(['id' => $question_id]);
            }
        }
        factory(QuestionRevision::class)->create(['question_id' => 1, 'revision_number' => 1]);

        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/learning-trees/{$this->learning_tree->id}");
    }

    /**
     * Structurally edits the live tree by pointing block id 1 (a non-root
     * exposition node, parent of block 2/4) at a different question_id -
     * this is exactly the kind of change learningTreeNeedsUpdate() detects
     * as "structure changed" (a different question per node), same as
     * adding or removing a node would be.
     */
    private function learningTreeJsonWithBlockQuestionId(int $block_id, int $new_question_id): string
    {
        $tree = json_decode($this->learning_tree->learning_tree, true);
        foreach ($tree['blocks'] as $key => $block) {
            if ($block['id'] === $block_id) {
                foreach ($block['data'] as $data_key => $entry) {
                    if ($entry['name'] === 'question_id') {
                        $tree['blocks'][$key]['data'][$data_key]['value'] = (string)$new_question_id;
                    }
                }
            }
        }
        return json_encode($tree);
    }

    private function saveStructuralEdit(int $new_question_id): void
    {
        if (!Question::find($new_question_id)) {
            factory(Question::class)->create(['id' => $new_question_id]);
        }
        $edited_tree = $this->learningTreeJsonWithBlockQuestionId(1, $new_question_id);
        $this->actingAs($this->user)
            ->patchJson("/api/learning-trees/{$this->learning_tree->id}", [
                'learning_tree' => $edited_tree,
                'question_ids' => [1, $new_question_id, 102439, 102441, 202, 2]
            ])
            ->assertJson(['type' => 'success']);
    }

    private function questionIdForBlockInSnapshot(int $block_id): ?int
    {
        $snapshot = $this->assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($this->assignment->id, $this->learning_tree->id);
        $blocks = json_decode($snapshot->learning_tree, true)['blocks'];
        $block = collect($blocks)->firstWhere('id', $block_id);
        $entry = collect($block['data'])->firstWhere('name', 'question_id');
        return $entry ? (int)$entry['value'] : null;
    }

    private function openTheAssignment(int $fake_student = 0, int $formative_student = 0): void
    {
        $student = factory(User::class)->create([
            'role' => 3,
            'fake_student' => $fake_student,
            'formative_student' => $formative_student
        ]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $student->id);
        $assignToTiming = AssignToTiming::where('assignment_id', $this->assignment->id)->first();
        $assignToTiming->due = now()->addWeek();
        $assignToTiming->save();
    }

    /**
     * Enrolls a student in the course without opening the assignment or
     * touching sections/timing - for tests that only care about course-wide
     * enrollment (autoUpdateRisksRealStudents()), independent of whether
     * this specific assignment is open.
     */
    private function enrollStudent(int $fake_student, int $formative_student = 0): User
    {
        $student = factory(User::class)->create([
            'role' => 3,
            'fake_student' => $fake_student,
            'formative_student' => $formative_student
        ]);
        $section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $student->id,
            'section_id' => $section->id,
            'course_id' => $this->course->id
        ]);
        return $student;
    }

    /** @test */
    public function auto_updates_a_closed_assignment_with_no_real_students_enrolled_when_the_course_flag_is_on()
    {
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        // no enrollments exist at all in this test - nothing at risk

        $this->saveStructuralEdit(999901);

        $this->assertEquals(999901, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function does_not_auto_update_when_the_course_flag_is_off()
    {
        // auto_update_question_revisions defaults to 0/off - not touched here

        $this->saveStructuralEdit(999902);

        // snapshot still shows the original question_id for block 1 -
        // learningTreeNeedsUpdate() will now correctly flag this tree as
        // stale, for an instructor to update manually
        $this->assertEquals(102438, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function auto_updates_an_open_assignment_with_no_real_students_enrolled_when_the_course_flag_is_on()
    {
        // EK: autoUpdateRisksRealStudents() matches QuestionController::store()'s
        // course-wide, enrollment-based precedent exactly - open/closed status
        // plays no part in it. openTheAssignment(1, 0) opens the assignment via
        // a *fake* enrolled student, so this confirms open status alone still
        // doesn't block auto-update once the only enrolled student isn't real.
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        $this->openTheAssignment(1, 0);

        $this->saveStructuralEdit(999903);

        $this->assertEquals(999903, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function does_not_auto_update_when_a_real_student_is_enrolled_in_the_course_even_with_the_assignment_closed_and_no_submissions()
    {
        // EK: the defining difference from the manual button's check - a
        // real student merely being enrolled in the course blocks
        // auto-update, even with the assignment closed and zero submissions
        // anywhere. Matches QuestionController::store()'s course-wide
        // precedent, which also doesn't require actual student work to
        // exist before treating a course as unsafe to auto-update.
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        $this->enrollStudent(0, 0);

        $this->saveStructuralEdit(999904);

        $this->assertEquals(102438, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function auto_updates_despite_a_fake_student_enrolled_in_the_course()
    {
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        $this->enrollStudent(1, 0);

        $this->saveStructuralEdit(999905);

        $this->assertEquals(999905, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function does_not_auto_update_when_a_formative_student_is_enrolled_even_if_also_flagged_fake()
    {
        // EK: the exact fake_student=0 OR formative_student=1 precedent from
        // QuestionController::store() - a formative_student=1 enrollment
        // counts as "real" for this check regardless of fake_student, so
        // this must still block even with fake_student also set to 1.
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);
        $this->enrollStudent(1, 1);

        $this->saveStructuralEdit(999908);

        $this->assertEquals(102438, $this->questionIdForBlockInSnapshot(1));
    }

    /** @test */
    public function auto_updated_snapshot_still_has_html_and_blockarr()
    {
        // regression coverage: autoUpdateEligibleAssignmentSnapshots() calls
        // updateToLatestRevision() -> buildLearningTreeSnapshot(), which must
        // keep html/blockarr (flowy.import() renders from those, never from
        // blocks) - not the same code path as the propagate/patch bug fixed
        // elsewhere, but worth confirming directly here too since this is a
        // new caller of the snapshot-writing logic.
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);

        $this->saveStructuralEdit(999906);

        $snapshot = $this->assignmentQuestionLearningTree
            ->getAssignmentQuestionLearningTreeByLearningTreeId($this->assignment->id, $this->learning_tree->id);
        $decoded = json_decode($snapshot->learning_tree, true);
        $this->assertArrayHasKey('html', $decoded);
        $this->assertNotEmpty($decoded['html']);
        $this->assertArrayHasKey('blockarr', $decoded);
        $this->assertNotEmpty($decoded['blockarr']);
    }

    /** @test */
    public function a_no_op_save_does_not_trigger_auto_update_logic()
    {
        DB::table('courses')->where('id', $this->course->id)->update(['auto_update_question_revisions' => 1]);

        // saving the exact same JSON that's already live - updateLearningTree()
        // short-circuits to a 'no_change' response before ever reaching the
        // auto-update call
        $this->actingAs($this->user)
            ->patchJson("/api/learning-trees/{$this->learning_tree->id}", [
                'learning_tree' => $this->learning_tree->learning_tree,
                'question_ids' => [1, 102438, 102439, 102441, 202, 2]
            ])
            ->assertJson(['type' => 'no_change']);

        $this->assertEquals(102438, $this->questionIdForBlockInSnapshot(1));
    }
}
