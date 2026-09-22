<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTypeTest extends TestCase
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

    public function test_initial_inventory_types_are_defined_by_migration(): void
    {
        $this->assertSame(['Aset', 'Hibah', 'Non Aset'], InventoryType::query()->orderBy('name')->pluck('name')->all());
    }

    public function test_type_normalizes_slug_and_rejects_case_insensitive_duplicate(): void
    {
        $type = InventoryType::create(['name' => ' Barang Khusus ']);
        $this->assertSame('barang-khusus', $type->slug);
        $this->expectException(ValidationException::class);
        InventoryType::create(['name' => 'barang khusus']);
    }

    public function test_inventory_type_crud_is_authorized_and_delete_nulls_assignment(): void
    {
        $type = InventoryType::create(['name' => 'Sementara']);
        $item = InventoryItem::factory()->create(['inventory_type_id' => $type->id]);
        $this->actingAs($this->admin())->get('/inventory-types')->assertOk();
        $this->actingAs($this->admin())->delete("/inventory-types/{$type->id}")->assertRedirect();
        $this->assertDatabaseHas('tr_inventory_items', ['id' => $item->id, 'inventory_type_id' => null]);
    }
}
