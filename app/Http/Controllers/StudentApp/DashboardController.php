<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\StudentPlacement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;

        $placement = $student === null
            ? null
            : StudentPlacement::query()
                ->with('rombel:id,name,code')
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();

        return Inertia::render('student-app/dashboard', [
            'student' => $student === null ? null : array_merge(
                $student->only(['full_name', 'nis']),
                ['photo_url' => $this->photoUrl($student->photo_path)],
            ),
            'rombel' => $placement?->rombel?->name ?? $placement?->rombel?->code,
            'news' => Inertia::defer(fn (): array => $this->schoolNews()),
        ]);
    }

    private function photoUrl(?string $photoPath): ?string
    {
        return $photoPath === null
            ? null
            : Storage::disk('public')->url($photoPath);
    }

    /**
     * Retrieve and normalize school news without blocking the initial dashboard shell.
     *
     * @return array<int, array<string, mixed>>
     */
    private function schoolNews(): array
    {
        return Cache::flexible('student-app:school-news', [600, 1200], function (): array {
            try {
                $response = Http::timeout(5)->get('https://smpn17denpasar.sch.id/api/public/berita');

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json('data', []))
                    ->filter(fn ($post) => is_array($post) && isset($post['title'], $post['url']))
                    ->take(5)
                    ->map(function (array $post): array {
                        if (isset($post['imageUrl']) && is_string($post['imageUrl'])) {
                            $imageUrl = parse_url($post['imageUrl']);
                            parse_str($imageUrl['query'] ?? '', $query);

                            if (
                                ($imageUrl['host'] ?? null) === 'docs.google.com'
                                && ($imageUrl['path'] ?? null) === '/uc'
                                && isset($query['id'])
                                && is_string($query['id'])
                            ) {
                                $post['imageUrl'] = 'https://drive.google.com/thumbnail?id='
                                    . rawurlencode($query['id'])
                                    . '&sz=w1200';
                            }
                        }

                        return $post;
                    })
                    ->values()
                    ->all();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        });
    }
}
