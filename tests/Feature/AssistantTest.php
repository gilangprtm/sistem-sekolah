<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'services.assistant.enabled' => true,
            'services.assistant.plain_mode' => false,
            'services.assistant.system_prompt_only' => false,
        ]);
    }

    public function test_assistant_requires_authentication(): void
    {
        $this->postJson('/api/v1/assistant/chat', ['message' => 'Halo'])
            ->assertUnauthorized();
    }

    public function test_web_session_authenticates_assistant_request(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]],
        ])]);

        $this->actingAs($user)
            ->postJson('/api/v1/assistant/chat', ['message' => 'Halo'])
            ->assertOk()
            ->assertJsonPath('message', 'Siap.');
    }

    public function test_plain_mode_forwards_only_chat_messages(): void
    {
        config(['services.assistant.plain_mode' => true]);
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hi.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'message' => 'hi',
                'history' => [['role' => 'user', 'content' => 'sebelumnya']],
            ])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            return ! array_key_exists('tools', $request->data())
                && ! in_array(['role' => 'system', 'content' => 'Kamu adalah asisten dari sistem sekolah yang akan menjawab pertanyaan dan berbicara dengan bahasa indonesia yang sopan. Gunakan tools yang tersedia dan jangan mengarang data'], $request->data()['messages'], true)
                && $request->data()['messages'][0]['content'] === 'sebelumnya'
                && $request->data()['messages'][1]['content'] === 'hi';
        });
    }

    public function test_inventory_permission_scopes_provider_tools(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Halo'])
            ->assertOk()
            ->assertJsonPath('message', 'Siap.');

        Http::assertSent(function ($request): bool {
            $tools = $request->data()['tools'] ?? [];
            $names = array_map(fn (array $tool) => $tool['function']['name'], $tools);

            return in_array('inventory_summary', $names, true)
                && in_array('inventory_search', $names, true);
        });
    }

    public function test_user_without_inventory_permission_receives_no_inventory_tools(): void
    {
        $user = User::factory()->create();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Saya siap membantu.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Tampilkan inventaris'])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            return ! array_key_exists('tools', $request->data());
        });
    }

    public function test_tool_call_is_executed_and_followed_by_final_answer(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['kode_barang' => 'A.01.01', 'nama_jenis_barang' => 'Kursi']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-1',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_search', 'arguments' => '{"query":"Kursi"}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ditemukan 1 item.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari kursi'])
            ->assertOk()
            ->assertJsonPath('message', 'Ditemukan 1 item.');

        $this->assertCount(2, Http::recorded());
        $this->assertSame('tool', Http::recorded()[1][0]->data()['messages'][3]['role']);
    }

    public function test_unknown_tool_fails_closed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-unknown',
                'type' => 'function',
                'function' => ['name' => 'delete_everything', 'arguments' => '{}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Hapus semua'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Assistant gagal memproses permintaan.')
            ->assertJsonPath('error_code', 'assistant_execution_failed')
            ->assertJsonStructure(['request_id']);
    }
}
