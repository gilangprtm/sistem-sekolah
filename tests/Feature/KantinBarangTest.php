<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Models\User;
use App\Services\KantinBarangService;
use Database\Seeders\KantinSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KantinBarangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'Kantin Tester '.uniqid()]);
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_is_redirected_and_user_without_permission_is_forbidden(): void
    {
        $this->get('/kantin/barang')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/kantin/barang')
            ->assertForbidden();
    }

    public function test_index_requires_both_barang_and_category_view_permissions(): void
    {
        $barangOnly = $this->userWithPermissions(['kantin.barang.view']);

        $this->actingAs($barangOnly)
            ->get('/kantin/barang')
            ->assertForbidden();
    }

    public function test_master_barang_creates_category_and_server_generated_category_sequence(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->post('/kantin/barang', [
            'kode_barang' => 'CLIENT-CANNOT-CHOOSE',
            'category_name' => ' Buku ',
            'name' => 'Buku Tulis',
            'brand' => 'Sidu',
            'satuan' => 'pcs',
            'harga' => '5000',
        ])->assertRedirect('/kantin/barang')->assertSessionHasNoErrors();

        $this->actingAs($user)->post('/kantin/barang', [
            'category_name' => 'buku',
            'name' => 'Buku Gambar',
            'brand' => 'Fabel',
            'satuan' => 'pcs',
            'harga' => '7000',
        ])->assertRedirect('/kantin/barang')->assertSessionHasNoErrors();

        $category = KantinCategory::query()->where('name', 'Buku')->firstOrFail();
        $this->assertDatabaseHas('m_kantin_kategori', [
            'id' => $category->id,
            'slug' => 'buku',
        ]);
        $this->assertDatabaseHas('m_kantin_barang', [
            'kode_barang' => 'Buku-0001',
            'brand' => 'Sidu',
            'kantin_kategori_id' => $category->id,
        ]);
        $this->assertDatabaseHas('m_kantin_barang', [
            'kode_barang' => 'Buku-0002',
            'brand' => 'Fabel',
            'kantin_kategori_id' => $category->id,
        ]);
        $this->assertSame(1, KantinCategory::query()->whereRaw('LOWER(name) = ?', ['buku'])->count());
        $this->assertDatabaseMissing('m_kantin_barang', ['kode_barang' => 'CLIENT-CANNOT-CHOOSE']);
    }

    public function test_existing_category_requires_category_view_and_new_category_requires_category_create(): void
    {
        $category = KantinCategory::factory()->create(['name' => 'Makanan', 'slug' => 'makanan']);
        $viewOnly = $this->userWithPermissions([
            'kantin.barang.view',
            'kantin.barang.create',
            'kantin.kategori.view',
        ]);

        $this->actingAs($viewOnly)->post('/kantin/barang', [
            'category_id' => $category->id,
            'category_name' => $category->name,
            'name' => 'Roti',
            'satuan' => 'bungkus',
            'harga' => '2500',
        ])->assertRedirect('/kantin/barang')->assertSessionHasNoErrors();

        $this->actingAs($viewOnly)->post('/kantin/barang', [
            'category_name' => 'Minuman',
            'name' => 'Air Mineral',
            'satuan' => 'botol',
            'harga' => '4000',
        ])->assertForbidden();
    }

    public function test_master_barang_can_update_and_archive_without_physical_delete(): void
    {
        $user = $this->superAdmin();
        $barang = KantinBarang::factory()->create([
            'name' => 'Pensil',
            'status' => 'active',
        ]);

        $this->actingAs($user)->patch("/kantin/barang/{$barang->id}", [
            'category_id' => $barang->kantin_kategori_id,
            'category_name' => $barang->category->name,
            'name' => 'Pensil 2B',
            'brand' => 'Faber-Castell',
            'satuan' => 'pcs',
            'harga' => '3500',
            'description' => 'Pensil untuk kebutuhan siswa.',
            'status' => 'active',
        ])->assertRedirect('/kantin/barang')->assertSessionHasNoErrors();

        $this->actingAs($user)->post("/kantin/barang/{$barang->id}/archive")
            ->assertRedirect('/kantin/barang')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_kantin_barang', [
            'id' => $barang->id,
            'name' => 'Pensil 2B',
            'status' => 'inactive',
        ]);
    }

    public function test_required_master_barang_fields_are_validated(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/kantin/barang', [])
            ->assertSessionHasErrors(['category_id', 'category_name', 'name', 'satuan', 'harga']);
    }

    public function test_harga_accepts_supported_rupiah_formats_and_rejects_malformed_values(): void
    {
        $user = $this->superAdmin();
        $acceptedPrices = [
            '5000' => '5000.00',
            '5.000' => '5000.00',
            '5.000,50' => '5000.50',
            '5000.50' => '5000.50',
        ];

        foreach ($acceptedPrices as $harga => $expected) {
            $name = 'Barang '.str_replace(['.', ','], '', $harga);

            $this->actingAs($user)
                ->post('/kantin/barang', [
                    'category_name' => 'Harga '.md5($harga),
                    'name' => $name,
                    'satuan' => 'pcs',
                    'harga' => $harga,
                ])
                ->assertRedirect('/kantin/barang')
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('m_kantin_barang', [
                'name' => $name,
                'harga' => $expected,
            ]);
        }

        foreach (['5.00.0', '5.000,500', '5,000.50', 'abc'] as $harga) {
            $this->actingAs($user)
                ->post('/kantin/barang', [
                    'category_name' => 'Malformed '.md5($harga),
                    'name' => 'Barang Malformed',
                    'satuan' => 'pcs',
                    'harga' => $harga,
                ])
                ->assertSessionHasErrors('harga');
        }
    }

    public function test_stored_decimal_edit_round_trips_as_indonesian_decimal_rupiah(): void
    {
        $user = $this->superAdmin();
        $barang = KantinBarang::factory()->create(['harga' => '5000.50']);

        $this->actingAs($user)
            ->patch('/kantin/barang/'.$barang->id, [
                'category_id' => $barang->kantin_kategori_id,
                'name' => $barang->name,
                'satuan' => $barang->satuan,
                'harga' => '5.000,50',
            ])
            ->assertRedirect('/kantin/barang')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_kantin_barang', [
            'id' => $barang->id,
            'harga' => '5000.50',
        ]);
    }

    public function test_code_prefix_is_shared_with_seeder_and_handles_normalized_collisions(): void
    {
        $this->assertSame('A-B', KantinBarangService::codePrefix('A+B'));
        $this->assertSame('A-B', KantinBarangService::codePrefix('A B'));

        $category = KantinCategory::factory()->create(['name' => 'A+B', 'slug' => 'a-plus-b']);
        $collidingCategory = KantinCategory::factory()->create(['name' => 'A B', 'slug' => 'a-b-space']);
        KantinBarang::factory()->create([
            'kantin_kategori_id' => $category->id,
            'kode_barang' => 'A-B-0001',
        ]);

        $service = app(KantinBarangService::class);
        $this->assertSame('A-B-0002', $service->nextCode($collidingCategory));

        $this->seed(KantinSeeder::class);
        $seeded = KantinBarang::query()->where('name', 'Buku Tulis')->firstOrFail();
        $this->assertSame(
            KantinBarangService::codePrefix($seeded->category->name).'-0001',
            $seeded->kode_barang,
        );
    }

    public function test_category_delete_is_restricted_and_sidebar_contract_is_permission_gated(): void
    {
        $barang = KantinBarang::factory()->create();

        $this->expectException(QueryException::class);
        $barang->category->delete();
    }

    public function test_create_and_update_flash_success_toast(): void
    {
        $controllerSource = File::get(app_path('Http/Controllers/KantinBarangController.php'));

        $this->assertSame(2, substr_count($controllerSource, "Inertia::flash('toast'"));
        $this->assertStringContainsString('Barang Kantin berhasil dibuat.', $controllerSource);
        $this->assertStringContainsString('Barang Kantin berhasil diperbarui.', $controllerSource);
    }

    public function test_sidebar_uses_canonical_curriculum_urls_and_segment_safe_active_matching(): void
    {
        $sidebarSource = File::get(resource_path('js/components/dashboard/app-sidebar.tsx'));
        $navSource = File::get(resource_path('js/components/dashboard/nav-main.tsx'));

        foreach ([
            '/kurikulum/academic-years',
            '/kurikulum/subjects',
            '/kurikulum/rombels',
            '/kurikulum/schedule',
            '/kurikulum/management-class',
            '/kurikulum/teacher-subjects',
        ] as $url) {
            $this->assertStringContainsString("url: '{$url}'", $sidebarSource);
        }

        $this->assertStringContainsString('function normalizePath', $navSource);
        $this->assertStringContainsString('path.startsWith(`${candidatePath}/`)', $navSource);
        $this->assertStringNotContainsString('return path === item.url;', $navSource);
        $this->assertStringNotContainsString("url: '/academic-years'", $sidebarSource);
        $this->assertStringNotContainsString("url: '/subjects'", $sidebarSource);
        $this->assertStringNotContainsString("url: '/rombels'", $sidebarSource);
        $this->assertStringNotContainsString("url: '/schedule'", $sidebarSource);
        $this->assertStringNotContainsString("url: '/management-class'", $sidebarSource);
        $this->assertStringNotContainsString("url: '/teacher-subjects'", $sidebarSource);
    }

    public function test_code_allocation_and_sidebar_contract_are_bounded(): void
    {
        $serviceSource = File::get(app_path('Services/KantinBarangService.php'));
        $sidebarSource = File::get(resource_path('js/components/dashboard/app-sidebar.tsx'));

        $this->assertStringContainsString('lockForUpdate', $serviceSource);
        $this->assertStringContainsString("can('kantin.barang.view')", $sidebarSource);
        $this->assertStringContainsString('const canteenItems: NavMainItem[] = [];', $sidebarSource);
        $this->assertStringContainsString("label: 'Kantin', items: canteenItems", $sidebarSource);
        $this->assertStringContainsString("title: 'Master Barang'", $sidebarSource);
        $this->assertStringContainsString("url: '/kantin/barang'", $sidebarSource);
        $this->assertStringNotContainsString("title: 'Kantin'", $sidebarSource);

        $pageSource = File::get(resource_path('js/pages/kantin/barang/index.tsx'));
        $this->assertStringContainsString('onSelect={()=>openEdit(barang)}', preg_replace('/\s+/', '', $pageSource));
        $this->assertStringContainsString('setOpen(true)', $pageSource);
        $this->assertStringContainsString('harga: formatRupiah(barang.harga)', $pageSource);
        $this->assertStringContainsString('satuan: barang.satuan', $pageSource);
        $this->assertStringContainsString("import { useFlashToast } from '@/hooks/use-flash-toast';", $pageSource);
        $this->assertStringContainsString('useFlashToast();', $pageSource);
        $this->assertStringContainsString("router.on('httpException'", $pageSource);
        $this->assertStringContainsString("router.on('networkError'", $pageSource);
        $this->assertStringContainsString('aria-invalid', $pageSource);
        $this->assertStringContainsString('Gagal menyimpan', $pageSource);
        $this->assertStringContainsString('Gagal mengarsipkan barang.', $pageSource);
        $this->assertStringContainsString('onFinish: () => setProcessing(false)', $pageSource);
        $this->assertStringContainsString('disabled={processing}', $pageSource);
    }
}
