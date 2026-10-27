<?php

namespace App\Models;

use Database\Factories\TeacherQuizFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherQuiz extends Model
{
    /** @use HasFactory<TeacherQuizFactory> */
    use HasFactory;

    protected $table = 'tr_teacher_quizzes';

    protected $fillable = ['created_by', 'academic_year_id', 'academic_period_id', 'subject_id', 'link', 'is_open'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<StudentQuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(StudentQuizAttempt::class, 'teacher_quiz_id');
    }
}
