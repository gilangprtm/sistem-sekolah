<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleCustomSlot extends Model
{
    protected $table = 'tr_curriculum_schedule_custom_slots';

    protected $fillable = [
        'schedule_plan_id', 'academic_period_id', 'day', 'lesson_number',
        'start_time', 'end_time', 'label',
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

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
