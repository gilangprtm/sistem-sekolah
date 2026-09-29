<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryRoom;
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

    public function test_list_registers_via_api_uses_resource_contract(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory/registers?search=A.01.01&page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.display_code', 'A.01.01.001')
            ->assertJsonPath('data.0.item.code', 'A.01.01')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_inventory_list_filters_by_resource_year(): void
    {
        InventoryItem::factory()->create(['kode_barang' => 'A.01.01', 'tahun_pembelian' => 2024]);
        InventoryItem::factory()->create(['kode_barang' => 'B.02.02', 'tahun_pembelian' => 2025]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory?year=2024')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.code', 'A.01.01');
    }

    public function test_inventory_list_rejects_page_size_above_limit(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_inventory_list_accepts_maximum_page_size(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/inventory?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.per_page', 100);
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

        $this->assertDatabaseCount('tr_inventory_units', 3);
        $this->assertDatabaseHas('tr_inventory_units', ['inventory_item_id' => $item->id, 'register' => '003']);
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

    public function test_assign_unit_room_via_api(): void
    {
        $item = InventoryItem::factory()->create();
        $unit = $item->units()->create(['register' => '001', 'condition' => 'B']);
        $room = InventoryRoom::query()->create(['name' => 'Lab Komputer', 'code' => 'LAB-KOM']);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/inventory/{$item->id}/units/{$unit->id}/room", [
                'inventory_room_id' => $room->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.room.id', $room->id);
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
