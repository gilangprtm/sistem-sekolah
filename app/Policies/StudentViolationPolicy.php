<?php

namespace App\Policies;

use App\Models\StudentViolation;
use App\Models\User;

class StudentViolationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'view');
    }

    public function view(User $user, StudentViolation $studentViolation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $this->allowed($user, 'create');
    }

    public function update(User $user, StudentViolation $studentViolation): bool
    {
        return $this->viewAny($user) && $this->allowed($user, 'update');
    }

    public function delete(User $user, StudentViolation $studentViolation): bool
    {
        return $this->viewAny($user) && $this->allowed($user, 'delete');
    }

    private function allowed(User $user, string $action): bool
    {
        return $user->hasRole('Super Admin', 'web')
            || ($user->hasRole('Kesiswaan', 'web') && $user->can('student_affairs.violation.'.$action));
    }
}
