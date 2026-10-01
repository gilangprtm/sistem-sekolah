<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AcademicYear extends Model
{
    protected $table = 'm_academic_years';

    protected $fillable = ['year', 'status', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];
    }

    /** @return HasMany<AcademicPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class);
    }

    public function activate(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT pg_advisory_xact_lock(hashtextextended('m_academic_years.active', 0))");
            }

            self::query()
                ->where('status', 'active')
                ->whereKeyNot($this->id)
                ->lockForUpdate()
                ->get()
                ->each(fn (self $activeYear): bool => $activeYear->update([
                    'status' => 'inactive',
                    'end_date' => now()->toDateString(),
                ]));

            $this->update([
                'status' => 'active',
                'start_date' => $this->start_date ?? now()->toDateString(),
                'end_date' => null,
            ]);
        });
    }

    public function deactivate(): void
    {
        DB::transaction(function (): void {
            $today = now()->toDateString();
            $this->update(['status' => 'inactive', 'end_date' => $today]);
            $this->periods()->where('status', 'active')->update(['status' => 'inactive', 'end_date' => $today]);
        });
    }
}
