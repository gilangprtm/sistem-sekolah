<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Services\StudentCsvImporter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_students_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('students/index'));
    }

    public function test_super_admin_can_open_student_create_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/create')
                ->where('availableAccounts', fn ($accounts): bool => $accounts->isEmpty()));
    }

    public function test_super_admin_can_open_student_edit_page_with_current_linked_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($admin)
            ->get("/students/{$studentId}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/edit')
                ->where('student.id', $studentId)
                ->where('student.nisn', null)
                ->where('availableAccounts', fn ($accounts): bool => $accounts->count() === 1
                    && $accounts->first()['id'] === $studentUser->id));
    }

    public function test_super_admin_can_create_student_with_available_siswa_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $studentUser->id,
                'nis' => 'NIS-001',
                'nisn' => 'NISN-001',
                'full_name' => 'Siti Aminah',
                'gender' => 'P',
                'birth_place' => 'Bandung',
                'birth_date' => '2012-05-10',
                'address' => 'Jl. Melati',
                'phone' => '081234567890',
                'mother_name' => 'Ibu Siti',
                'father_name' => 'Bapak Amin',
                'mother_occupation' => 'Pedagang',
                'father_occupation' => 'Wiraswasta',
                'parent_phone' => '081298765432',
                'status' => 'active',
            ])
            ->assertRedirect('/students')
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Data Siswa berhasil dibuat.');

        $this->assertDatabaseHas('m_students', [
            'user_id' => $studentUser->id,
            'nis' => 'NIS-001',
            'nisn' => 'NISN-001',
            'full_name' => 'Siti Aminah',
            'gender' => 'P',
            'phone' => '081234567890',
            'mother_name' => 'Ibu Siti',
            'father_name' => 'Bapak Amin',
            'mother_occupation' => 'Pedagang',
            'father_occupation' => 'Wiraswasta',
            'parent_phone' => '081298765432',
            'status' => 'active',
        ]);
    }

    public function test_student_contact_and_parent_data_are_exposed_on_edit_but_not_card_payload(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $student = Student::factory()->create([
            'full_name' => 'Siswa Kontak',
            'phone' => '081234567890',
            'mother_name' => 'Ibu Siswa',
            'father_name' => 'Bapak Siswa',
            'mother_occupation' => 'Guru',
            'father_occupation' => 'Petani',
            'parent_phone' => '081298765432',
        ]);

        $this->actingAs($admin)
            ->get("/students/{$student->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('student.phone', '081234567890')
                ->where('student.mother_name', 'Ibu Siswa')
                ->where('student.father_name', 'Bapak Siswa')
                ->where('student.mother_occupation', 'Guru')
                ->where('student.father_occupation', 'Petani')
                ->where('student.parent_phone', '081298765432'));

        $this->actingAs($admin)
            ->get(route('students.cards.show', $student))
            ->assertInertia(fn ($page) => $page
                ->missing('student.phone')
                ->missing('student.mother_name')
                ->missing('student.father_name')
                ->missing('student.parent_phone'));
    }

    public function test_student_contact_and_parent_data_are_validated(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Data Terlalu Panjang',
                'phone' => str_repeat('1', 31),
                'mother_name' => str_repeat('I', 256),
                'parent_phone' => str_repeat('2', 31),
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['phone', 'mother_name', 'parent_phone']);
    }

    public function test_student_excel_template_has_metadata_example_and_exact_contract_columns(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get(route('students.import.template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition', 'attachment; filename=template-master-siswa.xlsx');
        $archive = new \ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'student-template-test-');
        file_put_contents($path, $response->streamedContent());
        $this->assertSame(true, $archive->open($path));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        @unlink($path);
        $this->assertIsString($sheet);
        $this->assertStringContainsString('nis', $sheet);
        $this->assertStringContainsString('Nomor Induk Siswa', $sheet);
        $this->assertStringContainsString('Siti Aminah', $sheet);
    }

    public function test_student_excel_import_ignores_metadata_and_example_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $xlsx = $this->studentXlsx([
            ['NIS-XLSX-001', 'NISN-XLSX-001', '', 'Siswa Excel', 'P', '', '', '', '', '', '', '', '', '', 'active'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.xlsx', $xlsx)])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', '1 data Siswa berhasil diimpor (1 dibuat, 0 diperbarui).');

        $this->assertDatabaseHas('m_students', ['nis' => 'NIS-XLSX-001', 'full_name' => 'Siswa Excel']);
        $this->assertDatabaseMissing('m_students', ['full_name' => 'Siti Aminah']);
    }

    public function test_student_excel_import_rejects_blank_data_row_and_malformed_file(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.xlsx', $this->studentXlsx([array_fill(0, 15, '')]))])
            ->assertSessionHasErrors('rows.4');

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.xlsx', 'not an xlsx')])
            ->assertSessionHasErrors('file');
    }

    public function test_student_excel_import_accepts_300_rows_and_rejects_301_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $rows = [];

        for ($index = 1; $index <= 300; $index++) {
            $rows[] = [sprintf('XLSX-NIS-%03d', $index), sprintf('XLSX-NISN-%03d', $index), '', "Siswa Excel {$index}", '', '', '', '', '', '', '', '', '', '', 'active'];
        }

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.xlsx', $this->studentXlsx($rows))])
            ->assertRedirect();
        $this->assertDatabaseCount('m_students', 300);

        $rows[] = ['XLSX-NIS-301', 'XLSX-NISN-301', '', 'Siswa Excel 301', '', '', '', '', '', '', '', '', '', '', 'active'];
        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.xlsx', $this->studentXlsx($rows))])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('m_students', 300);
    }

    public function test_student_csv_import_creates_rows_without_account_linkage(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $csv = $this->studentCsv([
            ['NIS-CSV-001', 'NISN-CSV-001', '2024', 'Siswa CSV', 'P', 'Denpasar', '2012-01-02', 'Jl. CSV', '0812', 'Ibu CSV', 'Bapak CSV', 'Guru', 'Wiraswasta', '0813', 'active'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', '1 data Siswa berhasil diimpor (1 dibuat, 0 diperbarui).');

        $this->assertDatabaseHas('m_students', [
            'nis' => 'NIS-CSV-001',
            'nisn' => 'NISN-CSV-001',
            'full_name' => 'Siswa CSV',
            'phone' => '0812',
            'parent_phone' => '0813',
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_student_csv_import_updates_by_nis_or_nisn_and_preserves_account_and_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $photoPath = 'students/existing/photo.jpg';
        Storage::disk('public')->put($photoPath, 'photo');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'nis' => 'NIS-EXISTING',
            'nisn' => 'NISN-EXISTING',
            'photo_path' => $photoPath,
            'full_name' => 'Nama Lama',
        ]);
        $csv = $this->studentCsv([
            ['NIS-EXISTING', '', '', 'Nama Baru', '', '', '', '', '0814', '', '', '', '', '', 'inactive'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame($studentUser->id, $student->user_id);
        $this->assertSame($photoPath, $student->photo_path);
        $this->assertSame('Nama Baru', $student->full_name);
        $this->assertSame('NISN-EXISTING', $student->nisn);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_student_csv_import_rejects_nis_nisn_collision_atomically(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['nis' => 'NIS-COLLISION', 'nisn' => null]);
        Student::factory()->create(['nis' => null, 'nisn' => 'NISN-COLLISION']);
        $csv = $this->studentCsv([
            ['NIS-COLLISION', 'NISN-COLLISION', '', 'Siswa Collision', '', '', '', '', '', '', '', '', '', '', 'active'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('m_students', 2);
    }

    public function test_student_csv_import_reports_field_row_errors_and_rejects_invalid_utf8(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $csv = $this->studentCsv([
            ['NIS-ERROR-001', '', '', 'Siswa Error', 'X', '', '', '', '', '', '', '', '', '', 'active'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertSessionHasErrors('rows.1.gender');

        $invalidUtf8 = implode(',', [
            'nis', 'nisn', 'tahun_angkatan', 'full_name', 'gender', 'birth_place', 'birth_date',
            'address', 'phone', 'mother_name', 'father_name', 'mother_occupation',
            'father_occupation', 'parent_phone', 'status',
        ])."\r\nNIS-INVALID, , ,Nama \xB1, , , , , , , , , , ,active\r\n";

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $invalidUtf8)])
            ->assertSessionHasErrors('file');
    }

    public function test_student_csv_import_rejects_malformed_row_atomically(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $csv = $this->studentCsv([
            ['NIS-ATOMIC-001', '', '', 'Siswa Sebelum Error', '', '', '', '', '', '', '', '', '', '', 'active'],
            ['NIS-ATOMIC-002', '', '', '', 'X', '', '', '', '', '', '', '', '', '', 'active'],
        ]);

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertSessionHasErrors('rows.2.full_name');

        $this->assertDatabaseMissing('m_students', ['nis' => 'NIS-ATOMIC-001']);
    }

    public function test_student_csv_import_accepts_300_rows_and_rejects_301_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $rows = [];

        for ($index = 1; $index <= 300; $index++) {
            $rows[] = [sprintf('NIS-%03d', $index), sprintf('NISN-%03d', $index), '', "Siswa {$index}", '', '', '', '', '', '', '', '', '', '', 'active'];
        }

        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $this->studentCsv($rows))])
            ->assertRedirect();
        $this->assertDatabaseCount('m_students', 300);

        $rows[] = ['NIS-301', 'NISN-301', '', 'Siswa 301', '', '', '', '', '', '', '', '', '', '', 'active'];
        $this->actingAs($admin)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $this->studentCsv($rows))])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('m_students', 300);
    }

    public function test_student_csv_import_requires_create_and_update_permissions(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Guru');
        $operator->givePermissionTo('student.view');
        $csv = $this->studentCsv([
            ['NIS-SECURITY-001', '', '', 'Siswa Security', '', '', '', '', '', '', '', '', '', '', 'active'],
        ]);

        $this->actingAs($operator)
            ->post(route('students.import'), ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertForbidden();
        $this->assertDatabaseMissing('m_students', ['nis' => 'NIS-SECURITY-001']);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function studentXlsx(array $rows): string
    {
        $template = (new StudentCsvImporter)->templateXlsx();
        $path = tempnam(sys_get_temp_dir(), 'student-xlsx-test-');
        file_put_contents($path, $template);
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($path));
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertFalse(str_contains($sheet, '<cols>'));
        $this->assertFalse($archive->locateName('xl/styles.xml') !== false);
        $data = '';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 4;
            $data .= '<row r="'.$number.'">';

            foreach ($row as $columnIndex => $value) {
                $column = '';
                $numberForColumn = $columnIndex + 1;

                while ($numberForColumn > 0) {
                    $remainder = ($numberForColumn - 1) % 26;
                    $column = chr(65 + $remainder).$column;
                    $numberForColumn = intdiv($numberForColumn - 1, 26);
                }

                if ($value !== '') {
                    $data .= '<c r="'.$column.$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
            }

            $data .= '</row>';
        }

        $this->assertTrue(is_string($sheet));
        $archive->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', $data.'</sheetData>', $sheet));
        $archive->close();
        $xlsx = file_get_contents($path);
        @unlink($path);

        $this->assertIsString($xlsx);

        return $xlsx;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function studentCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, [
            'nis', 'nisn', 'tahun_angkatan', 'full_name', 'gender', 'birth_place', 'birth_date',
            'address', 'phone', 'mother_name', 'father_name', 'mother_occupation',
            'father_occupation', 'parent_phone', 'status',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function test_student_create_persists_nisn_and_exposes_it_on_edit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Dengan NISN',
                'nisn' => 'NISN-002',
                'status' => 'active',
            ])
            ->assertRedirect('/students')
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Data Siswa berhasil dibuat.');

        $student = Student::query()->firstOrFail();

        $this->assertDatabaseHas('m_students', [
            'id' => $student->id,
            'nisn' => 'NISN-002',
        ]);

        $this->actingAs($admin)
            ->get("/students/{$student->id}/edit")
            ->assertInertia(fn ($page) => $page->where('student.nisn', 'NISN-002'));
    }

    public function test_student_create_persists_tahun_angkatan_and_exposes_it_on_the_index(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Angkatan 2024',
                'tahun_angkatan' => 2024,
                'status' => 'active',
            ])
            ->assertRedirect('/students');

        $studentId = (int) $this->app['db']->table('m_students')->value('id');
        $this->assertDatabaseHas('m_students', [
            'id' => $studentId,
            'tahun_angkatan' => 2024,
        ]);

        $this->actingAs($admin)
            ->get('/students')
            ->assertInertia(fn ($page) => $page
                ->where('students.data.0.id', $studentId)
                ->where('students.data.0.tahun_angkatan', 2024));
    }

    public function test_student_tahun_angkatan_must_be_a_valid_year_when_provided(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Angkatan Invalid',
                'tahun_angkatan' => 'dua ribu dua puluh empat',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('tahun_angkatan');
    }

    public function test_student_create_persists_official_photo_and_exposes_it_on_the_form(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $photo = UploadedFile::fake()->create('student-photo.jpg', 100, 'image/jpeg');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Dengan Foto',
                'status' => 'active',
                'photo' => $photo,
            ])
            ->assertRedirect('/students');

        $student = Student::query()->firstOrFail();

        $this->assertNotNull($student->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));

        $this->actingAs($admin)
            ->get("/students/{$student->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->where('student.id', $student->id)
                ->where('student.photo_url', fn ($url): bool => is_string($url) && $url !== ''));
    }

    public function test_student_photo_must_be_an_image_within_one_megabyte(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Dokumen',
                'status' => 'active',
                'photo' => UploadedFile::fake()->create('student.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Siswa Foto Besar',
                'status' => 'active',
                'photo' => UploadedFile::fake()->create('student.jpg', 1025, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_student_year_input_accepts_only_digits_from_the_form_contract(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students/create')
            ->assertInertia(fn ($page) => $page->component('students/create'));

        $source = file_get_contents(resource_path('js/components/students/student-form.tsx'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString("replace(/\\D/g, '')", $source);
    }

    public function test_student_create_rejects_non_siswa_or_already_linked_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $guru->id,
                'full_name' => 'Invalid Account',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');

        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ])->assertRedirect('/students');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $studentUser->id,
                'full_name' => 'Duplicate Student',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_student_create_page_only_exposes_unlinked_siswa_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $linkedAccount = User::factory()->create();
        $linkedAccount->assignRole('Siswa');
        $unlinkedAccount = User::factory()->create();
        $unlinkedAccount->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $linkedAccount->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/students/create')
            ->assertInertia(fn ($page) => $page
                ->where('availableAccounts', fn ($accounts): bool => $accounts->count() === 1
                    && $accounts->first()['id'] === $unlinkedAccount->id
                    && array_keys($accounts->first()) === ['id', 'email']));
    }

    public function test_student_validation_rejects_duplicate_nisn(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/students', [
            'nisn' => 'NISN-001',
            'full_name' => 'First Student',
            'status' => 'active',
        ])->assertRedirect('/students');

        $this->actingAs($admin)
            ->post('/students', [
                'nisn' => 'NISN-001',
                'full_name' => 'Second Student',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('nisn');
    }

    public function test_student_validation_rejects_duplicate_nis_and_missing_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'nis' => 'NIS-001',
                'full_name' => '',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('full_name');

        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'nis' => 'NIS-001',
            'full_name' => 'First Student',
            'status' => 'active',
        ])->assertRedirect('/students');

        $secondUser = User::factory()->create();
        $secondUser->assignRole('Siswa');
        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $secondUser->id,
                'nis' => 'NIS-001',
                'full_name' => 'Second Student',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('nis');
    }

    public function test_student_list_search_includes_nisn(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create([
            'full_name' => 'Siswa Dengan NISN',
            'nisn' => 'NISN-SEARCH-001',
        ]);

        $this->actingAs($admin)
            ->get('/students?search=NISN-SEARCH-001')
            ->assertInertia(fn ($page) => $page
                ->where('students.total', 1)
                ->where('students.data.0.nisn', 'NISN-SEARCH-001'));
    }

    public function test_student_list_supports_search_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students?search=aminah&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('students/index')
                ->where('filters.search', 'aminah')
                ->where('students.per_page', 1));
    }

    public function test_student_list_applies_gender_and_status_filters_server_side(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create([
            'full_name' => 'Siswa Aktif Perempuan',
            'gender' => 'P',
            'status' => 'active',
        ]);
        Student::factory()->create([
            'full_name' => 'Siswa Tidak Aktif Laki-laki',
            'gender' => 'L',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->get('/students?gender=P&status=active')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.gender', 'P')
                ->where('filters.status', 'active')
                ->where('students.total', 1)
                ->where('students.data.0.full_name', 'Siswa Aktif Perempuan'));
    }

    public function test_student_list_shows_linked_and_unlinked_accounts_by_default(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $account = User::factory()->create();
        Student::factory()->create(['user_id' => $account->id, 'full_name' => 'A Terhubung']);
        Student::factory()->create(['full_name' => 'B Tidak Terhubung']);

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->where('students.total', 2)
                ->where('students.data.0.user.id', $account->id)
                ->where('students.data.1.user', null));
    }

    public function test_student_list_filters_connected_accounts_with_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $firstAccount = User::factory()->create();
        $secondAccount = User::factory()->create();
        Student::factory()->create(['user_id' => $firstAccount->id, 'full_name' => 'A Terhubung']);
        $second = Student::factory()->create(['user_id' => $secondAccount->id, 'full_name' => 'B Terhubung']);
        Student::factory()->create(['full_name' => 'C Tidak Terhubung']);

        $this->actingAs($admin)
            ->get('/students?account=connected&per_page=1&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.account', 'connected')
                ->where('students.total', 2)
                ->where('students.per_page', 1)
                ->where('students.current_page', 2)
                ->has('students.data', 1)
                ->where('students.data.0.id', $second->id)
                ->where('students.data.0.user.id', $secondAccount->id));
    }

    public function test_student_list_combines_unconnected_accounts_with_existing_filters(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $target = Student::factory()->create([
            'full_name' => 'Siswa Target', 'gender' => 'P', 'status' => 'active',
        ]);
        Student::factory()->create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Siswa Terhubung', 'gender' => 'P', 'status' => 'active',
        ]);
        Student::factory()->create([
            'full_name' => 'Siswa Laki-laki', 'gender' => 'L', 'status' => 'active',
        ]);
        Student::factory()->create([
            'full_name' => 'Siswa Nonaktif', 'gender' => 'P', 'status' => 'inactive',
        ]);
        Student::factory()->create([
            'full_name' => 'Nama Lain', 'gender' => 'P', 'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/students?account=unconnected&gender=P&status=active&search=Siswa')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.account', 'unconnected')
                ->where('students.total', 1)
                ->where('students.data.0.id', $target->id)
                ->where('students.data.0.user', null));
    }

    public function test_student_list_ignores_unknown_account_filter_values(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['user_id' => User::factory()->create()->id]);
        Student::factory()->create();

        $this->actingAs($admin)
            ->get('/students?account=unknown')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('students.total', 2));
    }

    public function test_non_admin_and_guest_cannot_manage_students(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($guru)->get('/students')->assertForbidden();
        $this->app['auth']->logout();
        $this->get('/students')->assertRedirect('/login');
    }

    public function test_student_rejects_future_birth_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Future Student',
                'birth_date' => now()->addDay()->toDateString(),
                'status' => 'active',
            ])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_account_assignment_permission_is_required_to_attach_or_unlink(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $operator = User::factory()->create();
        $operator->assignRole('Guru');
        $operator->givePermissionTo(['student.view', 'student.update']);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Assigned Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($operator)
            ->patch("/students/{$studentId}", [
                'user_id' => null,
                'full_name' => 'Assigned Student',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_index_preserves_currently_linked_account_for_edit_context(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/students')
            ->assertInertia(fn ($page) => $page
                ->where('students.data.0.user.id', $studentUser->id)
                ->where('students.data.0.user.email', $studentUser->email));
    }

    public function test_deleting_linked_user_nulls_student_account_reference(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Unlinked After Delete',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $studentUser->delete();

        $this->assertDatabaseHas('m_students', [
            'id' => $studentId,
            'user_id' => null,
        ]);
    }

    public function test_student_index_consumes_success_flash_toast(): void
    {
        $source = file_get_contents(resource_path('js/layouts/app/dashboard-layout.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("import { useFlashToast } from '@/hooks/use-flash-toast';", $source);
        $this->assertStringContainsString('useFlashToast();', $source);
    }

    public function test_super_admin_can_update_and_delete_student(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Initial Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($admin)
            ->patch("/students/{$studentId}", [
                'user_id' => $studentUser->id,
                'nisn' => 'NISN-UPDATED',
                'full_name' => 'Updated Student',
                'status' => 'inactive',
            ])
            ->assertRedirect('/students')
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Data Siswa berhasil diperbarui.');
        $this->assertDatabaseHas('m_students', ['id' => $studentId, 'nisn' => 'NISN-UPDATED', 'full_name' => 'Updated Student', 'status' => 'inactive']);

        $this->actingAs($admin)->delete("/students/{$studentId}")->assertRedirect();
        $this->assertDatabaseMissing('m_students', ['id' => $studentId]);
    }
}
