<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kode_barang
 * @property string $nama_jenis_barang
 * @property string|null $merk_type
 * @property string|null $no_identitas
 * @property string|null $bahan
 * @property string|null $asal_perolehan
 * @property int|null $tahun_pembelian
 * @property string|null $ukuran_konstruksi
 * @property string|null $satuan
 * @property string $harga
 * @property string|null $keterangan
 * @property int $qty
 * @property float $total
 * @property-read Collection<int, InventoryUnit> $units
 * @property-read int|null $units_count
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    /** Field immutable (tidak dapat diubah setelah create). */
    public const IMMUTABLE_FIELDS = [
        'kode_barang',
        'nama_jenis_barang',
        'merk_type',
        'no_identitas',
        'bahan',
        'asal_perolehan',
        'tahun_pembelian',
        'ukuran_konstruksi',
        'satuan',
        'harga',
    ];

    /** Field yang boleh diubah. */
    public const MUTABLE_FIELDS = [
        'keterangan',
    ];

    protected $fillable = [
        'kode_barang',
        'nama_jenis_barang',
        'merk_type',
        'no_identitas',
        'bahan',
        'asal_perolehan',
        'tahun_pembelian',
        'ukuran_konstruksi',
        'satuan',
        'harga',
        'keterangan',
    ];

    /**
     * Unit fisik milik item ini.
     *
     * @return HasMany<InventoryUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }

    /**
     * Jumlah unit (qty).
     */
    public function getQtyAttribute(): int
    {
        return $this->units()->count();
    }

    /**
     * Total nilai = qty × harga.
     */
    public function getTotalAttribute(): float
    {
        return (float) $this->harga * $this->qty;
    }

    /**
     * Hitung register berikutnya untuk item ini (001, 002, ...).
     */
    public function nextRegisterNumber(): int
    {
        $last = $this->units()->max('register');

        return $last ? (int) $last + 1 : 1;
    }

    protected function casts(): array
    {
        return [
            'tahun_pembelian' => 'integer',
            'harga' => 'decimal:2',
        ];
    }
}
