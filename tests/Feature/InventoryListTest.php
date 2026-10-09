<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_list_shows_items_with_pagination_and_defaults_to_assets_view(): void
    {
        $admin = $this->admin();
        $this->makeItem(['kode_barang' => 'A.01.01', 'nama_jenis_barang' => 'Laptop']);
        $this->makeItem(['kode_barang' => 'B.01.01', 'nama_jenis_barang' => 'Meja']);

        $response = $this->actingAs($admin)->get('/inventaris/inventory');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('view', 'assets')
            ->has('items.data', 2));
    }

    public function test_legacy_inventory_url_redirects_to_canonical_url_and_preserves_query(): void
    {
        $response = $this->actingAs($this->admin())
            ->get('/inventory?search=A.01.01&view=assets');

        $response->assertMovedPermanently()
            ->assertRedirect('/inventaris/inventory?search=A.01.01&view=assets');
    }

    public function test_invalid_view_falls_back_to_assets_view(): void
    {
        $response = $this->actingAs($this->admin())->get('/inventaris/inventory?view=unknown');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('view', 'assets'));
    }

    public function test_search_by_kode_barang(): void
    {
        $admin = $this->admin();
        $this->makeItem(['kode_barang' => 'A.01.01', 'nama_jenis_barang' => 'Laptop']);
        $this->makeItem(['kode_barang' => 'B.01.01', 'nama_jenis_barang' => 'Meja']);

        $response = $this->actingAs($admin)->get('/inventaris/inventory?search=A.01.01');

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

        $response = $this->actingAs($admin)->get('/inventaris/inventory?tahun=2024');

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

        $response = $this->actingAs($admin)->get('/inventaris/inventory?kondisi=RB');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->has('items.data', 1));
    }

    public function test_register_view_is_paginated_and_builds_display_code(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem([
            'kode_barang' => '28.09.2025',
            'nama_jenis_barang' => 'Laptop',
            'tahun_pembelian' => 2025,
            'harga' => 1500000,
        ]);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'RB']);

        $response = $this->actingAs($admin)->get('/inventaris/inventory?view=registers&search=28.09.2025&kondisi=RB&register_per_page=1');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('view', 'registers')
            ->has('registers.data', 1)
            ->where('registers.total', 1)
            ->where('registers.per_page', 1)
            ->where('registers.data.0.display_code', '28.09.2025.002')
            ->where('registers.data.0.item.kode_barang', '28.09.2025')
            ->where('registers.data.0.item.tahun_pembelian', 2025)
            ->where('registers.data.0.item.harga', '1500000.00')
            ->where('registers.data.0.condition', 'RB'));
    }

    public function test_register_page_isolated_from_asset_page_and_query_is_preserved(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(['kode_barang' => 'A.01.01']);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'B']);
        $item->units()->create(['register' => '003', 'condition' => 'RB']);

        $response = $this->actingAs($admin)->get('/inventaris/inventory?view=registers&register_page=2&register_per_page=1&kondisi=B');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('view', 'registers')
            ->where('filters.kondisi', 'B')
            ->where('registers.current_page', 2)
            ->where('registers.last_page', 2)
            ->where('registers.per_page', 1)
            ->where('registers.data.0.register', '002')
            ->where('items.current_page', 1)
            ->where('items.per_page', 10));
    }

    public static function sharedFilterCases(): array
    {
        return [
            'tahun' => ['tahun'],
            'kondisi unit' => ['kondisi'],
            'asal perolehan' => ['asal'],
            'satuan' => ['satuan'],
            'kategori' => ['category'],
            'jenis inventaris' => ['inventory_type'],
        ];
    }

    #[DataProvider('sharedFilterCases')]
    public function test_shared_filters_apply_to_assets_and_registers(string $filter): void
    {
        $admin = $this->admin();
        $category = Category::query()->create(['name' => 'Elektronik']);
        $otherCategory = Category::query()->create(['name' => 'Mebel']);
        $inventoryType = InventoryType::query()->create(['name' => 'Peralatan']);
        $otherInventoryType = InventoryType::query()->create(['name' => 'Perabot']);
        $matchingItem = $this->makeItem([
            'kode_barang' => 'MATCH.01',
            'tahun_pembelian' => 2024,
            'asal_perolehan' => 'Pembelian',
            'satuan' => 'Unit',
            'inventory_category_id' => $category->id,
            'inventory_type_id' => $inventoryType->id,
        ]);
        $matchingItem->units()->create(['register' => '001', 'condition' => 'B']);
        $otherItem = $this->makeItem([
            'kode_barang' => 'OTHER.01',
            'tahun_pembelian' => 2023,
            'asal_perolehan' => 'Hibah',
            'satuan' => 'Buah',
            'inventory_category_id' => $otherCategory->id,
            'inventory_type_id' => $otherInventoryType->id,
        ]);
        $otherItem->units()->create(['register' => '002', 'condition' => 'RB']);

        $filterValues = [
            'tahun' => 2024,
            'kondisi' => 'B',
            'asal' => 'Pembelian',
            'satuan' => 'Unit',
            'category' => $category->id,
            'inventory_type' => $inventoryType->id,
        ];
        $response = $this->actingAs($admin)->get('/inventaris/inventory?'.http_build_query([
            'view' => 'registers',
            $filter => $filterValues[$filter],
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/index')
            ->where('view', 'registers')
            ->has('items.data', 1)
            ->where('items.data.0.kode_barang', 'MATCH.01')
            ->has('registers.data', 1)
            ->where('registers.data.0.item.kode_barang', 'MATCH.01'));
    }

    public function test_global_search_filters_assets_and_registers(): void
    {
        $admin = $this->admin();
        $matchingItem = $this->makeItem(['kode_barang' => 'A.01.01']);
        $matchingItem->units()->create(['register' => '001', 'condition' => 'B']);
        $otherItem = $this->makeItem(['kode_barang' => 'B.02.02']);
        $otherItem->units()->create(['register' => '002', 'condition' => 'B']);

        $registerResponse = $this->actingAs($admin)->get('/inventaris/inventory?view=registers&search=B.02.02');

        $registerResponse->assertOk();
        $registerResponse->assertInertia(fn ($page) => $page
            ->where('view', 'registers')
            ->where('filters.search', 'B.02.02')
            ->has('registers.data', 1)
            ->where('registers.data.0.item.kode_barang', 'B.02.02')
            ->has('items.data', 1)
            ->where('items.data.0.kode_barang', 'B.02.02'));

        $assetResponse = $this->actingAs($admin)->get('/inventaris/inventory?view=assets&search=001');

        $assetResponse->assertInertia(fn ($page) => $page
            ->where('view', 'assets')
            ->where('filters.search', '001')
            ->has('items.data', 1)
            ->where('items.data.0.kode_barang', 'A.01.01')
            ->has('registers.data', 1)
            ->where('registers.data.0.item.kode_barang', 'A.01.01'));
    }

    public function test_inventory_requires_authentication_and_inventory_permission(): void
    {
        $this->get('/inventaris/inventory')->assertRedirect('/login');

        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/inventaris/inventory')->assertForbidden();
    }
}
