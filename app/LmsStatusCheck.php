<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class LmsStatusCheck
 *
 * The result of probing one LTI registration (Canvas, Brightspace, Blackboard or Moodle) during one run of
 * check:lmsStatus. See LmsStatusProbe for how the probe works and how each status is decided.
 *
 * @package App
 */
class LmsStatusCheck extends Model
{
    const UP = 'up';
    const DOWN = 'down';
    const AUTH_ERROR = 'auth_error';

    public $timestamps = false;

    protected $fillable = ['lti_registration_id', 'checked_at', 'status', 'http_code', 'response_ms', 'error'];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function ltiRegistration()
    {
        return $this->belongsTo(LtiRegistration::class);
    }
}
