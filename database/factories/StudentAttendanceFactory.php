<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAttendance>
 */
class StudentAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => function (): int {
                $startYear = fake()->unique()->numberBetween(1900, 2500);

                return AcademicYear::query()->create([
                    'year' => $startYear.'/'.($startYear + 1),
                    'status' => 'inactive',
                ])->id;
            },
            'student_id' => Student::factory(),
            'attendance_date' => fake()->date(),
            'status' => fake()->randomElement(StudentAttendance::STATUSES),
            'version' => 1,
            'updated_by' => User::factory(),
        ];
    }
}
