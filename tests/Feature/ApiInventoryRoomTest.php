<?php

namespace Tests\Feature;

use App\Models\InventoryRoom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiInventoryRoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function token(string $role = 'Super Admin'): string
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->createToken('test')->plainTextToken;
    }

    public function test_room_crud_api_uses_envelope(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->postJson('/api/v1/inventory-rooms', [
                'name' => 'Lab IPA',
                'code' => 'LAB-IPA',
                'description' => 'Lantai dua',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Lab IPA');

        $id = $response->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/inventory-rooms')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);

        $this->withToken($token)
            ->patchJson("/api/v1/inventory-rooms/{$id}", [
                'name' => 'Lab IPA 1',
                'code' => 'LAB-IPA-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'LAB-IPA-1');

        $this->withToken($token)
            ->deleteJson("/api/v1/inventory-rooms/{$id}")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_room_index_uses_resource_search_and_pagination_contract(): void
    {
        $token = $this->token();
        InventoryRoom::query()->create(['name' => 'Kelas VII A', 'code' => 'L1.P8']);
        InventoryRoom::query()->create(['name' => 'Ruang Guru', 'code' => 'RG']);

        $this->withToken($token)
            ->getJson('/api/v1/inventory-rooms?search=Kelas&page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Kelas VII A')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_room_api_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/inventory-rooms')->assertUnauthorized();
        $this->withToken($this->token('Guru'))
            ->getJson('/api/v1/inventory-rooms')
            ->assertForbidden();
    }

    public function test_room_api_rejects_duplicate_code_and_name(): void
    {
        InventoryRoom::query()->create(['name' => 'Ruang Guru', 'code' => 'RG']);

        $this->withToken($this->token())
            ->postJson('/api/v1/inventory-rooms', ['name' => 'Ruang Guru', 'code' => 'RG'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }
}
