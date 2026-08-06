<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log: one row per "propagate" revision action, recording who did it
 * and exactly which rows it touched.
 *
 * @property int $id
 * @property int $question_revision_id
 * @property int $user_id
 * @property array $assignment_question_ids ordinary (non-tree) assignment_question rows updated
 * @property array $assignment_question_learning_tree_ids Learning Tree snapshot rows patched - join to assignment_question_learning_tree for the assignment_id/learning_tree_id behind each
 */
class QuestionRevisionPropagation extends Model
{
    protected $fillable = [
        'question_revision_id',
        'user_id',
        'assignment_question_ids',
        'assignment_question_learning_tree_ids'
    ];

    protected $casts = [
        'assignment_question_ids' => 'array',
        'assignment_question_learning_tree_ids' => 'array'
    ];

    public function questionRevision()
    {
        return $this->belongsTo(QuestionRevision::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
