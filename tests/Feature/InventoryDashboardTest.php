<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
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

    public function test_dashboard_kpis_calculated_from_units(): void
    {
        $admin = $this->admin();

        $item = InventoryItem::factory()->create([
            'kode_barang' => 'A.01.01',
            'harga' => 2000000,
        ]);
        $item->units()->create(['register' => '001', 'condition' => 'B']);
        $item->units()->create(['register' => '002', 'condition' => 'B']);
        $item->units()->create(['register' => '003', 'condition' => 'KB']);
        $item->units()->create(['register' => '004', 'condition' => 'RB']);
        $item->units()->create(['register' => '005', 'condition' => 'B']);

        $response = $this->actingAs($admin)->get('/inventory/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('inventory/dashboard')
            ->where('kpis.total_aset', 5)
            ->where('kpis.baik', 3)
            ->where('kpis.kurang_baik', 1)
            ->where('kpis.rusak_berat', 1)
            ->where('kpis.total_kelompok', 1)
            ->where('kpis.total_nilai', 10000000)
            ->has('statistik_kondisi', 3));
    }

    public function test_non_admin_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/inventory/dashboard')
            ->assertForbidden();
    }
}
