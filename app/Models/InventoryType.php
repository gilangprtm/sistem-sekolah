<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryType extends Model
{
    protected $table = 'm_inventory_types';

    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            $type->name = trim($type->name);
            $type->slug = Str::slug($type->name);
            if (static::query()->whereKeyNot($type->getKey())->where(function ($query) use ($type): void {
                $query->whereRaw('LOWER(name) = ?', [mb_strtolower($type->name)])
                    ->orWhere('slug', $type->slug);
            })->exists()) {
                throw ValidationException::withMessages(['name' => 'Jenis inventaris sudah digunakan.']);
            }
        });
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
