<?php

namespace Database\Factories;

use App\Models\CounselingNote;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CounselingNote>
 */
class CounselingNoteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'counseling_date' => fake()->date(),
            'content' => fake()->paragraph(),
            'created_by' => User::factory(),
            'creator_name' => fake()->name(),
            'updated_by' => null,
            'updater_name' => null,
        ];
    }
}
