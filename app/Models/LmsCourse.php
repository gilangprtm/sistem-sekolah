<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $grade_level
 * @property string $title
 * @property int $created_by
 * @property bool $has_approved_revision
 * @property Carbon|null $archived_at
 * @property-read Subject $subject
 * @property-read Teacher $designer
 * @property-read LmsCourseRevision|null $latestRevision
 */
class LmsCourse extends Model
{
    public const GRADE_LEVELS = ['VII', 'VIII', 'IX'];

    protected $table = 'm_lms_courses';

    protected $fillable = ['subject_id', 'grade_level', 'title', 'created_by'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    /** @return HasMany<LmsCourseRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(LmsCourseRevision::class, 'course_id');
    }

    /** @return HasOne<LmsCourseRevision, $this> */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(LmsCourseRevision::class, 'course_id')->ofMany('revision_number', 'max');
    }
}
