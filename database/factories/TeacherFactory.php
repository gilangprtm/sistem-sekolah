<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Teacher> */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['staff_type' => 'guru', 'full_name' => fake()->name(), 'status' => 'active'];
    }
}
