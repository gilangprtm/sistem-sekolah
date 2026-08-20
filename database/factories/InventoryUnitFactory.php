<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryUnit>
 */
class InventoryUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'register' => $this->faker->numerify('###'),
            'condition' => $this->faker->randomElement(InventoryUnit::CONDITIONS),
        ];
    }
}
