<?php

namespace App\Models;

use Database\Factories\InventoryUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_item_id
 * @property string $register
 * @property string $condition
 * @property-read InventoryItem $item
 */
class InventoryUnit extends Model
{
    /** @use HasFactory<InventoryUnitFactory> */
    use HasFactory;

    public const CONDITIONS = ['B', 'KB', 'RB'];

    protected $fillable = [
        'inventory_item_id',
        'register',
        'condition',
    ];

    /**
     * Item induk unit ini.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Format register agar selalu 3 digit (001, 002, ...).
     */
    public static function formatRegister(int $number): string
    {
        return str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return [
            'condition' => 'string',
        ];
    }
}
