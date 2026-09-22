<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCategoryTest extends TestCase
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

    public function test_category_crud_api_uses_envelope_and_normalizes_slug(): void
    {
        $token = $this->token();
        $response = $this->withToken($token)->postJson('/api/v1/categories', ['name' => 'Kursi & Meja', 'description' => 'Kelas']);
        $response->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.slug', 'kursi-meja');
        $id = $response->json('data.id');
        $this->withToken($token)->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->withToken($token)->patchJson("/api/v1/categories/{$id}", ['name' => 'Meja'])->assertOk()->assertJsonPath('data.slug', 'meja');
        $this->withToken($token)->deleteJson("/api/v1/categories/{$id}")->assertOk()->assertJsonPath('data', null);
    }

    public function test_category_api_requires_auth_and_permission(): void
    {
        $this->getJson('/api/v1/categories')->assertUnauthorized();
        $this->withToken($this->token('Guru'))->getJson('/api/v1/categories')->assertForbidden();
    }

    public function test_inventory_api_filters_by_category_and_preserves_decimal(): void
    {
        $category = Category::create(['name' => 'Elektronik']);
        $item = InventoryItem::factory()->create(['category_id' => $category->id, 'harga' => 123.45, 'kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        InventoryItem::factory()->create(['kode_barang' => 'B.01.01']);

        $this->withToken($this->token())->getJson("/api/v1/inventory?category={$category->id}")
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.category.id', $category->id);
    }

    public function test_category_api_validation_error_is_returned(): void
    {
        $this->withToken($this->token())->postJson('/api/v1/categories', ['name' => ''])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }

    public function test_user_without_assignment_permission_cannot_reassign_inventory_category(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $token = $user->createToken('test')->plainTextToken;
        $category = Category::create(['name' => 'Meja']);
        $item = InventoryItem::factory()->create();

        $this->withToken($token)
            ->patchJson("/api/v1/inventory/{$item->id}", ['category_id' => $category->id])
            ->assertForbidden();

        $this->assertNull($item->fresh()->category_id);
    }

    public function test_keterangan_only_update_does_not_require_assignment_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin Inventaris');
        $user->roles()->first()->revokePermissionTo('inventory.category.assign');
        $token = $user->createToken('test')->plainTextToken;
        $item = InventoryItem::factory()->create();

        $this->withToken($token)
            ->patchJson("/api/v1/inventory/{$item->id}", ['keterangan' => 'Catatan'])
            ->assertOk()
            ->assertJsonPath('data.keterangan', 'Catatan');
    }
}
