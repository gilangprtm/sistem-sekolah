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
 * @property string|null $image_path
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
        'image_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
        ];
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        $path = route('kantin.barang.image', ['kantinBarang' => $this->getKey()], false);

        if (! app()->bound('request')) {
            return $path;
        }

        $request = request();
        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto'));
        $scheme = $request->isSecure() || $forwardedProto === 'https'
            ? 'https'
            : $request->getScheme();

        return $scheme.'://'.$request->getHttpHost().$path;
    }

    /** @return BelongsTo<KantinCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KantinCategory::class, 'kantin_kategori_id');
    }
}
