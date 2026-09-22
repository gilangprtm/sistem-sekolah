<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property-read Collection<int, InventoryItem> $inventoryItems
 */
class Category extends Model
{
    protected $table = 'm_inventory_categories';

    protected $fillable = [
        'name',
        'description',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = $category->name;
            }

            $category->slug = Str::slug($category->slug ?: $category->name);

            $duplicate = static::query()
                ->whereKeyNot($category->getKey())
                ->where(function ($query) use ($category): void {
                    $query->whereRaw('LOWER(name) = ?', [mb_strtolower($category->name)])
                        ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower($category->slug)]);
                })
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'Nama atau slug kategori sudah digunakan.',
                ]);
            }
        });
    }

    /**
     * Inventory groups assigned to this category.
     *
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'inventory_category_id');
    }
}
