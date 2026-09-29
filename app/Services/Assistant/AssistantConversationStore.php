<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AssistantConversationStore
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function put(int|string $userId, string $sessionId, array $messages): string
    {
        $conversationId = (string) Str::uuid();
        $this->save($conversationId, $userId, $sessionId, $messages);

        return $conversationId;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function save(string $conversationId, int|string $userId, string $sessionId, array $messages): void
    {
        if (! Str::isUuid($conversationId)) {
            return;
        }

        Cache::put($this->key($conversationId), [
            'user_id' => (string) $userId,
            'session_id' => $sessionId,
            'messages' => array_slice($messages, -$this->maxMessages()),
        ], $this->ttl());
    }

    /**
     * @return array<int, array{role: string, content: string}>|null
     */
    public function get(string $conversationId, int|string $userId, string $sessionId): ?array
    {
        if (! Str::isUuid($conversationId)) {
            return null;
        }

        $context = Cache::get($this->key($conversationId));

        if (! is_array($context)
            || (string) ($context['user_id'] ?? '') !== (string) $userId
            || ($context['session_id'] ?? null) !== $sessionId
            || ! is_array($context['messages'] ?? null)) {
            return null;
        }

        return $context['messages'];
    }

    public function forget(string $conversationId, int|string $userId, string $sessionId): void
    {
        if ($this->get($conversationId, $userId, $sessionId) !== null) {
            Cache::forget($this->key($conversationId));
        }
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addSeconds(max((int) config('assistant.context_ttl', 900), 1));
    }

    private function maxMessages(): int
    {
        return max((int) config('assistant.context_max_messages', 12), 2);
    }

    private function key(string $conversationId): string
    {
        return 'assistant:conversation:'.$conversationId;
    }
}
