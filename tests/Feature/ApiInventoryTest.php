<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin->createToken('test')->plainTextToken;
    }

    public function test_create_inventory_via_api(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/v1/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.kode_barang', 'A.01.01')
            ->assertJsonCount(3, 'data.units');
    }

    public function test_list_inventory_via_api(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_show_inventory_with_units(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'KB']);

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/inventory/{$item->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.units');
    }

    public function test_add_units_via_api(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/inventory/{$item->id}/units", ['qty' => 2])
            ->assertOk();

        $this->assertDatabaseCount('inventory_units', 3);
        $this->assertDatabaseHas('inventory_units', ['inventory_item_id' => $item->id, 'register' => '003']);
    }

    public function test_update_condition_via_api(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01']);
        $unit = $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/inventory/{$item->id}/units/{$unit->id}", ['condition' => 'RB'])
            ->assertOk()
            ->assertJsonPath('data.condition', 'RB');
    }

    public function test_dashboard_kpis_via_api(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01', 'harga' => 1000000]);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'B']);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.total_aset', 2)
            ->assertJsonPath('data.kpis.total_nilai', 2000000)
            ->assertJsonPath('data.kpis.baik', 2);
    }

    public function test_non_admin_cannot_manage_inventory(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 1000,
                'qty' => 1,
            ])
            ->assertForbidden();
    }
}
