<?php

namespace App\Http\Controllers;

use App\Assignment;
use App\Exceptions\Handler;
use App\Question;
use App\ShownHint;
use App\Webwork;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShownHintController extends Controller
{
    /**
     * @param Request $request
     * @param Assignment $assignment
     * @param Question $question
     * @param ShownHint $shownHint
     * @param Webwork $webwork
     * @return array
     * @throws Exception
     */
    public function store(Request    $request,
                          Assignment $assignment,
                          Question   $question,
                          ShownHint  $shownHint,
                          Webwork    $webwork): array
    {

        $response['type'] = 'error';
        $authorized = Gate::inspect('store', [$shownHint, $assignment::find($assignment->id), $question->id]);

        if (!$authorized->allowed()) {
            $response['message'] = $authorized->message();
            return $response;
        }


        try {
            ShownHint::firstOrCreate([
                'user_id' => $request->user()->id,
                'assignment_id' => $assignment->id,
                'question_id' => $question->id,
            ]);

            if ($question->technology === 'webwork' && $request->problemJWT) {
                $webwork_response = $webwork->getHint($request->problemJWT);
                if ($webwork_response['type'] === 'error') {
                    $response['message'] = $webwork_response['message'];
                    return $response;
                }
                $response['hint'] = $webwork_response['message'];
            } else {
                $question->addTimeToS3Files($question->hint, new \DOMDocument(), false);
                $response['hint'] = $question->hint;
            }

            $response['type'] = 'success';
        } catch (Exception $e) {
            $response['message'] = 'We were unable to confirm that you would like the hint to be shown.';
            $h = new Handler(app());
            $h->report($e);
        }
        return $response;


    }

}
