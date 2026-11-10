<?php

namespace App\Console\Commands\AI;

use App\Exceptions\Handler;
use App\QuestionMediaUpload;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class fixUnprocessedTranscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:unprocessedTranscriptions';

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
            $pending_transcriptions = DB::table('pending_transcriptions')
                ->whereBetween('created_at', [Carbon::now()->subMinutes(2), Carbon::now()->subMinute()])
                ->where('message', '')
                ->where('filename', 'NOT LIKE', '%.webm')
                ->get();

            $pending_transcriptions = DB::table('pending_transcriptions')
                ->whereBetween('created_at', [Carbon::now()->subMinutes(2), Carbon::now()->subMinute()])
                ->where('message', '')
                ->where('filename', 'NOT LIKE', '%.webm')
                ->get();
            $pending_transcriptions = DB::table('pending_transcriptions')
                ->where('message', '')
                ->whereIn('environment', ['staging', 'production','dev'])
                ->limit(2)
                ->orderBy('id', 'desc')
                ->get();

            echo count($pending_transcriptions) . "\r\n";
            foreach ($pending_transcriptions as $pending_transcription) {
                echo $pending_transcription->filename . "\r\n";
                $questionMediaUpload = new QuestionMediaUpload();
                $questionMediaUpload->processTranscribe($pending_transcription->filename);
            }
            return 0;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            return 1;
        }
    }
}
