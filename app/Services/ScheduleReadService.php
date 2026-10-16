<?php

namespace App\Services;

use App\Models\Rombel;
use App\Models\ScheduleCustomSlot;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use Illuminate\Support\Collection;

final class ScheduleReadService
{
    public function publishedPlanForGrade(int $periodId, string $grade): ?SchedulePlan
    {
        $publishedPlan = SchedulePlan::query()
            ->with('customSlots')
            ->where('academic_period_id', $periodId)
            ->where('grade_level', $grade)
            ->where('status', 'published')
            ->latest('id')
            ->first();

        if ($publishedPlan !== null) {
            return $publishedPlan;
        }

        return SchedulePlan::query()
            ->with('customSlots')
            ->where('academic_period_id', $periodId)
            ->whereNull('grade_level')
            ->whereIn('status', ['published', 'archived'])
            ->whereHas('teachingAssignments', fn ($query) => $query->whereIn(
                'rombel_id',
                Rombel::query()
                    ->where('grade_level', $grade)
                    ->select('id'),
            ))
            ->latest('id')
            ->first();
    }

    public function publishedPlanForRombel(int $periodId, int $rombelId): ?SchedulePlan
    {
        return SchedulePlan::query()
            ->with('customSlots')
            ->where('academic_period_id', $periodId)
            ->where('status', 'published')
            ->whereHas('entries', fn ($query) => $query->where('rombel_id', $rombelId))
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, int>  $rombelIds
     * @return Collection<int, ScheduleEntry>
     */
    public function entriesForWeb(?int $periodId, array $rombelIds, ?int $planId = null): Collection
    {
        if ($periodId === null || $rombelIds === []) {
            return collect();
        }

        return ScheduleEntry::query()
            ->with([
                'subject:id,color',
                'teachingAssignment.teacherSubject:id,code',
            ])
            ->where('academic_period_id', $periodId)
            ->whereIn('rombel_id', $rombelIds)
            ->when($planId !== null, fn ($query) => $query->where('schedule_plan_id', $planId))
            ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'))
            ->orderBy('day')
            ->orderBy('lesson_number')
            ->get()
            ->each(function (ScheduleEntry $entry): void {
                $entry->setAttribute('schedule_entry_id', $entry->id);
                $entry->setAttribute('schedule_plan_id', $entry->schedule_plan_id);
                $entry->setAttribute('assignment_code', $entry->teachingAssignment->teacherSubject->code);
                $entry->setAttribute('subject_color', $entry->subject->color ?? '#F3F4F6');
                $entry->unsetRelation('subject');
                $entry->unsetRelation('teachingAssignment');
            });
    }

    /**
     * @return Collection<int, ScheduleEntry>
     */
    public function entriesForStudent(int $planId, int $periodId, int $rombelId): Collection
    {
        return ScheduleEntry::query()
            ->where('schedule_plan_id', $planId)
            ->where('academic_period_id', $periodId)
            ->where('rombel_id', $rombelId)
            ->orderByRaw("CASE day WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3 WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 ELSE 6 END")
            ->orderBy('lesson_number')
            ->get([
                'id',
                'day',
                'lesson_number',
                'start_time',
                'end_time',
                'subject_code',
                'subject_name',
                'teacher_name',
            ]);
    }

    /**
     * @return array<int, array{key: string, label: string, schedule: string}>
     */
    public function days(): array
    {
        $days = config('schedule.days');

        if (! is_array($days)) {
            return [];
        }

        $result = [];
        foreach ($days as $day) {
            if (! is_array($day)
                || ! is_string($day['key'] ?? null)
                || ! is_string($day['label'] ?? null)
                || ! is_string($day['schedule'] ?? null)) {
                continue;
            }

            $result[] = [
                'key' => $day['key'],
                'label' => $day['label'],
                'schedule' => $day['schedule'],
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array<int, array{number: int, start: string, end: string}>>
     */
    public function lessonSlotsByDay(): array
    {
        $result = [];
        foreach ($this->days() as $day) {
            $slots = [];
            foreach ($this->weekdayRows($day['schedule']) as $row) {
                if ($row['type'] !== 'lesson' || ! isset($row['number'])) {
                    continue;
                }

                $slots[] = [
                    'number' => $row['number'],
                    'start' => $row['start'],
                    'end' => $row['end'],
                ];
            }

            $result[$day['key']] = $slots;
        }

        return $result;
    }

    /**
     * @param  Collection<int, ScheduleCustomSlot>  $customSlots
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function scheduleRows(Collection $customSlots): array
    {
        $result = [];
        foreach ($this->days() as $day) {
            $daySlots = $customSlots->where('day', $day['key'])->keyBy('lesson_number');
            $rows = [];
            foreach ($this->weekdayRows($day['schedule']) as $row) {
                if ($row['type'] === 'lesson' && isset($row['number']) && $daySlots->has($row['number'])) {
                    $customSlot = $daySlots->get($row['number']);
                    $rows[] = [
                        ...$row,
                        'type' => 'custom',
                        'label' => $customSlot->label,
                    ];

                    continue;
                }

                $rows[] = $row;
            }

            $result[$day['key']] = $rows;
        }

        return $result;
    }

    /**
     * @return array<int, array{type: string, number?: int, start: string, end: string}>
     */
    private function weekdayRows(string $schedule): array
    {
        $rows = config('schedule.weekdays.'.$schedule);

        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_string($row['type'] ?? null)
                || ! is_string($row['start'] ?? null)
                || ! is_string($row['end'] ?? null)) {
                continue;
            }

            $number = $row['number'] ?? null;
            if ($number !== null && ! is_int($number)) {
                continue;
            }

            $normalized = [
                'type' => $row['type'],
                'start' => $row['start'],
                'end' => $row['end'],
            ];
            if ($number !== null) {
                $normalized['number'] = $number;
            }
            $result[] = $normalized;
        }

        return $result;
    }
}
