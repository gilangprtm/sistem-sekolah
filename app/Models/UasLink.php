<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UasLink extends Model
{
    protected $table = 'tr_curriculum_uas_links';

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'category',
        'academic_year_id',
        'academic_period_id',
        'subject_id',
        'link',
        'opens_at',
    ];

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
