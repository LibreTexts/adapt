<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\AssignmentQuestionLearningTree;
use App\AssignmentSyncQuestion;
use App\CanGiveUp;
use App\DataShop;
use App\Exceptions\Handler;
use App\Exceptions\MasteryRetakeConflictException;
use App\Http\Requests\StoreSubmission;
use App\JWE;
use App\LearningTree;
use App\LearningTreeNodeSubmission;
use App\Score;
use App\Services\MasteryAssignmentAttemptService;
use App\Submission;
use App\UnconfirmedSubmission;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\JWT;
use MiladRahimi\Jwt\Cryptography\Algorithms\Hmac\HS256;
use MiladRahimi\Jwt\Cryptography\Keys\HmacKey;
use MiladRahimi\Jwt\Generator;

class JWTController extends Controller
{
    use JWT;

    public function init()
    {

        $JWE = new JWE();
        $token = $JWE->encrypt('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMFwvYXBpXC9sb2dpbiIsImlhdCI6MTYwMjU5NTM5MSwiZXhwIjoyNDY2NTk1MzkxLCJuYmYiOjE2MDI1OTUzOTEsImp0aSI6IlV5alhWSlRzdHdmbkNyZjEiLCJzdWIiOjEsInBydiI6Ijg3ZTBhZjFlZjlmZDE1ODEyZmRlYzk3MTUzYTE0ZTBiMDQ3NTQ2YWEifQ.-o0_89Kc5dqt58pbFGw4AktqrndDPb_L5lEmRY4Vqes');
        echo "The encrypted token: " . $token;
        echo "The decrypted token: " . $JWE->decrypt($token);
    }

    public function signWithNewSecret()
    {

        try {
            if (!app()->environment('local')) {
                dd('Can only run locally');
            }
            $key = new HmacKey(config('myconfig.analytics_token'));
            $signer = new HS256($key);
            $generator = new Generator($signer);
            $jwt = $generator->generate(['id' => 7]);

            dd($jwt); // "abc.123.xyz"
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $payload = auth()->payload();
        print_r($payload->toArray()); // current user info
        \JWTAuth::getJWTProvider()->setSecret('secret'); //change the secret
        $claims = ['foo' => 'bar'];//create the claims
        $token = \JWTAuth::getJWTProvider()->encode(array_merge($claims, $payload->toArray())); //create the token


        //set the same secret for encoding
        $secret = file_get_contents(base_path() . '/JWE/webwork');

        \JWTAuth::getJWTProvider()->setSecret($secret);
        //$decoded = \JWTAuth::getJWTProvider()->decode($token);
        auth()->setToken($token)->getPayload();
        var_dump(auth()->user());
    }


    function base64UrlEncode($text)
    {
        return str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode($text)
        );
    }

    /**
     * @param $content
     * @param $secret
     * @return bool
     */
    public function validateSignature($content, $secret): bool
    {

        //https://developer.okta.com/blog/2019/02/04/create-and-verify-jwts-in-php
        //verify
        // split the token
        $tokenParts = explode('.', $content);
        if (!(isset($tokenParts[0]) && isset($tokenParts[1]) && isset($tokenParts[2]))) {
            return false;
        }
        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signatureProvided = $tokenParts[2];
        $signature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // verify it matches the signature provided in the token
        return ($base64UrlSignature === $signatureProvided);
        //
    }

