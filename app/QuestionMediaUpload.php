<?php

namespace App;

use App\Exceptions\Handler;
use App\Helpers\Helper;
use Aws\Exception\AwsException;
use DOMDocument;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;
use Orhanerday\OpenAi\OpenAi;
use Snowfire\Beautymail\Beautymail;

class QuestionMediaUpload extends Model
{

    protected $guarded = [];

    /**
     * @param $question_id
     * @param $question_revision_id
     * @return mixed
     * @throws Exception
     */
    public function getByQuestionIdAndQuestionRevisionId($question_id, $question_revision_id)
    {
        $media_uploads = $question_revision_id
            ? $this->where('question_id', $question_id)
                ->where('question_revision_id', $question_revision_id)
                ->get()
            : $this->where('question_id', $question_id)
                ->get();
        if ($media_uploads) {
            $domDocument = new DOMDocument();
            $question = new Question();
            foreach ($media_uploads as $key => $media_upload) {
                $media_upload->text = $media_upload->getText($question, $domDocument);
                if (!$media_upload->text) {
                    if ($media_upload->transcript) {
                        $media_upload->transcript = $this->parseVtt($media_upload->transcript);
                    }
                    $media_upload->url = Helper::schemaAndHost() . "question-media-player/$media_upload->s3_key";
                } else {
                    $media_upload->url = '';
                    $media_upload->transcript = '';
                }
            }
        }
        return $media_uploads;
    }

    /**
     * @param string $environment
     * @param string $filename
     * @param string $upload_type
     * @param string $status
     * @param string $message
     * @param string $transcript
     * @return void
     * @throws Exception
     */
    public function sendUpdatedTranscriptionStatus(string $environment,
                                                   string $filename,
                                                   string $upload_type,
                                                   string $status,
                                                   string $message,
                                                   string $transcript = '')
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

            $jwt = $generator->generate(['transcribe' => 1]);
            $this->_logInfo('sending update info to ' . $domain);
            DB::table('pending_transcriptions')->where('filename', $filename)->update(['status' => $status, 'message' => $message]);

