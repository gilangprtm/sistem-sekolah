<?php

namespace Database\Factories;

use App\Models\Rombel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rombel> */
class RombelFactory extends Factory
{
    protected $model = Rombel::class;

    public function definition(): array
    {
        $gradeLevel = fake()->randomElement(['VII', 'VIII', 'IX']);
        $parallelCode = fake()->unique()->regexify('[A-Z][0-9]?');

        return [
            'code' => $gradeLevel.'-'.$parallelCode,
            'name' => 'Kelas '.$gradeLevel.' '.$parallelCode,
            'grade_level' => $gradeLevel,
            'parallel_code' => $parallelCode,
            'status' => 'active',
        ];
    }
}
