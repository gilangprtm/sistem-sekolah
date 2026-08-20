<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function makeItem(array $attrs = []): InventoryItem
    {
        return InventoryItem::factory()->create($attrs);
    }

    public function test_list_shows_items_with_pagination(): void
    {
        $admin = $this->admin();
        $this->makeItem(['kode_barang' => 'A.01.01', 'nama_jenis_barang' => 'Laptop']);
        $this->makeItem(['kode_barang' => 'B.01.01', 'nama_jenis_barang' => 'Meja']);

        $response = $this->actingAs($admin)->get('/inventory');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('items.data', 2));
    }

    public function test_search_by_kode_barang(): void
    {
        $admin = $this->admin();
        $this->makeItem(['kode_barang' => 'A.01.01', 'nama_jenis_barang' => 'Laptop']);
        $this->makeItem(['kode_barang' => 'B.01.01', 'nama_jenis_barang' => 'Meja']);

        $response = $this->actingAs($admin)->get('/inventory?search=A.01.01');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('items.data', 1)
            ->where('items.data.0.kode_barang', 'A.01.01'));
    }

    public function test_filter_by_tahun(): void
    {
        $admin = $this->admin();
        $this->makeItem(['kode_barang' => 'A.01.01', 'tahun_pembelian' => 2020]);
        $this->makeItem(['kode_barang' => 'B.01.01', 'tahun_pembelian' => 2024]);

        $response = $this->actingAs($admin)->get('/inventory?tahun=2024');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('items.data', 1)
            ->where('items.data.0.tahun_pembelian', 2024));
    }

    public function test_filter_by_kondisi(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'RB']);

        $response = $this->actingAs($admin)->get('/inventory?kondisi=RB');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('items.data', 1));
    }
}
