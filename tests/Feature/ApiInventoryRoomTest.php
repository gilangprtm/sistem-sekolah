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
            ->assertJsonPath('data.0.id', $id);

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
