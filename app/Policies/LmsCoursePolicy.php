<?php

namespace App\Policies;

use App\Models\LmsCourse;
use App\Models\LmsCourseRevision;
use App\Models\User;

class LmsCoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manageDesigns($user)
            || ($user->can('lms.course.design') && $this->isActiveTeacher($user));
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewStudentCourses(User $user): bool
    {
        return $user->hasRole('Siswa') && $user->student?->status === 'active';
    }

    public function browseMyCourses(User $user): bool
    {
        return $user->hasRole('Super Admin')
            || ($user->can('lms.course.view') && $this->isActiveTeacher($user));
    }

    public function manageDesigns(User $user): bool
    {
        return $user->hasRole('Super Admin')
            || ($user->hasRole('Kurikulum') && $user->can('lms.course.design'));
    }

    public function view(User $user, LmsCourse $course): bool
    {
        return $this->viewAny($user)
            && ($this->manageDesigns($user) || $user->teacher?->id === $course->created_by)
            && $course->archived_at === null;
    }

    public function update(User $user, LmsCourse $course): bool
    {
        return $this->view($user, $course)
            && $course->latestRevision?->isDraft() === true;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('Super Admin')
            || ($user->hasRole('Kurikulum') && ($user->can('lms.course.design') || $user->can('lms.course.archive')));
    }

    public function inspect(User $user, LmsCourse $course): bool
    {
        return $this->manage($user);
    }

    public function revise(User $user, LmsCourse $course): bool
    {
        return $this->view($user, $course)
            && $course->latestRevision?->status === LmsCourseRevision::STATUS_APPROVED;
    }

    public function activate(User $user, LmsCourse $course): bool
    {
        return $this->manage($user)
            && ($user->hasRole('Super Admin') || $user->can('lms.course.design'))
            && $course->archived_at === null
            && $course->latestRevision?->isDraft() === true;
    }

    public function archive(User $user, LmsCourse $course): bool
    {
        return $this->manage($user)
            && ($user->hasRole('Super Admin') || $user->can('lms.course.archive'))
            && $course->archived_at === null;
    }

    public function restore(User $user, LmsCourse $course): bool
    {
        return $this->manage($user)
            && ($user->hasRole('Super Admin') || $user->can('lms.course.archive'))
            && $course->archived_at !== null;
    }

    private function isActiveTeacher(User $user): bool
    {
        return $user->teacher?->status === 'active' && $user->teacher->staff_type === 'guru';
    }
}
