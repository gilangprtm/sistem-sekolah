<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use App\Models\TeacherAgenda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentAttendanceService
{
    public function __construct(private TeacherAgendaService $agendas) {}

    /** @param array<string, mixed> $input
     * @return array{year_id: int, date: string, roster: array<int, array<string, mixed>>}
     */
    public function context(User $user, array $input): array
    {
        $teacher = $user->teacher;
        abort_if($teacher === null, 403, 'Akun belum terhubung dengan profil guru.');
        if ($input['source'] === 'homeroom') {
            abort_unless($user->can('teacher.homeroom.view'), 403);
            $year = AcademicYear::query()->where('status', 'active')->latest('id')->firstOrFail();
            $assignment = HomeroomAssignment::query()->where('academic_year_id', $year->id)
                ->where('teacher_id', $teacher->id)->where('status', 'active')->firstOrFail();
            $date = now('Asia/Makassar')->toDateString();
            if ($input['date'] !== $date) {
                throw ValidationException::withMessages(['date' => 'Tanggal WITA telah berubah. Muat ulang absensi hari ini.']);
            }
            $roster = Student::query()->where('status', 'active')
                ->whereIn('id', StudentPlacement::query()->select('student_id')->where('academic_year_id', $year->id)
                    ->where('rombel_id', $assignment->rombel_id)->where('status', 'active'))
                ->orderBy('full_name')->orderBy('id')->get(['id', 'full_name', 'nis', 'phone', 'parent_phone'])
                ->map(fn (Student $student): array => ['student_id' => $student->id, ...$student->only(['full_name', 'nis', 'phone', 'parent_phone'])])->all();

            return ['year_id' => $year->id, 'date' => $date, 'roster' => $roster];
        }
        abort_unless($user->can('teacher.agenda.manage'), 403);
        if (! empty($input['agenda_id'])) {
            $agenda = TeacherAgenda::query()->where('teacher_id', $teacher->id)->findOrFail($input['agenda_id']);
            if ($agenda instanceof TeacherAgenda) {
                abort_unless($input['date'] === $agenda->agenda_date->toDateString(), 404);
                $yearId = AcademicPeriod::query()->findOrFail($agenda->academic_period_id)->academic_year_id;

                return ['year_id' => $yearId, 'date' => $input['date'], 'roster' => $agenda->attendance];
            }

            abort(404);
        }
        $period = $this->agendas->activePeriod();
        abort_if($period === null, 422, 'Semester aktif belum tersedia.');
        $context = $this->agendas->context($teacher, $period, $input['date'], $input['block_key']);

        return ['year_id' => $period->academic_year_id, 'date' => $input['date'], 'roster' => $context['students']];
    }

    /** @param array<int, array<string, mixed>> $roster
     * @return array<int, array<string, mixed>>
     */
    public function rows(int $yearId, string $date, array $roster): array
    {
        $saved = StudentAttendance::query()->where('academic_year_id', $yearId)->whereDate('attendance_date', $date)
            ->whereIn('student_id', array_column($roster, 'student_id'))->get()->keyBy('student_id');

        foreach ($roster as $student) {
            abort_if(isset($student['status']) && $saved->get($student['student_id']) === null, 409,
                'Absensi Agenda lama belum dipindahkan. Jalankan pemeriksaan/backfill sebelum menggunakan absensi harian.');
        }

        return array_map(fn (array $student): array => [
            ...$student,
            'status' => $saved->has($student['student_id']) ? $saved->get($student['student_id'])->status : ($student['status'] ?? 'A'),
            'version' => $saved->has($student['student_id']) ? $saved->get($student['student_id'])->version : 0,
        ], $roster);
    }

    /** @param array<string, mixed> $input */
    public function save(User $user, array $input): void
    {
        abort_unless($user->can('teacher.attendance.manage'), 403);
        DB::transaction(function () use ($user, $input): void {
            $context = $this->context($user, $input);
            $ids = array_column($input['attendance'], 'student_id');
            $allowed = array_column($context['roster'], 'student_id');
            if (array_diff($ids, $allowed) !== []) {
                throw ValidationException::withMessages(['attendance' => 'Siswa tidak termasuk dalam daftar kelas ini. Muat ulang absensi.']);
            }
            sort($ids);
            Student::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $current = StudentAttendance::query()->where('academic_year_id', $context['year_id'])
                ->whereDate('attendance_date', $context['date'])->whereIn('student_id', $ids)->lockForUpdate()->get()->keyBy('student_id');
            foreach ($input['attendance'] as $entry) {
                $record = $current->get($entry['student_id']);
                $version = $record instanceof StudentAttendance ? $record->version : 0;
                if ((int) $entry['version'] !== $version) {
                    throw ValidationException::withMessages(['attendance' => 'Absensi sudah diubah guru lain. Muat ulang sebelum menyimpan kembali.']);
                }
            }
            foreach ($input['attendance'] as $entry) {
                $values = ['status' => $entry['status'], 'version' => $entry['version'] + 1, 'updated_by' => $user->id];
                $record = $current->get($entry['student_id']);
                if ($record instanceof StudentAttendance) {
                    $record->update($values);
                } else {
                    StudentAttendance::query()->create([
                        'academic_year_id' => $context['year_id'], 'student_id' => $entry['student_id'], 'attendance_date' => $context['date'],
                        ...$values,
                    ]);
                }
            }
        });
    }
}
