<?php

namespace App\Http\Requests;

use App\Rules\IsValidRubricItems;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRubricRequest extends FormRequest
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

        $rules['rubric_items'] = ['required', new IsValidRubricItems($this->scoring_style === 'dynamic' ? 'dynamic' : 'static')];
        $rules['scoring_style'] = ['nullable', Rule::in(['static', 'dynamic', 'classic'])]; //'classic' is the old name for static, still accepted from pages loaded before the rename
        if ($this->validate_name_and_description) {
            $unique = Rule::unique('rubric_templates')
                ->where('user_id', $this->user()->id);
            if ($this->update_existing_template) {
                $unique = $unique->ignore($this->id);
            }
            $rules['name'] = ['required', $unique];
            $rules['description'] = 'required';
        }
        return $rules;
    }
}
