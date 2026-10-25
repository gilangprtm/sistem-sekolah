<?php

namespace App\Services;

use App\Models\LmsCourse;
use App\Models\LmsCourseAttempt;
use App\Models\LmsCourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class LmsCourseGrading
{
    /** @param array<string, mixed> $page */
    public function hasWeights(array $page): bool
    {
        $total = 0;
        foreach ($page['questions'] ?? [] as $question) {
            $points = $question['points'] ?? null;
            if (! is_numeric($points) || ! is_finite((float) $points) || (float) $points <= 0 || (float) $points > 100) {
                return false;
            }
            $total += (int) round((float) $points * 100);
        }

        return $total === 10000;
    }

    /** @param array<string, mixed> $data */
    public function grade(User $user, LmsCourse $course, array $data, LmsCourseResponses $responses): void
    {
        Gate::forUser($user)->authorize('grade', $course);
        DB::transaction(function () use ($user, $course, $data, $responses): void {
            $locked = LmsCourse::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($user)->authorize('grade', $locked);
            if (isset($data['revision_id']) && (int) $data['revision_id'] !== $locked->approved_revision_id) {
                throw ValidationException::withMessages(['grading' => 'Revisi Course telah berubah. Riwayat penilaian hanya dapat dibaca.']);
            }
            $context = $responses->teacherContext($user, $locked, (int) $data['rombel_id'], (int) $data['student_id']);
            $revision = $context['revision'];
            $attempt = $context['attempt'];
            $submittedPages = $responses->submittedPages($attempt, $revision);
            $page = $this->pages($revision)[$data['page_id']] ?? null;
            if ($page === null || ! $this->hasWeights($page) || ! in_array($data['page_id'], $submittedPages, true)) {
                throw ValidationException::withMessages(['grading' => 'Tab belum dikumpulkan atau bobot belum diatur.']);
            }
            $result = $this->results($attempt, $revision, $submittedPages)[$data['page_id']];
            if (! hash_equals($result['token'], $data['grading_token'])) {
                throw ValidationException::withMessages(['grading' => 'Penilaian telah berubah. Muat ulang sebelum menyimpan.']);
            }
            $definitions = array_column($page['questions'], null, 'id');
            $scores = [];
            foreach ($data['scores'] as $id => $score) {
                $question = $definitions[$id] ?? null;
                if ($question === null || $question['type'] !== 'essay' || (float) $score > (float) $question['points']) {
                    throw ValidationException::withMessages(['grading' => 'Nilai uraian harus sesuai soal pada tab ini, antara 0 dan nilai maksimalnya.']);
                }
                $scores[$id] = $score;
            }
            foreach ($scores as $id => $score) {
                $answer = $attempt->answers()->firstOrNew(['question_key' => $id]);
                $answer->forceFill([
                    'answer' => $answer->answer ?? ['value' => ''], 'score' => $score,
                    'graded_by' => $user->id, 'graded_at' => now(),
                ])->save();
            }
        });
    }

    /** @param array<string, mixed> $question */
    public function automaticScore(array $question, ?string $value): float
    {
        $correctId = null;
        foreach ($question['options'] ?? [] as $option) {
            if ((bool) ($option['is_correct'] ?? false)) {
                $correctId = $option['id'] ?? null;
                break;
            }
        }

        return $value !== null && $value !== '' && $value === $correctId ? (float) $question['points'] : 0.0;
    }

    /** @return array<string, array<string, mixed>> */
    public function pages(LmsCourseRevision $revision): array
    {
        $pages = [];
        foreach ($revision->content['materials'] ?? [] as $material) {
            foreach ([$material, ...($material['sections'] ?? [])] as $page) {
                if (($page['questions'] ?? []) !== []) {
                    $pages[$page['id']] = $page;
                }
            }
        }

        return $pages;
    }

    /** @param list<string> $submittedPages
     * @return array<string, array{weights_set: bool, submitted: bool, score: float|null, ungraded_essays: int, questions: array<string, array{maximum: float|null, score: float|null}>, token: string}>
     */
    public function results(?LmsCourseAttempt $attempt, LmsCourseRevision $revision, array $submittedPages): array
    {
        $answers = $attempt?->loadMissing('answers')->answers->keyBy('question_key') ?? collect();
        $results = [];
        foreach ($this->pages($revision) as $pageId => $page) {
            $weightsSet = $this->hasWeights($page);
            $submitted = in_array($pageId, $submittedPages, true);
            $questions = [];
            $ungraded = 0;
            $total = 0;
            $trace = [];
            foreach ($page['questions'] as $question) {
                $answer = $answers->get($question['id']);
                $maximum = $weightsSet ? (float) $question['points'] : null;
                $score = null;
                if ($submitted && $weightsSet) {
                    if ($question['type'] === 'multiple_choice') {
                        $score = $this->automaticScore($question, $answer?->answer['value'] ?? null);
                    } elseif ($answer?->graded_at !== null && $answer->score !== null) {
                        $score = (float) $answer->score;
                    } else {
                        $ungraded++;
                    }
                }
                $total += (int) round(($score ?? 0) * 100);
                $questions[$question['id']] = ['maximum' => $maximum, 'score' => $score];
                $isGradedEssay = $question['type'] === 'essay' && $answer?->graded_at !== null && $answer->score !== null;
                $trace[] = [$question['id'], $answer?->answer, $isGradedEssay ? $answer->score : null, $isGradedEssay ? $answer->graded_by : null, $isGradedEssay ? $answer->graded_at->format('Y-m-d H:i:s.u') : null, $isGradedEssay ? $answer->updated_at?->format('Y-m-d H:i:s.u') : null];
            }
            $results[$pageId] = [
                'weights_set' => $weightsSet, 'submitted' => $submitted,
                'score' => $weightsSet && $submitted && $ungraded === 0 ? $total / 100 : null,
                'ungraded_essays' => $ungraded, 'questions' => $questions,
                'token' => hash('sha256', json_encode([$attempt?->id, $revision->id, $page, $submitted, $trace], JSON_THROW_ON_ERROR)),
            ];
        }

        return $results;
    }
}
