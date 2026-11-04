<?php

namespace App\Console\Commands\AI;

use App\Console\Commands\Cleanup\removeUnenrolledTestingStudents;
use App\Exceptions\Handler;
use App\Jobs\InitProcessTranscribe;
use App\Question;
use App\QuestionMediaUpload;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class notifyFailedTranscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:failedTranscriptions';

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
            $oneHourAgo = Carbon::now()->subHour();  // 1 hour ago
            $twoHoursAgo = Carbon::now()->subHours(2); // 2 hours ago

            $bad_discussion_comments = DB::table('discussion_comments')
                ->whereBetween('created_at', [$twoHoursAgo, $oneHourAgo])
                ->whereNotNull('file')
                ->where('status', '<>', 'transcript completed')
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();

            $bad_question_media_uploads = DB::table('question_media_uploads')
                ->whereBetween('created_at', [$twoHoursAgo, $oneHourAgo])
                ->whereNotNull('s3_key')
                ->where('status', '<>', 'transcript completed')
                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();
            $text = '';
            if ($bad_discussion_comments) {
                $text .= "Bad discussion comments: " . implode(", ", $bad_discussion_comments);
            }
            if ($bad_question_media_uploads) {
                $text .= "Bad question media uploads: " . implode(", ", $bad_question_media_uploads);
            }
            if ($text) {
                Telegram::sendMessage([
                    'chat_id' => config('myconfig.telegram_channel_id'),
                    'parse_mode' => 'HTML',
                    'text' => $text
                ]);
            }
            return 0;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            return 1;
        }
    }
}
