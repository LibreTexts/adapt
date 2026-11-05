<?php

namespace App\Helpers;

use App\Assignment;
use App\User;
use Faker\Factory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stand-in student names for graders who can't see real ones. Each student gets one random stand-in per assignment,
 * saved so it stays the same in the Open Grader and every rubric preview. The database refuses a name that's already
 * used in the assignment, so two students in an assignment can never share one.
 */
class StudentAnonymizer
{
    const MAX_ATTEMPTS = 5;

    /**
     * @param User $viewer
     * @param Assignment $assignment
     * @return bool
     */
    public static function hiddenFrom(User $viewer, Assignment $assignment): bool
    {
        return (int)$viewer->role === 4 && !$assignment->graders_can_see_student_names;
    }

    /**
     * @param int $assignment_id
     * @param array $student_user_ids
     * @return array user id => ['first_name' => string, 'last_name' => string, 'name' => string]
     */
    public static function names(int $assignment_id, array $student_user_ids): array
    {
        $student_user_ids = array_values(array_unique(array_map('intval', $student_user_ids)));
        $names = self::savedNames($assignment_id, $student_user_ids);
        //another request can take a name at the same moment; only that student is tried again
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $missing_user_ids = array_diff($student_user_ids, array_keys($names));
            if (!$missing_user_ids) {
                return $names;
            }
            self::createNames($assignment_id, $missing_user_ids);
            $names = self::savedNames($assignment_id, $student_user_ids);
        }
        if (array_diff($student_user_ids, array_keys($names))) {
            throw new RuntimeException("Could not create stand-in names for assignment $assignment_id.");
        }
        return $names;
    }

    /**
     * @param int $assignment_id
     * @param int $student_user_id
     * @return string
     */
    public static function fullName(int $assignment_id, int $student_user_id): string
    {
        return self::names($assignment_id, [$student_user_id])[$student_user_id]['name'];
    }

    /**
     * @param int $assignment_id
     * @param array $student_user_ids
     * @return array
     */
    private static function savedNames(int $assignment_id, array $student_user_ids): array
    {
        $names = [];
        foreach (array_chunk($student_user_ids, 1000) as $user_ids) {
            $rows = DB::table('student_stand_in_names')
                ->where('assignment_id', $assignment_id)
                ->whereIn('user_id', $user_ids)
                ->get();
            foreach ($rows as $row) {
                $names[(int)$row->user_id] = ['first_name' => $row->first_name,
                    'last_name' => $row->last_name,
                    'name' => "$row->first_name $row->last_name"];
            }
        }
        return $names;
    }

    /**
     * @param int $assignment_id
     * @param array $student_user_ids
     * @return void
     */
    private static function createNames(int $assignment_id, array $student_user_ids)
    {
        $used = [];
        $rows = DB::table('student_stand_in_names')
            ->where('assignment_id', $assignment_id)
            ->get(['first_name', 'last_name']);
        foreach ($rows as $row) {
            $used[mb_strtolower("$row->first_name $row->last_name")] = true;
        }
        $faker = Factory::create();
        foreach ($student_user_ids as $student_user_id) {
            do {
                $first_name = $faker->firstName;
                $last_name = $faker->lastName;
                $key = mb_strtolower("$first_name $last_name");
            } while (isset($used[$key]));
            $used[$key] = true;
            DB::table('student_stand_in_names')->insertOrIgnore([
                'assignment_id' => $assignment_id,
                'user_id' => $student_user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'created_at' => now(),
                'updated_at' => now()]);
        }
    }
}
