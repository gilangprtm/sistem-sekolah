<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $course_id
 * @property int $revision_number
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed> $content
 * @property string $status
 * @property int $created_by
 * @property-read LmsCourse $course
 * @property-read Teacher $designer
 */
class LmsCourseRevision extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'tr_lms_course_revisions';

    protected $fillable = [
        'course_id', 'revision_number', 'title', 'description', 'status',
        'content_schema_version', 'content', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'revision_number' => 'integer',
            'content_schema_version' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function editToken(LmsCourse $course): string
    {
        return hash('sha256', json_encode([
            $this->id, $this->title, $this->description, $this->content,
            $this->status, $this->updated_at?->toIso8601String(),
            $course->subject_id, $course->grade_level, $course->created_by,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return BelongsTo<LmsCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }
}
