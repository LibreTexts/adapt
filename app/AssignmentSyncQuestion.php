<?php

namespace App;

use App\Custom\FCMNotification;
use App\Helpers\Helper;
use App\Traits\DateFormatter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;


class AssignmentSyncQuestion extends Model
{
    use DateFormatter;

    protected $table = 'assignment_question';
    protected $guarded = [];

    /**
     * @throws Exception
     */
    public function draftAssignTo(array $assign_to, string $timezone): array
    {
        $clean_assign_to = [
            'groups' => $assign_to['groups'] ?? []
        ];
        if (strtotime($assign_to['available_from_date'] ?? '') !== false && strtotime($assign_to['available_from_time'] ?? '') !== false) {
            $local_datetime = $assign_to['available_from_date'] . ' ' . $assign_to['available_from_time'];
            $clean_assign_to['available_from'] = $this->convertLocalMysqlFormattedDateToUTC($local_datetime, $timezone);
        }

// Convert due to UTC timestamp
        if (strtotime($assign_to['due_date'] ?? '') !== false && strtotime($assign_to['due_time'] ?? '') !== false) {
            $local_datetime = $assign_to['due_date'] . ' ' . $assign_to['due_time'];
            $clean_assign_to['due'] = $this->convertLocalMysqlFormattedDateToUTC($local_datetime, $timezone);
        }

        if (strtotime($assign_to['final_submission_deadline_date'] ?? '') !== false && strtotime($assign_to['final_submission_deadline_time'] ?? '') !== false) {
            $local_datetime = $assign_to['final_submission_deadline_date'] . ' ' . $assign_to['final_submission_deadline_time'];
            $clean_assign_to['final_submission_deadline'] = $this->convertLocalMysqlFormattedDateToUTC($local_datetime, $timezone);
        }

        return $clean_assign_to;
    }

    /**
     * @throws Exception
     */
    public
    function reOrderAndWeightQuestions(Assignment $assignment): array
    {
        $response = [];
        $currently_ordered_questions = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->orderBy('order')
            ->get();

        if ($currently_ordered_questions) {
            $currently_ordered_question_ids = [];
            foreach ($currently_ordered_questions as $currently_ordered_question) {
                $currently_ordered_question_ids[] = $currently_ordered_question->question_id;
            }
            $this->orderQuestions($currently_ordered_question_ids, $assignment);

            $this->updatePointsBasedOnWeights($assignment);
            if ($assignment->points_per_question === 'question weight') {
                $response['updated_points'] = $this->getQuestionPointsByAssignment($assignment);
            }
        }
        return $response;
    }


    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     */
    public
    function removeRandomizedAssessment(Assignment $assignment, Question $question)
    {
        $response['message'] = '';
        $response['return_response'] = false;
        if ($assignment->number_of_randomized_assessments) {
            $assignment_questions = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->get();
            if ($assignment_questions->count() === $assignment->number_of_randomized_assessments + 1) {
                $response['message'] = "You can't remove a question because there wouldn't be enough questions left to randomize from.";
                $response['return_response'] = true;
                return $response;
            }
            $question_ids = $assignment->questions->pluck('id')->toArray();
            $randomized_assignment_questions = RandomizedAssignmentQuestion::where('assignment_id', $assignment->id)->get();
            $randomized_assignment_questions_by_user_id = [];
            foreach ($randomized_assignment_questions as $randomized_assignment_question) {
                if (!isset($randomized_assignment_questions_by_user_id[$randomized_assignment_question->user_id])) {
                    $randomized_assignment_questions_by_user_id[$randomized_assignment_question->user_id] = [];
                }
                $randomized_assignment_questions_by_user_id[$randomized_assignment_question->user_id][] = $randomized_assignment_question->question_id;
            }

            foreach ($randomized_assignment_questions_by_user_id as $user_id => $user_question_ids) {
                if (in_array($question->id, $user_question_ids)) {
                    $other_question_id = $this->getOtherRandomizedQuestionId($user_question_ids, $question_ids, $question->id);
                    if (!$other_question_id) {
                        $response['message'] = "We were unable to remove the question due to an issue with re-configuring the randomizations.  Please contact support.";
                        $response['return_response'] = true;
                        return $response;
                    }
                    $randomizedAssignmentQuestion = new RandomizedAssignmentQuestion();
                    $randomizedAssignmentQuestion->assignment_id = $assignment->id;
                    $randomizedAssignmentQuestion->question_id = $other_question_id;
                    $randomizedAssignmentQuestion->user_id = $user_id;
                    $randomizedAssignmentQuestion->save();
                }
                $response['message'] = "As this is a randomized assignment, please ask your students to revisit their assignment as a question may have been updated.";
                return $response;
            }
        }
        return $response;
    }

