<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyReadOnlyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The app's migrations are MySQL-only; build just what loading a User touches.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
        Schema::create('categorizables', function (Blueprint $table) {
            $table->foreignId('category_id');
            $table->morphs('categorizable');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test');
            $table->foreignId('role_id')->nullable();
            $table->string('api_key')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Route::middleware('api')->prefix('api')->group(function () {
            Route::get('ro-test/tasks', fn () => 'ok');
            Route::post('ro-test/tasks', fn () => 'ok');
        });
    }

    private function user(string $roleSlug, ?string $apiKey = null): User
    {
        // Skip model events so activity logging doesn't need its tables.
        return User::withoutEvents(function () use ($roleSlug, $apiKey) {
            $role = Role::forceCreate(['slug' => $roleSlug]);

            return User::forceCreate(['role_id' => $role->id, 'api_key' => $apiKey]);
        });
    }

    public function test_staff_can_read_but_not_write(): void
    {
        $this->actingAs($this->user('employee'));

        $this->getJson('/api/ro-test/tasks')->assertOk();
        $this->postJson('/api/ro-test/tasks')->assertForbidden();
    }

    public function test_staff_can_still_create_invoices(): void
    {
        $this->actingAs($this->user('manager'));

        // Hits the real invoice route; it only needs to get past the middleware.
        $this->assertNotSame(403, $this->postJson('/api/projects/1/invoices')->status());
    }

    public function test_staff_can_still_mark_inbox_threads_read(): void
    {
        $this->actingAs($this->user('employee'));

        $this->assertNotSame(403, $this->postJson('/api/inbox/threads/1/read')->status());
    }

    public function test_super_admin_can_write(): void
    {
        $this->actingAs($this->user('super-admin'));

        $this->postJson('/api/ro-test/tasks')->assertOk();
    }

    public function test_requests_without_a_staff_user_pass_through(): void
    {
        $this->postJson('/api/ro-test/tasks')->assertOk();
    }

    public function test_api_key_tracker_cannot_create_tasks(): void
    {
        $this->user('employee', 'staff-key');
        $this->user('super-admin', 'admin-key');

        $this->postJson('/api/activity/tasks/quick', [], ['X-API-KEY' => 'staff-key'])->assertForbidden();
        $this->assertNotSame(403, $this->postJson('/api/activity/tasks/quick', [], ['X-API-KEY' => 'admin-key'])->status());
    }
}
