<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryUnitRulesTest extends TestCase
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

    private function itemWithUnits(int $qty = 3): InventoryItem
    {
        $item = InventoryItem::factory()->create([
            'kode_barang' => 'A.01.01',
            'harga' => 1000000,
        ]);

        for ($i = 1; $i <= $qty; $i++) {
            $item->units()->create([
                'register' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'condition' => 'B',
            ]);
        }

        return $item;
    }

    public function test_add_units_increases_qty_and_continues_register(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(5);

        $this->actingAs($admin)
            ->post("/inventory/{$item->id}/units", ['qty' => 3])
            ->assertRedirect();

        $this->assertDatabaseCount('inventory_units', 8);
        $this->assertDatabaseHas('inventory_units', ['inventory_item_id' => $item->id, 'register' => '006']);
        $this->assertDatabaseHas('inventory_units', ['inventory_item_id' => $item->id, 'register' => '008']);
    }

    public function test_update_condition_per_unit(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(3);
        $unit = $item->units()->first();

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}/units/{$unit->id}", ['condition' => 'RB'])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_units', ['id' => $unit->id, 'condition' => 'RB']);
    }

    public function test_invalid_condition_rejected(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(1);
        $unit = $item->units()->first();

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}/units/{$unit->id}", ['condition' => 'X'])
            ->assertSessionHasErrors('condition');
    }

    public function test_immutable_fields_are_protected_on_update(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(1);

        // Update request hanya menerima keterangan; kode_barang dsb harus diabaikan
        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}", [
                'keterangan' => 'Catatan baru',
                'kode_barang' => 'X.99.99',
                'nama_jenis_barang' => 'Diubah',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame('A.01.01', $item->kode_barang);
        $this->assertNotSame('Diubah', $item->nama_jenis_barang);
        $this->assertSame('Catatan baru', $item->keterangan);
    }

    public function test_delete_permanently_removes_item_and_units(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(3);

        $this->actingAs($admin)
            ->delete("/inventory/{$item->id}")
            ->assertRedirect('/inventory');

        $this->assertDatabaseMissing('inventory_items', ['id' => $item->id]);
        $this->assertDatabaseCount('inventory_units', 0);
    }

    public function test_qty_cannot_decrease(): void
    {
        $admin = $this->admin();
        $item = $this->itemWithUnits(5);

        // Tidak ada endpoint untuk mengurangi qty — hanya menambah unit.
        // DELETE unit harus gagal (404 route tidak ada / 405 method not allowed).
        $response = $this->actingAs($admin)
            ->delete("/inventory/{$item->id}/units/1");

        $this->assertTrue(in_array($response->status(), [404, 405], true));
        $this->assertDatabaseCount('inventory_units', 5);
    }
}
