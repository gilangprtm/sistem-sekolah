<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExamAttemptEvent extends Model
{
    protected $table = 'tr_curriculum_exam_attempt_events';

    protected $fillable = [
        'exam_attempt_id',
        'event_type',
        'occurred_at',
        'duration_seconds',
        'interruption_count',
        'actor_user_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'duration_seconds' => 'integer',
            'interruption_count' => 'integer',
        ];
    }

    /** @return BelongsTo<StudentExamAttempt, $this> */
    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(StudentExamAttempt::class, 'exam_attempt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
