<?php

namespace Database\Factories;

use App\Models\KantinCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KantinCategory> */
class KantinCategoryFactory extends Factory
{
    protected $model = KantinCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
