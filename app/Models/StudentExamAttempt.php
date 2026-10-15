<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentExamAttempt extends Model
{
    protected $table = 'tr_curriculum_exam_attempts';

    protected $fillable = [
        'student_id',
        'uas_link_id',
        'status',
        'interruption_count',
        'started_at',
        'locked_at',
        'unlocked_by',
        'unlocked_at',
        'unlock_reason',
    ];

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

    /** @return BelongsTo<UasLink, $this> */
    public function uasLink(): BelongsTo
    {
        return $this->belongsTo(UasLink::class);
    }

    /** @return HasMany<StudentExamAttemptEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(StudentExamAttemptEvent::class, 'exam_attempt_id');
    }
}
