<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Student;
use App\Models\StudentPlacement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeroomController extends Controller
{
    public function __invoke(Request $request): Response
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

        if ($assignment !== null) {
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
            'filters' => $request->only(['per_page']),
            'hasTeacherProfile' => $teacher !== null,
        ]);
    }
}
