<?php

namespace App\Http\Requests;

use App\Helpers\Helper;
use App\Question;
use App\Rules\atLeastOneFillInTheBlank;
use App\Rules\atLeastOneSelectChoice;
use App\Rules\atLeastTwoMatches;
use App\Rules\atLeastTwoResponses;
use App\Rules\AtomsAndBonds;
use App\Rules\AutoGradedDoesNotExist;
use App\Rules\BowTieItems;
use App\Rules\correctResponseRequired;
use App\Rules\DragAndDropClozeDistractors;
use App\Rules\DragAndDropClozePrompt;
use App\Rules\DropDownTableRows;
use App\Rules\hasValid3DModelParameters;
use App\Rules\hasValid3dModelSolutionStructure;
use App\Rules\HasValidAccountingJournalEntries;
use App\Rules\HasValidAccountingMultiPartComputation;
use App\Rules\HasValidAccountingReport;
use App\Rules\HasValidFlashcard;
use App\Rules\HighlightTableHeaders;
use App\Rules\HighlightTableRows;
use App\Rules\HighlightTextPrompt;
use App\Rules\HighlightTextResponses;
use App\Rules\IsCorrectNumberOfSelectChoices;
use App\Rules\isValidA11yAutoGradedQuestionId;
use App\Rules\IsValidCourseAssignmentTopic;
use App\Rules\IsValidDropDownTriadCauseAndEffects;
use App\Rules\IsValidFrameworkItemSyncQuestion;
use App\Rules\IsValidLearningOutcomes;
use App\Rules\IsValidMatchingPrompt;
use App\Rules\IsValidNumericalPrompt;
use App\Rules\IsValidQtiPrompt;
use App\Rules\IsValidQuestionChapterId;
use App\Rules\IsValidQuestionSectionId;
use App\Rules\IsValidQuestionSubjectId;
use App\Rules\IsValidRubricItems;
use App\Rules\IsValidRubricTemplate;
use App\Rules\IsValidSelectChoice;
use App\Rules\IsValidSketcherStructure;
use App\Rules\MatrixMultipleResponseColumns;
use App\Rules\MatrixMultipleResponseRows;
use App\Rules\MultipleResponseSelectPrompt;
use App\Rules\MultipleResponseSelectResponses;
use App\Rules\oneCauseAndTwoEffectsInBody;
use App\Rules\RubricCategories;
use App\Rules\TableHeaders;
use App\Rules\MatrixMultipleChoiceRows;
use App\Rules\MultipleResponseGroupingRows;
use App\Rules\nonRepeatingMatchingTerms;
use App\Rules\nonRepeatingSimpleChoice;
use App\Rules\nonRepeatingTermsToMatch;
use App\Rules\ValidNumericalPlaceholders;
use App\SavedQuestionsFolder;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     * @throws Exception
     */
    public function rules(Question $question)
    {
        $rules = [
            'question_type' => Rule::in('assessment', 'exposition'),
            'public' => 'required',
            'title' => 'required|string',
            'description' => 'nullable',
            'question_subject_id' => new IsValidQuestionSubjectId(),
            'question_chapter_id' => new IsValidQuestionChapterId($this->question_subject_id),
            'question_section_id' => new IsValidQuestionSectionId($this->question_chapter_id),
            'author' => 'required',
            'tags' => 'nullable',
            'open_ended_submission_type' => ['required', Rule::in('rich text', 'file', 'audio', '0', 'no submission, manual grading',)],
            'framework_item_sync_question' => new IsValidFrameworkItemSyncQuestion(),
            'text_question' => 'nullable',
            'a11y_auto_graded_question_id' => new isValidA11yAutoGradedQuestionId(),
            'answer_html' => 'nullable',
            'solution_html' => 'nullable',
            'hint' => 'nullable',
            'notes' => 'nullable',
            'license' => ['required', Rule::in($question->getValidLicenses())],
            'license_version' => 'nullable',
            'question_editor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', [2, 5])],
        ];
        // source_url not required for bulk imports
        $rules['source_url'] = $this->source_url_required ? 'required|url' : 'nullable';
        if ($this->route()->getActionMethod() === 'update') {
            if (Helper::isAdmin() || $question->folderIdRequired($this->user(), Question::find($this->id)->question_editor_user_id)) {
                $rules['folder_id'] = ['required'];
            }
            if (Helper::isAdmin() && $this->question_editor_user_id) {
                $rules['folder_id'][] = function ($attribute, $value, $fail) {
                    $folder = SavedQuestionsFolder::where('id', $value)
                        ->where('user_id', $this->question_editor_user_id)
                        ->where('type', 'my_questions')
                        ->first();
                    if (!$folder) {
                        $fail('The folder must belong to the new question owner.');
                    }
                };
            }
        } else {
            $rules['folder_id'] = ['required'];
        }
        if ($this->course_id || $this->assignment || $this->topic) {
            $rules['folder_id'][] = new IsValidCourseAssignmentTopic($this->course_id, $this->assignment, $this->topic);
        }
        if ($this->learning_outcomes) {
            $rules['learning_outcomes'] = new IsValidLearningOutcomes($this->learning_outcomes);
        }
        if ($this->rubric_items) {
            $rules['rubric_items'] = ['required', new IsValidRubricItems()];
            $rules['rubric_shown'] = ['required', Rule::in(true, false)];
        }
        if ($this->rubric_template_save_option && $this->rubric_template_save_option !== 'do not save as template') {
            $is_edit = $this->rubric_template_save_option === 'update existing template';
            $rules['rubric_name'] = ['required', new IsValidRubricTemplate($is_edit, $this->rubric_template_id, $this->rubric_name, $this->user()->id)];
            $rules['rubric_description'] = 'required';
            if ($this->rubric_template_save_option === 'update existing template') {
                $rules['rubric_template_id'] = ['required', new IsValidRubricTemplate($is_edit, $this->rubric_template_id, $this->rubric_name, $this->user()->id)];

            }
        }
        switch ($this->question_type) {
            case('report'):
            {
                $rules['rubric_categories'] = new RubricCategories();
                $rules['purpose'] = 'required';
                $rules['grading_style_id'] = ['required', Rule::exists('grading_styles', 'id')
                ];
                $rules['non_technology_text'] = 'required';
                $rules['technology'] = ['required', Rule::in(['text'])];
                break;
            }
            case('assessment'):
                if ($this->technology === 'text') {
                    $rules['non_technology_text'] = 'required';
                    $rules['technology'] = 'nullable';
                    $rules['technology_id'] = 'nullable';
                } else {
                    $rules['non_technology_text'] = 'nullable';
                    $rules['technology'] = ['required', Rule::in(['text', 'webwork', 'h5p', 'imathas', 'qti'])];
                    switch ($this->technology) {
                        case('webwork'):
                            if ($this->new_auto_graded_code === 'webwork') {
                                $rules['webwork_code'] = ['required', 'string'];
                            } else {
                                $rules['technology_id'] = ['required', 'string'];
                            }
                            break;
                        case('h5p'):
                        case('imathas'):
                            $rules['technology_id'] = ['required', 'integer', 'not_in:0'];
                            break;
                        case('qti'):
                            $qti_array = json_decode($this->qti_json, true);
                            switch ($qti_array['questionType']) {
                                case('flashcard'):
                                    $rules['qti_json'] = new HasValidFlashcard();
                                    break;
                                case('forge'):
                                    $rules['qti_prompt'] = ['required'];
                                    break;
                                case('accounting_report'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['qti_json'] = new HasValidAccountingReport();
                                    break;
                                case('accounting_multi_part_computation'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['qti_json'] = new HasValidAccountingMultiPartComputation();
                                    break;
                                case('accounting_journal_entry'):
                                    $rules['qti_json'] = new HasValidAccountingJournalEntries();
                                    break;
                                case('three_d_model_multiple_choice'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['parameters'] = new hasValid3DModelParameters();
                                    $rules['solution_structure'] = new hasValid3dModelSolutionStructure();
                                    break;
                                case('three_d_model_multiple_answer'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['parameters'] = new hasValid3DModelParameters();
                                    break;
                                case('discuss_it'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['media_uploads'] = ['required', "array", "min:1"];
                                    break;
                                case('submit_molecule'):
                                case('marker'):
                                case('pushing_arrows'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['solution_structure'] = ['required', new isValidSketcherStructure($qti_array['questionType'])];
                                    if ($qti_array['questionType'] === 'marker' && $qti_array['partialCredit'] === 'inclusive') {
                                        $rules['atoms_and_bonds'] = ['required', new AtomsAndBonds()];
                                    }
                                    break;
                                case
                                ('highlight_table'):
                                    $rules['colHeaders'] = ['required', new HighlightTableHeaders()];
                                    $rules['rows'] = ['required', new HighlightTableRows()];
                                    break;
                                case('highlight_text'):
                                    $rules['qti_prompt'] = ['required', new HighlightTextPrompt()];
                                    $rules['responses'] = ['required', new HighlightTextResponses()];
                                    break;
                                case('drag_and_drop_cloze'):
                                    $rules['qti_prompt'] = ['required', new DragAndDropClozePrompt($this['correct_responses'])];
                                    $rules['distractors'] = ['required', new DragAndDropClozeDistractors()];
                                    break;
                                case('bow_tie'):
                                    $rules['qti_prompt'] = ['required'];
                                    foreach (['actions_to_take', 'potential_conditions', 'parameters_to_monitor'] as $item) {
                                        $rules[$item] = ['required', new BowTieItems()];
                                    }
                                    break;

                                case('multiple_response_select_all_that_apply'):
                                case('multiple_response_select_n'):
                                    $rules['qti_prompt'] = ['required', new MultipleResponseSelectPrompt()];
                                    $number_to_select = ($qti_array['questionType'] === 'multiple_response_select_n') ? $qti_array['numberToSelect'] : null;
                                    $rules['responses'] = ['required', new MultipleResponseSelectResponses($qti_array['questionType'], $this['responses'], $number_to_select)];
                                    break;
                                case('matrix_multiple_response'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['colHeaders'] = ['required', new MatrixMultipleResponseColumns($this['colHeaders'], $this['rows'])];
                                    $rules['rows'] = ['required', new MatrixMultipleResponseRows($this['rows'])];
                                    break;
                                case('multiple_response_grouping'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['headers'] = ['required', new TableHeaders($this['headers'])];
                                    $rules['rows'] = ['required', new MultipleResponseGroupingRows($this['rows'])];
                                    break;
                                case('drop_down_table'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['colHeaders'] = ['required', new TableHeaders($this['colHeaders'])];
                                    $rules['rows'] = ['required', new DropDownTableRows($this['rows'])];
                                    break;
                                case('matrix_multiple_choice'):
                                    $rules['qti_prompt'] = ['required'];
                                    $rules['headers'] = ['required', new TableHeaders($this['headers'], 3)];
                                    $rules['rows'] = ['required', new MatrixMultipleChoiceRows($this['rows'])];
                                    break;
                                case('numerical'):
                                    $rules['qti_prompt'] = ['required', new IsValidNumericalPrompt($this->qti_json, $this->route('question'))];
                                    $rules['correct_response'] = 'required|numeric';
                                    $qti = json_decode($this->qti_json, true);
                                    $toleranceType = $qti['correctResponse']['toleranceType'] ?? 'absolute';
                                    if ($toleranceType === 'relative') {
                                        $rules['relative_tolerance'] = 'required|numeric|min:0';
                                    } else {
                                        $rules['margin_of_error'] = 'required|numeric|min:0';
                                    }
                                    break;
                                case('multi_numerical'):
                                    if ($this->input('placeholders') !== null) {
                                        $rules['qti_prompt'] = [
                                            'required',
                                            function ($attribute, $value, $fail) {
                                                if (!preg_match('/<u>.+?<\/u>/s', $value ?? '')) {
                                                    $fail('The prompt must contain at least one underlined blank.');
                                                }
                                            },
                                        ];
                                        $rules['placeholders'] = ['required', new ValidNumericalPlaceholders()];
                                    } else {
                                        // Legacy fallback
                                        $rules['correct_response'] = 'required|numeric';
                                        $rules['margin_of_error'] = 'required|numeric|min:0';
                                    }
                                    break;
                                case('matching'):
                                    $rules['qti_prompt'] = ['required', new IsValidMatchingPrompt($qti_array['questionType'], $this->qti_json, $this->route('question'))];
                                    foreach ($this->all() as $key => $value) {
                                        if (strpos($key, 'qti_matching_') !== false) {
                                            $rules[$key] = ['required'];
                                        }
                                    }
                                    $rules['qti_matching_term_to_match_0'][] = new nonRepeatingTermsToMatch($qti_array);
                                    $rules['qti_matching_term_to_match_0'][] = new nonRepeatingMatchingTerms($qti_array);
                                    $rules['qti_matching_term_to_match_0'][] = new atLeastTwoMatches($qti_array);
                                    break;
                                case('multiple_answers'):
                                case('multiple_choice'):
                                case('true_false'):
                                    $rules['qti_prompt'] = ['required', new IsValidQtiPrompt($this->qti_json, $this->route('question'))];
                                    $qti_simple_choices = [];
                                    foreach ($this->all() as $key => $value) {
                                        if (strpos($key, 'qti_simple_choice_') !== false) {
                                            $qti_simple_choices[] = $value;
                                            $rules[$key] = ['required'];
                                        }
                                    }
                                    $rules['qti_simple_choice_0'][] = new nonRepeatingSimpleChoice($qti_simple_choices);
                                    $rules['qti_simple_choice_0'][] = new correctResponseRequired($this->qti_json);
                                    $rules['qti_simple_choice_0'][] = new atLeastTwoResponses($qti_simple_choices);
                                    if ($qti_array['questionType'] === 'multiple_choice') {
                                        $rules['qti_randomize_order'] = ['required', Rule::in('yes', 'no')];
                                    }
                                    break;
                                case('drop_down_rationale_triad'):
                                    /// body needs 1 [condition] and 2 [rationales]
                                    foreach ($this->all() as $key => $value) {
                                        if (strpos($key, 'qti_select_choice_') !== false) {
                                            $identifier = str_replace('qti_select_choice_', '', $key);
                                            $rules[$key] = new IsValidDropDownTriadCauseAndEffects($value, $identifier);
                                        }
                                    }
                                    $rules['qti_item_body'] = ['required', new oneCauseAndTwoEffectsInBody($this->qti_item_body)];
                                    break;
                                case('drop_down_rationale'):
                                case ('select_choice'):
                                    foreach ($this->all() as $key => $value) {
                                        if (strpos($key, 'qti_select_choice_') !== false) {
                                            $identifier = str_replace('qti_select_choice_', '', $key);
                                            $rules[$key] = new IsValidSelectChoice($qti_array, $identifier);
                                        }
                                    }
                                    $rules['qti_item_body'] = ['required', new atLeastOneSelectChoice($qti_array)];
                                    if (isset($qti_array['dropDownRationaleType'])) {
                                        $rules['qti_item_body'][] = new IsCorrectNumberOfSelectChoices($qti_array);
                                    }
                                    break;
                                case('fill_in_the_blank'):
                                    $rules['qti_item_body'] = ['required', new atLeastOneFillInTheBlank($qti_array)];
                                    break;
                                default:
                                    throw new Exception ("{$qti_array['questionType']} does not yet have validation.");
                            }
                            break;
                        case('text'):
                            $rules['technology_id'] = ['nullable'];
                    }
                    $question_id = $this->id ?? null;
                    if (!$this->bulk_upload_into_assignment) {
                        if ($this->technology !== 'qti') {
                            $rules['technology_id'][] = new AutoGradedDoesNotExist($this->technology, $this->webwork_code, $question_id);
                        }
                    }
                }
                break;
            case
            ('exposition'):
                $rules['non_technology_text'] = 'required';
                $rules['technology'] = ['required', Rule::in(['text'])];
        }


        return $rules;
    }

    public
    function messages()
    {
        $messages = [];

        if ($this->technology === 'webwork') {
            $messages['technology_id.required'] = 'The file path field is required.';
            $messages['webwork_code.required'] = 'WeBWork code is required.';
        }
        $messages['non_technology_text.required'] = $this->question_type === 'assessment'
            ? 'Either the Open-Ended Content field or the technology field is required.'
            : 'The Open-Ended Content field is required.';
        $messages['folder_id.required'] = "The folder is required.";
        $messages['question_editor_user_id.exists'] = 'The new owner must be an instructor or non-instructor editor.';

        foreach ($this->all() as $key => $value) {
            if (strpos($key, 'qti_simple_choice_') !== false) {
                $index = str_replace("qti_simple_choice_", '', $key) + 1;
                $messages["$key.required"] = "Response $index is missing an entry.";
            }
        }
        foreach ($this->all() as $key => $value) {
            if (strpos($key, 'qti_matching_term_to_match_') !== false) {
                $index = str_replace("qti_matching_term_to_match_", '', $key) + 1;
                $messages["$key.required"] = "The term to match from Matching $index is required.";
            }
            if (strpos($key, 'qti_matching_matching_term_') !== false) {
                $index = str_replace("qti_matching_matching_term_", '', $key) + 1;
                $messages["$key.required"] = "The matching term from Matching $index is required.";
            }
            if (strpos($key, 'qti_matching_distractor_') !== false) {
                $index = str_replace("qti_matching_distractor_", '', $key) + 1;
                $messages["$key.required"] = "Distractor $index is required.";
            }


        }
        try {
            $qti_array = json_decode($this->qti_json, true);
        } catch (Exception $e) {


        }
        $messages['qti_prompt.required'] = isset($qti_array['questionType']) && $qti_array['questionType'] === 'discuss_it'
            ? "Instructions are required."
            : "A prompt is required.";
        $messages['qti_item_body.required'] = "The question text is required.";
        $messages['source_url.url'] = "Please enter a valid URL.";
        $messages['open_ended_submission_type.required'] = "The open-ended submission type field is required.";
        return $messages;
    }

}
