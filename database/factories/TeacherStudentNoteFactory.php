<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherStudentNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeacherStudentNote> */
class TeacherStudentNoteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'teacher_id' => Teacher::factory(),
            'teacher_name' => fake()->name(),
            'content' => fake()->sentence(),
        ];
    }
}
