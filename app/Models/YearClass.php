<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YearClass extends Model
{
    protected $table = 'tr_curriculum_year_classes';

    protected $fillable = ['academic_year_id', 'rombel_id'];

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<Rombel, $this> */
    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }

    /** @return HasMany<StudentPlacement, $this> */
    public function studentPlacements(): HasMany
    {
        return $this->hasMany(StudentPlacement::class, 'academic_year_id', 'academic_year_id')
            ->where('rombel_id', $this->rombel_id);
    }

    /** @return HasMany<HomeroomAssignment, $this> */
    public function homeroomAssignments(): HasMany
    {
        return $this->hasMany(HomeroomAssignment::class, 'academic_year_id', 'academic_year_id')
            ->where('rombel_id', $this->rombel_id);
    }
}
