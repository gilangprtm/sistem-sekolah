<?php

namespace Database\Factories;

use App\Models\KantinSaldo;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KantinSaldo> */
class KantinSaldoFactory extends Factory
{
    protected $model = KantinSaldo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'saldo' => '0.00',
        ];
    }
}
