<?php

namespace Tests\Feature\Instructors;

use PHPUnit\Framework\Attributes\Test;
use App\Course;
use App\Enrollment;
use App\Grader;
use App\Section;
use App\User;
use Tests\TestCase;

/**
 * Graders get their students in the same order as instructors (enrollment order), without formative students.
 */
class GraderStudentOrderTest extends TestCase
{
    #[Test]
    public function graders_get_students_in_enrollment_order_like_instructors()
    {
        $instructor = factory(User::class)->create();
        $course = factory(Course::class)->create(['user_id' => $instructor->id]);
        $section = factory(Section::class)->create(['course_id' => $course->id]);
        $other_section = factory(Section::class)->create(['course_id' => $course->id]);
        //accounts created first, but enrolled last
        $older_account = factory(User::class)->create(['role' => 3]);
        $middle_account = factory(User::class)->create(['role' => 3]);
        $newer_account = factory(User::class)->create(['role' => 3]);
        $formative_student = factory(User::class)->create(['role' => 3, 'formative_student' => 1]);
        $not_my_section = factory(User::class)->create(['role' => 3]);
        foreach ([[$newer_account, $section], [$middle_account, $section], [$not_my_section, $other_section],
                     [$formative_student, $section], [$older_account, $section]] as [$student, $student_section]) {
            factory(Enrollment::class)->create([
                'user_id' => $student->id,
                'section_id' => $student_section->id,
                'course_id' => $course->id
            ]);
        }
        $grader_user = factory(User::class)->create(['role' => 4]);
        Grader::create(['user_id' => $grader_user->id, 'section_id' => $section->id]);

        $this->actingAs($grader_user);
        $grader_order = (new Grader())->enrollmentsByCourse($course->id)->pluck('id')->toArray();
        $this->assertEquals([$newer_account->id, $middle_account->id, $older_account->id], $grader_order);

        $instructor_order = Course::find($course->id)->enrolledUsers->pluck('id')
            ->filter(function ($id) use ($grader_order) {
                return in_array($id, $grader_order);
            })->values()->toArray();
        $this->assertEquals($instructor_order, $grader_order);
    }
}
