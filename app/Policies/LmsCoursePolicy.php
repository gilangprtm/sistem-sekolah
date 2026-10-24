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
            && $course->latestRevision?->status === LmsCourseRevision::STATUS_DRAFT
            && ! ($course->has_approved_revision ?? $course->revisions()->where('status', LmsCourseRevision::STATUS_APPROVED)->exists());
    }

    private function isActiveTeacher(User $user): bool
    {
        return $user->teacher?->status === 'active' && $user->teacher->staff_type === 'guru';
    }
}
