<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\TeacherQuiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherQuiz>
 */
class TeacherQuizFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'academic_year_id' => fn (): int => AcademicYear::query()->firstOrCreate(
                ['year' => '2026/2027'], ['status' => 'active'],
            )->id,
            'academic_period_id' => fn (array $attributes): int => AcademicPeriod::query()->firstOrCreate(
                ['academic_year_id' => $attributes['academic_year_id'], 'code' => 'S1'],
                ['name' => 'Semester 1', 'status' => 'active'],
            )->id,
            'subject_id' => Subject::factory(),
            'link' => 'https://forms.gle/'.fake()->lexify('????????'),
            'is_open' => false,
        ];
    }
}
