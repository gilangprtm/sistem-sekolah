<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeroomStudentContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_homeroom_teacher_with_only_menu_permission_updates_only_contacts(): void
    {
        [$user, $student, $year, $rombel] = $this->createHomeroom();
        $original = $student->fresh()->getAttributes();
        $placement = StudentPlacement::query()->where('student_id', $student->id)->firstOrFail()->getAttributes();
        $this->assertFalse($user->can('student.update'));

        $this->actingAs($user)->from('/guru/wali-kelas?page=2&per_page=1')->patch($this->endpoint($student), [
            'phone' => '0812-003 456', 'parent_phone' => '+62 812-0000-1234',
            'full_name' => 'Forged', 'mother_name' => 'Forged', 'father_name' => 'Forged',
            'address' => 'Forged', 'status' => 'inactive', 'user_id' => $user->id,
            'nis' => 'Forged', 'id' => 99999, 'rombel_id' => 99999,
            'academic_year_id' => 99999,
        ])->assertRedirect('/guru/wali-kelas?page=2&per_page=1')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Kontak siswa berhasil diperbarui.');

        $updated = $student->fresh()->getAttributes();
        $this->assertSame('0812-003 456', $updated['phone']);
        $this->assertSame('+62 812-0000-1234', $updated['parent_phone']);
        foreach ($original as $field => $value) {
            if (! in_array($field, ['phone', 'parent_phone', 'updated_at'], true)) {
                $this->assertSame($value, $updated[$field], $field.' must remain read-only');
            }
        }
        $this->assertSame($placement, StudentPlacement::query()->where('student_id', $student->id)->firstOrFail()->getAttributes());
        $this->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page
            ->where('canUpdateStudentContacts', true)
            ->where('students.data.0.phone', '0812-003 456')
            ->where('students.data.0.parent_phone', '+62 812-0000-1234')
            ->where('attendanceStudents.0.phone', '0812-003 456')
            ->where('attendanceStudents.0.parent_phone', '+62 812-0000-1234'));
    }

    #[DataProvider('partialContacts')]
    public function test_partial_patch_preserves_omitted_contact_and_accepts_empty_null_and_max_length(array $payload, ?string $phone, ?string $parentPhone): void
    {
        [$user, $student] = $this->createHomeroom();

        $this->actingAs($user)->patch($this->endpoint($student), $payload)->assertRedirect();

        $this->assertSame($phone, $student->fresh()->phone);
        $this->assertSame($parentPhone, $student->fresh()->parent_phone);
    }

    public static function partialContacts(): array
    {
        return [
            'phone only' => [['phone' => '00123'], '00123', '08999'],
            'parent only' => [['parent_phone' => '+62 (123)'], '08111', '+62 (123)'],
            'empty phone' => [['phone' => ''], null, '08999'],
            'null parent' => [['parent_phone' => null], '08111', null],
            'both cleared' => [['phone' => null, 'parent_phone' => ''], null, null],
            'max length' => [['phone' => str_repeat('0', 30), 'parent_phone' => str_repeat('1', 30)], str_repeat('0', 30), str_repeat('1', 30)],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_invalid_contact_rejects_entire_patch_without_writes(string $field, mixed $value): void
    {
        [$user, $student] = $this->createHomeroom();
        $original = $student->fresh()->getAttributes();

        $this->actingAs($user)->patch($this->endpoint($student), [
            'phone' => '01234', 'parent_phone' => '05678', $field => $value,
        ])->assertSessionHasErrors($field);

        $this->assertSame($original, $student->fresh()->getAttributes());
    }

    public static function invalidContacts(): array
    {
        return [
            'phone over max' => ['phone', str_repeat('1', 31)],
            'parent over max' => ['parent_phone', str_repeat('1', 31)],
            'phone numeric' => ['phone', 812345],
            'parent numeric' => ['parent_phone', 812345],
            'phone array' => ['phone', ['08123']],
            'parent array' => ['parent_phone', ['08123']],
        ];
    }

    public function test_guest_and_user_missing_menu_permission_cannot_write(): void
    {
        [$user, $student] = $this->createHomeroom();
        $original = $student->fresh()->getAttributes();

        $this->patch($this->endpoint($student), ['phone' => '01234'])->assertRedirect(route('login'));
        $user->revokePermissionTo('teacher.homeroom.view');
        $this->actingAs($user)->patch($this->endpoint($student), ['phone' => '01234'])->assertForbidden();
        $this->get('/guru/wali-kelas')->assertForbidden();

        $this->assertSame($original, $student->fresh()->getAttributes());
    }

    public function test_unverified_user_cannot_write(): void
    {
        [$user, $student] = $this->createHomeroom();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->patch($this->endpoint($student), ['phone' => '01234'])->assertForbidden();

        $this->assertSame('08111', $student->fresh()->phone);
    }

    #[DataProvider('ineligibleContexts')]
    public function test_ineligible_context_cannot_write_even_after_viewing_the_page(string $context): void
    {
        [$user, $student, $year, $rombel, $teacher, $assignment] = $this->createHomeroom();
        $this->actingAs($user)->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page->where('canUpdateStudentContacts', true));
        $placement = StudentPlacement::query()->where('student_id', $student->id)->firstOrFail();

        match ($context) {
            'no profile' => $teacher->update(['user_id' => null]),
            'inactive teacher' => $teacher->update(['status' => 'inactive']),
            'staff profile' => $teacher->update(['staff_type' => 'staff']),
            'other class' => $placement->update(['rombel_id' => Rombel::factory()->create()->id]),
            'ended placement' => $placement->update(['status' => 'ended']),
            'past placement' => $placement->update(['academic_year_id' => AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive'])->id]),
            'ended assignment' => $assignment->update(['status' => 'ended']),
            'revoked assignment' => $assignment->delete(),
            'old assignment' => $assignment->update(['academic_year_id' => AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive'])->id]),
            'year switch' => AcademicYear::query()->create(['year' => '2027/2028', 'status' => 'active']),
            'no active year' => $year->update(['status' => 'inactive']),
            'inactive student' => $student->update(['status' => 'inactive']),
        };
        $original = $student->fresh()->getAttributes();

        $this->patch($this->endpoint($student), ['phone' => '01234'])->assertNotFound();

        $this->assertSame($original, $student->fresh()->getAttributes());
    }

    public static function ineligibleContexts(): array
    {
        return array_map(fn (string $context): array => [$context], array_combine(
            $contexts = ['no profile', 'inactive teacher', 'staff profile', 'other class', 'ended placement', 'past placement', 'ended assignment', 'revoked assignment', 'old assignment', 'year switch', 'no active year', 'inactive student'],
            $contexts,
        ));
    }

    public function test_missing_assignment_and_privileged_role_do_not_bypass_context_and_prop_is_hidden(): void
    {
        [$user, $student, $year, $rombel, $teacher, $assignment] = $this->createHomeroom();
        $assignment->delete();
        $user->assignRole('Super Admin');

        $this->actingAs($user)->patch($this->endpoint($student), ['phone' => '01234'])->assertNotFound();
        $this->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page->where('canUpdateStudentContacts', false));

        $this->assertSame('08111', $student->fresh()->phone);
    }

    /** @return array{User, Student, AcademicYear, Rombel, Teacher, HomeroomAssignment} */
    private function createHomeroom(): array
    {
        $user = User::factory()->create();
        $user->givePermissionTo('teacher.homeroom.view');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $rombel = Rombel::factory()->create();
        $assignment = HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id, 'teacher_id' => $teacher->id,
            'rombel_id' => $rombel->id, 'status' => 'active',
        ]);
        $student = Student::factory()->create(['phone' => '08111', 'parent_phone' => '08999']);
        StudentPlacement::query()->create([
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'student_id' => $student->id, 'status' => 'active',
        ]);

        return [$user, $student, $year, $rombel, $teacher, $assignment];
    }

    private function endpoint(Student $student): string
    {
        return route('teacher.homeroom.student-contact.update', $student);
    }
}
