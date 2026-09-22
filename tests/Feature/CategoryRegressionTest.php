<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryRegressionTest extends TestCase
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

    public function test_schema_supports_legacy_uncategorized_items_and_set_null_delete(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasColumn('inventory_items', 'category_id'));

        $legacy = InventoryItem::factory()->create(['category_id' => null]);
        $category = Category::create(['name' => 'Meja']);
        $item = InventoryItem::factory()->create(['category_id' => $category->id]);

        $category->delete();

        $this->assertDatabaseHas('inventory_items', ['id' => $legacy->id, 'category_id' => null]);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'category_id' => null]);
    }

    public function test_category_reassignment_has_web_and_api_parity(): void
    {
        $admin = $this->admin();
        $first = Category::create(['name' => 'Kursi']);
        $second = Category::create(['name' => 'Meja']);
        $item = InventoryItem::factory()->create(['category_id' => $first->id]);

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}", ['category_id' => $second->id])
            ->assertRedirect();
        $this->assertSame($second->id, $item->fresh()->category_id);

        $token = $admin->createToken('regression')->plainTextToken;
        $this->withToken($token)
            ->patchJson("/api/v1/inventory/{$item->id}", ['category_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data.category_id', $first->id);
        $this->assertSame($first->id, $item->fresh()->category_id);
    }
}
