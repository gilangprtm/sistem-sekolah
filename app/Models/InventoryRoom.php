<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryRoom extends Model
{
    protected $table = 'm_inventory_rooms';

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class, 'inventory_room_id');
    }
}
