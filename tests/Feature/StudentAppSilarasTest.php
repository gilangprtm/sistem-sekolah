<?php

namespace Tests\Feature;

use App\Models\HumasAspiration;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\HumasPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAppSilarasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_can_send_an_aspiration_attributed_to_their_own_student_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Pengirim',
        ]);
        $otherStudent = Student::factory()->create();
        $content = "Mohon menambah tempat teduh.\nTerima kasih.";

        $this->actingAs($user)
            ->post(route('student-app.silaras.store'), [
                'content' => $content,
                'student_id' => $otherStudent->id,
            ])
            ->assertRedirect(route('student-app.services'))
            ->assertSessionHas('inertia.flash_data.toast.message', 'Aspirasi berhasil dikirim.');

        $aspiration = HumasAspiration::query()->sole();
        $this->assertSame($student->id, $aspiration->student_id);
        $this->assertSame($content, $aspiration->content);
        $this->assertNotSame($content, DB::table('tr_humas_aspirations')->where('id', $aspiration->id)->value('content'));
        $this->assertDatabaseCount('tr_humas_aspirations', 1);
    }

    public function test_silaras_page_is_available_to_students_and_denied_to_guests_and_other_roles(): void
    {
        $this->post(route('student-app.silaras.store'), ['content' => 'Tidak diizinkan.'])
            ->assertRedirect(route('login'));

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Guru', 'web'));
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('student-app.services'))->assertForbidden();
        $this->post(route('student-app.silaras.store'), ['content' => 'Tidak diizinkan.'])
            ->assertForbidden();

        $student = User::factory()->create();
        $student->assignRole('Siswa');
        $this->actingAs($student)
            ->get(route('student-app.services'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('student-app/services')->missing('aspirations'));
        $this->get(route('student-app.silaras.store'))->assertMethodNotAllowed();
        $this->get(route('humas.silaras.index'))->assertForbidden();
        $this->assertDatabaseCount('tr_humas_aspirations', 0);
    }

    public function test_student_without_linked_profile_cannot_create_unattributed_aspiration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->from(route('student-app.services'))
            ->post(route('student-app.silaras.store'), ['content' => 'Identitas harus terhubung.'])
            ->assertRedirect(route('student-app.services'))
            ->assertSessionHasErrors([
                'content' => 'Akun ini belum terhubung dengan data siswa.',
            ]);

        $this->assertDatabaseCount('tr_humas_aspirations', 0);
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidContents(): array
    {
        return [
            'empty' => ['', 'Aspirasi wajib diisi.'],
            'whitespace' => [" \n\t ", 'Aspirasi wajib diisi.'],
            'array' => [['invalid'], 'Aspirasi harus berupa teks.'],
            'too long' => [str_repeat('a', 5001), 'Aspirasi maksimal 5.000 karakter.'],
        ];
    }

    #[DataProvider('invalidContents')]
    public function test_invalid_aspirations_are_rejected_without_persistence(mixed $content, string $message): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('student-app.silaras.store'), ['content' => $content])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content' => $message]);

        $this->assertDatabaseCount('tr_humas_aspirations', 0);
    }

    public function test_dashboard_submission_returns_to_dashboard_instead_of_services(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->from(route('student-app.dashboard'))
            ->post(route('student-app.silaras.store'), ['content' => 'Aspirasi dari beranda.'])
            ->assertRedirect(route('student-app.dashboard'))
            ->assertSessionHasNoErrors();

        $aspiration = HumasAspiration::query()->sole();
        $this->assertSame($student->id, $aspiration->student_id);
        $this->assertSame('Aspirasi dari beranda.', $aspiration->content);
    }

    public function test_dashboard_submission_keeps_missing_student_error_on_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)->from(route('student-app.dashboard'))
            ->post(route('student-app.silaras.store'), ['content' => 'Aspirasi dari beranda.'])
            ->assertRedirect(route('student-app.dashboard'))
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('tr_humas_aspirations', 0);
    }

    public function test_maximum_length_aspiration_is_accepted(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Student::factory()->create(['user_id' => $user->id]);
        $content = str_repeat('a', 5000);

        $this->actingAs($user)->post(route('student-app.silaras.store'), ['content' => $content])
            ->assertRedirect(route('student-app.services'))
            ->assertSessionHasNoErrors();

        $this->assertSame($content, HumasAspiration::query()->sole()->content);
    }

    public function test_sending_is_rate_limited_without_creating_an_extra_record(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Student::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('student-app.silaras.store'), ['content' => 'Aspirasi siswa.'])
                ->assertRedirect(route('student-app.services'));
        }

        $this->post(route('student-app.silaras.store'), ['content' => 'Melampaui batas.'])
            ->assertTooManyRequests();
        $this->assertDatabaseCount('tr_humas_aspirations', 6);
    }

    public function test_humas_can_read_student_submission_and_identity_through_existing_admin_page(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'full_name' => 'Pengirim Silaras',
            'nisn' => '0099887766',
        ]);

        $this->actingAs($studentUser)->post(route('student-app.silaras.store'), [
            'content' => 'Kantin perlu menyediakan air minum.',
        ]);

        $this->seed(HumasPermissionSeeder::class);
        $humas = User::factory()->create();
        $humas->assignRole('Humas');

        $this->actingAs($humas)
            ->get(route('humas.silaras.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('aspirations.data.0.student.id', $student->id)
                ->where('aspirations.data.0.student.full_name', 'Pengirim Silaras')
                ->where('aspirations.data.0.student.nisn', '0099887766')
                ->where('aspirations.data.0.content', 'Kantin perlu menyediakan air minum.'));

        $this->assertDatabaseCount('tr_humas_aspirations', 1);
    }
}
