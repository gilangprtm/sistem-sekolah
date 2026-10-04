<?php

namespace Tests\Feature;

use App\Models\Rombel;
use Database\Seeders\RombelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RombelSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_rombel_seeder_creates_all_grades_and_parallel_classes(): void
    {
        $this->seed(RombelSeeder::class);

        $this->assertDatabaseCount('m_rombels', 30);
        $this->assertSame(
            collect(['VII', 'VIII', 'IX'])
                ->flatMap(fn (string $grade) => collect(range('A', 'J'))->map(fn (string $parallel) => $grade.'-'.$parallel))
                ->sort()
                ->values()
                ->all(),
            Rombel::query()->orderBy('code')->pluck('code')->all(),
        );
        $this->assertSame(30, Rombel::query()->where('status', 'active')->count());
    }

    public function test_default_rombel_seeder_is_idempotent_and_repairs_default_values(): void
    {
        $this->seed(RombelSeeder::class);
        Rombel::query()->where('code', 'VII-A')->update([
            'name' => 'Nama Lama',
            'status' => 'inactive',
        ]);

        $this->seed(RombelSeeder::class);

        $rombel = Rombel::query()->where('code', 'VII-A')->firstOrFail();
        $this->assertDatabaseCount('m_rombels', 30);
        $this->assertSame('Kelas VII A', $rombel->name);
        $this->assertSame('active', $rombel->status);
    }
}
