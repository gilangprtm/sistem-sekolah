<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentQuizAttemptEvent extends Model
{
    protected $table = 'tr_teacher_quiz_attempt_events';

    protected $fillable = ['quiz_attempt_id', 'event_type', 'occurred_at', 'duration_seconds', 'interruption_count', 'actor_user_id', 'reason'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'duration_seconds' => 'integer',
            'interruption_count' => 'integer',
        ];
    }

    /** @return BelongsTo<StudentQuizAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(StudentQuizAttempt::class, 'quiz_attempt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
