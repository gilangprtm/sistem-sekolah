<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinSaldo;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KantinPosTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pos_scan_state_uses_the_school_hero_background(): void
    {
        $this->get('/kantin/pos')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('kantin/pos/index'));

        $source = file_get_contents(resource_path('js/pages/kantin/pos/index.tsx'));
        $this->assertStringContainsString("backgroundImage: \"url('/images/hero-background.png')\"", $source);
        $this->assertStringContainsString('if (!student)', $source);
        $this->assertStringContainsString('<Head title="Scan Kartu Pelajar - Kantin" />', $source);
        $this->assertStringContainsString('Scan kartu pelajar untuk mulai berbelanja, atau masuk', $source);
    }

    public function test_identify_by_nisn_requires_pin_and_returns_student_after_pin_verification(): void
    {
        $studentUser = User::factory()->create(['email' => 'nisn-student@example.test']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'nisn' => '0098765432',
            'status' => 'active',
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        $challenge = $this->postJson('/kantin/pos/identify', ['nisn' => $student->nisn])
            ->assertOk()
            ->assertJsonPath('pin_required', true)
            ->json('challenge_token');

        $this->postJson('/kantin/pos/verify-pin', [
            'challenge_token' => $challenge,
            'pin' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('student.id', $student->id)
            ->assertJsonPath('student.name', $student->full_name);
    }

    public function test_identify_by_unknown_nisn_does_not_find_student(): void
    {
        $this->postJson('/kantin/pos/identify', ['nisn' => '0000000000'])
            ->assertNotFound()
            ->assertJson(['message' => 'Kartu pelajar tidak ditemukan atau siswa tidak aktif.']);
    }

    public function test_identify_by_nisn_rejects_inactive_students_and_profiles_without_accounts(): void
    {
        $studentUser = User::factory()->create();
        Student::factory()->create([
            'user_id' => $studentUser->id,
            'nisn' => '0012345671',
            'status' => 'inactive',
            'kantin_pin_hash' => Hash::make('123456'),
        ]);
        Student::factory()->create([
            'user_id' => null,
            'nisn' => '0012345672',
            'status' => 'active',
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        foreach (['0012345671', '0012345672'] as $nisn) {
            $this->postJson('/kantin/pos/identify', ['nisn' => $nisn])
                ->assertNotFound()
                ->assertJsonMissingPath('challenge_token');
        }
    }

    public function test_identify_requires_an_identifier_and_validates_nisn_as_a_bounded_string(): void
    {
        $this->postJson('/kantin/pos/identify', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['qr', 'nisn']);

        foreach ([1234567890, ['0012345678'], str_repeat('1', 21)] as $nisn) {
            $this->postJson('/kantin/pos/identify', ['nisn' => $nisn])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['nisn']);
        }
    }

    public function test_identify_rejects_submitting_nisn_and_qr_together(): void
    {
        $this->postJson('/kantin/pos/identify', [
            'qr' => 'student@example.test',
            'nisn' => '0012345678',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['qr', 'nisn']);
    }

    public function test_pos_product_surface_uses_darkened_school_hero_background(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/pos/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("bg-[url('/images/hero-background.png')]", $source);
        $this->assertStringContainsString('bg-[#100B32]/80', $source);
        $this->assertStringContainsString('pointer-events-none fixed inset-0', $source);
        $this->assertStringContainsString('<header className="mb-8 text-white">', $source);
    }

    public function test_mobile_pos_cart_toggles_an_adaptive_half_viewport_drawer(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/pos/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('const [cartOpen, setCartOpen] = useState(false);', $source);
        $this->assertStringContainsString('aria-expanded={cartOpen}', $source);
        $this->assertStringContainsString('aria-controls="cart-details"', $source);
        $this->assertStringContainsString('max-h-[50dvh]', $source);
        $this->assertStringContainsString('>{count} barang</span>', $source);
        $this->assertStringContainsString('{formatRupiah(total)}</span>', $source);
        $this->assertMatchesRegularExpression('/\\$\\{cartOpen \\? \'flex\' : \'hidden\'\\}[^\n]*lg:flex/', $source);
        $this->assertMatchesRegularExpression('/id="cart-details"[\\s\\S]*?id="cart-summary"/', $source);
        $this->assertMatchesRegularExpression('/id="cart-summary"\\s+className="mt-auto shrink-0 border-t/', $source);
        $this->assertStringContainsString("event.key === 'Escape' && cartOpen", $source);
    }

    public function test_pos_offers_card_camera_scan_and_manual_nisn_without_external_scanner_mode(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/pos/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('JSON.stringify({ [method]: value.trim() })', $source);
        $this->assertStringContainsString("identify(nisn, 'nisn')", $source);
        $this->assertStringContainsString("identify(codes[0].rawValue, 'qr')", $source);
        $this->assertStringContainsString("'Scan Kartu Pelajar'", $source);
        $this->assertStringContainsString('Atau masuk menggunakan NISN', $source);
        $this->assertStringContainsString('Verifikasi NISN', $source);
        $this->assertStringNotContainsString('pemindai QR eksternal', $source);
        $this->assertStringNotContainsString('identificationMethod', $source);
    }

    public function test_identify_requires_pin_when_student_has_no_pin(): void
    {
        $studentUser = User::factory()->create(['email' => 'student-no-pin@example.test']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'nisn' => '0012345678',
            'status' => 'active',
        ]);

        $this->postJson('/kantin/pos/identify', ['qr' => $studentUser->email])
            ->assertStatus(409)
            ->assertJson(['message' => 'Buat PIN terlebih dulu di aplikasi Portal Siswa']);

        $this->postJson('/kantin/pos/identify', ['nisn' => $student->nisn])
            ->assertStatus(409)
            ->assertJson(['message' => 'Buat PIN terlebih dulu di aplikasi Portal Siswa']);
    }

    public function test_pin_challenge_allows_correct_pin_and_invalidates_after_three_wrong_attempts(): void
    {
        $studentUser = User::factory()->create(['email' => 'student-pin@example.test']);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'kantin_pin_hash' => Hash::make('123456'), 'status' => 'active']);

        $challenge = $this->postJson('/kantin/pos/identify', ['qr' => $studentUser->email])->json('challenge_token');
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/kantin/pos/verify-pin', ['challenge_token' => $challenge, 'pin' => '000000'])
                ->assertStatus(422);
        }
        $this->postJson('/kantin/pos/verify-pin', ['challenge_token' => $challenge, 'pin' => '000000'])
            ->assertStatus(429);
        $this->postJson('/kantin/pos/verify-pin', ['challenge_token' => $challenge, 'pin' => '123456'])
            ->assertForbidden();
        $this->assertSame($student->id, Student::find($student->id)->id);
    }

    public function test_verified_student_can_checkout_when_session_is_not_available_anymore(): void
    {
        $studentUser = User::factory()->create(['email' => 'student-card@example.test']);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'full_name' => 'Siswa Terverifikasi',
            'kantin_pin_hash' => Hash::make('123456'),
            'status' => 'active',
        ]);
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);
        KantinSaldo::factory()->create(['student_id' => $student->id, 'saldo' => '10000.00']);

        $challenge = $this->postJson('/kantin/pos/identify', [
            'qr' => $studentUser->email,
        ])->assertOk()->json('challenge_token');
        $identify = $this->postJson('/kantin/pos/verify-pin', [
            'challenge_token' => $challenge,
            'pin' => '123456',
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

    public function test_pin_input_preserves_password_masking_and_numeric_normalization_contract(): void
    {
        $source = file_get_contents(resource_path('js/pages/kantin/pos/index.tsx'));

        $this->assertIsString($source);
        $pinBranch = substr($source, strpos($source, 'if (pinChallenge && !student)'), strpos($source, 'if (!student)') - strpos($source, 'if (pinChallenge && !student)'));
        $this->assertStringContainsString("backgroundImage: \"url('/images/hero-background.png')\"", $pinBranch);
        $this->assertStringContainsString('aria-hidden="true"', $pinBranch);
        $this->assertStringContainsString('bg-gradient-to-br', $pinBranch);
        $this->assertStringContainsString('<input', $source);
        $this->assertStringContainsString('type="password"', $source);
        $this->assertStringContainsString('.replace(/\\D/g, \'\')', $source);
        $this->assertStringContainsString('inputMode="numeric"', $source);
        $this->assertStringContainsString('maxLength={6}', $source);
        $this->assertStringContainsString('aria-label="PIN Kantin"', $source);
        $this->assertStringContainsString('text-black caret-black', $source);
    }

    public function test_checkout_requires_server_verified_student_session_or_token(): void
    {
        $product = KantinBarang::factory()->create(['harga' => '5000.00']);

        $this->postJson('/kantin/pos/checkout', [
            'verification_token' => str_repeat('a', 64),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
            ->assertForbidden()
            ->assertJson(['message' => 'Sesi kartu tidak valid. Silakan scan ulang.']);

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
            ->assertJson(['message' => 'Sesi kartu tidak valid. Silakan scan ulang.']);

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

        $student->update(['kantin_pin_hash' => Hash::make('123456')]);
        $challenge = $this->postJson('/kantin/pos/identify', ['qr' => $studentUser->email])
            ->assertOk()
            ->json('challenge_token');
        $token = $this->postJson('/kantin/pos/verify-pin', ['challenge_token' => $challenge, 'pin' => '123456'])
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
            ->assertJson(['message' => 'Sesi kartu tidak valid. Silakan scan ulang.']);

        $this->assertSame(1, DB::table('tr_kantin_penjualan')->count());
    }
}
