<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\Assistant\AssistantToolExecutor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantTeacherSubjectTest extends TestCase
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

    public function test_unverified_user_receives_and_executes_teacher_subjects_without_permissions(): void
    {
        $user = User::factory()->unverified()->create();
        $teacher = Teacher::factory()->create(['full_name' => 'Budi Santoso']);
        $subject = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'code' => 'MTK01',
        ]);

        $this->fakeTeacherSubjectProvider(['subject_search' => 'matematika']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari guru matematika'])
            ->assertOk();

        $firstRequest = Http::recorded()[0][0];
        $tools = collect($firstRequest->data()['tools'] ?? []);
        $this->assertSame(['curriculum_class_query', 'teacher_subjects'], $tools->map(fn (array $tool): string => $tool['function']['name'])->all());

        $result = $this->toolResultFromRecordedRequest();
        $this->assertSame(['Budi Santoso'], $result['data']);
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1], $result['meta']);
        $this->assertArrayNotHasKey('id', $result['data']);
        $this->assertStringNotContainsString('MTK', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('01', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_teacher_subjects_schema_declares_defaults_and_strict_properties(): void
    {
        $user = User::factory()->unverified()->create();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Halo'])
            ->assertOk();

        $schema = Http::recorded()[0][0]->data()['tools'][1]['function']['parameters'];
        $this->assertFalse($schema['additionalProperties']);
        $this->assertArrayNotHasKey('required', $schema);
        $this->assertSame(['string', 'null'], $schema['properties']['subject_search']['type']);
        $this->assertSame(100, $schema['properties']['subject_search']['maxLength']);
        $this->assertSame(1, $schema['properties']['page']['default']);
        $this->assertSame(25, $schema['properties']['per_page']['default']);
        $this->assertSame(1, $schema['properties']['page']['minimum']);
        $this->assertSame(50, $schema['properties']['page']['maximum']);
        $this->assertSame(1, $schema['properties']['per_page']['minimum']);
        $this->assertSame(50, $schema['properties']['per_page']['maximum']);
    }

    public function test_inventory_permission_keeps_inventory_tools_and_adds_teacher_subjects(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Siap.']]]])]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Halo'])
            ->assertOk();

        $names = collect(Http::recorded()[0][0]->data()['tools'] ?? [])
            ->map(fn (array $tool): string => $tool['function']['name'])
            ->all();
        $this->assertSame(['curriculum_class_query', 'inventory_items', 'inventory_registers', 'inventory_rooms', 'inventory_categories', 'teacher_subjects'], $names);
    }

    public function test_teacher_subjects_all_mode_accepts_omitted_null_and_whitespace_search(): void
    {
        $user = User::factory()->unverified()->create();
        $subject = Subject::factory()->create(['code' => 'ALL', 'name' => 'All Subject']);
        $teachers = collect(['Alpha Teacher', 'Beta Teacher'])->map(function (string $name) use ($subject): Teacher {
            $teacher = Teacher::factory()->create(['full_name' => $name]);
            TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'ALL'.$teacher->id]);

            return $teacher;
        });

        foreach ([[], ['subject_search' => null], ['subject_search' => '   ']] as $arguments) {
            $result = app(AssistantToolExecutor::class)->execute($user, 'teacher_subjects', $arguments);
            $this->assertSame($teachers->pluck('full_name')->sort()->values()->all(), $result['data']);
            $this->assertSame(2, $result['meta']['total']);
        }
    }

    public function test_teacher_subjects_all_mode_uses_database_pagination_before_mapping_names(): void
    {
        $user = User::factory()->unverified()->create();
        $subject = Subject::factory()->create(['code' => 'PAGE', 'name' => 'Page Subject']);
        $first = Teacher::factory()->create(['full_name' => 'Alpha']);
        $second = Teacher::factory()->create(['full_name' => 'Beta']);
        $third = Teacher::factory()->create(['full_name' => 'Gamma']);
        foreach ([$first, $second, $third] as $teacher) {
            TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'PAGE'.$teacher->id]);
            TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => Subject::factory()->create(['code' => 'X'.$teacher->id, 'name' => 'Extra '.$teacher->id])->id, 'code' => 'X'.$teacher->id.'01']);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'm_teacher')) {
                $queries[] = $query->sql;
            }
        });

        $result = app(AssistantToolExecutor::class)->execute($user, 'teacher_subjects', ['page' => 2, 'per_page' => 2]);
        $this->assertSame(['Gamma'], $result['data']);
        $this->assertSame(3, $result['meta']['total']);
        $this->assertNotEmpty($queries);
        $this->assertTrue(collect($queries)->contains(fn (string $sql): bool => str_contains(strtolower($sql), 'limit')));
    }

    public function test_teacher_subjects_filters_active_anchors_deduplicates_and_sorts_names(): void
    {
        $user = User::factory()->unverified()->create();
        $budi = Teacher::factory()->create(['full_name' => 'Budi Santoso']);
        $andi = Teacher::factory()->create(['full_name' => 'Andi Saputra']);
        $inactiveTeacher = Teacher::factory()->create(['full_name' => 'Inactive Teacher', 'status' => 'inactive']);
        $matching = Subject::factory()->create(['code' => 'SCI', 'name' => 'Science']);
        $otherMatching = Subject::factory()->create(['code' => 'SCI2', 'name' => 'Science Advanced']);
        $inactiveSubject = Subject::factory()->create(['code' => 'SCI3', 'name' => 'Science Inactive', 'status' => 'inactive']);
        TeacherSubject::factory()->create(['teacher_id' => $budi->id, 'subject_id' => $matching->id, 'code' => 'SCI01']);
        TeacherSubject::factory()->create(['teacher_id' => $budi->id, 'subject_id' => $otherMatching->id, 'code' => 'SCI202']);
        TeacherSubject::factory()->create(['teacher_id' => $andi->id, 'subject_id' => $matching->id, 'code' => 'SCI03']);
        TeacherSubject::factory()->create(['teacher_id' => $inactiveTeacher->id, 'subject_id' => $matching->id, 'code' => 'SCI04']);
        TeacherSubject::factory()->create(['teacher_id' => $budi->id, 'subject_id' => $inactiveSubject->id, 'code' => 'SCI305']);

        $this->fakeTeacherSubjectProvider(['subject_search' => 'science']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assistant/chat', ['message' => 'Cari guru science'])
            ->assertOk();

        $result = $this->toolResultFromRecordedRequest();
        $this->assertSame(['Andi Saputra', 'Budi Santoso'], $result['data']);
        $this->assertSame(2, $result['meta']['total']);
    }

    public function test_duplicate_teacher_names_are_ordered_by_teacher_id(): void
    {
        $user = User::factory()->unverified()->create();
        $subject = Subject::factory()->create(['code' => 'DUP', 'name' => 'Duplicate']);
        $first = Teacher::factory()->create(['full_name' => 'same name']);
        $second = Teacher::factory()->create(['full_name' => 'Same Name']);
        TeacherSubject::factory()->create(['teacher_id' => $first->id, 'subject_id' => $subject->id, 'code' => 'DUP01']);
        TeacherSubject::factory()->create(['teacher_id' => $second->id, 'subject_id' => $subject->id, 'code' => 'DUP02']);

        $this->fakeTeacherSubjectProvider(['subject_search' => 'duplicate']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/assistant/chat', ['message' => 'Cari guru duplicate'])->assertOk();

        $result = $this->toolResultFromRecordedRequest();
        $this->assertSame(['same name', 'Same Name'], $result['data']);
    }

    public function test_teacher_subjects_paginates_and_returns_empty_result_with_last_page_one(): void
    {
        $user = User::factory()->unverified()->create();
        $subject = Subject::factory()->create(['code' => 'BIO', 'name' => 'Biology']);
        foreach (['Citra', 'Dian', 'Eka'] as $name) {
            $teacher = Teacher::factory()->create(['full_name' => $name]);
            TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'BIO'.substr($name, 0, 2)]);
        }

        $this->fakeTeacherSubjectProvider(['subject_search' => 'biology', 'page' => 2, 'per_page' => 2]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/assistant/chat', ['message' => 'Cari guru biology'])->assertOk();
        $result = $this->toolResultFromRecordedRequest();
        $this->assertSame(['Eka'], $result['data']);
        $this->assertSame(['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $result['meta']);

    }

    public function test_teacher_subjects_empty_result_has_last_page_one(): void
    {
        $user = User::factory()->unverified()->create();
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'teacher-subject-empty', 'type' => 'function', 'arguments' => '{}',
                'function' => ['name' => 'teacher_subjects', 'arguments' => json_encode(['subject_search' => 'physics'])],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Tidak ditemukan.']]]]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/assistant/chat', ['message' => 'Cari guru physics'])->assertOk();
        $empty = $this->toolResultFromRecordedRequest();
        $this->assertSame([], $empty['data']);
        $this->assertSame(['current_page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1], $empty['meta']);
    }

    public function test_teacher_subjects_all_mode_returns_empty_page_for_out_of_range_page(): void
    {
        $user = User::factory()->unverified()->create();
        $result = app(AssistantToolExecutor::class)->execute($user, 'teacher_subjects', [
            'subject_search' => null,
            'page' => 50,
            'per_page' => 25,
        ]);

        $this->assertSame([], $result['data']);
        $this->assertSame(['current_page' => 50, 'per_page' => 25, 'total' => 0, 'last_page' => 1], $result['meta']);
    }

    public function test_teacher_subjects_rejects_invalid_arguments_and_unknown_properties(): void
    {
        $user = User::factory()->unverified()->create();
        $executor = app(AssistantToolExecutor::class);

        $this->expectException(\InvalidArgumentException::class);
        $executor->execute($user, 'teacher_subjects', ['subject_search' => str_repeat('x', 101)]);
    }

    private function fakeTeacherSubjectProvider(array $arguments): void
    {
        Http::fakeSequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'tool_calls' => [[
                'id' => 'teacher-subject-call',
                'type' => 'function',
                'function' => ['name' => 'teacher_subjects', 'arguments' => $arguments === [] ? '{}' : json_encode($arguments)],
            ]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Data tersedia.']]]]);
    }

    /** @return array{data: array<int, string>, meta: array<string, int>} */
    private function toolResultFromRecordedRequest(int $requestIndex = 0): array
    {
        $recorded = Http::recorded();
        $request = $recorded[$requestIndex * 2 + 1][0];
        $messages = $request->data()['messages'];
        $toolMessage = end($messages);

        return json_decode($toolMessage['content'], true, 512, JSON_THROW_ON_ERROR);
    }
}
