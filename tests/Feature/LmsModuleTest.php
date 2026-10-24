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

    public function test_authorized_teacher_can_open_each_lms_menu(): void
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
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('lms/index')
                ->where('section', 'management'));

        $this->get(route('lms.my-courses'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('lms/index')
                ->where('section', 'my-courses'));
    }
}
