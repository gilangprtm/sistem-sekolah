<?php

namespace App\Models;

use Database\Factories\StudentAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    /** @use HasFactory<StudentAttendanceFactory> */
    use HasFactory;

    public const STATUSES = ['H', 'I', 'S', 'D', 'A'];

    protected $table = 'tr_student_attendances';

    protected $fillable = ['academic_year_id', 'student_id', 'attendance_date', 'status', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'version' => 'integer'];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
