<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property array{submitted_pages?: list<string>}|null $progress
 * @property-read int|null $answers_count
 */
class LmsCourseAttempt extends Model
{
    protected $table = 'tr_lms_course_attempts';

    protected $fillable = ['course_run_id', 'course_id', 'course_revision_id', 'student_id', 'status', 'started_at', 'submitted_at', 'progress'];

    protected function casts(): array
    {
        return ['progress' => 'array', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    /** @return HasMany<LmsCourseAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(LmsCourseAnswer::class, 'course_attempt_id');
    }
}
