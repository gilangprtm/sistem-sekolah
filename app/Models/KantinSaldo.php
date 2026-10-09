<?php

namespace App\Models;

use Database\Factories\KantinSaldoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $student_id
 * @property string $saldo
 */
class KantinSaldo extends Model
{
    /** @use HasFactory<KantinSaldoFactory> */
    use HasFactory;

    protected $table = 'm_kantin_saldo';

    protected $fillable = ['student_id', 'saldo'];

    protected function casts(): array
    {
        return ['saldo' => 'decimal:2'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return HasMany<KantinSaldoTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(KantinSaldoTransaction::class, 'kantin_saldo_id');
    }
}
