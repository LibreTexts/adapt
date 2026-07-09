<?php

namespace App\Http\Controllers;


use App\CurrentQuestionEditor;
use App\Forge;
use App\Assignment;
use App\AssignmentSyncQuestion;
use App\AssignmentTemplate;
use App\AssignmentTopic;
use App\BetaCourseApproval;
use App\Course;
use App\Helpers\Helper;
use App\Helpers\Accounting;
use App\Http\Requests\StoreQuestionRequest;
use App\IMathAS;
use App\Jobs\GenerateFlashcardAudioVTT;
use App\Jobs\GenerateFlashcardTTS;
use App\Jobs\InitProcessTranscribe;
use App\Jobs\ProcessValidateQtiFile;
use App\JWE;
use App\Libretext;
use App\PendingQuestionRevision;
use App\QtiJob;
use App\Question;
use App\QuestionMediaUpload;
use App\QuestionRevision;
use App\RubricCategory;
use App\RubricTemplate;
use App\SavedQuestionsFolder;
use App\Section;
use App\Services\WebworkMacroService;
use App\Tag;
use App\Traits\AssignmentProperties;
use App\Traits\DateFormatter;
use App\AssignmentQuestionLearningTree;
use App\LearningTree;
use App\LearningTreeHistory;
use App\RefreshQuestionRequest;
use App\User;
use App\Webwork;
use App\WebworkAttachment;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Traits\IframeFormatter;
use App\Traits\LibretextFiles;
use App\Exceptions\Handler;
use Exception;

use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

use Symfony\Component\HttpFoundation\StreamedResponse;


class QuestionController extends Controller
{
    use IframeFormatter;
    use LibretextFiles;
    use DateFormatter;
    use AssignmentProperties;

    protected $flashcard_keys = [
        'Title*',
        'Front*',
        'Front Text Language*',
        'Back*',
        'Back Text Language*',
        'Hint',
        'Tags',
        'Folder*',
        'Public*',
        'Author*',
        'License*',
    ];
    /**
     * @var string[]
     */
    private $webwork_keys;
    /**
     * @var string[]
     */
    private $advanced_keys;
    /**
     * @var array|string[]
     */
    private $bow_tie_keys;
    /**
     * @var array|string[]
     */
    private $case_study_notes_keys;
    private $case_study_notes;

