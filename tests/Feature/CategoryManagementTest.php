<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
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

    public function test_category_normalizes_slug_when_created(): void
    {
        $category = Category::create(['name' => ' Kursi & Meja ', 'description' => 'Ruang kelas']);

        $this->assertSame('kursi-meja', $category->slug);
        $this->assertDatabaseHas('m_inventory_categories', ['id' => $category->id, 'slug' => 'kursi-meja']);
    }

    public function test_category_rejects_case_insensitive_duplicate_name_and_slug(): void
    {
        Category::create(['name' => 'Elektronik']);

        $this->expectException(ValidationException::class);
        Category::create(['name' => 'elektronik']);
    }

    public function test_category_update_ignores_itself_but_rejects_other_category(): void
    {
        $category = Category::create(['name' => 'Kursi']);
        $category->update(['name' => 'KURSI']);
        $this->assertSame('kursi', $category->fresh()->slug);

        Category::create(['name' => 'Meja']);
        $this->expectException(ValidationException::class);
        $category->update(['name' => 'meja']);
    }

    public function test_non_admin_is_forbidden_from_category_crud(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/categories')
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_category(): void
    {
        $this->actingAs($this->admin())
            ->post('/categories', ['name' => 'Perabot', 'description' => 'Kelas'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_inventory_categories', ['name' => 'Perabot', 'slug' => 'perabot']);
    }

    public function test_deleting_category_sets_linked_inventory_category_to_null(): void
    {
        $category = Category::create(['name' => 'Kursi']);
        $item = InventoryItem::factory()->create(['inventory_category_id' => $category->id]);

        $this->actingAs($this->admin())
            ->delete("/categories/{$category->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('m_inventory_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('tr_inventory_items', ['id' => $item->id, 'inventory_category_id' => null]);
    }
}
