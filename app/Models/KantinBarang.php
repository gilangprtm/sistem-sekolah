<?php

namespace App\Models;

use Database\Factories\KantinBarangFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $kantin_kategori_id
 * @property string $kode_barang
 * @property string $name
 * @property string|null $brand
 * @property string $satuan
 * @property string $harga
 * @property string|null $description
 * @property string $status
 * @property-read KantinCategory $category
 */
class KantinBarang extends Model
{
    /** @use HasFactory<KantinBarangFactory> */
    use HasFactory;

    protected $table = 'm_kantin_barang';

    protected $fillable = [
        'kantin_kategori_id',
        'kode_barang',
        'name',
        'brand',
        'satuan',
        'harga',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<KantinCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KantinCategory::class, 'kantin_kategori_id');
    }
}
