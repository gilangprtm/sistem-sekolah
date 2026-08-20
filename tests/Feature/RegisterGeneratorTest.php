<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Services\RegisterGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private RegisterGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RegisterGeneratorService::class);
    }

    public function test_create_item_with_qty_3_generates_registers_001_002_003(): void
    {
        $item = $this->service->createItemWithUnits([
            'kode_barang' => 'A.01.01',
            'nama_jenis_barang' => 'Laptop',
            'harga' => 2000000,
            'qty' => 3,
        ]);

        $this->assertSame(3, $item->units()->count());
        $this->assertSame(['001', '002', '003'], $item->units()->pluck('register')->all());
    }

    public function test_register_restarts_for_different_kode_barang(): void
    {
        $this->service->createItemWithUnits([
            'kode_barang' => 'A.01.01',
            'nama_jenis_barang' => 'Laptop',
            'harga' => 2000000,
            'qty' => 3,
        ]);

        $itemB = $this->service->createItemWithUnits([
            'kode_barang' => 'B.01.01',
            'nama_jenis_barang' => 'Meja',
            'harga' => 500000,
            'qty' => 2,
        ]);

        $this->assertSame(['001', '002'], $itemB->units()->pluck('register')->all());
    }

    public function test_add_units_continues_register_sequence(): void
    {
        $item = $this->service->createItemWithUnits([
            'kode_barang' => 'A.01.01',
            'nama_jenis_barang' => 'Laptop',
            'harga' => 2000000,
            'qty' => 5,
        ]);

        $this->service->addUnits($item, 3);

        $this->assertSame(8, $item->units()->count());
        $this->assertSame(
            ['001', '002', '003', '004', '005', '006', '007', '008'],
            $item->units()->orderBy('register')->pluck('register')->all()
        );
    }

    public function test_qty_zero_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createItemWithUnits([
            'kode_barang' => 'A.01.01',
            'nama_jenis_barang' => 'Laptop',
            'harga' => 2000000,
            'qty' => 0,
        ]);
    }

    public function test_register_unique_constraint_prevents_duplicate(): void
    {
        $item = InventoryItem::factory()->create([
            'kode_barang' => 'A.01.01',
        ]);

        $item->units()->create(['register' => '001', 'condition' => 'B']);

        $this->expectException(QueryException::class);

        $item->units()->create(['register' => '001', 'condition' => 'B']);
    }
}
