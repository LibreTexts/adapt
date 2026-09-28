<?php

namespace App;

use App\Exceptions\Handler;
use \Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use \Illuminate\Http\Request;
use Snowfire\Beautymail\Beautymail;

class Email extends Model
{
    /**
     * @throws Exception
     */
    public function sendAssignmentDueReminders()
    {

        $assignment_notifications = DB::table('notifications')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->join('assign_to_users', 'users.id', '=', 'assign_to_users.user_id')
            ->join('assign_to_timings', 'assign_to_users.assign_to_timing_id', '=', 'assign_to_timings.id')
            ->join('assignments', 'assign_to_timings.assignment_id', '=', 'assignments.id')
            ->join('courses', 'assignments.course_id', '=', 'courses.id')
            ->whereBetween('assign_to_timings.due', [
                DB::raw("DATE_FORMAT(
        DATE_ADD(UTC_TIMESTAMP(), INTERVAL notifications.hours_until_due HOUR),
        '%Y-%m-%d %H:%i:00'
    )"),
                DB::raw("DATE_FORMAT(
        DATE_ADD(UTC_TIMESTAMP(), INTERVAL notifications.hours_until_due HOUR),
        '%Y-%m-%d %H:%i:59'
    )")
            ])
            ->where('assignments.notifications', 1)
            ->select('users.id AS user_id', 'users.first_name', 'users.last_name', 'users.email',
                'assignments.name AS assignment_name', 'assignments.id AS assignment_id',
                'courses.name AS course_name',
                'notifications.hours_until_due')
            ->get();
        $extension_notifications = DB::table('notifications')
            ->join('users', 'notifications.user_id', '=', 'users.id')
            ->join('extensions', 'users.id', '=', 'extensions.user_id')
            ->join('assignments', 'extensions.assignment_id', '=', 'assignments.id')
            ->join('courses', 'assignments.course_id', '=', 'courses.id')
            ->where('extensions.extension', DB::raw("DATE_FORMAT(DATE_ADD(UTC_TIMESTAMP(), INTERVAL notifications.hours_until_due HOUR), '%Y-%m-%d %H:%i:00')"))
            ->where('assignments.notifications', 1)
            ->select('users.id as user_id', 'users.first_name', 'users.last_name', 'users.email',
                'assignments.name AS assignment_name', 'assignments.id AS assignment_id',
                'courses.name AS course_name',
                'notifications.hours_until_due')
            ->get();

        $sent_emails = [];
//extensions override the other ones
        foreach ($extension_notifications as $notification) {
            try {
                $this->processAssignmentDueReminders($notification);
                if (!isset($sent_emails[$notification->assignment_id])) {
                    $sent_emails[$notification->assignment_id] = [];
                }
                $sent_emails[$notification->assignment_id][] = $notification->user_id;
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
            }
        }

        foreach ($assignment_notifications as $notification) {
            try {
                if (isset($sent_emails[$notification->assignment_id]) && in_array($notification->user_id, $sent_emails[$notification->assignment_id])) {
                    continue;
                }
                $this->processAssignmentDueReminders($notification);
                if (!isset($sent_emails[$notification->assignment_id])) {
                    $sent_emails[$notification->assignment_id] = [];
                }
                $sent_emails[$notification->assignment_id][] = $notification->user_id;
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
            }
        }
    }

    function processAssignmentDueReminders($notification)
    {
        $mail_info = [
            'student_first_name' => $notification->first_name,
            'assignment' => $notification->assignment_name,
            'course' => $notification->course_name,
            'hours_until_due' => $notification->hours_until_due,
            'assignment_link' => config('app.url') . "/students/assignments/$notification->assignment_id/summary",
            'notifications_link' => config('app.url') . "/settings/notifications"
        ];
        Log::info('Regular assignment reminder:' . $notification->assignment_id . ' ' . $notification->user_id);
        $beautymail = app()->make(Beautymail::class);
        $beautymail->send('emails.assignment_due_reminder', $mail_info, function ($message)
        use ($notification) {
            $message->from('adapt@libretexts.org')
                ->to($notification->email, $notification->first_name)
                ->subject('An Upcoming Assignment Is Due');
        });
    }


}
