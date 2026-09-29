<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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
                && in_array('inventory_search', $names, true)
                && in_array('inventory_register_query', $names, true);
        });
    }

    public function test_register_provider_schema_is_allowlisted_and_empty_schema_properties_are_objects(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar register'])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body());
            $tools = collect($payload->tools ?? [])->keyBy('function.name');
            $registerSchema = $tools->get('inventory_register_query')->function->parameters ?? null;
            $summarySchema = $tools->get('inventory_summary')->function->parameters ?? null;

            return $summarySchema?->properties instanceof \stdClass
                && $registerSchema?->additionalProperties === false
                && $registerSchema?->properties->fields->items->enum !== []
                && ! in_array('sql', $registerSchema?->properties->fields->items->enum ?? [], true)
                && $registerSchema?->properties->per_page->maximum === 50;
        });
    }

    public function test_empty_provider_response_uses_polite_capability_fallback(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Apa kondisi register?'])
            ->assertOk()
            ->assertJsonPath('message', 'Maaf, saya belum dapat memberikan jawaban yang terverifikasi. Saya dapat membantu ringkasan inventaris, pencarian aset, query aset, dan query register/unit sesuai permission akun Anda.');
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

    public function test_register_tool_execution_rechecks_inventory_permission(): void
    {
        $user = User::factory()->create();

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-register-forbidden',
                'type' => 'function',
                'function' => ['name' => 'inventory_register_query', 'arguments' => '{}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Buka register'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_register_tool_call_is_read_only_and_returns_backend_display_code(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $item = InventoryItem::factory()->create(['kode_barang' => '28.09.2025', 'tahun_pembelian' => 2025]);
        $item->units()->create(['register' => '002', 'condition' => 'RB']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-register-1',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_register_query', 'arguments' => json_encode([
                        'fields' => ['display_code', 'condition', 'item.kode_barang'],
                        'filters' => ['condition' => 'RB', 'tahun_pembelian' => 2025],
                        'sort' => ['field' => 'register', 'direction' => 'asc'],
                        'page' => 1,
                        'per_page' => 10,
                    ])],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Register ditemukan.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari register rusak berat'])
            ->assertOk()
            ->assertJsonPath('message', 'Register ditemukan.');

        $toolMessage = Http::recorded()[1][0]->data()['messages'][3];
        $toolResult = json_decode($toolMessage['content'], true);
        $this->assertSame('tool', $toolMessage['role']);
        $this->assertSame('28.09.2025.002', $toolResult['data'][0]['display_code']);
        $this->assertSame('RB', $toolResult['data'][0]['condition']);
        $this->assertSame('28.09.2025', $toolResult['data'][0]['item']['kode_barang']);
        $this->assertSame(1, $toolResult['pagination']['total']);
    }

    public static function invalidRegisterArguments(): array
    {
        return [
            'field' => [['fields' => ['password']]],
            'filter key' => [['filters' => ['secret' => 'x']]],
            'asset kind' => [['filters' => ['asset_kind' => 'unknown']]],
            'sort field' => [['sort' => ['field' => 'secret', 'direction' => 'asc']]],
            'sort direction' => [['sort' => ['field' => 'register', 'direction' => 'sideways']]],
            'page type' => [['page' => 'first']],
            'page negative' => [['page' => -1]],
            'per page type' => [['per_page' => 'many']],
            'per page 51' => [['per_page' => 51]],
            'per page 100' => [['per_page' => 100]],
        ];
    }

    #[DataProvider('invalidRegisterArguments')]
    public function test_register_tool_rejects_invalid_contract_arguments(array $arguments): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-register-invalid',
                'type' => 'function',
                'function' => ['name' => 'inventory_register_query', 'arguments' => json_encode($arguments)],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Buka data register'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_register_tool_ignores_provider_placeholder_filters_for_condition_counts(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $item = InventoryItem::factory()->create();
        $item->units()->createMany([
            ['register' => '001', 'condition' => 'B'],
            ['register' => '002', 'condition' => 'KB'],
        ]);

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-register-defaults',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_register_query', 'arguments' => json_encode([
                        'fields' => ['id'],
                        'filters' => [
                            'search' => '',
                            'register' => '',
                            'condition' => 'B',
                            'inventory_item_id' => 0,
                            'kode_barang' => '',
                            'tahun_pembelian' => 0,
                            'asal_perolehan' => '',
                            'satuan' => '',
                            'inventory_category_id' => 0,
                            'inventory_type_id' => 0,
                            'asset_kind' => 'tangible',
                        ],
                        'sort' => ['field' => 'id', 'direction' => 'asc'],
                        'page' => 1,
                        'per_page' => 1,
                    ])],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ditemukan 1 register B.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Berapa register kondisi B?'])
            ->assertOk()
            ->assertJsonPath('message', 'Ditemukan 1 register B.');

        $toolMessage = Http::recorded()[1][0]->data()['messages'][3];
        $toolResult = json_decode($toolMessage['content'], true);
        $this->assertSame(1, $toolResult['pagination']['total']);
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
