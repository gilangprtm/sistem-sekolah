<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiUsersRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        return $admin->createToken('test')->plainTextToken;
    }

    public function test_super_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data' => []]]);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    public function test_create_user_with_roles(): void
    {
        $role = Role::findByName('Guru');

        $this->withToken($this->adminToken())
            ->postJson('/api/v1/users', [
                'name' => 'Budi',
                'email' => 'budi@example.com',
                'password' => 'password123',
                'roles' => [$role->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'budi@example.com');

        $this->assertDatabaseHas('users', ['email' => 'budi@example.com']);
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id]);
    }

    public function test_super_admin_can_list_roles(): void
    {
        $this->withToken($this->adminToken())
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_unauthorized_without_token(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
