<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\DiscussionComment;
use App\Exceptions\Handler;
use App\Helpers\Helper;
use App\Http\Requests\StoreQuestionMediaTextRequest;
use App\Jobs\InitProcessTranscribe;
use App\Question;
use App\QuestionMediaUpload;
use Carbon\Carbon;
use DOMDocument;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Parser;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\CloudflareStreamable;

class QuestionMediaController extends Controller
{
    use CloudflareStreamable;

    /**
     * @return array|void
     */
    public function phpInfo()
    {
        $response['message'] = "No access to php info.";
        $response['type'] = 'error';
        if (Helper::isAdmin()) {
            phpInfo();
        } else {
            return $response;
        }
    }

    /**
     * Render the AblePlayer media_player blade for a flashcard media URL.
     * The pre-signed URL comes directly from the card's qti_json — no DB lookup needed.
     *
     * Route (add to web.php):
     *   Route::get('/flashcard-media', 'MediaController@flashcardMedia');
     *
     * @throws Exception
     */
    public function flashcardMedia(Request $request)
    {
        $url = $request->query('url');
        $type = $request->query('type', 'video');
        $stream_hls_url = '';
        if ($type === 'video') {
            $parsed = parse_url($url);
            $host = $parsed['host'];
            $path = ltrim($parsed['path'], '/');
            $bucket = Storage::disk('s3')->getDriver()->getAdapter()->getBucket();

            if (str_starts_with($host, 's3.')) {
                $s3_path = preg_replace('#^' . preg_quote($bucket, '#') . '/#', '', $path);
            } else {
                $s3_path = $path;
            }

            $cloudflare_uid = $this->getCloudflareStreamUid($s3_path);
            if ($cloudflare_uid) {
                $stream_hls_url = "https://videodelivery.net/{$cloudflare_uid}/manifest/video.m3u8";
            }
        }

        if (!$url) {
            abort(400, 'Missing url parameter');
        }

        // Validate the URL is a signed S3 URL from our own bucket to prevent open-redirect abuse
        $bucket = config('filesystems.disks.s3.bucket');
        $region = config('filesystems.disks.s3.region');

        $allowed_hosts = [
            "{$bucket}.s3.amazonaws.com",
            "{$bucket}.s3.{$region}.amazonaws.com",
            "s3.amazonaws.com",
            "s3.{$region}.amazonaws.com",
        ];

        $host = parse_url($url, PHP_URL_HOST);
        $valid = collect($allowed_hosts)->contains(function ($h) use ($host) {
            return str_ends_with($host, $h);
        });

        if (!$valid) {
            abort(403, 'Invalid media URL');
        }

        // For VTT: the card JSON may include a separate vttUrl field
        $vtt_file = $request->query('vtt_url', '');
        $start_time = (int)$request->query('start_time', 0);
        $is_phone = 0;
        return view('media_player', [
            'type' => $type,
            'temporary_url' => $url,
            'mp4_temporary_url' => $type === 'video' && !$stream_hls_url ? $url : '',
            'vtt_file' => $vtt_file,
            'start_time' => $start_time,
            'is_phone' => $is_phone,
            'stream_hls_url' => $stream_hls_url,
            'show_buttons' => false,
        ]);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function initTranscribe(Request $request): array
    {
        try {
            $this->_hasAccessToTranscribe($request);
            DB::table('pending_transcriptions')->updateOrInsert(
                ['filename' => $request->filename], [
                    'language' => $request->language ? $request->language : '',
                    'upload_type' => $request->upload_type,
                    'environment' => $request->environment,
                    'status' => 'initializing',
                    'message' => '',
                    'created_at' => now(),
                    'updated_at' => now()]
            );
            $response['type'] = 'init-processing';
            $response['message'] = 'Made it to dev server.';
            return $response;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
            return $response;
        }
    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function updateTranscribeStatus(Request $request): array
    {
        try {
            //$this->_hasAccessToTranscribe($request);
            $upload_type_model = (new QuestionMediaUpload())->getUploadTypeModel($request->upload_type, $request->filename);
            if (!$upload_type_model) {
                $response['type'] = 'error';
                $response['message'] = "Does not exist in the DB: $request->upload_type --- $request->filename";
            }
            $upload_type_model->status = $request->status;
            $upload_type_model->message = $request->message;
            $upload_type_model->transcript = $request->transcript;
            $upload_type_model->save();
            $response['type'] = 'success';
            $response['message'] = "$request->upload_type updated for $upload_type_model->id.";
        } catch (Exception $e) {
            $response['type'] = 'error';
            $response['message'] = $e->getMessage();
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;
    }

    /**
     * @param StoreQuestionMediaTextRequest $request
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function updateText(StoreQuestionMediaTextRequest $request,
                        QuestionMediaUpload           $questionMediaUpload): array
    {

        try {
            $response['type'] = 'error';
            $s3_key = $request->s3_key;
            $authorized = Gate::inspect('updateText', [$questionMediaUpload, $s3_key]);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $question_media_upload_dir = $questionMediaUpload->getDir();
            $data['text'] = str_replace('<p>&nbsp;</p>', '', $data['text']);
            Storage::disk('s3')->put("$question_media_upload_dir/pending-$s3_key", $data['text']);
            $questionMediaUpload = QuestionMediaUpload::where('s3_key', $s3_key)->first();
            /* if ($questionMediaUpload) {
                 $questionMediaUpload->original_filename = $data['description'];
                 $questionMediaUpload->text = $data['text'];
                 $questionMediaUpload->save();
             } else {
                 $response['message'] = 'We were unable to locate that text file. Please try again or contact us for assistance.';
                 return $response;
             }*/
            $response['s3_key'] = $s3_key;
            $response['size'] = Storage::disk('s3')->size("$question_media_upload_dir/$s3_key");
            $response['type'] = 'success';
            $response['message'] = 'The text has been updated.';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not update the text. Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param StoreQuestionMediaTextRequest $request
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function storeText(StoreQuestionMediaTextRequest $request,
                       QuestionMediaUpload           $questionMediaUpload): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('storeText', $questionMediaUpload);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $s3_key = md5(uniqid('', true)) . '.html';
            Storage::disk('s3')->put("{$questionMediaUpload->getDir()}/$s3_key", $data['text']);
            $response['s3_key'] = $s3_key;
            $response['type'] = 'success';
            $response['size'] = Storage::disk('s3')->size("{$questionMediaUpload->getDir()}/$s3_key");
            $response['message'] = 'The text has been added.';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not store the text. Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Request $request
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function temporaryUrls(Request $request, QuestionMediaUpload $questionMediaUpload): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('temporaryUrls', $questionMediaUpload);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $question_media_uploads = $request->question_media_uploads;
            foreach ($question_media_uploads as $key => $question_media_upload) {
                $s3_key = $question_media_upload['s3_key'];
                $needs_temporary_url = strpos($s3_key, '.pdf') !== false || strpos($s3_key, '.mp4') !== false;
                $question_media_uploads[$key]['temporary_url'] = $needs_temporary_url ?
                    Storage::disk('s3')
                        ->temporaryUrl("{$questionMediaUpload->getDir()}/$s3_key", Carbon::now()->addDays(7))
                    : null;

            }
            $response['type'] = 'success';
            $response['question_media_uploads'] = $question_media_uploads;
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not get the temporary urls. Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Assignment $assignment
     * @param Question $question
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function getByAssignmentQuestion(Assignment          $assignment,
                                     Question            $question,
                                     QuestionMediaUpload $questionMediaUpload): array
    {

        try {
//need the assignment ID for authorization
            $response['type'] = 'error';
            $assignment_question = DB::table('assignment_question')
                ->where('assignment_id', $assignment->id)
                ->where('question_id', $question->id)
                ->first();
            $question_media_uploads =
                $assignment_question->question_revision_id ? $questionMediaUpload
                    ->where('question_id', $question->id)
                    ->where('question_revision_id', $assignment_question->question_revision_id)
                    ->orderBy('order')
                    ->get()
                    : $questionMediaUpload
                    ->where('question_id', $question->id)
                    ->orderBy('order')
                    ->get();

            $domDocument = new DOMDocument();
            foreach ($question_media_uploads as $key => $value) {
                $value->order = $key + 1;//in case something weird happened when creating the question
                if (pathinfo($value->s3_key, PATHINFO_EXTENSION) != 'html') {
                    $question_media_uploads[$key]['temporary_url'] = Storage::disk('s3')->temporaryUrl("{$questionMediaUpload->getDir()}/$value->s3_key", Carbon::now()->addDays(7));
                }
                if (pathinfo($value->s3_key, PATHINFO_EXTENSION) === 'html') {
                    $question_media_uploads[$key]['text'] = $value->getText($question, $domDocument);

                }

            }
            $response['question_media_uploads'] = $question_media_uploads;
            $response['type'] = 'success';

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not get the media associated with this question. Please try again or contact us for assistance.";

        }
        return $response;


    }

    /**
     * @param string $src
     * @return Application|Factory|View
     */
    public
    function conductorMedia(string $src)
    {
        return view('conductor_media', ['src' => $src]);
    }


    /**
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function validateVTT(QuestionMediaUpload $questionMediaUpload): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('validateVTT', $questionMediaUpload);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {
            $s3_key = $questionMediaUpload->s3_key;
            $original_s3_key = $s3_key;
            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key($s3_key);
            $s3_key = "{$questionMediaUpload->getDir()}/$vtt_file";
            if (!Storage::disk('s3')->exists($s3_key)) {
                $response['message'] = "We were unable to locate the .vtt file on the server.";
                return $response;
            }

            // Check the file extension
            $extension = pathinfo($s3_key, PATHINFO_EXTENSION);
            if (strtolower($extension) !== 'vtt') {
                $response['message'] = "That is not a .vtt file.";
                Storage::disk('s3')->delete($s3_key);
                return $response;
            }

            $vtt_content = Storage::disk('s3')->get($s3_key);
            if (strpos($vtt_content, 'WEBVTT') !== 0) {
                $response['message'] = "Missing WEBVTT from the file.";
                Storage::disk('s3')->delete($s3_key);
                return $response;
            }
            $questionMediaUpload->where('s3_key', $original_s3_key)->update(['transcript' => $vtt_content]);

            $transcript = $questionMediaUpload->parseVtt($vtt_content);


            $response['type'] = 'success';
            $response['transcript'] = $transcript;
            $response['message'] = "The transcript has been updated.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not upload the transcript. Please try again or contact us for assistance.";

        }
        return $response;

    }


    /**
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array|StreamedResponse
     * @throws Exception
     */
    public
    function downloadTranscript(QuestionMediaUpload $questionMediaUpload)
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('download', $questionMediaUpload);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        try {

            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key();
            if (Storage::disk('s3')->exists("{$questionMediaUpload->getDir()}/$vtt_file")) {
                return Storage::disk('s3')->download("{$questionMediaUpload->getDir()}/$vtt_file");
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
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function destroy(QuestionMediaUpload $questionMediaUpload): array
    {
        $response['type'] = 'error';
        $authorized = Gate::inspect('destroy', $questionMediaUpload);
        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }
        if (DB::table('discussions')->where('media_upload_id', $questionMediaUpload->id)->first()) {
            $response['message'] = "This media cannot be deleted since it has already been used as part of a discussion in an assignment question.";
            return $response;
        }
        try {
            DB::beginTransaction();
            $original_filename = $questionMediaUpload->original_filename;
            $questionMediaUpload->delete();
            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key();
            if (Storage::disk('s3')->exists("{$questionMediaUpload->getDir()}/$vtt_file")) {
                Storage::disk('s3')->delete("{$questionMediaUpload->getDir()}/$vtt_file");
            }
            Storage::disk('s3')->delete("{$questionMediaUpload->getDir()}/$questionMediaUpload->s3_key");
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "$original_filename has been deleted.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not delete the question media. Please try again or contact us for assistance.";
        }
        return $response;

    }

    /**
     * @param string $media
     * @param int $start_time
     * @return Application|Factory|View
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface|FileNotFoundException
     * @throws Exception
     */
    public function index(string $media, int $start_time = 0)
    {
        $questionMediaUpload = new QuestionMediaUpload();
        $show_captions_session = session()->get('show_captions');

        $show_captions = is_array($show_captions_session) && !(in_array($media, array_keys($show_captions_session)) && $show_captions_session[$media] === 0);

        $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key($media);
        $type = strpos($media, '.mp3') !== false ? 'audio' : 'video';
        $temporary_url = Storage::disk('s3')->temporaryUrl("{$questionMediaUpload->getDir()}/$media", Carbon::now()->addDays(7));
        $mp4_temporary_url = $type === 'video' ? $temporary_url : '';
        $vtt_file = $show_captions ? Storage::disk('s3')->temporaryUrl("{$questionMediaUpload->getDir()}/$vtt_file", Carbon::now()->addDays(7)) : '';
        $is_phone = 0;

        $stream_hls_url = null;
        if ($type === 'video') {
            $s3_path = "{$questionMediaUpload->getDir()}/$media";
            $cloudflare_uid = $this->getCloudflareStreamUid($s3_path);
            if ($cloudflare_uid) {
                $stream_hls_url = "https://videodelivery.net/{$cloudflare_uid}/manifest/video.m3u8";
            }
        }

        return view('media_player', ['type' => $type,
            'temporary_url' => $temporary_url,
            'mp4_temporary_url' => $mp4_temporary_url,
            'vtt_file' => $vtt_file,
            'start_time' => $start_time,
            'is_phone' => $is_phone,
            'show_buttons' => false,
            'stream_hls_url' => $stream_hls_url]);
    }

    /**
     * @param Request $request
     * @param int $media_upload_id
     * @param QuestionMediaUpload $questionMediaUpload
     * @return array
     * @throws Exception
     */
    public
    function reProcessTranscript(Request             $request,
                                 int                 $media_upload_id,
                                 QuestionMediaUpload $questionMediaUpload): array
    {
        $response['type'] = 'error';
        try {
            switch ($request->model) {
                case('QuestionMediaUpload'):
                    throw new Exception ("QuestionMediaUpload not yet support for reprocessing transcripts.");
                case('DiscussionComment'):
                    $model = DiscussionComment::find($media_upload_id);
                    $s3_key = $model->file;
                    break;
                default:
                    throw new Exception("$request->model is not a valid model to update a caption.");
            }
            $authorized = Gate::inspect('reProcessTranscript', $model);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            if ($model->re_processed_transcript) {
                $response['message'] = 'Transcripts can only be re-processed once.';
                return $response;
            }
            $vtt_file = $questionMediaUpload->getVttFileNameFromS3Key($s3_key);
            if ($vtt_file && (Storage::disk('s3')->exists($vtt_file))) {
                Storage::disk('s3')->delete($vtt_file);
            }
            $model->transcript = '';
            $model->re_processed_transcript = 1;
            $model->save();
            InitProcessTranscribe::dispatch($s3_key, 'discussion_comment');
            $response['type'] = 'success';
            $response['message'] = 'The original transcript has been removed and a new one is being processed.';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not re-process the transcription. Please try again or contact us for assistance.";

        }
        return $response;

    }

    /**
     * @param Request $request
     * @param int $media_upload_id
     * @param int $caption
     * @return array
     * @throws Exception
     */
    public
    function updateCaption(Request $request,
                           int     $media_upload_id,
                           int     $caption): array
    {

        try {
            $response['type'] = 'error';
            $questionMediaUpload = new QuestionMediaUpload();
            switch ($request->model) {
                case('QuestionMediaUpload'):
                    $model = QuestionMediaUpload::find($media_upload_id);
                    $s3_key = $model->s3_key;
                    break;
                case('DiscussionComment'):
                    $model = DiscussionComment::find($media_upload_id);
                    $s3_key = $model->file;
                    break;
                default:
                    throw new Exception("$request->model is not a valid model to update a caption.");
            }
            $authorized = Gate::inspect('updateCaption', $model);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $parsed_vtt = $questionMediaUpload->parseVtt($model->transcript);
            $parsed_vtt[$caption]['text'] = $request->text;
            $model->transcript = $questionMediaUpload->convertArrayToVTT($parsed_vtt);
            $model->save();
            $s3_dir = $questionMediaUpload->getDir();

            $file_name_without_ext = pathinfo($s3_key, PATHINFO_FILENAME);
            Storage::disk('s3')->put("$s3_dir/$file_name_without_ext.vtt", $model->transcript);
            $response['type'] = 'success';
            $response['message'] = "The caption has been updated.";
            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We could not update the caption. Please try again or contact us for assistance.";

        }

        return $response;
    }

    /**
     * @param Request $request
     * @throws Exception
     */
    private function _hasAccessToTranscribe(Request $request): void
    {
        if (!$request->bearerToken()) {
            throw new Exception ('Missing Bearer Token.');
        }

        $key_secret = DB::table('key_secrets')->where('key', 'adapt_transcribe')->first();
        if (!$key_secret) {
            throw new Exception("No key_secret for adapt_transcribe exists.");
        }
        $token = $request->bearerToken();

        $key = new HmacKey($key_secret->secret);

        $signer = new HS256($key);
        $parser = new Parser($signer);
        if (!$parser->parse($token)) {
            throw new Exception ('Invalid adapt_transcribe key.');
        }
    }
}
