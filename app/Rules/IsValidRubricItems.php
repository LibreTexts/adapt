<?php

namespace App\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule;

class IsValidRubricItems implements Rule
{
    /**
     * @var array
     */
    private $message;
    /**
     * @var string static|dynamic
     */
    private $scoring_style;

    /**
     * @param string $scoring_style static (unchanged rules) or dynamic (0-point items allowed)
     */
    public function __construct(string $scoring_style = 'static')
    {
        $this->scoring_style = $scoring_style === 'dynamic' ? 'dynamic' : 'static';
    }


    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     * @throws Exception
     */
    public function passes($attribute, $value)
    {
        $this->message = [];
        $passes = true;
        foreach ($value as $key => $item) {
            $this->message[$key] = ['title' => 'passes', 'points' => 'passes'];
        }
        $criteria = [];
        foreach ($value as $key => $item) {
            if (!$item['title']) {
                $this->message[$key]['title'] = 'A title is required.';
                $passes = false;
            } else {
                if (in_array(strtolower($item['title']), $criteria)) {
                    $this->message[$key]['title'] = 'This title already exists.';
                    $passes = false;
                }
                $criteria[] = strtolower($item['title']);
            }

            $points_missing = $this->scoring_style === 'dynamic'
                ? (!isset($item['points']) || $item['points'] === '' || $item['points'] === null) //0 is allowed in dynamic rubrics
                : !$item['points'];
            if ($points_missing
                || $item['points'] < 0
                || filter_var($item['points'], FILTER_VALIDATE_FLOAT) === false) {
                $passes = false;
                $this->message[$key]['points'] = "The points should be a number that is at least 0.";
            }

        }
        return $passes;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return json_encode($this->message);
    }
}
