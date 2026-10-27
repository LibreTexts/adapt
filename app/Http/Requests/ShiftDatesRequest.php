<?php

namespace App\Http\Requests;

use App\Rules\IsValidPeriodOfTime;
use Illuminate\Foundation\Http\FormRequest;

class ShiftDatesRequest extends FormRequest
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
        //shift_by is only used when shifting by a period of time or a new first available on;
        //with a single common date it's ignored, so it shouldn't be able to block the save
        $rules = [];
        if ($this->input('shifting_method') !== 'assignment date property') {
            $rules['shift_by'] = new IsValidPeriodOfTime(true);
        }
        return $rules;
    }
}
