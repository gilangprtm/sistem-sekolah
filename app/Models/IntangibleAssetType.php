<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property string $name
 * @property string $slug
 */
class IntangibleAssetType extends Model
{
    protected $table = 'm_inventory_intangible_asset_types';

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->name = trim($type->name);
            $type->slug = Str::slug($type->name);
            if (static::query()->whereKeyNot($type->getKey())->where(function ($query) use ($type): void {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($type->name)])->orWhere('slug', $type->slug);
            })->exists()) {
                throw ValidationException::withMessages(['name' => 'Jenis aset tak berwujud sudah digunakan.']);
            }
        });
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'intangible_asset_type_id', 'id');
    }
}
