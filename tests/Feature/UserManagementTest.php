<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_access_users_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk();
    }

    public function test_users_page_supports_search_and_bounded_pagination(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator']);
        $admin->assignRole('Super Admin');
        User::factory()->create(['name' => 'Target User', 'email' => 'target@example.com']);
        User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

        $this->actingAs($admin)
            ->get('/users?search=target&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('users/index')
                    ->where('filters.search', 'target')
                    ->where('filters.per_page', '1')
                    ->where('users.per_page', 1)
                    ->where('users.total', 1)
                    ->where('users.data.0.email', 'target@example.com');
            });
    }

    public function test_non_admin_cannot_access_users_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_users_page(): void
    {
        $this->get('/users')
            ->assertRedirect('/login');
    }
}
