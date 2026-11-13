<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Student;
use App\Models\StudentPlacement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HomeroomStudentContactController extends Controller
{
    public function update(Request $request, Student $student): RedirectResponse
    {
        abort_if($request->user()->email_verified_at === null, 403);

        $validated = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'parent_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        abort_if($validated === [], 422);

        DB::transaction(function () use ($request, $student, $validated): void {
            $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
            $teacher = $request->user()->teacher()->first();

            abort_if($year === null || $teacher === null || $teacher->status !== 'active' || $teacher->staff_type !== 'guru', 404);

            $assignment = HomeroomAssignment::query()
                ->where('academic_year_id', $year->id)
                ->where('teacher_id', $teacher->id)
                ->where('status', 'active')
                ->first();

            abort_if($assignment === null, 404);

            $placement = StudentPlacement::query()
                ->where('academic_year_id', $year->id)
                ->where('rombel_id', $assignment->rombel_id)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            abort_if($placement === null || $student->status !== 'active', 404);

            $student->fill($validated)->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kontak siswa berhasil diperbarui.']);

        return back(fallback: route('teacher.homeroom'));
    }
}
