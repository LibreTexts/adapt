<?php

namespace App\Http\Controllers;

use App\Exceptions\Handler;
use App\Http\Requests\StoreQuestionChapterRequest;
use App\Http\Requests\StoreQuestionSectionRequest;
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

class QuestionSectionController extends Controller
{

    /**
     * @param QuestionSection $questionSection
     * @return array
     * @throws Exception
     */
    public function destroy(QuestionSection $questionSection): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('destroy', [$questionSection, 'section']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            DB::beginTransaction();
            $name = $questionSection->name;
            Question::where('question_section_id', $questionSection->id)
                ->update(['question_section_id' => null]);
            QuestionRevision::where('question_section_id', $questionSection->id)
                ->update(['question_section_id' => null]);
            LearningTree::where('question_section_id', $questionSection->id)
                ->update(['question_section_id' => null]);
            $questionSection->delete();
            DB::commit();
            $response['type'] = 'info';
            $response['message'] = "The section <strong>$name</strong> has been deleted.";
        } catch (Exception $e) {
            DB::rollback();
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to delete the question section.  Please try again.";

        }
        return $response;
    }
    /**
     * @param QuestionChapter $questionChapter
     * @return array
     * @throws Exception
     */
    public function getQuestionSectionsByQuestionChapter(QuestionChapter $questionChapter): array
    {
        try {
            $response['type'] = 'error';
            $response['question_sections'] = QuestionSection::where('name', '<>', '')
                ->where('question_chapter_id', $questionChapter->id)
                ->orderBy('name')
                ->get();
            $response['type'] = 'success';
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to get the question sections.  Please try again.";
        }
        return $response;
    }

    /**
     * @param StoreQuestionSectionRequest $request
     * @param QuestionSection $questionSection
     * @return array
     * @throws Exception
     */
    public function update(StoreQuestionSectionRequest $request, QuestionSection $questionSection): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('update', [$questionSection, 'section']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $original_name = $questionSection->name;
            $questionSection->name = $data['name'];
            $questionSection->save();
            $response['type'] = 'success';
            $response['message'] = "The section <strong>{$original_name}</strong> has been updated to <strong>{$data['name']}</strong>.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the question subject.  Please try again.";

        }
        return $response;
    }

    /**
     * @param StoreQuestionSectionRequest $request
     * @param QuestionChapter $questionChapter
     * @param QuestionSection $questionSection
     * @return array
     * @throws Exception
     */
    public function store(StoreQuestionSectionRequest $request,
                          QuestionChapter             $questionChapter,
                          QuestionSection             $questionSection): array
    {
        try {
            $response['type'] = 'error';
            $authorized = Gate::inspect('store', [$questionSection, 'section']);
            if (!$authorized->allowed()) {
                $response['message'] = $authorized->message();
                return $response;
            }
            $data = $request->validated();
            $questionSection->name = $data['name'];
            $questionSection->question_chapter_id = $questionChapter->id;
            $questionSection->save();
            $response['type'] = 'success';
            $response['question_level_id'] = $questionSection->id;
            $response['message'] = "The section <strong>{$data['name']}</strong> has been added.";
        } catch (Exception $e) {
            $h = new Handler(app());
            $h->report($e);
            $response['message'] = "We were unable to update the question section.  Please try again.";

        }
        return $response;
    }

}
