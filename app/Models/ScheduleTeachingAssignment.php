<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTeachingAssignment extends Model
{
    protected $table = 'tr_curriculum_teaching_assignments';

    protected $fillable = [
        'schedule_plan_id', 'academic_period_id', 'rombel_id', 'subject_id',
        'teacher_subject_id', 'teacher_id', 'weekly_jp', 'subject_code',
        'subject_name', 'teacher_name', 'rombel_code', 'status',
    ];

    protected function casts(): array
    {
        return ['weekly_jp' => 'integer'];
    }

    /** @return BelongsTo<SchedulePlan, $this> */
    public function schedulePlan(): BelongsTo
    {
        return $this->belongsTo(SchedulePlan::class);
    }

    /** @return BelongsTo<TeacherSubject, $this> */
    public function teacherSubject(): BelongsTo
    {
        return $this->belongsTo(TeacherSubject::class, 'teacher_subject_id');
    }
}
