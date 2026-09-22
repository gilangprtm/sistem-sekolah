<?php

namespace Tests\Feature;

use App\Models\InventoryType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTypeAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_inventory_create_auto_creates_and_reuses_type_case_insensitively(): void
    {
        $existing = InventoryType::create(['name' => 'Peralatan']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/inventory', [
            'kode_barang' => 'TYPE.01', 'nama_jenis_barang' => 'Meja', 'harga' => 100, 'qty' => 1,
            'inventory_type_name' => ' peralatan ',
        ])->assertRedirect('/inventory');

        $this->assertSame(1, InventoryType::whereRaw('LOWER(name) = ?', ['peralatan'])->count());
        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'TYPE.01', 'inventory_type_id' => $existing->id]);
    }
}
