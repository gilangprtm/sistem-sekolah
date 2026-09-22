<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryAutoCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_inventory_create_auto_creates_category_from_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/inventory', [
                'kode_barang' => 'AUTO.01',
                'nama_jenis_barang' => 'Kursi',
                'harga' => '100.25',
                'qty' => 1,
                'category_name' => '  Perabot Kelas  ',
            ])
            ->assertRedirect('/inventory');

        $category = Category::where('name', 'Perabot Kelas')->first();
        $this->assertNotNull($category);
        $this->assertSame('perabot-kelas', $category->slug);
        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'AUTO.01', 'inventory_category_id' => $category->id]);
    }

    public function test_auto_create_reuses_existing_category_case_insensitively(): void
    {
        $existing = Category::create(['name' => 'Perabot']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/inventory', [
            'kode_barang' => 'AUTO.02',
            'nama_jenis_barang' => 'Meja',
            'harga' => 100,
            'qty' => 1,
            'category_name' => ' perabot ',
        ]);

        $this->assertSame(1, Category::whereRaw('LOWER(name) = ?', ['perabot'])->count());
        $this->assertDatabaseHas('tr_inventory_items', ['kode_barang' => 'AUTO.02', 'inventory_category_id' => $existing->id]);
    }
}
