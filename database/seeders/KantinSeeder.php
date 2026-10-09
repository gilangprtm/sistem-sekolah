<?php

namespace Database\Seeders;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Services\KantinBarangService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KantinSeeder extends Seeder
{
    /** @var array<int, array{name: string, barang: array<int, array{name: string, brand: string|null, satuan: string, harga: int}>}> */
    private const CATALOG = [
        [
            'name' => 'Buku',
            'barang' => [
                ['name' => 'Buku Tulis', 'brand' => 'Sidu', 'satuan' => 'pcs', 'harga' => 5000],
                ['name' => 'Buku Gambar', 'brand' => 'Fabel', 'satuan' => 'pcs', 'harga' => 7000],
            ],
        ],
        [
            'name' => 'Pulpen',
            'barang' => [
                ['name' => 'Pulpen Hitam', 'brand' => null, 'satuan' => 'pcs', 'harga' => 3000],
            ],
        ],
        [
            'name' => 'Penghapus',
            'barang' => [
                ['name' => 'Penghapus Putih', 'brand' => null, 'satuan' => 'pcs', 'harga' => 2000],
            ],
        ],
        [
            'name' => 'Makanan',
            'barang' => [
                ['name' => 'Roti', 'brand' => null, 'satuan' => 'bungkus', 'harga' => 5000],
            ],
        ],
        [
            'name' => 'Minuman',
            'barang' => [
                ['name' => 'Air Mineral', 'brand' => null, 'satuan' => 'botol', 'harga' => 4000],
            ],
        ],
    ];

    public function run(): void
    {
        $barangService = app(KantinBarangService::class);

        foreach (self::CATALOG as $catalog) {
            $category = KantinCategory::query()->updateOrCreate(
                ['slug' => Str::slug($catalog['name'])],
                ['name' => $catalog['name']],
            );

            foreach ($catalog['barang'] as $barang) {
                $existing = KantinBarang::query()
                    ->where('kantin_kategori_id', $category->id)
                    ->where('name', $barang['name'])
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'brand' => $barang['brand'],
                        'satuan' => $barang['satuan'],
                        'harga' => $barang['harga'],
                        'status' => 'active',
                    ]);

                    continue;
                }

                KantinBarang::query()->create([
                    'kantin_kategori_id' => $category->id,
                    'kode_barang' => $barangService->nextCode($category),
                    'name' => $barang['name'],
                    'brand' => $barang['brand'],
                    'satuan' => $barang['satuan'],
                    'harga' => $barang['harga'],
                    'status' => 'active',
                ]);
            }
        }
    }
}
