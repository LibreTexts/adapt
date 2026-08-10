<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LmsOutageDisabled extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Number of times the queued job may be attempted before it's
     * considered failed and left in failed_jobs.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Seconds to wait before each retry (escalating backoff), in case
     * Mailgun returns a transient error/throttle response.
     *
     * @var array
     */
    public $backoff = [30, 60, 300, 600, 900];

    public $instructor_first_name;
    public $course_name;

    /**
     * @param string $instructor_first_name
     * @param string $course_name
     */
    public function __construct(string $instructor_first_name, string $course_name)
    {
        $this->instructor_first_name = $instructor_first_name;
        $this->course_name = $course_name;
    }

    /**
     * @return LmsOutageDisabled
     */
    public function build()
    {
        return $this->view('emails.lms_outage_disabled')
            ->subject('Action Needed: LMS Access Temporarily Disabled for ' . $this->course_name)
            ->with(array_merge(
            // sunny.blade.php (and its partials) expect $logo, $twitter,
            // $facebook, etc. which are normally injected automatically
            // when sending through the Beautymail::send() helper. Since
            // this is a standard queueable Mailable instead, pass them
            // through explicitly so the layout doesn't hit an undefined
            // variable - this matters especially when the mail is
            // rendered by a queue worker rather than a real request.
                config('beautymail.view', []),
                [
                    'instructor_first_name' => $this->instructor_first_name,
                    'course_name' => $this->course_name,
                ]
            ));
    }
}
