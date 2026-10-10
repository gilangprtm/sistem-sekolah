<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinSaldo;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KantinPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_student_can_checkout_when_session_is_not_available_anymore(): void
    {
        $studentUser = User::factory()->create(['email' => 'student-card@example.test']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'full_name' => 'Siswa Terverifikasi',
            'status' => 'active',
        ]);
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);
        KantinSaldo::factory()->create(['student_id' => $student->id, 'saldo' => '10000.00']);

        $identify = $this->postJson('/kantin/pos/identify', [
            'qr' => $studentUser->email,
        ])->assertOk();

        $token = $identify->json('verification_token');
        $this->assertIsString($token);
        $this->assertSame($student->id, $identify->json('student.id'));

        $this->withSession([])
            ->postJson('/kantin/pos/checkout', [
                'verification_token' => $token,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('total', '5000.00')
            ->assertJsonPath('balance', '5000.00');

        $this->assertDatabaseHas('tr_kantin_penjualan', [
            'student_id' => $student->id,
            'total' => '5000.00',
        ]);
    }

    public function test_checkout_requires_server_verified_student_session_or_token(): void
    {
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);

        $this->postJson('/kantin/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertForbidden()
            ->assertJson(['message' => 'Scan kartu terlebih dahulu.']);

        $this->assertDatabaseCount('tr_kantin_penjualan', 0);
    }

    public function test_arbitrary_student_id_is_rejected_and_not_used(): void
    {
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);

        $this->postJson('/kantin/pos/checkout', [
            'student_id' => 999999,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);

        $this->assertDatabaseCount('tr_kantin_penjualan', 0);
    }

    public function test_invalid_verification_token_cannot_authorize_checkout(): void
    {
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);

        $this->postJson('/kantin/pos/checkout', [
            'verification_token' => str_repeat('a', 64),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertForbidden()
            ->assertJson(['message' => 'Scan kartu terlebih dahulu.']);

        $this->assertDatabaseCount('tr_kantin_penjualan', 0);
    }

    public function test_verification_token_cannot_be_reused_after_checkout(): void
    {
        $studentUser = User::factory()->create(['email' => 'student-card-reuse@example.test']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'status' => 'active',
        ]);
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);
        KantinSaldo::factory()->create(['student_id' => $student->id, 'saldo' => '20000.00']);

        $token = $this->postJson('/kantin/pos/identify', ['qr' => $studentUser->email])
            ->assertOk()
            ->json('verification_token');

        $payload = [
            'verification_token' => $token,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $this->postJson('/kantin/pos/checkout', $payload)->assertOk();
        $this->withSession([])
            ->postJson('/kantin/pos/checkout', $payload)
            ->assertForbidden()
            ->assertJson(['message' => 'Scan kartu terlebih dahulu.']);

        $this->assertSame(1, DB::table('tr_kantin_penjualan')->count());
    }
}
