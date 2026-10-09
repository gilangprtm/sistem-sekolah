<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KantinSaldoTransaction extends Model
{
    protected $table = 'tr_kantin_saldo_mutasi';

    protected $fillable = [
        'student_id',
        'kantin_saldo_id',
        'created_by',
        'type',
        'amount',
        'balance_before',
        'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<KantinSaldo, $this> */
    public function saldo(): BelongsTo
    {
        return $this->belongsTo(KantinSaldo::class, 'kantin_saldo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
