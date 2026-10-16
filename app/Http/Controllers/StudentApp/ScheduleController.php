<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\ScheduleCustomSlot;
use App\Models\ScheduleEntry;
use App\Models\StudentPlacement;
use App\Services\ScheduleReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                        $readService = app(ScheduleReadService::class);
                        $schedulePlan = $readService->publishedPlanForRombel(
                            $academicPeriod->id,
                            $placement->rombel_id,
                        );

                        if ($schedulePlan === null) {
                            $status = 'no_schedule';
                            $message = 'Jadwal untuk kelas ini belum tersedia.';
                        } else {
                            $customSlots = $schedulePlan->customSlots;
                            $entries = $readService->entriesForStudent(
                                $schedulePlan->id,
                                $academicPeriod->id,
                                $placement->rombel_id,
                            );

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
     * @param  Collection<int, ScheduleEntry>  $entries
     * @param  Collection<int, ScheduleCustomSlot>  $customSlots
     * @return array<int, array{key: string, label: string, rows: array<int, array<string, mixed>>, entries: array<int, array<string, mixed>>}>
     */
    private function groupEntriesByDay(Collection $entries, Collection $customSlots): array
    {
        $rowsByDay = app(ScheduleReadService::class)->scheduleRows($customSlots);
        $days = config('schedule.days');

        return collect(app(ScheduleReadService::class)->days())->map(function (array $day) use ($entries, $rowsByDay): array {
            $key = $day['key'];

            return [
                'key' => $key,
                'label' => $day['label'],
                'rows' => $rowsByDay[$key],
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
