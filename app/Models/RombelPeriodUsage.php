<?php

namespace App\Models;

use Database\Factories\RombelPeriodUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RombelPeriodUsage extends Model
{
    /** @use HasFactory<RombelPeriodUsageFactory> */
    use HasFactory;

    protected $table = 'tr_curriculum_period_rombels';

    protected $fillable = ['academic_period_id', 'rombel_id'];

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return BelongsTo<Rombel, $this> */
    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }
}