    public function processAnswerJWT(Request $request)
    {
        try {

            $log_exception = true;
            $JWE = new JWE();
            //$referer = $request->headers->get('referer');//will use this to determine the technology
            $technology = 'webwork';

            $secret = $JWE->getSecret($technology);
            \JWTAuth::getJWTProvider()->setSecret($secret);
            $content = $request->getContent();

            // Log::info('Content:' . $content);
            if (!$this->validateSignature($content, $secret)) {
                throw new Exception("Your JWT does not have a valid signature.");
            }
            $answerJWT = $this->getPayload($content, $secret);

            if (!isset($answerJWT->problemJWT)) {
                Log::info('Answer JWT:' . json_encode($answerJWT));
                throw new Exception("You are missing the problemJWT in your answerJWT!");
            }

            $jwe = new JWE();
            $problemJWT = $jwe->decrypt($answerJWT->problemJWT, $technology);
            $token = \JWTAuth::getJWTProvider()->encode(json_decode($problemJWT, true));
            //Log::info($token);
//if the token isn't formed correctly return a message

            $problemJWT = json_decode($problemJWT);
            if (!User::find($problemJWT->sub)) {
                throw new Exception('User not found');
            }
            $missing_properties = !(
                isset($problemJWT->adapt) &&
                isset($problemJWT->adapt->assignment_id) &&
                isset($problemJWT->adapt->question_id) &&
                isset($problemJWT->adapt->technology));
            if ($missing_properties) {
                throw new Exception("The problemJWT has an incorrect structure.  Please contact us for assistance.");
            }
            if ($problemJWT->adapt->technology === 'imathas') {
                auth()->setToken($token)->getPayload();
            }
            if (!in_array($problemJWT->adapt->technology, ['webwork', 'imathas'])) {
                throw new Exception($problemJWT->adapt->technology . " is not an accepted technology.  Please contact us for assistance.");
            }

            $callback_assignment = Assignment::find($problemJWT->adapt->assignment_id);
            $callback_user = User::find($problemJWT->sub);
            if ($callback_assignment && $callback_assignment->mastery_retake_enabled) {
                app(MasteryAssignmentAttemptService::class)->validateSubmission(
                    $callback_assignment,
                    $callback_user,
                    $problemJWT->adapt->mastery_attempt_id ?? null,
                    (int)$problemJWT->adapt->question_id
                );
            }

            if ($problemJWT->adapt->technology === 'webwork' && isset($answerJWT->score['answers'])) {
                $answers = $answerJWT->score['answers'];
                foreach ($answers as $value) {
                    if (isset($value['error_message']) && $value['error_message']) {
                        $log_exception = false;
                        $canGiveUp = new CanGiveUp();
                        $completed_assignment = false;
                        try {
                            DB::table('webwork_submission_errors')->insert([
                                'user_id' => $problemJWT->sub,
                                'assignment_id' => $problemJWT->adapt->assignment_id,
                                'question_id' => $problemJWT->adapt->question_id,
                                'first_error' => $value['error_message'],
                                'problem_jwt' => json_encode($problemJWT),
                                'created_at' => now(),
                                'updated_at' => now()]);
                            $assignment = DB::table('assignments')->where('id', $problemJWT->adapt->assignment_id)->first();
                            $completed_assignment = $assignment->scoring_type === 'c';
                        } catch (Exception $e) {
                            $h = new Handler(app());
                            $h->report($e);
                        }
                        $canGiveUp->store($problemJWT->sub, $problemJWT->adapt->assignment_id, $problemJWT->adapt->question_id);
                        if (!$completed_assignment) {
                            $error_message = str_replace(["'", PHP_EOL], ["`", "<br>"], $value['error_message']);
                            throw new Exception ("There is an error with at least one of your submissions: $error_message");
                        }
                    }
                }
            }
            //good to go!
            $request = new storeSubmission();
            $request['assignment_id'] = $problemJWT->adapt->assignment_id;
            $request['question_id'] = $problemJWT->adapt->question_id;
            $request['technology'] = $problemJWT->adapt->technology;
            $request['mastery_attempt_id'] = $problemJWT->adapt->mastery_attempt_id ?? null;
            $request['learning_tree_id'] = $problemJWT->adapt->learning_tree_id ?? null;
            $request['branch_id'] = $problemJWT->adapt->branch_id ?? null;
            //nothing to be saved since this is a learning tree assignment and it's part of a remediation
            $request['submission'] = $answerJWT;
            if (isset($problemJWT->adapt->is_learning_tree_node)) {
                $learningTreeNodeSubmission = new LearningTreeNodeSubmission();
                $learning_tree = LearningTree::find($request['learning_tree_id']);
                return $learningTreeNodeSubmission->store($request, new AssignmentQuestionLearningTree(), $learning_tree, new DataShop());
            }
            if (($request['technology'] === 'webwork') && $answerJWT->score === null) {
                $canGiveUp = new CanGiveUp();
                $canGiveUp->store($problemJWT->sub, $problemJWT->adapt->assignment_id, $problemJWT->adapt->question_id);
                throw new Exception('Score field was null.');
            }
            $Submission = new Submission();
            if ($problemJWT->adapt->technology === 'webwork') {
                if (isset($problemJWT->adapt->preview)) {
                    $response['type'] = 'preview';
                    $response['message'] = '';
                    return $response;
                }
                UnconfirmedSubmission::updateOrCreate([
                    'assignment_id' => $problemJWT->adapt->assignment_id,
                    'question_id' => $problemJWT->adapt->question_id,
                    'user_id' => $problemJWT->sub
                ],
                    [
                        'submission' => json_encode($request->all())
                    ]);
                $response['type'] = 'unconfirmed';
                $response['message'] = 'Saved to unconfirmed table.';
                return $response;
            }
            return $Submission->store($request, new Submission(), new Assignment(), new Score(), new DataShop(), new AssignmentSyncQuestion());

        } catch (MasteryRetakeConflictException $e) {
            return response()->json([
                'type' => 'error',
                'reason' => $e->reason(),
                'message' => $e->getMessage()
            ], 409);
        } catch (Exception $e) {
            if ($log_exception) {
                $h = new Handler(app());
                $h->report($e);
            }
            $response['type'] = 'error';
            $response['status'] = 400;

            $response['message'] = "There was a server error and the response could not be saved: " . $e->getMessage();
            return $response;
        }
    }

}
