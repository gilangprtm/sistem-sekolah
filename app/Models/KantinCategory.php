<?php

namespace App\Models;

use Database\Factories\KantinCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property-read HasMany<KantinBarang, $this> $barangs
 */
class KantinCategory extends Model
{
    /** @use HasFactory<KantinCategoryFactory> */
    use HasFactory;

    protected $table = 'm_kantin_kategori';

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->name = trim($category->name);
            $category->slug = Str::slug($category->name);

            $duplicate = static::query()
                ->whereKeyNot($category->getKey())
                ->where(function ($query) use ($category): void {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower($category->name)])
                        ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower($category->slug)]);
                })
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'category_name' => 'Kategori sudah digunakan.',
                ]);
            }
        });
    }

    /** @return HasMany<KantinBarang, $this> */
    public function barangs(): HasMany
    {
        return $this->hasMany(KantinBarang::class, 'kantin_kategori_id');
    }
}
