<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentExamAttempt;
use App\Models\UasLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentExamAttemptService
{
    public const INTERRUPTION_LIMIT = 4;

    /** @return array{attempt: StudentExamAttempt, warning: bool, locked: bool} */
    public function start(Student $student, UasLink $uasLink, bool $reopen = false, ?int $expectedCount = null): array
    {
        return DB::transaction(function () use ($student, $uasLink, $reopen, $expectedCount): array {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $attempt = StudentExamAttempt::query()
                ->where('student_id', $student->id)
                ->where('uas_link_id', $uasLink->id)
                ->lockForUpdate()
                ->first();

            if ($attempt === null) {
                $attempt = StudentExamAttempt::query()->create([
                    'student_id' => $student->id,
                    'uas_link_id' => $uasLink->id,
                    'status' => 'active',
                    'started_at' => now(),
                    'interruption_count' => 1,
                ]);
                $attempt->events()->create([
                    'event_type' => 'exam_attempt_started',
                    'occurred_at' => now(),
                    'interruption_count' => 1,
                ]);

                return ['attempt' => $attempt, 'warning' => false, 'locked' => false];
            }

            if ($attempt->status === 'locked') {
                return ['attempt' => $attempt, 'warning' => false, 'locked' => true];
            }

            if ($attempt->interruption_count === 0) {
                $attempt->forceFill(['interruption_count' => 1])->save();
                $attempt->events()->create([
                    'event_type' => 'exam_attempt_started',
                    'occurred_at' => now(),
                    'interruption_count' => 1,
                ]);

                return ['attempt' => $attempt->fresh(), 'warning' => false, 'locked' => false];
            }

            if (! $reopen) {
                return ['attempt' => $attempt, 'warning' => false, 'locked' => false];
            }

            if ($expectedCount !== null && $attempt->interruption_count !== $expectedCount) {
                $locked = $attempt->interruption_count >= self::INTERRUPTION_LIMIT;

                return [
                    'attempt' => $attempt,
                    'warning' => ! $locked && $attempt->interruption_count > 1,
                    'locked' => $locked,
                ];
            }

            $count = min($attempt->interruption_count + 1, self::INTERRUPTION_LIMIT);
            $locked = $count >= self::INTERRUPTION_LIMIT;
            $receivedAt = now();
            $attempt->forceFill([
                'interruption_count' => $count,
                'status' => $locked ? 'locked' : 'active',
                'locked_at' => $locked ? $receivedAt : null,
            ])->save();
            $attempt->events()->create([
                'event_type' => 'exam_attempt_reopened',
                'occurred_at' => $receivedAt,
                'interruption_count' => $count,
            ]);

            return [
                'attempt' => $attempt->fresh(),
                'warning' => ! $locked,
                'locked' => $locked,
            ];
        });
    }

    /** @return array{attempt: StudentExamAttempt, warning: bool, locked: bool} */
    public function recordInterruption(StudentExamAttempt $attempt, string $eventType): array
    {
        if (! in_array($eventType, ['exam_page_hidden', 'exam_page_visible'], true)) {
            throw ValidationException::withMessages(['event' => 'Event ujian tidak valid.']);
        }

        return DB::transaction(function () use ($attempt, $eventType): array {
            $attempt = StudentExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== 'active') {
                throw ValidationException::withMessages(['attempt' => 'Percobaan ujian tidak aktif.']);
            }

            $count = $attempt->interruption_count;
            $lastEvent = $attempt->events()->latest('id')->first();
            $receivedAt = now();

            if ($eventType === 'exam_page_hidden') {
                if ($lastEvent?->event_type === 'exam_page_hidden') {
                    throw ValidationException::withMessages(['event' => 'Gangguan halaman sedang diproses.']);
                }
                $attempt->events()->create([
                    'event_type' => $eventType,
                    'occurred_at' => $receivedAt,
                    'duration_seconds' => null,
                    'interruption_count' => $count,
                ]);

                return ['attempt' => $attempt, 'warning' => false, 'locked' => false];
            }

            if ($lastEvent?->event_type !== 'exam_page_hidden') {
                throw ValidationException::withMessages(['event' => 'Tidak ada gangguan halaman yang menunggu.']);
            }

            $count = min($count + 1, self::INTERRUPTION_LIMIT);
            $durationSeconds = max(
                0,
                (int) CarbonImmutable::parse((string) $lastEvent->occurred_at)->diffInSeconds($receivedAt),
            );
            $attempt->forceFill([
                'interruption_count' => $count,
                'status' => $count >= self::INTERRUPTION_LIMIT ? 'locked' : 'active',
                'locked_at' => $count >= self::INTERRUPTION_LIMIT ? $receivedAt : null,
            ])->save();
            $attempt->events()->create([
                'event_type' => $eventType,
                'occurred_at' => $receivedAt,
                'duration_seconds' => $durationSeconds,
                'interruption_count' => $count,
            ]);

            return [
                'attempt' => $attempt->fresh(),
                'warning' => $count < self::INTERRUPTION_LIMIT,
                'locked' => $count >= self::INTERRUPTION_LIMIT,
            ];
        });
    }

    public function complete(StudentExamAttempt $attempt): StudentExamAttempt
    {
        return DB::transaction(function () use ($attempt): StudentExamAttempt {
            $attempt = StudentExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== 'active') {
                return $attempt;
            }

            $completedAt = now();
            $attempt->forceFill([
                'status' => 'locked',
                'interruption_count' => self::INTERRUPTION_LIMIT,
                'locked_at' => $completedAt,
            ])->save();
            $attempt->events()->create([
                'event_type' => 'exam_attempt_completed',
                'occurred_at' => $completedAt,
                'interruption_count' => self::INTERRUPTION_LIMIT,
            ]);

            return $attempt->fresh();
        });
    }

    public function unlock(StudentExamAttempt $attempt, int $adminId, string $reason): StudentExamAttempt
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi.']);
        }

        return DB::transaction(function () use ($attempt, $adminId, $reason): StudentExamAttempt {
            $attempt = StudentExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $attempt->forceFill([
                'status' => 'active',
                'interruption_count' => 0,
                'locked_at' => null,
                'unlocked_by' => $adminId,
                'unlocked_at' => now(),
                'unlock_reason' => $reason,
            ])->save();
            $attempt->events()->create([
                'event_type' => 'admin_unlock',
                'occurred_at' => now(),
                'actor_user_id' => $adminId,
                'reason' => $reason,
                'interruption_count' => 0,
            ]);

            return $attempt->fresh();
        });
    }
}
