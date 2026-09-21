<?php

namespace App\Http\Requests;

use App\Rules\IsValidMinNumberForDiscussIt;
use App\Rules\IsValidNumberOfDiscussItGroups;
use App\Rules\IsValidPeriodOfTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateDiscussItSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request): array
    {
        $assignment_id = $request->route('assignment')->id;
        $question_id = $request->route('question')->id;
        $response_modes = $this->response_modes ?: [];
        $rules = [
            'response_modes' => ['required'],
            'number_of_groups' => new IsValidNumberOfDiscussItGroups($assignment_id, $question_id),
            "students_can_edit_comments" => ['required', Rule::in([0, 1])],
            "students_can_delete_comments" => ['required', Rule::in([0, 1])],
            "min_number_of_initiated_discussion_threads" => ['required', 'integer', 'min:0'],
            "min_number_of_comments" => ['integer', 'min:0'],
            "min_number_of_replies" => ['required', new IsValidMinNumberForDiscussIt()],
            "min_number_of_initiate_or_reply_in_threads" => ['required', 'integer', 'min:0'],
            "min_number_of_words" => in_array('text', $response_modes) ? ['required', 'integer', 'min:1'] : '',
            'min_length_of_audio_video' => in_array('audio', $response_modes) || in_array('video', $response_modes) ? ['required', new IsValidPeriodOfTime()] : '',
            'auto_grade' => ['required', Rule::in(0, 1)],
            'completion_criteria' => ['required', Rule::in(0, 1)]];

        if (is_array($response_modes) && (in_array('audio', $response_modes) || in_array('video', $response_modes))) {
            $language_codes = [
                'en', 'fr', 'es', 'de', 'zh', 'ar', 'it', 'ru', 'pt', 'ja', 'hi', 'bn', 'pa'
            ];
            $rules['language'] = ['required', Rule::in($language_codes)];
        }
        return $rules;
    }

    public function messages()
    {
        $messages['response_modes.required'] = "At least one method of responding is required.";
        return $messages;

    }
}
