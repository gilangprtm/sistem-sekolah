<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class NineRouterClient
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function complete(array $messages, array $tools = []): array
    {
        $baseUrl = rtrim((string) config('services.ninerouter.base_url'), '/');
        $apiKey = (string) config('services.ninerouter.api_key');
        $model = (string) config('services.ninerouter.model');

        if (! $baseUrl || ! $apiKey || ! $model) {
            throw new RuntimeException('provider_configuration');
        }

        $payload = ['model' => $model, 'messages' => $messages, 'stream' => false];
        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.ninerouter.connect_timeout', 5))
                ->timeout((int) config('services.ninerouter.request_timeout', 60))
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('provider_transport', 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('provider_http_'.$response->status());
        }

        $result = $response->json();
        if (! is_array($result) || ! isset($result['choices'][0]['message'])) {
            throw new RuntimeException('provider_malformed_response');
        }

        return $result;
    }
}
