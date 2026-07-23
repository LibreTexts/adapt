<?php

namespace App\Http\Controllers;

use App\Exceptions\Handler;
use App\Http\Requests\StoreQuestionChapterRequest;
use App\Http\Requests\StoreQuestionSubjectRequest;
use App\LearningTree;
use App\Question;
use App\QuestionChapter;
use App\QuestionRevision;
use App\QuestionSection;
use App\QuestionSubject;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuestionChapterController extends Controller
{

    /**
     * @param QuestionChapter $questionChapter
     * @return array
     * @throws Exception
     */
    public function destroy(QuestionChapter $questionChapter): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('destroy', [$questionChapter, 'chapter']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $name = $questionChapter->name;
            Question::where('question_chapter_id', $questionChapter->id)
                ->update(
                    ['question_chapter_id' => null,
                        'question_section_id' => null]);
            QuestionRevision::where('question_chapter_id', $questionChapter->id)
                ->update(
                    ['question_chapter_id' => null,
                        'question_section_id' => null]);
            LearningTree::where('question_chapter_id', $questionChapter->id)
                ->update(
                    ['question_chapter_id' => null,
                        'question_section_id' => null]);
            QuestionSection::where('question_chapter_id', $questionChapter->id)->delete();
            QuestionChapter::where('id', $questionChapter->id)->delete();
            $questionChapter->delete();
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "The chapter <strong>$name</strong> has been deleted.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to delete the question chapter.  Please try again.";

        }
        return $response;
    }

    /**
     * @param QuestionSubject $questionSubject
     * @return array
     * @throws Exception
     */
    public function getQuestionChaptersByQuestionSubject(QuestionSubject $questionSubject): array
    {
        try {
            $response['type'] = 'error';
            $response['question_chapters'] = QuestionChapter::where('name', '<>', '')
                ->where('question_subject_id', $questionSubject->id)
                ->orderBy('name')
                ->get();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the question chapters.  Please try again.";
        }
        return $response;
    }

    /**
     * @param StoreQuestionChapterRequest $request
     * @param QuestionSubject $questionSubject
     * @param QuestionChapter $questionChapter
     * @return array
     * @throws Exception
     */
    public function store(StoreQuestionChapterRequest $request,
                          QuestionSubject             $questionSubject,
                          QuestionChapter             $questionChapter): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('store', [$questionChapter, 'chapter']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $questionChapter->name = $data['name'];
            $questionChapter->question_subject_id = $questionSubject->id;
            $questionChapter->save();
            $response['type'] = 'success';
            $response['question_level_id'] = $questionChapter->id;
            $response['message'] = "The chapter <strong>{$data['name']}</strong> has been added.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the question chapter.  Please try again.";

        }
        return $response;
    }

    /**
     * @param StoreQuestionChapterRequest $request
     * @param QuestionChapter $questionChapter
     * @return array
     * @throws Exception
     */
    public function update(StoreQuestionChapterRequest $request,
                           QuestionChapter             $questionChapter): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('update', [$questionChapter, 'chapter']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $original_name = $questionChapter->name;
            $questionChapter->name = $data['name'];
            $questionChapter->save();
            $response['type'] = 'success';
            $response['message'] = "The chapter <strong>{$original_name}</strong> has been updated to <strong>{$data['name']}</strong>.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the question chapter.  Please try again.";

        }
        return $response;
    }
}
