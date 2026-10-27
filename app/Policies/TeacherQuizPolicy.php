<?php

namespace App\Policies;

use App\Models\TeacherQuiz;
use App\Models\User;

class TeacherQuizPolicy
{
    public function manage(User $user, TeacherQuiz $quiz): bool
    {
        return $user->can('teacher.quiz.view') && $quiz->created_by === $user->id;
    }
}
