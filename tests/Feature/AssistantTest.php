<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryRoom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            ->assertJsonPath('message', 'Siap.')
            ->assertJsonStructure(['conversation_id']);
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
        $this->test_inventory_registry_exposes_only_resource_tools();
    }

    public function test_inventory_registry_exposes_only_resource_tools(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar inventaris'])
            ->assertOk();

        Http::assertSent(function ($request): bool {
            $names = collect(json_decode($request->body())->tools ?? [])
                ->map(fn (object $tool): string => $tool->function->name)
                ->all();

            return $names === ['curriculum_class_query', 'inventory_items', 'inventory_registers', 'inventory_rooms', 'inventory_categories', 'teacher_subjects', 'kantin_catalog_query'];
        });
    }

    public function test_legacy_inventory_tool_is_not_executable(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-legacy-tool',
                'type' => 'function',
                'function' => ['name' => 'inventory_summary', 'arguments' => '{}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Ringkas inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_inventory_items_resource_returns_api_like_paginated_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $item = InventoryItem::factory()->create([
            'kode_barang' => '28.09.2026',
            'nama_jenis_barang' => 'Laptop',
        ]);
        $item->units()->create(['register' => '001', 'condition' => 'B']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-inventory-items',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => json_encode([
                    'search' => 'Laptop',
                    'page' => 1,
                    'per_page' => 25,
                ])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Laptop tersedia.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari laptop'])
            ->assertOk()
            ->assertJsonPath('message', 'Laptop tersedia.');

        $result = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame('28.09.2026', $result['data'][0]['code']);
        $this->assertSame('Laptop', $result['data'][0]['name']);
        $this->assertSame(1, $result['data'][0]['register_count']);
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1], $result['meta']);
    }

    public function test_inventory_registers_resource_returns_api_like_paginated_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $item = InventoryItem::factory()->create(['kode_barang' => '28.09.2026', 'nama_jenis_barang' => 'Laptop']);
        $unit = $item->units()->create(['register' => '001', 'condition' => 'RB']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-inventory-registers',
                'type' => 'function',
                'function' => ['name' => 'inventory_registers', 'arguments' => json_encode([
                    'condition' => 'RB',
                    'page' => 1,
                    'per_page' => 25,
                ])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Register ditemukan.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari register rusak'])
            ->assertOk()
            ->assertJsonPath('message', 'Register ditemukan.');

        $result = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame($unit->id, $result['data'][0]['id']);
        $this->assertSame('28.09.2026.001', $result['data'][0]['display_code']);
        $this->assertSame('Laptop', $result['data'][0]['item']['name']);
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1], $result['meta']);
    }

    public function test_inventory_categories_resource_returns_api_like_paginated_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $category = Category::query()->create(['name' => 'Elektronik']);
        InventoryItem::factory()->create(['inventory_category_id' => $category->id]);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-inventory-categories',
                'type' => 'function',
                'function' => ['name' => 'inventory_categories', 'arguments' => '{}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Kategori tersedia.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar kategori'])
            ->assertOk()
            ->assertJsonPath('message', 'Kategori tersedia.');

        $result = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame('Elektronik', $result['data'][0]['name']);
        $this->assertSame(1, $result['data'][0]['item_count']);
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1], $result['meta']);
    }

    public function test_paginated_resource_tools_complete_pages_with_matching_tool_results(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        $scenarios = [
            ['name' => 'inventory_items', 'search' => 'slice-item', 'create' => function (): void {
                InventoryItem::factory()->create(['nama_jenis_barang' => 'slice-item-1']);
                InventoryItem::factory()->create(['nama_jenis_barang' => 'slice-item-2']);
            }],
            ['name' => 'inventory_registers', 'search' => 'slice-register', 'create' => function (): void {
                $item = InventoryItem::factory()->create(['kode_barang' => 'SLICE.REG']);
                $item->units()->createMany([
                    ['register' => 'slice-register-1', 'condition' => 'B'],
                    ['register' => 'slice-register-2', 'condition' => 'B'],
                ]);
            }],
            ['name' => 'inventory_rooms', 'search' => 'slice-room', 'create' => function (): void {
                InventoryRoom::query()->create(['name' => 'slice-room-1', 'code' => 'SR1']);
                InventoryRoom::query()->create(['name' => 'slice-room-2', 'code' => 'SR2']);
            }],
            ['name' => 'inventory_categories', 'search' => 'slice-category', 'create' => function (): void {
                Category::query()->create(['name' => 'slice-category-1']);
                Category::query()->create(['name' => 'slice-category-2']);
            }],
        ];

        foreach ($scenarios as $scenario) {
            $scenario['create']();
        }

        $sequence = Http::fakeSequence();
        foreach ($scenarios as $scenario) {
            foreach ([1, 2] as $page) {
                $sequence->push(['choices' => [['message' => [
                    'role' => 'assistant',
                    'tool_calls' => [[
                        'id' => $scenario['name'].'-page-'.$page,
                        'type' => 'function',
                        'function' => [
                            'name' => $scenario['name'],
                            'arguments' => json_encode(['search' => $scenario['search'], 'page' => $page, 'per_page' => 1]),
                        ],
                    ]],
                ]]]]);
            }
            $sequence->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => $scenario['name'].' complete.',
            ]]]]);
        }

        foreach ($scenarios as $index => $scenario) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/assistant/chat', ['message' => 'Complete '.$scenario['name']])
                ->assertOk()
                ->assertJsonPath('message', $scenario['name'].' complete.');

            $records = Http::recorded();
            $this->assertCount(($index + 1) * 3, $records);
            $firstPagePayload = $records[$index * 3 + 1][0]->data()['messages'];
            $secondPagePayload = $records[$index * 3 + 2][0]->data()['messages'];
            $firstCall = $firstPagePayload[count($firstPagePayload) - 2]['tool_calls'][0];
            $firstResult = $firstPagePayload[count($firstPagePayload) - 1];
            $secondCall = $secondPagePayload[count($secondPagePayload) - 2]['tool_calls'][0];
            $secondResult = $secondPagePayload[count($secondPagePayload) - 1];
            $firstResultData = json_decode($firstResult['content'], true);
            $secondResultData = json_decode($secondResult['content'], true);

            $this->assertSame($scenario['name'].'-page-1', $firstCall['id']);
            $this->assertSame(1, json_decode($firstCall['function']['arguments'], true)['page']);
            $this->assertSame($firstCall['id'], $firstResult['tool_call_id']);
            $this->assertSame(1, $firstResultData['meta']['current_page']);
            $this->assertSame(2, $firstResultData['meta']['last_page']);
            $this->assertSame($scenario['name'].'-page-2', $secondCall['id']);
            $this->assertSame(2, json_decode($secondCall['function']['arguments'], true)['page']);
            $this->assertSame($secondCall['id'], $secondResult['tool_call_id']);
            $this->assertSame(2, $secondResultData['meta']['current_page']);
            $this->assertSame(2, $secondResultData['meta']['last_page']);
        }
    }

    public function test_paginated_resource_rejects_page_beyond_last_page_without_extra_execution(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['nama_jenis_barang' => 'slice-bound-1']);
        InventoryItem::factory()->create(['nama_jenis_barang' => 'slice-bound-2']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-bound-page-1',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"slice-bound","page":1,"per_page":1}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-bound-page-2',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"slice-bound","page":2,"per_page":1}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-bound-page-3',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"slice-bound","page":3,"per_page":1}'],
            ]]]]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Loop halaman inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_pagination_out_of_bounds');

        $this->assertCount(3, Http::recorded());
        $thirdPayload = Http::recorded()[2][0]->data()['messages'];
        $this->assertCount(6, $thirdPayload);
        $this->assertSame('call-bound-page-2', $thirdPayload[5]['tool_call_id']);
    }

    public function test_resource_tools_reject_unknown_arguments(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-resource-unknown',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"exclude_room_name":"VII A"}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
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
            $itemSchema = $tools->get('inventory_items')->function->parameters ?? null;
            $registerSchema = $tools->get('inventory_registers')->function->parameters ?? null;
            $roomSchema = $tools->get('inventory_rooms')->function->parameters ?? null;
            $categorySchema = $tools->get('inventory_categories')->function->parameters ?? null;

            return $itemSchema?->additionalProperties === false
                && $itemSchema?->properties->per_page->maximum === 50
                && $registerSchema?->additionalProperties === false
                && $registerSchema?->properties->condition->enum === ['B', 'KB', 'RB', null]
                && $registerSchema?->properties->per_page->maximum === 50
                && $roomSchema?->additionalProperties === false
                && $roomSchema?->properties->search->type === ['string', 'null']
                && $roomSchema?->properties->per_page->maximum === 50
                && $categorySchema?->additionalProperties === false
                && $categorySchema?->properties->per_page->maximum === 50;
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
            ->assertJsonPath('message', 'Maaf, saya belum dapat memberikan jawaban yang terverifikasi. Saya dapat membantu membaca resource inventaris yang diizinkan untuk akun Anda.');
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
            $tools = $request->data()['tools'] ?? [];

            return count($tools) === 3
                && $tools[0]['function']['name'] === 'curriculum_class_query'
                && $tools[1]['function']['name'] === 'teacher_subjects'
                && $tools[2]['function']['name'] === 'kantin_catalog_query';
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
                'function' => ['name' => 'inventory_registers', 'arguments' => '{}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Buka register'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_register_tool_call_is_read_only_and_returns_backend_display_code(): void
    {
        $this->test_inventory_registers_resource_returns_api_like_paginated_data();
    }

    public function test_tool_rounds_stop_at_deterministic_limit_and_trace_compact_result(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-round-1',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-round-2',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"round-2"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-round-3',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"round-3"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-round-4',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"round-4"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-round-5',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"round-5"}'],
            ]]]]]]);

        Log::spy();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Jalankan rangkaian query'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_tool_round_limit');

        $this->assertCount(5, Http::recorded());
        Log::shouldHaveReceived('debug')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Assistant tool result observed'
                    && $context['tool_name'] === 'inventory_items'
                    && $context['result_keys'] === ['data', 'meta'];
            });
    }

    public function test_inventory_rooms_resource_returns_api_like_paginated_data(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $room = InventoryRoom::query()->create(['name' => 'Lab Komputer', 'code' => 'LAB01']);
        $item = InventoryItem::factory()->create(['kode_barang' => '28.09.2026']);
        $item->units()->create(['register' => '001', 'condition' => 'B', 'inventory_room_id' => $room->id]);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-inventory-rooms',
                'type' => 'function',
                'function' => ['name' => 'inventory_rooms', 'arguments' => json_encode([
                    'search' => 'Lab',
                    'page' => 1,
                    'per_page' => 25,
                ])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Lab Komputer tersedia.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar ruangan lab'])
            ->assertOk()
            ->assertJsonPath('message', 'Lab Komputer tersedia.');

        $toolResult = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame([
            [
                'id' => $room->id,
                'code' => 'LAB01',
                'name' => 'Lab Komputer',
                'register_count' => 1,
            ],
        ], $toolResult['data']);
        $this->assertSame([
            'current_page' => 1,
            'per_page' => 25,
            'total' => 1,
            'last_page' => 1,
        ], $toolResult['meta']);
    }

    public function test_inventory_rooms_resource_returns_all_rooms_with_pagination_meta(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $firstRoom = InventoryRoom::query()->create(['name' => 'Kelas VII A', 'code' => 'L1.P8']);
        $secondRoom = InventoryRoom::query()->create(['name' => 'Ruang Guru', 'code' => 'RG']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-inventory-rooms-all',
                'type' => 'function',
                'function' => ['name' => 'inventory_rooms', 'arguments' => json_encode([
                    'page' => 1,
                    'per_page' => 1,
                ])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ada dua ruangan.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar semua ruangan'])
            ->assertOk()
            ->assertJsonPath('message', 'Ada dua ruangan.');

        $toolResult = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame([
            [
                'id' => $firstRoom->id,
                'code' => 'L1.P8',
                'name' => 'Kelas VII A',
                'register_count' => 0,
            ],
        ], $toolResult['data']);
        $this->assertSame([
            'current_page' => 1,
            'per_page' => 1,
            'total' => 2,
            'last_page' => 2,
        ], $toolResult['meta']);
        $this->assertNotSame($firstRoom->id, $secondRoom->id);
    }

    public function test_inventory_rooms_resource_rejects_invalid_arguments(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-inventory-rooms-invalid',
                'type' => 'function',
                'function' => ['name' => 'inventory_rooms', 'arguments' => json_encode([
                    'page' => 0,
                    'per_page' => 25,
                ])],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar ruangan'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_inventory_rooms_resource_rejects_unknown_arguments(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'id' => 'call-inventory-rooms-unknown',
                'type' => 'function',
                'function' => ['name' => 'inventory_rooms', 'arguments' => json_encode([
                    'search' => 'Lab',
                    'exclude_room_name' => 'Kelas VII A',
                ])],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Daftar ruangan'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_register_resource_supports_room_filter(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $room = InventoryRoom::query()->create(['name' => 'Ruang Kepsek', 'code' => 'RK']);
        $item = InventoryItem::factory()->create(['kode_barang' => '28.09.2026']);
        $item->units()->create(['register' => '002', 'condition' => 'B', 'inventory_room_id' => $room->id]);
        $item->units()->create(['register' => '003', 'condition' => 'B']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-room-register',
                'type' => 'function',
                'function' => ['name' => 'inventory_registers', 'arguments' => json_encode([
                    'room_id' => $room->id,
                    'page' => 1,
                    'per_page' => 10,
                ])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Register di Ruang Kepsek ditemukan.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Apa register di Ruang Kepsek?'])
            ->assertOk()
            ->assertJsonPath('message', 'Register di Ruang Kepsek ditemukan.');

        $toolResult = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame('28.09.2026.002', $toolResult['data'][0]['display_code']);
        $this->assertSame('Ruang Kepsek', $toolResult['data'][0]['room']['name']);
        $this->assertSame(1, $toolResult['meta']['total']);
    }

    public static function invalidRegisterArguments(): array
    {
        return [
            'unknown key' => [['exclude_room_name' => 'VII A']],
            'condition' => [['condition' => 'unknown']],
            'item id' => [['inventory_item_id' => 0]],
            'room id' => [['room_id' => 0]],
            'year' => [['year' => 1800]],
            'page type' => [['page' => 'first']],
            'page negative' => [['page' => 0]],
            'per page type' => [['per_page' => 'many']],
            'per page 51' => [['per_page' => 51]],
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
                'function' => ['name' => 'inventory_registers', 'arguments' => json_encode($arguments)],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Buka data register'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');
    }

    public function test_register_resource_uses_explicit_condition_filter_without_placeholder_defaults(): void
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
                    'id' => 'call-register-condition',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_registers', 'arguments' => json_encode([
                        'condition' => 'B',
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

        $toolResult = json_decode(Http::recorded()[1][0]->data()['messages'][3]['content'], true);
        $this->assertSame(1, $toolResult['meta']['total']);
    }

    public function test_invalid_resource_arguments_receive_one_bounded_self_correction(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['nama_jenis_barang' => 'Koreksi pagination']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-invalid-arguments',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"page":0,"per_page":1}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-corrected-arguments',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"page":1,"per_page":1}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Data berhasil diperoleh.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari data inventaris'])
            ->assertOk()
            ->assertJsonPath('message', 'Data berhasil diperoleh.');

        $this->assertCount(3, Http::recorded());
        $correctionMessages = Http::recorded()[1][0]->data()['messages'];
        $error = json_decode($correctionMessages[3]['content'], true);
        $this->assertSame('tool', $correctionMessages[3]['role']);
        $this->assertSame('call-invalid-arguments', $correctionMessages[3]['tool_call_id']);
        $this->assertSame(['error' => 'invalid_arguments'], $error);
        $correctedMessages = Http::recorded()[2][0]->data()['messages'];
        $this->assertSame('call-corrected-arguments', $correctedMessages[4]['tool_calls'][0]['id']);
        $this->assertSame('call-corrected-arguments', $correctedMessages[5]['tool_call_id']);
    }

    public function test_invalid_resource_arguments_allow_only_one_self_correction(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-invalid-once',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"page":0}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-invalid-twice',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"per_page":51}'],
            ]]]]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari data inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_execution_failed');

        $this->assertCount(2, Http::recorded());
        $secondPayload = Http::recorded()[1][0]->data()['messages'];
        $this->assertSame(['error' => 'invalid_arguments'], json_decode($secondPayload[3]['content'], true));
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
                    'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"Kursi"}'],
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

    public function test_repeated_tool_call_returns_safe_fallback_and_logs_redacted_trace(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-repeat-1',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_registers', 'arguments' => '{"condition":"B","page":1}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-repeat-2',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-repeat-3',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_registers', 'arguments' => '{"page":1,"condition":"B"}'],
                ]],
            ]]]]);

        Log::spy();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari kursi'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_repeated_tool_call')
            ->assertJsonPath('message', 'Saya sudah memperoleh data yang diperlukan, tetapi belum dapat menyusun jawaban akhir. Silakan coba lagi.');

        $this->assertCount(3, Http::recorded());
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Assistant request stopped after repeated tool call'
                    && $context['request_id'] !== ''
                    && $context['attempt'] === 3;
            });
    }

    public function test_repeated_tool_call_recovers_once_with_tools_disabled(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-repeat-1',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'tool_calls' => [[
                    'id' => 'call-repeat-2',
                    'type' => 'function',
                    'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ringkasan tersedia.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Ringkas inventaris'])
            ->assertOk()
            ->assertJsonPath('message', 'Ringkasan tersedia.');

        $this->assertCount(3, Http::recorded());
        $this->assertArrayNotHasKey('tools', Http::recorded()[2][0]->data());
    }

    public function test_recovery_rejects_new_tool_calls_without_executing_them(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['kode_barang' => 'RECOVERY-A']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-recovery-a',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-recovery-a-duplicate',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-recovery-b',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"RECOVERY-A"}'],
            ]]]]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Ringkas inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'assistant_repeated_tool_call');

        $this->assertCount(3, Http::recorded());
        $this->assertStringNotContainsString('call-recovery-b', json_encode(Http::recorded()[2][0]->data(), JSON_THROW_ON_ERROR));
    }

    public function test_malformed_provider_tool_call_fails_closed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'tool_calls' => [[
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{}'],
            ]],
        ]]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Ringkas inventaris'])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'provider_malformed_response');
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

    public function test_follow_up_conversation_requires_fresh_resource_call_without_stale_tool_results(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['nama_jenis_barang' => 'Data lama']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-follow-up-old',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"Data lama"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Data lama ditemukan.']]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-follow-up-fresh',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"Data terbaru"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Data terbaru ditemukan.']]]]);

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari data lama'])
            ->assertOk()
            ->assertJsonPath('message', 'Data lama ditemukan.');
        $conversationId = $first->json('conversation_id');
        InventoryItem::factory()->create(['nama_jenis_barang' => 'Data terbaru']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'conversation_id' => $conversationId,
                'message' => 'Perbarui data inventaris',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Data terbaru ditemukan.');

        $followUpPayload = Http::recorded()[2][0]->data()['messages'];
        $this->assertStringContainsString('fresh resource tool call', $followUpPayload[0]['content']);
        $this->assertSame('user', $followUpPayload[3]['role']);
        $this->assertFalse(collect($followUpPayload)->contains(fn (array $message): bool => ($message['role'] ?? null) === 'tool'));
        $freshRequestMessages = Http::recorded()[3][0]->data()['messages'];
        $this->assertSame('inventory_items', $freshRequestMessages[4]['tool_calls'][0]['function']['name']);
        $freshToolResult = json_decode($freshRequestMessages[5]['content'], true);
        $this->assertSame('Data terbaru', $freshToolResult['data'][0]['name']);
    }

    public function test_explicit_refresh_flag_uses_fresh_allowlisted_resource_call(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        InventoryItem::factory()->create(['nama_jenis_barang' => 'Refresh terbaru']);

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'call-explicit-refresh',
                'type' => 'function',
                'function' => ['name' => 'inventory_items', 'arguments' => '{"search":"Refresh terbaru"}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Refresh selesai.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'message' => 'Tampilkan data inventaris',
                'refresh' => true,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Refresh selesai.');

        $payload = Http::recorded()[0][0]->data()['messages'];
        $this->assertStringContainsString('fresh resource tool call', $payload[0]['content']);
        $this->assertSame('user', $payload[1]['role']);
        $this->assertSame('inventory_items', Http::recorded()[1][0]->data()['messages'][2]['tool_calls'][0]['function']['name']);
    }

    public function test_rejected_conversation_id_does_not_emit_follow_up_prompt_semantics(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Percakapan baru.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'conversation_id' => '00000000-0000-0000-0000-000000000001',
                'message' => 'Mulai lagi',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Percakapan baru.');

        $messages = Http::recorded()[0][0]->data()['messages'];
        $this->assertStringNotContainsString('fresh resource tool call', $messages[0]['content']);
    }

    public function test_server_conversation_context_takes_precedence_over_client_history(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Jawaban pertama.']]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Jawaban kedua.']]]]);

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Pertanyaan pertama'])
            ->assertOk()
            ->assertJsonPath('message', 'Jawaban pertama.')
            ->assertJsonStructure(['conversation_id']);

        $conversationId = $first->json('conversation_id');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'conversation_id' => $conversationId,
                'message' => 'Pertanyaan kedua',
                'history' => [['role' => 'assistant', 'content' => 'Konteks palsu dari client']],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Jawaban kedua.');

        $secondPayload = Http::recorded()[1][0]->data()['messages'];
        $contents = array_column($secondPayload, 'content');
        $this->assertContains('Pertanyaan pertama', $contents);
        $this->assertContains('Jawaban pertama.', $contents);
        $this->assertContains('Pertanyaan kedua', $contents);
        $this->assertNotContains('Konteks palsu dari client', $contents);
    }

    public function test_missing_conversation_id_uses_compatibility_history_and_returns_new_id(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Jawaban kompatibel.']]],
        ])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', [
                'message' => 'Pertanyaan baru',
                'history' => [['role' => 'assistant', 'content' => 'Konteks lama']],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Jawaban kompatibel.')
            ->assertJsonStructure(['conversation_id']);

        $messages = Http::recorded()[0][0]->data()['messages'];
        $this->assertSame('Konteks lama', $messages[1]['content']);
        $this->assertSame('Pertanyaan baru', $messages[2]['content']);
    }
}
