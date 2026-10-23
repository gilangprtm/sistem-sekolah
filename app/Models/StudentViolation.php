<?php

namespace App\Models;

use Database\Factories\StudentViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentViolation extends Model
{
    /** @use HasFactory<StudentViolationFactory> */
    use HasFactory;

    protected $table = 'tr_student_affairs_violations';

    protected $fillable = ['student_id', 'violation_date', 'category', 'content', 'follow_up', 'created_by', 'updated_by', 'creator_name', 'updater_name'];

    protected $hidden = ['category', 'content', 'follow_up'];

    protected function casts(): array
    {
        return ['violation_date' => 'date:Y-m-d', 'category' => 'encrypted', 'content' => 'encrypted', 'follow_up' => 'encrypted'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<StudentAffairsViolationCategory, $this> */
    public function violationCategory(): BelongsTo
    {
        return $this->belongsTo(StudentAffairsViolationCategory::class, 'student_affairs_violation_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
