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
use Illuminate\Support\Collection;
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

    /** @return array{students: list<array<string, mixed>>, selected: array<string, mixed>|null, gradebook: array<string, mixed>|null, revision: LmsCourseRevision, revisions: list<array{id: int, number: int, current: bool}>} */
    public function response(User $user, LmsCourse $course, int $rombelId, ?int $studentId, ?int $revisionId = null): array
    {
        $period = $this->access->activePeriod();
        abort_if($period === null, 404);
        abort_unless($this->assignments($course, $period)->where('rombel_id', $rombelId)
            ->when(! $user->hasRole('Super Admin'), fn (Builder $query) => $query->where('teacher_id', $user->teacher?->id))->exists(), 404);
        $students = $this->roster($period, $rombelId)->orderBy('full_name')->orderBy('id')->get(['id', 'full_name', 'nis']);
        abort_if($studentId !== null && ! $students->contains('id', $studentId), 404);
        $revision = $this->responseRevision($course, $period, $rombelId, $revisionId);
        $run = $this->findRun($course, $period, $rombelId, $revision);
        $attempts = LmsCourseAttempt::query()->where('course_run_id', $run?->id)
            ->whereIn('student_id', $students->modelKeys())
            ->withCount(['answers' => fn (Builder $query) => $query->where('answer->value', '!=', '')])->get()->keyBy('student_id');
        $selectedAttempt = $studentId === null ? null : $attempts->get($studentId);
        $gradebook = null;
        if ($user->can('grade', $course)) {
            $attempts->loadMissing('answers');
            $grading = app(LmsCourseGrading::class);
            $pages = [];
            foreach ($revision->content['materials'] ?? [] as $index => $material) {
                foreach ([$material, ...($material['sections'] ?? [])] as $page) {
                    if (($page['questions'] ?? []) !== []) {
                        $pages[] = ['id' => $page['id'], 'title' => ($index + 1).'. '.$material['title'].($page['id'] === $material['id'] ? '' : ' · '.$page['title'])];
                    }
                }
            }
            $gradebook = [
                'pages' => $pages,
                'students' => array_values($students->map(function (Student $student) use ($attempts, $grading, $revision): array {
                    $attempt = $attempts->get($student->id);
                    $results = $grading->results($attempt, $revision, $this->submittedPages($attempt, $revision));
                    $scores = [];
                    foreach ($results as $pageId => $result) {
                        $scores[$pageId] = array_intersect_key($result, array_flip(['weights_set', 'submitted', 'score', 'ungraded_essays']));
                    }

                    return ['id' => $student->id, 'name' => $student->full_name, 'scores' => (object) $scores];
                })->all()),
            ];
        }

        return [
            'gradebook' => $gradebook,
            'revision' => $revision,
            'revisions' => $this->responseRevisions($course, $period, $rombelId),
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
                'submitted_page_ids' => $selectedAttempt === null ? [] : $this->submittedPages($selectedAttempt, $revision),
                'answers' => (object) $this->answers($selectedAttempt),
                'grading' => app(LmsCourseGrading::class)->results($selectedAttempt, $revision, $selectedAttempt === null ? [] : $this->submittedPages($selectedAttempt, $revision)),
                'revision' => $revision,
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
        $revision = $course->approvedRevision;
        abort_if($revision === null, 404);
        $run = $this->findRun($course, $period, $placement->rombel_id, $revision);
        $attempt = $run === null ? null : LmsCourseAttempt::query()->where('course_run_id', $run->id)
            ->where('student_id', $user->student->id)->first();

        return compact('period', 'placement', 'assignment', 'run', 'revision', 'attempt');
    }

    /** @return array{revision: LmsCourseRevision, attempt: LmsCourseAttempt} */
    public function teacherContext(User $user, LmsCourse $course, int $rombelId, int $studentId, ?int $revisionId = null): array
    {
        $period = $this->access->activePeriod();
        abort_if($period === null, 404);
        abort_unless($this->assignments($course, $period)->where('rombel_id', $rombelId)
            ->when(! $user->hasRole('Super Admin'), fn (Builder $query) => $query->where('teacher_id', $user->teacher?->id))->exists(), 404);
        $student = $this->roster($period, $rombelId)->whereKey($studentId)->firstOrFail();
        $revision = $this->responseRevision($course, $period, $rombelId, $revisionId);
        $run = $this->findRun($course, $period, $rombelId, $revision);
        abort_if($run === null, 404);
        $attempt = LmsCourseAttempt::query()->where('course_run_id', $run->id)->where('student_id', $student->id)->firstOrFail();

        return compact('revision', 'attempt');
    }

    private function findRun(LmsCourse $course, AcademicPeriod $period, int $rombelId, LmsCourseRevision $revision): ?LmsCourseRun
    {
        return LmsCourseRun::query()->with('revision')->where('course_id', $course->id)
            ->where('academic_period_id', $period->id)->where('rombel_id', $rombelId)
            ->where('course_revision_id', $revision->id)->first();
    }

    private function responseRevision(LmsCourse $course, AcademicPeriod $period, int $rombelId, ?int $revisionId = null): LmsCourseRevision
    {
        $revisionId ??= $course->approved_revision_id;
        abort_if($revisionId === null, 404);
        $revision = $course->revisions()->whereKey($revisionId)->where('status', LmsCourseRevision::STATUS_APPROVED)->firstOrFail();
        abort_unless($revision->id === $course->approved_revision_id || $this->findRun($course, $period, $rombelId, $revision) !== null, 404);

        return $revision;
    }

    /** @return list<array{id: int, number: int, current: bool}> */
    private function responseRevisions(LmsCourse $course, AcademicPeriod $period, int $rombelId): array
    {
        return array_values($course->revisions()->where('status', LmsCourseRevision::STATUS_APPROVED)
            ->where(function (Builder $query) use ($course, $period, $rombelId): void {
                $query->whereKey($course->approved_revision_id)->orWhereIn('id', LmsCourseRun::query()->select('course_revision_id')
                    ->where('course_id', $course->id)->where('academic_period_id', $period->id)->where('rombel_id', $rombelId));
            })->orderByDesc('revision_number')->get()->map(fn (LmsCourseRevision $revision): array => [
                'id' => $revision->id, 'number' => $revision->revision_number, 'current' => $revision->id === $course->approved_revision_id,
            ])->all());
    }

    /** @return array<string, string> */
    public function answers(?LmsCourseAttempt $attempt): array
    {
        return $attempt === null ? [] : $attempt->answers()->where('answer->value', '!=', '')->orderBy('question_key')->get()
            ->mapWithKeys(fn ($answer): array => [$answer->question_key => $answer->answer['value']])->all();
    }

    public function token(?LmsCourseAttempt $attempt, LmsCourseRevision $revision): string
    {
        return hash('sha256', json_encode([$revision->id, $attempt?->id, $attempt?->status, $attempt?->progress, $this->answers($attempt)], JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public function submittedPages(?LmsCourseAttempt $attempt, LmsCourseRevision $revision): array
    {
        if ($attempt === null) {
            return [];
        }
        if ($attempt->status === 'submitted') {
            return array_values($this->questionPages($revision)->keys()->all());
        }

        return array_values(array_intersect(
            $this->questionPages($revision)->keys()->all(),
            $attempt->progress['submitted_pages'] ?? [],
        ));
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
            abort_if($attempt?->status === 'submitted' || in_array($data['page_id'], $this->submittedPages($attempt, $revision), true), 409);
            $pages = $this->questionPages($revision);
            if (! $pages->has($data['page_id'])) {
                throw ValidationException::withMessages(['page_id' => 'Tab tidak valid atau tidak memiliki soal pada versi Course ini.']);
            }
            $answers = $this->validateAnswers($pages->get($data['page_id']), $data['answers']);
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
            $questionIds = array_column($pages->get($data['page_id'])['questions'], 'id');
            $attempt->answers()->whereIn('question_key', $questionIds)->whereNotIn('question_key', array_keys($answers))->delete();
            $page = $pages->get($data['page_id']);
            $grading = app(LmsCourseGrading::class);
            $definitions = array_column($page['questions'], null, 'id');
            foreach ($answers as $questionId => $value) {
                $answer = $attempt->answers()->updateOrCreate(['question_key' => $questionId], ['answer' => ['value' => $value], 'answered_at' => now()]);
                if ($grading->hasWeights($page) && $definitions[$questionId]['type'] === 'multiple_choice') {
                    $answer->forceFill(['score' => $grading->automaticScore($definitions[$questionId], $value), 'graded_by' => null, 'graded_at' => now()])->save();
                }
            }
            $submittedPages = array_values(array_unique([
                ...$this->submittedPages($attempt, $revision), $data['page_id'],
            ]));
            $allQuestionPagesSubmitted = count(array_intersect($pages->keys()->all(), $submittedPages)) === $pages->count();
            $attempt->forceFill([
                'progress' => [...($attempt->progress ?? []), 'submitted_pages' => $submittedPages],
                'status' => $allQuestionPagesSubmitted ? 'submitted' : 'in_progress',
                'submitted_at' => $allQuestionPagesSubmitted ? now() : null,
            ])->save();
        });
    }

    /** @return Collection<string, array<string, mixed>> */
    private function questionPages(LmsCourseRevision $revision): Collection
    {
        return new Collection(app(LmsCourseGrading::class)->pages($revision));
    }

    /** @param array<string, mixed> $page
     * @param  array<string, mixed>  $answers
     * @return array<string, string>
     */
    private function validateAnswers(array $page, array $answers): array
    {
        $definitions = [];
        foreach ($page['questions'] ?? [] as $question) {
            $definitions[$question['id']] = $question;
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
