<?php

namespace App\Http\Controllers;

use App\BetaCourseApproval;
use App\Custom\FCMNotification;
use App\DiscussionComment;
use App\DiscussionGroup;
use App\Enrollment;
use App\Exceptions\Handler;
use App\Forge;
use App\ForgeAssignmentQuestion;
use App\ForgeSettings;
use App\Helpers\Helper;
use App\Http\Requests\CustomTimeToSubmitRequest;
use App\Http\Requests\StartClickerAssessment;
use App\Http\Requests\UpdateAssignmentQuestionWeightRequest;
use App\Http\Requests\UpdateCompletionScoringModeRequest;
use App\Http\Requests\UpdateDiscussItSettingsRequest;
use App\Http\Requests\UpdateForgeSettings;
use App\Http\Requests\UpdateOpenEndedSubmissionType;
use App\IMathAS;
use App\JWE;
use App\LearningTree;
use App\PendingQuestionRevision;
use App\RandomizedAssignmentQuestion;
use App\ReportToggle;
use App\RubricPointsBreakdown;
use App\Solution;
use App\Traits\LibretextFiles;
use App\Traits\Seed;
use App\Traits\Statistics;
use App\User;
use App\Webwork;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use DOMDocument;
use Exception;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateAssignmentQuestionPointsRequest;
use App\Assignment;
use App\Question;
use App\Submission;
use App\SubmissionFile;
use App\Extension;


use App\Traits\IframeFormatter;
use App\Traits\DateFormatter;
use App\AssignmentSyncQuestion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Traits\S3;
use App\Traits\SubmissionFiles;
use App\Traits\GeneralSubmissionPolicy;
use App\Traits\LatePolicy;
use App\Traits\JWT;
use Carbon\Carbon;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;


