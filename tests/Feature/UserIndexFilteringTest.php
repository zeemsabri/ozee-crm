<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserIndexFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_filter_users_with_linked_telegram_accounts(): void
    {
        $viewer = $this->createViewerUser();
        Sanctum::actingAs($viewer);

        $linkedUser = User::factory()->create([
            'name' => 'Linked User',
        ]);

        $unlinkedUser = User::factory()->create([
            'name' => 'Unlinked User',
        ]);

        TelegramAccount::create([
            'telegram_id' => '987654321',
            'telegramable_id' => $linkedUser->id,
            'telegramable_type' => User::class,
            'username' => 'linked_user',
            'first_name' => 'Linked',
        ]);

        $response = $this->getJson('/api/users?telegram_linked=linked');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $linkedUser->id,
            'name' => 'Linked User',
        ]);
        $response->assertJsonFragment([
            'telegram_id' => '987654321',
            'username' => 'linked_user',
        ]);
        $response->assertJsonMissing([
            'id' => $unlinkedUser->id,
            'name' => 'Unlinked User',
        ]);
    }

    public function test_it_can_filter_users_without_linked_telegram_accounts(): void
    {
        $viewer = $this->createViewerUser();
        Sanctum::actingAs($viewer);

        $linkedUser = User::factory()->create([
            'name' => 'Telegram Connected',
        ]);

        $unlinkedUser = User::factory()->create([
            'name' => 'Telegram Missing',
        ]);

        TelegramAccount::create([
            'telegram_id' => '123123123',
            'telegramable_id' => $linkedUser->id,
            'telegramable_type' => User::class,
            'username' => 'connected_user',
            'first_name' => 'Connected',
        ]);

        $response = $this->getJson('/api/users?telegram_linked=unlinked');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $unlinkedUser->id,
            'name' => 'Telegram Missing',
        ]);
        $response->assertJsonMissing([
            'id' => $linkedUser->id,
            'name' => 'Telegram Connected',
        ]);
    }

    private function createViewerUser(): User
    {
        $role = Role::create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'description' => 'Can view users',
            'type' => 'application',
        ]);

        $permission = Permission::create([
            'name' => 'View Users',
            'slug' => 'view_users',
            'description' => 'Can view users index',
            'category' => 'users',
        ]);

        $role->assignPermission($permission);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}