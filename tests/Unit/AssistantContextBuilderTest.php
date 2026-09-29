<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantContextBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_builds_system_context_and_current_user_message(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('inventory.view');
        $builder = app(AssistantContextBuilder::class);

        $messages = $builder->build(
            $user,
            [['role' => 'assistant', 'content' => 'Jawaban lama.']],
            'Pertanyaan baru',
            false,
        );

        $this->assertSame('system', $messages[0]['role']);
        $this->assertStringContainsString('resource tools', $messages[0]['content']);
        $this->assertSame(['role' => 'assistant', 'content' => 'Jawaban lama.'], $messages[1]);
        $this->assertSame(['role' => 'user', 'content' => 'Pertanyaan baru'], $messages[2]);
    }

    public function test_plain_mode_omits_system_context(): void
    {
        $user = User::factory()->create();
        $builder = app(AssistantContextBuilder::class);

        $messages = $builder->build($user, [], 'Halo', true);

        $this->assertSame([['role' => 'user', 'content' => 'Halo']], $messages);
    }
}
