<?php

namespace App\Jobs;

use App\AssignmentSyncQuestion;
use App\Course;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\NonUpdatedQuestionRevision;
use App\PendingQuestionRevision;
use App\QuestionRevision;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUpdateAllQuestionRevisions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Course
     */
    private $course;

    /**
     * @var bool
     */
    private $changesAreTopical;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Course $course, bool $changesAreTopical = false)
    {

        $this->course = $course;
        $this->changesAreTopical = $changesAreTopical;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function handle()
    {

        try {
            $client = Helper::centrifuge();
            $nonUpdatedQuestionRevision = new NonUpdatedQuestionRevision();
            $questionRevision = new QuestionRevision();
            $assignmentSyncQuestion = new AssignmentSyncQuestion();
            $pendingQuestionRevision = new PendingQuestionRevision();
            $response = $nonUpdatedQuestionRevision->updateToLatestQuestionRevisionByCourse($this->course,
                $questionRevision,
                $assignmentSyncQuestion,
                $pendingQuestionRevision,
                $this->changesAreTopical);
            $client->publish("update-all-question-revisions-{$this->course->id}", ["type" => $response['type'], "message" => $response['message']]);
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $client = Helper::centrifuge();
            $client->publish("update-all-question-revisions-{$this->course->id}", ["type" => 'error', "message" => "We were unable to update all of the question revisions.  Please contact support for assistance."]);
        }
    }
}
