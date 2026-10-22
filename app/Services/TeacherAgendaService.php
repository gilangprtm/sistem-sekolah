<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\ScheduleEntry;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class TeacherAgendaService
{
    public function activePeriod(): ?AcademicPeriod
    {
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();

        return $year === null ? null : AcademicPeriod::query()
            ->with('academicYear')
            ->where('academic_year_id', $year->id)
            ->where('status', 'active')->latest('id')->first();
    }

    /** @return list<array{block_key: string, snapshot: array<string, mixed>}> */
    public function blocks(Teacher $teacher, AcademicPeriod $period, string $date): array
    {
        $day = strtolower(CarbonImmutable::parse($date, 'Asia/Makassar')->format('l'));
        $entries = ScheduleEntry::query()
            ->where('teacher_id', $teacher->id)
            ->where('academic_period_id', $period->id)
            ->where('day', $day)
            ->whereHas('schedulePlan', fn ($query) => $query->where('status', 'published'))
            ->orderBy('lesson_number')->orderBy('id')->get();
        $groups = [];
        foreach ($entries->groupBy(fn (ScheduleEntry $entry): string => implode('|', [
            $entry->schedule_plan_id, $entry->rombel_id, $entry->subject_id,
        ])) as $assignmentEntries) {
            $previous = null;
            foreach ($assignmentEntries->sortBy('lesson_number') as $entry) {
                $continues = $previous !== null
                    && $entry->lesson_number === $previous->lesson_number + 1
                    && $entry->start_time === $previous->end_time;
                if (! $continues) {
                    $groups[] = [$entry];
                } else {
                    $groups[array_key_last($groups)][] = $entry;
                }
                $previous = $entry;
            }
        }

        usort($groups, fn (array $left, array $right): int => [
            $left[0]->start_time, $left[0]->lesson_number, $left[0]->rombel_code,
        ] <=> [
            $right[0]->start_time, $right[0]->lesson_number, $right[0]->rombel_code,
        ]);

        return array_map(function (array $group) use ($period): array {
            $first = $group[0];
            $last = $group[array_key_last($group)];
            $snapshot = [
                'academic_year' => $period->academicYear->year,
                'academic_period' => $period->name,
                'rombel_id' => $first->rombel_id,
                'rombel_code' => $first->rombel_code,
                'subject_id' => $first->subject_id,
                'subject_code' => $first->subject_code,
                'subject_name' => $first->subject_name,
                'teacher_name' => $first->teacher_name,
                'day' => $first->day,
                'lesson_start' => $first->lesson_number,
                'lesson_end' => $last->lesson_number,
                'start_time' => $first->start_time,
                'end_time' => $last->end_time,
            ];
            $key = hash('sha256', implode('|', [
                $period->id, $first->rombel_id, $first->subject_id,
                $first->lesson_number, $last->lesson_number, $first->start_time, $last->end_time,
            ]));

            return ['block_key' => $key, 'snapshot' => $snapshot];
        }, $groups);
    }

    /** @return array{block_key: string, snapshot: array<string, mixed>, students: array<int, array<string, mixed>>, context_token: string} */
    public function context(Teacher $teacher, AcademicPeriod $period, string $date, string $key): array
    {
        $block = collect($this->blocks($teacher, $period, $date))->firstWhere('block_key', $key);
        if ($block === null) {
            throw ValidationException::withMessages(['block_key' => 'Blok jadwal sudah berubah atau tidak tersedia untuk Anda. Muat ulang agenda.']);
        }
        $students = Student::query()
            ->where('status', 'active')
            ->whereIn('id', StudentPlacement::query()->select('student_id')
                ->where('academic_year_id', $period->academic_year_id)
                ->where('rombel_id', $block['snapshot']['rombel_id'])
                ->where('status', 'active'))
            ->orderBy('full_name')->orderBy('id')->get(['id', 'full_name', 'nis'])
            ->map(fn (Student $student): array => [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
            ])->all();

        return [
            ...$block,
            'students' => $students,
            'context_token' => hash('sha256', json_encode([$date, $block, $students], JSON_THROW_ON_ERROR)),
        ];
    }
}
