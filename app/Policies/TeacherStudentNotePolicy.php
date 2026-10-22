<?php

namespace App\Policies;

use App\Models\TeacherStudentNote;
use App\Models\User;

class TeacherStudentNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('teacher.student-note.view') && $this->isTeacher($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->can('teacher.student-note.create');
    }

    public function update(User $user, TeacherStudentNote $note): bool
    {
        return false;
    }

    public function delete(User $user, TeacherStudentNote $note): bool
    {
        return false;
    }

    private function isTeacher(User $user): bool
    {
        $teacher = $user->teacher;

        return $teacher !== null && $teacher->status === 'active' && $teacher->staff_type === 'guru';
    }
}
