<?php

namespace App\Services;

use App\Models\LmsCourse;
use App\Models\LmsCourseRevision;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LmsCourseLifecycle
{
    public function activate(User $user, LmsCourse $course, string $token): void
    {
        $this->transition($course, $token, function (LmsCourse $locked, LmsCourseRevision $revision) use ($user): void {
            Gate::forUser($user)->authorize('activate', $locked);
            $this->assertComplete($locked, $revision);
            $revision->forceFill(['status' => LmsCourseRevision::STATUS_APPROVED])->save();
            $locked->forceFill(['approved_revision_id' => $revision->id, 'title' => $revision->title])->save();
        });
    }

    public function revise(User $user, LmsCourse $course, string $token): void
    {
        $this->transition($course, $token, function (LmsCourse $locked, LmsCourseRevision $latest) use ($user): void {
            Gate::forUser($user)->authorize('revise', $locked);
            $locked->revisions()->create([
                'revision_number' => $latest->revision_number + 1,
                'title' => $latest->title,
                'description' => $latest->description,
                'status' => LmsCourseRevision::STATUS_DRAFT,
                'content_schema_version' => $latest->content_schema_version,
                'content' => $latest->content,
                'created_by' => $locked->created_by,
            ]);
        });
    }

    public function archive(User $user, LmsCourse $course, string $token): void
    {
        $this->transition($course, $token, function (LmsCourse $locked) use ($user): void {
            Gate::forUser($user)->authorize('archive', $locked);
            $locked->forceFill(['archived_by' => $user->id, 'archived_at' => now()])->save();
        });
    }

    public function restore(User $user, LmsCourse $course, string $token): void
    {
        $this->transition($course, $token, function (LmsCourse $locked, LmsCourseRevision $latest) use ($user): void {
            Gate::forUser($user)->authorize('restore', $locked);

            if ($latest->status === LmsCourseRevision::STATUS_APPROVED) {
                $locked->revisions()->create([
                    'revision_number' => $latest->revision_number + 1,
                    'title' => $latest->title,
                    'description' => $latest->description,
                    'status' => LmsCourseRevision::STATUS_DRAFT,
                    'content_schema_version' => $latest->content_schema_version,
                    'content' => $latest->content,
                    'created_by' => $locked->created_by,
                ]);
            }

            $locked->forceFill(['approved_revision_id' => null, 'archived_by' => null, 'archived_at' => null])->save();
        });
    }

    private function transition(LmsCourse $course, string $token, callable $callback): void
    {
        DB::transaction(function () use ($course, $token, $callback): void {
            $locked = LmsCourse::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $revision = $locked->revisions()->orderByDesc('revision_number')->lockForUpdate()->firstOrFail();
            $locked->setRelation('latestRevision', $revision);
            if (! hash_equals($revision->editToken($locked), $token)) {
                throw ValidationException::withMessages(['revision_id' => 'Course telah berubah. Muat ulang halaman sebelum melanjutkan.']);
            }
            $callback($locked, $revision);
            $locked->touch();
        });
    }

    private function assertComplete(LmsCourse $course, LmsCourseRevision $revision): void
    {
        $grading = app(LmsCourseGrading::class);
        $service = app(LmsCourseContent::class);
        $content = $service->normalize(['materials' => $revision->content['materials'] ?? []]);
        if (trim($revision->title) === '' || $content['materials'] === []) {
            throw ValidationException::withMessages(['course' => 'Isi judul dan minimal satu Materi sebelum mengaktifkan Course.']);
        }
        if (! Subject::query()->whereKey($course->subject_id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['course' => 'Mata pelajaran course tidak aktif.']);
        }
        foreach ($content['materials'] as $material) {
            foreach ([$material, ...($material['sections'] ?? [])] as $page) {
                if ($page['questions'] !== [] && ! $grading->hasWeights($page)) {
                    throw ValidationException::withMessages(['course' => 'Total bobot soal pada setiap Materi atau Bagian harus tepat 100. Periksa: '.$page['title'].'.']);
                }
                if (trim(html_entity_decode(strip_tags($page['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xc2\xa0") === '' && $page['media'] === [] && $page['questions'] === [] && ($page['sections'] ?? []) === []) {
                    throw ValidationException::withMessages(['course' => 'Setiap Materi dan Bagian harus berisi teks, media, atau soal.']);
                }
            }
        }
        foreach ($service->media($content) as $media) {
            if ($media['type'] === 'youtube') {
                continue;
            }
            $asset = $revision->content['assets'][$media['asset_id']] ?? null;
            if (! is_array($asset) || ($asset['type'] ?? null) !== $media['type'] || ! str_starts_with($asset['path'] ?? '', 'lms/courses/'.$course->id.'/') || ! Storage::disk('local')->exists($asset['path'])) {
                throw ValidationException::withMessages(['course' => 'Lampiran course tidak tersedia. Periksa materi sebelum melanjutkan.']);
            }
        }
    }
}
