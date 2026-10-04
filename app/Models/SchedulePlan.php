<?php

namespace App\Models;

use Database\Factories\SchedulePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchedulePlan extends Model
{
    /** @use HasFactory<SchedulePlanFactory> */
    use HasFactory;

    protected $table = 'tr_curriculum_schedule_plans';

    protected $fillable = [
        'academic_period_id', 'grade_level', 'source_plan_id', 'created_by', 'status',
        'revision', 'payload_hash', 'selected_rombel_ids', 'published_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'revision' => 'integer',
            'selected_rombel_ids' => 'array',
        ];
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return HasMany<ScheduleTeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(ScheduleTeachingAssignment::class, 'schedule_plan_id');
    }

    /** @return HasMany<ScheduleEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    /** @return HasMany<ScheduleCustomSlot, $this> */
    public function customSlots(): HasMany
    {
        return $this->hasMany(ScheduleCustomSlot::class);
    }
}
