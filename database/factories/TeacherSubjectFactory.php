<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeacherSubject> */
class TeacherSubjectFactory extends Factory
{
    protected $model = TeacherSubject::class;

    public function definition(): array
    {
        $subjectCode = strtoupper(fake()->unique()->lexify('???'));

        return [
            'teacher_id' => Teacher::factory(),
            'subject_id' => Subject::factory(['code' => $subjectCode]),
            'suffix' => '01',
            'code' => $subjectCode.'01',
        ];
    }
}
