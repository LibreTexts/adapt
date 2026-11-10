<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by check:lmsStatus when one or more schools' LMS status changes between runs (up -> down, down -> up,
 * up -> auth_error...). One email per run covers every change in that run. check:lmsStatus --test-email sends it on
 * demand with "[TEST]" in the subject.
 */
class LmsStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array each: name, lms, base_url, from, status, http_code, response_ms, error */
    public $changes;

    /** @var array total, up, down, auth_error, by_lms => [label => [total, up]] for the whole run */
    public $counts;

    /** @var bool */
    public $isTest;

    public function __construct(array $changes, array $counts, bool $isTest = false)
    {
        $this->changes = $changes;
        $this->counts = $counts;
        $this->isTest = $isTest;
    }

    /**
     * @return LmsStatusChanged
     */
    public function build()
    {
        $problems = count(array_filter($this->changes, function ($change) {
            return $change['status'] !== 'up';
        }));
        $recovered = count($this->changes) - $problems;
        $parts = [];
        if ($problems) {
            $parts[] = $problems . ' ' . ($problems === 1 ? 'school has a problem' : 'schools have problems');
        }
        if ($recovered) {
            $parts[] = $recovered . ' recovered';
        }
        if (!$parts) {
            $parts[] = 'all ' . $this->counts['total'] . ' up';
        }

        return $this->view('emails.lms_status_changed')
            ->subject(($this->isTest ? '[TEST] ' : '') . 'LMS status: ' . implode(', ', $parts))
            ->with(array_merge(
            // sunny.blade.php expects the Beautymail layout variables; see LmsOutageEnabled for why they're passed here.
                config('beautymail.view', []),
                [
                    'changes' => $this->changes,
                    'counts' => $this->counts,
                    'is_test' => $this->isTest,
                    'dashboard_url' => rtrim(config('app.url'), '/') . '/control-panel/lti-integrations',
                ]
            ));
    }
}
