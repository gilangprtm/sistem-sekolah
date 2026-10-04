<?php

namespace Database\Seeders;

use App\Models\Rombel;
use Illuminate\Database\Seeder;

class RombelSeeder extends Seeder
{
    private const GRADE_LEVELS = ['VII', 'VIII', 'IX'];

    private const PARALLEL_CODES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    public function run(): void
    {
        foreach (self::GRADE_LEVELS as $gradeLevel) {
            foreach (self::PARALLEL_CODES as $parallelCode) {
                $code = $gradeLevel.'-'.$parallelCode;

                Rombel::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => 'Kelas '.$gradeLevel.' '.$parallelCode,
                        'grade_level' => $gradeLevel,
                        'parallel_code' => $parallelCode,
                        'status' => 'active',
                    ],
                );
            }
        }
    }
}
