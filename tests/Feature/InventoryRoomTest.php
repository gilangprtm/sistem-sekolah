<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryRoom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRoomTest extends TestCase
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
        $user->assignRole('Admin Inventaris');

        return $user;
    }

    public function test_admin_can_create_room_and_assign_selected_units(): void
    {
        $admin = $this->admin();
        $item = InventoryItem::factory()->create();
        $availableUnit = $item->units()->create(['register' => '001', 'condition' => 'B']);
        $previousRoom = InventoryRoom::query()->create(['name' => 'Ruang Lama', 'code' => 'RL']);
        $assignedUnit = $item->units()->create([
            'register' => '002',
            'condition' => 'B',
            'inventory_room_id' => $previousRoom->id,
        ]);

        $this->actingAs($admin)
            ->post('/inventory-rooms', [
                'name' => 'Ruang Kelas 1A',
                'code' => 'R-1A',
                'description' => 'Lantai satu',
                'unit_ids' => [$availableUnit->id, $assignedUnit->id],
            ])
            ->assertRedirectToRoute('inventory-rooms.index');

        $room = InventoryRoom::query()->where('code', 'R-1A')->firstOrFail();
        $this->assertSame('Ruang Kelas 1A', $room->name);
        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $availableUnit->id,
            'inventory_room_id' => $room->id,
        ]);
        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $assignedUnit->id,
            'inventory_room_id' => $room->id,
        ]);

        $this->actingAs($admin)
            ->patch("/inventory-rooms/{$room->id}", [
                'name' => 'Ruang Kelas 1B',
                'code' => 'R-1B',
                'description' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('m_inventory_rooms', [
            'id' => $room->id,
            'name' => 'Ruang Kelas 1B',
            'code' => 'R-1B',
        ]);

        $this->actingAs($admin)
            ->delete("/inventory-rooms/{$room->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('m_inventory_rooms', ['id' => $room->id]);
    }

    public function test_admin_can_open_edit_page_and_update_current_register_assignments(): void
    {
        $admin = $this->admin();
        $room = InventoryRoom::query()->create([
            'name' => 'Laboratorium Lama',
            'code' => 'LAB-LAMA',
            'description' => 'Keterangan lama',
        ]);
        $item = InventoryItem::factory()->create(['kode_barang' => 'INV.001']);
        $keptUnit = $item->units()->create([
            'register' => '001',
            'condition' => 'B',
            'inventory_room_id' => $room->id,
        ]);
        $removedUnit = $item->units()->create([
            'register' => '002',
            'condition' => 'KB',
            'inventory_room_id' => $room->id,
        ]);
        $addedUnit = $item->units()->create(['register' => '003', 'condition' => 'B']);

        $this->actingAs($admin)
            ->get("/inventory-rooms/{$room->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory-rooms/edit')
                ->where('room.id', $room->id)
                ->where('room.unit_ids', [$keptUnit->id, $removedUnit->id])
                ->has('assignedUnits.data', 2)
                ->has('lookupUnits.data', 3));

        $this->actingAs($admin)
            ->patch("/inventory-rooms/{$room->id}", [
                'name' => 'Laboratorium Baru',
                'code' => 'LAB-BARU',
                'description' => 'Keterangan baru',
                'unit_ids' => [$keptUnit->id, $addedUnit->id],
            ])
            ->assertRedirectToRoute('inventory-rooms.index');

        $this->assertDatabaseHas('m_inventory_rooms', [
            'id' => $room->id,
            'name' => 'Laboratorium Baru',
            'code' => 'LAB-BARU',
        ]);
        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $keptUnit->id,
            'inventory_room_id' => $room->id,
        ]);
        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $addedUnit->id,
            'inventory_room_id' => $room->id,
        ]);
        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $removedUnit->id,
            'inventory_room_id' => null,
        ]);
    }

    public function test_room_routes_require_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/inventory-rooms')->assertForbidden();
        $this->actingAs($user)->post('/inventory-rooms', [
            'name' => 'Ruang Guru',
            'code' => 'RG',
        ])->assertForbidden();
    }

    public function test_room_index_supports_server_side_pagination_search_and_placement_filter(): void
    {
        $item = InventoryItem::factory()->create();
        $assignedRoom = InventoryRoom::query()->create([
            'name' => 'Ruang Terisi',
            'code' => 'TERISI',
            'description' => 'Gedung A',
        ]);
        $item->units()->create([
            'register' => '001',
            'condition' => 'B',
            'inventory_room_id' => $assignedRoom->id,
        ]);
        InventoryRoom::query()->create([
            'name' => 'Ruang Kosong',
            'code' => 'KOSONG',
            'description' => 'Gedung B',
        ]);
        foreach (range(1, 11) as $number) {
            InventoryRoom::query()->create([
                'name' => "Ruang Tambahan {$number}",
                'code' => "R-{$number}",
            ]);
        }

        $this->actingAs($this->admin())
            ->get('/inventory-rooms?per_page=5&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory-rooms/index')
                ->has('rooms.data', 5)
                ->where('rooms.current_page', 2)
                ->where('rooms.per_page', 5)
                ->where('rooms.total', 13)
                ->where('filters.search', '')
                ->where('filters.placement', ''));

        $this->actingAs($this->admin())
            ->get('/inventory-rooms?search=Gedung%20A')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rooms.data', 1)
                ->where('rooms.data.0.code', 'TERISI'));

        $this->actingAs($this->admin())
            ->get('/inventory-rooms?placement=unassigned')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rooms.total', 12));
    }

    public function test_room_index_and_create_page_are_available_to_authorized_admin(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'INV.001']);
        $unit = $item->units()->create(['register' => '001', 'condition' => 'B']);
        $assignedRoom = InventoryRoom::query()->create(['name' => 'Ruang Lama', 'code' => 'RL']);
        $assignedUnit = $item->units()->create([
            'register' => '002',
            'condition' => 'KB',
            'inventory_room_id' => $assignedRoom->id,
        ]);

        $this->actingAs($this->admin())
            ->get('/inventory-rooms')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory-rooms/index')
                ->has('rooms'));

        $this->actingAs($this->admin())
            ->get('/inventory-rooms/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory-rooms/create')
                ->has('lookupUnits.data', 2)
                ->where('lookupUnits.data.0.id', $unit->id)
                ->where('lookupUnits.data.0.display_code', 'INV.001.001')
                ->where('lookupUnits.data.1.id', $assignedUnit->id)
                ->where('lookupUnits.data.1.room.name', 'Ruang Lama'));
    }

    public function test_create_room_lookup_uses_server_side_search_and_pagination(): void
    {
        $item = InventoryItem::factory()->create(['kode_barang' => 'INV.001']);
        foreach (range(1, 12) as $number) {
            $item->units()->create([
                'register' => str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'condition' => 'B',
            ]);
        }
        $otherItem = InventoryItem::factory()->create(['kode_barang' => 'OTHER.001']);
        $otherUnit = $otherItem->units()->create(['register' => '001', 'condition' => 'KB']);

        $this->actingAs($this->admin())
            ->get('/inventory-rooms/create?lookup_per_page=5&lookup_page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('inventory-rooms/create')
                ->has('lookupUnits.data', 5)
                ->where('lookupUnits.current_page', 2)
                ->where('lookupUnits.per_page', 5)
                ->where('lookupUnits.total', 13));

        $this->actingAs($this->admin())
            ->get('/inventory-rooms/create?lookup_search=OTHER.001')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('lookupUnits.data', 1)
                ->where('lookupUnits.data.0.id', $otherUnit->id)
                ->where('lookupUnits.data.0.display_code', 'OTHER.001.001'));
    }

    public function test_room_validation_rejects_duplicate_name_and_code(): void
    {
        $admin = $this->admin();
        InventoryRoom::query()->create([
            'name' => 'Laboratorium',
            'code' => 'LAB',
        ]);

        $this->actingAs($admin)
            ->post('/inventory-rooms', [
                'name' => 'Laboratorium',
                'code' => 'LAB',
            ])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_admin_can_assign_and_clear_current_room_for_unit(): void
    {
        $admin = $this->admin();
        $room = InventoryRoom::query()->create(['name' => 'Perpustakaan', 'code' => 'PUS']);
        $item = InventoryItem::factory()->create();
        $unit = $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}/units/{$unit->id}/room", [
                'inventory_room_id' => $room->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $unit->id,
            'inventory_room_id' => $room->id,
        ]);

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}/units/{$unit->id}/room", [
                'inventory_room_id' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $unit->id,
            'inventory_room_id' => null,
        ]);
    }

    public function test_assigning_unknown_room_is_rejected(): void
    {
        $admin = $this->admin();
        $item = InventoryItem::factory()->create();
        $unit = $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->actingAs($admin)
            ->patch("/inventory/{$item->id}/units/{$unit->id}/room", [
                'inventory_room_id' => 999999,
            ])
            ->assertSessionHasErrors('inventory_room_id');
    }

    public function test_deleting_room_clears_current_assignments(): void
    {
        $admin = $this->admin();
        $room = InventoryRoom::query()->create(['name' => 'Ruang TU', 'code' => 'TU']);
        $item = InventoryItem::factory()->create();
        $unit = $item->units()->create([
            'register' => '001',
            'condition' => 'B',
            'inventory_room_id' => $room->id,
        ]);

        $this->actingAs($admin)->delete("/inventory-rooms/{$room->id}");

        $this->assertDatabaseHas('tr_inventory_units', [
            'id' => $unit->id,
            'inventory_room_id' => null,
        ]);
    }
}
