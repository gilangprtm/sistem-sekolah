<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleEntry extends Model
{
    protected $table = 'tr_curriculum_schedule_entries';

    protected $fillable = [
        'schedule_plan_id', 'teaching_assignment_id', 'academic_period_id',
        'rombel_id', 'subject_id', 'teacher_id', 'day', 'lesson_number',
        'start_time', 'end_time', 'subject_code', 'subject_name',
        'teacher_name', 'rombel_code',
    ];

    protected function casts(): array
    {
        return ['lesson_number' => 'integer'];
    }

    /** @return BelongsTo<SchedulePlan, $this> */
    public function schedulePlan(): BelongsTo
    {
        return $this->belongsTo(SchedulePlan::class);
    }

    /** @return BelongsTo<ScheduleTeachingAssignment, $this> */
    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(ScheduleTeachingAssignment::class, 'teaching_assignment_id');
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
