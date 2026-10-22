<?php

namespace App\Console\Commands;

use App\Models\AcademicPeriod;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAgenda;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackfillStudentAttendance extends Command
{
    protected $signature = 'attendance:backfill-agendas {--apply : Tulis hasil setelah pemeriksaan konflik}';

    protected $description = 'Periksa/pindahkan status JSON Agenda lama tanpa menimpa konflik atau menghapus snapshot';

    public function handle(): int
    {
        $rows = [];
        $conflict = false;
        $years = AcademicPeriod::query()->pluck('academic_year_id', 'id');
        $actors = Teacher::query()->pluck('user_id', 'id');
        TeacherAgenda::query()->orderBy('id')->chunkById(200, function ($agendas) use (&$rows, &$conflict, $years, $actors): void {
            foreach ($agendas as $agenda) {
                foreach ($agenda->attendance as $student) {
                    if (! isset($student['status'])) {
                        continue;
                    }
                    $yearId = $years->get($agenda->academic_period_id);
                    $actorId = $actors->get($agenda->teacher_id);
                    $date = $agenda->agenda_date->toDateString();
                    $key = implode('|', [$yearId, $student['student_id'], $date]);
                    $realStudent = Student::query()->whereKey($student['student_id'])->exists();
                    if ($yearId === null || $actorId === null || ! $realStudent || ! in_array($student['status'], StudentAttendance::STATUSES, true)
                        || (isset($rows[$key]) && $rows[$key]['status'] !== $student['status'])) {
                        $this->error('Konflik/konteks legacy tidak valid pada agenda '.$agenda->id.'. Tidak ada data ditulis.');
                        $conflict = true;

                        continue;
                    }
                    $rows[$key] = [
                        'academic_year_id' => $yearId, 'student_id' => $student['student_id'],
                        'attendance_date' => $date, 'status' => $student['status'], 'version' => 1, 'updated_by' => $actorId,
                    ];
                }
            }
        });
        if ($conflict) {
            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($rows): void {
                $ids = array_unique(array_column($rows, 'student_id'));
                sort($ids);
                Student::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                foreach ($rows as $row) {
                    $existing = StudentAttendance::query()->where('academic_year_id', $row['academic_year_id'])
                        ->where('student_id', $row['student_id'])->whereDate('attendance_date', $row['attendance_date'])->first();
                    if ($existing !== null && $existing->status !== $row['status']) {
                        throw new RuntimeException('Konflik dengan data kehadiran harian. Tidak ada data ditulis.');
                    }
                    if ($existing !== null) {
                        continue;
                    }
                    if ($this->option('apply')) {
                        StudentAttendance::query()->create($row);
                    }
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info(count($rows).' kehadiran unik valid. '.($this->option('apply') ? 'Backfill selesai; JSON lama dipertahankan.' : 'Mode pemeriksaan: tidak ada data ditulis.'));

        return self::SUCCESS;
    }
}
