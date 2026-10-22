<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\TeacherAgenda;
use App\Services\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('teacher/documents/agenda-report', $this->reportData($request));
    }

    public function download(Request $request, XlsxWriter $writer): StreamedResponse
    {
        $report = $this->reportData($request);
        $rows = [
            ['Laporan Agenda Guru'],
            ['Guru', $report['teacherName']],
            ['Dari', $report['from'], 'Sampai', $report['to']],
            ['Tanggal', 'Tahun Ajaran', 'Semester', 'Kelas', 'Mata Pelajaran', 'Jam Pelajaran', 'Total JP', 'Materi', 'Keterangan'],
        ];

        foreach ($report['agendas'] as $agenda) {
            $snapshot = $agenda['snapshot'];
            $remarks = [];
            foreach (['H' => 'Hadir', 'I' => 'Ijin', 'S' => 'Sakit', 'D' => 'Dispensasi', 'A' => 'Alpa'] as $status => $label) {
                $count = $agenda['counts'][$status];
                if ($count > 0) {
                    $remarks[] = $count.' '.$label;
                }
            }
            $rows[] = [
                $agenda['date'], $snapshot['academic_year'], $snapshot['academic_period'],
                $snapshot['rombel_code'], $snapshot['subject_name'], $snapshot['start_time'].'–'.$snapshot['end_time'],
                (int) $snapshot['lesson_end'] - (int) $snapshot['lesson_start'] + 1, $agenda['material'], implode(', ', $remarks),
            ];
        }

        $content = $writer->write($rows, 'Agenda Guru', [
            'columnWidths' => [1 => 14.0, 2 => 15.0, 3 => 14.0, 4 => 12.5, 5 => 28.0, 6 => 19.0, 7 => 12.5, 8 => 60.0, 9 => 30.0],
            'headerImage' => public_path('images/kop-sekolah.png'),
            'titleRow' => 1, 'headerRow' => 4, 'borderFromRow' => 5,
            'wrapColumns' => [5, 8, 9], 'centerColumns' => [1, 2, 3, 4, 6, 7],
        ]);
        $filename = 'agenda-'.$report['from'].'-'.$report['to'].'.xlsx';

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{from: string, to: string, teacherName: string, agendas: list<array{id: int, date: string, snapshot: array<string, mixed>, material: string, counts: array<string, int>, unrecorded: int}>}
     */
    private function reportData(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) + 1 > 366, 422, 'Rentang cetak maksimal 366 hari.');
        $teacher = $request->user()->teacher;
        abort_if($teacher === null, 403, 'Akun belum terhubung dengan profil guru.');
        $agendas = TeacherAgenda::query()->with('academicPeriod:id,academic_year_id')
            ->where('teacher_id', $teacher->id)->whereBetween('agenda_date', [$data['from'], $data['to']])
            ->orderBy('agenda_date')->orderBy('id')->limit(1001)->get();
        abort_if($agendas->count() > 1000, 422, 'Terlalu banyak agenda. Persempit rentang tanggal.');
        $attendance = StudentAttendance::query()
            ->whereIn('academic_year_id', $agendas->map(fn (TeacherAgenda $agenda): int => $agenda->academicPeriod->academic_year_id)->unique())
            ->whereIn('student_id', $agendas->flatMap(fn (TeacherAgenda $agenda): array => array_column($agenda->attendance, 'student_id'))->unique())
            ->whereDate('attendance_date', '>=', $data['from'])->whereDate('attendance_date', '<=', $data['to'])->get()
            ->keyBy(fn (StudentAttendance $record): string => $record->academic_year_id.'|'.CarbonImmutable::parse($record->getRawOriginal('attendance_date'))->toDateString().'|'.$record->student_id);

        return [
            'from' => $data['from'], 'to' => $data['to'], 'teacherName' => $teacher->full_name,
            'agendas' => array_values($agendas->map(function (TeacherAgenda $agenda) use ($attendance): array {
                $counts = array_fill_keys(['H', 'I', 'S', 'D', 'A'], 0);
                $unrecorded = 0;
                foreach ($agenda->attendance as $student) {
                    $key = $agenda->academicPeriod->academic_year_id.'|'.$agenda->agenda_date->toDateString().'|'.$student['student_id'];
                    $record = $attendance->get($key);
                    if ($record === null) {
                        $unrecorded++;
                    } else {
                        $counts[$record->status]++;
                    }
                }

                return [
                    'id' => $agenda->id, 'date' => $agenda->agenda_date->toDateString(),
                    'snapshot' => $agenda->schedule_snapshot, 'material' => $agenda->material,
                    'counts' => $counts, 'unrecorded' => $unrecorded,
                ];
            })->all()),
        ];
    }
}
