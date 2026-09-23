<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NineRouterClient
{
    public function complete(array $messages, array $tools = []): array
    {
        $baseUrl = rtrim((string) config('services.ninerouter.base_url'), '/');
        $apiKey = (string) config('services.ninerouter.api_key');
        $model = (string) config('services.ninerouter.model');

        if (! $baseUrl || ! $apiKey || ! $model) {
            throw new RuntimeException('Assistant provider is not configured.');
        }

        $payload = ['model' => $model, 'messages' => $messages, 'stream' => false];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout((int) config('services.ninerouter.connect_timeout', 5))
            ->timeout((int) config('services.ninerouter.request_timeout', 60))
            ->post($baseUrl.'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Assistant provider request failed.');
        }

        return $response->json();
    }
}
