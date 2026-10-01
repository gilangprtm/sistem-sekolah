<?php

namespace App\Models;

use Database\Factories\TeacherSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSubject extends Model
{
    /** @use HasFactory<TeacherSubjectFactory> */
    use HasFactory;

    protected $table = 'm_teacher_subjects';

    protected $fillable = ['teacher_id', 'subject_id', 'suffix', 'code'];

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
