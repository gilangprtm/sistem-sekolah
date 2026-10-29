<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /** @param Builder<ScheduleEntry> $query */
    #[Scope]
    protected function conflictingWith(Builder $query, int $teacherId, int $subjectId, int $rombelId): void
    {
        $query->where(function (Builder $query) use ($teacherId, $subjectId, $rombelId): void {
            $query->where('rombel_id', $rombelId)
                ->orWhere(function (Builder $query) use ($teacherId, $subjectId): void {
                    $query->where('teacher_id', $teacherId)
                        ->where(function (Builder $query) use ($subjectId): void {
                            $query->where('subject_id', '!=', $subjectId)
                                ->orWhereHas('subject', fn (Builder $query) => $query->where('allow_combined', false));
                        });
                });
        });
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
