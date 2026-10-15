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

    public function start(Student $student, UasLink $uasLink): StudentExamAttempt
    {
        return DB::transaction(function () use ($student, $uasLink): StudentExamAttempt {
            $attempt = StudentExamAttempt::query()->firstOrCreate(
                ['student_id' => $student->id, 'uas_link_id' => $uasLink->id],
                ['status' => 'active', 'started_at' => now()],
            );

            if ($attempt->status === 'locked') {
                throw ValidationException::withMessages([
                    'attempt' => 'Percobaan ujian ini terkunci. Hubungi administrator untuk membuka kembali.',
                ]);
            }

            return $attempt;
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

                return ['attempt' => $attempt, 'warning' => $count > 0, 'locked' => false];
            }

            if ($lastEvent?->event_type !== 'exam_page_hidden') {
                throw ValidationException::withMessages(['event' => 'Tidak ada gangguan halaman yang menunggu.']);
            }

            $count++;
            $durationSeconds = max(
                0,
                CarbonImmutable::parse((string) $lastEvent->occurred_at)->diffInSeconds($receivedAt),
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
