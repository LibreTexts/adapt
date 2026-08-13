<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MasteryAssignmentAttempt extends Model
{
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_MASTERED = 'mastered';

    protected $guarded = [];

    protected $casts = [
        'question_ids' => 'array',
        'variant_identifiers' => 'array',
        'question_results' => 'array',
        'score' => 'float',
        'possible_score' => 'float',
        'completed_at' => 'datetime'
    ];

    /**
     * Return the assignment whose question snapshot this attempt records.
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}
