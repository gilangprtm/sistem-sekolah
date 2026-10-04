<?php

namespace App\Models;

use Database\Factories\RombelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rombel extends Model
{
    /** @use HasFactory<RombelFactory> */
    use HasFactory;

    protected $table = 'm_rombels';

    protected $fillable = ['code', 'name', 'grade_level', 'parallel_code', 'status'];

    /** @return HasMany<RombelPeriodUsage, $this> */
    public function periodUsages(): HasMany
    {
        return $this->hasMany(RombelPeriodUsage::class);
    }
}