class AssignmentSyncQuestionController extends Controller
{
    use DateFormatter;
    use IframeFormatter;
    use DateFormatter;
    use GeneralSubmissionPolicy;
    use S3;
    use SubmissionFiles;
    use JWT;
    use LibretextFiles;
    use LatePolicy;
    use Statistics;
    use Seed;


    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public function getFlashcardCardSettings(Assignment             $assignment,
                                             Question               $question,
                                             AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getFlashcardCardSettings', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->whereNotNull('flashcard_card_settings')
                ->select('flashcard_card_settings')
                ->first();

            $response['type'] = 'success';
            $flashcard_card_settings = null;
            if ($assignment_question) {
                $flashcard_card_settings = json_decode($assignment_question->flashcard_card_settings);
            } else {
                if (request()->user()->role === 2) {
                    $flashcard_card_settings = json_decode($assignment->flashcard_settings);
                    $flashcard_card_settings = ['captions' => $flashcard_card_settings->captions->enabled,
                        'text_to_speech' => $flashcard_card_settings->text_to_speech->enabled,
                        'show_hint' => $flashcard_card_settings->show_hint->enabled];
                }
            }
            $response['flashcard_card_settings'] = $flashcard_card_settings;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error getting the flashcard card settings. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateFlashcardCardSettings(Request                $request,
                                         Assignment             $assignment,
                                         Question               $question,
                                         AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateFlashcardCardSettings', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $settings = $request->all();

            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['flashcard_card_settings' => json_encode($settings)]);

            $response['type'] = 'success';
            $response['message'] = 'The card settings have been updated.';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error updating the flashcard card settings. Please try again or contact us for assistance.';
        }
        return $response;
    }

    /**
     * Get the submission count for a forge draft from the 3rd party API
     *
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function getForgeDraftSubmissions(Assignment $assignment, Question $question, AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('getForgeDraftSubmissions', [$assignmentSyncQuestion, $assignment, $question]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            // Get the draft UUID from forge_settings
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->forge_source_id)
                ->first();

            if (!$assignment_question || !$assignment_question->forge_settings) {
                $response['message'] = 'Forge settings not found.';
                return $response;
            }

            $forge_settings = json_decode($assignment_question->forge_settings, true);
            $drafts = $forge_settings['drafts'] ?? [];

            // Find the draft with this question_id
            $draft_uuid = null;
            foreach ($drafts as $draft) {
                if (isset($draft['question_id']) && $draft['question_id'] === $question->id) {
                    $draft_uuid = $draft['uuid'];
                    break;
                }
            }

            if (!$draft_uuid) {
                $response['message'] = 'Draft not found.';
                return $response;
            }
            if (app()->environment('local')) {
                $response['type'] = 'success';
                $response['submission_count'] = 0;
                return $response;
            }
            $api_url = config('services.antecedent.url') . "/api/adapt/draft/$draft_uuid/submissions";

            $secret = DB::table('key_secrets')
                ->where('key', 'forge')
                ->first()
                ->secret;
            $api_response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $secret",
            ])->get($api_url);

            if (!$api_response->successful()) {
                $response['message'] = 'Failed to retrieve submission count from external service.';
                return $response;
            }

            $api_data = $api_response->json();
            $response['type'] = 'success';
            $response['submission_count'] = $api_data['data']['submissionCount'] ?? 0;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = 'There was an error checking for submissions. Please try again or contact us for assistance.';
        }

        return $response;
    }

    /**
     * @param UpdateForgeSettings $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param ForgeSettings $forgeSettings
     * @return array
     * @throws Exception
     */
    public
    function updateForgeSettings(
        UpdateForgeSettings    $request,
        Assignment             $assignment,
        Question               $question,
        AssignmentSyncQuestion $assignmentSyncQuestion,
        ForgeSettings          $forgeSettings
    ): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateForgeSettings', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $data = $request->all();
            $user_timezone = Auth::user()->time_zone;
            $user_id = Auth::user()->id;

            // Get the current order of the main forge question
            $main_assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();

            if (!$main_assignment_question) {
                $response['message'] = "The main forge question is not associated with this assignment.";
                return $response;
            }

            $main_question_points = $main_assignment_question->points;

            // Get existing draft question IDs from current forge_settings
            $existing_forge_settings = json_decode($main_assignment_question->forge_settings, true);
            $existing_draft_question_ids = [];
            if ($existing_forge_settings && isset($existing_forge_settings['drafts'])) {
                foreach ($existing_forge_settings['drafts'] as $existing_draft) {
                    if (!$existing_draft['isFinal'] && isset($existing_draft['question_id'])) {
                        $existing_draft_question_ids[$existing_draft['uuid']] = $existing_draft['question_id'];
                    }
                }
            }

            // Build clean drafts array with only necessary fields, converting to UTC
            $clean_drafts = [];
            $draft_number = 1;
            $new_draft_question_ids = []; // Track which draft question IDs are still in use

            DB::beginTransaction();
            if (isset($data['drafts']) && is_array($data['drafts'])) {
                foreach ($data['drafts'] as $index => $draft) {
                    // Auto-generate title if empty
                    $title = trim($draft['title'] ?? '');
                    if (empty($title)) {
                        if ($draft['isFinal']) {
                            $title = 'Final Submission';
                        } else {
                            $title = 'Draft ' . $draft_number;
                            $draft_number++;
                        }
                    } else {
                        if (!$draft['isFinal']) {
                            $draft_number++;
                        }
                    }

                    $clean_draft = [
                        'uuid' => $draft['uuid'],
                        'title' => $title,
                        'late_policy' => $draft['late_policy'],
                        "late_deduction_percent" => $draft['late_deduction_percent'],
                        "late_deduction_applied_once" => $draft['late_deduction_applied_once'],
                        "late_deduction_application_period" => $draft["late_deduction_application_period"],
                        'isFinal' => $draft['isFinal'],
                        'assign_tos' => [],
                        'extensions' => []
                    ];

                    if (isset($draft['assign_tos']) && is_array($draft['assign_tos'])) {
                        foreach ($draft['assign_tos'] as $assign_to) {
                            $clean_draft['assign_tos'][] = $assignmentSyncQuestion->draftAssignTo($assign_to, $user_timezone);
                        }
                    }

                    // Process extensions at draft level
                    if (isset($draft['extensions']) && is_array($draft['extensions'])) {
                        foreach ($draft['extensions'] as $extension) {
                            $timezone = request()->user()->time_zone;

                            $clean_extension = [
                                'user_id' => $extension['user_id'],
                                'student_name' => $extension['student_name'],
                                'due_date' => $extension['due_date'],
                                'due_time' => $extension['due_time'],
                                'final_submission_deadline_date' => $extension['final_submission_deadline_date'] ?? '',
                                'final_submission_deadline_time' => $extension['final_submission_deadline_time'] ?? '',
                            ];
                            // Convert extension times to UTC

                            $clean_extension['due'] = $this->convertLocalMysqlFormattedDateToUTC(
                                $clean_extension['due_date'] . ' ' . $clean_extension['due_time'],
                                $timezone
                            );

                            if (!empty($clean_extension['final_submission_deadline_date']) && !empty($clean_extension['final_submission_deadline_time'])) {
                                $clean_extension['final_submission_deadline'] = $this->convertLocalMysqlFormattedDateToUTC(
                                    $clean_extension['final_submission_deadline_date'] . ' ' . $clean_extension['final_submission_deadline_time'],
                                    $timezone
                                );
                            }

                            $clean_draft['extensions'][] = $clean_extension;
                        }
                    }

                    // Handle draft questions (non-final)
                    if (!$draft['isFinal']) {
                        $draft_question_id = null;

                        // Build qti_json for the draft, based on parent's qti_json but with forge_iteration type
                        $parent_qti_json = json_decode($question->qti_json, true);
                        $draft_qti_json = null;
                        if ($parent_qti_json) {
                            $parent_qti_json['questionType'] = 'forge_iteration';
                            $draft_qti_json = json_encode($parent_qti_json);
                        }

                        // Check if this draft already has a question
                        if (isset($existing_draft_question_ids[$draft['uuid']])) {
                            $draft_question_id = $existing_draft_question_ids[$draft['uuid']];

                            // Update existing question title and qti_json if changed
                            $update_data = ['title' => $title];
                            if ($draft_qti_json) {
                                $update_data['qti_json'] = $draft_qti_json;
                            }
                            Question::where('id', $draft_question_id)
                                ->update($update_data);
                        } else {
                            // Create new question for this draft
                            $draft_question = Question::create([
                                'qti_json_type' => 'forge_iteration',
                                'qti_json' => $draft_qti_json,
                                'forge_source_id' => $question->id,
                                'title' => $title,
                                'library' => 'adapt',
                                'technology' => 'qti',
                                'technology_iframe' => '',
                                'rubric' => $question->rubric,
                                'question_editor_user_id' => $user_id,
                                'page_id' => 0, // Will be updated below
                                'public' => 0,
                            ]);
                            $draft_question->page_id = $draft_question->id;
                            $draft_question->save();

                            $draft_question_id = $draft_question->id;
                        }

                        $clean_draft['question_id'] = $draft_question_id;
                        $new_draft_question_ids[] = $draft_question_id;
                    } else {
                        $assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                            ->where('question_id', $question->id)
                            ->first();
                        DB::table('assignment_question_forge_draft')
                            ->where('assignment_question_id', $assignment_question->id)
                            ->delete();
                        DB::table("assignment_question_forge_draft")->insert([
                            'assignment_question_id' => $assignment_question->id,
                            'forge_draft_id' => $draft['uuid'],
                            'created_at' => now(),
                            'updated_at' => now()]);
                    }

                    $clean_drafts[] = $clean_draft;
                }
            }

            // Find draft questions that were removed and should be deleted
            $removed_draft_question_ids = array_diff(
                array_values($existing_draft_question_ids),
                $new_draft_question_ids
            );

            // TODO: For now, just log removed drafts - implement actual deletion later
            if (!empty($removed_draft_question_ids)) {
                // Remove from assignment_question
                DB::table('assignment_question')
                    ->where('assignment_id', $assignment->id)
                    ->whereIn('question_id', $removed_draft_question_ids)
                    ->delete();

                // TODO: Delete the actual questions and handle any related data
                // For now, we'll leave the questions in the database
                // Question::whereIn('id', $removed_draft_question_ids)->delete();
            }

            // Update assignment_question order and add new draft questions
            // First, shift all questions after the main forge question to make room for drafts


            // Get all questions in this assignment ordered
            $assignment_questions = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->orderBy('order')
                ->get();

            // Recalculate orders: drafts go right before the main forge question
            $new_order = 1;
            $draft_index = 0;

            foreach ($assignment_questions as $aq) {
                // When we reach the main forge question, insert drafts before it
                if ($aq->question_id == $question->id) {
                    // Add/update draft questions before the main question
                    foreach ($clean_drafts as &$clean_draft) {
                        if (!$clean_draft['isFinal'] && isset($clean_draft['question_id'])) {
                            $existing_aq = DB::table('assignment_question')
                                ->where('assignment_id', $assignment->id)
                                ->where('question_id', $clean_draft['question_id'])
                                ->first();
                            if ($existing_aq) {
                                // Update order
                                DB::table('assignment_question')
                                    ->where('id', $existing_aq->id)
                                    ->update(['order' => $new_order]);
                                if (!DB::table('assignment_question_forge_draft')
                                    ->where('assignment_question_id', $existing_aq->id)
                                    ->first()) {
                                    DB::table('assignment_question_forge_draft')
                                        ->insert(['assignment_question_id' => $existing_aq->id,
                                            'forge_draft_id' => $clean_draft['uuid'],
                                            'created_at' => now(),
                                            'updated_at' => now()]);
                                }
                            } else {

                                // Insert new assignment_question
                                $assignment_question_id = DB::table('assignment_question')->insertGetId([
                                    'assignment_id' => $assignment->id,
                                    'question_id' => $clean_draft['question_id'],
                                    'order' => $new_order,
                                    'weight' => $assignment->points_per_question === 'question weight' ? 1 : null,
                                    'points' => $main_question_points,
                                    'open_ended_submission_type' => '0',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                DB::table('assignment_question_forge_draft')->insert([
                                    'assignment_question_id' => $assignment_question_id,
                                    'forge_draft_id' => $clean_draft['uuid'],
                                    'created_at' => now(),
                                    'updated_at' => now()]);
                            }
                            $new_order++;
                        }
                    }
                    unset($clean_draft); // Break reference

                    // Now set the main forge question order
                    DB::table('assignment_question')
                        ->where('id', $aq->id)
                        ->update(['order' => $new_order]);
                    $new_order++;
                } else {
                    // Skip draft questions we already handled
                    if (in_array($aq->question_id, $new_draft_question_ids)) {
                        continue;
                    }
                    // Update order for other questions
                    DB::table('assignment_question')
                        ->where('id', $aq->id)
                        ->update(['order' => $new_order]);
                    $new_order++;
                }
            }

            $forge_settings = [
                'final_submission_locked' => $request->final_submission_locked ? $request->final_submission_locked : false,
                'drafts' => $clean_drafts,
                'settings' => $data['settings'] ?? []
            ];

            $data_to_post_to_forge = $forge_settings;
            foreach ($data_to_post_to_forge['drafts'] as &$draft) {
                unset($draft['assign_tos']);
            }
            unset($draft); // Break reference

            $forge_assignment_question = ForgeAssignmentQuestion::where('adapt_assignment_id', $assignment->id)
                ->where('adapt_question_id', $question->id)
                ->first();

            if (!$forge_assignment_question) {
                $response['message'] = "There is no associated Forge assignment question.";
                DB::rollBack();
                return $response;
            }
            $data_to_post_to_forge['forge_question_id'] = $forge_assignment_question->forge_question_id;
            $data_to_post_to_forge['forge_class_id'] = $forge_assignment_question->forge_class_id;
            if (!app()->environment('local')) {
                $forge_response = $forgeSettings->store($data_to_post_to_forge);

                if ($forge_response['type'] === 'error') {
                    throw new Exception($forge_response['message']);
                }
            }
            // Store forge_settings only on the Final Submission (main forge question)
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['forge_settings' => json_encode($forge_settings)]);
            $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment);
            DB::commit();

            $response['type'] = 'success';
            $response['message'] = 'The Forge settings have been updated.';
            $response['drafts'] = $clean_drafts; // Return drafts with question_ids
        } catch (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the Forge settings: {$e->getMessage()}";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function getForgeSettings(
        Request                $request,
        Assignment             $assignment,
        Question               $question,
        AssignmentSyncQuestion $assignmentSyncQuestion
    ): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getForgeSettings', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $user_timezone = Auth::user()->time_zone;

            // Get assignment's assign_tos (same as assignment summary)
            $assign_tos = $assignment->assignToGroups();

            foreach ($assign_tos as $key => $assign_to) {
                $available_from = $assign_to['available_from'];
                $due = $assign_to['due'];
                $final_submission_deadline = $assign_to['final_submission_deadline'];
                $assign_tos[$key]['available_from_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($available_from, $user_timezone);
                $assign_tos[$key]['available_from_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($available_from, $user_timezone);
                $assign_tos[$key]['due_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($due, $user_timezone);
                $assign_tos[$key]['due_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($due, $user_timezone);
                if ($final_submission_deadline) {
                    $assign_tos[$key]['final_submission_deadline_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($final_submission_deadline, $user_timezone);
                    $assign_tos[$key]['final_submission_deadline_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($final_submission_deadline, $user_timezone);
                }

            }


            // Get existing forge settings from assignment_question table
            $assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();

            $drafts = [];
            $settings = [];
            $final_submission_locked = false;
            if ($assignment_question && $assignment_question->forge_settings) {
                $forge_settings = json_decode($assignment_question->forge_settings, true);
                $drafts = $forge_settings['drafts'] ?? [];
                $settings = $forge_settings['settings'] ?? [];
                $final_submission_locked = $forge_settings['final_submission_locked'] ?? false;
                // Convert draft timestamps from UTC to local date/time fields
                foreach ($drafts as $draft_index => $draft) {
                    if (isset($draft['assign_tos']) && is_array($draft['assign_tos'])) {
                        foreach ($draft['assign_tos'] as $assign_to_index => $assign_to) {
                            // Convert available_from UTC timestamp to local date/time
                            if (!empty($assign_to['available_from'])) {
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['available_from_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($assign_to['available_from'], $user_timezone);
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['available_from_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($assign_to['available_from'], $user_timezone);
                            }

                            // Convert due UTC timestamp to local date/time
                            if (!empty($assign_to['due'])) {
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['due_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($assign_to['due'], $user_timezone);
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['due_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($assign_to['due'], $user_timezone);
                            }

                            if (!empty($assign_to['final_submission_deadline'])) {
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['final_submission_deadline_date'] = $this->convertUTCMysqlFormattedDateToLocalDate($assign_to['final_submission_deadline'], $user_timezone);
                                $drafts[$draft_index]['assign_tos'][$assign_to_index]['final_submission_deadline_time'] = $this->convertUTCMysqlFormattedDateToLocalTime($assign_to['final_submission_deadline'], $user_timezone);
                            }
                        }
                    }
                }
            }

            $response['type'] = 'success';
            $response['late_policy'] = $assignment->late_policy;
            $response['late_deduction_percent'] = $assignment->late_deduction_percent;
            $response['late_deduction_application_period'] = $assignment->late_deduction_application_period;
            $response['late_deduction_applied_once'] = $assignment->late_deduction_application_period === 'once';
            $response['assign_tos'] = $assign_tos;
            $response['drafts'] = $drafts;
            $response['final_submission_locked'] = $final_submission_locked;
            $response['settings'] = $settings;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the Forge settings. Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     * @throws Exception
     */
    public
    function removeOpenEndedQuestions(Assignment             $assignment,
                                      AssignmentSyncQuestion $assignmentSyncQuestion,
                                      BetaCourseApproval     $betaCourseApproval): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('allSolutionsReleasedWhenClosed', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $fake_student_user_id = Enrollment::join('users', 'enrollments.user_id', '=', 'users.id')
                ->where('course_id', $assignment->course->id)
                ->where('users.fake_student', 1)
                ->select('users.id')
                ->first()
                ->id;
            $assignment_questions = $assignmentSyncQuestion->where('assignment_id', $assignment->id)->get();
            $submissions = Submission::where('assignment_id', $assignment->id)
                ->join('users', 'submissions.user_id', '=', 'users.id')
                ->whereNotIn('users.id', [$fake_student_user_id, $assignment->course->user_id])
                ->get();
            $question_ids_with_submissions = [];
            foreach ($submissions as $submission) {
                $question_ids_with_submissions[] = $submission->question_id;
            }
            $submission_files = SubmissionFile::where('assignment_id', $assignment->id)
                ->join('users', 'submission_files.user_id', '=', 'users.id')
                ->whereNotIn('users.id', [$fake_student_user_id, $assignment->course->user_id])
                ->get();
            foreach ($submission_files as $submission_file) {
                $question_ids_with_submissions[] = $submission_file->question_id;
            }
            $question_ids_with_submissions = array_unique($question_ids_with_submissions);
            if ($question_ids_with_submissions) {
                $response['type'] = 'error';
                $response['message'] = 'You cannot remove questions from this assignment since there are already student submissions.';
                return $response;
            }
            DB::beginTransaction();
            foreach ($assignment_questions as $assignment_question) {
                if ($assignment_question->open_ended_submission_type !== '0') {
                    $question = Question::find($assignment_question->question_id);
                    $assignmentSyncQuestion->removeRandomizedAssessment($assignment, $question);
                    $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
                    Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
                    DB::table('assignment_question')->where('question_id', $question->id)
                        ->where('assignment_id', $assignment->id)
                        ->delete();
                    DB::table('randomized_assignment_questions')->where('assignment_id', $assignment->id)
                        ->where('question_id', $question->id)
                        ->delete();
                    DB::table('question_level_overrides')->where('question_id', $question->id)
                        ->where('assignment_id', $assignment->id)
                        ->delete();
                    DB::table('submission_histories')->where('question_id', $question->id)
                        ->where('assignment_id', $assignment->id)
                        ->delete();
                    $assignmentSyncQuestion->reOrderAndWeightQuestions($assignment);

                    $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question->id, 'remove', 0);
                }
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = 'The open-ended assessments have been removed.';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to remove the open-ended questions from this assignment.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function allSolutionsReleasedWhenClosed(Assignment             $assignment,
                                            AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('allSolutionsReleasedWhenClosed', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $all_solutions_released_when_closed = true;
            if ($assignment->assessment_type === 'clicker') {
                $assignment_questions = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                    ->where('release_solution_when_question_is_closed', 0)
                    ->get();
                foreach ($assignment_questions as $assignment_question) {
                    //check for view and submit
                    if ($assignment_question && time() > strtotime($assignment_question->clicker_end)) {
                        $all_solutions_released_when_closed = false;
                    }
                }
            }
            $response['type'] = 'success';
            $response['all_solutions_released_when_closed'] = $all_solutions_released_when_closed;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to check whether whether all solutions are released when closed for this assignment.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param RubricPointsBreakdown $rubricPointsBreakdown
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function deleteCustomRubric(Assignment             $assignment,
                                Question               $question,
                                RubricPointsBreakdown  $rubricPointsBreakdown,
                                AssignmentSyncQuestion $assignmentSyncQuestion)
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('deleteCustomRubric', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->delete();
            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_rubric' => null]);
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "The overriding rubric has been deleted.";
        } catch (Exception $e) {

            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete the custom rubric for this assignment question.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param RubricPointsBreakdown $rubricPointsBreakdown
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateUseExistingRubric(Assignment             $assignment,
                                     Question               $question,
                                     RubricPointsBreakdown  $rubricPointsBreakdown,
                                     AssignmentSyncQuestion $assignmentSyncQuestion)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateUseExistingRubric', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->delete();
            $assignment_question = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $use_existing_rubric = +$assignment_question->use_existing_rubric;
            $assignment_question->update(['use_existing_rubric' => 1 - $use_existing_rubric]);
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = $use_existing_rubric ? "This question will use the override rubric." : "This question will use the existing rubric.";
        } catch (Exception $e) {

            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete the custom rubric for this assignment question.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param RubricPointsBreakdown $rubricPointsBreakdown
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public
    function updateCustomRubric(Assignment             $assignment,
                                Question               $question,
                                RubricPointsBreakdown  $rubricPointsBreakdown,
                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                Request                $request): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateCustomRubric', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $custom_rubric = json_decode($request->custom_rubric);
            $current_custom_rubric = json_decode($assignment_question->custom_rubric, 1);
            if (!$current_custom_rubric) {
                $current_custom_rubric = ['rubric_items' => []];
            }
            $current_custom_rubric_items = $current_custom_rubric['rubric_items'] ?: [];
            $new_custom_rubric = json_decode(json_encode($custom_rubric), 1);
            $new_custom_rubric_items = $new_custom_rubric['rubric_items'] ?: [];
            DB::beginTransaction();
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_rubric' => $request->custom_rubric, 'use_existing_rubric' => 0]);
            if (!$this->_rubricsAreTheSame($current_custom_rubric_items, $new_custom_rubric_items)) {
                $rubric_points_breakdown_exists = $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->exists();
                $rubricPointsBreakdown->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->delete();
                $response['message'] = 'The rubric for this assignment question has been updated.';
                if ($rubric_points_breakdown_exists) {
                    $response['message'] .= "<br><br>In addition, the existing student rubric point breakdowns have been deleted for this assignment question.";
                }
                $response['type'] = 'success';
            } else {
                $response['type'] = 'info';
                $response['message'] = 'No updating of rubric.';
            }
            DB::commit();
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your custom rubric to this assignment question.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Request $request
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function checkForDiscussItQuestionsOverMultipleAssignmentQuestions(Request                $request,
                                                                       AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('checkForDiscussItQuestionsOverMultipleAssignmentQuestions', $assignmentSyncQuestion);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignment_questions = $request->all();
            $discuss_it_question_exists = DB::table('assignment_question')
                ->whereNotNull('discuss_it_settings')
                ->where(function ($query) use ($assignment_questions) {
                    foreach ($assignment_questions as $question) {
                        $query->orWhere([
                            ['assignment_id', '=', $question['assignment_id']],
                            ['question_id', '=', $question['question_id']]
                        ]);
                    }
                })
                ->exists();
            $response['type'] = 'success';
            $response['discuss_it_question_exists'] = $discuss_it_question_exists;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error determining whether discuss it questions exist in this group of assignment questions.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param string $level
     * @param int $id
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function checkForDiscussitClickerOrOpenEndedQuestionsByCourseOrAssignment(string                 $level,
                                                                              int                    $id,
                                                                              AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('checkForDiscussitClickerOrOpenEndedQuestionsByCourseOrAssignment', $assignmentSyncQuestion);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            switch ($level) {
                case('course'):
                    $assignments = Assignment::where('course_id', $id)->get();
                    $discuss_it_questions_exist = false;
                    $open_ended_questions_in_real_time_assignment_exist = false;
                    foreach ($assignments as $assignment) {
                        if ($discuss_it_questions_exist && $open_ended_questions_in_real_time_assignment_exist) {
                            continue;
                        }
                        $assignment_questions = $assignmentSyncQuestion->getAssignmentQuestionsConsideringRevisions($assignment);
                        if (!$discuss_it_questions_exist) {
                            $discuss_it_questions_exist = $assignmentSyncQuestion->discussItQuestionsExist($assignment_questions);
                        }
                        if (!$open_ended_questions_in_real_time_assignment_exist) {
                            $open_ended_questions_in_real_time_assignment_exist = $assignmentSyncQuestion->openEndedQuestionsInRealTimeAssignmentExists($assignment, $assignment_questions);
                        }
                    }
                    $clicker_questions_exist = DB::table('courses')
                        ->join('assignments', 'courses.id', '=', 'assignments.course_id')
                        ->join('assignment_question', 'assignments.id', '=', 'assignment_question.assignment_id')
                        ->where('courses.id', $id)
                        ->where(function ($query) {
                            return $query
                                ->whereNotNull('assignment_question.custom_clicker_time_to_submit')
                                ->orWhere('assignment_question.custom_clicker_time_to_submit', 0);
                        })
                        ->exists();
                    break;
                case('assignment'):
                    $clicker_questions_exist = DB::table('assignments')
                        ->join('assignment_question', 'assignments.id', '=', 'assignment_question.assignment_id')
                        ->where('assignments.id', $id)
                        ->where(function ($query) {
                            return $query
                                ->whereNotNull('assignment_question.custom_clicker_time_to_submit')
                                ->orWhere('assignment_question.release_solution_when_question_is_closed', 0);
                        })
                        ->exists();
                    $assignment = Assignment::find($id);
                    $assignment_questions = $assignmentSyncQuestion->getAssignmentQuestionsConsideringRevisions($assignment);
                    $discuss_it_questions_exist = $assignmentSyncQuestion->discussItQuestionsExist($assignment_questions);
                    $open_ended_questions_in_real_time_assignment_exist = $assignmentSyncQuestion->openEndedQuestionsInRealTimeAssignmentExists($assignment, $assignment_questions);

                    break;
                default:
                    throw new Exception("$level is not a valid level.");
            }


            $response['discuss_it_questions_exist'] = $discuss_it_questions_exist;
            $response['open_ended_questions_in_real_time_assignment_exist'] = $open_ended_questions_in_real_time_assignment_exist;
            $response['clicker_questions_exist'] = $clicker_questions_exist;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error determining whether discuss it questions exist in the $level.  Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param int $can_submit_work_override
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateCanSubmitWorkOverride(Request                $request,
                                         Assignment             $assignment,
                                         Question               $question,
                                         int                    $can_submit_work_override,
                                         AssignmentSyncQuestion $assignmentSyncQuestion)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateCanSubmitWorkOverride', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['can_submit_work_override' => $can_submit_work_override]);
            $response['type'] = $can_submit_work_override ? 'success' : 'info';
            $verb = $can_submit_work_override ? 'can' : 'cannot';
            $response['message'] = "Your students $verb submit their work for review.";

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the 'can submit work' option.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function getDiscussItQuestionsByAssignment(Request                $request,
                                               Assignment             $assignment,
                                               Question               $question,
                                               AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getDiscussItQuestionsByAssignment', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $question_ids = $assignment->questions->pluck('id')->toArray();
            $discuss_it_questions = $question->whereIn('id', $question_ids)
                ->where('technology', 'qti')
                ->get();
            $discuss_it_question_ids = [];
            foreach ($discuss_it_questions as $discuss_it_question) {
                if ($discuss_it_question->isDiscussIt()) {
                    $discuss_it_question_ids[] = $discuss_it_question->id;
                }
            }
            $submitted_file_infos = DB::table('submission_files')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $request->user()->id)
                ->get();
            $submitted_file_infos_by_question_id = [];
            foreach ($submitted_file_infos as $submitted_file_info) {
                $submitted_file_infos_by_question_id[$submitted_file_info->question_id] = $submitted_file_info;
            }

            $discuss_it_question_info = [];
            foreach ($discuss_it_question_ids as $discuss_it_question_id) {
                $submitted_file_info = $submitted_file_infos_by_question_id[$discuss_it_question_id] ?? null;
                $last_submitted = $submitted_file_info
                    ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime(
                        $submitted_file_info->date_submitted,
                        $request->user()->time_zone, 'M d, Y g:i:s a')
                    : 'Not yet fully submitted';
                $score = $submitted_file_info ? Helper::removeZerosAfterDecimal($submitted_file_info->score) : 'N/A';
                $discuss_it_question_info[] = ['id' => $discuss_it_question_id,
                    'last_submitted' => $last_submitted,
                    'total_score' => $score];
            }
            $response['discuss_it_question_info'] = $discuss_it_question_info;
            $response['type'] = 'success';
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the discuss it question info for this assignment.  Please try again or contact us for assistance.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function getDiscussItSettings(Request                $request,
                                  Assignment             $assignment,
                                  Question               $question,
                                  AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getDiscussItSettings', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $discuss_it_settings = $assignmentSyncQuestion->discussItSettings($assignment->id, $question->id);
            $discussion_comments_exist = DB::table('discussion_comments')
                ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
                ->join('users', 'discussion_comments.user_id', '=', 'users.id')
                ->where('users.role', 3)
                ->where('discussions.assignment_id', $assignment->id)
                ->where('discussions.question_id', $question->id)
                ->exists();
            $discuss_it_completion_status = [];
            $response['type'] = 'success';
            $response['discuss_it_settings'] = $discuss_it_settings;
            $response['discuss_it_completion_status'] = $discuss_it_completion_status;
            $response['discussion_comments_exist'] = $discussion_comments_exist;
            $response['show_submit_at_least_x_comments'] = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->first()
                    ->id < 2382618;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the discuss-it settings.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param UpdateDiscussItSettingsRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateDiscussItSettings(UpdateDiscussItSettingsRequest $request,
                                     Assignment                     $assignment,
                                     Question                       $question,
                                     AssignmentSyncQuestion         $assignmentSyncQuestion): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateDiscussItSettings', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            DB::beginTransaction();
            $grading_criteria = ['min_length_of_audio_video' => '',
                "min_number_of_words" => ''];
            foreach ($grading_criteria as $key => $value) {
                if (!isset($data[$key])) {
                    $data[$key] = $value;
                }
            }

            DB::table('discussion_groups')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->delete();

            for ($i = 1; $i <= $data['number_of_groups']; $i++) {
                DiscussionGroup::create(['assignment_id' => $assignment->id,
                    'question_id' => $question->id,
                    'user_id' => $request->user()->id,
                    'group' => $i]);
            }
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['discuss_it_settings' => $data]);

            Cache::put("discuss_it_settings_{$assignment->course->user_id}", json_encode($data));
            $response['type'] = 'success';
            $response['message'] = 'The discuss-it settings have been updated.';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the discuss-it settings.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    public
    function updateIFrameProperties(Request                $request,
                                    Assignment             $assignment,
                                    Question               $question,
                                    AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('updateIFrameProperties', [$assignmentSyncQuestion, $assignment, $question]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $item = $request->item;
            if (!in_array($item, ['assignment', 'submission', 'attribution'])) {
                $response['message'] = "$item is not a valid iframe item.";
                return $response;
            }
            $column = "{$item}_information_shown_in_iframe";
            $current_value = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first()
                ->$column;

            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update([$column => !$current_value]);
            $response['type'] = $current_value ? 'info' : 'success';
            $current_value_text = $current_value ? 'will not' : 'will';
            $response['message'] = "The $item information $current_value_text be shown in the iframe.";


        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the iframe properties.  Please try again or contact us for assistance.";
        }

        return $response;


    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function validateCanSwitchToOrFromCompiledPdf(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $submission_files = DB::table('submission_files')
                ->join('users', 'submission_files.user_id', '=', 'users.id')
                ->where('fake_student', 0)
                ->where('assignment_id', $assignment->id)
                ->first();
            if ($submission_files) {
                $response['message'] = "Since students have already submitted responses, you can't switch this option.";
                return $response;
            }
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error validating whether you can switch from a compiled PDF assignment.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function validateCanSwitchToCompiledPdf(Assignment $assignment): array
    {
        $response['type'] = 'error';
        try {
            $has_other_types = DB::table('assignment_question')->where('assignment_id', $assignment->id)
                ->where('open_ended_submission_type', '<>', 'file')
                ->where('open_ended_submission_type', '<>', '0')
                ->first();
            if ($has_other_types) {
                $response['message'] = 'If you would like to use the compiled PDF feature, please update your assessments so that they are all of type "file" or "none".';
                return $response;
            }

            $response['type'] = 'success';


        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error validating whether you can switch from a compiled PDF assignment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     * @throws Exception
     */
    public
    function remixAssignmentWithChosenQuestions(Request                $request,
                                                Assignment             $assignment,
                                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                                BetaCourseApproval     $betaCourseApproval): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('remixAssignmentWithChosenQuestions', [$assignmentSyncQuestion, $assignment]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
            $response['message'] = "You cannot access the remixer since there are already submissions and this assignment computes points using question weights.";
            return $response;
        }


        try {
            $chosen_questions = $request->chosen_questions;
            $assignment_questions = $assignment->questions->pluck('id')->toArray();
            switch ($request->question_source) {
                case('all_questions'):
                case('my_questions'):
                case('my_favorites'):
                    $belongs_to_assignment = false;
                    break;
                case('commons'):
                case('my_courses'):
                case('all_public_courses'):
                    $belongs_to_assignment = true;
                    break;
                default:
                    $response['message'] = "$request->question_source is not a valid question source.";
                    return $response;
            }
            DB::beginTransaction();
            foreach ($chosen_questions as $key => $question) {
                if (!in_array($question['question_id'], $assignment_questions)) {
                    $learning_tree_id = null;
                    if ($belongs_to_assignment) {
                        $assignment_question = DB::table('assignment_question')
                            ->where('assignment_id', $question['assignment_id'])
                            ->where('question_id', $question['question_id'])
                            ->first();
                        if (!$assignment_question) {
                            $response['message'] = "Question {$question['question_id']} does not belong to that assignment.";
                            DB::rollBack();
                            return $response;
                        }
                        if ($assignment_question->discuss_it_settings) {
                            $assignment_question->discuss_it_settings = +$request->reset_discuss_it_settings_to_default === 1
                                ? Helper::defaultDiscussItSettings()
                                : Helper::makeDiscussItSettingsBackwardsCompatible($assignment_question->discuss_it_settings);
                        }

                        $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
                            ->where('assignment_question_id', $assignment_question->id)
                            ->first();
                        if ($assignment_question_learning_tree) {
                            $learning_tree_id = $assignment_question_learning_tree->learning_tree_id;
                        }
                    } else {
                        switch ($request->question_source) {
                            case('my_favorites'):
                                $assignment_question = DB::table('my_favorites')
                                    ->where('question_id', $question['question_id'])
                                    ->where('user_id', $request->user()->id)
                                    ->select('question_id',
                                        'open_ended_submission_type',
                                        'open_ended_text_editor',
                                        'learning_tree_id')
                                    ->first();
                                $possible_discuss_it_question = Question::find($assignment_question->question_id);
                                if ($possible_discuss_it_question->isDiscussIt()) {
                                    $assignment_question->discuss_it_settings = Helper::defaultDiscussItSettings();
                                }
                                $assignment_question_learning_tree = $assignment_question->learning_tree_id !== null;
                                $learning_tree_id = $assignment_question->learning_tree_id;
                                unset($assignment_question->learning_tree_id);
                                break;
                            case('my_questions'):
                            case('all_questions'):
                                $assignment_question = DB::table('questions')
                                    ->where('id', $question['question_id'])
                                    ->select('id AS question_id')
                                    ->first();
                                $possible_discuss_it_question = Question::find($assignment_question->question_id);
                                if ($possible_discuss_it_question->isDiscussIt()) {
                                    $assignment_question->discuss_it_settings = Helper::defaultDiscussItSettings();
                                }
                                //they can always change the stuff below.  Since the question is not in an assignment I can't tell what the instructor wants
                                $assignment_question->open_ended_submission_type = 0;
                                $assignment_question->open_ended_text_editor = null;
                                // (Maybe the above is old logic.  So let me check for the assignment id)
                                if ($assignment->id) {
                                    $assignment_question->open_ended_submission_type = $assignment->default_open_ended_submission_type;
                                    $assignment_question->open_ended_text_editor = $assignment->default_open_ended_text_editor;
                                }
                                $assignment_question_learning_tree = false;
                                break;
                            default:
                                $response['message'] = "$request->question_source is not a valid question source.";
                                return $response;

                        }
                    }

                    if ($assignment->file_upload_mode === 'compiled_pdf'
                        && !in_array($assignment_question->open_ended_submission_type, ['0', 'file'])) {
                        $response['message'] = "Your assignment is of file upload type Compiled PDF but you're trying to remix an open-ended type of $assignment_question->open_ended_submission_type.  If you would like to use this question, please edit your assignment and change the file upload type to 'Individual Assessment Upload' or 'Compiled Upload/Individual Assessment Upload'.";
                        DB::rollBack();
                        return $response;
                    }

                    unset($assignment_question->id);
                    $assignment_question->assignment_id = $assignment->id;
                    $assignment_question->order = count($assignment_questions) + $key + 1;
                    $question_to_add = Question::find($question['question_id']);
                    switch ($assignment->points_per_question) {
                        case('number of points'):
                            $assignment_question->points = $assignment->default_points_per_question;
                            break;
                        case('question weight'):
                            $assignment_question->points = 0;//will be updated below
                            $assignment_question->weight = 1;
                            break;
                        default:
                            throw new exception ("Invalid points_per_question");
                    }


                    $assignment_question->created_at = Carbon::now();
                    $assignment_question->updated_at = Carbon::now();
                    $assignment_question->completion_scoring_mode = ($assignment->scoring_type === 'c')
                        ? $assignment->default_completion_scoring_mode
                        : null;
                    $assignment_question_exists = DB::table('assignment_question')
                        ->where('assignment_id', $assignment->id)
                        ->where('question_id', $question['question_id'])->first();
                    $assignment_question_arr = (array)$assignment_question;

                    if ($assignment_question_exists) {
                        DB::table('assignment_question')
                            ->where('assignment_id', $assignment->id)
                            ->where('question_id', $question['question_id'])
                            ->update($assignment_question_arr);
                    } else {
                        DB::table('assignment_question')->insertGetId($assignment_question_arr);
                        if (!$assignment_question_learning_tree) {
                            $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question['question_id'], 'add');
                        }
                    }
                    $Question = Question::find($question['question_id']);
                    DB::table('assignment_question')
                        ->where('assignment_id', $assignment->id)
                        ->where('question_id', $question['question_id'])
                        ->update(['question_revision_id' => $Question->latestQuestionRevision('id')]);


                    if ($assignment_question_learning_tree) {
                        if (!DB::table('assignment_question_learning_tree')
                            ->where('assignment_question_id', $assignment_question->id)
                            ->first()) {
                            DB::table('assignment_question_learning_tree')
                                ->insert(['assignment_question_id' => $assignment_question->id,
                                    'learning_tree_id' => $learning_tree_id]);
                            $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question['question_id'], 'add', $learning_tree_id);
                        }
                    }
                }
            }
            //clean up the order, just in case
            $current_ordered_assignment_questions = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->orderBy('order')
                ->select('id')
                ->get();

            foreach ($current_ordered_assignment_questions as $key => $assignment_question) {
                DB::table('assignment_question')->where('id', $assignment_question->id)
                    ->update(['order' => $key + 1]);

            }
            $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment);
            DB::commit();
            $response['message'] = "The assessment has been added to your assignment.";
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the questions for this assignment.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    public
    function storeOpenEndedDefaultText(Request $request, Assignment $assignment, Question $question, AssignmentSyncQuestion $assignmentSyncQuestion)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('storeOpenEndedSubmissionDefaultText', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['open_ended_default_text' => $request->open_ended_default_text]);
            $response['message'] = 'The default text has been updated.';
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error saving the default open ended text.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function order(Request                $request,
                   Assignment             $assignment,
                   AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('order', [$assignmentSyncQuestion, $assignment]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $assignmentSyncQuestion->orderQuestions($request->ordered_questions, $assignment);
            DB::commit();
            $response['message'] = 'Your questions have been re-ordered.';
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error ordering the questions for this assignment.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function setCurrentPage(Assignment             $assignment,
                            Question               $question,
                            AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['message'] = 'Current page has been set.';
        try {
            $authorized = Gate::inspect('setCurrentPage', [$assignmentSyncQuestion, $assignment, $question]);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $client = Helper::centrifuge();
            $client->publish("set-current-page-$assignment->id", [
                "assignment_id" => $assignment->id,
                "question_id" => $question->id]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error setting the current page for the clicker assignment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param int $show_answer
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function viewClickerSubmissions(Assignment             $assignment,
                                    Question               $question,
                                    int                    $show_answer,
                                    AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('viewClickerSubmissions', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['answer_shown' => $show_answer]);
            $client = Helper::centrifuge();
            $published_response = ["view_clicker_submissions" => true,
                "show_answer" => $show_answer,
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'qti_answer_json' => ''];

            if ($show_answer
                && $question->qti_json
                && !in_array($question->qti_json_type, ['true_false', 'multiple_choice'])) {
                $published_response['qti_answer_json'] = $question->formatQtiJson('answer_json', $question->qti_json, [], true);
            }
            $client->publish("view-clicker-submissions-$assignment->id",
                $published_response);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error viewing the submissions for this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function endClickerAssessment(Request                $request,
                                  Assignment             $assignment,
                                  Question               $question,
                                  AssignmentSyncQuestion $assignmentSyncQuestion,
                                  Webwork                $webwork): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('endClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $assignment_question = $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();

            $assignment_question->update(['clicker_end' => Carbon::yesterday()]);
            $published_response = ["assignment_id" => $assignment->id,
                "question_id" => $question->id,
                "status" => 'view_and_not_submit',
                "message" => $request->message,
                "time_left" => 0];
            $published_response['show_solution_radio_button'] = $assignment_question->release_solution_when_question_is_closed;
            if ($published_response['show_solution_radio_button']) {
                $published_response['solution_html'] = $request->solution_html ? str_replace('<h2 class="editable">Solution</h2>', '', $request->solution_html) : '';
                if ($published_response['solution_html']) {
                    $published_response['solution_html'] = base64_encode($request->solution_html);
                }
                if ($question->qti_json) {
                    $published_response['qti_answer_json'] = base64_encode($question->formatQtiJson('answer_json', $question->qti_json, [], true));
                }
            }
            $client = Helper::centrifuge();
            $published_response['solution_html'] = base64_encode($request->solution_html);
            $client->publish("clicker-status-$assignment->id", $published_response);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error ending this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param CustomTimeToSubmitRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateCustomClickerTimeToSubmit(CustomTimeToSubmitRequest $request,
                                             Assignment                $assignment,
                                             Question                  $question,
                                             AssignmentSyncQuestion    $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateCustomClickerTimeToSubmit', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $data = $request->validated();
            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_clicker_time_to_submit' => $data['time_to_submit']]);
            $response['type'] = 'success';
            $response['message'] = 'The time to submit has been updated.';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the time to submit for this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateReleaseSolutionWhenQuestionIsClosed(Assignment             $assignment,
                                                       Question               $question,
                                                       AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('updateReleaseSolutionWhenQuestionIsClosed', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $new_release_solution_when_question_is_closed = !$assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first()
                ->release_solution_when_question_is_closed;
            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['release_solution_when_question_is_closed' => $new_release_solution_when_question_is_closed]);
            $response['type'] = $new_release_solution_when_question_is_closed ? 'success' : 'info';
            $can_or_not = $new_release_solution_when_question_is_closed ? 'can' : 'cannot';
            $response['message'] = "Your students $can_or_not view the solution once this question is closed.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the time to submit for this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function resumeClickerAssessment(Request                $request,
                                     Assignment             $assignment,
                                     Question               $question,
                                     AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('resumeClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $client = Helper::centrifuge();
            $client->publish("clicker-status-$assignment->id",
                ["assignment_id" => $assignment->id,
                    "question_id" => $question->id,
                    "status" => 'resumed',
                    'current_time_left' => $request->current_time_left]);
            $clicker_start = CarbonImmutable::now();
            DB::table('assignment_question')->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update([
                    'clicker_start' => $clicker_start,
                    'clicker_end' => $clicker_start->addMilliseconds($request->current_time_left)
                ]);
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error resuming this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function pauseClickerAssessment(Assignment             $assignment,
                                    Question               $question,
                                    AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('pauseClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $client = Helper::centrifuge();
            $client->publish("clicker-status-$assignment->id",
                ["assignment_id" => $assignment->id,
                    "question_id" => $question->id,
                    "status" => 'paused']);
            $clicker_start = CarbonImmutable::now();
            DB::table('assignment_question')->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update([
                    'clicker_start' => $clicker_start,
                    'clicker_end' => $clicker_start->addDay()
                ]);
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error pausing this clicker assessment.  Please contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param StartClickerAssessment $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function restartTimerInClickerAssessment(StartClickerAssessment $request,
                                             Assignment             $assignment,
                                             Question               $question,
                                             AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('restartTimerInClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $clicker_start = CarbonImmutable::now();
            $seconds_padding = 1;
            $interval = CarbonInterval::make($request->time_to_submit);
            $total_seconds = $interval->totalSeconds;
            $clicker_end = $clicker_start->addSeconds($seconds_padding + $total_seconds);
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
                $client->publish("clicker-status-$assignment->id",
                    ["assignment_id" => $assignment->id,
                        "question_id" => $question->id,
                        "status" => 'view_and_submit',
                        "time_left" => $time_left]);

            }

            $response['type'] = 'success';
            $response['time_left'] = $time_left;

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error restarting the timer for this clicker assessment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param StartClickerAssessment $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function addTimeToClickerAssessment(StartClickerAssessment $request,
                                        Assignment             $assignment,
                                        Question               $question,
                                        AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('addTimeToClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $clicker_start = CarbonImmutable::now();
            $seconds_padding = 1;
            $clicker_end = $clicker_start->addSeconds($seconds_padding + $request->time_to_add + $request->time_left);
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
                $client->publish("clicker-status-$assignment->id",
                    ["assignment_id" => $assignment->id,
                        "question_id" => $question->id,
                        "status" => 'view_and_submit',
                        "time_left" => $time_left]);

            }

            $response['type'] = 'success';
            $response['time_left'] = $time_left;

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error adding time to this clicker assessment.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    /**
     * @param StartClickerAssessment $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param FCMNotification $FCMNotification
     * @return array
     * @throws Exception
     */
    public
    function openClickerAssessment(StartClickerAssessment $request,
                                   Assignment             $assignment,
                                   Question               $question,
                                   AssignmentSyncQuestion $assignmentSyncQuestion,
                                   FCMNotification        $FCMNotification): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('openClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            //if (app()->environment('production')) {
            DB::beginTransaction();
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
            DB::commit();
            // }
            $time_to_submit = $request->time_to_submit;
            $assignmentSyncQuestion->startClickerAssessment($FCMNotification, $assignment, $question, $time_to_submit, 5, true);
            $response['type'] = 'success';
            $response['message'] = 'Your students can begin submitting responses.';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error starting this clicker assessment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function resetClickerAssessment(Assignment             $assignment,
                                    Question               $question,
                                    AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('resetClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            DB::beginTransaction();
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
            DB::table('assignment_question')->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update([
                    'clicker_start' => null,
                    'clicker_end' => null
                ]);
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = 'All student submissions have been removed and the clicker timings have been reset.';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error restting this clicker assessment.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    /**
     * @param StartClickerAssessment $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param FCMNotification $FCMNotification
     * @return array
     * @throws Exception
     */
    public
    function startClickerAssessment(StartClickerAssessment $request,
                                    Assignment             $assignment,
                                    Question               $question,
                                    AssignmentSyncQuestion $assignmentSyncQuestion,
                                    FCMNotification        $FCMNotification): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('startClickerAssessment', [$assignmentSyncQuestion, $assignment, $question]);

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $data = $request->validated();
            $time_to_submit = $data['time_to_submit'];
            $reload_student_view = $request->reload_student_view ? $request->reload_student_view : false;
            $assignmentSyncQuestion->startClickerAssessment($FCMNotification, $assignment, $question, $time_to_submit, 5, $reload_student_view);
            $response['type'] = 'success';
            $response['message'] = 'Your students can begin submitting responses.';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error starting this clicker assessment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function getQuestionIdsByAssignment(Assignment $assignment): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['type'] = 'success';
            $response['question_ids'] = json_encode($assignment->questions()->pluck('question_id'));//need to do since it's an array
            $response['question_ids_array'] = $assignment->questions()->pluck('question_id')->toArray();
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment question ids.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param Solution $solution
     * @param PendingQuestionRevision $pendingQuestionRevision
     * @param Webwork $webwork
     * @param IMathAS $IMathAS
     * @return array
     * @throws Exception
     */
    public
    function getQuestionSummaryByAssignment(Request                 $request,
                                            Assignment              $assignment,
                                            Question                $question,
                                            Solution                $solution,
                                            PendingQuestionRevision $pendingQuestionRevision,
                                            Webwork                 $webwork,
                                            IMathAS                 $IMathAS): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $dom = new DOMDocument();
            //Get all assignment questions Question Upload, Solution, Number of Points
            $assignment_questions = DB::table('assignment_question')
                ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                ->leftJoin('assignment_question_learning_tree', 'assignment_question.id', '=', 'assignment_question_learning_tree.assignment_question_id')
                ->leftJoin('learning_trees', 'assignment_question_learning_tree.learning_tree_id', '=', 'learning_trees.id')
                ->where('assignment_id', $assignment->id)
                ->orderBy('order')
                ->select('assignment_question.*',
                    'questions.library',
                    'questions.license',
                    'questions.public',
                    'questions.page_id',
                    'questions.question_editor_user_id',
                    'questions.technology_iframe',
                    'questions.technology',
                    'questions.technology_id',
                    'questions.title',
                    DB::raw('questions.id AS question_id'),
                    'questions.library',
                    'questions.qti_json',
                    'questions.qti_json_type',
                    'questions.forge_source_id',
                    'questions.question_editor_user_id',
                    'questions.answer_html',
                    'questions.solution_html',
                    'questions.webwork_code',
                    'learning_tree_id',
                    'learning_trees.title as learning_tree_title',
                    'learning_trees.user_id AS learning_tree_user_id',
                    'learning_trees.description AS learning_tree_description',
                    'learning_trees.public AS learning_tree_public',
                    'learning_trees.notes AS learning_tree_notes')
                ->get();

            $question_ids = [];
            foreach ($assignment_questions as $key => $value) {
                $question_ids[] = $value->question_id;
            }
            $formative_questions = $question->formativeQuestions($question_ids);
            $h5p_non_adapts = $question->getH5pNonAdapts($question_ids);

            $h5p_non_adapts_by_question_id = [];
            foreach ($h5p_non_adapts as $h5p_non_adapt) {
                $h5p_non_adapts_by_question_id[$h5p_non_adapt->id] = $h5p_non_adapt->h5p_type;
            }
            $uploaded_solutions_by_question_id = $solution->getUploadedSolutionsByQuestionId($assignment, $question_ids);
            $h5p_questions_exists = false;
            $rows = [];
            $pending_question_revisions = $pendingQuestionRevision->getCurrentOrUpcomingByAssignment($assignment);
            $questions_by_question_id = [];
            $questions = Question::whereIn('id', $question_ids)->get();
            $forge_solutions_by_parent_question_id = [];
            foreach ($questions as $value) {
                $questions_by_question_id[$value->id] = $value;
                if ($value->qti_json_type === 'forge' && ($value->answer_html || $value->solution_html)) {
                    $forge_solutions_by_parent_question_id[$value->id] = [
                        'solution_html' => $value->solution_html,
                        'answer_html' => $value->answer_html];
                }
            }
            foreach ($assignment_questions as $key => $value) {
                if (isset($forge_solutions_by_parent_question_id[$value->forge_source_id])) {
                    $assignment_questions[$key]->solution_html = $forge_solutions_by_parent_question_id[$value->forge_source_id]['solution_html'];
                    $assignment_questions[$key]->answer_html = $forge_solutions_by_parent_question_id[$value->forge_source_id]['answer_html'];
                }
            }
            $show_reset_open_ended_button = false;
            $has_open_ended_questions = false;
            foreach ($assignment_questions as $value) {
                $columns = [];
                $columns['title'] = $value->title;
                if ($value->open_ended_submission_type === 'text') {
                    $value->open_ended_submission_type = $value->open_ended_text_editor . ' text';
                }

                $columns['submission'] = Helper::getSubmissionType($value);

                // Format forge submission types
                if ($value->qti_json_type === 'forge') {
                    $columns['submission'] = 'forge (final)';
                } elseif ($value->qti_json_type === 'forge_iteration') {
                    $columns['submission'] = 'forge (draft)';
                } elseif ($value->qti_json_type === 'three_d_model_multiple_choice'){
                    $columns['submission'] = '3D multiple choice';
                }

                $columns['license'] = $value->license;
                $columns['public'] = $value->public;
                $columns['pending_question_revision'] = isset($pending_question_revisions[$value->question_id]);
                $columns['auto_graded_only'] = !($value->technology === 'text' || $value->open_ended_submission_type);
                $columns['is_open_ended'] = $value->open_ended_submission_type !== '0';
                $columns['is_formative_question'] = in_array($value->question_id, $formative_questions);
                $columns['auto_graded_only'] = !($value->technology === 'text' || $value->open_ended_submission_type);
                $columns['is_open_ended'] = $value->open_ended_submission_type !== '0';
                if (in_array($value->open_ended_submission_type, ['audio', 'file', 'text'])) {
                    $show_reset_open_ended_button = true;
                }
                if ($columns['is_open_ended']) {
                    $has_open_ended_questions = true;
                }
                $columns['learning_tree'] = $value->learning_tree_id !== null;
                $columns['learning_tree_id'] = $value->learning_tree_id;
                $columns['learning_tree_user_id'] = $value->learning_tree_user_id;
                $columns['learning_tree_can_edit'] = $value->learning_tree_user_id === request()->user()->id;
                $columns['learning_tree_notes'] = $columns['learning_tree_can_edit'] ? $value->learning_tree_notes : '';
                $columns['learning_tree_public'] = $value->learning_tree_public;
                $columns['learning_tree_description'] = $value->learning_tree_description;
                $columns['points'] = Helper::removeZerosAfterDecimal($value->points);
                $columns['solution'] = $uploaded_solutions_by_question_id[$value->question_id]['original_filename'] ?? false;
                $columns['qti_json'] = $value->qti_json;
                $columns['qti_json_type'] = $value->qti_json_type;
                $columns['forge_source_id'] = $value->forge_source_id;
                $columns['h5p_non_adapt'] = $h5p_non_adapts_by_question_id[$value->question_id] ?? null;
                $columns['imathas_solution'] = $IMathAS->solutionExists($value);
                $columns['solution_file_url'] = $uploaded_solutions_by_question_id[$value->question_id]['solution_file_url'] ?? false;
                $columns['solution_text'] = $uploaded_solutions_by_question_id[$value->question_id]['solution_text'] ?? false;
                $columns['solution_type'] = null;
                $columns['render_webwork_solution'] = $columns['algorithmic'] = $webwork->algorithmicSolution($value);
                $columns['technology_iframe_src'] = null;
                $columns['solution_html'] = '';
                if ($webwork->inCodeSolution($value)) {
                    $value->solution_html = '<div class="mt-section"><h2 class="editable">Solution</h2>' . $webwork->inCodeSolution($value) . '</div>';
                }
                if ($columns['render_webwork_solution'] || $columns['imathas_solution']) {
                    $question_id = $value->question_id;
                    $question = $questions_by_question_id[$question_id];
                    $seed = DB::table('seeds')->where('assignment_id', $assignment->id)
                        ->where('question_id', $question_id)
                        ->where('user_id', Auth::user()->id)
                        ->first();
                    $questions_for_which_seeds_exist = $seed ? [$question_id] : [];
                    $seeds_by_question_id = [];
                    if ($seed) {
                        $seeds_by_question_id[$question->id] = $seed;
                    }
                    $seed = $this->getAssignmentQuestionSeed($assignment, $question, $questions_for_which_seeds_exist, $seeds_by_question_id);
                    $seed = is_object($seed) ? $seed->seed : $seed;
                    $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, true, new DOMDocument(), new JWE());
                    $columns['technology_iframe_src'] = $columns['technology_iframe'] = $this->formatIframeSrc($question['technology_iframe'], rand(1, 1000), $technology_src_and_problemJWT['problemJWT'], []);
                    $columns['solution_type'] = 'html';
                    $columns['problem_jwt'] = $technology_src_and_problemJWT['problemJWT'];
                } else {
                    $columns['solution_html'] = $question->addTimeToS3Files($value->solution_html, $dom);
                    if (!$columns['solution_html']) {
                        $columns['solution_html'] = $question->addTimeToS3Files($value->answer_html, $dom);
                    }
                }
                if ($columns['solution_html']) {
                    $columns['solution_type'] = 'html';
                }
                if ($columns['solution_file_url']) {
                    $columns['solution_type'] = 'q';
                }
                $columns['qti_answer_json'] = '';
                if (!$columns['solution_html'] && $value->qti_json) {
                    $columns['qti_answer_json'] = $question->formatQtiJson('answer_json', $value->qti_json, [], true);
                }
                $columns['order'] = $value->order;
                $columns['question_id'] = $columns['id'] = $value->question_id;
                $columns['clone_source_id'] = $questions_by_question_id[$value->question_id]->clone_source_id;
                $columns['technology'] = $value->technology;
                if ($value->technology === 'h5p') {
                    $h5p_questions_exists = true;
                }

                // For forge_iteration (draft) questions, use the forge_source_id for the ADAPT ID
                $question_id_for_adapt_id = $value->forge_source_id ?? $value->question_id;
                $columns['assignment_id_question_id'] = "{$assignment->id}-{$question_id_for_adapt_id}";

                $columns['library'] = $value->library;
                $columns['question_editor_user_id'] = $value->question_editor_user_id;
                $columns['mindtouch_url'] = "https://{$value->library}.libretexts.org/@go/page/{$value->page_id}";
                $title = $assignment->assessment_type === 'learning tree' ? $value->learning_tree_title : $value->title;
                $columns['title'] = $value->custom_question_title ?: $title;
                $rows[] = $columns;
            }
            $response['show_reset_open_ended_button'] = $show_reset_open_ended_button;
            $response['has_open_ended_questions'] = $has_open_ended_questions;
            $response['assessment_type'] = $assignment->assessment_type;
            $response['formative'] = $assignment->course->formative || $assignment->formative;
            $response['beta_assignments_exist'] = $assignment->betaAssignments() !== [];
            $response['is_beta_assignment'] = $assignment->isBetaAssignment();
            $response['is_alpha_course'] = $assignment->course->alpha === 1;
            $response['solutions_released'] = (bool)$assignment->solutions_released;
            $response['is_commons_course'] = Helper::isCommonsCourse($assignment->course);
            $response['submissions_exist'] = $assignment->hasSubmissionsOrFileSubmissions();
            $response['is_question_weight'] = $assignment->points_per_question === 'question weight';
            $response['is_algorithmic_assignment'] = $assignment->algorithmic;
            $response['course_has_anonymous_users'] = $assignment->course->anonymous_users === 1;
            $response['solutions_availability'] = $assignment->solutions_availability;
            $response['h5p_questions_exist'] = $h5p_questions_exists;
            $response['real_time_with_multiple_attempts'] = $assignment->assessment_type === 'real time' && $assignment->number_of_allowed_attempts !== '1';
            $response['type'] = 'success';
            $response['rows'] = $rows;


        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the questions summary for this assignment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param $assignment
     * @param $uploaded_solutions_by_question_id
     * @param $question_id
     * @return string
     */
    private
    function _getSolutionLink($assignment, $uploaded_solutions_by_question_id, $question_id): string
    {
        return isset($uploaded_solutions_by_question_id[$question_id]) ?
            '<a href="' . Storage::disk('s3')->temporaryUrl("solutions/{$assignment->course->user_id}/{$uploaded_solutions_by_question_id[$question_id]['file']}", now()->addMinutes(360)) . '" target="_blank">' . $uploaded_solutions_by_question_id[$question_id]['original_filename'] . '</a>'
            : 'None';
    }

    /**
     * @param Assignment $assignment
     * @param bool $a11y_redirect
     * @return array
     * @throws Exception
     */
    public
    function getQuestionInfoByAssignment(Assignment $assignment, bool $a11y_redirect): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('view', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['questions'] = [];
            $response['question_files'] = [];
            $response['question_ids'] = [];
            $response['clicker_status'] = [];
            $assignment_question_info = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->join('questions', 'assignment_question.question_id', '=', 'questions.id')
                ->select('assignment_question.*', 'questions.a11y_auto_graded_question_id')
                ->orderBy('order')
                ->get();
            if ($assignment_question_info->isNotEmpty()) {
                foreach ($assignment_question_info as $question_info) {
                    //for getQuestionsByAssignment (internal)
                    $question_info->points = Helper::removeZerosAfterDecimal($question_info->points);
                    if ($a11y_redirect && $question_info->a11y_auto_graded_question_id) {
                        $question_info->question_id = $question_info->a11y_auto_graded_question_id;
                    }
                    $response['questions'][$question_info->question_id] = $question_info;
                    //for the axios call from questions.get.vue
                    $response['question_ids'][] = $question_info->question_id;
                    if ($question_info->open_ended_submission_type === 'file') {
                        $response['question_files'][] = $question_info->question_id;
                    }

                }
            }
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment questions' information.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param SubmissionFile $submissionFile
     * @return array
     * @throws Exception
     */
    public
    function hasNonScoredSubmissionFiles(Assignment             $assignment,
                                         Question               $question,
                                         AssignmentSyncQuestion $assignmentSyncQuestion,
                                         SubmissionFile         $submissionFile)
    {
        $response['type'] = 'error';

        $authorized = Gate::inspect('hasNonScoredSubmissionFiles', [$assignmentSyncQuestion, $assignment]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $message = '';
            $has_non_scored_submission_files = $assignment->course->alpha
                ? $submissionFile->hasNonFakeStudentFileSubmissionsForAssignmentQuestion($assignment->addBetaAssignmentIds(), $question->id, false)
                : $submissionFile->hasNonFakeStudentFileSubmissionsForAssignmentQuestion([$assignment->id], $question->id, false);
            if ($has_non_scored_submission_files) {
                $message = $assignment->course->alpha ?
                    "Either your course or one of the tethered Beta courses has ungraded open-ended submissions for this question."
                    : "This question has ungraded open-ended submissions.";
                $message .= "  If you changed the type, these submissions will be removed.  Would you still like to change the submission type?";
            }
            $response['has_non_scored_submission_files'] = $has_non_scored_submission_files;
            $response['message'] = $message;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error checking whether there are open-ended submissions.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    public
    function updateOpenEndedSubmissionType(UpdateOpenEndedSubmissionType $request,
                                           Assignment                    $assignment,
                                           Question                      $question,
                                           AssignmentSyncQuestion        $assignmentSyncQuestion,
                                           SubmissionFile                $submissionFile): array
    {

        $response['type'] = 'error';

        $authorized = Gate::inspect('updateOpenEndedSubmissionType', [$assignmentSyncQuestion, $assignment, $question, $submissionFile]);

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $assignment_ids = [$assignment->id];
            if ($assignment->course->alpha) {
                $assignment_ids = $assignment->addBetaAssignmentIds();
            }

            $data = $request->validated();
            $open_ended_text_editor = null;
            if ((strpos($data['open_ended_submission_type'], 'text') !== false)) {
                $open_ended_text_editor = str_replace(' text', '', $data['open_ended_submission_type']);
                $data['open_ended_submission_type'] = 'text';

            }
            DB::table('assignment_question')
                ->whereIn('assignment_id', $assignment_ids)
                ->where('question_id', $question->id)
                ->update(['open_ended_submission_type' => $data['open_ended_submission_type'],
                    'open_ended_text_editor' => $open_ended_text_editor]);

            //should have no scores....
            DB::table('submission_files')
                ->whereIn('assignment_id', $assignment_ids)
                ->where('question_id', $question->id)
                ->delete();


            $response['type'] = 'success';
            $response['message'] = "The open-ended submission type has been updated.";
            if ($assignment->assessment_type !== 'delayed' && !($data['open_ended_submission_type'] === 0 || $data['open_ended_submission_type'] === 'no submission, manual grading')) {
                $response['message'] = "You are requesting that your students submit an open-ended response in a <strong>real time assignment</strong>.<br><br>This is not recommended.";
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the open-ended submission type.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    /**
     * @param UpdateCompletionScoringModeRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateCompletionScoringMode(UpdateCompletionScoringModeRequest $request,
                                         Assignment                         $assignment,
                                         Question                           $question,
                                         AssignmentSyncQuestion             $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('update', [$assignmentSyncQuestion, $assignment]);
        $data = $request->validated();

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        if ($assignment->scoring_type !== 'c') {
            $response['message'] = "This option is only available for assignments that are graded for 'completion'.";
        }

        $completion_scoring_mode = Helper::getCompletionScoringMode('c', $data['completion_scoring_mode'], $request->completion_split_auto_graded_percentage);
        $assignment_ids = [$assignment->id];
        if ($assignment->course->alpha) {
            $assignment_ids = $assignment->addBetaAssignmentIds();
        }
        try {
            $is_randomized_assignment = $assignment->number_of_randomized_assessments;
            if ($is_randomized_assignment) {
                DB::table('assignment_question')
                    ->whereIn('assignment_id', $assignment_ids)
                    ->update(['points' => $data['points']]);
                $assignment->default_completion_scoring_mode = $completion_scoring_mode;
                $assignment->save();
                $message = 'Since this is a randomized assignment, all questions now have the same completion scoring mode.';
            } else {
                DB::table('assignment_question')
                    ->whereIn('assignment_id', $assignment_ids)
                    ->where('question_id', $question->id)
                    ->update(['completion_scoring_mode' => $completion_scoring_mode]);
                $message = 'The completion scoring mode has been updated.';
            }
            $response['type'] = 'success';
            $response['message'] = $message;
            $response['completion_scoring_mode'] = $completion_scoring_mode;
            $response['update_completion_scoring_mode'] = $is_randomized_assignment;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the number of points.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param UpdateAssignmentQuestionPointsRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updatePoints(UpdateAssignmentQuestionPointsRequest $request, Assignment $assignment, Question $question, AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('update', [$assignmentSyncQuestion, $assignment]);
        $data = $request->validated();

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }


        $assignment_ids = [$assignment->id];
        if ($assignment->course->alpha) {
            $assignment_ids = $assignment->addBetaAssignmentIds();
        }
        try {
            $is_randomized_assignment = $assignment->number_of_randomized_assessments;
            if ($is_randomized_assignment) {
                DB::table('assignment_question')
                    ->whereIn('assignment_id', $assignment_ids)
                    ->update(['points' => $data['points']]);
                $assignment->default_points_per_question = $data['points'];
                $assignment->save();
                $message = 'Since this is a randomized assignment, all question points have been updated to the same value.';
            } else {
                DB::table('assignment_question')
                    ->whereIn('assignment_id', $assignment_ids)
                    ->where('question_id', $question->id)
                    ->update(['points' => $data['points']]);
                $message = 'The number of points have been updated.';

            }
            $response['type'] = 'success';
            $response['message'] = $message;
            $response['update_points'] = $is_randomized_assignment;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the number of points.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param UpdateAssignmentQuestionWeightRequest $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateWeight(UpdateAssignmentQuestionWeightRequest $request,
                          Assignment                            $assignment,
                          Question                              $question,
                          AssignmentSyncQuestion                $assignmentSyncQuestion)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('update', [$assignmentSyncQuestion, $assignment]);
        $data = $request->validated();

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $is_randomized_assignment = $assignment->number_of_randomized_assessments;
        if ($is_randomized_assignment) {
            $response['message'] = "Weights for randomized assignments cannot be altered.";
            return $response;
        }


        $assignment_ids = [$assignment->id];
        if ($assignment->course->alpha) {
            $assignment_ids = $assignment->addBetaAssignmentIds();
        }
        try {
            DB::beginTransaction();
            DB::table('assignment_question')
                ->whereIn('assignment_id', $assignment_ids)
                ->where('question_id', $question->id)
                ->update(['weight' => $data['weight']]);
            foreach ($assignment_ids as $assignment_id) {
                $assignment_to_update = Assignment::find($assignment_id);
                $assignmentSyncQuestion->updatePointsBasedOnWeights($assignment_to_update);
            }

            $message = "The weight has been updated and the questions' points for the entire assignment have been updated.";
            $response['type'] = 'success';
            $response['message'] = $message;
            $response['updated_points'] = $assignmentSyncQuestion->getQuestionPointsByAssignment($assignment);
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the number of points.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     * @throws Exception
     */
    public
    function store(Assignment             $assignment,
                   Question               $question,
                   AssignmentSyncQuestion $assignmentSyncQuestion,
                   BetaCourseApproval     $betaCourseApproval): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('add', [$assignmentSyncQuestion, $assignment]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
            $response['message'] = "You cannot add a question since there are already submissions and this assignment computes points using question weights.";
            return $response;
        }
        try {
            DB::beginTransaction();
            $assignmentSyncQuestion->store($assignment, $question, $betaCourseApproval);
            $response['message'] = 'The question has been added to the assignment.';
            $response['type'] = 'success';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error adding the question to the assignment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @return array
     * @throws Exception
     */
    public
    function destroy(Assignment             $assignment,
                     Question               $question,
                     AssignmentSyncQuestion $assignmentSyncQuestion,
                     BetaCourseApproval     $betaCourseApproval): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('delete', [$assignmentSyncQuestion, $assignment, $question]);


        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
            $response['message'] = "You cannot remove this question since there are already submissions and this assignment computes points using question weights.";
            return $response;
        }

        try {
            DB::beginTransaction();
            $remove_randomized_assessment_response = $assignmentSyncQuestion->removeRandomizedAssessment($assignment, $question);
            if ($remove_randomized_assessment_response['message']) {
                $response['message'] = $remove_randomized_assessment_response['message'];
                if ($remove_randomized_assessment_response['return_response']) {
                    return $response;
                }
            }
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
            $assignment_question_id = DB::table('assignment_question')->where('question_id', $question->id)
                ->where('assignment_id', $assignment->id)
                ->first()
                ->id;

            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
            $assignment_question_learning_tree = DB::table('assignment_question_learning_tree')
                ->where('assignment_question_id', $assignment_question_id)
                ->first();
            $learning_tree_id = $assignment_question_learning_tree ? $assignment_question_learning_tree->learning_tree_id : 0;//needed for the course approvals piece
            if ($learning_tree_id) {
                $learning_tree_tables = ['learning_tree_node_seeds', 'learning_tree_resets', 'learning_tree_node_submissions'];
                foreach ($learning_tree_tables as $learning_tree_table) {
                    DB::table($learning_tree_table)
                        ->where('assignment_id', $assignment->id)
                        ->where('learning_tree_id', $learning_tree_id)
                        ->delete();
                }
            }
            DB::table('assignment_question_learning_tree')
                ->where('assignment_question_id', $assignment_question_id)
                ->delete();
            if ($question->forge_source_id) {
                $assignment_question = DB::table('assignment_question')->where('question_id', $question->id)
                    ->where('assignment_id', $assignment->id)
                    ->first();
                $assignment_question_id = $assignment_question->id;
                $assignment_question_forge_draft = DB::table('assignment_question_forge_draft')
                    ->where('assignment_question_id', $assignment_question_id)
                    ->first();

                $parent_question = Question::find($question->forge_source_id);
                $parent_assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                    ->where('question_id', $parent_question->id)
                    ->first();
                $forgeSettings = json_decode($parent_assignment_question->forge_settings, true);
                $forgeSettings['drafts'] = array_values(
                    array_filter($forgeSettings['drafts'], function ($draft) use ($assignment_question_forge_draft) {
                        return $draft['uuid'] !== $assignment_question_forge_draft->forge_draft_id;
                    })
                );
                $parent_assignment_question->forge_settings = json_encode($forgeSettings);
                $parent_assignment_question->save();
                DB::table('assignment_question_forge_draft')
                    ->where('assignment_question_id', $assignment_question_id)
                    ->delete();
            } else {
                if ($question->qti_json_type === 'forge') {
                    $parent_assignment_question = AssignmentSyncQuestion::where('assignment_id', $assignment->id)
                        ->where('question_id', $question->id)
                        ->first();
                    DB::table('assignment_question_forge_draft')
                        ->where('assignment_question_id', $assignment_question_id)
                        ->delete();
                    ForgeAssignmentQuestion::where('adapt_assignment_id', $assignment->id)
                        ->where('adapt_question_id', $question->id)
                        ->delete();
                }
            }
            DB::table('assignment_question')->where('question_id', $question->id)
                ->where('assignment_id', $assignment->id)
                ->delete();

            DB::table('randomized_assignment_questions')->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->delete();
            DB::table('question_level_overrides')->where('question_id', $question->id)
                ->where('assignment_id', $assignment->id)
                ->delete();
            DB::table('submission_histories')->where('question_id', $question->id)
                ->where('assignment_id', $assignment->id)
                ->delete();
            DB::table('rubric_points_breakdowns')->where('question_id', $question->id)
                ->where('assignment_id', $assignment->id)
                ->delete();

            $re_order_and_Weight_questions_response = $assignmentSyncQuestion->reOrderAndWeightQuestions($assignment);
            if (isset($re_order_and_Weight_questions_response['updated_points'])) {
                $response['updated_points'] = $re_order_and_Weight_questions_response['updated_points'];
            }

            $betaCourseApproval->updateBetaCourseApprovalsForQuestion($assignment, $question->id, 'remove', $learning_tree_id);

            DB::commit();
            $response['type'] = 'info';
            $response['message'] = 'The question has been removed from the assignment.';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error removing the question from the assignment.  Please try again or contact us for assistance.";
        }

        return $response;

    }


    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param Submission $Submission
     * @param Extension $Extension
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param IMathAS $IMathAS
     * @param Webwork $webwork
     * @return array
     * @throws Exception
     */
    public
    function updateLastSubmittedAndLastResponse(Request                $request,
                                                Assignment             $assignment,
                                                Question               $question,
                                                Submission             $Submission,
                                                Extension              $Extension,
                                                AssignmentSyncQuestion $assignmentSyncQuestion,
                                                IMathAS                $IMathAS,
                                                Webwork                $webwork): array
    {
        /**helper function to get the response info from server side technologies...*/
        $DOMDocument = new DOMDocument();
        $submission = $Submission
            ->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', Auth::user()->id)
            ->first();

        $gave_up = DB::table('can_give_ups')
            ->where('question_id', $question->id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', Auth::user()->id)
            ->where('status', 'gave up')
            ->first();


        $submissions_by_question_id[$question->id] = $submission;
        $question_technologies[$question->id] = Question::find($question->id)->technology;
        $response_info = $this->getResponseInfo($assignment, $Extension, $Submission, $submissions_by_question_id, $question_technologies, $question->id);
        $solution = false;
        $solution_type = false;
        $solution_file_url = false;
        $solution_text = false;

        $qti_answer_json = null;

        ///MAYBE I need to cache it here as well ---- do by user and assignment?????
        /// START
        ///
        $real_time_show_solution = $assignmentSyncQuestion->showRealTimeSolution($assignment, $Submission, $submissions_by_question_id[$question->id], $question);

        $seed = null;
        if ($question->qti_json || in_array($question->technology, ['webwork', 'imathas'])) {
            $seed_info = DB::table('seeds')
                ->where('user_id', request()->user()->id)
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $seed = $seed_info ? $seed_info->seed : null;
        }
        $solution_html = '';
        $answer_html = '';
        $imathas_solution = false;
        $problem_jwt = '';
        //use this to show the table.
        $submission_array = $Submission->getSubmissionArray($assignment, $question, $submission, false);

        $submission = $question->technology === 'imathas' || ($question->technology === 'webwork' && $assignment->assessment_type === 'real time')
            ? $submissions_by_question_id[$question->id]
            : null;

        if (in_array($request->user()->role, [2, 5]) || $real_time_show_solution || $gave_up) {
            $solution_info = DB::table('solutions')
                ->where('question_id', $question->id)
                ->where('user_id', $assignment->course->user_id)
                ->first();
            if ($solution_info) {
                $solution = $solution_info->original_filename;
                $solution_type = $solution_info->type;
                $solution_file_url = $solution_info->file;
                $solution_text = $solution_info->text;
            }
            if ($question->qti_json) {
                $qti_answer_json = $question->formatQtiJson('answer_json', $question['qti_json'], $seed, true);
            }
            if ($webwork->inCodeSolution($question)) {
                $question->solution_html = $webwork->inCodeSolution($question);
            }
            if (($question->solution_html || $question->answer_html) && !$solution) {
                $solution_type = 'html';
                $solution_html = $question->addTimeToS3Files($question->solution_html, $DOMDocument, false);
                $answer_html = $question->addTimeToS3Files($question->answer_html, $DOMDocument, false);

            }
            if ($question->technology === 'webwork') {
                $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, true, new DOMDocument(), new JWE());
                $technology_iframe_src = $this->formatIframeSrc($question['technology_iframe'], rand(1, 1000), $technology_src_and_problemJWT['problemJWT'], $response_info['session_jwt']);
                $solution_type = 'html';
            }
            if ($question->technology === 'imathas' && $IMathAS->solutionExists($question)) {
                $imathas_solution = true;
                $solution_type = 'html';
            }
        }

        if ($question->technology === 'imathas') {

            $custom_claims = [];
            $custom_claims['stuanswers'] = $Submission->getStudentResponse($submission, 'imathas');
            $custom_claims['raw'] = [];
            if ($assignment->assessment_type === 'real time' && $submission) {
                $custom_claims['raw'] = json_decode($submission) ?
                    json_decode($submission->submission)->raw
                    : [];

            }
            if ($imathas_solution) {
                $custom_claims['imathas']['includeans'] = true;
            }

            $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, true, new DOMDocument(), new JWE(), $custom_claims);
            $technology_iframe_src = $this->formatIframeSrc($question['technology_iframe'], rand(1, 1000), $technology_src_and_problemJWT['problemJWT'], $response_info['session_jwt']);
            if ($imathas_solution) {
                $problem_jwt = $technology_src_and_problemJWT['problemJWT'];
            }
        }


        $qti_json = $question->qti_json
            ? $question->formatQtiJson('question_json', $question['qti_json'], $seed, $assignment->assessment_type === 'real time' || Auth::user()->role === 2, $response_info['student_response'])
            : null;


        $last_submitted = $response_info['last_submitted'] === 'N/A'
            ? 'N/A'
            : $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($response_info['last_submitted'],
                $request->user()->time_zone, 'M d, Y g:i:s a');

        $answered_correctly = null;
        if ($assignment->assessment_type === 'real time' && $qti_json) {
            $qti_json_arr = json_decode($question['qti_json'], 1);
            if (isset($qti_json_arr['questionType'])
                && $qti_json_arr['questionType'] === 'submit_molecule'
                && json_decode($question['qti_json'])) {
                $answered_correctly = (bool)$Submission->computeScoreFromSubmitMolecule(json_decode($question['qti_json']), $response_info['student_response'])['proportion_correct'];
            }

        }
        return ['last_submitted' => $last_submitted,
            'gave_up' => $gave_up,
            'student_response' => $response_info['student_response'],
            'answered_correctly' => $answered_correctly,
            'submission_count' => $response_info['submission_count'],
            'submission_score' => Helper::removeZerosAfterDecimal($response_info['submission_score']),
            'late_penalty_percent' => $response_info['late_penalty_percent'],
            'late_question_submission' => $response_info['late_question_submission'],
            'answered_correctly_at_least_once' => $response_info['answered_correctly_at_least_once'],
            'session_jwt' => $response_info['session_jwt'],
            'qti_answer_json' => $qti_answer_json,
            'qti_json' => $qti_json,
            'solution' => $solution,
            'imathas_solution' => $imathas_solution,
            'problem_jwt' => $problem_jwt,
            'solution_file_url' => $solution_file_url,
            'solution_text' => $solution_text,
            'solution_type' => $solution_type,
            'answer_html' => $answer_html,
            'technology_iframe_src' => $technology_iframe_src ?? null,
            'solution_html' => $solution_html,
            'submission_array' => $submission_array,
            'completed_all_assignment_questions' => $assignmentSyncQuestion->completedAllAssignmentQuestions($assignment),
            'too_many_submissions' => $submission ? $submission->tooManySubmissions($assignment, $submission) : false
        ];

    }

    /**
     * @param Assignment $assignment
     * @param $Extension
     * @param Submission $Submission
     * @param $submissions_by_question_id
     * @param $question_technologies
     * @param $question_id
     * @return array
     * @throws Exception
     */
    public
    function getResponseInfo(Assignment $assignment,
                                        $Extension,
                             Submission $Submission,
                                        $submissions_by_question_id,
                                        $question_technologies,
                                        $question_id): array
    {
        //$Extension will be the model when returning the information to the user at the individual level
        //it will be the actual date when doing it for the assignment since I just need to do it once
        $student_response = $question_technologies[$question_id] === 'qti' ? '' : 'N/A';
        $correct_response = null;
        $late_penalty_percent = 0;
        $session_jwt = '';
        $submission_score = 0;
        $last_submitted = 'N/A';
        $submission_count = 0;
        $late_question_submission = false;
        $answered_correctly_at_least_once = 0;
        $submitted_work = null;
        $submitted_work_at = null;

        if (isset($submissions_by_question_id[$question_id])) {
            $submission = $submissions_by_question_id[$question_id];
            $decoded_submission = json_decode($submission->submission, 1);
            if ($decoded_submission && isset($decoded_submission['sessionJWT'])) {
                $session_jwt = $decoded_submission['sessionJWT'];
            }
            $last_submitted = $submission->updated_at;
            $submission_score = $submission->score;
            $submission_count = $submission->submission_count;
            $late_penalty_percent = $Submission->latePenaltyPercent($assignment, Carbon::parse($last_submitted));
            $late_question_submission = $this->isLateSubmission($Extension, $assignment, Carbon::parse($last_submitted));
            $answered_correctly_at_least_once = $submission->answered_correctly_at_least_once;
            $submitted_work = $submission->submitted_work;
            $submitted_work_at = $submission->submitted_work_at;
            $student_response = $Submission->getStudentResponse($submission, $question_technologies[$question_id]);

        }
        return compact('student_response',
            'submitted_work',
            'submitted_work_at',
            'correct_response',
            'submission_score',
            'last_submitted',
            'submission_count',
            'late_penalty_percent',
            'late_question_submission',
            'answered_correctly_at_least_once',
            'session_jwt');

    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Submission $Submission
     * @param SubmissionFile $SubmissionFile
     * @param Extension $Extension
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Enrollment $enrollment
     * @param Question $Question
     * @param Solution $solution
     * @param PendingQuestionRevision $pendingQuestionRevision
     * @param IMathAS $IMathAS
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public
    function getQuestionsToView(Request                 $request,
                                Assignment              $assignment,
                                Submission              $Submission,
                                SubmissionFile          $SubmissionFile,
                                Extension               $Extension,
                                AssignmentSyncQuestion  $assignmentSyncQuestion,
                                Enrollment              $enrollment,
                                Question                $Question,
                                Solution                $solution,
                                PendingQuestionRevision $pendingQuestionRevision,
                                IMathAS                 $IMathAS): array
    {


        $start_time = microtime(true);
        $response['type'] = 'error';
        $response['is_instructor_logged_in_as_student'] = request()->user()->instructor_user_id && request()->user()->role === 3;
        $response['is_instructor_with_anonymous_view'] = Helper::hasAnonymousUserSession()
            && request()->user()->role === 2
            && $assignment->course->user_id !== request()->user()->id;
        $authorized = Gate::inspect('view', $assignment);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $enrollment = $enrollment->where('course_id', $assignment->course->id)
                ->where('user_id', $request->user()->id)
                ->first();

            $a11y_redirect = $enrollment && $enrollment->a11y_redirect;

            //determine "true" due date to see if submissions were late
            $extension = $Extension->getAssignmentExtensionByUser($assignment, Auth::user());
            $due_date_considering_extension = $assignment->assignToTimingByUser('due');

            if ($extension) {
                if (Carbon::parse($extension) > Carbon::parse($assignment->assignToTimingByUser('due'))) {
                    $due_date_considering_extension = $extension;
                }
            }


            $assignment_question_info = $this->getQuestionInfoByAssignment($assignment, $a11y_redirect);
            $question_ids = [];
            $points = [];
            $weights = [];
            $webwork = new Webwork();
            if (!$assignment_question_info['questions']) {
                $response['type'] = 'success';
                $response['questions'] = [];
                return $response;
            }


            $user_as_collection = collect([Auth::user()]);
            // $submission_texts_by_question_and_user = $SubmissionText->getUserAndQuestionTextInfo($assignment, 'allStudents', $user_as_collection);


            $submission_files_by_question_and_user = $SubmissionFile->getUserAndQuestionFileInfo($assignment, 'allStudents', $user_as_collection);
            $submission_files = [];

            //want to just pull out the single user which will be returned for each question
            foreach ($submission_files_by_question_and_user as $key => $submission) {
                $submission_files[] = $submission[0];

            }

            $submission_files_by_question_id = [];
            foreach ($submission_files as $submission_file) {
                $submission_files_by_question_id[$submission_file['question_id']] = $submission_file;
            }
            $learning_trees_by_question_id = [];
            $open_ended_submission_types = [];
            $open_ended_text_editors = [];
            $open_ended_default_texts = [];
            $completion_scoring_modes = [];
            $clicker_status = [];
            $clicker_time_left = [];
            $iframe_showns = [];
            $formative_questions = [];
            $custom_question_titles = [];
            $report_toggles_by_question_id = [];
            if (in_array($request->user()->role, [2, 5])) {
                $formative_questions = $Question->formativeQuestions($assignment->questions->pluck('id')->toArray());
            }

            foreach ($assignment_question_info['questions'] as $question) {
                $question_ids[$question->question_id] = $question->question_id;
                $open_ended_submission_types[$question->question_id] = $question->open_ended_submission_type;
                $open_ended_text_editors[$question->question_id] = $question->open_ended_text_editor;
                $open_ended_default_texts[$question->question_id] = $question->open_ended_default_text;
                $completion_scoring_modes[$question->question_id] = $question->completion_scoring_mode;
                $iframe_showns[$question->question_id] = ['attribution_information_shown_in_iframe' => (boolean)$question->attribution_information_shown_in_iframe,
                    'submission_information_shown_in_iframe' => (boolean)$question->submission_information_shown_in_iframe,
                    'assignment_information_shown_in_iframe' => (boolean)$question->assignment_information_shown_in_iframe];
                $custom_question_titles[$question->question_id] = $question->custom_question_title;
                $points[$question->question_id] = Helper::removeZerosAfterDecimal($question->points);
                $weights[$question->question_id] = Helper::removeZerosAfterDecimal($question->weight);
                $clicker_status[$question->question_id] = $assignmentSyncQuestion->getFormattedClickerStatus($assignment->solutions_released, $question);
                if (!$question->clicker_start) {
                    $clicker_time_left[$question->question_id] = 0;
                } else {
                    $start = Carbon::now();
                    $end = Carbon::parse($question->clicker_end);
                    $num_seconds = 0;
                    if ($end > $start) {
                        $num_seconds = $end->diffInMilliseconds($start);
                    }
                    $clicker_time_left[$question->question_id] = $num_seconds;
                }

            }
            $question_info = DB::table('questions')
                ->select('*')
                ->whereIn('questions.id', $question_ids)
                ->get();
            $question_technologies = [];
            $question_editor_user_ids = [];
            foreach ($question_info as $question) {
                $question_technologies[$question->id] = $question->technology;
                if ($question->question_editor_user_id) {
                    $question_editor_user_ids[] = $question->question_editor_user_id;
                }
            }
            $forge_solutions_by_parent_question_id = [];
            foreach ($question_info as $value) {
                if ($value->qti_json_type === 'forge' && ($value->answer_html || $value->solution_html)) {
                    $forge_solutions_by_parent_question_id[$value->id] = [
                        'solution_html' => $value->solution_html,
                        'answer_html' => $value->answer_html];
                }
            }
            foreach ($assignment->questions as $key => $value) {
                if (isset($forge_solutions_by_parent_question_id[$value->forge_source_id])) {
                    $assignment->questions[$key]->solution_html = $forge_solutions_by_parent_question_id[$value->forge_source_id]['solution_html'];
                    $assignment->questions[$key]->answer_html = $forge_solutions_by_parent_question_id[$value->forge_source_id]['answer_html'];
                }
            }


            $question_editor_names_by_question_editor_user_id = [];
            if ($request->user()->role !== 3) {
                $question_editor_names = DB::table('users')
                    ->whereIn('id', $question_editor_user_ids)
                    ->select('id', DB::raw('CONCAT(first_name, " " , last_name) AS question_editor_name'))
                    ->get();
                foreach ($question_editor_names as $question_editor_name) {
                    $question_editor_names_by_question_editor_user_id[$question_editor_name->id] = $question_editor_name->question_editor_name;
                }
            }


            $h5p_non_adapts = $Question->getH5pNonAdapts($question_ids);

            $question_h5p_non_adapt = [];
            foreach ($h5p_non_adapts as $question) {
                $question_h5p_non_adapt[$question->id] = $question->h5p_type;
            }


            //these question_ids come from the assignment
            //in case an instructor accidentally assigns the same problem twice I added in assignment_id
            $submissions = DB::table('submissions')
                ->whereIn('question_id', $question_ids)
                ->where('user_id', Auth::user()->id)
                ->where('assignment_id', $assignment->id)
                ->get();

            $at_least_one_submission = DB::table('submissions')
                ->join('users', 'submissions.user_id', '=', 'users.id')
                ->where('assignment_id', $assignment->id)
                ->where('users.fake_student', 0)
                ->select('question_id')
                ->groupBy('question_id')
                ->get();

            $at_least_one_submission_file = DB::table('submission_files')
                ->join('users', 'submission_files.user_id', '=', 'users.id')
                ->where('assignment_id', $assignment->id)
                ->where('users.fake_student', 0)
                ->select('question_id')
                ->groupBy('question_id')
                ->get();

            $can_give_ups = DB::table('can_give_ups')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', auth()->user()->id)
                ->where('status', 'can give up')
                ->select('question_id')
                ->get()
                ->pluck('question_id')
                ->toArray();
            $gave_ups = DB::table('can_give_ups')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', auth()->user()->id)
                ->where('status', 'gave up')
                ->select('question_id')
                ->get()
                ->pluck('question_id')
                ->toArray();

            $report_toggles = DB::table('report_toggles')
                ->whereIn('question_id', $question_ids)
                ->get();
            foreach ($report_toggles as $report_toggle) {
                $report_toggles_by_question_id[$report_toggle->question_id] = [
                    'section_scores' => $report_toggle->section_scores,
                    'comments' => $report_toggle->comments,
                    'criteria' => $report_toggle->criteria
                ];
            }
            $questions_with_at_least_one_submission = [];
            foreach ($at_least_one_submission as $question) {
                $questions_with_at_least_one_submission[] = $question->question_id;
            }
            foreach ($at_least_one_submission_file as $question) {
                $questions_with_at_least_one_submission[] = $question->question_id;
            }


            $submissions_by_question_id = [];
            if ($submissions) {
                foreach ($submissions as $key => $value) {
                    $submissions_by_question_id[$value->question_id] = $value;
                }
            }
            //if they've already explored the learning tree, then we can let them view it right at the start
            $learning_tree_titles_by_question_id = [];
            if ($assignment->assessment_type === 'learning tree') {
                $learning_trees_with_at_least_one_node_submission = DB::table('learning_tree_node_submissions')
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->select('learning_tree_id')
                    ->groupBy('learning_tree_id')
                    ->get()
                    ->pluck('learning_tree_id')
                    ->toArray();
                $learning_tree_ids = [];
                $number_of_learning_tree_paths_by_question_id = [];
                $assignment_learning_trees = $assignment->learningTrees();
                foreach ($assignment_learning_trees as $learning_tree) {
                    $learning_tree_ids[] = $learning_tree->learning_tree_id;
                }

                $learning_trees = LearningTree::whereIn('id', $learning_tree_ids)->get();
                foreach ($learning_trees as $learningTree) {
                    $number_of_learning_tree_paths_by_question_id[$learningTree->root_node_question_id] = count($learningTree->finalQuestionIds());
                    $learning_tree_titles_by_question_id[$learningTree->root_node_question_id] = $learningTree->title;
                }

                $number_learning_tree_resets_available = DB::table('learning_tree_resets')
                    ->where('user_id', $request->user()->id)
                    ->where('assignment_id', $assignment->id)
                    ->get();

                foreach ($number_learning_tree_resets_available as $value) {
                    $number_learning_tree_resets_available_by_learning_tree_id[$value->learning_tree_id] = $value->number_resets_available;
                }
                foreach ($assignment_learning_trees as $value) {
                    $learning_trees_by_question_id[$value->question_id] = $value->learning_tree_id;
                    $number_resets_available_by_question_id[$value->question_id] = $number_learning_tree_resets_available_by_learning_tree_id[$value->learning_tree_id] ?? 0;
                    $at_least_one_learning_tree_node_submission_by_question_id[$value->question_id] = in_array($value->learning_tree_id, $learning_trees_with_at_least_one_node_submission);
                    $number_of_successful_paths_for_a_reset[$value->question_id] = $value->number_of_successful_paths_for_a_reset;
                }
            }
            $mean_and_std_dev_by_question_submissions = $this->getMeanAndStdDevByColumn('submissions', 'assignment_id', [$assignment->id], 'question_id');
            $mean_and_std_dev_by_submission_files = $this->getMeanAndStdDevByColumn('submission_files', 'assignment_id', [$assignment->id], 'question_id');


            $seeds = DB::table('seeds')
                ->whereIn('question_id', $question_ids)
                ->where('user_id', Auth::user()->id)
                ->where('assignment_id', $assignment->id)
                ->get();

            $seeds_by_question_id = [];
            if ($seeds) {
                foreach ($seeds as $key => $value) {
                    $seeds_by_question_id[$value->question_id] = $value->seed;
                }
            }
            $questions_for_which_seeds_exist = array_keys($seeds_by_question_id);

            $uploaded_solutions_by_question_id = $solution->getUploadedSolutionsByQuestionId($assignment, $question_ids);

            $submission_score_overrides = DB::table('submission_score_overrides')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', Auth::user()->id)
                ->get();

            $submission_score_overrides_by_question_id = [];
            foreach ($submission_score_overrides as $submission_score_override) {
                $submission_score_overrides_by_question_id[$submission_score_override->question_id] = $submission_score_override->score;
            }
            $domd = new DOMDocument();
            $JWE = new JWE();

            $randomly_chosen_questions = [];
            if ($assignment->number_of_randomized_assessments && $request->user()->role == 3) {
                $randomly_chosen_questions = $this->getRandomlyChosenQuestions($assignment, $request->user());
            }

            $shown_hints = DB::table('shown_hints')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', Auth::user()->id)
                ->get('question_id')
                ->pluck('question_id')
                ->toArray();

            $assignment_level_override = DB::table('assignment_level_overrides')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $request->user()->id)
                ->first();
            $compiled_pdf_override = DB::table('compiled_pdf_overrides')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $request->user()->id)
                ->first();
            $question_level_overrides = DB::table('question_level_overrides')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $request->user()->id)
                ->get('question_id')
                ->pluck('question_id')
                ->toArray();

            $assignment_questions = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->get();
            foreach ($assignment_questions as $assignment_question) {
                $assignment_questions_by_question_id[$assignment_question->question_id] = $assignment_question;
            }
            //get the correct revisions
            $question_revision_ids = [];
            foreach ($assignment_question_info['questions'] as $question) {
                if ($question->question_revision_id) {
                    $question_revision_ids[] = $question->question_revision_id;
                }
            }


            $question_revisions = DB::table('assignment_question')
                ->join('question_revisions', 'assignment_question.question_revision_id', '=', 'question_revisions.id')
                ->select('question_revisions.*')
                ->whereIn('question_revisions.id', $question_revision_ids)
                ->get();

            $question_revisions_by_question_id = [];
            foreach ($question_revisions as $question_revision) {
                $question_revisions_by_question_id[$question_revision->question_id] = $question_revision;
                $question_revision_ids[] = $question_revision->id;
            }
            $question_media_uploads = DB::table('question_media_uploads')
                ->select('s3_key', 'show_captions')
                ->whereIn('question_revision_id', $question_revision_ids)
                ->get();
            $show_captions = [];
            foreach ($question_media_uploads as $question_media_upload) {
                $show_captions[$question_media_upload->s3_key] = $question_media_upload->show_captions;
            }
            session()->put('show_captions', $show_captions);
            //for current or upcoming assignments get pending question revisions

            $pending_question_revisions_by_question_id = $pendingQuestionRevision->getCurrentOrUpcomingByAssignment($assignment);
            $latest_question_revisions_by_question_id = $assignmentSyncQuestion->getlatestQuestionRevisionsByAssignment($question_ids);


            if ($a11y_redirect) {
                $questions_with_auto_graded_redirect = Question::whereIn('id', $assignment->questions->pluck('id')->toArray())
                    ->whereNotNull('a11y_auto_graded_question_id')
                    ->get();
                $auto_graded_redirect_question_ids = [];
                foreach ($questions_with_auto_graded_redirect as $question) {
                    $auto_graded_redirect_question_ids[] = $question->a11y_auto_graded_question_id;
                }
                $auto_grade_redirect_questions = Question::whereIn('id', $auto_graded_redirect_question_ids)->get();
                $auto_redirect_questions_by_id = [];
                foreach ($auto_grade_redirect_questions as $question) {
                    $auto_redirect_questions_by_id[$question->id] = $question;
                }
                foreach ($assignment->questions as $key => $question) {
                    if ($question->a11y_auto_graded_question_id) {
                        $assignment->questions[$key] = $auto_redirect_questions_by_id[$question->a11y_auto_graded_question_id];
                    }
                }
            }

            foreach ($assignment->questions as $key => $question) {
                if ($assignment->number_of_randomized_assessments
                    && $request->user()->role == 3
                    && !$request->user()->fake_student
                    && !in_array($question->id, $randomly_chosen_questions)) {
                    $assignment->questions->forget($key);
                    continue;
                }

                $assignment->questions[$key]['question_editor_name'] = $question_editor_names_by_question_editor_user_id[$question->question_editor_user_id] ?? 'None provided';

                $assignment->questions[$key]['can_submit_work'] =
                    $assignment->can_submit_work &&
                    isset($assignment_questions_by_question_id[$question->id]->can_submit_work_override) &&
                    $assignment_questions_by_question_id[$question->id]->can_submit_work_override !== null
                        ? $assignment_questions_by_question_id[$question->id]->can_submit_work_override
                        : $assignment->can_submit_work;
                $assignment->questions[$key]['question_revision_id'] = isset($question_revisions_by_question_id[$question->id]) ? $question_revisions_by_question_id[$question->id]->id : null;
                $assignment->questions[$key]['question_revision_number'] = isset($question_revisions_by_question_id[$question->id]) ? $question_revisions_by_question_id[$question->id]->revision_number : null;
                $assignment->questions[$key]['question_revision_id_latest'] = $latest_question_revisions_by_question_id[$question->id] ?? null;
                $assignment->questions[$key]['time_to_submit'] = isset($assignment_questions_by_question_id[$question->id]) ? $assignment_questions_by_question_id[$question->id]->custom_clicker_time_to_submit : null;
                $assignment->questions[$key]['release_solution_when_question_is_closed'] = isset($assignment_questions_by_question_id[$question->id]) ? $assignment_questions_by_question_id[$question->id]->release_solution_when_question_is_closed : 0;
                $assignment->questions[$key]['question_reason_for_edit'] = null;
                $assignment->questions[$key]['question_revision_number'] = 0;


                $assignment->questions[$key]['pending_question_revision'] = in_array(request()->user()->role, [2, 5]) && isset($pending_question_revisions_by_question_id[$question->id])
                    ? $pending_question_revisions_by_question_id[$question->id]
                    : null;

                if ($assignment->questions[$key]['question_revision_id']) {
                    $question = $question->updateWithQuestionRevision($question_revisions_by_question_id[$question->id]);
                    $assignment->questions[$key]['question_revision_number'] = $question_revisions_by_question_id[$question->id]->revision_number;
                }


                $iframe_technology = true;//assume there's a technology --- will be set to false once there isn't

                $assignment->questions[$key]['report'] = $question->question_type === 'report';
                if ($question->question_type === 'report') {
                    $rubric_categories = $assignmentSyncQuestion->rubricCategoriesByAssignmentAndQuestion($assignment, $question);
                    if ($request->user()->role === 3) {
                        $report_toggles_info = $report_toggles_by_question_id[$question->id] ?? ['section_scores' => 0, 'comments' => 0, 'criteria' => 0];
                        $reportToggle = new ReportToggle();
                        $assignment->questions[$key]['rubric_categories'] = $reportToggle->getShownReportItems($rubric_categories, $report_toggles_info);
                    } else {
                        $assignment->questions[$key]['rubric_categories'] = $rubric_categories;
                    }
                }
                $assignment->questions[$key]['is_formative_question'] = $request->user()->role === 2 && in_array($question->id, $formative_questions);
                $assignment->questions[$key]['loaded_question_updated_at'] = $question->updated_at->timestamp;
                $assignment->questions[$key]['library'] = $question->library;
                $assignment->questions[$key]['page_id'] = $question->page_id;
                $assignment->questions[$key]['common_question_text'] = $assignment->common_question_text;
                $title = $assignment->assessment_type === 'learning tree' ? $learning_tree_titles_by_question_id[$question->id] : $question->title;
                $assignment->questions[$key]['title'] = $custom_question_titles[$question->id] ?: $title;
                $assignment->questions[$key]['h5p_non_adapt'] = $question_h5p_non_adapt[$question->id] ?? null;

                $assignment->questions[$key]['author'] = $question->author;
                $assignment->questions[$key]['source_url'] = $request->user()->role === 3 ? '' : $question->source_url;
                $assignment->questions[$key]['has_at_least_one_submission'] = in_array($question->id, $questions_with_at_least_one_submission);
                $assignment->questions[$key]['private_description'] = $request->user()->role === 2
                    ? $question->private_description
                    : '';
                $assignment->questions[$key]['license'] = $question->license;
                $assignment->questions[$key]['attribution'] = $question->attribution;
                $assignment->questions[$key]['assignment_information_shown_in_iframe'] = $iframe_showns[$question->id]['assignment_information_shown_in_iframe'];
                $assignment->questions[$key]['submission_information_shown_in_iframe'] = $iframe_showns[$question->id]['submission_information_shown_in_iframe'];
                $assignment->questions[$key]['attribution_information_shown_in_iframe'] = $iframe_showns[$question->id]['attribution_information_shown_in_iframe'];
                $assignment->questions[$key]['clicker_status'] = $clicker_status[$question->id];
                $assignment->questions[$key]['clicker_time_left'] = $clicker_time_left[$question->id];
                $assignment->questions[$key]['points'] = Helper::removeZerosAfterDecimal(round($points[$question->id], 4));
                $assignment->questions[$key]['weight'] = $weights[$question->id];
                $assignment->questions[$key]['mindtouch_url'] = $request->user()->role === 3
                    ? ''
                    : "https://{$question->library}.libretexts.org/@go/page/{$question->page_id}";

                $response_info = $this->getResponseInfo($assignment, $extension, $Submission, $submissions_by_question_id, $question_technologies, $question->id);
                $assignment->questions[$key]['answered_correctly_at_least_once'] = $response_info['answered_correctly_at_least_once'] ?? 0;
                $student_response = $response_info['student_response'];
                $correct_response = $response_info['correct_response'];
                $submitted_work = $response_info['submitted_work']
                    ? Storage::disk('s3')->temporaryUrl("submitted-work/{$assignment->id}/{$response_info['submitted_work']}", now()->addDay())
                    : null;
                $submitted_work_at = $response_info['submitted_work_at']
                    ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime(
                        $response_info['submitted_work_at'],
                        $request->user()->time_zone, 'M d, Y \a\t g:i:s a')
                    : null;
                $submission_score = Helper::removeZerosAfterDecimal($response_info['submission_score']);
                $assignment->questions[$key]['discuss_it_satisfied_all_requirements'] = null;
                if ($question->qti_json_type === 'discuss_it') {
                    $discussionComment = new DiscussionComment();
                    $discuss_it_satisfied_requirements =
                        $discussionComment->satisfiedRequirements($assignment,
                            $question->id,
                            request()->user()->id,
                            $assignmentSyncQuestion);
                    $assignment->questions[$key]['discuss_it_satisfied_all_requirements'] = $discuss_it_satisfied_requirements['satisfied_all_requirements'] ?? null;
                }
                $last_submitted = $response_info['last_submitted'];
                $submission_count = $response_info['submission_count'];
                $late_question_submission = $response_info['late_question_submission'];

                $assignment->questions[$key]['question_rubric_exists'] = isset($assignment_questions_by_question_id[$question->id]) && $question->rubric !== null;
                $custom_rubric = $assignment_questions_by_question_id[$question->id]->custom_rubric ?? null;
                $assignment->questions[$key]['rubric'] =
                    isset($assignment_questions_by_question_id[$question->id])
                    && ($assignment_questions_by_question_id[$question->id]->use_existing_rubric || $assignment_questions_by_question_id[$question->id]->use_existing_rubric === null)
                        ? $question->rubric
                        : $custom_rubric;


                $assignment->questions[$key]['use_existing_rubric'] = isset($assignment_questions_by_question_id[$question->id]) && ($assignment_questions_by_question_id[$question->id]->use_existing_rubric || $assignment_questions_by_question_id[$question->id]->use_existing_rubric === null);

                $assignment->questions[$key]['overriding_rubric'] = isset($assignment_questions_by_question_id[$question->id]) && $assignment_questions_by_question_id[$question->id]->custom_rubric;
                $assignment->questions[$key]['student_response'] = $student_response;
                $assignment->questions[$key]['submitted_work'] = $submitted_work;
                $assignment->questions[$key]['submitted_work_at'] = $submitted_work_at;
                $assignment->questions[$key]['open_ended_submission_type'] = $open_ended_submission_types[$question->id];
                $assignment->questions[$key]['open_ended_text_editor'] = $open_ended_text_editors[$question->id];
                $assignment->questions[$key]['open_ended_default_text'] = $open_ended_default_texts[$question->id];
                $assignment->questions[$key]['completion_scoring_mode'] = $completion_scoring_modes[$question->id];

                $real_time_show_solution = isset($submissions_by_question_id[$question->id]) && $assignmentSyncQuestion->showRealTimeSolution($assignment, $Submission, $submissions_by_question_id[$question->id], $question);
                $can_give_up = in_array($question->id, $can_give_ups);
                $gave_up = in_array($question->id, $gave_ups);
                $show_solution = (!Helper::isAnonymousUser() || !Helper::hasAnonymousUserSession())
                    &&
                    ($assignment->solutions_released
                        || $real_time_show_solution
                        || $gave_up
                        || (isset($clicker_status[$question->id]) && $clicker_status[$question->id] === 'view_and_not_submit'));
//don't show the solution if they have an override
                if ($assignment_level_override || $compiled_pdf_override || in_array($question->id, $question_level_overrides)) {
                    $show_solution = false;
                }
                if ($show_solution) {
                    $assignment->questions[$key]['correct_response'] = $correct_response;
                }

                $assignment->questions[$key]['can_give_up'] = $can_give_up;
                $render_webwork_solution = $webwork->algorithmicSolution($assignment->questions[$key]);
                $assignment->questions[$key]['algorithmic'] = $webwork->algorithmicSolution($assignment->questions[$key]);
                $imathas_solution = ($show_solution || in_array(request()->user()->role, [2, 5]))
                    && $IMathAS->solutionExists($assignment->questions[$key]);
                $assignment->questions[$key]['solution_exists'] = isset($uploaded_solutions_by_question_id[$question->id])
                    || $assignment->questions[$key]->answer_html
                    || $assignment->questions[$key]->solution_html
                    || $render_webwork_solution
                    || $imathas_solution;
                $assignment->questions[$key]['render_webwork_solution'] = $render_webwork_solution; //for the assignments summary page
                $assignment->questions[$key]['imathas_solution'] = $imathas_solution;
                if ($assignment->show_scores) {
                    $assignment->questions[$key]['submission_score'] = $submission_score;
                    $assignment->questions[$key]['submission_z_score'] = isset($mean_and_std_dev_by_question_submissions[$question->id])
                        ? $this->computeZScore($submission_score, $mean_and_std_dev_by_question_submissions[$question->id])
                        : 'N/A';
                }
                if ($assignment->assessment_type === 'learning tree') {
                    $assignment->questions[$key]['learning_tree_id'] = $learning_trees_by_question_id[$question->id];
                    $assignment->questions[$key]['number_resets_available'] = $number_resets_available_by_question_id[$question->id] ?? 0;
                    $assignment->questions[$key]['at_least_one_learning_tree_node_submission'] = $at_least_one_learning_tree_node_submission_by_question_id[$question->id] ?? false;
                    $assignment->questions[$key]['number_of_learning_tree_paths'] = $number_of_learning_tree_paths_by_question_id[$question->id] ?? 0;
                    $assignment->questions[$key]['number_of_successful_paths_for_a_reset'] = $number_of_successful_paths_for_a_reset[$question->id] ?? 0;
                }

                $assignment->questions[$key]['last_submitted'] = ($last_submitted !== 'N/A')
                    ? $this->convertUTCMysqlFormattedDateToHumanReadableLocalDateAndTime($last_submitted, Auth::user()->time_zone, 'n/j/y g:ia')
                    : $last_submitted;

                $assignment->questions[$key]['late_penalty_percent'] = ($last_submitted !== 'N/A')
                    ? $Submission->latePenaltyPercent($assignment, Carbon::parse($last_submitted))
                    : 0;

                $assignment->questions[$key]['late_question_submission'] = ($last_submitted !== 'N/A')
                    ?
                    $late_question_submission
                    : false;

                $assignment->questions[$key]['submission_count'] = $submission_count;


                $submission_file = $submission_files_by_question_id[$question->id] ?? false;


                if ($submission_file) {

                    $assignment->questions[$key]['open_ended_submission_type'] = $submission_file['open_ended_submission_type'];
                    $assignment->questions[$key]['submission'] = $submission_file['submission'];
                    $assignment->questions[$key]['submission_file_exists'] = (boolean)$assignment->questions[$key]['submission'];
                    $formatted_submission_file_info = $this->getFormattedSubmissionFileInfo($submission_file, $assignment->id, $this);
                    $assignment->questions[$key]['submission_file_late_penalty_percent'] = $formatted_submission_file_info['applied_late_penalty'];
                    $assignment->questions[$key]['original_filename'] = $formatted_submission_file_info['original_filename'];
                    $assignment->questions[$key]['date_submitted'] = $formatted_submission_file_info['date_submitted'];

                    $assignment->questions[$key]['late_file_submission'] = $formatted_submission_file_info['date_submitted'] !== 'N/A' && Carbon::parse($submission_file['date_submitted'])->greaterThan(Carbon::parse($due_date_considering_extension));

                    if ($assignment->show_scores) {
                        $submission_files_score = $formatted_submission_file_info['submission_file_score'];
                        $assignment->questions[$key]['date_graded'] = $formatted_submission_file_info['date_graded'];
                        $assignment->questions[$key]['submission_file_score'] = $submission_files_score;
                        $assignment->questions[$key]['grader_id'] = $submission_files_by_question_id[$question->id]['grader_id'];
                        $assignment->questions[$key]['submission_file_z_score'] = isset($mean_and_std_dev_by_submission_files[$question->id])
                            ? $this->computeZScore($submission_files_score, $mean_and_std_dev_by_submission_files[$question->id])
                            : 'N/A';
                    }
                    if ($assignment->show_scores) {
                        $assignment->questions[$key]['file_feedback_exists'] = $formatted_submission_file_info['file_feedback_exists'];
                        $assignment->questions[$key]['file_feedback'] = $formatted_submission_file_info['file_feedback'];
                        $assignment->questions[$key]['text_feedback'] = $formatted_submission_file_info['text_feedback'];

                        $assignment->questions[$key]['file_feedback_url'] = null;
                        $formatted_submission_file_info['file_feedback'] = null;
                        if ($formatted_submission_file_info['file_feedback_exists']) {
                            $assignment->questions[$key]['file_feedback_url'] = $formatted_submission_file_info['file_feedback_url'];
                            $assignment->questions[$key]['file_feedback_type'] = (pathinfo($formatted_submission_file_info['file_feedback'], PATHINFO_EXTENSION) === 'mpga') ? 'audio' : 'file';
                        }
                    }
                    //for PDFS we can set the page
                    $page = $submission_files_by_question_id[$question->id]['page']
                        ? "#page=" . $submission_files_by_question_id[$question->id]['page']
                        : '';

                    $assignment->questions[$key]['submission_file_url'] = $formatted_submission_file_info['temporary_url'] . $page;
                    $assignment->questions[$key]['submission_file_page'] = $submission_files_by_question_id[$question->id]['page'] ?: null;


                }

                if ($assignment->show_scores) {
                    $total_score = $submission_score_overrides_by_question_id[$question->id]
                        ?? floatval($assignment->questions[$key]['submission_file_score'] ?? 0)
                        + floatval($assignment->questions[$key]['submission_score'] ?? 0);
                    $assignment->questions[$key]['total_score'] = round(min(floatval($points[$question->id]), $total_score), 4);
                    $assignment->questions[$key]['submission_score_override'] = $submission_score_overrides_by_question_id[$question->id] ?? null;
                }
                $local_solution_exists = isset($uploaded_solutions_by_question_id[$question->id]['solution_file_url']);
                $assignment->questions[$key]['answer_html'] = !$local_solution_exists && (in_array(request()->user()->role, [2, 5]) || $show_solution) ? $question->addTimeToS3Files($assignment->questions[$key]->answer_html, $domd) : null;

                if ($webwork->inCodeSolution($assignment->questions[$key])) {
                    $assignment->questions[$key]->solution_html = $webwork->inCodeSolution($assignment->questions[$key]);
                }
                $assignment->questions[$key]['solution_html'] = !$local_solution_exists && (in_array(request()->user()->role, [2, 5]) || $show_solution) ? $question->addTimeToS3Files($assignment->questions[$key]->solution_html, $domd) : null;
                $seed = in_array($question->technology, ['webwork', 'imathas', 'qti'])
                    ? $this->getAssignmentQuestionSeed($assignment, $question, $questions_for_which_seeds_exist, $seeds_by_question_id)
                    : '';
                if ($show_solution || in_array(request()->user()->role, [2, 5])) {
                    $assignment->questions[$key]['solution'] = $uploaded_solutions_by_question_id[$question->id]['original_filename'] ?? false;
                    $assignment->questions[$key]['solution_type'] = $uploaded_solutions_by_question_id[$question->id]['solution_type'] ?? false;
                    $assignment->questions[$key]['solution_file_url'] = $uploaded_solutions_by_question_id[$question->id]['solution_file_url'] ?? false;
                    $assignment->questions[$key]['solution_text'] = $uploaded_solutions_by_question_id[$question->id]['solution_text'] ?? false;

                    if ($imathas_solution || (($assignment->questions[$key]['answer_html'] || $assignment->questions[$key]['solution_html']) && !$assignment->questions[$key]['solution_type'])) {
                        $assignment->questions[$key]['solution_type'] = 'html';
                    }
                    $assignment->questions[$key]['qti_answer_json'] = $question->qti_json ? $question->formatQtiJson('answer_json', $question->qti_json, $seed, true) : null;
                    if (in_array($question->qti_json_type, ['forge', 'forge_iteration']) && $assignment->questions[$key]['answer_html']) {
                        $qti_answer_json = json_decode($assignment->questions[$key]['qti_answer_json'], 1);
                        $qti_answer_json['solution_html'] = $assignment->questions[$key]['answer_html'];
                        $assignment->questions[$key]['qti_answer_json'] = json_encode($qti_answer_json);
                    }
                }
                if (request()->user()->role === 3) {
                    $assignment->questions[$key]['webwork_code'] = null;
                }
                if ($show_solution
                    && request()->user()->role === 3
                    && ($render_webwork_solution || $imathas_solution)) {
                    //needed for the student assignment summary page which uses the get questions page to get the question info
                    $assignment->questions[$key]['solution_type'] = 'html';
                }


                $assignment->questions[$key]['qti_json'] = $question->qti_json ? $question->formatQtiJson('question_json', $question->qti_json, $seed, $assignment->assessment_type === 'real time' || $request->user()->role === 2, $student_response) : null;

                $assignment->questions[$key]['text_question'] = Auth::user()->role === 2 || (Auth::user()->role === 3 && $a11y_redirect === 'text_question')
                    ? $question->addTimeToS3Files($assignment->questions[$key]->text_question, $domd)
                    : null;
                $shown_hint = $assignment->can_view_hint && (Auth::user()->role === 2 || (Auth::user()->role === 3 && in_array($question->id, $shown_hints)));
                $assignment->questions[$key]['shown_hint'] = $shown_hint;
                $assignment->questions[$key]['hint_exists'] = $assignment->questions[$key]->hint !== null && $assignment->questions[$key]->hint !== '';
                $assignment->questions[$key]['hint'] = $shown_hint
                    ? $question->addTimeToS3Files($assignment->questions[$key]->hint, $domd)
                    : null;

                $assignment->questions[$key]['notes'] = Auth::user()->role === 2 ? $question->addTimeToS3Files($assignment->questions[$key]->notes, $domd) : null;

                $custom_claims = [];
                if ($question->technology === 'imathas' && isset($submissions_by_question_id[$question->id])) {
                    $custom_claims['stuanswers'] = $Submission->getStudentResponse($submissions_by_question_id[$question->id], 'imathas');

                    $custom_claims['raw'] = [];
                    $custom_claims['raw'] = ($assignment->assessment_type === 'real time' || $show_solution) && json_decode($submissions_by_question_id[$question->id]->submission) ?
                        json_decode($submissions_by_question_id[$question->id]->submission)->raw
                        : [];
                }
                $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $question, $seed, $show_solution, $domd, $JWE, $custom_claims);
                $technology_src = $technology_src_and_problemJWT['technology_src'];
                $problemJWT = $technology_src_and_problemJWT['problemJWT'];
                $assignment->questions[$key]['problem_jwt'] = $problemJWT;

                $sessionJWT = $response_info['session_jwt'];
                $a11y_question_html = '';
                $a11y_technology_question = null;
                $a11y_problemJWT = null;
                $a11y_technology_src = '';


                if (Auth::user()->role === 2 && $question->a11y_auto_graded_question_id) {
                    $a11y_technology_question = Question::find($question->a11y_auto_graded_question_id);

                    if (in_array($a11y_technology_question->technology, ['webwork', 'imathas'])) {
                        $a11y_technology_question->technology_iframe = $a11y_technology_question->getTechnologyIframeFromTechnology($a11y_technology_question->technology, $a11y_technology_question->technology_id);
                    }
                    if (in_array($a11y_technology_question->technology, ['webwork', 'imathas', 'qti'])) {
                        if (Auth::user()->role === 2) {
                            //there could be multiple questions on the page which could cause an issue so I'm just regenerating each time
                            $seed = $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $a11y_technology_question);
                        } else {
                            $a11y_seed = DB::table('seeds')
                                ->where('question_id', $a11y_technology_question->id)
                                ->where('user_id', Auth::user()->id)
                                ->where('assignment_id', $assignment->id)
                                ->first();
                            if ($a11y_seed) {
                                $seed = $a11y_seed->seed;
                            } else {
                                $seed = $this->getAssignmentQuestionSeed($assignment, $a11y_technology_question, $questions_for_which_seeds_exist, $seeds_by_question_id);
                            }
                        }
                    }
                    if (in_array($a11y_technology_question->technology, ['webwork', 'imathas'])) {
                        $a11y_technology_src_and_problemJWT = $a11y_technology_question->getTechnologySrcAndProblemJWT($request, $assignment, $a11y_technology_question, $seed, $show_solution, $domd, $JWE);
                        $a11y_technology_src = $a11y_technology_src_and_problemJWT['technology_src'];
                        $a11y_problemJWT = $a11y_technology_src_and_problemJWT['problemJWT'];
                    }
                    if ($a11y_technology_question->technology === 'qti') {
                        $assignment->questions[$key]['a11y_qti_json'] = $a11y_technology_question->qti_json
                            ? $a11y_technology_question->formatQtiJson('question_json', $a11y_technology_question->qti_json, $seed, $assignment->assessment_type === 'real time', $student_response)
                            : null;
                    }

                }

                if (Auth::user()->role === 3) {
                    //these will be populated above
                    $assignment->questions[$key]->a11y_auto_graded_question_id = null;
                    if (!$assignment->question_titles_shown) {
                        $order = $key + 1;
                        $assessment_type = $assignment->assessment_type === 'learning tree' ? 'Tree' : 'Question';
                        $assignment->questions[$key]['title'] = "$assessment_type #$order";
                    }

                }
                if ((Auth::user()->role === 2 || (Auth::user()->role === 3 && $a11y_redirect === 'text_question')) && $question->text_question) {
                    $a11y_question_html = $question->text_question;
                }
                $assignment->questions[$key]->a11y_question_html = $a11y_question_html;

                if ($technology_src) {
                    $assignment->questions[$key]->iframe_id = $this->createIframeId();
                    //don't return if not available yet!
                    $assignment->questions[$key]->technology_iframe = (Helper::isAnonymousUser()) || !(Auth::user()->role === 3 && !Auth::user()->fake_student) || ($assignment->shown && time() >= strtotime($assignment->assignToTimingByUser('available_from')))
                        ? $this->formatIframeSrc($question['technology_iframe'], $assignment->questions[$key]->iframe_id, $problemJWT, $sessionJWT)
                        : '';
                    $assignment->questions[$key]->technology_src = Auth::user()->role === 2 ? $technology_src : '';
                    if ($a11y_technology_question && in_array($a11y_technology_question->technology, ['h5p', 'webwork', 'imathas'])) {

                        $assignment->questions[$key]->a11y_technology_iframe = !(Auth::user()->role === 3 && !Auth::user()->fake_student) || ($assignment->shown && time() >= strtotime($assignment->assignToTimingByUser('available_from')))
                            ? $this->formatIframeSrc($a11y_technology_question->technology_iframe, $assignment->questions[$key]->iframe_id, $a11y_problemJWT)
                            : '';
                        $assignment->questions[$key]->a11y_technology_src = Auth::user()->role === 2 ? $a11y_technology_src : '';
                        if (Auth::user()->role === 3 && $enrollment->a11y_redirect === 'a11y_technology') {
                            $assignment->questions[$key]->technology_iframe = $a11y_technology_src;
                            $assignment->questions[$key]->technology_src = $a11y_technology_src;

                        }
                    }
                }
                $assignment->questions[$key]->submission_array = isset($submissions_by_question_id[$question->id]) && in_array($question->technology, ['imathas', 'webwork'])
                    ? $Submission->getSubmissionArray($assignment, $question, $submissions_by_question_id[$question->id], false, $assignment_questions_by_question_id[$question->id])
                    : [];
                ///////DO I EVEN NEED THE LINE BELOW???????TODO
                /*   $assignment->questions[$key]->answer_qti_json = $assignment->questions[$key]->technology === 'qti' && $show_solution
                       ? $question->formatQtiJson('answer_json',$question['qti_json'], $seed, true)
                       : null;*/


                //Frankenstein type problems

                $assignment->questions[$key]->non_technology_iframe_src = $this->getHeaderHtmlIframeSrc($question, $assignment->questions[$key]['question_revision_number']);
                $assignment->questions[$key]->has_auto_graded_and_open_ended = $iframe_technology && $assignment->questions[$key]['open_ended_submission_type'] !== '0';
            }

            $response['type'] = 'success';
            $response['questions'] = $assignment->questions->values();
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time);
            DB::table('execution_times')->insert([
                'method' => 'getQuestionsToView',
                'parameters' => '{"assignment_id": ' . $assignment->id . ', "user_id": ' . $request->user()->id . '}',
                'execution_time' => round($execution_time, 2),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the assignment questions.  Please try again or contact us for assistance.";
        }
        return $response;
    }


    function getRandomlyChosenQuestions(Assignment $assignment, User $user)
    {
        $randomly_chosen_questions = RandomizedAssignmentQuestion::where('assignment_id', $assignment->id)
            ->where('user_id', $user->id)
            ->select('question_id')
            ->get()
            ->pluck('question_id')
            ->toArray();
        if (!$randomly_chosen_questions) {
            $numbers = range(0, count($assignment->questions) - 1);
            shuffle($numbers);
            $randomly_chosen_question_keys = array_slice($numbers, 0, $assignment->number_of_randomized_assessments);
            $question_ids = $assignment->questions->pluck('id')->toArray();
            foreach ($randomly_chosen_question_keys as $randomly_chosen_question_key) {
                $question_id = $question_ids[$randomly_chosen_question_key];
                $randomizedAssignmentQuestion = new RandomizedAssignmentQuestion();
                $randomizedAssignmentQuestion->assignment_id = $assignment->id;
                $randomizedAssignmentQuestion->question_id = $question_id;
                $randomizedAssignmentQuestion->user_id = $user->id;
                $randomizedAssignmentQuestion->save();
                $randomly_chosen_questions[] = $question_id;
            }
        }
        return $randomly_chosen_questions;
    }


    /**
     * @throws Exception
     */
    public
    function getAssignmentQuestionSeed(Assignment $assignment,
                                       Question   $question,
                                       array      $questions_for_which_seeds_exist,
                                       array      $seeds_by_question_id)
    {
        if (in_array($question->id, $questions_for_which_seeds_exist)) {
            $seed = $seeds_by_question_id[$question->id];
        } else {
            $seed = $this->createSeedByTechnologyAssignmentAndQuestion($assignment, $question);
            DB::table('seeds')->insert([
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
                'user_id' => Auth::user()->id,
                'seed' => $seed,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

        }
        return $seed;
    }


    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function updateCustomTitle(Request                $request,
                               Assignment             $assignment,
                               Question               $question,
                               AssignmentSyncQuestion $assignmentSyncQuestion,
                               Forge                  $forge): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('updateCustomTitle', [$assignmentSyncQuestion, $assignment, $question]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $custom_question_title = $request->custom_question_title ? $request->custom_question_title : null;
            DB::beginTransaction();
            DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['custom_question_title' => $custom_question_title]);
            $forge_assignment_question = ForgeAssignmentQuestion::where('adapt_assignment_id', $assignment->id)
                ->where('adapt_question_id', $question->id)
                ->first();
            if ($forge_assignment_question) {
                $secret = DB::table('key_secrets')
                    ->where('key', 'forge')
                    ->first()
                    ->secret;
                $central_identity_id = $request->user()->central_identity_id;
                $http_response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer $secret",
                ])->withBody("$custom_question_title-$assignment->name", 'application/json')
                    ->post(config('services.antecedent.url') . "/api/adapt/question/$forge_assignment_question->forge_question_id/user/$central_identity_id/title");
                if (!$http_response->successful()) {
                    DB::rollback();
                    $response['message'] = "Forge error updating question title: " . $http_response->json()['message'];
                    return $response;
                }
            }
            DB::commit();
            $response['type'] = 'success';
            $response['message'] = "The question title has been updated for this assignment.";
            $response['original_question_title'] = $question->title;
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error updating the question title.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function getRubricCategoriesByAssignmentAndQuestion(Assignment             $assignment,
                                                        Question               $question,
                                                        AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $authorized = Gate::inspect('getRubricCategoriesByAssignmentAndQuestion', [$assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $rubric_categories = $assignmentSyncQuestion->rubricCategoriesByAssignmentAndQuestion($assignment, $question);
            $response['rubric_categories'] = $rubric_categories;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the rubric categories.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param PendingQuestionRevision $pendingQuestionRevision
     * @return array
     * @throws Exception
     */
    public
    function updateToLatestRevision(Request                 $request,
                                    Assignment              $assignment,
                                    Question                $question,
                                    AssignmentSyncQuestion  $assignmentSyncQuestion,
                                    PendingQuestionRevision $pendingQuestionRevision): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('updateToLatestRevision', [$assignmentSyncQuestion, $assignment, $question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            if (!$request->understand_student_submissions_removed) {
                $response['message'] = "You must confirm that you understand that student submissions will be removed.";
                return $response;
            }
            DB::beginTransaction();
            if ($request->latest_question_revision_id) {
                //override it when on the page where you have all possible revisions
                $question_revision_id = $request->latest_question_revision_id;
            } else {
                $pending_question_revision = $pendingQuestionRevision
                    ->where('assignment_id', $assignment->id)
                    ->where('question_id', $question->id)
                    ->first();
                $question_revision_id = $pending_question_revision->question_revision_id;
            }

            $assignmentSyncQuestion->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->update(['question_revision_id' => $question_revision_id]);
            $pendingQuestionRevision->where('assignment_id', $assignment->id)->where('question_id', $question->id)->delete();
            $student_emails_associated_with_submissions = [];

            if ($assignmentSyncQuestion->questionHasSomeTypeOfRealStudentSubmission($assignment, $question)) {
                $student_emails_associated_with_submissions = $assignmentSyncQuestion->studentEmailsAssociatedWithSomeTypeOfStudentSubmission($assignment, $question);
                $student_submissions_message = "In addition, the student submissions have been removed and the scores have been updated.";
            } else {
                $student_submissions_message = "There were no student submissions to this question so no student scores were updated.";
            }
            $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
            Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);

            DB::commit();

            $response['message'] = "The question has been updated.  $student_submissions_message";
            $response['student_emails_associated_with_submissions'] = $student_emails_associated_with_submissions;
            $response['type'] = 'success';

        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update to the latest question revision.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    private
    function _rubricsAreTheSame(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        foreach ($array1 as $key => $item1) {
            if (!isset($array2[$key])) {
                return false;
            }

            $item2 = $array2[$key];

            $title1 = $item1['title'] ?? null;
            $title2 = $item2['title'] ?? null;


            if (
                $this->_normalize($title1) !== $this->_normalize($title2)
            ) {
                return false;
            }
        }

        return true;
    }

    private
    function _normalize($value): string
    {
        return $value === null ? '' : (string)$value;
    }


}
