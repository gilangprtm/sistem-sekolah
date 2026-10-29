<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\UasLink;

class StudentExamAccess
{
    public function activeGradeLevel(?Student $student): ?string
    {
        if ($student === null || $student->status !== 'active') {
            return null;
        }

        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();

        if ($year === null) {
            return null;
        }

        return StudentPlacement::query()
            ->join('m_rombels', 'm_rombels.id', '=', 'tr_curriculum_student_placements.rombel_id')
            ->where('tr_curriculum_student_placements.academic_year_id', $year->id)
            ->where('tr_curriculum_student_placements.student_id', $student->id)
            ->where('tr_curriculum_student_placements.status', 'active')
            ->where('m_rombels.status', 'active')
            ->value('m_rombels.grade_level');
    }

    public function canAccess(?Student $student, UasLink $uasLink): bool
    {
        $gradeLevel = $this->activeGradeLevel($student);

        return $gradeLevel !== null && $uasLink->grade_level === $gradeLevel;
    }
}
