<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentSyncQuestion;
use App\Discussion;
use App\DiscussionComment;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Http\Requests\UpdateDiscussionRequest;
use App\Jobs\InitConvertToMP4;
use App\Jobs\InitProcessTranscribe;
use App\Question;
use App\QuestionMediaUpload;
use App\Score;
use App\Submission;
use App\SubmissionFile;
use App\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\CloudflareStreamable;

class DiscussionCommentController extends Controller
{

    use CloudflareStreamable;

    /**
     * @param Request $request
     * @param DiscussionComment $discussionComment
     * @return void
     * @throws Exception
     */
    public function updateMp4ConversionStatus(Request $request, DiscussionComment $discussionComment)
    {
        try {
            $num_updated = $discussionComment->where('file', $request->filename)->update([
                'mp4_status' => $request->mp4_status,
                'mp4_message' => $request->mp4_message]);
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
    }


    /**
     * @param Request $request
     * @param QuestionMediaUpload $questionMediaUpload
     * @return void
     */
    public function processConvertWebmToMp4(Request $request, QuestionMediaUpload $questionMediaUpload): void
    {
        try {
            $filename = $request->filename;
            $environment = $request->environment;
            DB::table('pending_mp4_conversions')->insert(['filename' => $filename,
                'environment' => $environment,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()]);

            switch ($environment) {
                case('production'):
                    $disk = 'production_s3';
                    break;
                case('staging'):
                    $disk = 'staging_s3';
                    break;
                case('local');
                case('dev'):
                    $disk = 's3';
                    break;
                default:
                    throw new Exception("$environment is not set up in the mp4 conversion endpoint.");
            }
            $adapter = Storage::disk($disk)->getDriver()->getAdapter(); // Get the filesystem adapter
            $client = $adapter->getClient(); // Get the aws client
            $bucket = $adapter->getBucket(); // Get the current bucket
            // Download the file from S3 and save it locally
            $local_path = storage_path('app/' . $questionMediaUpload->getDir() . "/" . $filename);
            $client->getObject([
                'Bucket' => $bucket,
                'Key' => $questionMediaUpload->getDir() . "/" . $filename,
                'SaveAs' => $local_path, // Save the file locally in the storage/app directory
            ]);

            $path_info = pathinfo($local_path);
            $output_filename = $path_info['dirname'] . '/' . $path_info['filename'] . '.mp4';

// Step 1: Get rotation metadata
            exec("ffprobe -v error -select_streams v:0 -show_entries stream_tags=rotate -of default=noprint_wrappers=1:nokey=1 \"$local_path\"", $rotation_output);
            $rotation = !empty($rotation_output) ? (int)$rotation_output[0] : 0;

// Step 2: Get dimensions
            exec("ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of default=noprint_wrappers=1 \"$local_path\"", $dimension_output);
            $width = $height = null;
            foreach ($dimension_output as $line) {
                if (strpos($line, 'width=') === 0) {
                    $width = (int)str_replace('width=', '', $line);
                } elseif (strpos($line, 'height=') === 0) {
                    $height = (int)str_replace('height=', '', $line);
                }
            }

// Step 3: Decide if we need to rotate
            $videoFilter = '';
            $shouldRotate = false;

            if ($rotation === 90) {
                $videoFilter = '-vf "transpose=1"';
                $shouldRotate = true;
            } elseif ($rotation === 180) {
                $videoFilter = '-vf "transpose=1,transpose=1"';
                $shouldRotate = true;
            } elseif ($rotation === 270) {
                $videoFilter = '-vf "transpose=2"';
                $shouldRotate = true;
            }

// Step 4: Build ffmpeg command
            if ($shouldRotate) {
                $cmd = "ffmpeg -i \"$local_path\" $videoFilter -metadata:s:v:0 rotate=0 -c:v libx264 -crf 23 -preset fast -c:a aac -movflags +faststart -y \"$output_filename\"";
            } else {
                $cmd = "ffmpeg -i \"$local_path\" -c:v libx264 -crf 23 -preset fast -c:a aac -movflags +faststart -y \"$output_filename\"";
            }

// Step 5: Execute
            exec($cmd, $ffmpegOutput, $returnVar);


            if ($ffmpegOutput) {
                throw new Error("FFmpeg failed: " . implode("\n", $ffmpegOutput));
            } else {
                $client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $questionMediaUpload->getDir() . "/" . basename($output_filename)
                ]);
                $client->putObject([
                    'Bucket' => $bucket,
                    'Key' => $questionMediaUpload->getDir() . "/" . basename($output_filename),
                    'SourceFile' => $output_filename,
                    'ContentType' => 'video/mp4',  // Set the content type explicitly
                ]);
            }
            $this->sendUpdatedMP4ConversionStatus($environment, $filename, "completed", '');

        } catch (Exception $e) {
            $this->sendUpdatedMP4ConversionStatus($request->environment, $filename, 'error', $e->getMessage());
        }
    }

    public function sendUpdatedMP4ConversionStatus(string $environment,
                                                   string $filename,
                                                   string $status,
                                                   string $message)
    {

        try {
            $response = null;
            switch ($environment) {
                case('production'):
                    $domain = "adapt.libretexts.org";
                    break;
                case('staging'):
                    $domain = "staging-adapt.libretexts.org";
                    break;
                case('dev'):
                    $domain = "dev.adapt.libretexts.org";
                    break;
                case('local'):
                    $domain = "local.adapt:8891";
                    break;
                default:
                    throw new Exception ("There is no domain for $environment so cannot update the transcription status.");
            }

            $key_secret = DB::table('key_secrets')->where('key', 'adapt_transcribe')->first();
            if (!$key_secret) {
                throw new Exception("No key_secret for adapt_transcribe exists.");
            }

            $hmac_key = new HmacKey($key_secret->secret);
            $signer = new HS256($hmac_key);
            $generator = new Generator($signer);

            $jwt = $generator->generate(['convert_to_mp4' => 1]);

            DB::table('pending_mp4_conversions')
                ->where('environment', $environment)
                ->where('filename', $filename)
                ->update(['status' => $status,
                    'message' => $message,
                    'updated_at' => now()]);

            $http = Http::withToken($jwt)
                ->timeout(360)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ]);

            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => '/Applications/MAMP/Library/OpenSSL/cert.pem']);
            }

            $response = $http->patch("https://$domain/api/discussion-comment/mp4-conversion-status", [
                'filename' => $filename,
                'mp4_status' => $status,
                'mp4_message' => $message
            ]);

            if (!$response->successful()) {
                throw new Exception ("Unable to post an update to mp4-conversion-status; check the dev database");
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $message = $response && $response->body() ? $response->body() : $e->getMessage();
            DB::table('pending_mp4_conversions')
                ->where('filename', $filename)
                ->where('filename', $filename)
                ->update(
                    ['status' => 'errorsss',
                        'message' => $message,
                        'updated_at' => now()]
                );

        }
    }

    /**
     * @param DiscussionComment $discussionComment
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array|StreamedResponse
     * @throws Exception
     */
    public function downloadTranscript(DiscussionComment   $discussionComment,
                                       QuestionMediaUpload $questionMediaUpload)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('downloadTranscript', $discussionComment);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $discussion = Discussion::find($discussionComment->discussion_id);
            $assignment_question_info = DB::table('assignments')
                ->join('assignment_question', 'assignments.id', '=', 'assignment_question.assignment_id')
                ->where('assignment_id', $discussion->assignment_id)
                ->where('question_id', $discussion->question_id)
                ->first();

            $assignment_name = str_replace(' ', '_', $assignment_question_info->name);
            $assignment_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '', $assignment_name);
            $question_number = "Question #" . $assignment_question_info->order;
            $created_at = Carbon::parse($discussionComment->created_at)
                ->setTimezone(request()->user()->time_zone);
            $created_by_user = User::find($discussionComment->user_id);
            $created_by_name = $created_by_user->first_name . ' ' . $created_by_user->last_name;
            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key($discussionComment->file);
            if (Storage::disk('s3')->exists("{$questionMediaUpload->getDir()}/$vtt_file")) {
                return Storage::disk('s3')->download("{$questionMediaUpload->getDir()}/$vtt_file", "$assignment_name-$question_number-$created_by_name-$created_at" . '.vtt');
            } else {
                $response['message'] = "We were unable to locate the .vtt file $vtt_file.";

            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not download the .vtt file. Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param DiscussionComment $discussionComment
     * @param QuestionMediaUpload $questionMediaUpload
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public function audioVideoUploadSatisfiedRequirement(Request                $request,
                                                         Assignment             $assignment,
                                                         Question               $question,
                                                         DiscussionComment      $discussionComment,
                                                         QuestionMediaUpload    $questionMediaUpload,
                                                         AssignmentSyncQuestion $assignmentSyncQuestion): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('storeAudioVideoDiscussionComment', [$discussionComment, $assignment->id, $question->id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $min_number_of_seconds_required = 0;
            $s3_key = $request->file;

            $storage_path = app()->environment('local')
                ? app()->storagePath() . "/app/tmp"
                : '/tmp';
            $adapter = Storage::disk('s3')->getDriver()->getAdapter(); // Get the filesystem adapter
            $client = $adapter->getClient(); // Get the aws client
            $bucket = $adapter->getBucket(); // Get the current bucket
            $path = "{$questionMediaUpload->getDir()}/$s3_key";
            if (Storage::disk('s3')->exists($path)) {
                $result = $client->getObject([
                    'Bucket' => $bucket,
                    'Key' => $path,
                    'Range' => 'bytes=0-1048576',
                ]);
            } else {
                throw new Exception ("We are unable to locate the file $path for processing.");
            }


            $fileContent = $result['Body'];
            $local_file_path = $storage_path . "/partial-$s3_key";
            file_put_contents($local_file_path, $fileContent);

            $ffmpegCommand = "ffmpeg -i $local_file_path 2>&1 | grep 'Duration' | awk '{print $2}' | tr -d ,";
            list($returnValue, $duration, $errorOutput) = Helper::runFfmpegCommand($ffmpegCommand);

            if ($returnValue !== 0) {
                throw new Exception ("FFmpeg error getting duration for $local_file_path: $errorOutput)");
            }
            $duration = str_replace("\n", '', $duration);
            $duration = preg_replace('/\.\d+$/', '', $duration);
            $time = Carbon::createFromFormat('H:i:s', $duration);
            $total_seconds = ($time->hour * 3600) + ($time->minute * 60) + $time->second;

            $discuss_it_settings = json_decode($assignmentSyncQuestion->discussItSettings($assignment->id, $question->id), 1);

            if ($discuss_it_settings['min_length_of_audio_video']) {
                $interval = CarbonInterval::fromString($discuss_it_settings['min_length_of_audio_video']);
                $min_number_of_seconds_required = $interval->totalSeconds;
            }
            $response['type'] = 'success';
            $response['file_requirement_satisfied'] = $total_seconds >= $min_number_of_seconds_required;
        } catch (Exception $e) {

            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to determine whether uploading this file would satisfy the requirements.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param DiscussionComment $discussionComment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @return array
     * @throws Exception
     */
    public
    function deletingWillMakeRequirementsNotSatisfied(Request                $request,
                                                      DiscussionComment      $discussionComment,
                                                      AssignmentSyncQuestion $assignmentSyncQuestion): array
    {
        try {
            $response['type'] = 'error';
            $discussion = Discussion::find($discussionComment->discussion_id);
            //for linked (daisy-chained) questions, the comment belongs to the assignment it was made in
            $posted_in_assignment_id = $discussionComment->postedInAssignmentId();
            $authorized = Gate::inspect('deletingWillMakeRequirementsNotSatisfied', [$discussionComment, $posted_in_assignment_id, $discussion->question_id, (int)$request->viewing_assignment_id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $user = User::find($discussionComment->user_id);
            if ($user->role === 2) {
                $response['type'] = 'success';
                $response['deleting_will_make_requirements_not_satisfied'] = false;
            }

            $assignment = Assignment::find($posted_in_assignment_id);
            $satisfied_requirements = $discussionComment->satisfiedRequirements($assignment,
                $discussion->question_id,
                $discussionComment->user_id,
                $assignmentSyncQuestion);

            $satisfied_all_requirements = $satisfied_requirements['satisfied_all_requirements'];
            if (!$satisfied_all_requirements) {
                $response['type'] = 'success';
                $response['deleting_will_make_requirements_not_satisfied'] = false;
            } else {
                DB::beginTransaction();
                $discussionComment->delete();
                $satisfied_requirements = $discussionComment->satisfiedRequirements($assignment,
                    $discussion->question_id,
                    $discussionComment->user_id,
                    $assignmentSyncQuestion);
                $satisfied_all_requirements = $satisfied_requirements['satisfied_all_requirements'];
                $response['type'] = 'success';
                $response['deleting_will_make_requirements_not_satisfied'] = !$satisfied_all_requirements;
                DB::rollback();
                return $response;
            }
        } catch (Exception $e) {
            if (DB::transactionLevel()) {
                DB::rollback();
            }
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to determine whether deleting the comment would make the requirements not satisfied.  Please try again or contact us for assistance.";
        }
        return $response;


    }

    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param QuestionMediaUpload $questionMediaUpload
     * @param DiscussionComment $discussionComment
     * @return array
     * @throws Exception
     */
    public
    function storeAudioDiscussionComment(Request             $request,
                                         Assignment          $assignment,
                                         Question            $question,
                                         QuestionMediaUpload $questionMediaUpload,
                                         DiscussionComment   $discussionComment): array
    {

        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('storeAudioVideoDiscussionComment', [$discussionComment, $assignment->id, $question->id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $file = Helper::storeAudio($request, $questionMediaUpload->getDir());
            $response['type'] = 'success';
            $response['file'] = $file;

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were not able to save your audio comment.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param User $user
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param DiscussionComment $discussionComment
     * @return array
     * @throws Exception
     */
    public
    function satisfiedRequirements(Assignment             $assignment,
                                   Question               $question,
                                   User                   $user,
                                   AssignmentSyncQuestion $assignmentSyncQuestion,
                                   DiscussionComment      $discussionComment): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('getSatisfiedRequirements', [$discussionComment, $assignment->id, $user->id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $discuss_it_settings = json_decode($assignmentSyncQuestion->discussItSettings($assignment->id, $question->id));
            $satisfied_requirements = $discussionComment->satisfiedRequirements($assignment,
                $question->id,
                $user->id,
                $assignmentSyncQuestion);

            $response['type'] = 'success';
            $response['completion_criteria'] = (bool)+$discuss_it_settings->completion_criteria;
            $response['satisfied_requirements'] = $satisfied_requirements;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We are unable to see if the requirements were satisfied.  Please try again or contact us for assistance.";
        }
        return $response;
    }

    /**
     * @param Request $request
     * @param DiscussionComment $discussionComment
     * @param Submission $submission
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function destroy(Request             $request,
                     DiscussionComment   $discussionComment,
                     Submission          $submission,
                     QuestionMediaUpload $questionMediaUpload): array
    {
        try {
            $discussion = DB::table('discussion_comments')
                ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
                ->where('discussion_comments.id', $discussionComment->id)
                ->first();

            $response['type'] = 'error';
            //for linked (daisy-chained) questions, the comment belongs to the assignment it was made in
            $posted_in_assignment_id = $discussionComment->postedInAssignmentId();
            $authorized = Gate::inspect('destroy', [$discussionComment, $posted_in_assignment_id, $discussion->question_id, (int)$request->viewing_assignment_id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $already_graded = $request->user()->role === 3 && $submission->where('assignment_id', $posted_in_assignment_id)
                    ->where('question_id', $discussion->question_id)
                    ->where('user_id', $request->user()->id)
                    ->first();
            if ($already_graded) {
                $response['message'] = "Since this comment is part of a discussion that has already been graded, you cannot delete it.";
                return $response;
            }
            DB::beginTransaction();
            $discussion_id = $discussionComment->discussion_id;


            $discussionComment->delete();
            $deleted_discussion = false;
            if (!DB::table('discussion_comments')->where('discussion_id', $discussion_id)->first()) {
                Discussion::find($discussion_id)->delete();
                $deleted_discussion = true;
            }
            //$questionMediaUpload->deleteFileAndVttFile($discussionComment->file); Holding off on this for now

            $response['type'] = 'info';
            $response['message'] = $deleted_discussion ?
                "The comment and discussion thread have been deleted."
                : "The comment has been deleted.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We are unable to delete this discussion comment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param UpdateDiscussionRequest $request
     * @param DiscussionComment $discussionComment
     * @param AssignmentSyncQuestion $assignmentSyncQuestion
     * @param Submission $submission
     * @param Score $score
     * @param SubmissionFile $submissionFile
     * @return array
     * @throws Exception
     */
    public
    function update(UpdateDiscussionRequest $request,
                    DiscussionComment       $discussionComment,
                    AssignmentSyncQuestion  $assignmentSyncQuestion,
                    Submission              $submission,
                    Score                   $score,
                    SubmissionFile          $submissionFile): array
    {
        $response['type'] = 'error';
        $data = $request->validated();
        $type = $request->type;


        $pasted_comment = $request->pasted_comment ? 1 : 0;
        try {

            $discussion_info = DB::table('discussion_comments')
                ->join('discussions', 'discussion_comments.discussion_id', '=', 'discussions.id')
                ->where('discussion_comments.id', $discussionComment->id)
                ->first();
            $discussion = Discussion::find($discussion_info->discussion_id);
            //for linked (daisy-chained) questions, the comment belongs to the assignment it was made in
            $assignment = Assignment::find($discussionComment->postedInAssignmentId());
            $question = Question::find($discussion_info->question_id);

            $response['type'] = 'error';
            $authorized = Gate::inspect('update', [$discussionComment, $assignment->id, $discussion->question_id, (int)$request->viewing_assignment_id]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }

            $discuss_it_settings = json_decode($assignmentSyncQuestion->discussItSettings($assignment->id, $question->id));
            $satisfied_requirement = $discussionComment->satisfiedRequirement($type, $discuss_it_settings, $request);

            $discussionComment->{$type} = $data[$type];
            $discussionComment->pasted_comment = $pasted_comment;
            $discussionComment->created_at = now();

            if ($type === 'file') {
                $discussionComment->file = $request->file;
                $discussionComment->save();
            }
            if ($type === 'file') {
                InitProcessTranscribe::dispatch($request->file, 'discussion_comment');
            }

            if ($discussionComment->recording_type === 'video' && str_contains($request->file, '.webm')) {
                InitConvertToMP4::dispatch($discussionComment->id, $assignment->id);
            }
            if ($satisfied_requirement) {
                $discussionComment->satisfied_requirement = 1;
            }
            $discussionComment->save();
            $discussion_comment_submission_results = $discussionComment->satisfiedRequirements($assignment, $question->id, $request->user()->id, $assignmentSyncQuestion);
            $discussionComment->updateScore($discuss_it_settings,
                $discussion_comment_submission_results,
                $assignmentSyncQuestion,
                $request->user()->id,
                $assignment,
                $question,
                $submission,
                $submissionFile,
                $score);
            $response['type'] = 'success';
            $response['message'] = 'The comment has been updated.';
            $response['discussion_comment_id'] = $discussionComment->id;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We are unable to update this discussion comment.  Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param Request $request
     * @param string $key
     * @param string $key_id
     * @param int $is_phone
     * @param QuestionMediaUpload $questionMediaUpload
     * @return Application|Factory|View
     * @throws Exception
     */
    public function mediaPlayer(Request             $request,
                                string              $key,
                                string              $key_id,
                                int                 $is_phone,
                                QuestionMediaUpload $questionMediaUpload)
    {

        switch ($key) {
            case('discussion-comment-id'):
                $discussionComment = DiscussionComment::find($key_id);
                $file = $discussionComment->file;
                $type = $discussionComment->getRecordingType();
                break;
            case('filename'):
                $type = 'video';
                $file = $key_id;
                break;
            default:
                return view('media_player_error', ['message' => "$key is not a valid key for the media player"]);

        }
        $vtt_file_exists = false;
        try {
            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key($file);
            $vtt_file_exists = true;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
        }
        $mp4_file = $questionMediaUpload->getDir() . '/' . pathinfo($file, PATHINFO_FILENAME) . '.mp4';
        $mp4_temporary_url = Storage::disk('s3')->exists($mp4_file)
            ? Storage::disk('s3')->temporaryUrl($mp4_file, Carbon::now()->addDays(7))
            : '';

        $vtt_file = $vtt_file_exists
            ? Storage::disk('s3')->temporaryUrl("{$questionMediaUpload->getDir()}/$vtt_file", Carbon::now()->addDays(7))
            : '';
        $temporary_url = Storage::disk('s3')->temporaryUrl("{$questionMediaUpload->getDir()}/$file", Carbon::now()->addDays(7));
        if (!Storage::disk('s3')->exists("{$questionMediaUpload->getDir()}/$file")) {
            return view('media_player_error', ['message' => "The file {$questionMediaUpload->getDir()}/$file was not found on the server."]);
        } else {
            try {
                $disk = Storage::disk('s3');
                $client = $disk->getDriver()->getAdapter()->getClient();
                $bucket = $disk->getDriver()->getAdapter()->getBucket(); // Get the current bucket
                $result = $client->headObject([
                    'Bucket' => $bucket,
                    'Key' => "{$questionMediaUpload->getDir()}/$file",
                ]);
                $sizeBytes = $result['ContentLength'];
                $sizeMB = round($sizeBytes / 1024 / 1024, 2); // convert to MB with 2 decimals
                $endpoint = $request->path();
                $timestamp = now()->format('Y-m-d H:i:s'); // rounds to the nearest second

                DB::table('s3_log')->updateOrInsert(
                    [
                        'endpoint' => $endpoint,
                        's3_key' => "{$questionMediaUpload->getDir()}/$file",
                        'created_at' => $timestamp, // key part, to-the-second precision
                    ],
                    [
                        'filesize' => $sizeMB,
                        'updated_at' => now(),
                    ]
                );
            } catch (Exception $e) {
                $h = new Handler(app());
                $h->report($e);
            }
        }

        $stream_hls_url = null;
        if ($type === 'video') {
            $s3_path = "{$questionMediaUpload->getDir()}/$file";
            $cloudflare_uid = $this->getCloudflareStreamUid($s3_path);
            if ($cloudflare_uid) {
                $stream_hls_url = "https://videodelivery.net/{$cloudflare_uid}/manifest/video.m3u8";
            }
        }

        return view('media_player', ['type' => $type,
            'media_id' => "media_id-$key_id",
            'temporary_url' => $temporary_url,
            'mp4_temporary_url' => $mp4_temporary_url,
            'vtt_file' => $vtt_file,
            'start_time' => 0,
            'show_buttons' => true,
            'is_phone' => $is_phone,
            'in_modal' => true,
            'stream_hls_url' => $stream_hls_url]);
    }
}
