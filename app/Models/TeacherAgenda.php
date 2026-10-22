<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $academic_period_id
 * @property Carbon $agenda_date
 * @property string $block_key
 * @property array<string, mixed> $schedule_snapshot
 * @property string $material
 * @property array<int, array<string, mixed>> $attendance
 * @property Carbon|null $updated_at
 */
class TeacherAgenda extends Model
{
    protected $table = 'tr_teacher_agendas';

    /** @var list<string> */
    protected $fillable = [
        'teacher_id',
        'academic_period_id',
        'agenda_date',
        'block_key',
        'schedule_snapshot',
        'material',
        'attendance',
    ];

    protected function casts(): array
    {
        return [
            'agenda_date' => 'date:Y-m-d',
            'schedule_snapshot' => 'array',
            'attendance' => 'array',
        ];
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }
}
