<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KantinDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dashboard_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/kantin/dashboard')->assertForbidden();
    }

    public function test_dashboard_aggregates_period_sales_and_leaderboards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 9, 12));
        $role = Role::create(['name' => 'Kantin Dashboard Tester '.uniqid()]);
        $role->syncPermissions(['kantin.dashboard.view']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $student = Student::factory()->create(['full_name' => 'Pembeli Satu', 'nis' => 'S-001']);
        $category = KantinCategory::factory()->create();
        $product = KantinBarang::factory()->create(['kantin_kategori_id' => $category->id, 'name' => 'Roti Dashboard']);
        $sale = DB::table('tr_kantin_penjualan')->insertGetId([
            'student_id' => $student->id,
            'total' => '12000.00',
            'created_at' => Carbon::now()->subHour(),
            'updated_at' => Carbon::now()->subHour(),
        ]);
        DB::table('tr_kantin_penjualan_detail')->insert([
            'penjualan_id' => $sale,
            'kantin_barang_id' => $product->id,
            'quantity' => 2,
            'harga' => '6000.00',
            'subtotal' => '12000.00',
            'created_at' => Carbon::now()->subHour(),
            'updated_at' => Carbon::now()->subHour(),
        ]);

        $this->actingAs($user)->get('/kantin/dashboard?period=today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period', 'today')
                ->where('summary.revenue', 12000)
                ->where('summary.transactions', 1)
                ->where('summary.buyers', 1)
                ->where('summary.itemsSold', 2)
                ->where('transactions.0.full_name', 'Pembeli Satu')
                ->where('topBuyers.0.total', 12000)
                ->where('topItems.0.quantity', 2));

        Carbon::setTestNow();
    }

    public function test_dashboard_year_period_includes_current_year_only(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 9, 12));
        $role = Role::create(['name' => 'Kantin Dashboard Year Tester '.uniqid()]);
        $role->syncPermissions(['kantin.dashboard.view']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $student = Student::factory()->create(['full_name' => 'Pembeli Tahun']);
        $category = KantinCategory::factory()->create();
        $product = KantinBarang::factory()->create(['kantin_kategori_id' => $category->id]);

        $currentYearSale = DB::table('tr_kantin_penjualan')->insertGetId([
            'student_id' => $student->id,
            'total' => '15000.00',
            'created_at' => Carbon::create(2026, 1, 1, 0),
            'updated_at' => Carbon::create(2026, 1, 1, 0),
        ]);
        DB::table('tr_kantin_penjualan_detail')->insert([
            'penjualan_id' => $currentYearSale,
            'kantin_barang_id' => $product->id,
            'quantity' => 3,
            'harga' => '5000.00',
            'subtotal' => '15000.00',
            'created_at' => Carbon::create(2026, 1, 1, 0),
            'updated_at' => Carbon::create(2026, 1, 1, 0),
        ]);

        $previousYearSale = DB::table('tr_kantin_penjualan')->insertGetId([
            'student_id' => $student->id,
            'total' => '9000.00',
            'created_at' => Carbon::create(2025, 12, 31, 23, 59, 59),
            'updated_at' => Carbon::create(2025, 12, 31, 23, 59, 59),
        ]);
        DB::table('tr_kantin_penjualan_detail')->insert([
            'penjualan_id' => $previousYearSale,
            'kantin_barang_id' => $product->id,
            'quantity' => 2,
            'harga' => '4500.00',
            'subtotal' => '9000.00',
            'created_at' => Carbon::create(2025, 12, 31, 23, 59, 59),
            'updated_at' => Carbon::create(2025, 12, 31, 23, 59, 59),
        ]);

        $this->actingAs($user)->get('/kantin/dashboard?period=year')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('period', 'year')
                ->where('summary.revenue', 15000)
                ->where('summary.transactions', 1)
                ->where('summary.buyers', 1)
                ->where('summary.itemsSold', 3)
                ->where('transactions.0.id', $currentYearSale)
                ->where('topBuyers.0.total', 15000)
                ->where('topItems.0.quantity', 3));

        Carbon::setTestNow();
    }

    public function test_invalid_dashboard_period_falls_back_to_today(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 9, 12));
        $role = Role::create(['name' => 'Kantin Dashboard Invalid Period Tester '.uniqid()]);
        $role->syncPermissions(['kantin.dashboard.view']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/kantin/dashboard?period=invalid')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('period', 'today'));

        Carbon::setTestNow();
    }

    public function test_dashboard_source_has_requested_sections_and_period_tabs(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/dashboard.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/dashboard/app-sidebar.tsx'));

        $this->assertIsString($source);
        $this->assertIsString($sidebar);
        $this->assertStringContainsString('Transaksi Siswa Belanja', $source);
        $this->assertStringContainsString('Top Pembeli', $source);
        $this->assertStringContainsString('Top Barang', $source);
        $this->assertStringContainsString("label: 'Hari Ini'", $source);
        $this->assertStringContainsString("label: 'Minggu Ini'", $source);
        $this->assertStringContainsString("label: 'Bulan Ini'", $source);
        $this->assertStringContainsString("label: 'Tahun Ini'", $source);
        $this->assertStringContainsString("label: 'All Transaksi'", $source);
        $this->assertStringContainsString("title: 'Dashboard Kantin'", $sidebar);
    }
}
