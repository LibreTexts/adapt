<?php

namespace App;

use App\Helpers\Helper;
use App\Traits\DateFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\AssignmentProperties;

class AssignmentTemplate extends Model
{
    protected $guarded = [];
    use AssignmentProperties;
    use DateFormatter;

    public function createAssignmentFromTemplate(Course $course,
                                                 int $user_id,
                                                 int $assignment_template,
                                                 string $title,
                                                 string $instructions = '')
    {

        $assignment = DB::table('assignments')->where('name', $title)
            ->where('course_id', $course->id)
            ->first();
        if (!$assignment) {
            $assignment_template = AssignmentTemplate::find($assignment_template);

            $assignment_info = $assignment_template->toArray();
            $assignment_info['name'] = $title;
            $assignment_info['instructions'] = $instructions;
            $assignment_info['course_id'] = $course->id;
            $assignment_info['order'] = $course->assignments->count() + 1;
            foreach (['id', 'template_name', 'template_description', 'user_id', 'created_at', 'updated_at', 'assign_to_everyone'] as $value) {
                unset($assignment_info[$value]);
            }
            $assign_tos = Helper::getDefaultAssignTos($course->id);
            if ($assignment_info['late_policy'] !== 'not accepted') {
                foreach ($assign_tos as &$assign_to) {
                    $assign_to['final_submission_deadline_date'] = Carbon::now()->addDay()->format('Y-m-d');
                    $assign_to['final_submission_deadline_time'] = '9:00 AM';
                }
                unset($assign_to);
            }
            $assignment = Assignment::create($assignment_info);

            $this->addAssignTos($assignment, $assign_tos, new Section(), User::find($user_id));
        }
        return $assignment;
    }
}
