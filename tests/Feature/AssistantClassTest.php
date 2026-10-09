<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use App\Models\YearClass;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\AssistantToolRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'services.assistant.enabled' => true,
            'services.assistant.plain_mode' => false,
            'services.assistant.system_prompt_only' => false,
        ]);
    }

    public function test_every_authenticated_user_receives_the_class_query_tool(): void
    {
        $user = User::factory()->unverified()->create();
        $tools = app(AssistantToolRegistry::class)->forUser($user);
        $tool = collect($tools)->firstWhere('function.name', 'curriculum_class_query');

        $this->assertNotNull($tool);
        $this->assertFalse($tool['function']['parameters']['additionalProperties']);
        $this->assertSame(['string', 'null'], $tool['function']['parameters']['properties']['class_search']['type']);
        $this->assertSame(50, $tool['function']['parameters']['properties']['per_page']['maximum']);
    }

    public function test_class_query_defaults_to_active_year_and_returns_allowlisted_class_data(): void
    {
        AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $activeYear = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $rombel = Rombel::factory()->create([
            'code' => 'VII-A',
            'name' => 'Kelas VII A',
            'grade_level' => 'VII',
            'parallel_code' => 'A',
            'status' => 'active',
        ]);
        $teacher = Teacher::factory()->create(['full_name' => 'Wali Aktif', 'status' => 'active', 'staff_type' => 'guru']);
        $student = Student::factory()->create(['nis' => 'S-001', 'full_name' => 'Siswa Aktif', 'status' => 'active']);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post('/kurikulum/management-class/classes', [
            'academic_year_id' => $activeYear->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id,
        ])->assertRedirect();
        StudentPlacement::query()->create([
            'academic_year_id' => $activeYear->id,
            'rombel_id' => $rombel->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        $result = app(AssistantToolExecutor::class)->execute($admin, 'curriculum_class_query', [
            'include' => ['class_summary', 'homeroom_teacher', 'student_count', 'student_placements'],
        ]);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame($activeYear->id, $result['data'][0]['academic_year']['id']);
        $this->assertSame('VII-A', $result['data'][0]['class_summary']['code']);
        $this->assertSame(['id' => $teacher->id, 'full_name' => 'Wali Aktif'], $result['data'][0]['homeroom_teacher']);
        $this->assertSame(1, $result['data'][0]['student_count']);
        $this->assertSame(['full_name' => 'Siswa Aktif'], $result['data'][0]['student_placements'][0]);
        $this->assertArrayNotHasKey('id', $result['data'][0]['student_placements'][0]);
        $this->assertArrayNotHasKey('nis', $result['data'][0]['student_placements'][0]);
        $this->assertArrayNotHasKey('email', $result['data'][0]['student_placements'][0]);
        $this->assertArrayNotHasKey('status', $result['data'][0]['student_placements'][0]);
    }

    public function test_class_query_search_and_explicit_year_are_bounded(): void
    {
        $oldYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $activeYear = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $match = Rombel::factory()->create(['code' => 'VIII-B', 'name' => 'Kelas VIII B', 'status' => 'active']);
        $other = Rombel::factory()->create(['code' => 'VIII-C', 'name' => 'Kelas VIII C', 'status' => 'active']);
        foreach ([$match, $other] as $rombel) {
            YearClass::query()->create(['academic_year_id' => $oldYear->id, 'rombel_id' => $rombel->id]);
        }
        YearClass::query()->create(['academic_year_id' => $activeYear->id, 'rombel_id' => $match->id]);
        $user = User::factory()->unverified()->create();

        $result = app(AssistantToolExecutor::class)->execute($user, 'curriculum_class_query', [
            'academic_year_search' => '2025/2026',
            'class_search' => 'VIII-B',
            'page' => 1,
            'per_page' => 1,
            'include' => ['class_summary'],
        ]);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame($oldYear->id, $result['data'][0]['academic_year']['id']);
        $this->assertSame('VIII-B', $result['data'][0]['class_summary']['code']);
        $this->assertNull($result['data'][0]['student_count']);
        $this->assertSame(1, $result['meta']['last_page']);
    }

    public function test_student_search_returns_only_matching_classes_and_keeps_total_class_count(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $matchingRombel = Rombel::factory()->create(['code' => 'VII-A', 'name' => 'Kelas VII A', 'status' => 'active']);
        $otherRombel = Rombel::factory()->create(['code' => 'VII-B', 'name' => 'Kelas VII B', 'status' => 'active']);
        YearClass::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $matchingRombel->id]);
        YearClass::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $otherRombel->id]);
        $matchingStudent = Student::factory()->create(['full_name' => 'Budi Pratama', 'status' => 'active']);
        $otherMatchingStudent = Student::factory()->create(['full_name' => 'Dewi Anggraini', 'status' => 'active']);
        $otherStudent = Student::factory()->create(['full_name' => 'Citra Lestari', 'status' => 'active']);
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $matchingRombel->id, 'student_id' => $matchingStudent->id, 'status' => 'active']);
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $matchingRombel->id, 'student_id' => $otherMatchingStudent->id, 'status' => 'active']);
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $otherRombel->id, 'student_id' => $otherStudent->id, 'status' => 'active']);
        $user = User::factory()->unverified()->create();

        $result = app(AssistantToolExecutor::class)->execute($user, 'curriculum_class_query', [
            'student_search' => 'Budi',
            'include' => ['class_summary', 'student_count', 'student_placements'],
        ]);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('VII-A', $result['data'][0]['class_summary']['code']);
        $this->assertSame(2, $result['data'][0]['student_count']);
        $this->assertSame('Budi Pratama', $result['data'][0]['student_placements'][0]['full_name']);
    }

    public function test_class_query_excludes_inactive_records_and_rejects_unknown_arguments(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $rombel = Rombel::factory()->create(['status' => 'inactive']);
        YearClass::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id]);
        $user = User::factory()->unverified()->create();
        $executor = app(AssistantToolExecutor::class);

        $result = $executor->execute($user, 'curriculum_class_query', ['include' => ['class_summary']]);
        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['meta']['total']);

        $this->expectException(\InvalidArgumentException::class);
        $executor->execute($user, 'curriculum_class_query', ['sql' => 'select 1']);
    }

    public function test_class_query_tool_is_executable_without_inventory_permission(): void
    {
        $user = User::factory()->unverified()->create();
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'class-query',
                'type' => 'function',
                'function' => ['name' => 'curriculum_class_query', 'arguments' => '{}'],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Data kelas tersedia.']]]]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Tampilkan kelas'])
            ->assertOk()
            ->assertJsonPath('message', 'Data kelas tersedia.');

        $names = collect(Http::recorded()[0][0]->data()['tools'] ?? [])
            ->map(fn (array $tool): string => $tool['function']['name'])
            ->all();
        $this->assertContains('curriculum_class_query', $names);
    }
}
