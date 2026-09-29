<?php

namespace Tests\Unit;

use App\Services\Assistant\AssistantOrchestrator;
use App\Services\Assistant\NineRouterClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantOrchestratorTest extends TestCase
{
    public function test_orchestrator_forwards_tool_result_and_returns_final_content(): void
    {
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-orchestrator-1',
                    'type' => 'function',
                    'function' => [
                        'name' => 'inventory_summary',
                        'arguments' => '{}',
                    ],
                ]],
            ]]]])
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => 'Ada 12 register.',
            ]]]]);

        $toolCalls = [];
        $orchestrator = new AssistantOrchestrator(new NineRouterClient);

        $content = $orchestrator->run(
            [['role' => 'user', 'content' => 'Berapa register?']],
            [],
            'request-orchestrator-test',
            function (string $name, array $arguments) use (&$toolCalls): array {
                $toolCalls[] = [$name, $arguments];

                return ['total_units' => 12];
            },
        );

        $this->assertSame('Ada 12 register.', $content);
        $this->assertSame([['inventory_summary', []]], $toolCalls);
        $this->assertCount(2, Http::recorded());
        $this->assertSame('tool', Http::recorded()[1][0]->data()['messages'][2]['role']);
    }
}
