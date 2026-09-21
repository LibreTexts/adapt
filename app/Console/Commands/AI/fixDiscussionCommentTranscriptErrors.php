<?php

namespace App\Console\Commands\AI;

use App\DiscussionComment;
use App\Exceptions\Handler;
use Exception;
use Illuminate\Console\Command;

class fixDiscussionCommentTranscriptErrors extends Command
{
    protected $signature = 'fix:DiscussionCommentTranscriptErrors {--limit=5 : Maximum number of records to process}';

    protected $description = 'Reprocess discussion comments with error status';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $limit = $this->option('limit');
            $discussion_comments = DiscussionComment::where('status', 'error')
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get();
            $this->info("Found {$discussion_comments->count()} discussion comments with error status.");
            foreach ($discussion_comments as $discussion_comment) {
                $this->info("Processing discussion comment ID: {$discussion_comment->id}");
                $this->call('init:ProcessTranscription', [
                    's3_key' => $discussion_comment->file,
                    'upload_type' => 'discussion_comment',
                ]);
            }
            foreach ($discussion_comments as $discussion_comment) {
                $this->call('init:ProcessTranscription', [
                    's3_key' => $discussion_comment->file,
                    'upload_type' => 'discussion_comment',
                ]);
            }
        } catch (Exception $e) {
            $this->info($e->getMessage());
            $h = new Handler(app());
            $h->report($e);
        }
        return 0;
    }
}
