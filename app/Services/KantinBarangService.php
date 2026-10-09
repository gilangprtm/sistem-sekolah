<?php

namespace App\Services;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class KantinBarangService
{
    /**
     * @param  array{category_id?: int|null, category_name?: string|null, name: string, brand?: string|null, satuan: string, harga: numeric-string|int|float, description?: string|null, status?: string}  $data
     */
    public function create(array $data): KantinBarang
    {
        return DB::transaction(function () use ($data): KantinBarang {
            $category = $this->resolveCategory($data);
            $this->lockCodeAllocation();
            $category = KantinCategory::query()->whereKey($category->id)->firstOrFail();
            $code = $this->nextCode($category);

            try {
                return KantinBarang::query()->create([
                    'kantin_kategori_id' => $category->id,
                    'kode_barang' => $code,
                    'name' => $data['name'],
                    'brand' => $data['brand'] ?? null,
                    'satuan' => $data['satuan'],
                    'harga' => $data['harga'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'kode_barang' => 'Kode barang sudah digunakan. Silakan coba lagi.',
                ]);
            }
        });
    }

    /**
     * @param  array{category_id?: int|null, category_name?: string|null, name: string, brand?: string|null, satuan: string, harga: numeric-string|int|float, description?: string|null, status?: string}  $data
     */
    public function update(KantinBarang $barang, array $data): KantinBarang
    {
        return DB::transaction(function () use ($barang, $data): KantinBarang {
            $category = $this->resolveCategory($data);
            if ($category->id !== $barang->kantin_kategori_id) {
                $this->lockCodeAllocation();
                $category = KantinCategory::query()->whereKey($category->id)->firstOrFail();
                $barang->kantin_kategori_id = $category->id;
                $barang->kode_barang = $this->nextCode($category);
            }

            $barang->fill([
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'satuan' => $data['satuan'],
                'harga' => $data['harga'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? $barang->status,
            ]);
            $barang->save();

            return $barang->refresh();
        });
    }

    /**
     * @param  array{category_id?: int|null, category_name?: string|null}  $data
     */
    private function resolveCategory(array $data): KantinCategory
    {
        if (! empty($data['category_id'])) {
            return KantinCategory::query()->findOrFail($data['category_id']);
        }

        $name = trim((string) ($data['category_name'] ?? ''));
        $slug = Str::slug($name);
        $category = KantinCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orWhere('slug', $slug)
            ->first();

        if ($category !== null) {
            return $category;
        }

        KantinCategory::query()->insertOrIgnore([
            'name' => $name,
            'slug' => $slug,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return KantinCategory::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Build the canonical code prefix used by HTTP writes and seed data.
     * Separators are normalized while preserving the category display casing.
     */
    public static function codePrefix(string $categoryName): string
    {
        return Str::of($categoryName)
            ->trim()
            ->replaceMatches('/[^A-Za-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }

    /**
     * Allocate a globally unique code for a category.
     *
     * The caller locks all category rows before invoking this method, so two
     * concurrent writes cannot select the same normalized prefix and sequence.
     */
    public function nextCode(KantinCategory $category): string
    {
        $prefix = self::codePrefix($category->name);
        $lastNumber = KantinBarang::query()
            ->whereRaw('LOWER(kode_barang) LIKE LOWER(?)', [$prefix.'-%'])
            ->pluck('kode_barang')
            ->map(function (string $code) use ($prefix): int {
                $prefixLength = strlen($prefix) + 1;
                if (strcasecmp(substr($code, 0, $prefixLength), $prefix.'-') !== 0) {
                    return 0;
                }

                $suffix = substr($code, $prefixLength);

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return sprintf('%s-%04d', $prefix, $lastNumber + 1);
    }

    private function lockCodeAllocation(): void
    {
        KantinCategory::query()->select('id')->orderBy('id')->lockForUpdate()->get();
    }
}
