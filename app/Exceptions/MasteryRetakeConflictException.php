<?php

namespace App\Exceptions;

use RuntimeException;

class MasteryRetakeConflictException extends RuntimeException
{
    private $reason;

    /**
     * Create a conflict with a stable reason code for API clients.
     */
    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    /**
     * Return the machine-readable reason for the rejected lifecycle action.
     */
    public function reason(): string
    {
        return $this->reason;
    }
}
