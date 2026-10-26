<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAppServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_can_view_services_page(): void
    {
        $student = User::factory()->create();
        $student->assignRole('Siswa');

        $this->actingAs($student)
            ->get(route('student-app.services'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('student-app/services'));
    }

    public function test_guest_and_non_student_cannot_view_services_page(): void
    {
        $this->get(route('student-app.services'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get(route('student-app.services'))->assertForbidden();
    }

    public function test_services_page_uses_secondary_shell_and_links_only_to_existing_student_services(): void
    {
        $dashboard = file_get_contents(resource_path('js/pages/student-app/dashboard.tsx'));
        $this->assertIsString($dashboard);
        $this->assertStringContainsString("{ label: 'Silaras', icon: HeartHandshake }", $dashboard);
        $this->assertStringNotContainsString("{ label: 'Ujian', icon:", $dashboard);
        $this->assertStringContainsString('<SilarasAspirationDialog', $dashboard);

        $page = file_get_contents(resource_path('js/pages/student-app/services.tsx'));
        $this->assertIsString($page);
        $this->assertStringContainsString('title="Layanan Siswa"', $page);
        $this->assertStringContainsString('SecondaryPageShell', $page);
        $this->assertStringContainsString('kantin.url()', $page);
        $this->assertStringContainsString('uas.url()', $page);
        $this->assertStringContainsString('studentServices.url()', $dashboard);
        $this->assertStringContainsString("label: 'Ujian'", $page);
        $this->assertStringContainsString('<SilarasAspirationDialog', $page);
        $this->assertStringContainsString('courses.url()', $page);
        $this->assertStringContainsString('href: null', $page);

        $layout = file_get_contents(resource_path('js/layouts/student-app-layout.tsx'));
        $this->assertIsString($layout);
        $this->assertStringContainsString("const isServices = currentPath === '/student/services';", $layout);
        $this->assertStringContainsString('isKantin || isUas || isExam || isCourse || isServices', $layout);
    }
}
