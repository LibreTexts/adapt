<?php

namespace App\Console\Commands;

use App\DiscussionComment;
use App\Exceptions\Handler;
use App\QuestionMediaUpload;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class fixBadTranscriptionQuestionMediaUploads extends Command
{
    private const COMPLETED_STATUS = 'transcript completed';
    private const COMPLETED_MESSAGE = 'Transcript sent to S3';
    private const LOOKBACK_DAYS = 14;
    private const UPLOAD_TYPES = [
        'question_media_upload' => [
            'model' => QuestionMediaUpload::class,
            'filename_column' => 'original_filename',
            's3_key_column' => 's3_key',
        ],
        'discussion_comment' => [
            'model' => DiscussionComment::class,
            'filename_column' => 'file',
            's3_key_column' => 'file',
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:BadTranscriptionQuestionMediaUploads
                            {--type= : Only run one upload type: question_media_upload or discussion_comment}
                            {--limit=10 : The maximum number of files to process per upload type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-runs transcription for mp3/mp4/webm question media uploads and discussion comments from the last 14 days that did not complete';

    /**
     * @var int
     */
    private $limit;

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
     * Execute the console command.
     *
     * @return int
     * @throws Exception
     */
    public function handle(): int
    {
        $type = $this->option('type');
        if ($type && !isset(self::UPLOAD_TYPES[$type])) {
            $this->error("Invalid type: $type. Use one of: " . implode(', ', array_keys(self::UPLOAD_TYPES)));
            return 1;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $this->error('Invalid limit: ' . $this->option('limit') . '. Use a whole number of 1 or more.');
            return 1;
        }
        $this->limit = $limit;

        $number_failed = 0;
        foreach ($type ? [$type] : array_keys(self::UPLOAD_TYPES) as $upload_type) {
            try {
                $number_failed += $this->reprocess($upload_type);
            } catch (Exception $e) {
                $number_failed++;
                $this->reportException($upload_type, $e);
            }
        }
        return $number_failed ? 1 : 0;
    }

    /**
     * Re-runs transcription for one upload type, printing the rows and their results.
     *
     * @param string $upload_type
     * @return int the number of rows that failed
     * @throws Exception
     */
    private function reprocess(string $upload_type): int
    {
        $config = self::UPLOAD_TYPES[$upload_type];
        $rows = $this->incompleteTranscriptions($config['model']::query(), $config['filename_column'])->get();

        $this->line('');
        $this->info("$upload_type: {$rows->count()} found (limit $this->limit)");
        if ($rows->isEmpty()) {
            return 0;
        }

        $this->table(
            ['ID', 'File', 'Status', 'Message', 'Created at'],
            $rows->map(function ($row) use ($config) {
                return [
                    $row->id,
                    $row->{$config['filename_column']},
                    $row->status,
                    Str::limit((string)$row->message, 50),
                    $row->created_at,
                ];
            })->all()
        );

        $results = [];
        $number_failed = 0;
        foreach ($rows as $row) {
            $s3_key = $row->{$config['s3_key_column']};
            try {
                $exit_code = $this->call('init:ProcessTranscription', [
                    's3_key' => $s3_key,
                    'upload_type' => $upload_type,
                ]);
                $failed = $exit_code !== 0;
                $result = "Failed (exit code $exit_code)";
                if (!$failed) {
                    //InitProcessTranscribe catches its own errors and only records them on the row, so read that row back
                    $upload = new QuestionMediaUpload()->getUploadTypeModel($upload_type, $s3_key);
                    $failed = $upload->status === 'error';
                    $result = $failed
                        ? 'Failed: ' . Str::limit((string)$upload->message, 80)
                        : "Sent (status: $upload->status)";
                }
            } catch (Exception $e) {
                $this->reportException("$upload_type $row->id", $e);
                $result = 'Failed: ' . Str::limit($e->getMessage(), 80);
                $failed = true;
            }
            if ($failed) {
                $number_failed++;
            }
            $results[] = [$row->id, $s3_key, $result];
        }

        $this->table(['ID', 'S3 key', 'Result'], $results);
        $this->info("$upload_type: " . ($rows->count() - $number_failed) . " sent, $number_failed failed");

        return $number_failed;
    }

    /**
     * Limits a query to mp3/mp4/webm rows from the lookback window whose transcription did not complete.
     * Uses <=> because status and message can be NULL.
     *
     * @param Builder $query
     * @param string $filename_column
     * @return Builder
     */
    private function incompleteTranscriptions(Builder $query, string $filename_column): Builder
    {
        return $query
            ->whereRaw("LOWER(SUBSTRING_INDEX($filename_column, '.', -1)) IN ('mp4', 'mp3', 'webm')")
            ->whereRaw('NOT (status <=> ? AND message <=> ?)', [self::COMPLETED_STATUS, self::COMPLETED_MESSAGE])
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('created_at', 'desc')
            ->limit($this->limit);
    }

    /**
     * @param string $context
     * @param Exception $e
     * @return void
     * @throws Exception
     */
    private function reportException(string $context, Exception $e): void
    {
        $this->error("$context: {$e->getMessage()}");
        $h = new Handler(app());
        $h->report($e);
    }
}
