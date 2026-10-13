<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InventoryPriceFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_create_canonicalizes_supported_rupiah_values(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $sequence = 0;

        foreach ([
            '5000' => '5000.00',
            '5.000' => '5000.00',
            '5.000,50' => '5000.50',
            '1.000.000,80' => '1000000.80',
            '5000,50' => '5000.50',
            '5000.50' => '5000.50',
            '50000000' => '50000000.00',
            '50.000.000' => '50000000.00',
        ] as $input => $expected) {
            $sequence++;
            $code = 'PRICE.'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

            $this->actingAs($admin)
                ->post('/inventaris/inventory', [
                    'kode_barang' => $code,
                    'nama_jenis_barang' => 'Peralatan',
                    'harga' => $input,
                    'qty' => 1,
                ])
                ->assertRedirect('/inventaris/inventory')
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('tr_inventory_items', [
                'kode_barang' => $code,
                'harga' => $expected,
            ]);
        }
    }

    public function test_rupiah_display_formatter_preserves_exact_decimal_strings(): void
    {
        $utilsSource = File::get(resource_path('js/lib/utils.ts'));
        $showPageSource = File::get(resource_path('js/pages/inventory/show.tsx'));

        $formatter = substr(
            $utilsSource,
            strpos($utilsSource, 'export function formatRupiah('),
            strpos($utilsSource, 'export function formatCurrency(', strpos($utilsSource, 'export function formatRupiah('))
                - strpos($utilsSource, 'export function formatRupiah('),
        );

        $this->assertStringContainsString('String(value).trim()', $formatter);
        $this->assertStringContainsString("fraction.padEnd(2, '0')", $formatter);
        $this->assertStringNotContainsString('Number(value)', $formatter);
        $this->assertStringNotContainsString('formatRupiah(Number(item.harga))', $showPageSource);
        $this->assertStringContainsString('formatRupiah(item.harga)', $showPageSource);
    }

    public function test_inventory_create_rejects_noncanonical_or_overprecision_rupiah_input(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        foreach ([
            '5000.500',
            '1e3',
            '5,000.50',
            '5.00.0',
            '50000000000000',
            'Rp 5.000',
            '-5000',
        ] as $index => $invalidAmount) {
            $code = 'PRICE.BAD.'.($index + 1);

            $this->actingAs($admin)
                ->from('/inventaris/inventory/create')
                ->post('/inventaris/inventory', [
                    'kode_barang' => $code,
                    'nama_jenis_barang' => 'Peralatan',
                    'harga' => $invalidAmount,
                    'qty' => 1,
                ])
                ->assertSessionHasErrors('harga');

            $this->assertDatabaseMissing('tr_inventory_items', ['kode_barang' => $code]);
        }
    }
}
