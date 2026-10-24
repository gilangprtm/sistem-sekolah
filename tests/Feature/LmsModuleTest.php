<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_lms_pages(): void
    {
        $this->get(route('lms.design'))
            ->assertRedirect(route('login'));
        $this->get(route('lms.management'))
            ->assertRedirect(route('login'));
        $this->get(route('lms.my-courses'))
            ->assertRedirect(route('login'));
    }

    public function test_teacher_can_open_design_and_my_courses_but_not_management(): void
    {
        $this->seed(LmsPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $user->teacher()->create(['staff_type' => 'guru', 'full_name' => $user->name, 'status' => 'active']);
        $this->actingAs($user);

        $this->get(route('lms.design'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('lms/design/index')
                ->has('courses.data', 0));

        $this->get(route('lms.management'))
            ->assertForbidden();

        $this->get(route('lms.my-courses'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('lms/my-courses/index')
                ->has('courses.data', 0));
    }
}