    /**
     * @param array $user_question_ids
     * @param array $question_ids
     * @param int $question_id_to_remove
     * @return false|mixed
     */
    function getOtherRandomizedQuestionId(array $user_question_ids, array $question_ids, int $question_id_to_remove)
    {
        foreach ($question_ids as $question_id) {
            if (($question_id !== $question_id_to_remove) && !in_array($question_id, $user_question_ids)) {
                return $question_id;
            }
        }
        return false;

    }

    /**
     * @param FCMNotification $FCMNotification
     * @param Assignment $assignment
     * @param Question $question
     * @param $time_to_submit
     * @param int $seconds_padding
     * @param bool $reload_student_view
     * @return void
     * @throws Exception
     */
    public
    function startClickerAssessment(FCMNotification $FCMNotification,
                                    Assignment      $assignment,
                                    Question        $question,
                                                    $time_to_submit,
                                    int             $seconds_padding,
                                    bool            $reload_student_view = false)

    {

        $clicker_start = CarbonImmutable::now();
        $clicker_end = $clicker_start->add($time_to_submit)->addSeconds($seconds_padding);
        DB::beginTransaction();
        DB::table('assignment_question')->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->update([
                'clicker_start' => $clicker_start,
                'clicker_end' => $clicker_end
            ]);
        DB::commit();
        $time_left = $clicker_end->subSeconds($seconds_padding)->diffInMilliseconds($clicker_start);

        if (app()->environment() !== 'testing') {
            $client = Helper::centrifuge();
            $client->publish("set-current-page-$assignment->id", [
                "assignment_id" => $assignment->id,
                "question_id" => $question->id]);
            $client->publish("clicker-status-$assignment->id",
                ["assignment_id" => $assignment->id,
                    "question_id" => $question->id,
                    "status" => 'view_and_submit',
                    'reload_student_view' => $reload_student_view,
                    "time_left" => $time_left]);

        }
        $question_title = $question->title;
        if (!$question_title) {
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();

            $question_title = $assignment_question->custom_question_title ? $assignment_question->custom_question_title : "Question #$assignment_question->order";
        }
        $timezone = new DateTimeZone(auth()->user()->time_zone);
        $date = new DateTime('now', $timezone);
        $formatted_time = $date->format('g:i A T');
        $message['message'] = [
            'notification' => [
                'title' => 'Clicker Launch',
                'body' =>
                    "ADAPT poll, '{$question_title}' in {$assignment->course->name}, opening at $formatted_time"],
            'data' => [
                'path' => "Assignment/$assignment->id/Question/$question->id",
                'assignment_id' => (string)$assignment->id,
                'question_id' => (string)$question->id,
            ]
        ];

        $FCMNotification->sendNotificationsByAssignment($assignment, $message);
    }

    /**
     * @param int $assignment_id
     * @param $question_id
     * @return mixed|null
     */
    public
    function customRubric(int $assignment_id, $question_id)
    {
        $assignment_question_info = DB::table('assignment_question')
            ->where('assignment_question.assignment_id', $assignment_id)
            ->where('assignment_question.question_id', $question_id)
            ->first();
        return $assignment_question_info ? $assignment_question_info->custom_rubric : null;
    }

    /**
     * @param $assignment
     * @param $Submission
     * @param $submission
     * @param $question
     * @return bool
     */
    function showRealTimeSolution($assignment, $Submission, $submission, $question): bool
    {
        if (!$submission) {
            return false;
        }
        $real_time_show_solution = false;
        if ($assignment->assessment_type === 'real time'
            && $assignment->scoring_type === 'p'
            && $assignment->solutions_availability === 'automatic') {
            //can view if either they got it right OR they ask to view it (unlimited) OR they
            $attempt = json_decode($submission->submission);
            $proportion_correct = $Submission->getProportionCorrect($question->technology, $attempt);
            $answered_correctly = $question->technology !== 'text' && (abs($proportion_correct - 1) < PHP_FLOAT_EPSILON);
            if (!$answered_correctly) {
                $real_time_show_solution = $assignment->number_of_allowed_attempts === 'unlimited'
                    ? $submission->show_solution
                    : $submission->submission_count >= $assignment->number_of_allowed_attempts;
            } else {
                $real_time_show_solution = true;
            }
        }
        return $real_time_show_solution;
    }