    public function __construct()
    {
        $this->webwork_keys = ['Public*',
            'Folder*',
            'Title*',
            'File Path*',
            'Assignment',
            'Template',
            'Topic',
            'Author*',
            'License*',
            'License Version',
            'Source URL',
            'Tags'];
        $this->case_study_notes_keys = ['Common Question Text',
            'Name',
            'Gender',
            'Age',
            'DOB',
            'Code Status',
            'Allergies',
            'Weight',
            'BMI',
            'History and Physical',
            'Progress Notes',
            'Vital Signs',
            'Lab/Diagnostic Results',
            'Provider Orders',
            'MAR',
            'Handoff Report'];
        $this->bow_tie_keys = ['Public*',
            'Folder*',
            'Title*',
            'Assignment',
            'Template',
            'Topic',
            'Author*',
            'License*',
            'License Version',
            'Source URL',
            'Tags',
            'Question Body*',
            'Actions to Take*',
            'Potential Conditions*',
            'Parameters to Monitor*',
            'Correct Feedback',
            'Incorrect Feedback'
        ];

        $this->advanced_keys = ["Question Type*",
            "Public*",
            'Folder*',
            "Title*",
            'Assignment',
            "Template",
            'Topic',
            "Open-Ended Content",
            "Auto-Graded Technology",
            "Technology ID/File Path",
            "Author*",
            "License*",
            "License Version",
            "Source URL",
            "Tags",
            "Text Question",
            "Answer",
            "Solution",
            "Hint"
        ];

    }


    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function storeFlashcardTts(Request $request, Question $question): array
    {

        $authorized = Gate::inspect('storeFlashcardTts', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $revision = QuestionRevision::where('question_id', $question->id)
            ->orderByDesc('id')
            ->first();
        $revision_id = $revision ? $revision->id : 0;
        $side = $request->input('side');
        $dir = "uploads/flashcard-tts/{$question->id}/$revision_id";
        $filename = "{$side}.mp3";
        $qti_json = $revision_id ? json_decode($revision->qti_json, 1) : json_decode($question->qti_json, 1);
        $s3Key = "$dir/$filename";
        try {
            Storage::disk('s3')->putFileAs(
                $dir,
                $request->file('audio'),
                $filename
            );

            $ttsKeyField = $side === 'front' ? 'frontTtsS3Key' : 'backTtsS3Key';

            $qti_json['card'][$ttsKeyField] = $s3Key;
            $qti_json = json_encode($qti_json);
            DB::beginTransaction();
            if ($revision) {
                $revision->qti_json = $qti_json;
                $revision->save();
            }
            $question->qti_json = $qti_json;
            $question->save();
            DB::commit();
            $tts_url = Storage::disk('s3')->temporaryUrl(
                $s3Key,
                Carbon::now()->addHours(2)
            );

            $response['type'] = 'success';
            $response['tts_url'] = $tts_url;

        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not re-record the audio.  Please contact support.";
        }
        return $response;
    }

    public function validAccountingJournalEntries(): array
    {
        return Accounting::validAccountingJournalEntries();
    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function deleteAttachment(Request  $request,
                                     Question $question): array
    {
        try {
            $response['type'] = 'error';
            $question_id = $request->question_id;
            $s3_key = $request->s3_key;
            $authorized = Gate::inspect('deleteAttachment', [$question, $question_id, $s3_key]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $file_exists_in_revision = false;
            if ($request->question_id) {
                $revisions = QuestionRevision::where('question_id', $request->question_id);
                foreach ($revisions as $revision) {
                    $attachments = $revision->question_attachments;
                    if ($attachments) {
                        $attachments = json_decode($attachments);
                        foreach ($attachments as $attachment) {
                            if ($attachment->s3_key === $s3_key) {
                                $file_exists_in_revision = true;
                            }
                        }
                    }
                }
                $question = Question::find($request->question_id);
                $attachments = json_decode($question->attachments);
                foreach ($attachments as $key => $attachment) {
                    if ($attachment->s3_key === $s3_key) {
                        unset($attachments[$key]);
                    }
                }
                $question->attachments = json_encode($attachments);

                // delete not working!
                $question->save();
            }
            if (!$file_exists_in_revision) {
                if (Storage::disk('s3')->exists("uploads/question-attachments/$s3_key")) {
                    Storage::disk('s3')->delete("uploads/question-attachments/$s3_key");
                }
            }
            $response['type'] = 'info';
            $response['message'] = "$request->filename has been removed from this question.";

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not delete the attachment.  Please contact support.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param int $assignment_id
     * @param int $question_id
     * @param string $s3_key
     * @param Question $question
     * @return array|Application|RedirectResponse|Redirector
     * @throws Exception
     */
    public function downloadAttachment(Request  $request,
                                       int      $assignment_id,
                                       int      $question_id,
                                       string   $s3_key,
                                       Question $question)
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('downloadAttachment', [$question, $assignment_id, $question_id, $s3_key]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $s3_key = "uploads/question-attachments/$request->s3_key";
            if (Storage::disk('s3')->exists($s3_key)) {
                // Generate a temporary signed URL (valid for 5 minutes)
                $url = Storage::disk('s3')->temporaryUrl(
                    $s3_key,
                    now()->addMinutes(5),
                    [
                        'ResponseContentDisposition' => 'attachment; filename="' . basename($s3_key) . '"'
                    ]
                );

                // Redirect to the signed URL
                return redirect($url);
            } else {
                $response['message'] = "File not found.";
            }
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not download the attachment.  Please contact support.";


        }
        return $response;

    }

    /**
     * @param string $jwt
     * @return array
     * @throws Exception
     */
    public function imathasSolution(string $jwt)
    {
        $response['type'] = 'error';
        $data = [
            'ajax' => 1,
            'problemJWT' => $jwt
        ];
        try {
            $url = 'https://' . Helper::iMathASDomain() . '/imathas/adapt/embedq2.php';
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $ch_response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Curl error when trying to retrieve IMathAS solution: ' . curl_error($ch));

            }

            curl_close($ch);
            $response_object = json_decode($ch_response);
            if (!is_object($response_object)) {
                throw new Exception($ch_response);
            }
            $response['message'] = $response_object->disp->soln;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not retrieve the IMathAS solution.  Please contact support.";

        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function getQuestionTypes(Request $request, Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getQuestionTypes', $question);
        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response['question_types'] = $question->getQuestionTypes($request->question_ids);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the question types.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function getQtiAnswerJson(Request $request, Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getQtiAnswerJson', $question);
        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            if (!json_decode($request->qti_json)) {
                $response['message'] = "The answer cannot be viewed.  Please verify that the all parts to the question have been created.";
                return $response;
            }
            $response['qti_answer_json'] = $question->formatQtiJson('answer_json', $request->qti_json, [], true);
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the answer.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param BetaCourseApproval $betaCourseApproval
     * @param RubricCategory $rubricCategory
     * @param QuestionMediaUpload $questionMediaUpload
     * @param AssignmentQuestionLearningTree $assignmentQuestionLearningTree
     * @return array
     * @throws Exception
     */
    public function clone(Request                        $request,
                          AssignmentSyncQuestion         $assignmentSyncQuestion,
                          BetaCourseApproval             $betaCourseApproval,
                          RubricCategory                 $rubricCategory,
                          QuestionMediaUpload            $questionMediaUpload,
                          AssignmentQuestionLearningTree $assignmentQuestionLearningTree): array
    {
        $response['type'] = 'error';
        $cloned_question = [];
        $question_id = $request->question_id;
        $clone_source = Question::find($question_id);
        if (!$clone_source) {
            $response['message'] = "Question $question_id does not exist.";
            return $response;
        }

        try {
            $learning_tree_id = $request->learning_tree_id ? $request->learning_tree_id : 0;
            $authorized = Gate::inspect('clone', [$clone_source, $learning_tree_id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $question_editor_user_id = $request->question_editor_user_id;


            $assignment = null;
            $assignment_id = $request->assignment_id;
            $acting_as = $request->acting_as;
            $clone_to_folder_id = $request->clone_to_folder_id;

            if ($acting_as === 'admin' && !Helper::isAdmin()) {
                $response['message'] = 'You are not Admin.';
                return $response;
            }
            $question_editor = User::find($question_editor_user_id);
            switch ($acting_as) {
                case('admin'):
                    if (!in_array($question_editor->role, [2, 5])) {
                        $response['message'] = "The new owner must be an instructor or non-instructor editor.";
                        return $response;
                    }

                    $saved_questions_folder = DB::table('saved_questions_folders')
                        ->where('user_id', $question_editor_user_id)
                        ->where('type', 'my_questions')
                        ->where('name', 'Cloned Questions')
                        ->first();
                    if (!$saved_questions_folder) {
                        $savedQuestionFolder = new SavedQuestionsFolder();
                        $savedQuestionFolder->type = 'my_questions';
                        $savedQuestionFolder->name = 'Cloned Questions';
                        $savedQuestionFolder->user_id = $question_editor_user_id;
                        $savedQuestionFolder->save();
                        $saved_questions_folder_id = $savedQuestionFolder->id;
                    } else {
                        $saved_questions_folder_id = $saved_questions_folder->id;
                    }

                    $clone_to_folder_id = $saved_questions_folder_id;
                    break;
                default:
                    if ($question_editor_user_id !== request()->user()->id) {
                        $response['message'] = "You cannot clone a question to someone else's account.";
                        return $response;
                    }

                    // When cloning a learning tree, auto-place root question in "Cloned Questions"
                    if ($learning_tree_id && !$clone_to_folder_id) {
                        $cloned_questions_folder = DB::table('saved_questions_folders')
                            ->where('user_id', request()->user()->id)
                            ->where('type', 'my_questions')
                            ->where('name', 'Cloned Questions')
                            ->first();
                        if (!$cloned_questions_folder) {
                            $newFolder = new SavedQuestionsFolder();
                            $newFolder->user_id = request()->user()->id;
                            $newFolder->type = 'my_questions';
                            $newFolder->name = 'Cloned Questions';
                            $newFolder->save();
                            $clone_to_folder_id = $newFolder->id;
                        } else {
                            $clone_to_folder_id = $cloned_questions_folder->id;
                        }
                    }

                    if ($assignment_id) {
                        $assignment = Assignment::find($assignment_id);
                        if (!$assignment) {
                            $response['message'] = "That assignment does not exist.";
                            return $response;
                        }
                        $assignment_owner_user_id = $assignment->course->user_id;
                        if ($assignment_owner_user_id !== request()->user()->id) {
                            $response['message'] = "You cannot clone the question to an assignment that you don't own.";
                            return $response;
                        }
                        $assignment = Assignment::find($assignment_id);
                    }

                    if (!$clone_to_folder_id) {
                        $response['message'] = "Please choose a folder.";
                        return $response;
                    }
                    $saved_questions_folder = DB::table('saved_questions_folders')
                        ->where('id', $clone_to_folder_id)
                        ->where('type', 'my_questions')
                        ->first();
                    if (!$saved_questions_folder) {
                        $response['message'] = "That folder does not exist.";
                        return $response;
                    } else if ($saved_questions_folder->user_id !== request()->user()->id) {
                        $response['message'] = "You do not own that folder.";
                        return $response;
                    }
            }

            DB::beginTransaction();
            $cloned_question = $clone_source->replicate();
            $cloned_question->title = $cloned_question->title . ' copy';
            $cloned_question->public = 0;
            $cloned_question->clone_source_id = $question_id;
            $cloned_question->question_editor_user_id = $question_editor_user_id;
            $cloned_question->folder_id = $clone_to_folder_id;
            $cloned_question->save();
            $cloned_question->page_id = $cloned_question->id;
            $cloned_question->save();
            if ($rubricCategory->where('question_id', $clone_source->id)->first()) {
                $rubric_categories = $rubricCategory->where('question_id', $clone_source->id)->get();
                foreach ($rubric_categories as $rubric_category) {
                    $new_rubric_category = $rubric_category->replicate();
                    $new_rubric_category->question_id = $cloned_question->id;
                    $new_rubric_category->save();
                }
            }
            $latest_question_revision_id = $clone_source->latestQuestionRevision('id');
            $question_media_uploads = $latest_question_revision_id
                ? $questionMediaUpload->where('question_id', $clone_source->id)
                    ->where('question_revision_id', $latest_question_revision_id)
                    ->get()
                : $questionMediaUpload->where('question_id', $clone_source->id)
                    ->get();

            foreach ($question_media_uploads as $question_media_upload) {
                $question_media_upload = $question_media_upload->replicate();
                $s3_key = $question_media_upload->s3_key;
                $cloned_s3_key = md5(uniqid('', true)) . '.' . pathinfo($s3_key, PATHINFO_EXTENSION);
                if (Storage::disk('s3')->exists("{$questionMediaUpload->getDir()}/$s3_key")) {
                    $contents = Storage::disk('s3')->get("{$questionMediaUpload->getDir()}/$s3_key");
                    Storage::disk('s3')->put("{$questionMediaUpload->getDir()}/$cloned_s3_key", $contents);
                }
                if ($question_media_upload->transcript) {
                    $vtt_file = $question_media_upload->getVttFileNameFromS3Key();
                    Storage::disk('s3')->put("{$questionMediaUpload->getDir()}/$vtt_file", $question_media_upload->transcript);
                }
                $question_media_upload->s3_key = $cloned_s3_key;
                $question_media_upload->question_id = $cloned_question->id;
                if ($clone_source->qti_json) {
                    $cloned_question->qti_json = str_replace($s3_key, $cloned_s3_key, $cloned_question->qti_json);
                    $cloned_question->save();
                }
                $question_media_upload->save();
            }


            if ($clone_source->webwork_code) {
                $webwork = new Webwork();
                $latest_revision_id = $clone_source->latestQuestionRevision('id');
                $source_dir = $latest_revision_id ? "$clone_source->id-$latest_revision_id" : $clone_source->id;
                $webwork->cloneDir($source_dir, $cloned_question->id);

                $webwork_dir = $webwork->getDir($cloned_question->id, 0);
                $cloned_question->updateWebworkPath($webwork_dir);
                $webwork_attachments = WebworkAttachment::where('question_id', $question_id)
                    ->where('question_revision_id', $latest_revision_id)
                    ->get();
                foreach ($webwork_attachments as $webwork_attachment) {
                    $webworkAttachment = new WebworkAttachment();
                    $webworkAttachment->filename = $webwork_attachment->filename;
                    $webworkAttachment->question_id = $cloned_question->id;
                    $webworkAttachment->save();
                }
            }
            if ($assignment_id && !$learning_tree_id) {
                $custom_rubric = $assignmentSyncQuestion->customRubric($assignment->id, $question_id);
                $assignmentSyncQuestion->addQuestiontoAssignmentByQuestionId($assignment,
                    $cloned_question->id,
                    $assignmentSyncQuestion,
                    $assignment->default_open_ended_submission_type,
                    $assignment->default_open_ended_text_editor,
                    $betaCourseApproval,
                    $custom_rubric);
            }
            $learning_outcomes = DB::table('question_learning_outcome')
                ->where('question_id', $question_id)
                ->get();
            foreach ($learning_outcomes as $learning_outcome) {
                DB::table('question_learning_outcome')
                    ->insert(['question_id' => $cloned_question->id,
                        'learning_outcome_id' => $learning_outcome->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()]);
            }
            $tags = DB::table('question_tag')
                ->where('question_id', $question_id)
                ->get();

            foreach ($tags as $tag) {
                DB::table('question_tag')
                    ->insert(['question_id' => $cloned_question->id,
                        'tag_id' => $tag->tag_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()]);
            }
            DB::commit();

            // If cloning from a learning tree assignment, also clone the learning tree
            // with the new root question substituted in.
            if ($learning_tree_id) {
                DB::beginTransaction();
                $source_tree = LearningTree::find($learning_tree_id);
                if (!$source_tree) {
                    $response['message'] = "Question was cloned but the learning tree with ID $learning_tree_id could not be found.";
                    $response['type'] = 'error';
                    return $response;
                }

                // Rewrite the root node's question_id in the tree JSON blocks
                $tree_data = json_decode($source_tree->learning_tree, true);
                foreach ($tree_data['blocks'] as &$block) {
                    if ((int)$block['parent'] === -1) {
                        foreach ($block['data'] as &$datum) {
                            if ($datum['name'] === 'question_id') {
                                $datum['value'] = (string)$cloned_question->id;
                            }
                        }
                        unset($datum);
                    }
                }
                unset($block);

                // Also patch the html string (handles both single- and double-quoted attribute values)
                $old_root_id = (string)$source_tree->root_node_question_id;
                $new_root_id = (string)$cloned_question->id;
                $tree_data['html'] = str_replace(
                    ["value='$old_root_id'", "value=\"$old_root_id\""],
                    ["value='$new_root_id'", "value=\"$new_root_id\""],
                    $tree_data['html']
                );

                $cloned_tree = $source_tree->replicate();
                $cloned_tree->user_id = $request->user()->id;
                $cloned_tree->public = 0;
                $cloned_tree->title = $source_tree->title . ' copy';
                $cloned_tree->root_node_question_id = $cloned_question->id;
                $cloned_tree->learning_tree = json_encode($tree_data);
                $cloned_tree->save();

                $learningTreeHistory = new LearningTreeHistory();
                $learningTreeHistory->learning_tree = $cloned_tree->learning_tree;
                $learningTreeHistory->learning_tree_id = $cloned_tree->id;
                $learningTreeHistory->root_node_question_id = $cloned_tree->root_node_question_id;
                $learningTreeHistory->save();

                if ($assignment_id) {
                    $add_result = $assignmentQuestionLearningTree->addToAssignment(
                        $assignment,
                        $cloned_tree,
                        $assignmentSyncQuestion,
                        $betaCourseApproval
                    );
                    if ($add_result['type'] === 'error') {
                        DB::rollback();
                        $response['message'] = $add_result['message'];
                        return $response;
                    }
                }

                DB::commit();
            }

            if ($acting_as === 'admin') {
                $user = User::find($question_editor_user_id);
                $message = "$clone_source->title has been cloned and $user->first_name $user->last_name has been given editing rights.";
            } else {
                $clone_to_folder = SavedQuestionsFolder::find($clone_to_folder_id);
                $root_node = $learning_tree_id ? "The root " : 'The';
                $message = "$root_node question has been cloned to your '$clone_to_folder->name' folder.";
                if ($learning_tree_id) {
                    $message .= "<br><br>In addition, the learning tree has been cloned as '{$cloned_tree->title}'";
                    $message .= $assignment_id ? " and added to $assignment->name." : ".";
                } elseif ($assignment_id) {
                    $message .= "<br><br>In addition, it has been added to $assignment->name.";
                }
            }
            $response['message'] = $message;
            $response['type'] = 'success';

        } catch
        (Exception $e) {
            DB::rollback();
            if ($cloned_question && Storage::disk('s3')->exists("adapt/$cloned_question->id.php")) {
                Storage::disk('s3')->delete("adapt/$cloned_question->id.php");
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = isset($request->learning_tree_id) && $request->learning_tree_id
                ? "There was an error cloning the learning tree.  Please try again or contact us for assistance."
                : "There was an error cloning the question.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public function getAssignmentStatus(Question $question): array
    {
        $response['type'] = 'error';
        try {
            $response['question_exists_in_own_assignment'] = $question->questionExistsInOneOfTheirAssignments();
            $response['question_exists_in_another_instructors_assignment'] = $question->questionExistsInAnotherInstructorsAssignments();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error seeing if this exists in another instructor's assignment.  Please try again or contact us for assistance.";
        }


        return $response;


    }

    /**
     * @param Question $Question
     * @return array
     */
    public
    function getValidLicenses(Question $Question): array
    {
        $response['licenses'] = $Question->getValidLicenses();
        return $response;
    }

    /**
     * @param Request $request
     * @param Question $question
     * @param Section $section
     * @return array
     * @throws Exception
     */
    public
    function validateBulkImport(Request  $request,
                                Question $question,
                                Section  $section): array
    {
        $import_template = $request->import_template;
        $course_id = $request->course_id;
        $response['type'] = 'error';

        $authorized = Gate::inspect('validateBulkImport', [$question, $course_id]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response = $import_template === 'qti' ?
                $this->validateQTIBulkImport($request)
                : $this->validateCSVBulkImport($request, $import_template, $course_id, $section);
            if ($response['message']) {
                if (DB::transactionLevel()) {
                    DB::rollback();
                }
                return $response;
            }
            $response['type'] = 'success';
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $message = "We were not able to upload the questions file.  Please try again or contact us for assistance.";
            $response['message'] = $import_template === 'qti' ? $message : [$message];
        }
        return $response;

    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function validateQTIBulkImport(Request $request): array
    {
        $savedQuestionsFolder = new SavedQuestionsFolder();
        $response['message'] = [];
        $author_error = '';
        $folder_error = '';
        $license_error = '';
        $import_to_course_error = '';
        $assignment_template_error = '';
        if ($request->import_to_course) {
            $course = Course::find($request->import_to_course);
            if (!$course || $request->user()->id !== $course->user_id) {
                $import_to_course_error = "That is not one of your courses.";
            } else {
                if ($message = $course->bulkUploadAllowed()) {
                    $import_to_course_error = $message;
                } else {
                    if (!$request->assignment_template) {
                        $assignment_template_error = "Please choose an assignment template.";
                    } else {
                        $assignment_template = DB::table('assignment_templates')
                            ->where('id', $request->assignment_template)
                            ->where('user_id', $request->user()->id)
                            ->first();
                        if (!$assignment_template) {
                            $assignment_template_error = "That is not one of your assignment templates.";
                        }
                    }
                }
            }

        }
        if (!$request->author) {
            $author_error = "An author is required.";
        }
        if (!$request->folder_id) {
            $folder_error = "Please select a folder.";
        } else if (!$savedQuestionsFolder->isOwner($request->folder_id)) {
            $folder_error = "That is not one of your folders.";
        }
        if (!$request->license) {
            $license_error = "A license is required.";
        }
        if ($author_error || $folder_error || $license_error || $import_to_course_error || $assignment_template_error) {
            $response['message']['form_errors'] = ['author' => $author_error,
                'folder_id' => $folder_error,
                'license' => $license_error,
                'import_to_course' => $import_to_course_error,
                'assignment_template' => $assignment_template_error
            ];
            return $response;
        }

        $qtiJob = new QtiJob();
        $qtiJob->user_id = $request->user()->id;
        $qtiJob->qti_directory = pathinfo($request->qti_file)['filename'];
        $qtiJob->qti_source = $request->qti_source;
        $qtiJob->public = $request->public;
        $qtiJob->course_id = $request->import_to_course;
        $qtiJob->assignment_template_id = $request->assignment_template;
        $qtiJob->folder_id = $request->folder_id;
        $qtiJob->license = $request->license;
        $qtiJob->license_version = $request->license_version;
        $qtiJob->source_url = $request->source_url;
        $qtiJob->status = 'processing';
        $qtiJob->save();
        if (!app()->environment('testing')) {
            ProcessValidateQtiFile::dispatch($qtiJob, $request->qti_file, $request->import_to_course, $request->assignment_template);
        }
        $response['message'] = null;
        $response['qti_job_id'] = $qtiJob->id;
        return $response;
    }

    /**
     * @param Request $request
     * @param string $import_template
     * @param $course_id
     * @param $section
     * @return array
     * @throws Exception
     */
    public function validateCSVBulkImport(Request $request,
                                          string  $import_template,
                                                  $course_id,
                                                  $section): array
    {

        $Question = new Question();
        if (!app()->environment('testing')) {
            if (!$request->file('bulk_import_file')) {
                $response['message'] = ['No file was selected.'];
                return $response;
            }
            $bulk_import_file = $request->file('bulk_import_file')->store("override-scores/" . Auth()->user()->id, 'local');
            $csv_file = Storage::disk('local')->path($bulk_import_file);

            $content = Storage::disk('local')->get($bulk_import_file);
            if (!preg_match('//u', $content)) {
                $response['message'] = ['The file must be saved as UTF-8. In Excel, use "Save As" → "CSV UTF-8 (Comma delimited)".'];
                return $response;
            }


            if (!in_array($request->file('bulk_import_file')->getMimetype(), ['application/x-tex', 'application/csv', 'text/plain', 'text/x-tex'])) {
                $response['message'] = ["This is not a .csv file: {$request->file('bulk_import_file')->getMimetype()} is not a valid MIME type."];
                return $response;
            }
            $handle = fopen($csv_file, 'r');
            $header = fgetcsv($handle);
            switch ($import_template) {
                case('flashcard'):
                    $correct_keys = $this->flashcard_keys;
                    break;
                case('webwork'):
                    $correct_keys = $this->webwork_keys;
                    break;
                case('advanced'):
                    $correct_keys = $this->advanced_keys;
                    break;
                case('bow_tie'):
                    $correct_keys = $this->bow_tie_keys;
                    break;
                case('case_study_notes'):
                    $correct_keys = $this->case_study_notes_keys;
                    break;
                default:
                    throw new Exception ('Invalid import template.');

            }

            if ($course_id) {
                if (count($header) < count($correct_keys)) {
                    $response['message'] = ["It looks like you are trying to import your .csv file into a course but the .csv file is missing some of the course items in the header."];
                    return $response;
                }
            } else {
                if (count($header) > count($this->_removeCourseInfo($correct_keys))) {
                    $response['message'] = ["It looks like you are trying to import your .csv file outside of a course but the .csv file has course items in the header."];
                    return $response;
                }
            }
            fclose($handle);
            $bulk_import_items = Helper::csvToArray($csv_file);
        } else {
            $bulk_import_items = $request->csv_file_array;

        }

        if (!$bulk_import_items) {
            $response['message'] = ['The .csv file has no data.'];
            return $response;
        }
        switch ($import_template) {
            case('flashcard'):
                $keys = $this->flashcard_keys;
                break;
            case('webwork'):
                $keys = $this->webwork_keys;
                break;
            case('advanced'):
                $keys = $this->advanced_keys;
                break;
            case('bow_tie'):
                $keys = $this->bow_tie_keys;
                break;
            case('case_study_notes'):
                $keys = $this->case_study_notes_keys;
                break;
            default:
                throw new Exception ('Invalid import template to check keys.');
        }
        if (!$course_id) {
            $keys = $this->_removeCourseInfo($keys);
        }
        $uploaded_keys = array_keys($bulk_import_items[0]);
        foreach ($keys as $key => $correct_key) {
            if ($correct_key !== $uploaded_keys[$key]) {
                $response['message'] = ["The CSV should have a first row with the following headings: " . implode(', ', $keys) . "."];
                return $response;
            }
        }
        //structure looks good
        $messages = [];
        $assign_tos = $course_id ?
            Helper::getDefaultAssignTos($course_id)
            : null;

        if ($course_id) {
            $course = Course::find($course_id);
            $message = $course->bulkUploadAllowed();
            if ($message) {
                $response['message'][] = $message;
                return $response;
            }
        }


        $assignment_templates = [];
        DB::beginTransaction();
        foreach ($bulk_import_items as $key => $question) {
            $bulk_import_items[$key]['Course'] = $request->course_id;
            $bulk_import_items[$key]['row'] = $key + 2;
            $bulk_import_items[$key]['import_status'] = 'Pending';
            if ($import_template !== 'case_study_notes') {
                if ($question['Tags']) {
                    $tags = explode(',', $question['Tags']);
                    $bulk_import_items[$key]['Tags'] = [];
                    foreach ($tags as $tag) {
                        $bulk_import_items[$key]['Tags'][] = trim($tag);
                    }
                }
            }
            $row_num = $key + 2;
            if ($import_template === 'advanced' && !in_array($question['Question Type*'], ['assessment', 'exposition'])) {
                $messages[] = "Row $row_num has a Question Type of {$question['Question Type*']} but the valid question types are assessment and exposition.";
            }
            if ($import_template !== 'case_study_notes') {
                if (!$question['Folder*']) {
                    $messages[] = "Row $row_num is missing a Folder.";
                } else {
                    $folder = DB::table('saved_questions_folders')
                        ->where('type', 'my_questions')
                        ->where('name', trim($question['Folder*']))
                        ->where('user_id', $request->user()->id)
                        ->select('id')
                        ->first();
                    if (!$folder) {
                        $savedQuestionsFolder = new SavedQuestionsFolder();
                        $savedQuestionsFolder->name = trim($question['Folder*']);
                        $savedQuestionsFolder->user_id = $request->user()->id;
                        $savedQuestionsFolder->type = 'my_questions';
                        $savedQuestionsFolder->save();
                        $bulk_import_items[$key]['folder_id'] = $savedQuestionsFolder->id;
                    } else {
                        $bulk_import_items[$key]['folder_id'] = $folder->id;
                    }
                }
                $course_assignment_topic_error = false;
                if ($course_id) {
                    $question['Assignment'] = trim($question['Assignment']);
                    if ($question['Assignment'] && $question['Template']) {
                        if (!isset($assignment_templates[$question['Assignment']])) {
                            $assignment_templates[$question['Assignment']] = $question['Template'];
                        } else {
                            if ($assignment_templates[$question['Assignment']] !== $question['Template']) {
                                $messages[] = "Row $row_num has an Assignment {$question['Assignment']} and a Template {$question['Template']} but a previous row has the same Assignment with a different Template.";
                            }
                        }
                    }
                    if (!$question['Assignment']) {
                        $course_assignment_topic_error = true;
                        $messages[] = "Row $row_num is missing an Assignment.";

                    }
                    if (!$course_assignment_topic_error && $question['Topic'] && !$question['Assignment']) {
                        $messages[] = "Row $row_num has a Topic but not an Assignment.";
                    }
                    $assignment_error = false;
                    $assignment_template_error = false;
                    if (!$course_assignment_topic_error && $question['Assignment']) {
                        if (!($assignment = DB::table('assignments')
                            ->join('courses', "assignments.course_id", '=', 'courses.id')
                            ->where("courses.id", $course_id)
                            ->where("assignments.name", trim($question['Assignment']))
                            ->select("assignments.id AS assignment_id")
                            ->first())) {
                            $course = Course::find($request->course_id);
                            if (!$question['Template']) {
                                $assignment_error = true;
                                $messages[] = "Row $row_num has an assignment which is not in $course->name. In addition, there is no Template that can be used to create an assignment.";
                            } else {
                                $assignment_template = AssignmentTemplate::where('template_name', trim($question['Template']))
                                    ->where('user_id', $request->user()->id)
                                    ->first();
                                if ($assignment_template) {
                                    $assignment_template_error = true;
                                    $assignment_info = $assignment_template->toArray();
                                    $assignment_info['name'] = $question['Assignment'];
                                    $assignment_info['course_id'] = $request->course_id;
                                    $assignment_info['order'] = $course->assignments->count() + 1;
                                    foreach (['id', 'template_name', 'template_description', 'user_id', 'created_at', 'updated_at', 'assign_to_everyone'] as $value) {
                                        unset($assignment_info[$value]);
                                    }
                                    $assignment = Assignment::create($assignment_info);
                                    if ($assignment->late_policy !== 'not accepted') {
                                        $assign_tos[0]['final_submission_deadline_date'] = Carbon::parse($assign_tos[0]['due_date'])
                                            ->addDay()
                                            ->toDateString();
                                        $assign_tos[0]['final_submission_deadline_time'] = $assign_tos[0]['due_time'];
                                    }
                                    $this->addAssignTos($assignment, $assign_tos, $section, $request->user());
                                } else {
                                    $assignment_template_error = true;
                                    $messages[] = "Row $row_num has the template \"{$question['Template']}\", which is not one of your templates.";
                                }
                            }
                        }
                        if (!$assignment_error && !$assignment_template_error && trim($question['Topic'])) {
                            if (!DB::table('assignment_topics')
                                ->join('assignments', "assignment_topics.assignment_id", '=', "assignments.id")
                                ->where("assignments.id", $assignment->assignment_id)
                                ->where("assignment_topics.name", $question['Topic'])
                                ->first()) {
                                $assignmentTopic = new AssignmentTopic();
                                $assignmentTopic->assignment_id = $assignment->assignment_id;
                                $assignmentTopic->name = trim($question['Topic']);
                                $assignmentTopic->save();
                            }
                        }
                    }
                }

                if (!is_numeric($question['Public*']) || ((int)$question['Public*'] !== 0 && (int)$question['Public*'] !== 1)) {
                    $messages[] = "Row $row_num is missing a valid entry for Public (0 for no and 1 for yes).";
                }
                if ($import_template !== 'case_study_notes') {
                    if (!$question['Title*']) {
                        $messages[] = "Row $row_num is missing a Title.";
                    }
                }

                if ($import_template === 'advanced' && $question['Question Type*'] === 'exposition' && !$question['Open-Ended Content']) {
                    $messages[] = "Row $row_num is an exposition type question and is missing the source.";
                }

                if ($import_template === 'advanced' && $question['Question Type*'] === 'exposition' && ($question['Auto-Graded Technology'] || $question['Technology ID/File Path'])) {
                    $messages[] = "Row $row_num is an exposition type question but has an auto-graded technology.";
                }

                if ($import_template === 'advanced'
                    && $question['Question Type*'] === 'exposition'
                    && ($question['Text Question'] || $question['Answer'] || $question['Solution'] || $question['Hint'])) {
                    $messages[] = "Row $row_num is an exposition type question and should not have Text Question, Answer, Solution, or Hint.";
                }

                if ($import_template === 'advanced' && $question['Question Type*'] === 'assessment' && !$question['Open-Ended Content'] && !$question['Auto-Graded Technology']) {
                    $messages[] = "Row $row_num is an assessment and needs either an auto-graded technology or source.";
                }

                if ($import_template === 'webwork' && !$question['File Path*']) {
                    $messages[] = "Row $row_num does not have a File Path";
                }
                $technology_id = 0;
                switch ($import_template) {
                    case('webwork'):
                        $technology_id = $question['File Path*'];
                        break;
                    case('flashcard'):
                    case('bow_tie'):
                        break;
                    default:
                        $technology_id = $question['Technology ID/File Path'];
                }


                if ($import_template === 'advanced') {
                    switch ($question['Auto-Graded Technology']) {
                        case('webwork'):
                            if (!$technology_id) {
                                $messages[] = "Row $row_num uses webwork and is missing the File Path.";
                            }
                            break;
                        case('h5p'):
                        case('imathas'):
                            if (!filter_var($technology_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                                $messages[] = "Row $row_num uses {$question['Auto-Graded Technology']} and requires a positive integer as the Technology ID.";
                            }
                            break;
                        case(''):
                            $bulk_import_items[$key]['Auto-Graded Technology'] = 'text';
                            break;
                        default:
                            $messages[] = "Row $row_num is using an invalid technology: {$question['Auto-Graded Technology']}.";
                    }
                }
                if ($import_template === 'bow_tie') {
                    if (!$question['Question Body*']) {
                        $messages[] = "Row $row_num is missing a Question Body.";
                    }
                    if (!$question['Actions to Take*']) {
                        $messages[] = "Row $row_num is missing Actions to Take.";
                    } else if (count(explode(',', $question['Actions to Take*'])) < 3) {
                        $messages[] = "Row $row_num needs at least 3 Actions to Take.";
                    }

                    if (!$question['Potential Conditions*']) {
                        $messages[] = "Row $row_num is missing Potential Conditions.";
                    } else if (count(explode(',', $question['Actions to Take*'])) < 2) {
                        $messages[] = "Row $row_num needs at least 2 Potential Conditions.";
                    }
                    if (!$question['Parameters to Monitor*']) {
                        $messages[] = "Row $row_num is missing Parameters to Monitor.";
                    } else if (count(explode(',', $question['Parameters to Monitor*'])) < 3) {
                        $messages[] = "Row $row_num needs at least 3 Parameters to Monitor.";
                    }
                }
                if ($import_template === 'flashcard') {
                    if (!$question['Front*']) {
                        $messages[] = "Row $row_num is missing a Front (term).";
                    }
                    if (!$question['Back*']) {
                        $messages[] = "Row $row_num is missing a Back (definition).";
                    }
                    if (!in_array($question['Front Text Language*'], ['English', 'Spanish', 'French'])) {
                        $messages[] = "Row $row_num is missing a valid Front Text Language (English, Spanish, or French)";
                    }
                    if (!in_array($question['Back Text Language*'], ['English', 'Spanish', 'French'])) {
                        $messages[] = "Row $row_num is missing a valid Back Text Language (English, Spanish, or French)";
                    }
                }
                if (!$question['License*']) {
                    $messages[] = "Row $row_num is missing a license.";
                } else {
                    if (!in_array($question['License*'], $Question->getValidLicenses())) {
                        $messages[] = "Row $row_num is using an invalid license: {$question['License*']}. Valid licenses are " . implode(', ', $Question->getValidLicenses());
                    }
                }
                if (!$question['Author*']) {
                    $messages[] = "Row $row_num is missing an author.";
                }
            } else {
                if ($question['Weight'] && (strpos($question['Weight'], 'lb') === false && strpos($question['Weight'], 'kg') === false)) {
                    $messages[] = "Row $row_num should have kg or lb for the weight units.";
                }

                if (!in_array($question['Code Status'], ['Full Code', 'DNR'])) {
                    $messages[] = "Row $row_num should have Full Code or DNR for the code status.";
                }
            }
        }


        $message = $messages;
        $items_to_import = $bulk_import_items;
        return compact('message', 'items_to_import');
    }

    /**
     * @param Request $request
     * @param string $import_template
     * @param Course|null $course
     * @return array|string
     * @throws Exception
     */
    public function getBulkUploadTemplate(Request $request, string $import_template, Course $course = null)
    {
        try {
            switch ($import_template) {
                case('webwork-def-file'):
                case('webwork'):
                    $list = $this->webwork_keys;
                    break;
                case('bow_tie'):
                    $list = $this->bow_tie_keys;
                    break;
                case('case_study_notes'):
                    $list = $this->case_study_notes_keys;
                    break;
                case('advanced'):
                    $list = $this->advanced_keys;
                    break;
                case('flashcard'):
                    $list = $this->flashcard_keys;
                    break;
                default:
                    throw new Exception("Cannot get the CSV bulk upload template for $import_template.");
            }

            // Flashcard has no course-specific columns, but _removeCourseInfo
            // is harmless here (array_diff finds nothing to remove).
            if (!$course) {
                $list = $this->_removeCourseInfo($list);
            }

            $efs_dir = '/mnt/local/';
            $is_efs = is_dir($efs_dir);
            $storage_path = $is_efs
                ? $efs_dir
                : Storage::disk('local')->getAdapter()->getPathPrefix();

            $file = "$storage_path$import_template-bulk-question-import-template.csv";
            $fp = fopen($file, 'w');

            if ($import_template === 'webwork-def-file') {
                $contents = file($request->file);
                $new_list[0] = $list;
                $index = 1;
                foreach ($contents as $line) {
                    if (str_starts_with($line, 'source_file')) {
                        $pg_file = trim(str_replace('source_file = ', '', $line));
                        $row = [];
                        foreach ($list as $key => $item) {
                            $row[$key] = $key === 3 ? "huang_course/$pg_file" : '';
                        }
                        $new_list[$index] = $row;
                        $index++;
                    }
                }
                $list = $new_list;
                foreach ($list as $fields) {
                    fputcsv($fp, $fields);
                }
            } else {
                fputcsv($fp, $list);
            }

            fclose($fp);

            Storage::disk('s3')->delete("$import_template-bulk-question-import-template.csv");
            Storage::disk('s3')->put(
                "$import_template-bulk-question-import-template.csv",
                file_get_contents($file)
            );

            return $import_template === 'webwork-def-file'
                ? Storage::disk('s3')->get("$import_template-bulk-question-import-template.csv")
                : Storage::disk('s3')->download("$import_template-bulk-question-import-template.csv");

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to download the file.  Please try again or contact us for assistance.";
        }

        return $response;
    }


    /**
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function destroy(Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('destroy', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        try {
            $exists_in_assignment = DB::table('assignment_question')
                ->where('question_id', $question->id)
                ->exists();
            if ($exists_in_assignment) {
                $response['message'] = "This question already exists in an assignment and cannot be deleted.";
                return $response;
            }
            if ($question->existsInLearningTree()) {
                $response['message'] = "This question already exists in a Learning Tree and cannot be deleted.";
                return $response;
            }


            DB::beginTransaction();

            $webwork_dir = $question->technology === 'webwork' && $question->webwork_code
                ? dirname($question->technology_id)
                : '';

            $question->cleanUpTags();
            foreach (['adapt_mass_migrations',
                         'adapt_migrations',
                         'qti_imports',
                         'h5p_max_scores',
                         'my_favorites',
                         'h5p_activity_sets',
                         'question_learning_outcome',
                         'seeds',
                         'assignment_question_time_on_tasks',
                         'pending_question_revisions',
                         'question_revisions',
                         'can_give_ups',
                         'webwork_attachments',
                         'question_media_uploads',
                         'formatted_question_types'
                     ]
                     as $table) {
                $column = in_array($table, ['adapt_migrations', 'adapt_mass_migrations']) ? 'new_page_id' : 'question_id';
                DB::table($table)->where($column, $question->id)->delete();
            }
            $question->delete();
            if ($webwork_dir) {
                $webwork = new Webwork();
                $webwork_contents = $webwork->listDir($webwork_dir);
                if ($webwork_contents) {
                    foreach ($webwork_contents as $path_to_contents) {
                        $webwork->deletePath($path_to_contents);
                    }
                    $webwork->deletePath($webwork_dir);
                }
            }


            DB::commit();
            $response['message'] = "The question has been deleted.";
            $response['type'] = 'info';
        } catch
        (Exception $e) {
            DB::rollBack();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to delete this question.  Please try again or contact us for assistance.";
        }

        return $response;

    }


    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function index(Request $request, Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('index', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $questions = $question->where('question_editor_user_id', $request->user()->id)
                ->orderBy('updated_at', 'desc')
                ->get();

            $tags = DB::table('question_tag')
                ->join('tags', 'question_tag.tag_id', '=', 'tags.id')
                ->whereIn('question_id', $questions->pluck('id'))
                ->select('question_id', 'tag')
                ->get();
            $tags_by_question_id = [];
            foreach ($tags as $tag) {
                $tags_by_question_id[$tag->question_id][] = $tag->tag;
            }
            $extra_htmls = ['text_question',
                'answer_html',
                'solution_html',
                'hint',
                'notes'];
            $dom = new DomDocument();
            foreach ($questions as $key => $question) {
                foreach ($extra_htmls as $extra_html) {
                    if ($question[$extra_html]) {
                        $html = $question->cleanUpExtraHtml($dom, $question[$extra_html]);
                        $questions[$key][$extra_html] = $html;
                    }
                }
                $questions[$key]['tags'] = $tags_by_question_id[$question->id] ?? ['none'];
                $questions[$key]['updated_at'] = $this->convertUTCMysqlFormattedDateToLocalDateAndTime($question->updated_at, $request->user()->time_zone);
            }
            $response['my_questions'] = $questions;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve your questions.  Please try again or contact us for assistance.";
        }

        return $response;

    }


    /**
     * @throws Exception
     */
    public
    function update(StoreQuestionRequest   $request,
                    Question               $question,
                    Libretext              $libretext,
                    AssignmentSyncQuestion $assignmentSyncQuestion,
                    Assignment             $assignment): array
    {
        $response['type'] = 'error';
        if ($request->revision_action) {
            $error = false;
            switch ($request->revision_action) {
                case('notify'):
                    if (!$request->reason_for_edit) {
                        $error = true;
                        $response['reason_for_edit_error'] = 'Please provide a reason for the edit.';
                    }
                    if ($request->automatically_update_revision === null) {
                        $error = true;
                        $response['automatically_update_revision_error'] = 'Please specify whether you would like to automatically update this question in your current assignments.';
                    }
                    if ($error) {
                        return $response;
                    }
                    break;
                case('propagate'):
                    if (!$request->reason_for_edit && !Helper::isAdmin()) {
                        $error = true;
                        $response['reason_for_edit_error'] = 'Please provide a reason for the edit.';
                    }

                    if ($question->isDiscussIt()) {
                        $new_s3_keys = array_column($request->media_uploads, 's3_key');
                        $question_revision_id = $question->latestQuestionRevision('id');
                        $current_s3_keys = QuestionMediaUpload::where('question_id', $question->id)
                            ->where('question_revision_id', $question_revision_id)
                            ->get('s3_key')
                            ->pluck('s3_key')
                            ->toArray();
                        $current_s3_keys = array_unique($current_s3_keys);
                        sort($current_s3_keys);
                        sort($new_s3_keys);
                        if ($current_s3_keys !== $new_s3_keys) {
                            $response['message'] = 'You cannot propagate the question revision since there are differing media. This is a non-topical change to the question.';
                            return $response;
                        }
                    }
                    if (!Helper::isAdmin() && $question->nonMetaPropertiesDiffer($request)) {
                        $response['message'] = 'You cannot propagate the question revision since there are differing properties that are not topical in nature.';
                        return $response;
                    }
                    if (Helper::isAdmin() && !$request->changes_are_topical) {
                        $error = true;
                        $response['changes_are_topical_error'] = "You must confirm that the changes are topical.";
                    }
                    if ($error) {
                        return $response;
                    }
                    break;
                default:
                    $response['message'] = "$request->revision_action is not a valid revision action.";
                    return $response;
            }
        } else {
            $response['message'] = 'There is no revision action associated with this question.';
            return $response;
        }
        return $this->store($request,
            $question,
            $libretext,
            $assignmentSyncQuestion,
            $assignment);

    }

    /**
     * @param StoreQuestionRequest $request
     * @param Question $question
     * @param Libretext $libretext
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Assignment $assignment
     * @return array
     * @throws Exception
     */
    public
    function store(StoreQuestionRequest   $request,
                   Question               $question,
                   Libretext              $libretext,
                   AssignmentSyncQuestion $assignmentSyncQuestion,
                   Assignment             $assignment): array
    {
        $response['type'] = 'error';
        $original_question_technology = '';
        $is_update = isset($request->id);

        $authorized = $is_update ? Gate::inspect('update', [$question, $request->folder_id])
            : Gate::inspect('store', [$question, $request->folder_id]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $revision_action = $request->revision_action;
        $automatically_update_revision = $revision_action === 'notify' ? $request->automatically_update_revision : 0;
        $media_uploads = $request->media_uploads;
        try {
            $data = $request->validated();
            $question_type = '';
            $attachments = $request->attachments ? $request->attachments : [];

            foreach ($attachments as $key => $attachment) {
                $attachments[$key]['s3_key'] = basename($attachment['s3_key']);
            }
            $data['attachments'] = json_encode($attachments);
            if ($data['technology'] === 'qti') {
                foreach ($data as $key => $value) {
                    if (strpos($key, 'qti_') !== false) {
                        unset($data[$key]);
                    }
                }
                $question_type = json_decode($request->qti_json)->questionType;
                $data['qti_json_type'] = $question_type;

                switch ($question_type) {
                    case('three_d_model_multiple_choice'):
                        $unsets = ['parameters', 'solution_structure'];
                        $parameters = ["modelID", "BGImage", "annotations", "mode", "BGColor", "piece", "modelOffset", "cameraOffset", "selectionColor", "panel", "autospin", "STLmatCol", "hideDistance"];

                        $all_parameter_info = json_decode($request->qti_json, 1)['parameters'];
                        foreach ($all_parameter_info as $key => $value) {
                            if (!in_array($key, $parameters)) {
                                unset($all_parameter_info[$key]);
                            }
                        }
                        $qti_json = json_decode($request->qti_json, 1);
                        $qti_json['parameters'] = $all_parameter_info;
                        $request->qti_json = json_encode($qti_json);
                        break;
                    case('discuss_it'):
                        $unsets = ['media_uploads'];
                        break;
                    case('submit_molecule'):
                        $unsets = ['solution_structure'];
                        break;
                    case('marker'):
                        $unsets = ['solution_structure', 'atoms_and_bonds'];
                        $qti_json = json_decode($request->qti_json, 1);

                        $atoms = array_filter($request->atoms_and_bonds, function ($c) {
                            return isset($c['structuralComponent']) && $c['structuralComponent'] === 'atom';
                        });
                        $bonds = array_filter($request->atoms_and_bonds, function ($c) {
                            return isset($c['structuralComponent']) && $c['structuralComponent'] === 'bond';
                        });

                        usort($atoms, function ($a, $b) {
                            return $a['index'] - $b['index'];
                        });
                        usort($bonds, function ($a, $b) {
                            return $a['index'] - $b['index'];
                        });

                        $qti_json['solutionStructure'] = [
                            'atoms' => array_values($atoms),
                            'bonds' => array_values($bonds),
                        ];

                        $request->qti_json = json_encode($qti_json);

                        break;
                    case ('multiple_response_select_all_that_apply'):
                    case ('multiple_response_select_n'):
                        $unsets = ['colHeaders', 'rows', 'responses'];
                        break;
                    case ('drop_down_table'):
                    case('highlight_table'):
                    case('matrix_multiple_response'):
                        $unsets = ['colHeaders', 'rows'];
                        break;
                    case('highlight_text'):
                        $unsets = ['responses'];
                        //Log::info($request->qti_json);
                        $qti_json = json_decode($request->qti_json, 1);
                        $responses = [];
                        foreach ($qti_json['responses'] as $key => $response) {
                            $response['text'] = str_replace('&quot;', '"', $response['text']);
                            $response['text'] = str_replace('&#39;', "'", $response['text']);
                            $responses[$key] = $response;
                        }
                        $qti_json['responses'] = $responses;
                        $request->qti_json = json_encode($qti_json);
                        // Log::info($request->qti_json);
                        //Log::info(print_r($qti_json['responses'], 1));
                        break;
                    case('drag_and_drop_cloze'):
                        $unsets = ['correct_responses', 'distractors'];
                        break;
                    case('bow_tie'):
                        $unsets = ['actions_to_take', 'parameters_to_monitor', 'potential_conditions'];
                        break;
                    case('numerical'):
                        $unsets = ['correct_response', 'margin_of_error', 'relative_tolerance'];
                        break;
                    case('multi_numerical'):
                        $unsets = ['correct_response', 'margin_of_error', 'relative_tolerance', 'placeholders'];
                        break;
                    case('multiple_response_grouping'):
                    case('matrix_multiple_choice'):
                        $unsets = ['headers', 'rows'];
                        break;
                    default:
                        $unsets = [];
                }
                if ($unsets) {
                    foreach ($unsets as $unset) {
                        unset($data[$unset]);
                    }
                }
            }
            $data['qti_json'] = $data['technology'] === 'qti' ? $request->qti_json : null;

            /*   if ($data['qti_json']) {
                 $qti_json = json_decode($data['qti_json'], 1);
               if (isset($qti_json['itemBody'])) {
                     $itemBody = $qti_json['itemBody'];
                     $itemBody = str_replace("\n", '', $itemBody);
                     $unnecessary_characters = '<p>&nbsp;</p>';
                     $num_characters = strlen($unnecessary_characters);
                     if (substr($itemBody, 0, $num_characters) === $unnecessary_characters) {
                         $itemBody = substr($itemBody,$num_characters);
                     }
                     if (substr($itemBody, -$num_characters) === $unnecessary_characters) {
                         $itemBody = substr($itemBody,0,strlen($itemBody)-$num_characters);
                     }
                     $qti_json['itemBody'] = $itemBody;
                     $data['qti_json'] = json_encode($qti_json);
                 }
            } */

            if ($is_update && $data['qti_json'] && $question->qti_json !== $request->qti_json) {
                DB::table('seeds')->where('question_id', $question->id)
                    ->delete();
            }
            if (in_array($request->question_type, ['exposition', 'report'])) {
                $technology_id = null;
                foreach (['technology_id', 'a11y_auto_graded_question_id', 'webwork_code', 'text_question', 'answer_html', 'hint'] as $value) {
                    $data[$value] = null;
                }
                if ($data['question_type'] === 'report') {
                    unset($data['rubric_categories']);
                }
            } else {
                $technology_id = $data['technology_id'] ?? null;
                $data['a11y_auto_graded_question_id'] = $data['a11y_auto_graded_question_id'] ?? null;

                if ($data['a11y_auto_graded_question_id'] && strpos($data['a11y_auto_graded_question_id'], '-') !== false) {
                    $pos = strpos($data['a11y_auto_graded_question_id'], '-');
                    $data['a11y_auto_graded_question_id'] = substr($data['a11y_auto_graded_question_id'], $pos + 1);
                }
                $data['webwork_code'] = $request->technology === 'webwork' && $request->new_auto_graded_code === 'webwork'
                    ? $request->webwork_code
                    : null;
                $extra_htmls = ['text_question' => 'Text Question',
                    'answer_html' => 'Answer',
                    'solution_html' => 'Solution',
                    'hint' => 'Hint',
                    'notes' => 'Notes'];
                foreach ($extra_htmls as $extra_html => $extra_html_title) {
                    if (isset($data[$extra_html]) && $data[$extra_html]) {
                        $data[$extra_html] = '<div class="mt-section"><h2 class="editable">' . $extra_html_title . '</h2>' . $data[$extra_html] . "</div>";
                    }
                }
            }

            $data['library'] = 'adapt';
            $tags = [];
            if ($data['tags']) {
                foreach ($data['tags'] as $tag) {
                    $tags[] = trim($tag);
                }
            }
            unset($data['tags']);
            unset($data['framework_item_sync_question']);

            $data['page_id'] = $is_update
                ? Question::find($request->id)->page_id
                : 1 + $question->where('library', 'adapt')->orderBy('id', 'desc')->value('page_id');

            $data['url'] = null;

            $data['technology_iframe'] = $technology_id
                ? $question->getTechnologyIframeFromTechnology($data['technology'], $data['technology_id'])
                : '';

            $non_technology_text = isset($data['non_technology_text']) ? trim($data['non_technology_text']) : '';
            $data['rubric'] = $request->rubric;
            $data['non_technology'] = $non_technology_text !== '';
            $data['non_technology_html'] = $non_technology_text ?: null;
            $data['non_technology_html'] = str_replace('<p>&nbsp;</p>', '', $data['non_technology_html']);
            if ($is_update) {
                if ($data['technology'] !== 'h5p') {
                    $data['h5p_type'] = null;
                }
                $question_to_update = Question::find($request->id);
                if (Helper::isAdmin() && isset($data['question_editor_user_id']) && $data['question_editor_user_id']) {
                    // Admin explicitly reassigning — keep what was sent
                } elseif ($question->folderIdRequired($request->user(), $question_to_update->question_editor_user_id)) {
                    $data['question_editor_user_id'] = $request->user()->id;
                } else {
                    $data['question_editor_user_id'] = $question_to_update->question_editor_user_id;
                }
            } else {
                $data['question_editor_user_id'] = $request->user()->id;
            }

            $data['cached'] = false;
            unset($data['non_technology_text']);

            $rubric_items_exist = isset($data['rubric_items']) && $data['rubric_items'];
            if ($rubric_items_exist) {
                $rubric = json_encode(['rubric_items' => $data['rubric_items'], 'rubric_shown' => $data['rubric_shown']]);
                $data['rubric'] = $rubric;
                if ($request->rubric_template_save_option !== 'do not save as template') {
                    $request->rubric_template_id ? RubricTemplate::where('id', $request->rubric_template_id)
                        ->update(['name' => $data['rubric_name'],
                            'description' => $data['rubric_description'],
                            'rubric' => $rubric])
                        : RubricTemplate::create(['name' => $data['rubric_name'],
                        'description' => $data['rubric_description'],
                        'rubric' => $rubric,
                        'user_id' => $request->user()->id]);
                }
                foreach (['rubric_name', 'rubric_description', 'rubric_template_id', 'rubric_shown', 'rubric_items'] as $rubric_key) {
                    unset($data[$rubric_key]);
                }
            }


            DB::beginTransaction();
            $new_question_revision_id = 0;
            $currentQuestionRevision = null;
            $show_captions = true;
            if ($request->qti_json) {
                $qti_json = json_decode($request->qti_json, 1);
                $show_captions = !isset($qti_json['showCaptions']) || $qti_json['showCaptions'] === 'yes';
            }
            if ($is_update) {
                $question = Question::find($request->id);
                $original_question_technology = $question->technology;
                if (!QuestionRevision::where('question_id', $question->id)->first()) {
                    $question_revision = $question->toArray();
                    $question_revision['revision_number'] = 0;
                    $question_revision['question_id'] = $question['id'];
                    $question_revision['action'] = 'none';
                    unset($question_revision['id']);
                    $initial_question_revision = QuestionRevision::create($question_revision);
                    if ($request->question_type === 'report') {
                        RubricCategory::where('question_id', $question->id)->update(['question_revision_id' => $initial_question_revision->id]);
                    }
                    WebworkAttachment::where('question_id', $question->id)->update(['question_revision_id' => $initial_question_revision->id]);
                    QuestionMediaUpload::where('question_id', $question->id)->update([
                        'question_revision_id' => $initial_question_revision->id,
                        'show_captions' => $show_captions]);
                }
                $question->update($data);
                $currentQuestionRevision = QuestionRevision::where('question_id', $question->id)->orderBy('revision_number', 'desc')->first();

                $new_revision_number = QuestionRevision::where('question_id', $question->id)->max('revision_number') + 1;
                $new_question_revision = $question->toArray();
                $new_question_revision['revision_number'] = $new_revision_number;
                $new_question_revision['reason_for_edit'] = $request->reason_for_edit;
                $new_question_revision['question_id'] = $question['id'];
                $new_question_revision['question_editor_user_id'] = $request->user()->id;
                $new_question_revision['action'] = $revision_action;
                $new_question_revision['created_at'] = now();
                $new_question_revision['updated_at'] = now();

                unset($new_question_revision['id']);
                $newQuestionRevision = QuestionRevision::create($new_question_revision);
                $new_question_revision_id = $newQuestionRevision->id;

                if ($request->question_type === 'report') {
                    $question->addRubricCategories($request->rubric_categories, $new_question_revision_id);
                }

                switch ($revision_action) {
                    case('propagate'):
                        $assignment_questions = AssignmentSyncQuestion::where('question_id', $question->id)->get();
                        foreach ($assignment_questions as $assignment_question) {
                            $assignment_question->question_revision_id = $new_question_revision_id;
                            if ($rubric_items_exist && !$assignment_question->custom_rubric) {
                                $assignment_question->use_existing_rubric = 1;
                            }
                            $assignment_question->save();
                        }
                        DB::table('pending_question_revisions')
                            ->where('question_id', $question->id)
                            ->delete();
                        break;
                    case('notify'):
                        //set the current one
                        DB::table('assignment_question')
                            ->where('question_id', $question->id)
                            ->update(['question_revision_id' => $currentQuestionRevision->id]);
                        $assignment_ids_with_the_question = $assignmentSyncQuestion->getAssignmentIdsWithTheQuestion($question);
                        //regular open assignments


                        $open_assignment_ids = $assignment->getOpenAssignmentIdsFromSubsetOfAssignmentIds($assignment_ids_with_the_question);

                        $open_assignment_ids = array_unique($open_assignment_ids);
                        $formative_assignment_ids = DB::table('assignments')
                            ->whereIn('id', $assignment_ids_with_the_question)
                            ->where('formative', 1)
                            ->select('id')
                            ->pluck('id')
                            ->toArray();


                        $assignment_courses_with_auto_update_question_revision = DB::table('assignments')
                            ->join('courses', 'assignments.course_id', '=', 'courses.id')
                            ->where('courses.auto_update_question_revisions', 1)
                            ->whereIn('assignments.id', $assignment_ids_with_the_question)
                            ->select('assignments.id AS assignment_id', 'courses.id AS course_id')
                            ->get();

                        $potential_course_ids = [];
                        foreach ($assignment_courses_with_auto_update_question_revision as $assignment_course_with_auto_update_question_revision) {
                            $potential_course_ids[] = $assignment_course_with_auto_update_question_revision->course_id;
                        }
                        $courses_with_real_students = DB::table('enrollments')
                            ->join('users', 'enrollments.user_id', '=', 'users.id')
                            ->where(function ($query) {
                                $query->where('fake_student', 0)
                                    ->orWhere('formative_student', 1);
                            })
                            ->whereIn('course_id', $potential_course_ids)
                            ->select('course_id')
                            ->get()
                            ->pluck('course_id')
                            ->toArray();

                        $assignment_ids_from_courses_with_auto_update_question_revision_without_students = [];
                        foreach ($assignment_courses_with_auto_update_question_revision as $key => $assignment_course_with_auto_update_question_revision) {
                            if (!in_array($assignment_course_with_auto_update_question_revision->course_id, $courses_with_real_students)) {
                                $assignment_ids_from_courses_with_auto_update_question_revision_without_students[] = $assignment_course_with_auto_update_question_revision->assignment_id;
                            }
                        }

                        $updatable_assignment_ids = array_unique(array_merge($open_assignment_ids, $formative_assignment_ids, $assignment_ids_from_courses_with_auto_update_question_revision_without_students));
                        $owner_assignment_ids = DB::table('assignments')
                            ->join('courses', "assignments.course_id", "=", "courses.id")
                            ->where("courses.user_id", $request->user()->id)
                            ->get("assignments.id")
                            ->pluck('id')
                            ->toArray();
                        $open_assignment_ids_in_owner_course = $assignment->openAssignmentIdsInOwnerCourse($request->user(), $open_assignment_ids);


                        foreach ($updatable_assignment_ids as $key => $updatable_assignment_id) {
                            $auto_update_at_course_level = in_array($updatable_assignment_id, $assignment_ids_from_courses_with_auto_update_question_revision_without_students);


                            $auto_update_at_owner_level = in_array($updatable_assignment_id, $owner_assignment_ids) && $automatically_update_revision;
                            $update_question_revision = $auto_update_at_course_level || $auto_update_at_owner_level;
                            if (in_array($updatable_assignment_id, $open_assignment_ids_in_owner_course) && $request->automatically_update_revision === "0") {
                                //override by the owner not to update for open assignments
                                $update_question_revision = false;
                            }

                            if ($update_question_revision) {
                                $assignmentSyncQuestion->where('assignment_id', $updatable_assignment_id)
                                    ->where('question_id', $question->id)
                                    ->update(['question_revision_id' => $newQuestionRevision->id]);
                                $assignment = Assignment::find($updatable_assignment_id);
                                $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);
                                Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
                                unset($updatable_assignment_ids[$key]);
                            } else {
                                $pendingQuestionRevision = new PendingQuestionRevision();
                                $pending_question_revision = $pendingQuestionRevision->where('assignment_id', $updatable_assignment_id)
                                    ->where('question_id', $question->id)
                                    ->first();
                                if (!$pending_question_revision) {
                                    $pendingQuestionRevision->assignment_id = $updatable_assignment_id;
                                    $pendingQuestionRevision->question_id = $question->id;
                                    $pendingQuestionRevision->question_revision_id = $new_question_revision_id;
                                    $pendingQuestionRevision->save();
                                } else {
                                    $pending_question_revision->question_revision_id = $new_question_revision_id;
                                    $pending_question_revision->save();
                                }
                            }
                        }
                        break;
                    case('none'):

                        break;
                    default:
                        throw new Exception("$revision_action is not a valid action for saving questions.");
                }
            } else {

                if ($request->bulk_upload_into_assignment
                    && $data['technology'] !== 'text'
                    && !$request->nursing_question) {

                    if ($data['technology'] === 'qti'
                        && isset($data['qti_json_type'])
                        && $data['qti_json_type'] === 'flashcard') {
                        // Deduplicate flashcards by term (front) and answer (back)
                        // using the indexed qti_json_type column + JSON extract.
                        $card = json_decode($request->qti_json, true)['card'];
                        $question = Question::where('qti_json_type', 'flashcard')
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(qti_json, '$.card.term')) = ?", [$card['term']])
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(qti_json, '$.card.answer')) = ?", [$card['answer']])
                            ->first();
                        if (!$question) {
                            $question = Question::create($data);
                            $question->page_id = $question->id;
                            $question->save();
                        }
                    } else {
                        // Original deduplication path for webwork/h5p/imathas
                        $question = Question::where('technology', $data['technology'])
                            ->where('technology_id', $data['technology_id'] ?? null)
                            ->where('version', 1)
                            ->first();
                        if (!$question) {
                            $question = Question::create($data);
                            $question->page_id = $question->id;
                            $question->save();
                        }
                    }
                } else {
                    $question = Question::create($data);
                    $question->page_id = $question->id;
                    $question->save();
                }
            }
            if (!$is_update && $data['technology'] === 'h5p') {
                try {
                    $h5p_info = $question->getH5pInfo($data['technology_id']);
                    if (!$h5p_info['success']) {
                        throw new Exception("Could not get h5p info for question $question->id.");
                    }
                    $question->h5p_type = $h5p_info['h5p_type'];
                    $question->source_url = $h5p_info['source_url'];
                    $question->save();
                } catch (Exception $e) {
                    $h = new Handler(app());
                    $h->report($e);

                }
            }

            if ($data['question_type'] === 'report') {
                if (!$is_update) {
                    $question->addRubricCategories($request->rubric_categories, 0);
                }
            } else {
                DB::table('rubric_categories')->where('question_id', $question->id)->delete();
            }


            if (
                isset($data['technology']) && $data['technology'] === 'webwork'
                && !empty($data['webwork_code'])
            ) {

                $macroService = app(WebworkMacroService::class);
                $macroService->syncMacrosForQuestion(
                    $question->id,
                    $new_question_revision_id,   // 0 for brand-new questions
                    $data['webwork_code']
                );
            }

            $question->addTags($tags);
            $question->addFrameworkItems($request->framework_item_sync_question);
            $question->saveFormat();
            $question->non_technology_html = $non_technology_text;
            $assignment_name = '';
            if ($media_uploads) {
                if ($is_update) {
                    $current_s3_keys = QuestionMediaUpload::where('question_id', $question->id)
                        ->get()
                        ->pluck('s3_key')
                        ->toArray();
                    foreach ($media_uploads as $new_media_upload) {
                        $questionMediaUpload = new QuestionMediaUpload();
                        $question_media_upload_dir = $questionMediaUpload->getDir();
                        if (isset($new_media_upload['text']) && $new_media_upload['text']) {
                            $questionMediaUpload->text = $new_media_upload['text'];
                            if (Storage::disk('s3')->exists("$question_media_upload_dir/pending-{$new_media_upload['s3_key']}")) {
                                $text = Storage::disk('s3')->get("$question_media_upload_dir/pending-{$new_media_upload['s3_key']}");
                                $questionMediaUpload->text = $text;
                                Storage::disk('s3')->put("$question_media_upload_dir/{$new_media_upload['s3_key']}", $text);
                                Storage::disk('s3')->delete("$question_media_upload_dir/pending-{$new_media_upload['s3_key']}", $text);
                            }
                        }
                        $questionMediaUpload->question_id = $question->id;
                        $questionMediaUpload->original_filename = $new_media_upload['original_filename'];
                        $questionMediaUpload->size = $new_media_upload['size'];
                        $questionMediaUpload->s3_key = $new_media_upload['s3_key'];
                        $questionMediaUpload->order = $new_media_upload['order'] ?? null;
                        $questionMediaUpload->transcript = '';
                        $questionMediaUpload->question_revision_id = $new_question_revision_id;
                        $questionMediaUpload->show_captions = $show_captions;
                        $questionMediaUpload->save();
                        if (!in_array($new_media_upload['s3_key'], $current_s3_keys)) {
                            InitProcessTranscribe::dispatch($questionMediaUpload->s3_key, 'question_media_upload');
                        }
                    }
                } else {
                    foreach ($media_uploads as $media_upload) {
                        $questionMediaUpload = new QuestionMediaUpload();
                        $questionMediaUpload->question_id = $question->id;
                        $questionMediaUpload->original_filename = $media_upload['original_filename'];
                        $questionMediaUpload->size = $media_upload['size'];
                        $questionMediaUpload->s3_key = $media_upload['s3_key'];
                        if (isset($media_upload['order'])) {
                            $questionMediaUpload->order = $media_upload['order'];
                        }
                        $questionMediaUpload->transcript = '';
                        $questionMediaUpload->save();
                        InitProcessTranscribe::dispatch($questionMediaUpload->s3_key, 'question_media_upload');
                    }

                }
            }
            if ($request->course_id) { //for bulk uploads
                $assignment = DB::table('assignments')
                    ->join('courses', "assignments.course_id", "=", "courses.id")
                    ->where('course_id', $request->course_id)
                    ->where('courses.user_id', $request->user()->id)
                    ->where("assignments.name", $request->assignment)
                    ->select("assignments.id")
                    ->first();
                if (!$assignment) {
                    $response['message'] = "That is not one of your courses.";
                    return $response;
                }
                $assignment = Assignment::find($assignment->id);
                $assignment_id = $assignment->id;
                if (!in_array($question->id, $assignment->questions->pluck('id')->toArray())) {
                    $assignment_question_id = $assignmentSyncQuestion
                        ->store($assignment, $question, new BetaCourseApproval());
                    if ($request->topic) {
                        $topic_id = DB::table('assignment_topics')
                            ->where('assignment_id', $assignment_id)
                            ->where('name', $request->topic)
                            ->first()
                            ->id;
                        DB::table('assignment_question')
                            ->where('id', $assignment_question_id)
                            ->update(['assignment_topic_id' => $topic_id]);
                    }
                }
            }
            if ($request->assignment_id) { //for creating a new question within an assignment
                $assignment = Assignment::find($request->assignment_id);
                $course = Course::find($assignment->course_id);
                if (!$course->ownsCourseOrIsCoInstructor($request->user()->id)) {
                    DB::rollBack();
                    $response['message'] = "That is not one of your assignments.";
                    return $response;
                }
                $assignment_name = $assignment->name;
                if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
                    DB::rollBack();
                    $response['message'] = "You cannot add a question since there are already submissions and this assignment computes points using question weights.";
                    return $response;
                }
                if (!in_array($question->id, $assignment->questions->pluck('id')->toArray())) {
                    $assignmentSyncQuestion->store($assignment, $question, new BetaCourseApproval());
                }
            }
            if ($request->technology === 'webwork' && $request->new_auto_graded_code === 'webwork') {
                $webwork = new Webwork();
                $webwork_dir = $webwork->getDir($question->id, $new_question_revision_id);

                if (app()->environment() === 'testing' && $request->check_webwork_dir) {
                    DB::commit();
                    $response['webwork_dir'] = $webwork_dir;
                    $response['type'] = 'success';
                    return $response;
                }
                if ($is_update) {
                    $lastRevision = QuestionRevision::where('question_id', $question->id)
                        ->where('revision_number', $currentQuestionRevision->revision_number - 1)
                        ->first();
                    if (($lastRevision && $lastRevision->technology === 'webwork') || (!$lastRevision && $original_question_technology === 'webwork')) {
                        $source_dir = $lastRevision ? "$question->id-$currentQuestionRevision->id" : $question->id;
                        $webwork->cloneDir($source_dir, $webwork_dir);
                    }
                }
                $webwork->storeQuestion($question->webwork_code, $webwork_dir);


                $question->updateQuestionRevisionWebworkPath($webwork_dir, $new_question_revision_id);
                foreach ($request->webwork_attachments as $webwork_attachment) {
                    if ($webwork_attachment['status'] === 'pending') {
                        $s3_key = $webwork_attachment['s3_key'];
                        if (!Storage::disk('s3')->exists($s3_key)) {
                            throw new Exception("Could not locate the webwork attachment on S3: $s3_key");
                        }
                        $file_contents = Storage::disk('s3')->get($s3_key);
                        $mime_type = Storage::disk('s3')->mimeType($s3_key);
                        $webwork->storeAttachment($webwork_attachment['filename'], $file_contents, $webwork_dir, $mime_type);
                        Storage::disk('s3')->delete($s3_key);
                    }
                    $webworkAttachment = new WebworkAttachment();
                    $webworkAttachment->width = $webwork_attachment['width'] ?? null;
                    $webworkAttachment->height = $webwork_attachment['height'] ?? null;
                    $webworkAttachment->filename = $webwork_attachment['filename'];
                    $webworkAttachment->question_id = $question->id;
                    $webworkAttachment->question_revision_id = $new_question_revision_id;
                    $webworkAttachment->save();
                }
            }
            DB::table('empty_learning_tree_nodes')->where('question_id', $question->id)->delete();

            DB::commit();
            // Dispatch TTS generation for flashcard questions (text-based sides only).
            // $new_question_revision_id is 0 for brand-new questions (no revision row yet).
            if (($data['qti_json_type'] ?? '') === 'flashcard' && !app()->environment('testing')) {
                GenerateFlashcardTTS::dispatch($question->id, $new_question_revision_id);
                GenerateFlashcardAudioVTT::dispatch($question->id, $new_question_revision_id);
            }

            $action = $is_update ? 'updated' : 'created';
            $response['message'] = "The question has been $action.";
            $action = $is_update ? 'updated' : 'created';
            $response['message'] = "The question has been $action.";
            $response['question_id'] = $question->id;
            if ($request->assignment_id) {
                $response['message'] .= "  In addition, it has been added to $assignment_name.";
            }
            $response['url'] = $technology_id ? $question->getTechnologyURLFromTechnology($data['technology'], $data['technology_id']) : null;


            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save this question.  Please try again or contact us for assistance.";
        }

        return $response;
    }

    /**
     * @param StoreQuestionRequest $request
     * @param string $h5p_id
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function storeH5P(Request  $request,
                      string   $h5p_id,
                      Question $question): array

    {

        $response['type'] = 'error';
        $assignment_id = $request->assignment_id ?: 0;
        $authorized = Gate::inspect('storeH5P', [$question, $assignment_id]);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $response = $question->storeImportedH5P($request->user()->id, $h5p_id, $request->folder_id, $assignment_id);
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save this question.  Please try again or contact us for assistance.";
        }

        return $response;
    }


    /**
     * @param Request $request
     * @return Application|ResponseFactory|Response
     */
    public
    function setQuestionUpdatedAtSession(Request $request)
    {
        $cookie = cookie()->forever('loaded_question_updated_at', $request->loaded_question_updated_at);
        $response['loaded_question_updated_at'] = $request->loaded_question_updated_at;
        return response($response)->withCookie($cookie);
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function initRefreshQuestion(Assignment             $assignment,
                                 Question               $question,
                                 AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        $response['type'] = 'error';

        try {
            if (!Helper::isAdmin() && $assignment->isBetaAssignment()) {
                $response['message'] = "You cannot refresh this question since this is a Beta assignment. Please contact the Alpha instructor to request the refresh.";
                return $response;
            }

            $response['question_has_auto_graded_or_file_submissions_in_other_assignments'] = $assignmentSyncQuestion->questionHasAutoGradedOrFileSubmissionsInOtherAssignments($assignment, $question);
            $response['question_has_auto_graded_or_file_submissions_in_this_assignment'] = $assignmentSyncQuestion->questionHasSomeTypeOfRealStudentSubmission($assignment, $question);
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to determine the submission and assignment status for this question.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Question $question
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param RefreshQuestionRequest $refreshQuestionRequest
     * @return array
     * @throws Exception
     */
    public
    function refresh(Request                $request,
                     Question               $question,
                     Assignment             $assignment,
                     AssignmentSyncQuestion $assignmentSyncQuestion,
                     RefreshQuestionRequest $refreshQuestionRequest): array
    {

        try {

            $response['type'] = 'error';
            $authorized = Gate::inspect('refreshQuestion', [$question, $assignmentSyncQuestion, $assignment]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }


            DB::beginTransaction();

            if ($request->update_scores
                && !$assignmentSyncQuestion->questionHasAutoGradedOrFileSubmissionsInOtherAssignments($assignment, $question)) {
                $assignmentSyncQuestion->updateAssignmentScoreBasedOnRemovedQuestion($assignment, $question);

                Helper::removeAllStudentSubmissionTypesByAssignmentAndQuestion($assignment->id, $question->id);
            }

            if ($question->library !== 'adapt') {
                $question->getQuestionIdsByPageId($question->page_id, $question->library, 1);
            }

            $refreshed_question = $refreshQuestionRequest->where('question_id', $question->id)->first();
            if ($refreshed_question) {
                //it may not be there if the Admin does it right from a page
                $refreshed_question->status = 'approved';
                $refreshed_question->save();
            }

            DB::commit();
            $updated_scores_message = $request->update_scores
                ? "All submissions have been removed and your students will need to re-submit."
                : '';
            $response['message'] = "The question has been refreshed.  $updated_scores_message ";

            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to refresh the question.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function refreshProperties(Question $question): array
    {

        try {

            $response['type'] = 'error';
            $authorized = Gate::inspect('refreshProperties', [$question]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $response['type'] = 'error';
            if ($question->library !== 'adapt') {
                $question->refreshProperties();
            }
            $response['solution_html'] = $question->solution_html ?: null;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to update the question's properties. Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @return array|string|null
     */
    public
    function getDefaultImportLibrary(Request $request)
    {
        $response['default_import_library'] = $request->cookie('default_import_library') ?? null;
        return $response;
    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function updateProperties(Request $request, Question $question): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('updateProperties', $question);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $question->auto_attribution = $request->auto_attribution;
            $question->attribution = !$request->auto_attribution ? $request->attribution : null;
            $question->private_description = $request->private_description;
            $question->save();
            $response['type'] = 'success';
            $response['message'] = "The question's properties have been updated.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to update the question's properties. Please try again or contact us for assistance.";
        }
        return $response;


    }

    public
    function storeDefaultImportLibrary(Request $request, Libretext $libretext)
    {
        $response['type'] = 'error';
        $libraries = $libretext->libraries();
        $library = $request->default_import_library;
        $cookie = cookie()->forever('default_import_library', null);
        try {
            if ($library === null || in_array($library, $libraries)) {
                $cookie = cookie()->forever('default_import_library', $request->default_import_library);
                $response['type'] = 'success';
                $response['message'] = 'Your default import library has been updated.';
            } else {
                $response['message'] = 'That is not a valid library';
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your default import setting. Please try again or contact us for assistance.";

        }
        return response($response)->withCookie($cookie);

    }

    /**
     * @param Request $request
     * @param Question $Question
     * @param Assignment $assignment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Libretext $libretext
     * @param BetaCourseApproval $betaCourseApproval
     * @param Forge $forge
     * @return array
     * @throws Exception
     */
    public
    function directImportQuestion(Request                $request,
                                  Question               $Question,
                                  Assignment             $assignment,
                                  AssignmentSyncQuestion $assignmentSyncQuestion,
                                  Libretext              $libretext,
                                  BetaCourseApproval     $betaCourseApproval,
                                  Forge                  $forge): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('update', $assignment);

        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        if ($assignment->cannotAddOrRemoveQuestionsForQuestionWeightAssignment()) {
            $response['message'] = "You cannot import questions to this assignment since there are already submissions and this assignment computes points using question weights.";
            return $response;
        }


        try {
            $direct_import = $request->direct_import;
            $type = $request->type;
            if (!in_array($type, ['libretexts id', 'adapt id'])) {
                $response['message'] = "$type is not a valid direct import type.";
                return $response;
            }
            if (!$direct_import) {
                $response['message'] = "You didn't submit anything for direct import.";
                return $response;
            }
            $question_to_add_info = ($type === 'libretexts id')
                ? $Question->getQuestionToAddByPageId($request, $libretext)
                : $Question->getQuestionToAddByAdaptId($request, $assignment->course->formative || $assignment->formative);
            if ($question_to_add_info['type'] === 'error') {
                $response['message'] = $question_to_add_info['message'];
                return $response;
            }

            $question_id = $question_to_add_info['question_id'];
            $open_ended_submission_type = Question::find($question_id)->open_ended_submission_type;
            $direct_import_id = $question_to_add_info['direct_import_id'];
            DB::beginTransaction();
            $assignment_questions = $assignment->questions->pluck('id')->toArray();
            $open_ended_submission_type = $open_ended_submission_type ?: $assignment->default_open_ended_submission_type;
            $open_ended_text_editor = $assignment->default_open_ended_text_editor;
            $custom_rubric = null;
            if ($type === 'adapt id') {
                $assignment_question = $question_to_add_info['assignment_question'];
                if ($assignment_question) {
                    $open_ended_submission_type = $assignment_question->open_ended_submission_type;
                    $open_ended_text_editor = $assignment_question->open_ended_text_editor;
                    $custom_rubric = $assignment_question->custom_rubric;
                }
            }
            if (!in_array($question_id, $assignment_questions)) {
                $assignmentSyncQuestion->addQuestiontoAssignmentByQuestionId($assignment,
                    $question_id,
                    $assignmentSyncQuestion,
                    $open_ended_submission_type,
                    $open_ended_text_editor,
                    $betaCourseApproval,
                    $custom_rubric);
                $response['direct_import_id_added_to_assignment'] = $direct_import_id;
            } else {
                $response['direct_import_id_not_added_to_assignment'] = $direct_import_id;
            }
            DB::commit();
            $response['type'] = 'success';
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error importing the question.  Please try again or contact us for assistance.";
        }
        return $response;


    }


    /**
     * @param Request $request
     * @param Question $question
     * @param RefreshQuestionRequest $refreshQuestionRequest
     * @return array
     * @throws Exception
     */
    public
    function compareCachedAndNonCachedQuestions(Request                $request, Question $question,
                                                RefreshQuestionRequest $refreshQuestionRequest): array
    {
        $response['type'] = 'error';
        /* $authorized = Gate::inspect('refreshQuestion', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }*/
        try {
            $question_info = Question::select(' * ')
                ->where('id', $question->id)->first();
            $response['cached_question'] = $question->formatQuestionFromDatabase($request, $question_info);
            $response['uncached_question_src'] = "https://{$question_info['library']}.libretexts.org/@go/page/{$question_info['page_id']}";
            $response['nature_of_update'] = $refreshQuestionRequest->where('question_id', $question->id)
                ->select('nature_of_update')
                ->pluck('nature_of_update')
                ->first();

            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to retrieve the old and new questions.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function getWebworkCodeFromFilePath(Request  $request,
                                        Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getWebworkCodeFromFilePath', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        //allow for assignment-question
        $pattern = ' /^[^-]*-[^-]*$/';
        if (preg_match($pattern, $request->file_path)) {
            $parts = explode('-', $request->file_path);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                $request->file_path = $parts[1];
            } else {
                $response['message'] = "That does not look like a valid ADAPT ID or WebWork File Path.";
                return $response;
            }

        }

        try {
            if (is_numeric($request->file_path)) {
                $webwork_question = $question
                    ->where('id', $request->file_path)
                    ->where('technology', 'webwork')
                    ->first();
                if ($webwork_question) {
                    $webwork_code = $webwork_question->webwork_code ?: $question->getWebworkCodeFromFilePath($webwork_question->technology_id);
                } else {
                    $response['message'] = "That question is not a weBWork question.";
                    return $response;
                }
            } else {
                $webwork_code = $question->getWebworkCodeFromFilePath($request->file_path);
            }
            $response['webwork_code'] = $webwork_code;
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting the code from this filepath.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Question $question
     * @param Tag $Tag
     * @return array|StreamedResponse
     * @throws Exception
     */
    public
    function exportWebworkCode(Question $question,
                               Tag      $Tag)
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('exportWebworkCode', $question);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        $question = Question::where('technology', 'webwork')
            ->where('id', $question->id)
            ->first();
        if (!$question) {
            $response['error'] = "This is not a weBWork question.";
            return $response;
        }
        try {
            $webwork_code = $question->webwork_code ?: $question->getWebworkCodeFromFilePath($question->technology_id);
            $usable_tags = $Tag->getUsableTags([$question->id]);
            $tags = [];
            foreach ($usable_tags as $tag) {
                $tags[] = $tag->tag;
            }
            $comma_separated_tags = $tags ? implode(', ', $tags) : '';
            $meta_tags = "## Meta-tags\r\n";
            $meta_tags .= "## Title: $question->title\r\n";
            $meta_tags .= "## Author: $question->author\r\n";
            if ($question->license) {
                $meta_tags .= "## License: $question->license\r\n";
            }
            if ($question->license_version) {
                $meta_tags .= "## License version: $question->license_version\r\n";
            }
            if ($comma_separated_tags) {
                $meta_tags .= "## Tags: $comma_separated_tags\r\n";
            }
            $source = $question->webwork_code ? "ADAPT" : $question->technology_id;
            $meta_tags .= "## Source: $source\r\n";

            $meta_tags .= "## End Meta-tags \r\n\r\n";
            $filename = preg_replace(' /[^a-z0-9]+/', '-', strtolower($question->title));
            return response()->streamDownload(function () use ($meta_tags, $webwork_code) {
                echo $meta_tags . $webwork_code;
            }, "$filename.pg");
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting exporting this code.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Question $question
     * @param IMathAS $IMathAS
     * @return array
     * @throws Exception
     */
    public
    function preview(Request  $request,
                     Question $question,
                     IMathAS  $IMathAS): array
    {
        $response['type'] = 'error';
        try {
            $authorized = Gate::inspect('preview', $question);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $question['non_technology_iframe_src'] = null;
            $question['solution_html'] = $request->solution_html;
            $question['solution_type'] = $request->solution_html ? 'html' : null;
            $page_id = $request->user()->id;
            $user_id = request()->user()->id;
            $request->non_technology_text
                ? Storage::disk('s3')->put("preview/$user_id.php", $request->non_technology_text)
                : Storage::disk('s3')->delete("preview/$user_id.php");
            $question['library'] = 'preview';
            $question['page_id'] = $page_id;
            if ($request->non_technology_text) {
                $question['non_technology'] = $request->non_technology_text;
                $question['non_technology_iframe_src'] = "/api/get-header-html/preview";
            }
            $question['technology_iframe_src'] = null;
            $iframe_id = substr(sha1(mt_rand()), 17, 12);
            if ($request->technology !== 'text') {
                if ($request->technology === 'webwork' && $request->webwork_code) {
                    $webwork = new Webwork();
                    $webwork->storeQuestion($request->webwork_code, "preview/{$request->user()->id}");
                    if ($request->session_identifier) {
                        if ($request->pending_webwork_attachments) {
                            foreach ($request->pending_webwork_attachments as $pending_webwork_attachment) {
                                $s3_key = $pending_webwork_attachment['s3_key'];
                                if (Storage::disk('s3')->exists($s3_key)) {
                                    $file_contents = Storage::disk('s3')->get($s3_key);
                                    $mime_type = Storage::disk('s3')->mimeType($s3_key);
                                    $webwork->storeAttachment(
                                        $pending_webwork_attachment['filename'],
                                        $file_contents,
                                        "preview/{$request->user()->id}",
                                        $mime_type
                                    );
                                }
                            }
                        }
                    }

                    $problemJWT = $question->getPreviewWebworkProblemJWT("LibreTexts/ww_files/preview/{$request->user()->id}/code.pg");
                    $domain = $question->getWebworkDomain();
                    $endpoint = $question->getWebworkEndpoint();
                    $question['technology_iframe_src'] = "$domain/$endpoint?problemJWT=$problemJWT";
                    if ($webwork->algorithmicSolution($request) || $webwork->inCodeSolution($request)) {
                        $question['technology_iframe'] = $question['technology_iframe_src'];
                        $question['solution_html'] = null;
                        $question['solution_type'] = 'html';
                        $question['render_webwork_solution'] = true;
                    }
                } else {
                    $problem_jwt = '';
                    $question['imathas_solution'] = false;
                    $question['solution_type'] = '';
                    if ($request->technology === 'imathas') {
                        $preview_question = new Question();
                        $preview_question->technology = 'imathas';
                        $preview_question->technology_id = $request->technology_id;
                        if ($IMathAS->solutionExists($preview_question)) {
                            $seed = config('myconfig.imathas_seed');
                            $assignment = new Assignment();
                            $assignment->id = 0;//placeholder
                            $preview_question->technology_iframe = $request->technology_iframe;
                            $preview_question->technology_src = $request->technology_iframe_src;
                            $technology_src_and_problemJWT = $question->getTechnologySrcAndProblemJWT($request, $assignment, $preview_question, $seed, true, new DOMDocument(), new JWE());
                            $question['problem_jwt'] = $technology_src_and_problemJWT['problemJWT'];
                            $question['imathas_solution'] = true;
                            $question['solution_type'] = 'html';
                        }
                    }
                    $technology_iframe = $question->getTechnologyIframeFromTechnology($request->technology, $request->technology_id);
                    $question['technology_iframe_src'] = $this->formatIframeSrc($technology_iframe, $iframe_id, $problem_jwt);
                }
            }


            $question['id'] = 'some-id-that-is-not-really-an-id';//just a placeholder for previews
            $response['type'] = 'success';
            $response['question'] = $question;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = $e->getMessage();
        }

        return $response;


    }


    /**
     * @param string $library
     * @param int $page_id
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function getQuestionByLibraryAndPageId(string $library, int $page_id, Question $question): array
    {
        if ($library !== 'adapt') {
            $question->cacheQuestionFromLibraryByPageId($library, $page_id);
        }
        $question_to_show = $question->where('library', $library)->where('page_id', $page_id)->first();
        if (!$question_to_show) {
            $response['type'] = 'error';
            $response['message'] = "There is no question in the ADAPT library with page id $page_id.";
            return $response;
        }
        return $this->show($question_to_show, new Question());
    }

    /**
     * @param Request $request
     * @param Question $Question
     * @return array
     * @throws Exception
     */
    public
    function show(Request $request, Question $Question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('viewAny', $Question);
        $questionMediaUpload = new QuestionMediaUpload();
        if (!$authorized->allowed()) {

            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';
        $webwork = new Webwork();
        try {
            $question_info = Question::find($Question->id);
            $question_info = $question_info->updateWithQuestionRevision('latest');
            $render_webwork_solution = $webwork->algorithmicSolution($question_info);
            $question_revision_id = $question_info->question_revision_id;
            $question = $Question->formatQuestionFromDatabase($request, $question_info);
            if ($question_info->qti_json_type === 'forge') {
                $solution_html = null;
                if ($question_info->solution_html) {
                    $solution_html = $question_info->solution_html;
                } else if ($question_info->answer_html) {
                    $solution_html = $question_info->answer_html;
                }
                $qti_answer_json = json_decode($question['qti_answer_json'], 1);
                $qti_answer_json['solution_html'] = $solution_html;
                $question['qti_answer_json'] = json_encode($qti_answer_json);
            }
            if ($question_info->isDiscussIt()) {
                $qti_json = json_decode($question['qti_json'], 1);
                $qti_json['media_uploads'] = $question['media_uploads'];
                $question['qti_json'] = json_encode($qti_json);
            }
            $question['render_webwork_solution'] = $render_webwork_solution;
            $question['rubric_categories'] = DB::table('rubric_categories')
                ->where('question_id', $Question->id)
                ->where('question_revision_id', $question_revision_id)
                ->orderBy('order')
                ->get();
            $user = request()->user();
            if (Helper::isAdmin()) {
                $can_edit = true;
            } else if ($user->role === 5) {
                $can_edit = true;
                $question_editor = User::find($question_info->question_editor_user_id);
                if ($question_editor->role !== 5) {
                    $can_edit = false;
                }
            } else {
                $can_edit = (int)$user->id == $question_info->question_editor_user_id && ($user->role === 2);
            }
            $response['type'] = 'success';
            $question['can_edit'] = $can_edit;
            $response['question'] = $question;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting that question.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param Question $question
     * @return array
     * @throws Exception
     */
    public
    function getQuestionToEdit(Request $request, Question $question): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('getQuestionForEditing', $question);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }

        $response['type'] = 'error';
        try {
            $question_to_edit = Question::select('*')
                ->where('id', $question->id)->first();
            if ($question_to_edit) {
                $question_revision_id = $question->latestQuestionRevision('id') ? $question->latestQuestionRevision('id') : 0;
                $question_to_edit = $question->formatQuestionToEdit($request, $question_to_edit, $question_to_edit->id, $question_revision_id);
                $response['question_to_edit'] = $question_to_edit;
                $question_to_edit['question_exists_in_own_assignment'] = $question->questionExistsInOneOfTheirAssignments();
                $question_to_edit['question_exists_in_another_instructors_assignment'] = $question->questionExistsInAnotherInstructorsAssignments();
                $response['type'] = 'success';
                $response['question_to_edit'] = $question_to_edit;
                //dd($response);
            } else {
                $response['message'] = 'We were not able to locate that question in our database.';
            }

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error getting that question.  Please try again or contact us for assistance.";
        }
        return $response;

    }


    public
    function getQuestionIdsByWordTags(Request $request)
    {
        $chosen_tags = DB::table('tags')
            ->whereIn('tag', $request->get('tags'))
            ->get()
            ->pluck('id');

        if ($chosen_tags->isEmpty()) {
            echo json_encode([
                'type' => 'error',
                'message' => 'We could not find the tags in our database.']);
            exit;

        }
        $question_ids_grouped_by_tag = [];
        //get all of the question ids for each of the tags
        foreach ($chosen_tags as $key => $chosen_tag) {
            $question_ids_grouped_by_tag[$key] = DB::table('question_tag')
                ->select('question_id')
                ->where('tag_id', '=', $chosen_tag)
                ->get()
                ->pluck('question_id')->toArray();
            if (!$question_ids_grouped_by_tag[$key]) {
                echo json_encode(['type' => 'error',
                    'message' => 'There are no questions associated with those tags.']);
                exit;
            }
        }
        //now intersect them for each group
        $question_ids = $question_ids_grouped_by_tag[0];
        $intersected_question_ids = [];
        foreach ($question_ids_grouped_by_tag as $question_group) {
            $intersected_question_ids = array_intersect($question_ids, $question_group);
        }
        if (!count($intersected_question_ids)) {
            echo json_encode(['type' => 'error',
                'message' => 'There are no questions associated with those tags.']);
            exit;
        }
        return $intersected_question_ids;
    }

    public
    function validatePageId(Request $request)
    {
        $page_id = false;
        foreach ($request->get('tags') as $tag) {
            if (stripos($tag, 'id=') !== false) {
                $page_id = str_ireplace('id=', '', $tag);
            }
        }

        if ($page_id && (count($request->get('tags')) > 1)) {
            $response['message'] = "If you would like to search by page id, please don't include other tags.";
            echo json_encode($response);
            exit;
        }
        return $page_id;
    }

    /**
     * @param $list
     * @return array
     */
    private
    function _removeCourseInfo($list): array
    {
        return array_values(array_diff($list, ['Assignment', 'Template', 'Topic']));
    }

    /**
     * @param Question $question
     * @param int $question_revision_id
     * @param RubricCategory $rubricCategory
     * @return array
     * @throws Exception
     */
    public
    function getRubricCategories(Question $question, int $question_revision_id, RubricCategory $rubricCategory): array
    {
        try {
            $response['rubric_categories'] = $question->rubricCategories($rubricCategory, $question_revision_id);
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the rubric categories.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Question $question
     * @return array
     */
    public
    function getNonMetaProperties(Question $question): array
    {
        $response['type'] = 'success';
        $response['non_meta_properties'] = $question->nonMetaProperties();
        return $response;
    }

}
