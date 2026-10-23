<?php

namespace Tests\Feature\Students;

use App\FinalGrade;
use App\Section;
use Tests\TestCase;
use App\User;
use App\Score;
use App\Course;
use App\Assignment;
use App\Enrollment;
use App\SubmissionFile;
use App\Traits\Test;
use App\Traits\Statistics;

class StudentsAssignmentsIndexTest extends TestCase
{
    use Test;
    use Statistics;

    public function setup(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
        $this->student_user = factory(User::class)->create();
        $this->student_user->role = 3;
        $this->course = factory(Course::class)->create(['user_id' => $this->user->id, 'students_can_view_weighted_average' => 1]);
        $this->section = factory(Section::class)->create(['course_id' => $this->course->id]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user->id,
            'section_id' => $this->section->id,
            'course_id' => $this->course->id
        ]);

        $this->assignment = factory(Assignment::class)->create(['course_id' => $this->course->id, 'show_scores' => 1]);

        //create a student and enroll in the class

        $this->student_user_2 = factory(User::class)->create();
        $this->student_user_4 = factory(User::class)->create();

        $this->student_user_2->role = 3;
        $this->student_user_4->role = 3;

        factory(Enrollment::class)->create([
            'user_id' => $this->student_user_2->id,
            'course_id' => $this->course->id,
            'section_id' => $this->section->id
        ]);
        factory(Enrollment::class)->create([
            'user_id' => $this->student_user_4->id,
            'course_id' => $this->course->id,
            'section_id' => $this->section->id
        ]);

        //student not enrolled
        $this->student_user_3 = factory(User::class)->create();
        $this->student_user_3->role = 3;
        $this->submission_file = factory(SubmissionFile::class)
            ->create([
                'assignment_id' => $this->assignment->id,
                'type' => 'a',
                'user_id' => $this->student_user->id
            ]);

        $finalGrade = new FinalGrade();

        FinalGrade::create(['course_id' => $this->course->id,
            'letter_grades' => $finalGrade->defaultLetterGrades()]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_final_score_for_the_student_if_all_assignments_show_scores_and_student_is_not_assigned_to_all()
    {
        //4 assignments with 2 different weights
        $this->createAssignmentGroupWeightsAndAssignments();
        //GROUP 1 scores: 5/30 and 2/3 weight of 10
        //GROUP 2 scores: 25/30 weight of 90
        //Was:
        //10*((2/2 + 5/30 + 2/3)/3)+90*(.5*(25/100 + 75/100))=51.11%
        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            if ($assignment->name !== $this->assignment_4->name) {
                $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
            }
        }
//Now:   //10*((2/2 + 5/30 + 2/3)/3)+90*((25/100))=28.61% since not assigned to the last one
        Score::where('assignment_id', $this->assignment_4->id)
            ->where('user_id', $this->student_user->id)
            ->delete();
        $this->actingAs($this->student_user)->getJson("/api/scores/{$this->course->id}/get-course-scores-by-user")
            ->assertJson(['weighted_score' => '28.61%']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_final_score_for_the_student_if_all_assignments_show_scores_and_student_is_assigned_to_all()
    {
        //4 assignments with 2 different weights
        $this->createAssignmentGroupWeightsAndAssignments();
        //GROUP 1 scores: 5/30 and 2/3 weight of 10
        //GROUP 2 scores: 25/30 weight of 90
        //10*((2/2 + 5/30 + 2/3)/3)+90*(.5*(25/100 + 75/100))=51.11%
        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
        }
        $this->actingAs($this->student_user)->getJson("/api/scores/{$this->course->id}/get-course-scores-by-user")
            ->assertJson(['weighted_score' => '51.11%']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_z_score_for_an_assignment()
    {
        $scores = [80, 40, 36];
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user_2->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user_4->id);

        Score::create(['user_id' => $this->student_user->id, 'score' => $scores[0], 'assignment_id' => $this->assignment->id]);
        Score::create(['user_id' => $this->student_user_2->id, 'score' => $scores[1], 'assignment_id' => $this->assignment->id]);
        Score::create(['user_id' => $this->student_user_4->id, 'score' => $scores[2], 'assignment_id' => $this->assignment->id]);
        $mean = array_sum($scores) / count($scores);
        $std_dev = $this->stats_standard_deviation($scores);
        $z_score = Round(($scores[0] - $mean) / $std_dev, 2);
        $response = $this->actingAs($this->student_user)->getJson("/api/assignments/courses/{$this->course->id}");

        $this->assertEquals($z_score, $response['assignments'][0]['z_score']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function must_be_enrolled_in_the_course_to_view_the_score()
    {

        $this->createAssignmentGroupWeightsAndAssignments();
        $this->actingAs($this->student_user_3)->getJson("/api/scores/{$this->course->id}/get-course-scores-by-user")
            ->assertJson(['message' => 'You are not allowed to view these scores.']);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function course_must_have_students_can_view_weighted_average_enabled_to_view_the_score()
    {
        $this->course->students_can_view_weighted_average = 0;
        $this->course->save();
        $this->createAssignmentGroupWeightsAndAssignments();
        $this->actingAs($this->student_user)->getJson("/api/scores/{$this->course->id}/get-course-scores-by-user")
            ->assertJson(['weighted_score' => false]);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_assignment_file_info_if_owner()
    {
        $this->actingAs($this->student_user)->getJson("/api/assignment-files/assignment-file-info-by-student/{$this->assignment->id}")
            ->assertJson(['type' => 'success']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_get_assignment_file_info_if_not_owner()
    {
        $this->actingAs($this->student_user_2)->getJson("/api/assignment-files/assignment-file-info-by-student/{$this->assignment->id}")
            ->assertJson(['type' => 'error',
                'message' => 'You are not allowed to get the information on this file submission.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_store_assignment_file_if_enrolled_in_course()
    {

        $this->markTestIncomplete(
            'https://laravel.com/docs/7.x/http-tests#testing-file-uploads'
        );

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function computes_the_correct_z_score_at_the_course_level()
    {
        $this->course->show_z_scores = 1;
        $this->course->save();
        $this->createAssignmentGroupWeightsAndAssignments();
        $assignments = Assignment::all();
        foreach ($assignments as $assignment) {
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user->id);
            $this->assignUserToAssignment($assignment->id, 'course',$this->course->id, $this->student_user_2->id);
            $this->assignUserToAssignment($assignment->id, 'course', $this->course->id, $this->student_user_4->id);
        }
        $user_score = 51.11;
        $course_scores = [0, 0, 51.11];
        $mean = array_sum($course_scores) / 3;
        $std_dev = $this->stats_standard_deviation($course_scores);
        $z_score = Round(($user_score - $mean) / $std_dev, 2);

        $this->actingAs($this->student_user)->getJson("/api/scores/{$this->course->id}/get-course-scores-by-user")
            ->assertJson(['z_score' => '1.41']);

    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function correctly_computes_the_z_score_for_an_assignment_if_nothing_submitted()
    {
        $scores = [40, 36];
        Score::create(['user_id' => $this->student_user_2->id, 'score' => $scores[0], 'assignment_id' => $this->assignment->id]);
        Score::create(['user_id' => $this->student_user_4->id, 'score' => $scores[1], 'assignment_id' => $this->assignment->id]);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user_2->id);
        $this->assignUserToAssignment($this->assignment->id, 'course', $this->course->id, $this->student_user_4->id);

        $response = $this->actingAs($this->student_user)->getJson("/api/assignments/courses/{$this->course->id}");

        $this->assertEquals('N/A', $response['assignments'][0]['z_score']);
    }
}