    /**
     * @param Question $question
     * @return array
     */
    public
    function getAssignmentIdsWithTheQuestion(Question $question): array
    {
        return DB::table('assignment_question')
            ->where('question_id', $question->id)
            ->select('assignment_id')
            ->pluck('assignment_id')
            ->toArray();
    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @param string $setting
     * @return mixed
     */
    public
    function discussItSetting(int $assignment_id, int $question_id, string $setting)
    {
        $assignment_question = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->first();
        return json_decode($assignment_question->discuss_it_settings)->{$setting};

    }

    public
    function discussItCompletionStatus(int $user_id, int $assignment_id, int $question_id)
    {
        $discuss_it_settings = json_decode($this->discussItSettings($assignment_id, $question_id));
        $completion_items = ['min_length_of_audio_video',
            'min_number_of_discussion_threads',
            'min_number_of_comments',
            'min_number_of_words'];

        foreach ($completion_items as $key => $completion_item) {
            if (!$discuss_it_settings->{$completion_item}) {
                unset($completion_items[$key]);
            }
        }
        $completion_items = array_values($completion_items);
        $discussion_comments_by_user = DB::table('discussion_comments')->
        join('discussions', 'discussion_comments.dicussion_id', '=', 'discussions.id')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->where('discussion_comments.user_id', $user_id)
            ->select('discussion_comments.*')
            ->get();
        $total_amount_of_time = 0;
        $total_number_of_discussion_threads = 0;
        $total_number_of_comments = 0;
        $total_number_of_words = 0;
        $completion_status = [];
        foreach ($completion_items as $completion_item) {
            $completion_status[] = ['key' => $completion_item, 'completed' => false];
        }
        if ($discussion_comments_by_user) {
            foreach ($discussion_comments_by_user as $discussion_comment) {
                foreach ($completion_items as $completion_item) {
                    switch ($completion_item) {
                        case('min_length_of_audio_video'):
                            break;
                        case('min_number_of_discussion_threads'):

                            break;
                        case('min_number_of_comments');
                            break;
                        case('min_number_of_words'):

                            break;
                        default:
                            throw new Exception ("No logic yet for  $completion_item.");

                    }

                }
            }

        }

    }

    /**
     * @param int $assignment_id
     * @param int $question_id
     * @return false|string
     */
    public
    function discussItSettings(int $assignment_id, int $question_id)
    {
        $assignment_question_info = DB::table('assignment_question')
            ->where('assignment_id', $assignment_id)
            ->where('question_id', $question_id)
            ->select('discuss_it_settings')
            ->first();

        if ($assignment_question_info->discuss_it_settings) {
            $discuss_it_settings = json_decode($assignment_question_info->discuss_it_settings);
            if ($discuss_it_settings->min_length_of_audio_video) {
                $base = Carbon::now();
                $min_length_of_audio_video = $discuss_it_settings->min_length_of_audio_video;
                $min_length_of_audio_video = str_replace('and', ',', $min_length_of_audio_video);
                $parsedTime = Carbon::parse($min_length_of_audio_video);
                $discuss_it_settings->min_length_of_audio_video_in_milliseconds = $base->diffInMilliseconds($parsedTime);
            }
        } else {
            $discuss_it_settings = Helper::defaultDiscussItSettings();
            AssignmentSyncQuestion::where('assignment_id', $assignment_id)
                ->where('question_id', $question_id)
                ->update(['discuss_it_settings' => Helper::defaultDiscussItSettings()]);
            $discuss_it_settings = json_decode($discuss_it_settings);
        }
        return json_encode($discuss_it_settings);

    }


    public
    function rubricCategoriesByAssignmentAndQuestion(Assignment $assignment, Question $question)
    {
        $question_revision_id = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first()
            ->question_revision_id;
        $question_revision_id = $question_revision_id ?: 0;
        $rubric_categories = $question->rubricCategories(new RubricCategory(), $question_revision_id);
        $rubric_category_custom_criteria_by_id = [];

        $rubric_category_custom_criteria = DB::table('rubric_category_custom_criteria')
            ->where('assignment_id', $assignment->id)
            ->get();
        foreach ($rubric_category_custom_criteria as $value) {
            $rubric_category_custom_criteria_by_id[$value->rubric_category_id] = $value->custom_criteria;
        }

        foreach ($rubric_categories as $rubricCategory) {
            if (isset($rubric_category_custom_criteria_by_id[$rubricCategory->id])) {
                $rubricCategory['criteria'] = $rubric_category_custom_criteria_by_id[$rubricCategory->id];
            }
        }
        return $rubric_categories;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param BetaCourseApproval $betaCourseApproval
     * @return int
     * @throws Exception
     */
    public
    function store(Assignment         $assignment,
                   Question           $question,
                   BetaCourseApproval $betaCourseApproval): int
    {


        $points = $assignment->points_per_question === 'number of points'
            ? $assignment->default_points_per_question
            : 0;
        $open_ended_submission_type = $assignment->default_open_ended_submission_type;
        if ($question->open_ended_submission_type) {
            $open_ended_submission_type = $question->open_ended_submission_type;
        }
        $open_ended_text_editor = $assignment->default_open_ended_text_editor;
        if ($assignment->isBetaAssignment()) {
            $alpha_assignment_id = BetaAssignment::find($assignment->id)->alpha_assignment_id;
            $alpha_assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $alpha_assignment_id)
                ->where('question_id', $question->id)
                ->first();
            $points = $alpha_assignment_question->points;
            $open_ended_submission_type = $alpha_assignment_question->open_ended_submission_type;
            $open_ended_text_editor = $alpha_assignment_question->open_ended_text_editor;
        }
        $assignment_question_data = [
            'assignment_id' => $assignment->id,
            'question_id' => $question->id,
            'question_revision_id' => $question->latestQuestionRevision('id'),
            'order' => $this->getNewQuestionOrder($assignment),
            'points' => $points, //don't need to test since tested already when creating an assignment
            'weight' => $assignment->points_per_question === 'number of points' ? null : 1,
            'completion_scoring_mode' => $assignment->scoring_type === 'c' ? $assignment->default_completion_scoring_mode : null,
            'open_ended_submission_type' => $question->isDiscussIt() ? 0 : $open_ended_submission_type,
            'discuss_it_settings' => $question->isDiscussIt() ? Helper::defaultDiscussItSettings() : null,
            'open_ended_text_editor' => $open_ended_text_editor];

        $assignment_question_id = DB::table('assignment_question')
            ->insertGetId($assignment_question_data);
        $this->updatePointsBasedOnWeights($assignment);
        $this->addLearningTreeIfBetaAssignment($assignment_question_id, $assignment->id, $question->id);
        $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question->id, 'add');
        if ($question->isDiscussIt() && $assignment->isBetaAssignment()) {
            //Beta courses mirror the Alpha course's linked (daisy-chained) Discuss-it questions
            DiscussItChain::syncBetaAssignmentQuestion($assignment, $question->id);
        }

        return $assignment_question_id;

    }

