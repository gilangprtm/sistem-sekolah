<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceKioskController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('attendance-kiosk/index');
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate(['qr' => ['required', 'string', 'email', 'max:255']]);
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();

        if ($year === null) {
            return response()->json(['message' => 'Kehadiran belum dapat dicatat.'], 503);
        }

        $student = Student::query()->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('email', $validated['qr']))
            ->whereIn('id', StudentPlacement::query()->select('student_id')
                ->where('academic_year_id', $year->id)->where('status', 'active'))
            ->first();

        if ($student === null) {
            return response()->json(['message' => 'Kehadiran tidak dapat dicatat.'], 404);
        }

        $date = now('Asia/Makassar')->toDateString();

        try {
            $created = DB::transaction(function () use ($student, $year, $date): bool {
                $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->first();
                $existing = StudentAttendance::query()->where('academic_year_id', $year->id)
                    ->where('student_id', $student->id)->whereDate('attendance_date', $date)->exists();

                if ($existing) {
                    return false;
                }

                StudentAttendance::query()->create([
                    'academic_year_id' => $year->id,
                    'student_id' => $lockedStudent->id,
                    'attendance_date' => $date,
                    'status' => 'H',
                    'version' => 1,
                    'updated_by' => null,
                ]);

                return true;
            });
        } catch (QueryException $exception) {
            $attendanceExists = StudentAttendance::query()->where('academic_year_id', $year->id)
                ->where('student_id', $student->id)->whereDate('attendance_date', $date)->exists();

            if (! $attendanceExists) {
                throw $exception;
            }

            return response()->json(['message' => 'Kehadiran hari ini sudah tercatat.'], 200);
        }

        return response()->json(['message' => $created ? 'Kehadiran berhasil dicatat.' : 'Kehadiran hari ini sudah tercatat.'], $created ? 201 : 200);
    }
}
