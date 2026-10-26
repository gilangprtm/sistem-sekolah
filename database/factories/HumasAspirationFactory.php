<?php

namespace Database\Factories;

use App\Models\HumasAspiration;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HumasAspiration> */
class HumasAspirationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'content' => fake()->paragraph(),
        ];
    }
}
