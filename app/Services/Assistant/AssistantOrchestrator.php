<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class AssistantOrchestrator
{
    private int $attempt = 0;

    public function __construct(private readonly NineRouterClient $client) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $executeTool
     */
    public function run(array $messages, array $tools, string $requestId, callable $executeTool): string
    {
        $startedAt = microtime(true);

        try {
            return $this->runInternal($messages, $tools, $requestId, $executeTool, $startedAt);
        } catch (AssistantOrchestrationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AssistantOrchestrationException(
                $exception->getMessage() ?: 'assistant_execution_failed',
                $this->attempt,
                (int) ((microtime(true) - $startedAt) * 1000),
                $exception,
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $executeTool
     */
    private function runInternal(array $messages, array $tools, string $requestId, callable $executeTool, float $startedAt): string
    {
        $conversation = $messages;
        $seenToolSignatures = [];
        $lastToolSignature = null;
        $recoveryAttempted = false;
        $this->attempt = 0;
        $activeTools = $tools;
        $toolRoundCount = 0;
        $maxToolRounds = 5;
        $paginationStates = [];
        $selfCorrectionAttempted = false;
        $selfCorrectionSignature = null;

        while (true) {
            if (microtime(true) - $startedAt > 120) {
                throw new RuntimeException('assistant_execution_timeout');
            }
            if ($toolRoundCount >= $maxToolRounds) {
                throw new RuntimeException('assistant_tool_round_limit');
            }

            $this->attempt++;
            $result = $this->client->complete($conversation, $activeTools);
            $message = $result['choices'][0]['message'] ?? [];
            Log::debug('Assistant provider response observed', [
                'request_id' => $requestId,
                'attempt' => $this->attempt,
                'message_role' => $message['role'] ?? null,
                'has_content' => trim((string) ($message['content'] ?? '')) !== '',
                'tool_call_count' => is_array($message['tool_calls'] ?? null) ? count($message['tool_calls']) : 0,
            ]);
            if ($recoveryAttempted && ! empty($message['tool_calls'])) {
                throw new RuntimeException('assistant_repeated_tool_call');
            }

            $conversation[] = $message;

            if (empty($message['tool_calls'])) {
                return trim((string) ($message['content'] ?? ''));
            }

            $toolRoundCount++;

            foreach ($message['tool_calls'] as $call) {
                $signature = hash('sha256', json_encode([
                    $call['function']['name'] ?? '',
                    $this->canonicalToolArguments($call['function']['arguments'] ?? '{}'),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                Log::debug('Assistant tool call observed', [
                    'request_id' => $requestId,
                    'attempt' => $this->attempt,
                    'tool_name' => $call['function']['name'] ?? null,
                    'tool_call_id_present' => isset($call['id']),
                    'arguments_hash' => $signature,
                    'repeated' => isset($seenToolSignatures[$signature]),
                ]);

                if (isset($seenToolSignatures[$signature])) {
                    if ($signature === $selfCorrectionSignature) {
                        throw new \InvalidArgumentException('Invalid assistant resource arguments.');
                    }
                    if ($signature !== $lastToolSignature) {
                        throw new RuntimeException('assistant_repeated_tool_call');
                    }

                    $recoveryAttempted = true;
                    $activeTools = [];
                    array_pop($conversation);

                    continue 2;
                }
                $seenToolSignatures[$signature] = true;
                $lastToolSignature = $signature;

                $name = $call['function']['name'] ?? '';
                $arguments = json_decode($call['function']['arguments'] ?? '{}', true);
                $arguments = is_array($arguments) ? $arguments : [];
                $paginationKey = $this->paginationStateKey($name, $arguments);
                $requestedPage = $this->paginationPage($arguments);
                $knownPagination = $paginationStates[$paginationKey] ?? null;
                if ($knownPagination !== null && $requestedPage >= 1) {
                    if ($requestedPage > $knownPagination['last_page']) {
                        throw new RuntimeException('assistant_pagination_out_of_bounds');
                    }
                    if ($requestedPage !== $knownPagination['current_page'] + 1) {
                        throw new RuntimeException('assistant_pagination_invalid_sequence');
                    }
                }

                try {
                    $toolResult = $executeTool($name, $arguments);
                } catch (\InvalidArgumentException $exception) {
                    if ($selfCorrectionAttempted || ! $this->isResourceTool($name)) {
                        throw $exception;
                    }

                    $selfCorrectionAttempted = true;
                    $selfCorrectionSignature = $signature;
                    $toolResult = ['error' => 'invalid_arguments'];
                    Log::debug('Assistant tool argument correction requested', [
                        'request_id' => $requestId,
                        'attempt' => $this->attempt,
                        'tool_name' => $name,
                        'tool_call_id_present' => isset($call['id']),
                    ]);
                }
                $pagination = $this->paginationMetadata($toolResult);
                if ($pagination !== null) {
                    $paginationStates[$paginationKey] = $pagination;
                }
                Log::debug('Assistant tool result observed', [
                    'request_id' => $requestId,
                    'attempt' => $this->attempt,
                    'tool_name' => $name,
                    'result_keys' => array_keys($toolResult),
                    'numeric_summary' => $this->compactToolResultSummary($toolResult),
                ]);
                $conversation[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $result
     * @return array<string, int>
     */
    private function compactToolResultSummary(array $result): array
    {
        $summary = [];
        foreach (['total_items', 'total_units', 'total_rooms', 'rooms_with_units', 'assigned_units'] as $key) {
            if (isset($result[$key]) && is_int($result[$key])) {
                $summary[$key] = $result[$key];
            }
        }

        foreach (['data', 'rooms'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                $summary[$key.'_count'] = count($result[$key]);
            }
        }

        if (isset($result['pagination']) && is_array($result['pagination'])) {
            foreach (['total', 'page', 'per_page', 'last_page'] as $key) {
                if (isset($result['pagination'][$key]) && is_int($result['pagination'][$key])) {
                    $summary['pagination_'.$key] = $result['pagination'][$key];
                }
            }
        }

        return $summary;
    }

    private function isResourceTool(string $name): bool
    {
        return in_array($name, [
            'inventory_items',
            'inventory_registers',
            'inventory_rooms',
            'inventory_categories',
            'teacher_subjects',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function paginationStateKey(string $name, array $arguments): string
    {
        unset($arguments['page']);

        return $name.':'.$this->canonicalToolArguments($arguments);
    }

    /** @param array<string, mixed> $arguments */
    private function paginationPage(array $arguments): int
    {
        $page = $arguments['page'] ?? 1;

        return is_int($page) ? $page : (int) $page;
    }

    /**
     * @param  array<int|string, mixed>  $result
     * @return array{current_page: int, last_page: int}|null
     */
    private function paginationMetadata(array $result): ?array
    {
        $metadata = $result['meta'] ?? $result['pagination'] ?? null;
        if (! is_array($metadata)
            || ! is_int($metadata['current_page'] ?? null)
            || ! is_int($metadata['last_page'] ?? null)) {
            return null;
        }

        return [
            'current_page' => $metadata['current_page'],
            'last_page' => $metadata['last_page'],
        ];
    }

    private function canonicalToolArguments(mixed $arguments): string
    {
        $decoded = is_string($arguments) ? json_decode($arguments, true) : $arguments;

        if (! is_array($decoded)) {
            return (string) ($arguments ?? '{}');
        }

        $sortKeys = function (&$value) use (&$sortKeys): void {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as &$child) {
                $sortKeys($child);
            }
            unset($child);

            if (! array_is_list($value)) {
                ksort($value);
            }
        };

        $sortKeys($decoded);

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
