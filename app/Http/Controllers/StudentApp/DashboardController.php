<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\StudentPlacement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        $news = Cache::remember('student-app:school-news', now()->addMinutes(10), function (): array {
            try {
                $response = Http::timeout(5)->get('https://smpn17denpasar.sch.id/api/public/berita');

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json('data', []))
                    ->filter(fn ($post) => is_array($post) && isset($post['title'], $post['url']))
                    ->take(5)
                    ->values()
                    ->all();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        });

        return Inertia::render('student-app/dashboard', [
            'student' => $student?->only(['full_name', 'nis']),
            'rombel' => $placement?->rombel?->name ?? $placement?->rombel?->code,
            'news' => $news,
        ]);
    }
}
