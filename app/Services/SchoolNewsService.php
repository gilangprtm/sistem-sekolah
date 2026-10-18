<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SchoolNewsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        return Cache::flexible('school-news', [600, 1200], function (): array {
            try {
                $response = Http::timeout(5)->get('https://smpn17denpasar.sch.id/api/public/berita');

                if (! $response->successful()) {
                    return [];
                }

                /** @var array<int, array<string, mixed>> $posts */
                $posts = $response->json('data', []);

                return collect($posts)
                    ->filter(fn (array $post): bool => isset($post['title'], $post['url']))
                    ->take(5)
                    ->map(fn (array $post): array => $this->normalize($post))
                    ->values()
                    ->all();
            } catch (Throwable $exception) {
                report($exception);

                return [];
            }
        });
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function normalize(array $post): array
    {
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
                    .rawurlencode($query['id'])
                    .'&sz=w1200';
            }
        }

        if (isset($post['publishedAt']) && is_string($post['publishedAt'])) {
            try {
                $post['publishedAt'] = Carbon::parse($post['publishedAt'])
                    ->utc()
                    ->toRfc3339String();
            } catch (Throwable) {
                $post['publishedAt'] = '';
            }
        }

        return $post;
    }
}
