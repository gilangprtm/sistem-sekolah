<?php

namespace Tests\Feature;

use App\Models\TangibleAssetType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_tangible_type_is_auto_created_and_reused_case_insensitively(): void
    {
        $existing = TangibleAssetType::create(['name' => 'Perangkat']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/inventory', [
            'kode_barang' => 'ASSET.01', 'nama_jenis_barang' => 'Laptop', 'harga' => 100, 'qty' => 1,
            'asset_kind' => 'tangible', 'tangible_asset_type_name' => ' perangkat ',
        ])->assertRedirect('/inventory');

        $this->assertSame(1, TangibleAssetType::whereRaw('LOWER(name) = ?', ['perangkat'])->count());
        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'ASSET.01', 'tangible_asset_type_id' => $existing->id, 'asset_kind' => 'tangible']);
    }

    public function test_intangible_type_persists_and_tangible_is_null(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/inventory', [
            'kode_barang' => 'ASSET.02', 'nama_jenis_barang' => 'Lisensi', 'harga' => 100, 'qty' => 1,
            'asset_kind' => 'intangible', 'intangible_asset_type_name' => 'Software',
        ])->assertRedirect('/inventory');

        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'ASSET.02', 'asset_kind' => 'intangible', 'tangible_asset_type_id' => null]);
        $this->assertDatabaseHas('m_inventory_intangible_asset_types', ['name' => 'Software']);
    }
}
