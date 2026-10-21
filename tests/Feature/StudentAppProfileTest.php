<?php

namespace Tests\Feature;

use App\Models\KantinBarang;
use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAppProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_can_create_kantin_pin_without_exposing_hash(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('student-app.profile.kantin-pin'), [
                'current_password' => 'password',
                'pin' => '123456',
                'pin_confirmation' => '123456',
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', 'PIN Kantin berhasil dibuat.');

        $student->refresh();
        $this->assertNotSame('123456', $student->kantin_pin_hash);
        $this->assertNotNull($student->kantin_pin_hash);
        $profileProps = json_encode($this->actingAs($user)->get(route('student-app.profile'))->inertiaPage()['props']);
        $this->assertIsString($profileProps);
        $this->assertStringNotContainsString('123456', $profileProps);
    }

    public function test_student_can_change_kantin_pin_with_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        $this->actingAs($user)
            ->put(route('student-app.profile.kantin-pin'), [
                'current_password' => 'password',
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', 'PIN Kantin berhasil diganti.');

        $this->assertTrue(Hash::check('654321', $student->refresh()->kantin_pin_hash));
    }

    public function test_student_can_reset_kantin_pin_with_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        $this->actingAs($user)
            ->delete(route('student-app.profile.kantin-pin.reset'), [
                'current_password' => 'password',
                'confirmation' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', 'PIN Kantin berhasil direset.');

        $this->assertNull($student->refresh()->kantin_pin_hash);
    }

    public function test_student_kantin_pin_mutations_reject_wrong_password_and_missing_reset_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        $this->actingAs($user)
            ->put(route('student-app.profile.kantin-pin'), [
                'current_password' => 'wrong',
                'pin' => '654321',
                'pin_confirmation' => '654321',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->delete(route('student-app.profile.kantin-pin.reset'), [
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->assertTrue(Hash::check('123456', $student->refresh()->kantin_pin_hash));
    }

    public function test_student_kantin_page_exposes_own_balance_purchase_and_top_up_history_without_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);
        $otherStudent = Student::factory()->create();
        $saldo = KantinSaldo::factory()->create(['student_id' => $student->id, 'saldo' => '12500.00']);
        $product = KantinBarang::factory()->create(['name' => 'Roti Siswa', 'harga' => '2500.00']);
        $purchaseCreatedAt = now()->subMinute();
        $purchaseId = DB::table('tr_kantin_penjualan')->insertGetId([
            'student_id' => $student->id,
            'total' => '2500.00',
            'created_at' => $purchaseCreatedAt,
            'updated_at' => $purchaseCreatedAt,
        ]);
        DB::table('tr_kantin_penjualan_detail')->insert([
            'penjualan_id' => $purchaseId,
            'kantin_barang_id' => $product->id,
            'quantity' => 1,
            'harga' => '2500.00',
            'subtotal' => '2500.00',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $topupCreatedAt = now()->subMinutes(2)->setSeconds(0);
        $topup = KantinSaldoTransaction::query()->create([
            'student_id' => $student->id,
            'kantin_saldo_id' => $saldo->id,
            'type' => 'top_up',
            'amount' => '15000.00',
            'balance_before' => '0.00',
            'balance_after' => '15000.00',
        ]);
        DB::table('tr_kantin_saldo_mutasi')->where('id', $topup->id)->update([
            'created_at' => $topupCreatedAt,
            'updated_at' => $topupCreatedAt,
        ]);

        $this->actingAs($user)
            ->get(route('student-app.kantin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('balance.saldo', '12500.00')
                ->where('purchases.data.0.id', $purchaseId)
                ->where('purchases.data.0.date', $purchaseCreatedAt->utc()->toRfc3339String())
                ->where('purchases.data.0.details.0.barang_name', 'Roti Siswa')
                ->where('topups.data.0.amount', '15000.00')
                ->where('topups.data.0.date', $topupCreatedAt->utc()->toRfc3339String())
                ->missing('purchases.data.0.student_id')
                ->missing('topups.data.0.student_id')
                ->missing('topups.data.0.created_by'));

        $this->assertNotSame($student->id, $otherStudent->id);

        $source = file_get_contents(resource_path('js/pages/student-app/kantin.tsx'));
        $layoutSource = file_get_contents(resource_path('js/layouts/student-app-layout.tsx'));
        $this->assertIsString($source);
        $this->assertIsString($layoutSource);
        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('aria-label="Riwayat Kantin"', $source);
        $this->assertStringContainsString('id="student-kantin-balance-heading"', $source);
        $this->assertStringContainsString('href={kantinPos.url()}', $source);
        $this->assertStringContainsString('target="_blank"', $source);
        $this->assertStringContainsString('Menuju Kantin', $source);
        $this->assertStringContainsString("useState<'purchases' | 'topups'>", $source);
        $this->assertStringContainsString('Riwayat Pembelian', $source);
        $this->assertStringContainsString('Riwayat Top Up', $source);
        $this->assertStringContainsString('aria-controls="purchases-panel"', $source);
        $this->assertStringContainsString('aria-controls="topups-panel"', $source);
        $this->assertStringContainsString('grid-cols-2', $source);
        $this->assertStringContainsString('data="purchases"', $source);
        $this->assertStringContainsString('data="topups"', $source);
        $this->assertStringContainsString('onlyNext', $source);
        $this->assertStringContainsString('HistoryLoading', $source);
        $this->assertStringContainsString('bg-primary', $source);
        $this->assertStringContainsString('text-primary-foreground', $source);
        $this->assertStringContainsString('min-h-0 flex-1 overflow-y-auto overscroll-contain', $source);
        $this->assertStringContainsString("isKantin || isUas\n                            ? 'flex min-h-0 flex-1 flex-col overflow-hidden p-0'", $layoutSource);
        $this->assertStringContainsString('formatWitaDateTime', $source);
        $this->assertStringNotContainsString('ShoppingBasket', $source);
        $this->assertStringNotContainsString('Portal Siswa', $source);
        $this->assertStringNotContainsString('student-kantin-heading', $source);
        $this->assertStringNotContainsString('href="/kantin/pos"', $source);
    }

    public function test_unlinked_student_kantin_page_has_safe_empty_state_and_zero_balance(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->get(route('student-app.kantin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/kantin')
                ->where('has_kantin_pin', false)
                ->where('balance.saldo', '0.00')
                ->where('purchases.total', 0)
                ->where('topups.total', 0));
    }

    public function test_student_kantin_page_is_available_and_reports_pin_state_without_hash(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Student::factory()->create([
            'user_id' => $user->id,
            'kantin_pin_hash' => Hash::make('123456'),
        ]);

        $this->actingAs($user)
            ->get(route('student-app.kantin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/kantin')
                ->where('has_kantin_pin', true)
                ->missing('kantin_pin_hash'));

        $source = file_get_contents(resource_path('js/pages/student-app/dashboard.tsx'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('Segera tersedia', $source);
        $this->assertStringNotContainsString('Buat PIN</button>', $source);
        $this->assertStringContainsString("'/student/kantin'", $source);

        $layoutSource = file_get_contents(resource_path('js/layouts/student-app-layout.tsx'));
        $this->assertIsString($layoutSource);
        $this->assertStringNotContainsString('StudentPreferencesControl', $layoutSource);
        $this->assertStringContainsString("currentPath === '/student/kantin'", $layoutSource);
        $this->assertStringContainsString('isSecondaryPage = isKantin || isUas || isExam', $layoutSource);
        $this->assertStringContainsString('!isSecondaryPage && (', $layoutSource);
        $this->assertStringContainsString("'flex-1 p-0'", $layoutSource);

        $preferencesSource = file_get_contents(
            resource_path('js/components/student-app/preferences-control.tsx'),
        );
        $this->assertIsString($preferencesSource);
        $this->assertStringContainsString('Preferensi tampilan', $preferencesSource);
        $this->assertStringContainsString('ModalShell', $preferencesSource);
        $this->assertStringContainsString('Theme Preset', $preferencesSource);
        $this->assertStringContainsString('Fonts', $preferencesSource);
        $this->assertStringContainsString('Theme Mode', $preferencesSource);
        $this->assertStringContainsString("'light', 'dark', 'system'", $preferencesSource);
        $this->assertStringContainsString("setPreference('theme_mode', value", $preferencesSource);
        $this->assertStringContainsString("setPreference('theme_preset', value", $preferencesSource);
        $this->assertStringContainsString("setPreference('font', value", $preferencesSource);
        $this->assertStringContainsString('min-h-11', $preferencesSource);

        $shellSource = file_get_contents(resource_path('js/components/student-app/secondary-page-shell.tsx'));
        $this->assertIsString($shellSource);
        $this->assertStringContainsString('title: string', $shellSource);
        $this->assertStringContainsString('fallbackHref: string', $shellSource);
        $this->assertStringContainsString('window.history.back()', $shellSource);
        $this->assertStringContainsString('router.visit(fallbackHref)', $shellSource);
        $this->assertStringContainsString('aria-label="Kembali"', $shellSource);
        $this->assertStringContainsString('<h1 className="text-sm font-semibold">{title}</h1>', $shellSource);
        $kantinSource = file_get_contents(resource_path('js/pages/student-app/kantin.tsx'));
        $this->assertIsString($kantinSource);
        $this->assertStringContainsString('SecondaryPageShell title="Kantin"', $kantinSource);
        $examSource = file_get_contents(resource_path('js/pages/student-app/exam.tsx'));
        $this->assertIsString($examSource);
        $this->assertStringContainsString('SecondaryPageShell', $examSource);
        $lockedExamSource = file_get_contents(resource_path('js/pages/student-app/exam-locked.tsx'));
        $this->assertIsString($lockedExamSource);
        $this->assertStringContainsString('SecondaryPageShell', $lockedExamSource);
    }

    public function test_student_can_view_profile_with_linked_student_data(): void
    {
        $user = User::factory()->create(['name' => 'Akun Siswa']);
        $user->assignRole('Siswa');
        Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Terhubung',
            'nis' => 'NIS-001',
            'nisn' => 'NISN-001',
            'tahun_angkatan' => 2025,
            'birth_place' => 'Denpasar',
            'birth_date' => '2012-05-10',
            'address' => 'Jl. Pendidikan',
            'gender' => 'P',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/profile')
                ->where('user.name', 'Akun Siswa')
                ->where('user.email', $user->email)
                ->where('student.full_name', 'Siswa Terhubung')
                ->where('student.nis', 'NIS-001')
                ->where('student.nisn', 'NISN-001')
                ->where('student.photo_url', null)
                ->where('student.birth_date', '2012-05-10T00:00:00.000000Z')
                ->where('qrCode', fn ($qrCode): bool => is_string($qrCode) && str_starts_with($qrCode, 'data:image/svg+xml;base64,')));

        $props = $response->inertiaPage()['props'];
        $this->assertArrayHasKey('qrCode', $props);
        $this->assertIsString($props['qrCode']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $props['qrCode']);
    }

    public function test_student_can_update_own_password_with_current_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->from(route('student-app.profile'))
            ->put(route('student-app.profile.password'), [
                'current_password' => 'password123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('student-app.profile'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('inertia.flash_data.toast.message', 'Password berhasil diperbarui.');

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_student_password_update_rejects_wrong_current_password_and_mismatch(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->put(route('student-app.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ])
            ->assertSessionHasErrors(['current_password', 'password']);

        $this->assertTrue(Hash::check('password123', $user->refresh()->password));
    }

    public function test_student_password_update_is_role_protected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('student-app.profile.password'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertForbidden();

        $this->put(route('student-app.profile.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();
    }

    public function test_student_profile_contains_password_change_form_contract(): void
    {
        $source = file_get_contents(resource_path('js/pages/student-app/profile.tsx'));
        $visualSource = file_get_contents(resource_path('js/components/student-card-visual.tsx'));
        $this->assertIsString($source);
        $this->assertIsString($visualSource);
        $this->assertStringContainsString('Ganti password', $source);
        $this->assertStringContainsString("import { logout } from '@/routes';", $source);
        $this->assertStringContainsString('open={logoutDialogOpen}', $source);
        $this->assertStringContainsString('size="default"', $source);
        $this->assertStringContainsString("'Kartu'", $source);
        $this->assertStringContainsString('Ganti Password', $source);
        $this->assertStringContainsString('Keluar', $source);
        $this->assertStringContainsString('Keluar dari akun?', $source);
        $this->assertStringContainsString('href={logout()} as="button"', $source);
        $this->assertStringContainsString('variant="destructive"', $source);
        $this->assertStringContainsString('Batal', $source);
        $this->assertStringContainsString('StudentPreferencesControl', $source);
        $this->assertStringContainsString('ModalShell', $source);
        $this->assertStringContainsString('action="/student/profile/password"', $source);
        $this->assertStringContainsString('name="current_password"', $source);
        $this->assertStringContainsString('name="password_confirmation"', $source);
        $this->assertStringContainsString("'Foto'", $source);
        $this->assertStringContainsString('aria-pressed={showStudentCard}', $source);
        $this->assertStringContainsString('StudentCardVisual', $source);
        $this->assertStringContainsString("import { StudentCardVisual } from '@/components/student-card-visual';", $source);
        $this->assertStringContainsString('qrCode={qrCode}', $source);
        $this->assertStringContainsString('barcodeValue={', $source);
        $this->assertStringContainsString('String(student.id)', $source);
        $controllerSource = file_get_contents(app_path('Http/Controllers/StudentApp/ProfileController.php'));
        $this->assertIsString($controllerSource);
        $this->assertStringContainsString("'id',", $controllerSource);
        $this->assertStringContainsString('.split(/\\s+/)', $visualSource);
        $this->assertStringContainsString(".join('')", $visualSource);
        $this->assertStringContainsString('backgroundImage: `url(${CARD_BACKGROUND})`', $visualSource);
        $this->assertStringContainsString('KARTU PELAJAR SISWA', $visualSource);
        $this->assertStringContainsString('J. Kelamin', $visualSource);
        $this->assertStringContainsString('items-center text-left', $visualSource);
        $this->assertStringContainsString('whitespace-nowrap', $visualSource);
        $this->assertStringContainsString('T.T.L', $visualSource);
        $this->assertStringContainsString('Alamat', $visualSource);
        $this->assertStringNotContainsString('qrPayload', $source);
        $this->assertStringContainsString("'qrCode'", $controllerSource);
    }

    public function test_student_can_update_own_photo_and_old_photo_is_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'photo_path' => 'students/1/old.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'old-image');

        $response = $this->actingAs($user)->post(route('student-app.profile.photo'), [
            'photo' => UploadedFile::fake()->create('new-photo.jpg', 100, 'image/jpeg'),
        ]);

        $student->refresh();

        $response->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Foto profil berhasil diperbarui.');
        $this->assertNotSame('students/1/old.jpg', $student->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));
        $this->assertFalse(Storage::disk('public')->exists('students/1/old.jpg'));
    }

    public function test_student_without_linked_profile_cannot_update_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 100, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_student_photo_update_is_limited_to_images_of_one_megabyte(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 1025, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($student->fresh()->photo_path);
    }

    public function test_student_profile_uses_independent_nis_and_nisn_fallback_format(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        foreach (
            [
                [null, null],
                ['NIS-001', null],
                [null, 'NISN-001'],
                ['NIS-001', 'NISN-001'],
            ] as [$nis, $nisn]
        ) {
            $student->update(['nis' => $nis, 'nisn' => $nisn]);

            $this->actingAs($user)
                ->get(route('student-app.profile'))
                ->assertInertia(fn ($page) => $page
                    ->where('student.nis', $nis)
                    ->where('student.nisn', $nisn))
                ->assertOk();
        }

        $source = file_get_contents(resource_path('js/pages/student-app/profile.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('aspect-[2/3]', $source);
        $this->assertStringContainsString('object-cover', $source);
        $this->assertStringContainsString('return `${nis?.trim() || \'-\'} / ${nisn?.trim() || \'-\'}`;', $source);
        $this->assertStringContainsString("formatStudentNumbers(\n                                        student.nis,\n                                        student.nisn,\n                                    )", $source);
    }

    public function test_student_profile_is_role_protected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'))
            ->assertForbidden();
    }

    public function test_student_profile_exposes_linked_photo_url_without_storage_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'photo_path' => 'students/1/profile.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'profile-image');

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertInertia(fn ($page) => $page
                ->where('student.photo_url', Storage::disk('public')->url($student->photo_path))
                ->missing('student.photo_path'));
    }

    public function test_guest_cannot_update_student_photo(): void
    {
        $this->post(route('student-app.profile.photo'))
            ->assertRedirect(route('login'));
    }

    public function test_student_cannot_update_another_students_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create();

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 100, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($student->fresh()->photo_path);
    }

    public function test_student_without_linked_profile_can_view_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/profile')
                ->where('user.email', $user->email)
                ->where('qrCode', null)
                ->where('student', null));
    }
}
