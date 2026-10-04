<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    protected $table = 'm_academic_periods';

    protected $fillable = ['academic_year_id', 'code', 'name', 'status', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<RombelPeriodUsage, $this> */
    public function rombelUsages(): HasMany
    {
        return $this->hasMany(RombelPeriodUsage::class);
    }

    public function activate(): void
    {
        if ($this->academicYear()->where('status', 'inactive')->exists()) {
            throw new \DomainException('Semester hanya dapat diaktifkan pada Tahun Ajaran aktif.');
        }

        $this->update(['status' => 'active', 'start_date' => $this->start_date ?? now()->toDateString(), 'end_date' => null]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive', 'end_date' => now()->toDateString()]);
    }
}
