<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class IsValidMinNumberForDiscussIt implements Rule
{
    /**
     * @var string
     */
    private $message;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Determine if the validation rule passes.
     *
     * min_number_of_initiated_discussion_threads, min_number_of_replies, and
     * min_number_of_initiate_or_reply_in_threads are independent completion
     * criteria: DiscussionComment::numberOfRepliesThatSatisfiedTheRequirements()
     * explicitly excludes the comment that initiated a thread, so a thread-starting
     * comment is never also counted as a "reply". There is no requirement that one
     * of these minimums be greater than or equal to another.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $options = ["options" => ["min_range" => 0]];
        $this->message = '';

        if (filter_var($value, FILTER_VALIDATE_INT, $options) === false) {
            $this->message = "The min number of submitted replies should be an integer greater than or equal to 0.";
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->message;
    }
}
