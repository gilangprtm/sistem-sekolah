<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentQuizAttempt extends Model
{
    protected $table = 'tr_teacher_quiz_attempts';

    protected $fillable = ['student_id', 'teacher_quiz_id', 'status', 'interruption_count', 'started_at', 'locked_at', 'unlocked_by', 'unlocked_at', 'unlock_reason'];

    protected function casts(): array
    {
        return [
            'interruption_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'unlocked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<TeacherQuiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(TeacherQuiz::class, 'teacher_quiz_id');
    }

    /** @return HasMany<StudentQuizAttemptEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(StudentQuizAttemptEvent::class, 'quiz_attempt_id');
    }
}
