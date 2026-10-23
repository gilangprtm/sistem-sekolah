<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentViolation>
 */
class StudentViolationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'violation_date' => fake()->date(),
            'category' => 'Kedisiplinan',
            'content' => fake()->paragraph(),
            'follow_up' => null,
            'created_by' => User::factory(),
            'creator_name' => fake()->name(),
            'updated_by' => null,
            'updater_name' => null,
        ];
    }
}
