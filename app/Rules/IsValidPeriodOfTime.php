<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Rule;
use Throwable;

class IsValidPeriodOfTime implements Rule
{
    /**
     * @var bool
     */
    private $allowNegative;

    /**
     * Create a new rule instance.
     *
     * @param bool $allowNegative true where shifting backwards in time makes sense (e.g. shifting dates)
     * @return void
     */
    public function __construct(bool $allowNegative = false)
    {
        $this->allowNegative = $allowNegative;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        try {
            $base = CarbonImmutable::now();
            $shifted = $base->add($value);
            //"0 minutes" never counts; negative periods only count when they've been opted into
            return $this->allowNegative
                ? $shifted->notEqualTo($base)
                : $shifted->greaterThan($base);
        } catch (Throwable $e) {
            return false;
        }

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This should be a valid period of time such as 1 hour, 2 days, or 5 minutes.';
    }
}
