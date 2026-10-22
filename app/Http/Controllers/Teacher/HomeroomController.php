<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomSeat;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Services\StudentAttendanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeroomController extends Controller
{
    public function __invoke(Request $request, StudentAttendanceService $attendanceService): Response
    {
        $academicYear = AcademicYear::query()
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $teacher = $request->user()->teacher;
        $assignment = null;

        if ($academicYear !== null && $teacher !== null) {
            $assignment = HomeroomAssignment::query()
                ->with('rombel:id,code,name,grade_level,parallel_code')
                ->where('academic_year_id', $academicYear->id)
                ->where('teacher_id', $teacher->id)
                ->where('status', 'active')
                ->first();
        }

        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $students = null;
        $attendanceStudents = null;
        $seating = null;

        if ($assignment !== null) {
            $seating = HomeroomSeat::layout($academicYear->id, $assignment->rombel_id);

            $attendanceContext = $attendanceService->context($request->user(), [
                'source' => 'homeroom',
                'date' => now('Asia/Makassar')->toDateString(),
            ]);
            $attendanceStudents = $attendanceService->rows(
                $attendanceContext['year_id'],
                $attendanceContext['date'],
                $attendanceContext['roster'],
            );

            $students = StudentPlacement::query()
                ->with('student:id,nis,nisn,tahun_angkatan,full_name,gender,birth_place,birth_date,address,phone,mother_name,father_name,mother_occupation,father_occupation,parent_phone')
                ->where('academic_year_id', $academicYear->id)
                ->where('rombel_id', $assignment->rombel_id)
                ->where('status', 'active')
                ->orderBy(
                    Student::query()
                        ->select('full_name')
                        ->whereColumn('m_students.id', 'tr_curriculum_student_placements.student_id'),
                )
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (StudentPlacement $placement): array => [
                    'id' => $placement->student_id,
                    ...$placement->student?->only([
                        'nis',
                        'nisn',
                        'tahun_angkatan',
                        'full_name',
                        'gender',
                        'birth_place',
                        'birth_date',
                        'address',
                        'phone',
                        'mother_name',
                        'father_name',
                        'mother_occupation',
                        'father_occupation',
                        'parent_phone',
                    ]) ?? [],
                ]);
        }

        return Inertia::render('teacher/homeroom', [
            'academicYear' => $academicYear?->only(['id', 'year']),
            'homeroom' => $assignment?->rombel?->only(['id', 'code', 'name', 'grade_level', 'parallel_code']),
            'students' => $students,
            'attendanceStudents' => $attendanceStudents,
            'attendanceDate' => now('Asia/Makassar')->toDateString(),
            'canManageAttendance' => $request->user()->can('teacher.attendance.manage'),
            'seating' => $seating,
            'canManageSeating' => $request->user()->can('teacher.homeroom.seating.manage'),
            'filters' => $request->only(['per_page']),
            'hasTeacherProfile' => $teacher !== null,
        ]);
    }
}
