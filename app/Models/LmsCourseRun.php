<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property int $course_revision_id
 * @property int $academic_period_id
 * @property int $rombel_id
 * @property-read LmsCourseRevision $revision
 */
class LmsCourseRun extends Model
{
    protected $table = 'tr_lms_course_runs';

    protected $fillable = ['course_id', 'course_revision_id', 'academic_period_id', 'rombel_id', 'schedule_teaching_assignment_id', 'teacher_id'];

    /** @return BelongsTo<LmsCourseRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(LmsCourseRevision::class, 'course_revision_id');
    }
}
