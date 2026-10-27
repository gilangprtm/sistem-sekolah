<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentQuizAttempt;
use App\Models\TeacherQuiz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentQuizAttemptService
{
    /** Includes the initial opening, matching the existing Ujian protocol. */
    public const INTERRUPTION_LIMIT = 4;

    public function ensureOpen(TeacherQuiz $quiz): void
    {
        abort_unless($quiz->is_open, 403, 'Ulangan dikunci oleh Guru.');
    }

    /** @return array{attempt: StudentQuizAttempt, warning: bool, locked: bool} */
    public function start(Student $student, TeacherQuiz $quiz, bool $reopen, ?int $expectedCount): array
    {
        return DB::transaction(function () use ($student, $quiz, $reopen, $expectedCount): array {
            $quiz = TeacherQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $this->ensureOpen($quiz);
            $attempt = $quiz->attempts()->where('student_id', $student->id)->lockForUpdate()->first();

            if ($attempt === null) {
                $attempt = $quiz->attempts()->create([
                    'student_id' => $student->id,
                    'status' => 'active',
                    'interruption_count' => 1,
                    'started_at' => now(),
                ]);
                $attempt->events()->create([
                    'event_type' => 'exam_attempt_started',
                    'occurred_at' => now(),
                    'interruption_count' => 1,
                ]);

                return $this->result($attempt);
            }

            if ($attempt->status === 'locked') {
                return $this->result($attempt);
            }

            if ($attempt->interruption_count === 0) {
                $attempt->update(['interruption_count' => 1]);
                $attempt->events()->create([
                    'event_type' => 'exam_attempt_started',
                    'occurred_at' => now(),
                    'interruption_count' => 1,
                ]);

                return $this->result($attempt);
            }

            if (! $reopen) {
                return $this->result($attempt);
            }

            if ($expectedCount !== null && $expectedCount !== $attempt->interruption_count) {
                return $this->result($attempt, true);
            }

            $this->increment($attempt, 'exam_attempt_reopened');

            return $this->result($attempt, true);
        });
    }

    /** @return array{attempt: StudentQuizAttempt, warning: bool, locked: bool} */
    public function recordInterruption(StudentQuizAttempt $attempt, string $eventType): array
    {
        if (! in_array($eventType, ['exam_page_hidden', 'exam_page_visible'], true)) {
            throw ValidationException::withMessages(['event_type' => 'Event ulangan tidak valid.']);
        }

        return DB::transaction(function () use ($attempt, $eventType): array {
            $quiz = TeacherQuiz::query()->lockForUpdate()->findOrFail($attempt->teacher_quiz_id);
            $this->ensureOpen($quiz);
            $attempt = StudentQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status === 'locked') {
                return $this->result($attempt);
            }

            $lastEvent = $attempt->events()->latest('id')->first();
            if ($eventType === 'exam_page_hidden') {
                if ($lastEvent?->event_type === 'exam_page_hidden') {
                    return $this->result($attempt);
                }
                $attempt->events()->create([
                    'event_type' => $eventType,
                    'occurred_at' => now(),
                    'interruption_count' => $attempt->interruption_count,
                ]);

                return $this->result($attempt);
            }

            if ($lastEvent?->event_type !== 'exam_page_hidden') {
                return $this->result($attempt);
            }

            $duration = max(0, (int) CarbonImmutable::parse((string) $lastEvent->occurred_at)->diffInSeconds(now()));
            $this->increment($attempt, $eventType, $duration);

            return $this->result($attempt, true);
        });
    }

    public function complete(StudentQuizAttempt $attempt): StudentQuizAttempt
    {
        return DB::transaction(function () use ($attempt): StudentQuizAttempt {
            $quiz = TeacherQuiz::query()->lockForUpdate()->findOrFail($attempt->teacher_quiz_id);
            $this->ensureOpen($quiz);
            $attempt = StudentQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status === 'active') {
                $attempt->update(['status' => 'locked', 'locked_at' => now(), 'interruption_count' => self::INTERRUPTION_LIMIT]);
                $attempt->events()->create([
                    'event_type' => 'exam_attempt_completed',
                    'occurred_at' => now(),
                    'interruption_count' => self::INTERRUPTION_LIMIT,
                ]);
            }

            return $attempt;
        });
    }

    public function unlock(StudentQuizAttempt $attempt, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($attempt, $actorId, $reason): void {
            $quiz = TeacherQuiz::query()->lockForUpdate()->findOrFail($attempt->teacher_quiz_id);
            abort_unless($quiz->created_by === $actorId, 404);
            $attempt = StudentQuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'locked') {
                return;
            }
            $attempt->update([
                'status' => 'active',
                'interruption_count' => 0,
                'locked_at' => null,
                'unlocked_by' => $actorId,
                'unlocked_at' => now(),
                'unlock_reason' => $reason,
            ]);
            $attempt->events()->create([
                'event_type' => 'teacher_unlock',
                'occurred_at' => now(),
                'actor_user_id' => $actorId,
                'reason' => $reason,
                'interruption_count' => 0,
            ]);
        });
    }

    private function increment(StudentQuizAttempt $attempt, string $eventType, ?int $duration = null): void
    {
        $count = min($attempt->interruption_count + 1, self::INTERRUPTION_LIMIT);
        $attempt->update([
            'interruption_count' => $count,
            'status' => $count >= self::INTERRUPTION_LIMIT ? 'locked' : 'active',
            'locked_at' => $count >= self::INTERRUPTION_LIMIT ? now() : null,
        ]);
        $attempt->events()->create([
            'event_type' => $eventType,
            'occurred_at' => now(),
            'duration_seconds' => $duration,
            'interruption_count' => $count,
        ]);
    }

    /** @return array{attempt: StudentQuizAttempt, warning: bool, locked: bool} */
    private function result(StudentQuizAttempt $attempt, bool $warning = false): array
    {
        return [
            'attempt' => $attempt,
            'warning' => $warning && $attempt->status !== 'locked' && $attempt->interruption_count > 1,
            'locked' => $attempt->status === 'locked',
        ];
    }
}