    public
    function switchPointsPerQuestion(Assignment $assignment, $total_points)
    {
        switch ($assignment->points_per_question) {
            case('question weight'):
                //switch to points
                DB::table('assignment_question')
                    ->where('assignment_id', $assignment->id)
                    ->update(['weight' => null]);
                break;
            case('number of points'):
                //switch to weights
                $assignment_questions = DB::table('assignment_question')
                    ->where('assignment_id', $assignment->id)
                    ->select('id')
                    ->get();
                if (count($assignment_questions)) {
                    DB::table('assignment_question')
                        ->where('assignment_id', $assignment->id)
                        ->update(['weight' => 1, 'points' => $total_points / count($assignment_questions)]);
                }
                break;
        }


    }

    /**
     * @param Assignment $assignment
     * @return array
     */
    public
    function getQuestionPointsByAssignment(Assignment $assignment): array
    {

        $updated_points_info = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->select('question_id', 'points')
            ->get();

        $formatted_updated_points = [];
        foreach ($updated_points_info as $key => $updated_point) {
            $formatted_updated_points[$key]['question_id'] = $updated_point->question_id;
            $formatted_updated_points[$key]['points'] = Helper::removeZerosAfterDecimal($updated_point->points);
        }
        return $formatted_updated_points;

    }

