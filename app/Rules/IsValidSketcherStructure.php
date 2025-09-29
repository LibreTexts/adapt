<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IsValidSketcherStructure implements Rule
{
    private $question_type;
    /**
     * @var string
     */
    private $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($question_type)
    {
        $this->question_type = $question_type;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $solution_structure = json_decode($value, 1);
        $passes = false;
        foreach (['atoms', 'bonds', 'arrows'] as $item) {
            if (isset($solution_structure[$item]) && $solution_structure[$item]) {
                $passes = true;
            }
        }
        if (!$passes) {
            $this->message = 'You have not entered a structure into the Sketcher.';
            return false;
        }
        if ($this->question_type === 'pushing_arrows' && $solution_structure['arrows'] === []) {
            $this->message = "You have no arrows in your structure.";
            return false;

        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}
