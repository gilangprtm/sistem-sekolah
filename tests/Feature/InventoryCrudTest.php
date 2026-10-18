<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryCrudTest extends TestCase
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

    public function test_create_inventory_with_qty_generates_registers(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 3,
            ])
            ->assertRedirect('/inventaris/inventory');

        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'A.01.01']);
        $this->assertDatabaseCount('tr_inventory_units', 3);
    }

    public function test_create_inventory_assigns_selected_category(): void
    {
        $admin = $this->admin();
        $category = Category::query()->create(['name' => 'Elektronik']);

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.05',
                'nama_jenis_barang' => 'Monitor',
                'harga' => 2000000,
                'qty' => 1,
                'inventory_category_id' => $category->id,
            ])
            ->assertRedirect('/inventaris/inventory')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_inventory_items', [
            'kode_barang' => 'A.01.05',
            'inventory_category_id' => $category->id,
        ]);
    }

    public function test_create_inventory_without_purchase_year_keeps_inventory_list_renderable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.04',
                'nama_jenis_barang' => 'Kamera',
                'harga' => 2000000,
                'qty' => 1,
            ])
            ->assertRedirect('/inventaris/inventory')
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->get('/inventaris/inventory')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/index')
                ->where('filterOptions.tahun', []));
    }

    public function test_create_inventory_stores_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.02',
                'nama_jenis_barang' => 'Proyektor',
                'harga' => 2000000,
                'qty' => 1,
                'image' => UploadedFile::fake()->create('projector.png', 100, 'image/png'),
            ])
            ->assertRedirect('/inventaris/inventory')
            ->assertSessionHasNoErrors();

        $item = InventoryItem::query()->where('kode_barang', 'A.01.02')->firstOrFail();

        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_inventory_detail_exposes_a_protected_image_url(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $item = InventoryItem::factory()->create([
            'image_path' => 'inventory/items/projector.png',
        ]);
        Storage::disk('public')->put($item->image_path, 'image-content');

        $this->actingAs($admin)
            ->get("/inventaris/inventory/{$item->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory/show')
                ->where('item.image_url', route('inventory.image', $item, false).'?v='.$item->updated_at->getTimestamp())
                ->missing('item.image_path'));

        $this->actingAs($admin)
            ->get(route('inventory.image', $item))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('image-content');
    }

    public function test_inventory_image_requires_authentication_and_inventory_permission(): void
    {
        Storage::fake('public');
        $item = InventoryItem::factory()->create([
            'image_path' => 'inventory/items/projector.png',
        ]);
        Storage::disk('public')->put($item->image_path, 'image-content');

        $this->get(route('inventory.image', $item))->assertRedirect('/login');

        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        $this->actingAs($teacher)
            ->get(route('inventory.image', $item))
            ->assertForbidden();
    }

    public function test_update_inventory_replaces_uploaded_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $item = InventoryItem::factory()->create([
            'image_path' => 'inventory/items/old.png',
        ]);
        Storage::disk('public')->put($item->image_path, 'old-image');

        $this->actingAs($admin)
            ->post("/inventaris/inventory/{$item->id}", [
                '_method' => 'PATCH',
                'image' => UploadedFile::fake()->create('replacement.webp', 100, 'image/webp'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertNotNull($item->image_path);
        $this->assertNotSame('inventory/items/old.png', $item->image_path);
        Storage::disk('public')->assertMissing('inventory/items/old.png');
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_update_inventory_rejects_invalid_image_and_keeps_existing_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $item = InventoryItem::factory()->create([
            'image_path' => 'inventory/items/original.png',
        ]);
        Storage::disk('public')->put($item->image_path, 'original-image');

        $this->actingAs($admin)
            ->from("/inventaris/inventory/{$item->id}")
            ->post("/inventaris/inventory/{$item->id}", [
                '_method' => 'PATCH',
                'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect("/inventaris/inventory/{$item->id}")
            ->assertSessionHasErrors('image');

        $this->assertSame('inventory/items/original.png', $item->fresh()->image_path);
        Storage::disk('public')->assertExists('inventory/items/original.png');
    }

    public function test_create_inventory_rejects_non_image_upload(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/inventaris/inventory/create')
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.03',
                'nama_jenis_barang' => 'Printer',
                'harga' => 2000000,
                'qty' => 1,
                'image' => UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('tr_inventory_items', ['kode_barang' => 'A.01.03']);
        $this->assertDatabaseCount('tr_inventory_units', 0);
    }

    public function test_create_requires_unique_kode_barang(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 1,
            ])
            ->assertRedirect('/inventaris/inventory');

        $this->actingAs($admin)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop 2',
                'harga' => 1000000,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('kode_barang');
    }

    public function test_non_admin_cannot_create_inventory(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->post('/inventaris/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 1,
            ])
            ->assertForbidden();
    }
}
