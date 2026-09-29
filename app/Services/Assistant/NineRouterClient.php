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
        $message = $result['choices'][0]['message'] ?? null;
        if (! is_array($result) || ! is_array($message) || ($message['role'] ?? null) !== 'assistant') {
            throw new RuntimeException('provider_malformed_response');
        }

        if (isset($message['tool_calls'])) {
            if (! is_array($message['tool_calls']) || ! array_is_list($message['tool_calls']) || $message['tool_calls'] === []) {
                throw new RuntimeException('provider_malformed_response');
            }

            foreach ($message['tool_calls'] as $call) {
                $function = is_array($call) ? ($call['function'] ?? null) : null;
                $arguments = is_array($function) ? ($function['arguments'] ?? null) : null;
                if (! is_array($call)
                    || ! is_string($call['id'] ?? null)
                    || $call['id'] === ''
                    || ($call['type'] ?? null) !== 'function'
                    || ! is_array($function)
                    || ! is_string($function['name'] ?? null)
                    || $function['name'] === ''
                    || ! is_string($arguments)
                    || ! $this->isJsonObject($arguments)) {
                    throw new RuntimeException('provider_malformed_response');
                }
            }
        }

        return $result;
    }

    private function isJsonObject(string $value): bool
    {
        try {
            $decoded = json_decode($value, false, 512, JSON_THROW_ON_ERROR);

            return is_object($decoded);
        } catch (\JsonException) {
            return false;
        }
    }
}
