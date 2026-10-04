<?php

namespace Database\Factories;

use App\Models\SchedulePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchedulePlan> */
class SchedulePlanFactory extends Factory
{
    protected $model = SchedulePlan::class;

    public function definition(): array
    {
        return [
            'academic_period_id' => null,
            'source_plan_id' => null,
            'created_by' => null,
            'status' => 'draft',
            'revision' => 1,
            'payload_hash' => hash('sha256', fake()->uuid()),
            'published_at' => null,
            'archived_at' => null,
        ];
    }
}
