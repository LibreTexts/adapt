<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class LmsOutageCourse
 *
 * A record of a course that had its LMS connection (courses.lms) turned
 * off by an admin because an entire LMS type (Canvas, Blackboard, or
 * Brightspace) was down, across all schools using it. `turned_on_at` is
 * null while the course is still affected; it's set once the admin
 * turns that LMS type back on. `school_id` / `lti_registration_id` are
 * captured for traceability; `lms_type` is what turning back on
 * actually filters by.
 *
 * @package App
 */
class LmsOutageCourse extends Model
{
    protected $fillable = ['course_id', 'school_id', 'lti_registration_id', 'lms_type', 'turned_on_at'];

    protected $dates = ['turned_on_at'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function ltiRegistration()
    {
        return $this->belongsTo(LtiRegistration::class);
    }

    public function scopeCurrentlyOff($query)
    {
        return $query->whereNull('turned_on_at');
    }

    public function scopeForLmsType($query, $lmsType)
    {
        return $query->where('lms_type', $lmsType);
    }
}
