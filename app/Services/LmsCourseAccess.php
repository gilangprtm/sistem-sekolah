<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\LmsCourse;
use App\Models\LmsCourseRevision;
use App\Models\ScheduleTeachingAssignment;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class LmsCourseAccess
{
    public function __construct(private TeacherAgendaService $agenda) {}

    public function activePeriod(): ?AcademicPeriod
    {
        return $this->agenda->activePeriod();
    }

    /** @return Builder<ScheduleTeachingAssignment> */
    public function assignments(AcademicPeriod $period): Builder
    {
        return ScheduleTeachingAssignment::query()
            ->where('tr_curriculum_teaching_assignments.academic_period_id', $period->id)
            ->where('tr_curriculum_teaching_assignments.status', 'active')
            ->whereHas('schedulePlan', fn (Builder $plan) => $plan
                ->where('academic_period_id', $period->id)->where('status', 'published'))
            ->whereExists(fn (QueryBuilder $entries) => $entries->selectRaw('1')
                ->from('tr_curriculum_schedule_entries as entry')
                ->whereColumn('entry.teaching_assignment_id', 'tr_curriculum_teaching_assignments.id')
                ->whereColumn('entry.schedule_plan_id', 'tr_curriculum_teaching_assignments.schedule_plan_id')
                ->whereColumn('entry.academic_period_id', 'tr_curriculum_teaching_assignments.academic_period_id')
                ->whereColumn('entry.teacher_id', 'tr_curriculum_teaching_assignments.teacher_id')
                ->whereColumn('entry.subject_id', 'tr_curriculum_teaching_assignments.subject_id')
                ->whereColumn('entry.rombel_id', 'tr_curriculum_teaching_assignments.rombel_id'));
    }

    public function studentPlacement(User $user, AcademicPeriod $period): ?StudentPlacement
    {
        if (! $user->hasRole('Siswa') || $user->student?->status !== 'active') {
            return null;
        }

        return StudentPlacement::query()->with('rombel')
            ->where('academic_year_id', $period->academic_year_id)
            ->where('student_id', $user->student->id)->where('status', 'active')
            ->latest('id')->first();
    }

    /** @return Builder<LmsCourse> */
    public function availableToStudent(User $user): Builder
    {
        $query = LmsCourse::query()->whereNull('archived_at')
            ->whereHas('approvedRevision', fn (Builder $revision) => $revision->where('status', LmsCourseRevision::STATUS_APPROVED));
        $period = $this->activePeriod();
        $placement = $period === null ? null : $this->studentPlacement($user, $period);
        if ($placement?->rombel === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('grade_level', $placement->rombel->grade_level)
            ->whereIn('subject_id', $this->assignments($period)->where('rombel_id', $placement->rombel_id)->select('subject_id'));
    }

    /** @return Builder<LmsCourse> */
    public function available(User $user): Builder
    {
        $query = LmsCourse::query()->whereNull('archived_at')
            ->whereHas('approvedRevision', fn (Builder $revision) => $revision
                ->where('status', LmsCourseRevision::STATUS_APPROVED));

        if (! $user->can('browseMyCourses', LmsCourse::class)) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->hasRole('Super Admin')) {
            return $query;
        }
        $period = $this->activePeriod();
        if ($period === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists($this->assignments($period)
            ->join('m_rombels as rombel', 'rombel.id', '=', 'tr_curriculum_teaching_assignments.rombel_id')
            ->where('tr_curriculum_teaching_assignments.teacher_id', $user->teacher->id)
            ->whereColumn('tr_curriculum_teaching_assignments.subject_id', 'm_lms_courses.subject_id')
            ->whereColumn('rombel.grade_level', 'm_lms_courses.grade_level')->selectRaw('1')->toBase());
    }
}
