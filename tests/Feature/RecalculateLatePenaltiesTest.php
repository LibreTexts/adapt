<?php

namespace Tests\Feature;

use App\Assignment;
use App\Course;
use App\Question;
use App\Traits\Test;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * php artisan assignment:recalculateLatePenalties {assignment_id}
 *
 * Every test uses a late policy of 10% per 1 day and a 10-point question. The student's response
 * was submitted on Jan 10, 2026 at 1:00pm UTC. With the original due date of Jan 9 at 12:00pm
 * that is 1 day 1 hour late: 2 periods = 20%.
 */
class RecalculateLatePenaltiesTest extends TestCase
{
    use Test;

    private $user;
    private $student_user;
    private $course;
    private $assignment;
    private $question;
    private $submitted_at;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create(['role' => 3]);
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id]);
        $this->assignment = factory(Assignment::class)->create([
            'course_id' => $this->course->id,
            'late_policy' => 'deduction',
            'late_deduction_percent' => 10,
            'late_deduction_application_period' => '1 day',
            'assessment_type' => 'delayed',
            'scoring_type' => 'p',
        ]);
        $this->question = factory(Question::class)->create(['page_id' => rand(1, 1000000000), 'technology' => 'webwork']);
        DB::table('assignment_question')->insert([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'points' => 10,
            'order' => 1,
            'open_ended_submission_type' => '0',
        ]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->submitted_at = Carbon::parse('2026-01-10 13:00:00', 'UTC');
        $this->setDue('2026-01-09 12:00:00');
    }

    /** Sets the student's current due date. */
    private function setDue(string $due): void
    {
        DB::table('assign_to_timings')
            ->where('assignment_id', $this->assignment->id)
            ->update(['due' => Carbon::parse($due, 'UTC')]);
    }

    /**
     * A WeBWorK submission plus the submission_histories row ADAPT writes when it's accepted,
     * inserted directly so the times are under the test's control.
     */
    private function webworkSubmission(float $proportion_correct, float $stored_score, int $submission_count = 1): int
    {
        // Submission::getProportionCorrect() adds up the per-answer scores, so one answer worth the whole problem.
        $response = json_encode(['score' => [
            'result' => $proportion_correct,
            'answers' => ['AnSwEr0001' => ['score' => $proportion_correct]],
        ]]);
        $id = DB::table('submissions')->insertGetId([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $response,
            'score' => $stored_score,
            'submission_count' => $submission_count,
            'answered_correctly_at_least_once' => 0,
            'created_at' => $this->submitted_at,
            'updated_at' => $this->submitted_at,
        ]);
        $this->history($response);
        $this->assignmentScoreIs($stored_score);
        return $id;
    }

    private function history(string $response, ?Carbon $at = null): void
    {
        DB::table('submission_histories')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $response,
            'created_at' => $at ?: $this->submitted_at,
            'updated_at' => $at ?: $this->submitted_at,
        ]);
    }

    private function assignmentScoreIs(float $score): void
    {
        DB::table('scores')->updateOrInsert(
            ['user_id' => $this->student_user->id, 'assignment_id' => $this->assignment->id],
            ['score' => $score, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function runCommand(array $options = []): array
    {
        $exit_code = Artisan::call('assignment:recalculateLatePenalties',
            array_merge(['assignment_id' => $this->assignment->id, '--no-interaction' => true, '--force' => true], $options));
        return [$exit_code, Artisan::output()];
    }

    private function scoreIn(string $table, int $id): float
    {
        return (float)DB::table($table)->where('id', $id)->value('score');
    }

    private function assignmentScore(): float
    {
        return (float)DB::table('scores')
            ->where('user_id', $this->student_user->id)
            ->where('assignment_id', $this->assignment->id)
            ->value('score');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function moving_the_due_date_later_removes_the_late_penalty()
    {
        // 10 x 0.5 = 5, stored with 20% off = 4. New due Jan 11 12:00pm: on time, so 5.
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(5, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(5, $this->assignmentScore());
        $this->assertDatabaseHas('late_penalty_recalculations', [
            'record_table' => 'submissions',
            'record_id' => $submission_id,
            'old_penalty_percent' => 20,
            'new_penalty_percent' => 0,
            'old_score' => 4,
            'new_score' => 5,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function moving_the_due_date_earlier_adds_a_late_penalty()
    {
        // Stored 5 = on time under the old due date. New due Jan 9 12:00pm: 20% late, so 4.
        $this->setDue('2026-01-11 12:00:00');
        $submission_id = $this->webworkSubmission(0.5, 5);
        $this->setDue('2026-01-09 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(4, $this->assignmentScore());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_submission_time_comes_from_the_submission_history_not_updated_at()
    {
        // An instructor action on Jan 20 bumped updated_at; the history still says Jan 10 1:00pm.
        // Using updated_at would wrongly give a 90% penalty under the new due date.
        $submission_id = $this->webworkSubmission(0.5, 4);
        DB::table('submissions')->where('id', $submission_id)->update(['updated_at' => Carbon::parse('2026-01-20 09:00:00', 'UTC')]);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(5, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_latest_matching_history_row_is_used()
    {
        // An earlier, different response on Jan 5 must not be mistaken for the saved one.
        $this->history(json_encode(['score' => ['result' => 0.1, 'answers' => ['AnSwEr0001' => ['score' => 0.1]]]]),
            Carbon::parse('2026-01-05 10:00:00', 'UTC'));
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        $this->runCommand();

        $this->assertEquals(5, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_dates_and_timestamps_are_not_changed()
    {
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        $this->runCommand();

        $this->assertEquals($this->submitted_at->toDateTimeString(),
            Carbon::parse(DB::table('submissions')->where('id', $submission_id)->value('updated_at'))->toDateTimeString());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function dry_run_shows_the_change_but_saves_nothing()
    {
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand(['--dry-run' => true, '--by-question' => true]);

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Late penalty 20% -> 0%, so 4 -> 5 (+1)', $output);
        $this->assertStringContainsString('Dry run: nothing was saved', $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(4, $this->assignmentScore());
        $this->assertEquals(0, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_student_summary_shows_old_and_new_homework_scores()
    {
        $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [, $output] = $this->runCommand(['--dry-run' => true]);

        $this->assertStringContainsString($this->student_user->first_name, $output);
        $this->assertStringNotContainsString($this->student_user->email, $output);
        $this->assertStringContainsString('4 / 10', $output);
        $this->assertStringContainsString('5 / 10', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function question_detail_is_only_shown_when_asked_for()
    {
        $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [, $output] = $this->runCommand(['--dry-run' => true]);
        $this->assertStringNotContainsString('By question', $output);
        $this->assertStringNotContainsString('Late penalty changes', $output);
        $this->assertStringNotContainsString('Q1:', $output);

        [, $output] = $this->runCommand(['--dry-run' => true, '--by-question' => true]);
        $this->assertStringContainsString('By question', $output);
        $this->assertStringContainsString('Q1: 20% -> 0%', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function skipped_scores_point_to_the_per_question_table()
    {
        $this->webworkSubmission(0.5, 3.3);
        $this->setDue('2026-01-11 12:00:00');

        [, $output] = $this->runCommand(['--dry-run' => true]);

        $this->assertStringContainsString('1 skipped', $output);
        $this->assertStringContainsString('--by-question to see why', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_header_says_how_grades_reach_the_lms()
    {
        $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        $this->assignment->update(['lms_grade_passback' => 'automatic']);
        [, $output] = $this->runCommand(['--dry-run' => true]);
        $this->assertStringContainsString('LMS grade passback: automatic', $output);

        $this->assignment->update(['lms_grade_passback' => 'manual']);
        [, $output] = $this->runCommand(['--dry-run' => true]);
        $this->assertStringContainsString('LMS grade passback: manual', $output);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function without_a_terminal_nothing_is_saved_unless_forced()
    {
        // Like running it on Vapor: no terminal to answer the question.
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand(['--force' => false]);

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('Run again with --force', $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(0, DB::table('late_penalty_recalculations')->count());

        $this->runCommand();
        $this->assertEquals(5, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function running_it_again_changes_nothing()
    {
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        $this->runCommand();
        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('No scores need to change.', $output);
        $this->assertEquals(5, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(1, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function attempt_and_hint_penalties_are_kept_for_real_time_assignments()
    {
        // Third accepted submission (2 x 10% attempt penalty) plus a 5% hint penalty: 10 x 0.75 = 7.5.
        // Stored with 20% off: 6. With the due date moved later: 7.5.
        $this->useAttemptAndHintPenalties('real time');
        $submission_id = $this->webworkSubmission(1, 6, 3);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(7.5, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function learning_tree_assignments_are_scored_like_real_time()
    {
        // A learning-tree reset deletes the submission, so the attempt count is reliable here.
        $this->useAttemptAndHintPenalties('learning tree');
        $submission_id = $this->webworkSubmission(1, 6, 3);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(7.5, $this->scoreIn('submissions', $submission_id));
    }

    private function useAttemptAndHintPenalties(string $assessment_type): void
    {
        $this->assignment->update([
            'assessment_type' => $assessment_type,
            'number_of_allowed_attempts' => 'unlimited',
            'number_of_allowed_attempts_penalty' => 10,
            'can_view_hint' => 1,
            'hint_penalty' => 5,
        ]);
        DB::table('shown_hints')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_score_that_does_not_match_is_left_alone()
    {
        // 10 x 0.5 = 5 before the late penalty, but 3.3 can't come from 5 under any 10% step.
        $submission_id = $this->webworkSubmission(0.5, 3.3);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand(['--by-question' => true]);

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('does not match the stored score', $output);
        $this->assertEquals(3.3, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(0, DB::table('late_penalty_recalculations')->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_submission_with_no_history_is_skipped()
    {
        $submission_id = $this->webworkSubmission(0.5, 4);
        DB::table('submission_histories')->delete();
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand(['--by-question' => true]);

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('submission time is unknown', $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_score_with_an_instructor_override_is_skipped()
    {
        $submission_id = $this->webworkSubmission(0.5, 4);
        DB::table('submission_score_overrides')->insert([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand(['--by-question' => true]);

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString('Instructor score override', $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_score_pending_work_approval_is_recalculated_where_it_waits()
    {
        // Until the work is approved the submission holds 0 and the real score is pending.
        $submission_id = $this->webworkSubmission(0.5, 0);
        $pending_id = DB::table('submitted_work_pending_scores')->insertGetId([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'score' => 4,
            'created_at' => $this->submitted_at,
            'updated_at' => $this->submitted_at,
        ]);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(5, $this->scoreIn('submitted_work_pending_scores', $pending_id));
        $this->assertEquals(0, $this->scoreIn('submissions', $submission_id));
        $this->assertEquals(0, $this->assignmentScore());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function h5p_activity_set_scores_are_recomputed_from_every_part()
    {
        // Max score 4; the parts scored 1 and 2: 3/4 x 10 = 7.5. Stored with 20% off: 6.
        $this->question->update(['technology' => 'h5p']);
        DB::table('h5p_max_scores')->insert([
            'question_id' => $this->question->id,
            'max_score' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $part = function (string $sub_content_id, int $scaled) {
            return json_encode([
                'object' => ['id' => "https://studio.libretexts.org/h5p/1/embed?subContentId=$sub_content_id"],
                'result' => ['score' => ['raw' => $scaled, 'scaled' => $scaled, 'max' => 2]],
            ]);
        };
        foreach ([['a', 1], ['b', 2]] as [$sub_content_id, $scaled]) {
            DB::table('h5p_activity_sets')->insert([
                'user_id' => $this->student_user->id,
                'assignment_id' => $this->assignment->id,
                'question_id' => $this->question->id,
                'sub_content_id' => $sub_content_id,
                'submission' => $part($sub_content_id, $scaled),
                'correct' => $scaled,
                'submission_count' => 1,
                'created_at' => $this->submitted_at,
                'updated_at' => $this->submitted_at,
            ]);
        }
        $last_part = $part('b', 2);
        $submission_id = DB::table('submissions')->insertGetId([
            'user_id' => $this->student_user->id,
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'submission' => $last_part,
            'score' => 6,
            'submission_count' => 0,
            'answered_correctly_at_least_once' => 0,
            'created_at' => $this->submitted_at,
            'updated_at' => $this->submitted_at,
        ]);
        $this->history($last_part);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(7.5, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function auto_graded_discuss_it_scores_are_recalculated()
    {
        // Full 10 points with 20% off = 8; with the due date moved later: 10.
        $submission_file_id = $this->discussItScore(8, null);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertEquals(10, $this->scoreIn('submission_files', $submission_file_id));
        $this->assertDatabaseHas('late_penalty_recalculations', [
            'record_table' => 'submission_files',
            'record_id' => $submission_file_id,
            'new_score' => 10,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function discuss_it_scores_set_by_a_grader_are_not_touched()
    {
        $submission_file_id = $this->discussItScore(8, $this->user->id);
        $this->setDue('2026-01-11 12:00:00');

        $this->runCommand();

        $this->assertEquals(8, $this->scoreIn('submission_files', $submission_file_id));
    }

    private function discussItScore(float $score, ?int $grader_id): int
    {
        // The same columns DiscussionComment::updateScore() fills in.
        return DB::table('submission_files')->insertGetId([
            'assignment_id' => $this->assignment->id,
            'question_id' => $this->question->id,
            'user_id' => $this->student_user->id,
            'type' => 'discuss_it',
            'original_filename' => '',
            'submission' => '',
            'date_submitted' => $this->submitted_at,
            'date_graded' => $this->submitted_at,
            'grader_id' => $grader_id,
            'score' => $score,
            'created_at' => $this->submitted_at,
            'updated_at' => $this->submitted_at,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function fake_students_are_ignored()
    {
        DB::table('users')->where('id', $this->student_user->id)->update(['fake_student' => 1]);
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        $this->runCommand();

        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function assignments_without_a_deduction_policy_are_not_changed()
    {
        $this->assignment->update(['late_policy' => 'marked late']);
        $submission_id = $this->webworkSubmission(0.5, 4);
        $this->setDue('2026-01-11 12:00:00');

        [$exit_code, $output] = $this->runCommand();

        $this->assertEquals(0, $exit_code, $output);
        $this->assertStringContainsString("not 'deduction'", $output);
        $this->assertEquals(4, $this->scoreIn('submissions', $submission_id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_penalty_rule_matches_adapt()
    {
        // Penalties are computed in memory; they must equal Submission::latePenaltyPercentGivenUserId().
        $command = app(\App\Services\LatePenaltyRecalculator::class);
        $submission = new \App\Submission();
        $due = Carbon::parse('2026-01-09 12:00:00', 'UTC');
        $times = ['2026-01-09 11:59:00', '2026-01-09 12:00:00', '2026-01-10 11:00:00', '2026-01-10 12:00:00', '2026-01-30 12:00:00'];
        $policies = [[10, '1 day'], [30, '1 day'], [15, '6 hours'], [25, 'once']];
        foreach ($policies as [$percent, $period]) {
            $this->assignment->update(['late_deduction_percent' => $percent, 'late_deduction_application_period' => $period]);
            $assignment = Assignment::find($this->assignment->id);
            foreach ($times as $time) {
                $submitted_at = Carbon::parse($time, 'UTC');
                $this->assertEquals(
                    $submission->latePenaltyPercentGivenUserId($this->student_user->id, $assignment, $submitted_at->copy()),
                    $command->penaltyPercent($assignment, $due, $submitted_at),
                    "$percent% per $period, submitted $time");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_unknown_assignment_is_an_error()
    {
        $exit_code = Artisan::call('assignment:recalculateLatePenalties', ['assignment_id' => 999999999, '--no-interaction' => true]);
        $this->assertEquals(1, $exit_code);
        $this->assertStringContainsString('No assignment with id 999999999', Artisan::output());
    }
}
