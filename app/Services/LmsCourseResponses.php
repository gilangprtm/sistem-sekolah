<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\LmsCourse;
use App\Models\LmsCourseAttempt;
use App\Models\LmsCourseRevision;
use App\Models\LmsCourseRun;
use App\Models\Rombel;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LmsCourseResponses
{
    public function __construct(private LmsCourseAccess $access) {}

    /** @return Builder<ScheduleTeachingAssignment> */
    private function assignments(LmsCourse $course, AcademicPeriod $period): Builder
    {
        return $this->access->assignments($period)->where('subject_id', $course->subject_id)
            ->whereHas('rombel', fn (Builder $rombel) => $rombel->where('grade_level', $course->grade_level));
    }

    /** @return Builder<Student> */
    private function roster(AcademicPeriod $period, int $rombelId): Builder
    {
        return Student::query()->where('status', 'active')
            ->whereIn('id', StudentPlacement::query()->select('student_id')
                ->where('academic_year_id', $period->academic_year_id)
                ->where('rombel_id', $rombelId)->where('status', 'active'));
    }

    /** @return list<array<string, mixed>> */
    public function classes(User $user, LmsCourse $course): array
    {
        $period = $this->access->activePeriod();
        if ($period === null) {
            return [];
        }
        $assignments = $this->assignments($course, $period)
            ->when(! $user->hasRole('Super Admin'), fn (Builder $query) => $query->where('teacher_id', $user->teacher?->id))
            ->pluck('rombel_id');

        return array_values(Rombel::query()->whereIn('id', $assignments)->orderBy('code')->orderBy('id')
            ->get(['id', 'code', 'name'])->map(fn (Rombel $rombel): array => $rombel->only(['id', 'code', 'name']))->all());
    }

    /** @return array{students: list<array<string, mixed>>, selected: array<string, mixed>|null} */
    public function response(User $user, LmsCourse $course, int $rombelId, ?int $studentId): array
    {
        $period = $this->access->activePeriod();
        abort_if($period === null, 404);
        abort_unless($this->assignments($course, $period)->where('rombel_id', $rombelId)
            ->when(! $user->hasRole('Super Admin'), fn (Builder $query) => $query->where('teacher_id', $user->teacher?->id))->exists(), 404);
        $students = $this->roster($period, $rombelId)->orderBy('full_name')->orderBy('id')->get(['id', 'full_name', 'nis']);
        abort_if($studentId !== null && ! $students->contains('id', $studentId), 404);
        $run = $this->findRun($course, $period, $rombelId);
        $revision = $run === null ? $course->approvedRevision : $run->revision;
        $attempts = LmsCourseAttempt::query()->where('course_run_id', $run?->id)
            ->whereIn('student_id', $students->modelKeys())->withCount('answers')->get()->keyBy('student_id');
        $selectedAttempt = $studentId === null ? null : $attempts->get($studentId);

        return [
            'students' => array_values($students->map(function (Student $student) use ($attempts): array {
                $attempt = $attempts->get($student->id);

                return [
                    'id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis,
                    'status' => $attempt === null ? 'not_started' : $attempt->status, 'answered_count' => $attempt === null ? 0 : $attempt->answers_count,
                ];
            })->all()),
            'selected' => $studentId === null ? null : [
                'student_id' => $studentId, 'name' => $students->firstWhere('id', $studentId)->full_name,
                'status' => $selectedAttempt === null ? 'not_started' : $selectedAttempt->status,
                'answers' => (object) $this->answers($selectedAttempt), 'revision' => $revision,
                'submitted_at' => $selectedAttempt?->submitted_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array{period: AcademicPeriod, placement: StudentPlacement, assignment: ScheduleTeachingAssignment, run: LmsCourseRun|null, revision: LmsCourseRevision, attempt: LmsCourseAttempt|null} */
    public function studentContext(User $user, LmsCourse $course): array
    {
        abort_unless($this->access->availableToStudent($user)->whereKey($course->id)->exists(), 404);
        $period = $this->access->activePeriod();
        abort_if($period === null, 404);
        $placement = $this->access->studentPlacement($user, $period);
        abort_if($placement === null, 404);
        $assignment = $this->assignments($course, $period)->where('rombel_id', $placement->rombel_id)->orderBy('id')->firstOrFail();
        $run = $this->findRun($course, $period, $placement->rombel_id);
        $revision = $run === null ? $course->approvedRevision : $run->revision;
        abort_if($revision === null, 404);
        $attempt = $run === null ? null : LmsCourseAttempt::query()->where('course_run_id', $run->id)
            ->where('student_id', $user->student->id)->first();

        return compact('period', 'placement', 'assignment', 'run', 'revision', 'attempt');
    }

    private function findRun(LmsCourse $course, AcademicPeriod $period, int $rombelId): ?LmsCourseRun
    {
        return LmsCourseRun::query()->with('revision')->where('course_id', $course->id)
            ->where('academic_period_id', $period->id)->where('rombel_id', $rombelId)->first();
    }

    /** @return array<string, string> */
    public function answers(?LmsCourseAttempt $attempt): array
    {
        return $attempt === null ? [] : $attempt->answers()->orderBy('question_key')->get()
            ->mapWithKeys(fn ($answer): array => [$answer->question_key => $answer->answer['value']])->all();
    }

    public function token(?LmsCourseAttempt $attempt, LmsCourseRevision $revision): string
    {
        return hash('sha256', json_encode([$revision->id, $attempt?->id, $attempt?->status, $this->answers($attempt)], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function save(User $user, LmsCourse $course, array $data): void
    {
        DB::transaction(function () use ($user, $course, $data): void {
            $lockedCourse = LmsCourse::query()->lockForUpdate()->findOrFail($course->id);
            $context = $this->studentContext($user, $lockedCourse);
            $revision = $context['revision'];
            $attempt = $context['attempt'];
            if ((int) $data['revision_id'] !== $revision->id || ! hash_equals($this->token($attempt, $revision), $data['attempt_token'])) {
                throw ValidationException::withMessages(['answers' => 'Pengerjaan atau versi Course telah berubah. Muat ulang sebelum menyimpan.']);
            }
            abort_if($attempt?->status === 'submitted', 409);
            $answers = $this->validateAnswers($revision, $data['answers']);
            $run = $context['run'] ?? LmsCourseRun::query()->create([
                'course_id' => $course->id, 'academic_period_id' => $context['period']->id,
                'rombel_id' => $context['placement']->rombel_id, 'course_revision_id' => $revision->id,
                'schedule_teaching_assignment_id' => $context['assignment']->id, 'teacher_id' => $context['assignment']->teacher_id,
            ]);
            $attempt ??= LmsCourseAttempt::query()->create([
                'course_run_id' => $run->id, 'student_id' => $user->student->id,
                'course_id' => $course->id, 'course_revision_id' => $revision->id,
                'status' => 'in_progress', 'started_at' => now(),
            ]);
            $attempt->answers()->whereNotIn('question_key', array_keys($answers))->delete();
            foreach ($answers as $questionId => $value) {
                $attempt->answers()->updateOrCreate(['question_key' => $questionId], ['answer' => ['value' => $value], 'answered_at' => now()]);
            }
            if ($data['submit']) {
                $attempt->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
            }
        });
    }

    /** @param array<string, mixed> $answers
     * @return array<string, string>
     */
    private function validateAnswers(LmsCourseRevision $revision, array $answers): array
    {
        $definitions = [];
        foreach ($revision->content['materials'] ?? [] as $material) {
            foreach ([$material, ...($material['sections'] ?? [])] as $page) {
                foreach ($page['questions'] ?? [] as $question) {
                    $definitions[$question['id']] = $question;
                }
            }
        }
        $normalized = [];
        foreach ($answers as $id => $answer) {
            $question = $definitions[$id] ?? null;
            if ($question === null || ($answer !== null && ! is_string($answer)) || ($answer !== null && mb_strlen($answer) > 10000)) {
                throw ValidationException::withMessages(['answers' => 'Soal atau jawaban tidak valid untuk Course ini.']);
            }
            if ($answer === null || trim($answer) === '') {
                continue;
            }
            if ($question['type'] === 'multiple_choice' && ! in_array($answer, array_column($question['options'] ?? [], 'id'), true)) {
                throw ValidationException::withMessages(['answers' => 'Pilih salah satu opsi yang tersedia pada soal.']);
            }
            $normalized[$id] = $answer;
        }

        return $normalized;
    }
}
