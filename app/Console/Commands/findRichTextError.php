<?php

namespace App\Console\Commands;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;

class findRichTextError extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'find:richTextError';

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
    public function handle()
    {
        try {
            $ids = DB::table('assignment_question')
                ->where('open_ended_submission_type', 'rich text')
                ->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('assignment_question')
                    ->whereIn('id', $ids)
                    ->update([
                        'open_ended_submission_type' => 'text',
                        'open_ended_text_editor' => 'rich'
                    ]);
                // Telegram messages are limited to 4096 characters.
                foreach ($ids->chunk(400) as $ids_chunk) {
                    Telegram::sendMessage([
                        'chat_id' => config('myconfig.telegram_channel_id'),
                        'parse_mode' => 'HTML',
                        'text' => "Updated assignment_question records from 'rich text' to 'text' with the rich text editor. IDs: " . $ids_chunk->implode(', ')
                    ]);
                }
            }
            return 0;
        } catch (Exception $e) {
            echo $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return 1;
    }
}
