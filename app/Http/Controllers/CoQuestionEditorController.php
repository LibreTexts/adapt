<?php

namespace App\Http\Controllers;

use App\CoQuestionEditor;
use App\Exceptions\Handler;
use App\Question;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CoQuestionEditorController extends Controller
{
    /**
     * @param Request $request
     * @param Question $question
     * @param CoQuestionEditor $coQuestionEditor
     * @return array
     * @throws Exception
     */
    public function canEdit(Request          $request,
                            Question         $question,
                            CoQuestionEditor $coQuestionEditor): array
    {

        $response['type'] = 'error';
        try {
            $can_edit = $question->question_editor_user_id === $request->user()->id || ($coQuestionEditor->where('question_editor_user_id', $question->question_editor_user_id)
                    ->where('co_question_editor_user_id', $request->user()->id)
                    ->exists());
            $response['type'] = 'success';
            $response['can_edit'] = $can_edit;
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "There was an error seeing if you have co-question editor access. Please try again or contact us for assistance.";
        }
        return $response;
    }

}