    /**
     * @param Assignment $assignment
     * @param int $question_id
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param $open_ended_submission_type
     * @param $open_ended_text_editor
     * @param BetaCourseApproval $betaCourseApproval
     * @param $custom_rubric
     * @return void
     * @throws Exception
     */
    public
    function addQuestionToAssignmentByQuestionId(Assignment             $assignment,
                                                 int                    $question_id,
                                                 AssignmentSyncQuestion $assignmentSyncQuestion,
                                                                        $open_ended_submission_type,
                                                                        $open_ended_text_editor,
                                                 BetaCourseApproval     $betaCourseApproval,
                                                                        $custom_rubric)
    {

        switch ($assignment->points_per_question) {
            case('number of points'):
                $points = $assignment->default_points_per_question;
                $weight = null;
                break;
            case('question weight'):
                $points = 0;//will be updated below
                $weight = 1;
                break;
            default:
                throw new exception ("Invalid points_per_question");
        }

        $question_revision_id = Question::find($question_id)->latestQuestionRevision('id');
        $question = Question::find($question_id);
        if ($open_ended_submission_type === 'rich text') {
            $open_ended_submission_type = 'text';
        }
        DB::table('assignment_question')
            ->insertGetId([
                'assignment_id' => $assignment->id,
                'question_id' => $question_id,
                'order' => $assignmentSyncQuestion->getNewQuestionOrder($assignment),
                'points' => $points,
                'weight' => $weight,
                'question_revision_id' => $question_revision_id,
                'custom_rubric' => $custom_rubric,
                'open_ended_submission_type' => $question->isDiscussIt() ? 0 : $open_ended_submission_type,
                'open_ended_text_editor' => $open_ended_submission_type === 'text' ? 'rich text' : null,
                'discuss_it_settings' => Helper::defaultDiscussItSettings(),
                'completion_scoring_mode' => $assignment->scoring_type === 'c' ? $assignment->default_completion_scoring_mode : null,
                'open_ended_text_editor' => $open_ended_text_editor]);
        $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment);
        $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question_id, 'add');
    }

    /**
     * @param $assignment
     * @return void
     * @throws Exception
     */
    public
    function updatePointsBasedOnWeights($assignment)
    {
        $assignment_questions = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->get();
        $total_points = 0;
        if ($assignment->number_of_randomized_assessments) {
            $total_points = $assignment->default_points_per_question * $assignment->number_of_randomized_assessments;
        } else {
            if ($assignment->points_per_question === 'question weight') {

                $weights_total = DB::table('assignment_question')
                    ->where('assignment_id', $assignment->id)
                    ->sum('weight');

                foreach ($assignment_questions as $assignment_question) {
                    $points = ($assignment_question->weight / $weights_total) * $assignment->total_points;
                    $total_points += $points;
                    DB::table('assignment_question')
                        ->where('id', $assignment_question->id)
                        ->update(['points' => $points]);
                }
            } else {
                foreach ($assignment_questions as $assignment_question) {
                    $total_points += $assignment_question->points;
                }
            }
        }
        if ($assignment->course->lms_course_id && !$assignment->formative) {
            $lmsApi = new LmsAPI();
            $lms_result = $lmsApi->updateAssignment(
                $assignment->course->getLtiRegistration(),
                $assignment->course->user_id,
                $assignment->course->lms_course_id,
                $assignment->lms_assignment_id,
                ['total_points' => $total_points]);
            if ($lms_result['type'] === 'error') {
                throw new Exception("Error updating assignment $assignment->id on  LMS: " . $lms_result['message']);
            }
        }
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    public
    function questionExistsInOtherAssignments(Assignment $assignment, Question $question)
    {
        return DB::table('assignment_question')
            ->where('assignment_id', '<>', $assignment->id)
            ->where('question_id', $question->id)
            ->get()
            ->isNotEmpty();
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    public
    function questionHasAutoGradedOrFileSubmissionsInOtherAssignments(Assignment $assignment, Question $question): bool
    {
        $auto_graded_submissions = DB::table('submissions')
            ->join('users', 'submissions.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', '<>', $assignment->id)
            ->where('question_id', $question->id)
            ->first();
        $submission_files = DB::table('submission_files')
            ->join('users', 'submission_files.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', '<>', $assignment->id)
            ->where('question_id', $question->id)
            ->first();
        return $auto_graded_submissions || $submission_files;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return bool
     */
    public
    function questionHasSomeTypeOfRealStudentSubmission(Assignment $assignment, Question $question): bool
    {
        $auto_graded_submissions = DB::table('submissions')
            ->join('users', 'submissions.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first();
        $submission_files = DB::table('submission_files')
            ->join('users', 'submission_files.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first();
        $rubric_category_submissions = DB::table('rubric_category_submissions')
            ->join('rubric_categories', 'rubric_category_submissions.rubric_category_id', '=', 'rubric_categories.id')
            ->join('users', 'rubric_category_submissions.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->first();

        return $rubric_category_submissions || $auto_graded_submissions || $submission_files;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @return array
     */
    public
    function studentEmailsAssociatedWithSomeTypeOfStudentSubmission(Assignment $assignment, Question $question): array
    {
        $auto_graded_submission_emails = DB::table('submissions')
            ->join('users', 'submissions.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->select('email')
            ->get()
            ->pluck('email')
            ->toArray();
        $submission_file_emails = DB::table('submission_files')
            ->join('users', 'submission_files.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->select('email')
            ->get()
            ->pluck('email')
            ->toArray();
        $rubric_category_submissions = DB::table('rubric_category_submissions')
            ->join('rubric_categories', 'rubric_category_submissions.rubric_category_id', '=', 'rubric_categories.id')
            ->join('users', 'rubric_category_submissions.user_id', '=', 'users.id')
            ->where('fake_student', 0)
            ->where('assignment_id', $assignment->id)
            ->where('question_id', $question->id)
            ->select('email')
            ->get()
            ->pluck('email')
            ->toArray();
        $combined = array_merge($rubric_category_submissions, $auto_graded_submission_emails, $submission_file_emails);
        return array_unique($combined);
    }


    public
    function addLearningTreeIfBetaAssignment(int $assignment_question_id,
                                             int $assignment_id,
                                             int $question_id)
    {
        $beta_learning_tree = DB::table('beta_course_approvals')
            ->where('beta_assignment_id', $assignment_id)
            ->where('beta_question_id', $question_id)
            ->where('beta_learning_tree_id', '<>', 0)
            ->first();
        if ($beta_learning_tree) {
            DB::table('assignment_question_learning_tree')
                ->insert([
                    'assignment_question_id' => $assignment_question_id,
                    'learning_tree_id' => $beta_learning_tree->id,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
        }

    }

    public
    function completedAllAssignmentQuestions($assignment)
    {
        $num_technology_questions = $assignment->number_of_randomized_assignments
            ?: DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                ->where('technology', '<>', 'text')
                ->count();
        $num_non_technology_questions = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->where('open_ended_submission_type', '<>', '0')
            ->get()
            ->count();
        if ($num_technology_questions + $num_non_technology_questions === 0) {
            return false;
        }
        $num_submitted_technology_questions = DB::table('submissions')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', Auth::user()->id)
            ->get()
            ->count();
        if ($num_technology_questions !== $num_submitted_technology_questions) {
            return false;
        }
        $num_submitted_non_technology_questions = DB::table('submission_files')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', Auth::user()->id)
            ->where('type', '<>', 'a')
            ->get()
            ->count();
        return $num_submitted_non_technology_questions === $num_non_technology_questions;

    }

    /**
     * @param int $from_assignment_id
     * @param int $to_assignment_id
     * @param bool $reset_discuss_it_settings_to_default
     * @param bool $reset_clicker_settings_to_default
     * @param bool $remove_open_ended_questions_in_real_time_assignment
     * @return void
     */
    public
    function importAssignmentQuestionsAndLearningTrees(int  $from_assignment_id,
                                                       int  $to_assignment_id,
                                                       bool $reset_discuss_it_settings_to_default,
                                                       bool $reset_clicker_settings_to_default,
                                                       bool $remove_open_ended_questions_in_real_time_assignment)
    {
        $assignment_questions = DB::table('assignment_question')
            ->where('assignment_id', $from_assignment_id)
            ->get();
        $from_assignment = Assignment::find($from_assignment_id);
        if ($from_assignment->assessment_type === 'real time' && $remove_open_ended_questions_in_real_time_assignment) {
            foreach ($assignment_questions as $key => $assignment_question) {
                if ($assignment_question->open_ended_submission_type !== '0') {
                    $assignment_questions->forget($key);
                }
            }
        }
        $question_ids = [];
        foreach ($assignment_questions as $assignment_question) {
            $question_ids[] = $assignment_question->question_id;
        }
        $draft_question_ids = Question::whereIn('id', $question_ids)->whereNotNull('forge_source_id')->pluck('id')->toArray();

        $question_revision_ids_by_question_ids = $this->getLatestQuestionRevisionsByAssignment($question_ids);
        foreach ($assignment_questions as $assignment_question) {
            if (in_array($assignment_question->question_id, $draft_question_ids)) {
                continue;
            }
            $assignment_question->assignment_id = $to_assignment_id;
            $assignment_question->question_revision_id = $question_revision_ids_by_question_ids[$assignment_question->question_id] ?? null;
            $assignment_question->forge_settings = null;
            //links (daisy chains) are rebuilt by the caller when a whole course is copied
            $assignment_question->discuss_it_chain_id = null;
            $assignment_question->clicker_start = null;
            $assignment_question->clicker_end = null;
            if ($assignment_question->discuss_it_settings) {
                if ($reset_discuss_it_settings_to_default) {
                    $assignment_question->discuss_it_settings = Helper::defaultDiscussItSettings();
                } else {
                    $assignment_question->discuss_it_settings = Helper::makeDiscussItSettingsBackwardsCompatible($assignment_question->discuss_it_settings);
                }
            }
            $assignment_question->clicker_start = null;
            $assignment_question->clicker_end = null;
            if ($reset_clicker_settings_to_default) {
                $assignment_question->custom_clicker_time_to_submit = null;
                $assignment_question->release_solution_when_question_is_closed = 1;
            }

            //add each question
            $assignment_question_array = json_decode(json_encode($assignment_question), true);
            unset($assignment_question_array['id']);
            $new_assignment_question_id = DB::table('assignment_question')->insertGetId($assignment_question_array);
            //add the learning tree associated with the question
            $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
                ->where('assignment_question_id', $assignment_question->id)
                ->first();
            if ($assignment_question_learning_tree) {
                $new_data['assignment_question_id'] = $new_assignment_question_id;
                $new_data['created_at'] = $new_data['updated_at'] = Carbon::now();
                $fields = ['learning_tree_id',
                    'number_of_successful_paths_for_a_reset'
                ];
                foreach ($fields as $field) {
                    $new_data[$field] = $assignment_question_learning_tree->{$field};
                }
                DB::table('assignment_question_learning_tree')->insert($new_data);
            }
        }
    }

    public
    function getNewQuestionOrder(Assignment $assignment)
    {
        $max_order = DB::table('assignment_question')
            ->where('assignment_id', $assignment->id)
            ->max('order');
        return $max_order ? $max_order + 1 : 1;
    }

    /**
     * @param Collection $assignments
     * @return array
     */
    public
    function getQuestionCountByAssignmentIds(Collection $assignments): array
    {
        $questions_count_by_assignment_id = [];
        $non_randomized_assignment_ids = [];
        foreach ($assignments as $assignment) {
            if ($assignment->number_of_randomized_assessments) {
                $questions_count_by_assignment_id[$assignment->id] = $assignment->number_of_randomized_assessments;
            } else {
                $non_randomized_assignment_ids[] = $assignment->id;
            }
        }

        $questions_count = DB::table('assignment_question')
            ->whereIn('assignment_id', $non_randomized_assignment_ids)
            ->groupBy('assignment_id')
            ->select(DB::raw('count(*) as num_questions'), 'assignment_id')
            ->get();

        //reogranize by assignment id
        foreach ($questions_count as $key => $value) {
            $questions_count_by_assignment_id[$value->assignment_id] = $value->num_questions;
        }
        return $questions_count_by_assignment_id;
    }

    /**
     * @param int $solutions_released
     * @param $question_info
     * @return string
     */
    public
    function getFormattedClickerStatus(int $solutions_released, $question_info): string
    {
        $formatted_clicker_status = 'Error with formatted clicker status logic';
        if (auth()->user()->role === 2) {
            if (!$question_info->clicker_start && !$question_info->clicker_end) {
                $formatted_clicker_status = 'show_open';
            } else if (time() >= strtotime($question_info->clicker_start) && time() <= strtotime($question_info->clicker_end)) {
                $formatted_clicker_status = 'view_and_submit';
            } else if (time() > strtotime($question_info->clicker_end)) {
                $formatted_clicker_status = 'view_and_not_submit';
            }
        } else {
            if ($solutions_released) {
                $formatted_clicker_status = 'view_and_not_submit';
            } else {
                if (!$question_info->clicker_start && !$question_info->clicker_end) {
                    $formatted_clicker_status = 'neither_view_nor_submit';
                } else if (time() >= strtotime($question_info->clicker_start) && time() <= strtotime($question_info->clicker_end)) {
                    $formatted_clicker_status = 'view_and_submit';
                } else if (time() > strtotime($question_info->clicker_end)) {
                    $formatted_clicker_status = 'view_and_not_submit';
                }
            }
        }
        return $formatted_clicker_status;
    }

    public
    function orderQuestions(array $ordered_questions, Assignment $assignment)
    {
        foreach ($ordered_questions as $key => $question_id) {
            DB::table('assignment_question')->where('assignment_id', $assignment->id)
                ->where('question_id', $question_id)
                ->update(['order' => $key + 1]);
        }

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     */
    public
    function updateAssignmentScoreBasedOnRemovedQuestion(Assignment $assignment,
                                                         Question   $question)
    {

        $scores = DB::table('scores')->where('assignment_id', $assignment->id)
            ->select('user_id', 'score')
            ->get();

        $lti_launches_by_user_id = $assignment->ltiLaunchesByUserId();
        $ltiGradePassBack = new LtiGradePassback();

        //just remove the one...
        $submissions = DB::table('submissions')->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->select('user_id', 'score')
            ->get();
        $submissions_by_user_id = [];
        foreach ($submissions as $submission) {
            $submissions_by_user_id[$submission->user_id] = $submission->score;
        }

        $submission_files = DB::table('submission_files')->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->where('score', '<>', null)
            ->select('user_id', 'score')
            ->get();
        $submission_files_by_user_id = [];
        foreach ($submission_files as $submission_file) {
            $submission_files_by_user_id[$submission_file->user_id] = $submission_file->score;
        }

        $rubric_category_submissions = DB::table('rubric_category_submissions')
            ->join('rubric_categories', 'rubric_category_submissions.rubric_category_id', '=', 'rubric_categories.id')
            ->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->where('custom_score', '<>', null)
            ->select('user_id', 'custom_score')
            ->get();
        $rubric_category_submissions_by_user_id = [];
        foreach ($rubric_category_submissions as $rubric_category_submission) {
            $rubric_category_submissions_by_user_id[$rubric_category_submission->user_id] = $rubric_category_submission->custom_score;
        }

        foreach ($scores as $score) {
            $submission_file_score = $submission_files_by_user_id[$score->user_id] ?? 0;
            $submission_score = $submissions_by_user_id[$score->user_id] ?? 0;
            $rubric_category_submission_score = $rubric_category_submissions_by_user_id[$score->user_id] ?? 0;

            $new_score = $score->score - $submission_file_score - $submission_score - $rubric_category_submission_score;
            DB::table('scores')->where('assignment_id', $assignment->id)
                ->where('user_id', $score->user_id)
                ->update(['score' => $new_score]);
            if (isset($lti_launches_by_user_id[$score->user_id])) {
                DB::table('lti_grade_passbacks')
                    ->where('user_id', $score->user_id)
                    ->where('assignment_id', $assignment->id)
                    ->update(['score' => $new_score, 'updated_at' => now()]);
            }
        }
        DB::table('passback_by_assignments')->insert(
            ['assignment_id' => $assignment->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()]);

    }

    /**
     * @param array $question_ids
     * @return array
     */
    public
    function getLatestQuestionRevisionsByAssignment(array $question_ids): array
    {

        $latest_question_revisions = DB::table('question_revisions')
            ->whereIn('question_id', $question_ids)
            ->orderBy('revision_number', 'desc')
            ->select('id', 'question_id')
            ->get();
        $latest_question_revisions_by_id = [];
        foreach ($latest_question_revisions as $latest_question_revision) {
            if (!isset($latest_question_revisions_by_id[$latest_question_revision->question_id])) {
                $latest_question_revisions_by_id[$latest_question_revision->question_id] = $latest_question_revision->id;
            }
        }
        return $latest_question_revisions_by_id;


    }

    /**
     * @param Assignment $assignment
     * @return Collection
     */
    public function getAssignmentQuestionsConsideringRevisions(Assignment $assignment): Collection
    {

        $assignment_questions = DB::table('assignment_question')
            ->where('assignment_question.assignment_id', $assignment->id)
            ->get();
        $question_ids = [];
        foreach ($assignment_questions as $assignment_question) {
            $question_ids[] = $assignment_question->question_id;
        }
        $question_revisions = DB::table('assignment_question')
            ->join('question_revisions', 'assignment_question.question_revision_id', '=', 'question_revisions.id')
            ->select(
                'question_revisions.*',
                'question_revisions.id as question_revision_id',
                'question_revisions.question_id as id',
                'assignment_question.open_ended_submission_type as open_ended_submission_type'
            )
            ->where('assignment_question.assignment_id', $assignment->id)
            ->get();
        $question_revision_question_ids = [];
        foreach ($question_revisions as $question_revision) {
            $question_revision_question_ids[] = $question_revision->question_id;
        }
        $questions = Question::whereIn('id', $question_ids)
            ->whereNotIn('id', $question_revision_question_ids)
            ->get();
        return $question_revisions->concat($questions)->values();
    }

    /**
     * @param Assignment $assignment
     * @param $assignment_questions
     * @return bool
     */
    public function openEndedQuestionsInRealTimeAssignmentExists(Assignment $assignment, $assignment_questions): bool
    {

        if ($assignment->assessment_type !== 'real time') {
            return false;
        }
        foreach ($assignment_questions as $assignment_question) {
            if ($assignment_question->open_ended_submission_type !== '0') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $assignment_questions
     * @return bool
     */
    public function discussItQuestionsExist($assignment_questions): bool
    {
        foreach ($assignment_questions as $assignment_question) {
            if ($assignment_question->qti_json && json_decode($assignment_question->qti_json)->questionType === 'discuss_it') {
                return true;
            }

        }
        return false;
    }

}
