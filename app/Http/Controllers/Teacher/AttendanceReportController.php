<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use App\Services\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function __invoke(Request $request, XlsxWriter $writer): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('Guru'), 403);
        $teacher = $user->teacher;
        abort_if($teacher === null, 403, 'Akun belum terhubung dengan profil guru.');
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->firstOrFail();
        $assignment = HomeroomAssignment::query()->with('rombel:id,code')
            ->where('academic_year_id', $year->id)->where('teacher_id', $teacher->id)
            ->where('status', 'active')->firstOrFail();

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        abort_if(CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) + 1 > 366,
            422, 'Rentang unduh maksimal 366 hari.');

        $students = Student::query()->where('status', 'active')
            ->whereIn('id', StudentPlacement::query()->select('student_id')->where('academic_year_id', $year->id)
                ->where('rombel_id', $assignment->rombel_id)->where('status', 'active'))
            ->orderBy('full_name')->orderBy('id')->limit(1001)->get(['id', 'full_name']);
        abort_if($students->count() > 1000, 422, 'Terlalu banyak siswa dalam kelas. Maksimal 1.000 siswa.');
        $totals = StudentAttendance::query()->select(['student_id', 'status'])->selectRaw('COUNT(*) as total')
            ->where('academic_year_id', $year->id)->whereIn('student_id', $students->modelKeys())
            ->whereDate('attendance_date', '>=', $data['from'])->whereDate('attendance_date', '<=', $data['to'])
            ->groupBy('student_id', 'status')->get()->groupBy('student_id');

        $rows = [
            ['Laporan Kehadiran Siswa'],
            ['Wali Kelas', $teacher->full_name],
            ['Kelas', $assignment->rombel->code.' · '.$year->year],
            ['Rentang', $data['from'].' s.d. '.$data['to']],
            ['Nomor', 'Nama Siswa', 'Keterangan'],
        ];
        foreach ($students as $index => $student) {
            $counts = $totals->get($student->id)?->keyBy('status');
            $remarks = [];
            foreach (['I' => 'Ijin', 'S' => 'Sakit', 'D' => 'Dispensasi', 'A' => 'Alpa'] as $status => $label) {
                $count = (int) ($counts?->get($status)?->getAttribute('total') ?? 0);
                if ($count > 0) {
                    $remarks[] = $count.' '.$label;
                }
            }
            $rows[] = [$index + 1, $student->full_name, implode(', ', $remarks)];
        }

        $content = $writer->write($rows, 'Kehadiran Siswa', [
            'columnWidths' => [1 => 12.5, 2 => 52.0, 3 => 40.0],
            'headerImage' => public_path('images/kop-sekolah.png'),
            'titleRow' => 1, 'headerRow' => 5, 'borderFromRow' => 6,
            'wrapColumns' => [2, 3], 'centerColumns' => [1],
        ]);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'kehadiran-siswa-'.$data['from'].'-'.$data['to'].'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