            $http = Http::withToken($jwt)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ]);

            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => '/Applications/MAMP/Library/OpenSSL/cert.pem']);
            }

            $response = $http->patch("https://$domain/api/question-media/transcribe-status", [
                'filename' => $filename,
                'upload_type' => $upload_type,
                'status' => $status,
                'message' => $message,
                'transcript' => $transcript
            ]);

            if (!$response->successful()) {
                throw new Exception ("Unable to post an update to update-transcribe-status; check the dev database");
            }
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $message = $response && $response->body() ? $response->body() : $e->getMessage();
            DB::table('pending_transcriptions')
                ->where('filename', $filename)
                ->update(
                    ['status' => 'error', 'message' => $message]
                );

        }
    }

    /**
     * @param Question $question
     * @param DOMDocument $domDocument
     * @return string
     */
    public function getText(Question $question, DOMDocument $domDocument): string
    {
        if (strpos($this->s3_key, '.html') !== false) {
            try {
                $text = $this->text;
                if (!$text) {
                    $this->text = Storage::disk('s3')->get($this->getDir() . '/' . $this->s3_key);
                    $this->save();
                    $text = $this->text;
                }
                $text = $question->addTimeToS3Files($text, new DOMDocument(), false);
            } catch (Exception $e) {
                $text = "Unable to retrieve the text for this discuss it question.";
            }
        } else {
            $text = '';
        }
        return $text;
    }

    /**
     * @throws Exception
     */
    public function deleteFileAndVttFile($filename)
    {

        $s3_key = $filename ? $this->getDir() . "/" . $filename : false;
        if ($s3_key && (Storage::disk('s3')->exists($s3_key))) {
            DB::table('cloudflare_stream_videos')
                ->where('s3_path', $s3_key)
                ->update(['marked_for_deletion' => 1]);
            Storage::disk('s3')->delete($s3_key);
        }

        $vtt_file = $this->getVttFileNameFromS3Key($s3_key);
        if ($vtt_file && (Storage::disk('s3')->exists($vtt_file))) {
            Storage::disk('s3')->delete($vtt_file);
        }
    }

    /**
     * @param string $media
     * @return string
     * @throws Exception
     */
    public function getVttFileNameFromS3Key(string $media = ''): string
    {
        $s3_key = $media ?: $this->s3_key;
        $file_name_without_ext = pathinfo($s3_key, PATHINFO_FILENAME);
        return $file_name_without_ext ? "$file_name_without_ext.vtt" : '';
    }


    /**
     * @return void
     * @throws Exception
     */
    public function emailResult()
    {
        try {

            $instructor = DB::table('question_media_uploads')
                ->join('questions', 'question_media_uploads.question_id', '=', 'questions.id')
                ->join('users', 'questions.question_editor_user_id', '=', 'users.id')
                ->where('question_media_uploads.id', $this->id)
                ->select('first_name', 'last_name', 'email')
                ->first();
            $mail_info = [
                'first_name' => $instructor->first_name,
                'question_id' => $this->question_id,
                'original_filename' => $this->original_filename,
                'url' => Helper::schemaAndHost() . "source/edit/{$this->question_id}/$this->id",
                'success' => $this->status === 'completed'
            ];
            $beautymail = app()->make(Beautymail::class);
            $beautymail->send('emails.question_media_uploads_transcription_results', $mail_info, function ($message)
            use ($instructor) {
                $message->from('adapt@noreply.libretexts.org', 'ADAPT')
                    ->to($instructor->email, $instructor->first_name . ' ' . $instructor->last_name)
                    ->subject('Transcript Completed');
            });
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);

        }

    }

    /**
     * @return string
     */
    function getDir(): string
    {
        return "uploads/question-media";
    }

    /**
     * @param $array
     * @return string
     */
    function convertArrayToVTT($array): string
    {
        $vtt = "WEBVTT\n\n";
        foreach ($array as $entry) {
            $vtt .= $entry['start'] . " --> " . $entry['end'] . "\n";
            $vtt .= $entry['text'] . "\n\n";
        }
        return $vtt;
    }

    /**
     * @param $vttContent
     * @return array
     */
    function parseVtt($vttContent): array
    {
        $lines = explode("\n", $vttContent);
        $result = [];
        $timePattern = '/^(\d{2}:\d{2}:\d{2}\.\d{3}) --> (\d{2}:\d{2}:\d{2}\.\d{3})$/';

        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match($timePattern, $lines[$i], $matches)) {
                $start = $matches[1];
                $end = $matches[2];
                $text = $lines[$i + 1] ?? '';//in case the transcript doesn't return text
                $result[] = [
                    'start' => $start,
                    'end' => $end,
                    'text' => $text
                ];
                $i++; // Skip the text line
            }
        }

        return $result;
    }

    function convertMovToMP4()
    {

        $inputVideo = '/Users/franciscaparedes/adapt_laravel_8/storage/app/videos/Spanish_Recording.mov';
        $outputVideo = '/Users/franciscaparedes/adapt_laravel_8/storage/app/videos/Spanish_Recording.mp4';

        $command = escapeshellcmd("ffmpeg -y -i {$inputVideo} -vcodec h264 -acodec aac {$outputVideo}");

        $output = shell_exec($command);
        $return_var = null;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            // Command failed, handle the error
            echo "Error occurred during video conversion.\n";
            echo "Command output:\n";
            echo implode("\n", $output);
        } else {
            // Command succeeded
            echo "Video conversion completed successfully.\n";
        }

    }

    /**
     * @param string $filename
     * @return void
     * @throws Exception
     */
    public function processTranscribe(string $filename)
    {

        $pending_transcription = null;
        try {
            $pending_transcription = DB::table('pending_transcriptions')->where('filename', $filename)->first();
            if (!$pending_transcription) {
                throw new Exception("No pending transcriptions with filename $filename");
            }
            switch ($pending_transcription->environment) {
                case('production'):
                    $disk = 'production_s3';
                    break;
                case('staging'):
                    $disk = 'staging_s3';
                    break;
                case('local'):
                case('dev'):
                    $disk = 's3';
                    break;
                default:
                    throw new Exception("$pending_transcription->environment is not set up in the transcribe endpoint.");
            }
            $adapter = Storage::disk($disk); // Get the filesystem adapter
            $client = $adapter->getClient(); // Get the aws client
            $bucket = $adapter->getConfig()['bucket']; // Get the current bucket
            // Download the file from S3 and save it locally
            $local_path = storage_path('app/' . $this->getDir() . "/" . $filename);
            $client->getObject([
                'Bucket' => $bucket,
                'Key' => $this->getDir() . "/" . $filename,
                'SaveAs' => $local_path, // Save the file locally in the storage/app directory
            ]);


            file_exists($local_path) ?
                $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'processing', "File saved successfully!")
                : $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'error', "File download failed; doesn't exist on dev server.");


            $language = $pending_transcription->language;
            $transcript = $this->transcribeWithWhisper($local_path, $language);
            $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'partially completed', "Received transcript from Whisper.");
            $file_name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
            Storage::disk($disk)->put($this->getDir() . "/$file_name_without_ext.vtt", $transcript);
            $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'transcript completed', "Transcript sent to S3", $transcript);
            if ($pending_transcription->upload_type === 'question_media_upload') {
                $questionMediaUpload = QuestionMediaUpload::where('s3_key', $filename)->first();
                // $questionMediaUpload->emailResult();
            }

        } catch (AwsException $e) {
            $h = new Handler(app());
            $h->report($e);
            $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'error', "Could not get from AWS: " . $e->getMessage());

        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            if ($pending_transcription) {
                $this->sendUpdatedTranscriptionStatus($pending_transcription->environment, $filename, $pending_transcription->upload_type, 'error', $e->getMessage());
            }
        }
    }

    /**
     * @param $path_on_transcribe_server
     * @param $language
     * @return string
     * @throws Exception
     */
    function transcribeWithWhisper($path_on_transcribe_server, $language = null): string
    {
        $this->_logInfo("Location on server:" . $path_on_transcribe_server);
        $openai = new OpenAi(config('myconfig.openai_api_key'));

        // Split the video into smaller chunks using FFmpeg
        $output_dir = pathinfo($path_on_transcribe_server, PATHINFO_DIRNAME);
        $filename = basename($path_on_transcribe_server);
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
        $file_extension = $file_extension === 'webm' ? 'mp4' : $file_extension;
        $identifier = pathinfo($filename, PATHINFO_FILENAME);
        $output_file_pattern = "$output_dir/$identifier-chunk_%03d.$file_extension";


        // Get the path info
        $path_info = pathinfo($path_on_transcribe_server);

        $new_path_on_transcribe_server = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $file_extension;

        if ($file_extension === 'mp4') {
            $new_path_on_transcribe_server = $path_info['dirname'] . '/temp-' . $path_info['filename'] . '.' . $file_extension;

            // Whisper only needs the audio, so drop the video instead of re-encoding it
            $command = "ffmpeg -nostdin -y -i " . escapeshellarg($path_on_transcribe_server) . " -vn -c:a aac -loglevel error " . escapeshellarg($new_path_on_transcribe_server);
            $this->_logInfo($command);
            list($returnValue, $output, $errorOutput) = Helper::runFfmpegCommand($command);

            if ($returnValue !== 0) {
                throw new Exception ("FFmpeg error processing $filename: $errorOutput)");
            }

        }

        $command = "ffmpeg -nostdin -i " . escapeshellarg($new_path_on_transcribe_server) . " -c copy -map 0 -loglevel error -segment_time 30 -f segment " . escapeshellarg($output_file_pattern);


        $this->_logInfo($command);
        list($returnValue, $output, $errorOutput) = Helper::runFfmpegCommand($command);

        if ($returnValue !== 0) {
            throw new Exception ("FFmpeg error processing $filename: $errorOutput)");
        }


        $transcripts = [];

        foreach (glob("$output_dir/$identifier-chunk_*.$file_extension") as $key => $chunk) {
            //$upload_type_model->message = "Transcribing chunk $key";
            // $upload_type_model->save();
            $cFile = curl_file_create($chunk);
            $whisper_config = [
                "model" => "whisper-1",
                "file" => $cFile,
                "response_format" => "vtt"
            ];
            if ($language) {
                $whisper_config['language'] = $language;
            }
            $response = $openai->transcribe($whisper_config);

            $json_response = json_decode($response);
            if ($json_response && isset($json_response->error)) {
                throw new Exception($response);
            }

            $transcripts[] = $response;
            // $upload_type_model->message = "Transcribed chunk $key";
            // $upload_type_model->save();
        }

        $this->_logInfo('merging stuff');
        $transcript = $this->mergeVTTChunks($transcripts);
        $this->_logInfo($new_path_on_transcribe_server);
        foreach (glob("$output_dir/$identifier-chunk_*.$file_extension") as $chunk) {
            if (file_exists($chunk)) {
                unlink($chunk);
            }
        }
        if (file_exists($new_path_on_transcribe_server)) {
            unlink($new_path_on_transcribe_server);
        }

        if (file_exists($path_on_transcribe_server)) {
            unlink($path_on_transcribe_server);
        }
        $this->_logInfo($transcript);
        return $transcript;
    }

    function mergeVTTChunks(array $transcripts): string
    {

        $totalTime = 0;
        $finalVttContent = '';
        foreach ($transcripts as $transcript) {
            $lines = explode("\n", $transcript);
            $first_timing = '';
            $current_timing = '';
            $end = 0;

            foreach ($lines as $line) {
                if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $line, $matches)) {
                    $start = $this->convertToMilliseconds($matches[1], $matches[2], $matches[3], $matches[4]) + $totalTime;
                    $end = $this->convertToMilliseconds($matches[5], $matches[6], $matches[7], $matches[8]) + $totalTime;
                    $adjustedStart = $this->convertToVttTimestamp($start);
                    $adjustedEnd = $this->convertToVttTimestamp($end);
                    $current_timing = "$adjustedStart --> $adjustedEnd\n";
                    if (!$first_timing) {
                        $first_timing = "$current_timing";
                    }
                    $finalVttContent .= "$current_timing";

                } else {
                    $finalVttContent .= $line . "\n";
                }
            }

            // Calculate the total time duration of the current chunk and add to the total time
            if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $first_timing, $matches)) {
                $start = $this->convertToMilliseconds($matches[1], $matches[2], $matches[3], $matches[4]);
                if (preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3}) --> (\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $current_timing, $matches)) {
                    $end = $this->convertToMilliseconds($matches[5], $matches[6], $matches[7], $matches[8]);
                }
                $totalTime += $end - $start;

            }
        }
        $finalVttContent = str_replace("WEBVTT\n\n", '', $finalVttContent);
        $finalVttContent = str_replace("\n\n\n", "\n\n", $finalVttContent);
        return "WEBVTT\n\n$finalVttContent";
    }

    function convertToMilliseconds($hours, $minutes, $seconds, $milliseconds)
    {
        return ($hours * 3600 + $minutes * 60 + $seconds) * 1000 + $milliseconds;
    }

    function convertToVttTimestamp($milliseconds): string
    {
        $hours = floor($milliseconds / 3600000);
        $milliseconds -= $hours * 3600000;
        $minutes = floor($milliseconds / 60000);
        $milliseconds -= $minutes * 60000;
        $seconds = floor($milliseconds / 1000);
        $milliseconds -= $seconds * 1000;

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
    }

    private function _logInfo($message)
    {
        if (app()->environment('dev')) {
            Log::info($message);
        }
    }

    /**
     * @throws Exception
     */
    public function getUploadTypeModel(string $upload_type, string $s3_key)
    {
        switch ($upload_type) {
            case('question_media_upload'):
                $s3_key_column = 's3_key';
                $uploadTypeModel = new QuestionMediaUpload();
                break;
            case('discussion_comment'):
                $uploadTypeModel = new DiscussionComment();
                $s3_key_column = 'file';
                break;
            default:
                throw new Exception ("Invalid upload type for transcribing: $upload_type");
        }
        $upload_type_model = $uploadTypeModel->where($s3_key_column, $s3_key)->first();
        if (!$upload_type_model) {
            throw new Exception ("The key $s3_key does not exist for the model $upload_type.");
        }
        return $upload_type_model;
    }
}
