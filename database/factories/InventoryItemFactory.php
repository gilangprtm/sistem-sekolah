<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_barang' => $this->faker->unique()->numerify('##.##.##'),
            'nama_jenis_barang' => $this->faker->words(2, true),
            'merk_type' => $this->faker->company(),
            'no_identitas' => $this->faker->numerify('##########'),
            'bahan' => $this->faker->randomElement(['Kayu', 'Besi', 'Plastik', 'Kaca']),
            'asal_perolehan' => $this->faker->randomElement(['Pembelian', 'Hibah', 'Bantuan']),
            'tahun_pembelian' => $this->faker->year(),
            'ukuran_konstruksi' => $this->faker->randomElement(['100x50x30', '60x40', '50x50x50']),
            'satuan' => $this->faker->randomElement(['Unit', 'Buah', 'Set', 'Paket']),
            'harga' => $this->faker->numberBetween(100000, 10000000),
            'keterangan' => $this->faker->sentence(),
        ];
    }
}
