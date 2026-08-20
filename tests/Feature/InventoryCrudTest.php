<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->post('/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 3,
            ])
            ->assertRedirect('/inventory');

        $this->assertDatabaseHas('inventory_items', ['kode_barang' => 'A.01.01']);
        $this->assertDatabaseCount('inventory_units', 3);
    }

    public function test_create_requires_unique_kode_barang(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 1,
            ])
            ->assertRedirect('/inventory');

        $this->actingAs($admin)
            ->post('/inventory', [
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
            ->post('/inventory', [
                'kode_barang' => 'A.01.01',
                'nama_jenis_barang' => 'Laptop',
                'harga' => 2000000,
                'qty' => 1,
            ])
            ->assertForbidden();
    }
}
