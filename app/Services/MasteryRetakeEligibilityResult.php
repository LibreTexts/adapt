<?php

namespace App\Services;

class MasteryRetakeEligibilityResult
{
    private $reasons;

    /**
     * Store eligibility failures in their deterministic evaluation order.
     */
    public function __construct(array $reasons = [])
    {
        $this->reasons = array_values($reasons);
    }

    /**
     * Return whether no eligibility failures were found.
     */
    public function eligible(): bool
    {
        return count($this->reasons) === 0;
    }

    /**
     * Return all machine-readable eligibility failures.
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    /**
     * Return the first instructor-facing failure message, if any.
     */
    public function firstMessage(): string
    {
        return $this->eligible()
            ? ''
            : $this->reasons[0]['message'];
    }

}
