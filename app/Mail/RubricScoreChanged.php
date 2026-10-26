<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent (optionally) to a student whose question score changed because the dynamic rubric was edited.
 * Modeled on LmsOutageEnabled. It doesn't list which rubric items changed.
 */
class RubricScoreChanged extends Mailable
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

    public $student_first_name;
    public $assignment_name;
    public $question_title;
    public $old_score;
    public $new_score;
    public $question_points;
    public $link;
    public $cleared;

    /**
     * @param string $student_first_name
     * @param string $assignment_name
     * @param string $question_title
     * @param string $old_score
     * @param string $new_score
     * @param string $question_points
     * @param string $link
     * @param bool $cleared the score was cleared (rubric converted to dynamic) so the student will be regraded
     */
    public function __construct(string $student_first_name,
                                string $assignment_name,
                                string $question_title,
                                string $old_score,
                                string $new_score,
                                string $question_points,
                                string $link,
                                bool   $cleared = false)
    {
        $this->student_first_name = $student_first_name;
        $this->assignment_name = $assignment_name;
        $this->question_title = $question_title;
        $this->old_score = $old_score;
        $this->new_score = $new_score;
        $this->question_points = $question_points;
        $this->link = $link;
        $this->cleared = $cleared;
    }

    /**
     * @return RubricScoreChanged
     */
    public function build()
    {
        return $this->view('emails.rubric_score_changed')
            ->subject('Your score was updated: ' . $this->assignment_name)
            ->with(array_merge(
            // sunny.blade.php (and its partials) expect $logo, $twitter, etc., which Beautymail::send()
            // normally injects; this is a queueable Mailable, so pass them through (as LmsOutageEnabled does).
                config('beautymail.view', []),
                [
                    'student_first_name' => $this->student_first_name,
                    'assignment_name' => $this->assignment_name,
                    'question_title' => $this->question_title,
                    'old_score' => $this->old_score,
                    'new_score' => $this->new_score,
                    'question_points' => $this->question_points,
                    'link' => $this->link,
                    'cleared' => $this->cleared,
                ]
            ));
    }
}
