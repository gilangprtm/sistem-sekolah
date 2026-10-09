<?php

namespace Database\Factories;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KantinBarang> */
class KantinBarangFactory extends Factory
{
    protected $model = KantinBarang::class;

    public function definition(): array
    {
        $category = KantinCategory::factory();

        return [
            'kantin_kategori_id' => $category,
            'kode_barang' => 'Barang-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'brand' => fake()->optional()->company(),
            'satuan' => fake()->randomElement(['pcs', 'botol', 'bungkus']),
            'harga' => fake()->numberBetween(1000, 100000),
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
        ];
    }
}
