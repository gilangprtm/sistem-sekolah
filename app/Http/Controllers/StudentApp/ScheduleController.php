<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\StudentPlacement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;
        $status = 'ready';
        $message = null;
        $academicYear = null;
        $academicPeriod = null;
        $placement = null;
        $entries = collect();
        $customSlots = collect();

        if ($student === null) {
            $status = 'student_unlinked';
            $message = 'Akun ini belum terhubung dengan data siswa.';
        } else {
            $academicYear = AcademicYear::query()
                ->where('status', 'active')
                ->latest('id')
                ->first();

            if ($academicYear === null) {
                $status = 'no_active_year';
                $message = 'Tahun ajaran aktif belum tersedia.';
            } else {
                $academicPeriod = AcademicPeriod::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();

                if ($academicPeriod === null) {
                    $status = 'no_active_period';
                    $message = 'Semester aktif untuk tahun ajaran ini belum tersedia.';
                } else {
                    $placement = StudentPlacement::query()
                        ->with('rombel:id,code,name,grade_level')
                        ->where('academic_year_id', $academicYear->id)
                        ->where('student_id', $student->id)
                        ->where('status', 'active')
                        ->latest('id')
                        ->first();

                    if ($placement === null || $placement->rombel === null) {
                        $status = 'no_active_class';
                        $message = 'Penempatan kelas aktif belum tersedia.';
                    } else {
                        $schedulePlan = SchedulePlan::query()
                            ->with('customSlots')
                            ->where('academic_period_id', $academicPeriod->id)
                            ->where('status', 'published')
                            ->whereHas('entries', fn ($query) => $query->where('rombel_id', $placement->rombel_id))
                            ->latest('id')
                            ->first();

                        if ($schedulePlan === null) {
                            $status = 'no_schedule';
                            $message = 'Jadwal untuk kelas ini belum tersedia.';
                        } else {
                            $customSlots = $schedulePlan->customSlots;
                            $entries = ScheduleEntry::query()
                                ->where('schedule_plan_id', $schedulePlan->id)
                                ->where('academic_period_id', $academicPeriod->id)
                                ->where('rombel_id', $placement->rombel_id)
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

                            $entries = $entries->unique(fn (ScheduleEntry $entry): string => implode('|', [
                                $entry->day,
                                $entry->lesson_number,
                                $entry->subject_code,
                                $entry->subject_name,
                                $entry->teacher_name,
                            ]))->values();

                            if ($entries->isEmpty()) {
                                $status = 'no_schedule';
                                $message = 'Jadwal untuk kelas ini belum tersedia.';
                            }
                        }
                    }
                }
            }
        }

        return Inertia::render('student-app/schedule', [
            'status' => $status,
            'message' => $message,
            'academicYear' => $academicYear?->only(['year']),
            'academicPeriod' => $academicPeriod?->only(['code', 'name']),
            'rombel' => $placement?->rombel?->only(['code', 'name', 'grade_level']),
            'days' => $this->groupEntriesByDay($entries, $customSlots),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ScheduleEntry>  $entries
     * @param  \Illuminate\Support\Collection<int, \App\Models\ScheduleCustomSlot>  $customSlots
     * @return array<int, array{key: string, label: string, rows: array<int, array<string, mixed>>, entries: array<int, array<string, mixed>>}>
     */
    private function groupEntriesByDay(\Illuminate\Support\Collection $entries, \Illuminate\Support\Collection $customSlots): array
    {
        $labels = [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
        ];

        return collect($labels)->map(function (string $label, string $key) use ($entries, $customSlots): array {
            $configuredRows = config('schedule.weekdays.'.($key === 'friday' ? 'friday' : 'monday_thursday'));
            $dayCustomSlots = $customSlots->where('day', $key)->keyBy('lesson_number');
            $rows = collect($configuredRows)->map(function (array $row) use ($dayCustomSlots): array {
                if ($row['type'] === 'lesson' && $dayCustomSlots->has($row['number'])) {
                    $customSlot = $dayCustomSlots->get($row['number']);

                    return [...$row, 'type' => 'custom', 'label' => $customSlot->label];
                }

                return $row;
            })->values()->all();

            return [
                'key' => $key,
                'label' => $label,
                'rows' => $rows,
                'entries' => $entries
                    ->where('day', $key)
                    ->unique(fn (ScheduleEntry $entry): string => implode('|', [
                        $entry->lesson_number,
                        $entry->subject_code,
                        $entry->subject_name,
                        $entry->teacher_name,
                    ]))
                    ->map(fn (ScheduleEntry $entry): array => [
                        'id' => $entry->id,
                        'lessonNumber' => $entry->lesson_number,
                        'startTime' => $entry->start_time,
                        'endTime' => $entry->end_time,
                        'subjectCode' => $entry->subject_code,
                        'subjectName' => $entry->subject_name,
                        'teacherName' => $entry->teacher_name,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }
}
