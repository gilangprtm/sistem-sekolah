<?php

namespace Tests\Unit;

use App\Services\Assistant\AssistantConversationStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantConversationStoreTest extends TestCase
{
    public function test_it_creates_and_reads_a_user_bound_bounded_context(): void
    {
        config(['assistant.context_ttl' => 300, 'assistant.context_max_messages' => 4]);
        Cache::spy();
        $store = app(AssistantConversationStore::class);
        $session = (string) Str::uuid();
        $messages = [
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'two'],
            ['role' => 'user', 'content' => 'three'],
            ['role' => 'assistant', 'content' => 'four'],
            ['role' => 'user', 'content' => 'five'],
        ];

        $conversationId = $store->put(7, $session, $messages);
        $context = $store->get($conversationId, 7, $session);

        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $conversationId);
        $this->assertSame(array_slice($messages, -4), $context);
        Cache::shouldHaveReceived('put')->once();
    }

    public function test_it_does_not_return_a_context_for_another_user_or_session(): void
    {
        $store = app(AssistantConversationStore::class);
        $conversationId = $store->put(7, 'session-a', [['role' => 'user', 'content' => 'secret']]);

        $this->assertNull($store->get($conversationId, 8, 'session-a'));
        $this->assertNull($store->get($conversationId, 7, 'session-b'));
    }

    public function test_it_forgets_a_context(): void
    {
        $store = app(AssistantConversationStore::class);
        $conversationId = $store->put(7, 'session-a', [['role' => 'user', 'content' => 'secret']]);

        $store->forget($conversationId, 7, 'session-a');

        $this->assertNull($store->get($conversationId, 7, 'session-a'));
    }
}
