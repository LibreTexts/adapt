<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradingRequest extends FormRequest
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
     */
    public function rules()
    {
        $rules['text_feedback_editor']= Rule::in(['rich','plain']);
        if ($this->question_submission_score !== null){
            $rules['question_submission_score'] = 'numeric|gte:0';
         }
        if ($this->file_submission_score !== null){
            $rules['file_submission_score'] = 'numeric|gte:0';
        }
        if ($this->applied_late_penalty !== null){
            $rules['applied_late_penalty'] = 'numeric|gte:0|lte:100';

        }
        if ($this->dynamic_rubric !== null) {
            //dynamic rubric: the applied item ids; the server calculates file_submission_score from them
            $rules['dynamic_rubric'] = 'array';
            $rules['dynamic_rubric.applied_item_ids'] = 'present|array';
            $rules['dynamic_rubric.applied_item_ids.*'] = 'string|distinct';
            $rules['dynamic_rubric.adjustment'] = 'nullable|numeric';
            $rules['dynamic_rubric.rubric_version'] = 'required|integer|gte:0';
        }
       return $rules;

    }
}
