<?php

namespace Tests\Feature\Instructors;

use App\Assignment;
use App\AutoRelease;
use App\Course;
use App\Question;
use App\Section;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Traits\Test;

class ScoresAlwaysReleasedTest extends TestCase
{
    use Test;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->course_2 = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id);

        $this->assign_tos = [
            [
                'groups' => [['value' => ['course_id' => $this->course->id], 'text' => 'Everybody']],
                'available_from' => '2020-06-10 09:00:00',
                'available_from_date' => '2020-06-10',
                'available_from_time' => '9:00 AM',
                'due' => '2020-06-12 09:00:00',
                'due_date' => '2020-06-12',
                'due_time' => '9:00 AM',
                'final_submission_deadline' => '2021-06-12 09:00:00',
                'final_submission_deadline_date' => '2021-06-12',
                'final_submission_deadline_time' => '9:00 AM',
            ]
        ];
        $this->assignment_info = ['course_id' => $this->course->id,
            'name' => 'First Assignment',
            'can_view_hint' => 0,
            'assign_tos' => $this->assign_tos,
            'scoring_type' => 'p',
            'source' => 'a',
            'points_per_question' => 'number of points',
            'default_points_per_question' => 2,
            'can_submit_work' => 0,
            'students_can_view_assignment_statistics' => 0,
            'include_in_weighted_average' => 1,
            'can_contact_instructor_auto_graded' => 'never',
            'late_policy' => 'not accepted',
            'assessment_type' => 'delayed',
            'default_open_ended_submission_type' => 'file',
            'instructions' => 'Some instructions',
            "number_of_randomized_assessments" => null,
            'notifications' => 1,
            'assignment_group_id' => 1,
            'algorithmic' => 0,
            'formative' => 0,
            'file_upload_mode' => 'both'];

        foreach ($this->assign_tos[0]['groups'] as $key => $group) {
            $group_info = ["groups_$key" => ['Everybody'],
                "due_$key" => '2020-06-12 09:00:00',
                "due_date_$key" => '2020-06-12',
                "due_time_$key" => '9:00 AM',
                "available_from_$key" => '2020-06-10',
                "available_from_date_$key" => '2020-06-12',
                "available_from_time_$key" => '9:00 AM',
                "final_submission_deadline_date_$key" => '2021-06-12',
                "final_submission_deadline_time_$key" => '9:00 AM'];
            foreach ($group_info as $info_key => $info_value) {
                $this->assignment_info[$info_key] = $info_value;
            }
        }
    }

    /**
     * @param string $assessment_type
     * @return array
     */
    private function assignmentInfoFor(string $assessment_type): array
    {
        $assignment_info = $this->assignment_info;
        $assignment_info['assessment_type'] = $assessment_type;
        switch ($assessment_type) {
            case('real time'):
                $assignment_info['number_of_allowed_attempts'] = '1';
                $assignment_info['solutions_availability'] = 'automatic';
                break;
            case('learning tree'):
                $assignment_info['number_of_successful_paths_for_a_reset'] = 1;
                $assignment_info['min_number_of_minutes_in_exposition_node'] = 1;
                $assignment_info['reset_node_after_incorrect_attempt'] = 1;
                $assignment_info['number_of_allowed_attempts'] = 2;
                $assignment_info['number_of_allowed_attempts_penalty'] = 3;
                break;
        }
        return $assignment_info;
    }

    /**
     * @param int $assignment_id
     * @param array $data
     * @return void
     */
    private function createAutoRelease(int $assignment_id, array $data = []): void
    {
        AutoRelease::create(array_merge([
            'type' => 'assignment',
            'type_id' => $assignment_id,
            'show_scores' => '1 day',
            'show_scores_after' => 'due date',
            'show_scores_activated' => 1], $data));
    }

    /**
     * @param int $assignment_id
     * @return mixed
     */
    private function autoReleaseFor(int $assignment_id)
    {
        return DB::table('auto_releases')
            ->where('type', 'assignment')
            ->where('type_id', $assignment_id)
            ->first();
    }

    /**
     * Removes the questions so that switching assessment types isn't blocked.
     * @return void
     */
    private function removeQuestions(): void
    {
        DB::table('assignment_question')->where('assignment_id', $this->assignment->id)->delete();
    }

    /** @test */
    public function owner_cannot_hide_scores_for_real_time_assignment()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 1]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-scores")
            ->assertJson(['type' => 'error',
                'message' => 'Scores are always released for real time assignments.']);
        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
    }

    /** @test */
    public function owner_cannot_hide_scores_for_learning_tree_assignment()
    {
        $this->assignment->update(['assessment_type' => 'learning tree', 'show_scores' => 1]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-scores")
            ->assertJson(['type' => 'error',
                'message' => 'Scores are always released for learning tree assignments.']);
        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
    }

    /** @test */
    public function owner_can_release_hidden_scores_for_real_time_assignment()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-scores")
            ->assertJson(['type' => 'success']);
        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
    }

    /** @test */
    public function owner_can_still_hide_scores_for_delayed_assignment()
    {
        $this->assignment->update(['assessment_type' => 'delayed', 'show_scores' => 1]);
        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}/show-scores")
            ->assertJson(['type' => 'info']);
        $this->assertEquals(0, $this->assignment->fresh()->show_scores);
    }

    /** @test */
    public function creating_a_real_time_assignment_releases_scores_and_ignores_show_scores_auto_release()
    {
        $assignment_info = $this->assignmentInfoFor('real time');
        //the factory assignment in setup is already named 'First Assignment'
        $assignment_info['name'] = 'New Real Time Assignment';
        $assignment_info['auto_release_show_scores'] = '1 day';
        $assignment_info['auto_release_show_scores_after'] = 'due date';
        $this->actingAs($this->user)->postJson("/api/assignments", $assignment_info)
            ->assertJson(['type' => 'success']);

        $assignment = Assignment::orderBy('id', 'desc')->first();
        $this->assertEquals(1, $assignment->show_scores);
        $auto_release = $this->autoReleaseFor($assignment->id);
        $this->assertNull($auto_release ? $auto_release->show_scores : null);
    }

    /** @test */
    public function updating_a_real_time_assignment_releases_scores_and_clears_show_scores_auto_release()
    {
        $this->removeQuestions();
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->createAutoRelease($this->assignment->id);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfoFor('real time'))
            ->assertJson(['type' => 'success']);

        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
        $auto_release = $this->autoReleaseFor($this->assignment->id);
        $this->assertNull($auto_release ? $auto_release->show_scores : null);
    }

    /** @test */
    public function updating_a_learning_tree_assignment_ignores_a_submitted_show_scores_auto_release()
    {
        $this->removeQuestions();
        $this->assignment->update(['assessment_type' => 'learning tree', 'show_scores' => 1]);
        $assignment_info = $this->assignmentInfoFor('learning tree');
        $assignment_info['auto_release_shown'] = '1 day';
        $assignment_info['auto_release_show_scores'] = '1 day';
        $assignment_info['auto_release_show_scores_after'] = 'due date';

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $assignment_info)
            ->assertJson(['type' => 'success']);

        $auto_release = $this->autoReleaseFor($this->assignment->id);
        $this->assertNotNull($auto_release, 'The shown auto-release should still be saved.');
        $this->assertNull($auto_release->show_scores);
        $this->assertNull($auto_release->show_scores_after);
    }

    /** @test */
    public function switching_a_real_time_assignment_to_delayed_hides_the_scores()
    {
        $this->removeQuestions();
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 1]);

        $this->actingAs($this->user)
            ->patchJson("/api/assignments/{$this->assignment->id}", $this->assignmentInfoFor('delayed'))
            ->assertJson(['type' => 'success']);

        $this->assertEquals(0, $this->assignment->fresh()->show_scores);
    }

    /** @test */
    public function creating_from_a_real_time_template_releases_scores_and_clears_show_scores_auto_release()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->createAutoRelease($this->assignment->id, ['shown' => '1 day']);

        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/create-assignment-from-template")
            ->assertJson(['type' => 'success']);

        $new_assignment = Assignment::orderBy('id', 'desc')->first();
        $this->assertNotEquals($this->assignment->id, $new_assignment->id);
        $this->assertEquals(1, $new_assignment->show_scores);
        $auto_release = $this->autoReleaseFor($new_assignment->id);
        $this->assertNull($auto_release ? $auto_release->show_scores : null);
    }

    /** @test */
    public function creating_from_a_delayed_template_still_hides_scores()
    {
        $this->assignment->update(['assessment_type' => 'delayed', 'show_scores' => 1]);

        $this->actingAs($this->user)
            ->postJson("/api/assignments/{$this->assignment->id}/create-assignment-from-template")
            ->assertJson(['type' => 'success']);

        $new_assignment = Assignment::orderBy('id', 'desc')->first();
        $this->assertEquals(0, $new_assignment->show_scores);
    }

    /** @test */
    public function importing_a_learning_tree_assignment_releases_scores_and_drops_the_course_default_show_scores()
    {
        $this->assignment->update(['assessment_type' => 'learning tree', 'show_scores' => 0]);
        $this->course_2->update(['auto_release_shown' => '1 day',
            'auto_release_show_scores' => '2 days',
            'auto_release_show_scores_after' => 'due date']);

        $this->actingAs($this->user)
            ->postJson("/api/assignments/import/{$this->assignment->id}/to/{$this->course_2->id}",
                ['level' => 'properties_and_not_questions'])
            ->assertJson(['type' => 'success']);

        $imported_assignment = $this->course_2->assignments()->first();
        $this->assertEquals(1, $imported_assignment->show_scores);
        $auto_release = $this->autoReleaseFor($imported_assignment->id);
        $this->assertEquals('1 day', $auto_release->shown);
        $this->assertNull($auto_release->show_scores);
    }

    /** @test */
    public function global_manual_off_does_not_hide_real_time_scores_but_does_hide_delayed_scores()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 1]);
        $delayed_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id,
            'assessment_type' => 'delayed',
            'show_scores' => 1,
            'order' => 2]);

        $this->actingAs($this->user)
            ->patchJson("/api/auto-release/global-update/course/{$this->course->id}", [
                'update_item' => -1,
                'update_name' => 'Entire Course',
                'setting' => 'manual',
                'value' => 0])
            ->assertJson(['type' => 'info']);

        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
        $this->assertEquals(0, $delayed_assignment->fresh()->show_scores);
    }

    /** @test */
    public function cannot_activate_the_show_scores_auto_release_for_a_real_time_assignment()
    {
        $this->assignment->update(['assessment_type' => 'real time']);
        $this->createAutoRelease($this->assignment->id, ['show_scores_activated' => 0]);

        $this->actingAs($this->user)
            ->patchJson("/api/auto-release/activated/{$this->assignment->id}", ['property' => 'show_scores'])
            ->assertJson(['type' => 'error',
                'message' => 'Scores are always released for real time assignments.']);
        $this->assertEquals(0, $this->autoReleaseFor($this->assignment->id)->show_scores_activated);
    }

    /** @test */
    public function compare_to_course_default_finds_no_mismatch_when_the_settings_match()
    {
        $this->assignment->update(['assessment_type' => 'delayed', 'formative' => 0, 'late_policy' => 'not accepted']);
        $this->createAutoRelease($this->assignment->id, ['shown' => '1 day']);
        $this->course_2->update(['auto_release_shown' => '1 day',
            'auto_release_show_scores' => '1 day',
            'auto_release_show_scores_after' => 'due date']);

        $this->actingAs($this->user)
            ->getJson("/api/auto-release/compare-to-default/assignment/{$this->assignment->id}/course/{$this->course_2->id}")
            ->assertJson(['type' => 'success', 'non_matching_auto_releases' => []]);
    }

    /** @test */
    public function compare_to_course_default_detects_an_assignment_timing_the_course_does_not_have()
    {
        $this->assignment->update(['assessment_type' => 'delayed', 'formative' => 0]);
        $this->createAutoRelease($this->assignment->id, ['show_scores' => null,
            'show_scores_after' => null,
            'shown' => '1 day']);

        $response = $this->actingAs($this->user)
            ->getJson("/api/auto-release/compare-to-default/assignment/{$this->assignment->id}/course/{$this->course_2->id}")
            ->assertJson(['type' => 'success']);
        $this->assertEquals(['shown'], collect($response->json('non_matching_auto_releases'))->pluck('key')->toArray());
    }

    /** @test */
    public function compare_to_course_default_ignores_show_scores_for_real_time()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'formative' => 0]);
        $this->course_2->update(['auto_release_show_scores' => '1 day',
            'auto_release_show_scores_after' => 'due date']);

        $this->actingAs($this->user)
            ->getJson("/api/auto-release/compare-to-default/assignment/{$this->assignment->id}/course/{$this->course_2->id}")
            ->assertJson(['type' => 'success', 'non_matching_auto_releases' => []]);
    }

    /** @test */
    public function applying_course_auto_release_to_all_skips_show_scores_for_real_time()
    {
        $this->assignment->update(['assessment_type' => 'real time']);
        $delayed_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id,
            'assessment_type' => 'delayed',
            'order' => 2]);

        $this->actingAs($this->user)
            ->patchJson("/api/courses/auto-release/{$this->course->id}", [
                'auto_release_shown' => '1 day',
                'auto_release_show_scores' => '2 days',
                'auto_release_show_scores_after' => 'due date',
                'auto_release_solutions_released' => null,
                'auto_release_solutions_released_after' => null,
                'auto_release_students_can_view_assignment_statistics' => null,
                'auto_release_students_can_view_assignment_statistics_after' => null,
                'apply_to' => 'all'])
            ->assertJson(['type' => 'success']);

        $this->assertNull($this->autoReleaseFor($this->assignment->id)->show_scores);
        $this->assertEquals('2 days', $this->autoReleaseFor($delayed_assignment->id)->show_scores);
    }

    /** @test */
    public function command_dry_run_does_not_change_anything()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->createAutoRelease($this->assignment->id);

        Artisan::call('update:showScoresForRealTimeAndLearningTree', ['--dry-run' => true]);

        $this->assertEquals(0, $this->assignment->fresh()->show_scores);
        $this->assertEquals('1 day', $this->autoReleaseFor($this->assignment->id)->show_scores);
    }

    /** @test */
    public function command_releases_scores_and_clears_auto_release_only_for_real_time_and_learning_tree()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->createAutoRelease($this->assignment->id);
        $learning_tree_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id,
            'assessment_type' => 'learning tree',
            'show_scores' => 0,
            'order' => 2]);
        $delayed_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id,
            'assessment_type' => 'delayed',
            'show_scores' => 0,
            'order' => 3]);
        $this->createAutoRelease($delayed_assignment->id);

        Artisan::call('update:showScoresForRealTimeAndLearningTree');

        $this->assertEquals(1, $this->assignment->fresh()->show_scores);
        $this->assertEquals(1, $learning_tree_assignment->fresh()->show_scores);
        $auto_release = $this->autoReleaseFor($this->assignment->id);
        $this->assertNull($auto_release->show_scores);
        $this->assertNull($auto_release->show_scores_after);
        $this->assertEquals(0, $auto_release->show_scores_activated);

        $this->assertEquals(0, $delayed_assignment->fresh()->show_scores, 'Delayed assignments are untouched.');
        $this->assertEquals('1 day', $this->autoReleaseFor($delayed_assignment->id)->show_scores);
    }

    /** @test */
    public function command_saves_the_previous_values_and_revert_restores_them()
    {
        $this->assignment->update(['assessment_type' => 'real time', 'show_scores' => 0]);
        $this->createAutoRelease($this->assignment->id);
        $learning_tree_assignment = factory(Assignment::class)->create(['course_id' => $this->course->id,
            'assessment_type' => 'learning tree',
            'show_scores' => 0,
            'order' => 2]);

        Artisan::call('update:showScoresForRealTimeAndLearningTree');
        $this->assertEquals(2, DB::table('show_scores_released_assignments')->count());

        Artisan::call('revert:showScoresForRealTimeAndLearningTree', ['--dry-run' => true]);
        $this->assertEquals(1, $this->assignment->fresh()->show_scores, 'Dry run changes nothing.');

        Artisan::call('revert:showScoresForRealTimeAndLearningTree');
        $this->assertEquals(0, $this->assignment->fresh()->show_scores);
        $this->assertEquals(0, $learning_tree_assignment->fresh()->show_scores);
        $auto_release = $this->autoReleaseFor($this->assignment->id);
        $this->assertEquals('1 day', $auto_release->show_scores);
        $this->assertEquals('due date', $auto_release->show_scores_after);
        $this->assertEquals(1, $auto_release->show_scores_activated);
        $this->assertNull($this->autoReleaseFor($learning_tree_assignment->id), 'No auto-release is created where none existed.');
        $this->assertEquals(0, DB::table('show_scores_released_assignments')->count());
    }
}
