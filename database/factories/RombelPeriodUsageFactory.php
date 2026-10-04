<?php

namespace Database\Factories;

use App\Models\RombelPeriodUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RombelPeriodUsage> */
class RombelPeriodUsageFactory extends Factory
{
    protected $model = RombelPeriodUsage::class;

    public function definition(): array
    {
        return [
            'academic_period_id' => null,
            'rombel_id' => null,
        ];
    }
}
