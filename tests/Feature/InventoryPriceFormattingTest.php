<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPriceFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_create_accepts_clean_decimal_value_from_formatted_input(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/inventory', [
                'kode_barang' => 'PRICE.01',
                'nama_jenis_barang' => 'Peralatan',
                'harga' => '5000000.50',
                'qty' => 1,
            ])
            ->assertRedirect('/inventory')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_items', [
            'kode_barang' => 'PRICE.01',
            'harga' => '5000000.50',
        ]);
    }
}
