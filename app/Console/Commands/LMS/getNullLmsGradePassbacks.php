<?php

namespace App\Console\Commands\LMS;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;

class getNullLmsGradePassbacks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:nullLmsGradePassbacks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return int
     * @throws Exception
     */
    public function handle(): int
    {
        try {
            $null_lms_grade_passbacks = DB::table('courses')
                ->join('assignments', 'courses.id', '=', 'assignments.course_id')
                ->join('users', 'courses.user_id', '=', 'users.id')
                ->where('courses.lms', 1)
                ->whereNull('assignments.lms_grade_passback')
                ->where('assignments.formative',0)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('assign_to_timings')
                        ->whereColumn('assign_to_timings.assignment_id', 'assignments.id')
                        ->whereRaw('assign_to_timings.available_from <= UTC_TIMESTAMP()')
                        ->whereRaw('assign_to_timings.due > UTC_TIMESTAMP()');
                })
                ->select(DB::raw('CONCAT(first_name, " " , last_name) AS instructor'),
                    'courses.name AS course_name', 'assignments.name AS assignment_name',
                    'assignments.id AS assignment_id')
                ->get();
            if ($null_lms_grade_passbacks->isNotEmpty()) {
                $message = '';
                foreach ($null_lms_grade_passbacks as $null_lms_grade_passback) {
                    $message .= $null_lms_grade_passback->instructor . ' ' . $null_lms_grade_passback->course_name . ' ' . $null_lms_grade_passback->assignment_name . ' ' . $null_lms_grade_passback->assignment_id . "\r\n";
                }
                Telegram::sendMessage([
                    'chat_id' => config('myconfig.telegram_channel_id'),
                    'parse_mode' => 'HTML',
                    'text' => "Lms grade passbacks that are null: " . $message
                ]);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
            return 1;
        }
        return 0;
    }
}
