<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Grader extends Model
{
    protected $guarded = [];

    /**
     * The students in the current grader's sections, in enrollment order (the same order and the same students as
     * Course::enrolledUsers, which instructors get).
     *
     * @param int $course_id
     * @return Collection
     */
    public function enrollmentsByCourse(int $course_id)
    {
        $enrolled_user_ids = DB::table('graders')
            ->join('enrollments', 'graders.section_id', '=', 'enrollments.section_id')
            ->join('sections', 'graders.section_id', '=', 'sections.id')
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->select('users.id')
            ->where('sections.course_id', $course_id)
            ->where('users.fake_student', 0)
            ->where('users.formative_student', 0)
            ->where('graders.user_id', Auth::user()->id)
            ->orderBy('enrollments.id')
            ->get()
            ->pluck('id')
            ->unique()
            ->values()
            ->toArray();
        $users_by_id = User::whereIn('id', $enrolled_user_ids)->get()->keyBy('id');
        $enrolled_users = [];
        foreach ($enrolled_user_ids as $enrolled_user_id) {
            if (isset($users_by_id[$enrolled_user_id])) {
                $enrolled_users[] = $users_by_id[$enrolled_user_id];
            }
        }
        return new Collection($enrolled_users);
    }
}
