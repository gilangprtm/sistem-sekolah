<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KantinSaldoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_balance_ledger_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/kantin/saldo')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/kantin/top-up')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/kantin/saldo/top-up')->assertForbidden();
    }

    public function test_top_up_ui_is_embedded_in_balance_page(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/saldo/index.tsx'));
        $sidebar = file_get_contents(resource_path('js/components/dashboard/app-sidebar.tsx'));
        $searchDialog = file_get_contents(resource_path('js/components/dashboard/search-dialog.tsx'));

        $this->assertIsString($source);
        $this->assertIsString($sidebar);
        $this->assertIsString($searchDialog);
        $this->assertStringContainsString("import DataTableShell from '@/components/data-table/data-table-shell';", $source);
        $this->assertStringContainsString("import type { DataTableColumn } from '@/components/data-table/data-table-shell';", $source);
        $this->assertSame(3, substr_count($source, '<DataTableShell'));
        $this->assertStringContainsString('columns={studentColumns}', $source);
        $this->assertStringContainsString('columns={transactionColumns}', $source);
        $this->assertStringNotContainsString("id: 'type'", $source);
        $this->assertStringNotContainsString("header: 'Jenis'", $source);
        $this->assertStringContainsString('selectedStudents', $source);
        $this->assertStringContainsString('selectedPurchases', $source);
        $this->assertStringContainsString('selectedTopUps', $source);
        $this->assertSame(3, substr_count($source, 'selection={{'));
        $this->assertStringContainsString('variant="line"', $source);
        $this->assertStringContainsString('History Pembelian', $source);
        $this->assertStringContainsString('History Top Up', $source);
        $this->assertStringContainsString('purchaseHistory', $source);
        $this->assertStringContainsString('topUpHistory', $source);
        $this->assertStringContainsString('Pilih semua transaksi pembelian', $source);
        $this->assertStringContainsString('Pilih semua transaksi top up', $source);
        $this->assertStringContainsString('rowActions={transactionActions}', $source);
        $this->assertSame(2, substr_count($source, 'rowActions={transactionActions}'));
        $this->assertStringNotContainsString("id: 'balance-after'", $source);
        $this->assertStringContainsString('Detail Transaksi', $source);
        $this->assertStringNotContainsString('<p className="text-muted-foreground">Jenis</p>', $source);
        $this->assertStringContainsString('grid w-full grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]', $source);
        $this->assertStringContainsString('setViewedTransaction(transaction)', $source);
        $this->assertStringContainsString('<DropdownMenuItem', $source);
        $this->assertStringNotContainsString("variant: 'custom'", $source);
        $this->assertStringContainsString('Barang yang dibeli', $source);
        $this->assertStringContainsString('Total pembelian', $source);
        $this->assertStringContainsString('Nilai top up', $source);
        $this->assertStringContainsString('Saldo sebelum top up', $source);
        $this->assertStringContainsString('Saldo setelah', $source);
        $this->assertStringContainsString('Lihat', $source);
        $this->assertStringNotContainsString("from '@/components/ui/table'", $source);
        $this->assertStringContainsString('Top Up Saldo', $source);
        $this->assertStringContainsString("label: 'Hari Ini'", $source);
        $this->assertStringContainsString("label: 'Minggu Ini'", $source);
        $this->assertStringContainsString("label: 'Bulan Ini'", $source);
        $this->assertStringContainsString("label: 'Tahun Ini'", $source);
        $this->assertStringContainsString("label: 'All Transaksi'", $source);
        $this->assertStringContainsString('Total Top Up', $source);
        $this->assertStringContainsString('summary.topUp.amount', $source);
        $this->assertStringContainsString('summary.topUp.count', $source);
        $this->assertStringContainsString('formatWitaDateTime', $source);
        $this->assertStringContainsString('SearchableCombobox', $source);
        $this->assertStringContainsString('formatCurrencyInput(', $source);
        $this->assertStringContainsString('formatCurrencyInput(', $source);
        $this->assertStringContainsString('canonicalizeRupiahInput(amount)', $source);
        $this->assertStringContainsString("from '@/lib/utils';", $source);
        $this->assertStringContainsString("'/kantin/saldo/top-up'", $source);
        $this->assertStringContainsString('`/kantin/saldo/students?', $source);
        $this->assertStringNotContainsString('topUpStudents', $source);
        $this->assertStringNotContainsString('Top Up Saldo', $sidebar);
        $this->assertStringNotContainsString('Top Up Saldo', $searchDialog);
        $this->assertFileDoesNotExist(resource_path('js/pages/kantin/top-up/index.tsx'));
    }

    public function test_period_filters_ledger_and_aggregates_top_up_without_changing_global_balance(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 9, 12, 0, 0));
        $role = Role::create(['name' => 'Saldo Period Tester '.uniqid()]);
        $role->syncPermissions([
            'kantin.saldo.view',
            'kantin.saldo.history.view',
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $today = Student::factory()->create(['full_name' => 'Hari Ini']);
        $week = Student::factory()->create(['full_name' => 'Minggu Ini']);
        $old = Student::factory()->create(['full_name' => 'Lama']);
        $todaySaldo = KantinSaldo::factory()->create(['student_id' => $today->id, 'saldo' => '100.00']);
        $weekSaldo = KantinSaldo::factory()->create(['student_id' => $week->id, 'saldo' => '200.00']);
        $oldSaldo = KantinSaldo::factory()->create(['student_id' => $old->id, 'saldo' => '300.00']);

        $todayTransaction = KantinSaldoTransaction::query()->create([
            'student_id' => $today->id,
            'kantin_saldo_id' => $todaySaldo->id,
            'type' => 'top_up',
            'amount' => '25.00',
            'balance_before' => '75.00',
            'balance_after' => '100.00',
        ]);
        DB::table('tr_kantin_saldo_mutasi')->where('id', $todayTransaction->id)->update(['created_at' => Carbon::now()->subHours(2)]);

        $weekTransaction = KantinSaldoTransaction::query()->create([
            'student_id' => $week->id,
            'kantin_saldo_id' => $weekSaldo->id,
            'type' => 'top_up',
            'amount' => '50.00',
            'balance_before' => '150.00',
            'balance_after' => '200.00',
        ]);
        DB::table('tr_kantin_saldo_mutasi')->where('id', $weekTransaction->id)->update(['created_at' => Carbon::now()->subDays(2)]);

        $oldTransaction = KantinSaldoTransaction::query()->create([
            'student_id' => $old->id,
            'kantin_saldo_id' => $oldSaldo->id,
            'type' => 'top_up',
            'amount' => '75.00',
            'balance_before' => '225.00',
            'balance_after' => '300.00',
        ]);
        DB::table('tr_kantin_saldo_mutasi')->where('id', $oldTransaction->id)->update(['created_at' => Carbon::now()->subMonths(2)]);

        $this->actingAs($user)
            ->get('/kantin/saldo?period=week')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.period', 'week')
                ->where('purchaseHistory.total', 0)
                ->where('topUpHistory.total', 2)
                ->where('summary.balance', 600)
                ->where('summary.topUp.count', 2)
                ->where('summary.topUp.amount', 75));

        Carbon::setTestNow();
    }

    public function test_histories_are_separated_by_transaction_type_with_independent_pagination(): void
    {
        $role = Role::create(['name' => 'Saldo History Tester '.uniqid()]);
        $role->syncPermissions([
            'kantin.saldo.view',
            'kantin.saldo.history.view',
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $student = Student::factory()->create();
        $saldo = KantinSaldo::factory()->create(['student_id' => $student->id]);
        KantinSaldoTransaction::query()->create([
            'student_id' => $student->id,
            'kantin_saldo_id' => $saldo->id,
            'type' => 'top_up',
            'amount' => '50.00',
            'balance_before' => '90.00',
            'balance_after' => '140.00',
        ]);
        $product = KantinBarang::factory()->create([
            'name' => 'Roti Test',
            'harga' => '5.00',
        ]);
        $saleId = DB::table('tr_kantin_penjualan')->insertGetId([
            'student_id' => $student->id,
            'total' => '10.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tr_kantin_penjualan_detail')->insert([
            'penjualan_id' => $saleId,
            'kantin_barang_id' => $product->id,
            'quantity' => 2,
            'harga' => '5.00',
            'subtotal' => '10.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/kantin/saldo?purchase_page=1&top_up_page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchaseHistory.total', 1)
                ->where('purchaseHistory.data.0.type', 'purchase')
                ->where('purchaseHistory.data.0.amount', '10.00')
                ->where('purchaseHistory.data.0.details.0.barang_name', 'Roti Test')
                ->where('purchaseHistory.data.0.details.0.quantity', 2)
                ->where('purchaseHistory.data.0.details.0.subtotal', '10.00')
                ->where('topUpHistory.total', 1)
                ->where('topUpHistory.data.0.type', 'top_up')
                ->where('topUpHistory.data.0.amount', '50.00')
                ->where('topUpHistory.data.0.balance_before', '90.00')
                ->where('topUpHistory.data.0.balance_after', '140.00'));
    }

    public function test_balance_ledger_requires_history_permission_separately(): void
    {
        $role = Role::create(['name' => 'Saldo View Tester '.uniqid()]);
        $role->syncPermissions(['kantin.saldo.view']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/kantin/saldo')->assertForbidden();
    }

    public function test_top_up_updates_balance_and_creates_ledger_entry(): void
    {
        $role = Role::create(['name' => 'Saldo Tester '.uniqid()]);
        $role->syncPermissions([
            'kantin.saldo.view',
            'kantin.saldo.topup',
            'kantin.saldo.history.view',
        ]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $student = Student::factory()->create(['nis' => 'S-001', 'status' => 'active']);

        $this->actingAs($user)->post('/kantin/saldo/top-up', [
            'student_id' => $student->id,
            'amount' => '5.000,50',
        ])->assertRedirect('/kantin/saldo')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_kantin_saldo', ['student_id' => $student->id, 'saldo' => '5000.50']);
        $this->assertDatabaseHas('tr_kantin_saldo_mutasi', [
            'student_id' => $student->id,
            'kantin_saldo_id' => KantinSaldo::query()->where('student_id', $student->id)->value('id'),
            'type' => 'top_up',
            'amount' => '5000.50',
            'balance_before' => '0.00',
            'balance_after' => '5000.50',
            'created_by' => $user->id,
        ]);
        $this->assertSame(1, KantinSaldo::query()->where('student_id', $student->id)->count());
        $this->assertSame(1, KantinSaldoTransaction::query()->where('student_id', $student->id)->count());
    }

    public function test_top_up_accepts_plain_decimal_formats_and_rejects_malformed_amounts(): void
    {
        $role = Role::create(['name' => 'Saldo Amount Grammar Tester '.uniqid()]);
        $role->syncPermissions(['kantin.saldo.topup']);
        $user = User::factory()->create();
        $user->assignRole($role);

        foreach ([
            '5000' => '5000.00',
            '50000000' => '50000000.00',
            '50.000.000' => '50000000.00',
            '5.000' => '5000.00',
            '5000.50' => '5000.50',
            '12445,67' => '12445.67',
            '5000,50' => '5000.50',
            '5.000,50' => '5000.50',
        ] as $amount => $expected) {
            $student = Student::factory()->create(['status' => 'active']);

            $this->actingAs($user)->post('/kantin/saldo/top-up', [
                'student_id' => $student->id,
                'amount' => $amount,
            ])->assertRedirect('/kantin/saldo')->assertSessionHasNoErrors();

            $this->assertDatabaseHas('tr_kantin_saldo_mutasi', [
                'student_id' => $student->id,
                'amount' => $expected,
            ]);
        }

        foreach (['5.000,500', '5,000.50', '-5000', 'Rp 5.000', 'abc'] as $amount) {
            $student = Student::factory()->create(['status' => 'active']);

            $this->actingAs($user)->post('/kantin/saldo/top-up', [
                'student_id' => $student->id,
                'amount' => $amount,
            ])->assertSessionHasErrors('amount');

            $this->assertDatabaseMissing('tr_kantin_saldo_mutasi', [
                'student_id' => $student->id,
            ]);
        }
    }

    public function test_top_up_rejects_zero_and_inactive_students(): void
    {
        $role = Role::create(['name' => 'Saldo Validation Tester '.uniqid()]);
        $role->syncPermissions(['kantin.saldo.topup']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $student = Student::factory()->create(['status' => 'inactive']);

        $this->actingAs($user)->post('/kantin/saldo/top-up', [
            'student_id' => $student->id,
            'amount' => '0',
        ])->assertSessionHasErrors(['student_id', 'amount']);
    }
}
