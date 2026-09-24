<?php

namespace App;

use App\Traits;
use App\Helpers\Helper;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use DOMDocument;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DiscussionComment extends Model
{
    use Traits\DateFormatter;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        //safety net: the assignment the comment was made in defaults to the assignment of its thread
        static::creating(function (DiscussionComment $discussionComment) {
            if (!$discussionComment->posted_in_assignment_id && $discussionComment->discussion_id) {
                $discussionComment->posted_in_assignment_id = DB::table('discussions')
                    ->where('id', $discussionComment->discussion_id)
                    ->value('assignment_id');
            }
        });
    }

    /**
     * The assignment whose settings, dates, and completion this comment belongs to.
     * For linked (daisy-chained) questions this can differ from the thread's assignment.
     *
     * @return int
     */
    public function postedInAssignmentId(): int
    {
        if ($this->posted_in_assignment_id) {
            return (int)$this->posted_in_assignment_id;
        }
        return (int)DB::table('discussions')->where('id', $this->discussion_id)->value('assignment_id');
    }

    /**
     * @param null $value
     * @return string
     */
    public function getRecordingType($value = null): string
    {
        $discussion_comment = $value ?: $this;
        return $discussion_comment->recording_type === 'audio' || ($discussion_comment->file && strpos($discussion_comment->file, '.mp3') !== false) ? 'audio' : 'video';
    }

    /**
     * @param string $type
     * @param object $discuss_it_settings
     * @param $request
     * @return bool
     * @throws Exception
     */
    public function satisfiedRequirement(string $type, object $discuss_it_settings, $request): bool
    {
        $satisfied_requirement = true;

        switch ($type) {
            case('text'):
                if ($discuss_it_settings->min_number_of_words) {
                    $satisfied_requirement = $this->countWords($request->text) >= $discuss_it_settings->min_number_of_words;
                }
                break;
            case('file'):
                $satisfied_requirement = $request->file_requirement_satisfied;
                break;
            default:
                throw new Exception("Satisfied requirement does not have a valid type.");

        }
        return $satisfied_requirement;
    }


    public function updateScore($discuss_it_settings,
                                array $discussion_comment_submission_results,
                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                int $user_id,
                                Assignment $assignment,
                                Question $question,
                                Submission $submission,
                                SubmissionFile $submissionFile,
                                Score $score
    )
    {

        if (+$discuss_it_settings->auto_grade === 1
            && $discussion_comment_submission_results['satisfied_all_requirements']) {

            $assignment_question = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();

            $submission_file = $submissionFile->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->where('user_id', $user_id)
                ->first();
            if (!$submission_file) {
                $submissionFile = new  SubmissionFile();
                $submissionFile->assignment_id = $assignment->id;
                $submissionFile->question_id = $question->id;
                $submissionFile->user_id = $user_id;
                $submissionFile->type = 'discuss_it';
                $submissionFile->original_filename = '';
                $submissionFile->date_submitted = now();
                $submissionFile->submission = '';
                $submissionFile->date_graded = now();
                $submissionFile->grader_id = null;
                $submissionFile->score = $submission->applyLatePenalyToScore($assignment, $assignment_question->points);
                $submissionFile->save();
            }
            $score->updateAssignmentScore($user_id, $assignment->id, $assignment->lms_grade_passback === 'automatic');
        }
    }

    /**
     * @param Assignment $assignment
     * @param int $question_id
     * @param int $user_id
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     */
    public
    function satisfiedRequirements(Assignment             $assignment,
                                   int                    $question_id,
                                   int                    $user_id,
                                   AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $user = User::find($user_id);
        $assignment_id = $assignment->id;
        $discuss_it_settings = json_decode($assignmentSyncQuestion->discussItSettings($assignment_id, $question_id));

        $number_of_initiated_discussion_threads_that_satisfied_the_requirements = $this->numberOfInitiatedDiscussionThreadsThatSatisfiedTheRequirements($assignment_id, $question_id, $user_id);
        $number_of_replies_that_satisfied_the_requirements = $this->numberOfRepliesThatSatisfiedTheRequirements($assignment_id, $question_id, $user_id);
        $number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements = $this->numberOfInitiateOrReplyInThreadsThatSatisfiedTheRequirements($assignment_id, $question_id, $user_id);
        $number_of_comments_that_satisfied_the_requirements = $this->numberOfCommentsThatSatisfiedTheRequirements($assignment_id, $question_id, $user_id);

        $satisfied_min_number_of_initiated_discussion_threads_requirement =
            +$discuss_it_settings->min_number_of_initiated_discussion_threads === 0
            || $number_of_initiated_discussion_threads_that_satisfied_the_requirements >= $discuss_it_settings->min_number_of_initiated_discussion_threads;

        $satisfied_min_number_of_replies_requirement =
            +$discuss_it_settings->min_number_of_replies === 0
            || $number_of_replies_that_satisfied_the_requirements >= $discuss_it_settings->min_number_of_replies;

        $satisfied_min_number_of_initiate_or_reply_in_threads_requirement =
            +$discuss_it_settings->min_number_of_initiate_or_reply_in_threads === 0
            || $number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements >= $discuss_it_settings->min_number_of_initiate_or_reply_in_threads;

        $satisfied_min_number_of_comments_requirement = (!property_exists($discuss_it_settings, 'min_number_of_comments'))
            || (+$discuss_it_settings->min_number_of_comments === 0) || ($discuss_it_settings->min_number_of_comments && $number_of_comments_that_satisfied_the_requirements >= $discuss_it_settings->min_number_of_comments);

        $satisfied_requirement_by_discussion_comment_id = [];
        $satisfied_requirements = DB::table('discussion_comments')
            ->where('discussion_comments.user_id', $user_id)
            ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->select('discussion_comments.id AS discussion_comment_id', 'satisfied_requirement')
            ->get();
        foreach ($satisfied_requirements as $satisfied_requirement) {
            $satisfied_requirement_by_discussion_comment_id[] = [
                'discussion_comment_id' => $satisfied_requirement->discussion_comment_id,
                'satisfied_requirement' => (bool)$satisfied_requirement->satisfied_requirement];
        }

        $response['satisfied_min_number_of_initiated_discussion_threads_requirement'] = $satisfied_min_number_of_initiated_discussion_threads_requirement;
        $response['satisfied_min_number_of_replies_requirement'] = $satisfied_min_number_of_replies_requirement;
        $response['satisfied_min_number_of_initiate_or_reply_in_threads_requirement'] = $satisfied_min_number_of_initiate_or_reply_in_threads_requirement;
        $response['satisfied_min_number_of_comments_requirement'] = $satisfied_min_number_of_comments_requirement;


        $response['number_of_initiated_discussion_threads'] = $number_of_initiated_discussion_threads_that_satisfied_the_requirements;
        $number_of_initiated_discussion_threads_plural = $number_of_initiated_discussion_threads_that_satisfied_the_requirements !== 1 ? 's' : '';
        $response['number_of_initiated_discussion_threads_message'] = "You have initiated $number_of_initiated_discussion_threads_that_satisfied_the_requirements discussion thread$number_of_initiated_discussion_threads_plural.";

        $response['number_of_replies_that_satisfied_the_requirements'] = $number_of_replies_that_satisfied_the_requirements;
        $reply_or_ies = $number_of_replies_that_satisfied_the_requirements !== 1 ? 'replies' : 'reply';
        $response['number_of_replies_message'] = "You have submitted $number_of_replies_that_satisfied_the_requirements  $reply_or_ies to existing threads.";


        $response['number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements'] = $number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements;
        $number_of_initiate_or_reply_in_threads_plural = $number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements !== 1 ? 's' : '';
        $response['number_of_initiate_or_reply_in_threads_message'] = "You have participated in (initiate or reply) $number_of_initiate_or_reply_in_threads_that_satisfied_the_requirements thread$number_of_initiate_or_reply_in_threads_plural.";


        $response['min_number_of_initiated_discussion_threads'] = $discuss_it_settings->min_number_of_initiated_discussion_threads;
        $response['min_number_of_replies'] = $discuss_it_settings->min_number_of_replies;
        $response['min_number_of_initiate_or_reply_in_threads'] = $discuss_it_settings->min_number_of_initiate_or_reply_in_threads;

        $response['satisfied_requirement_by_discussion_comment_id'] = $satisfied_requirement_by_discussion_comment_id;


        $number_of_comments_plural = $number_of_comments_that_satisfied_the_requirements !== 1 ? 's' : '';
        $response['number_of_comments_submitted'] = $number_of_comments_that_satisfied_the_requirements;
        $response['number_of_comments_submitted_message'] = "You have submitted $number_of_comments_that_satisfied_the_requirements comment$number_of_comments_plural.";
        $response['min_number_of_comments_required'] = !property_exists($discuss_it_settings, 'min_number_of_comments') || $discuss_it_settings->min_number_of_comments;


        $submission_summary_info = DB::table('submission_files')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('user_id', $user_id)
            ->first();

        $show_scores = $discuss_it_settings && +$discuss_it_settings->auto_grade || $assignment->show_scores || $assignment->assessment_type === 'real time';
        $response['show_scores'] = $show_scores;
        if (!$submission_summary_info) {
            $submission_summary = [
                'date_submitted' => 'N/A',
                'date_graded' => 'N/A',
                'score' => 'N/A',
                'grader_id' => null,
                'file_feedback' => '',
                'text_feedback' => ''];
        } else {
            $submission_summary['date_submitted'] = $submission_summary_info->date_submitted ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($submission_summary_info->date_submitted, $user->time_zone, 'F d, Y g:i:sa') : 'N/A';
            $submission_summary['date_graded'] = $show_scores ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($submission_summary_info->date_graded, $user->time_zone, 'F d, Y g:i:sa') : 'N/A';
            $submission_summary['score'] = $show_scores ? Helper::removeZerosAfterDecimal($submission_summary_info->score) : 'N/A';
            $submission_summary['grader_id'] = $submission_summary_info->grader_id;
            $submission_summary['text_feedback'] = $submission_summary_info->text_feedback;
            $submission_summary['file_feedback'] = $submission_summary_info->file_feedback;
            $submission_summary['file_feedback_type'] = '';
            if ($submission_summary_info->file_feedback) {
                $submission_summary['file_feedback_type'] =
                    strpos($submission_summary_info->file_feedback, '.pdf') !== false
                        ? 'PDF'
                        : 'Audio';
                $submission_summary['file_feedback_url'] = Storage::disk('s3')->temporaryUrl("assignments/$submission_summary_info->assignment_id/$submission_summary_info->file_feedback", Carbon::now()->addDays(7));

            }
        }

        $response['submission_summary'] = $submission_summary;
        $response['satisfied_all_requirements'] = $satisfied_min_number_of_initiated_discussion_threads_requirement
            && $satisfied_min_number_of_replies_requirement
            && $satisfied_min_number_of_initiate_or_reply_in_threads_requirement
            && $satisfied_min_number_of_comments_requirement;
        return $response;
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $user_id
     * @return mixed
     */
    public
    function numberOfInitiatedDiscussionThreadsThatSatisfiedTheRequirements(int $assignment_id, int $question_id, int $user_id)
    {
        return $this->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussions.assignment_id', $assignment_id)
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->where('discussions.user_id', $user_id)
            ->where('discussion_comments.user_id', $user_id)
            ->where('discussion_comments.satisfied_requirement', 1)
            ->distinct('discussion_comments.discussion_id')
            ->count('discussion_comments.discussion_id');
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $user_id
     * @return mixed
     */
    public
    function numberOfRepliesThatSatisfiedTheRequirements(int $assignment_id, int $question_id, int $user_id)
    {
        return $this->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->where('discussion_comments.user_id', $user_id)
            ->where('discussion_comments.satisfied_requirement', 1)
            ->whereNotIn('discussion_comments.id', function ($query) {
                $query->selectRaw('MIN(discussion_comments.id)')
                    ->from('discussion_comments')
                    ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
                    ->whereColumn('discussions.user_id', 'discussion_comments.user_id')
                    ->groupBy('discussion_comments.discussion_id');
            })
            ->count();
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param int $user_id
     * @return mixed
     */
    public
    function numberOfCommentsThatSatisfiedTheRequirements(int $assignment_id, int $question_id, int $user_id)
    {
        return $this->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->where('discussion_comments.user_id', $user_id)
            ->where('discussion_comments.satisfied_requirement', 1)
            ->count();
    }

    /**
     * Counts the number of DISTINCT discussion threads (initiated or replied
     * to) in which this student has at least one comment that satisfied the
     * requirements. This must count distinct threads, not total comments --
     * a student who posts multiple satisfying comments in the same thread has
     * only participated in that one thread.
     *
     * @param $assignment_id
     * @param $question_id
     * @param $user_id
     * @return mixed
     */
    public function numberOfInitiateOrReplyInThreadsThatSatisfiedTheRequirements($assignment_id, $question_id, $user_id)
    {
        return $this->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
            ->where('discussion_comments.posted_in_assignment_id', $assignment_id)
            ->where('discussions.question_id', $question_id)
            ->where('discussion_comments.user_id', $user_id)
            ->where('discussion_comments.satisfied_requirement', 1)
            ->distinct('discussion_comments.discussion_id')
            ->count('discussion_comments.discussion_id');

    }

    public
    function getDir(): string
    {
        return ('uploads/discuss-it/discussion-comments');
    }

    /**
     * @param $htmlString
     * @return int
     */
    function countWords($htmlString): int
    {
        // Load the HTML string into a DOMDocument
        $htmlString = str_replace("\xc2\xa0", ' ', $htmlString);
        $htmlString = str_replace('&nbsp;', ' ', $htmlString);
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Count MathJax formulas and images as one word each
        $mathjaxCount = $dom->getElementsByTagName('span')->length;
        $imageCount = $dom->getElementsByTagName('img')->length;

        // Remove MathJax formulas and images from the content
        while ($dom->getElementsByTagName('span')->length > 0) {
            $node = $dom->getElementsByTagName('span')->item(0);
            $node->parentNode->removeChild($node);
        }
        while ($dom->getElementsByTagName('img')->length > 0) {
            $node = $dom->getElementsByTagName('img')->item(0);
            $node->parentNode->removeChild($node);
        }

        // Get the remaining text content
        $textContent = $dom->textContent;

        // Split the text into words
        $words = preg_split('/\s+/', trim($textContent), -1, PREG_SPLIT_NO_EMPTY);

        // Total word count
        return count($words) + $mathjaxCount + $imageCount;
    }
}
