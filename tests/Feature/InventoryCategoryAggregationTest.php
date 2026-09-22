<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCategoryAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_category_aggregation_separates_groups_units_and_preserves_decimal_value(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $category = Category::create(['name' => 'Kursi']);

        foreach ([['A.01.01', 100.25, 2], ['A.01.02', 50.50, 3]] as [$code, $price, $qty]) {
            $item = InventoryItem::factory()->create([
                'kode_barang' => $code,
                'category_id' => $category->id,
                'harga' => $price,
            ]);
            for ($number = 1; $number <= $qty; $number++) {
                $item->units()->create(['register' => str_pad((string) $number, 3, '0', STR_PAD_LEFT), 'condition' => 'B']);
            }
        }

        $response = $this->actingAs($admin)->get('/inventory/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('kpis.total_aset', 5)
            ->where('kpis.total_nilai', 352)
            ->where('category_stats.0.name', 'Kursi')
            ->where('category_stats.0.total_items', 2)
            ->where('category_stats.0.total_units', 5)
            ->where('category_stats.0.total_value', 352));
    }
}
