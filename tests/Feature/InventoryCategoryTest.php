<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryTest extends TestCase
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

    public function test_category_assignment_preserves_inventory_fields_and_units(): void
    {
        $category = Category::create(['name' => 'Elektronik']);
        $item = InventoryItem::factory()->create([
            'kode_barang' => 'A.01.01',
            'harga' => 123.45,
            'keterangan' => 'Tetap',
        ]);
        $item->units()->create(['register' => '001', 'condition' => 'RB']);

        $this->actingAs($this->admin())
            ->patch("/inventory/{$item->id}", ['inventory_category_id' => $category->id])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame($category->id, $item->inventory_category_id);
        $this->assertSame('A.01.01', $item->kode_barang);
        $this->assertSame('123.45', $item->harga);
        $this->assertSame('Tetap', $item->keterangan);
        $this->assertSame('001', $item->units()->first()->register);
        $this->assertSame('RB', $item->units()->first()->condition);
    }

    public function test_inventory_list_can_filter_by_category_and_keeps_uncategorized_items(): void
    {
        $category = Category::create(['name' => 'Meja']);
        $matching = InventoryItem::factory()->create(['inventory_category_id' => $category->id, 'kode_barang' => 'A.01.01']);
        InventoryItem::factory()->create(['inventory_category_id' => null, 'kode_barang' => 'B.01.01']);

        $this->actingAs($this->admin())
            ->get("/inventory?category={$category->id}")
            ->assertInertia(fn ($page) => $page
                ->component('inventory/index')
                ->has('items.data', 1)
                ->where('items.data.0.id', $matching->id));
    }

    public function test_user_without_assignment_permission_cannot_assign_category(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $category = Category::create(['name' => 'Meja']);
        $item = InventoryItem::factory()->create();

        $this->actingAs($user)
            ->patch("/inventory/{$item->id}", ['inventory_category_id' => $category->id])
            ->assertForbidden();
    }
}
