<?php

namespace App\Models;

use Database\Factories\TeacherStudentNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherStudentNote extends Model
{
    /** @use HasFactory<TeacherStudentNoteFactory> */
    use HasFactory;

    protected $table = 'tr_teacher_student_notes';

    protected $fillable = ['student_id', 'teacher_id', 'teacher_name', 'content'];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
