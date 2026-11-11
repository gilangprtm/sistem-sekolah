<?php

namespace App\Services;

use App\Models\Student;
use App\Models\TeacherQuiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class StudentQuizAccess
{
    /** @return Builder<TeacherQuiz> */
    public function availableToStudent(Student $student): Builder
    {
        $query = TeacherQuiz::query()->where('is_open', true);
        if ($student->status !== 'active') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(fn (QueryBuilder $placements) => $placements->selectRaw('1')
            ->from('tr_curriculum_student_placements as placement')
            ->join('m_rombels as rombel', 'rombel.id', '=', 'placement.rombel_id')
            ->whereColumn('placement.academic_year_id', 'tr_teacher_quizzes.academic_year_id')
            ->where('placement.student_id', $student->id)
            ->where('placement.status', 'active')
            ->whereExists(fn (QueryBuilder $assignments) => $assignments->selectRaw('1')
                ->from('tr_curriculum_teaching_assignments as assignment')
                ->join('m_teacher as teacher', 'teacher.id', '=', 'assignment.teacher_id')
                ->whereColumn('assignment.academic_period_id', 'tr_teacher_quizzes.academic_period_id')
                ->whereColumn('assignment.subject_id', 'tr_teacher_quizzes.subject_id')
                ->whereColumn('assignment.rombel_id', 'placement.rombel_id')
                ->where('assignment.status', 'active')
                ->where('teacher.staff_type', 'guru')->where('teacher.status', 'active')
                ->whereColumn('teacher.user_id', 'tr_teacher_quizzes.created_by')
                ->whereExists(fn (QueryBuilder $plans) => $plans->selectRaw('1')
                    ->from('tr_curriculum_schedule_plans as plan')
                    ->whereColumn('plan.id', 'assignment.schedule_plan_id')
                    ->whereColumn('plan.academic_period_id', 'tr_teacher_quizzes.academic_period_id')
                    ->where('plan.status', 'published'))
                ->whereExists(fn (QueryBuilder $entries) => $entries->selectRaw('1')
                    ->from('tr_curriculum_schedule_entries as entry')
                    ->whereColumn('entry.teaching_assignment_id', 'assignment.id')
                    ->whereColumn('entry.schedule_plan_id', 'assignment.schedule_plan_id')
                    ->whereColumn('entry.academic_period_id', 'assignment.academic_period_id')
                    ->whereColumn('entry.teacher_id', 'assignment.teacher_id')
                    ->whereColumn('entry.subject_id', 'assignment.subject_id')
                    ->whereColumn('entry.rombel_id', 'assignment.rombel_id'))));
    }

    public function allows(Student $student, TeacherQuiz $quiz): bool
    {
        return $this->availableToStudent($student)->whereKey($quiz->getKey())->exists();
    }
}
