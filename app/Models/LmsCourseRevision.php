<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $revision_number
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed> $content
 * @property string $status
 * @property int $created_by
 * @property int|null $submitted_by
 * @property int|null $reviewed_by
 * @property string|null $review_note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property-read LmsCourse $course
 * @property-read Teacher $designer
 */
class LmsCourseRevision extends Model
{
    public const STATUS_DRAFT = 'draft';

    /** Existing storage value retained for database compatibility; exposed as active. */
    public const STATUS_APPROVED = 'approved';

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

    /** Legacy pending/rejected revisions remain editable drafts without rewriting history. */
    public function isDraft(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, 'pending_approval', 'rejected'], true);
    }

    public function lifecycleStatus(): string
    {
        return $this->status === self::STATUS_APPROVED ? 'active' : 'draft';
    }

    public function editToken(LmsCourse $course): string
    {
        return hash('sha256', json_encode([
            $this->id, $this->title, $this->description, $this->content,
            $this->status, $this->updated_at?->toIso8601String(),
            $course->subject_id, $course->grade_level, $course->created_by,
            $course->archived_at?->toIso8601String(), $course->approved_revision_id,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return BelongsTo<LmsCourse, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LmsCourse::class, 'course_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<Teacher, $this> */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }
}
