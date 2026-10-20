<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomSeat;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\HomeroomSeatingPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeroomSeatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_targeted_permission_seed_is_additive_and_idempotent(): void
    {
        $guru = Role::findByName('Guru', 'web');
        $existingPermission = Permission::findOrCreate('teacher.custom.existing', 'web');
        $guru->givePermissionTo($existingPermission);
        $guru->revokePermissionTo('teacher.homeroom.seating.manage');

        $this->seed(HomeroomSeatingPermissionSeeder::class);
        $this->seed(HomeroomSeatingPermissionSeeder::class);

        $this->assertTrue($guru->fresh()->hasPermissionTo('teacher.custom.existing'));
        $this->assertTrue($guru->fresh()->hasPermissionTo('teacher.homeroom.view'));
        $this->assertTrue($guru->fresh()->hasPermissionTo('teacher.homeroom.seating.manage'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('teacher.homeroom.seating.manage'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.homeroom.seating.manage'));
    }

    public function test_roster_is_not_limited_by_table_pagination_and_exposes_saved_seats_and_photos(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $students = Student::factory()->count(3)->create();
        foreach ($students as $student) {
            $this->placeStudent($student, $year, $rombel);
        }
        $students[0]->update(['photo_path' => 'students/siswa.jpg']);
        HomeroomSeat::query()->create([
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'seat_number' => 8, 'student_id' => $students[0]->id,
        ]);

        $this->actingAs($user)->get('/guru/wali-kelas?per_page=1')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageSeating', true)
                ->has('students.data', 1)
                ->has('seating.students', 3)
                ->where('seating.seats.0.seat_number', 8)
                ->where('seating.seats.0.student_id', $students[0]->id));
    }

    public function test_seating_ignores_other_classes_past_years_and_ended_placements(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $oldYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $otherClass = Rombel::factory()->create();
        $other = Student::factory()->create();
        $this->placeStudent($other, $year, $otherClass);
        $ended = Student::factory()->create();
        $this->placeStudent($ended, $year, $rombel);
        StudentPlacement::query()->where('student_id', $ended->id)->update(['status' => 'ended']);
        foreach ([[$oldYear, $rombel, $other, 1], [$year, $otherClass, $other, 2], [$year, $rombel, $ended, 3]] as [$seatYear, $seatClass, $student, $seat]) {
            HomeroomSeat::query()->create([
                'academic_year_id' => $seatYear->id, 'rombel_id' => $seatClass->id,
                'seat_number' => $seat, 'student_id' => $student->id,
            ]);
        }

        $this->actingAs($user)->get('/guru/wali-kelas')->assertOk()
            ->assertInertia(fn ($page) => $page->has('seating.students', 0)->has('seating.seats', 0));
    }

    public function test_photo_contract_uses_public_storage_without_exposing_private_student_fields(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $student = Student::factory()->create(['photo_path' => 'students/seat-photo.jpg']);
        $this->placeStudent($student, $year, $rombel);

        $this->actingAs($user)->get('/guru/wali-kelas')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seating.students.0.photo_url', Storage::disk('public')->url('students/seat-photo.jpg'))
                ->missing('seating.students.0.photo_path')
                ->missing('seating.students.0.kantin_pin_hash')
                ->missing('seating.students.0.address'));
    }

    public function test_seating_ui_wires_assignment_touch_drag_swap_clear_and_unseated_only_search(): void
    {
        $source = file_get_contents(resource_path('js/pages/teacher/homeroom.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('length: 4', $source);
        $this->assertStringContainsString('length: 5', $source);
        $this->assertStringContainsString('Meja Guru', $source);
        $this->assertMatchesRegularExpression('/depth\s*\*\s*8\s*\+\s*group\s*\*\s*2\s*\+\s*side\s*\+\s*1/', $source);
        $this->assertStringContainsString('grid grid-cols-4 gap-10', $source);
        $this->assertStringContainsString('col-start-4 rounded-lg', $source);
        $this->assertStringNotContainsString('row *', $source);
        $this->assertStringContainsString('!seatedIds.has(id)', $source);
        $this->assertStringContainsString('options={seatOptions}', $source);
        $this->assertStringContainsString('TouchSensor', $source);
        $this->assertStringContainsString('onDragEnd={handleSeatDragEnd}', $source);
        $this->assertStringContainsString('next[sourceSeat] = targetStudent', $source);
        $this->assertStringContainsString('Clear · Kosongkan meja', $source);
        $this->assertStringContainsString('src={student.photo_url ?? undefined}', $source);
        $this->assertStringContainsString('grid h-44 w-full', $source);
        $this->assertStringContainsString('aspect-[2/3] h-auto w-full max-w-20', $source);
        $this->assertStringContainsString('aspect-auto rounded-md object-top', $source);
        $this->assertStringContainsString('router.put(', $source);
        $this->assertStringNotContainsString('Informasi tempat duduk belum tersedia.', $source);
    }

    public function test_teacher_can_assign_swap_move_replace_and_clear_students(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $first = Student::factory()->create();
        $second = Student::factory()->create();
        $third = Student::factory()->create();
        foreach ([$first, $second, $third] as $student) {
            $this->placeStudent($student, $year, $rombel);
        }
        $this->actingAs($user);
        foreach ([[1 => $first->id, 2 => $second->id], [1 => $second->id, 2 => $first->id], [3 => $second->id, 2 => $first->id], [3 => $third->id, 2 => $first->id], []] as $occupants) {
            $seats = $this->emptySeats();
            foreach ($occupants as $number => $studentId) {
                $seats[$number - 1]['student_id'] = $studentId;
            }
            $this->put('/guru/wali-kelas/tempat-duduk', [
                'seats' => $seats,
                'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
            ])->assertRedirect('/guru/wali-kelas');
            foreach ($occupants as $number => $studentId) {
                $this->assertDatabaseHas('tr_teacher_homeroom_seats', [
                    'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
                    'seat_number' => $number, 'student_id' => $studentId,
                ]);
            }
        }
        $this->assertDatabaseCount('tr_teacher_homeroom_seats', 40);
        $this->assertSame(0, HomeroomSeat::query()->whereNotNull('student_id')->count());
    }

    public function test_outsiders_inactive_students_and_duplicate_assignments_are_rejected_without_writes(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $student = Student::factory()->create();
        $this->placeStudent($student, $year, $rombel);
        $outsider = Student::factory()->create();
        $inactive = Student::factory()->create(['status' => 'inactive']);
        $this->placeStudent($inactive, $year, $rombel);
        $this->actingAs($user);
        foreach ([$outsider->id, $inactive->id] as $id) {
            $seats = $this->emptySeats();
            $seats[0]['student_id'] = $id;
            $this->put('/guru/wali-kelas/tempat-duduk', [
                'seats' => $seats,
                'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
            ])->assertSessionHasErrors('seats');
        }
        $seats = $this->emptySeats();
        $seats[0]['student_id'] = $student->id;
        $seats[1]['student_id'] = $student->id;
        $this->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $seats,
            'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
        ])->assertSessionHasErrors('seats.1.student_id');
        $this->assertDatabaseCount('tr_teacher_homeroom_seats', 0);
    }

    public function test_stale_snapshot_cannot_overwrite_existing_seats(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $version = HomeroomSeat::snapshot($year->id, $rombel->id)['version'];
        $student = Student::factory()->create();
        $this->placeStudent($student, $year, $rombel);
        HomeroomSeat::query()->create([
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'seat_number' => 1, 'student_id' => $student->id,
        ]);
        $this->actingAs($user)->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $this->emptySeats(), 'version' => $version,
        ])->assertSessionHasErrors('seats');
        $this->assertDatabaseHas('tr_teacher_homeroom_seats', [
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'seat_number' => 1, 'student_id' => $student->id,
        ]);
    }

    public function test_version_is_bound_to_class_and_year_and_duplicate_seat_numbers_are_rejected(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $otherClass = Rombel::factory()->create();
        $this->actingAs($user)->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $this->emptySeats(),
            'version' => HomeroomSeat::snapshot($year->id, $otherClass->id)['version'],
        ])->assertSessionHasErrors('seats');
        $seats = $this->emptySeats();
        $seats[1]['seat_number'] = 1;
        $this->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $seats,
            'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
        ])->assertSessionHasErrors('seats.1.seat_number');
        $this->assertDatabaseCount('tr_teacher_homeroom_seats', 0);
    }

    public function test_guest_unprivileged_and_view_only_users_cannot_update_seats(): void
    {
        $this->put('/guru/wali-kelas/tempat-duduk')->assertRedirect(route('login'));
        $user = User::factory()->create();
        $this->actingAs($user)->put('/guru/wali-kelas/tempat-duduk')->assertForbidden();
        $user->givePermissionTo('teacher.homeroom.view');
        $this->put('/guru/wali-kelas/tempat-duduk')->assertForbidden();
    }

    public function test_teacher_without_active_assignment_cannot_update_seats(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $this->emptySeats(), 'version' => str_repeat('0', 64),
        ])->assertNotFound();
    }

    public function test_invalid_layout_is_rejected_and_another_class_cannot_be_targeted_by_payload(): void
    {
        [$user, $year, $rombel] = $this->createHomeroom();
        $this->actingAs($user);
        $this->put('/guru/wali-kelas/tempat-duduk', [])->assertSessionHasErrors(['seats', 'version']);
        foreach ([array_slice($this->emptySeats(), 1), array_fill(0, 40, ['seat_number' => 41, 'student_id' => null])] as $seats) {
            $this->put('/guru/wali-kelas/tempat-duduk', [
                'seats' => $seats, 'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
            ])->assertSessionHasErrors();
        }
        $otherClass = Rombel::factory()->create();
        $this->put('/guru/wali-kelas/tempat-duduk', [
            'seats' => $this->emptySeats(),
            'version' => HomeroomSeat::snapshot($year->id, $rombel->id)['version'],
            'rombel_id' => $otherClass->id,
        ])->assertRedirect('/guru/wali-kelas');
        $this->assertDatabaseMissing('tr_teacher_homeroom_seats', ['rombel_id' => $otherClass->id]);
        $this->assertDatabaseCount('tr_teacher_homeroom_seats', 40);
    }

    /** @return array{User, AcademicYear, Rombel} */
    private function createHomeroom(): array
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create();
        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id, 'status' => 'active',
        ]);

        return [$user, $year, $rombel];
    }

    private function placeStudent(Student $student, AcademicYear $year, Rombel $rombel): void
    {
        StudentPlacement::query()->create([
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'student_id' => $student->id, 'status' => 'active',
        ]);
    }

    /** @return array<int, array{seat_number: int, student_id: null}> */
    private function emptySeats(): array
    {
        return array_map(fn (int $number): array => [
            'seat_number' => $number, 'student_id' => null,
        ], range(1, 40));
    }
}
