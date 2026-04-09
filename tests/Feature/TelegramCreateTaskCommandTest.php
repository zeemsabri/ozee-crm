<?php

namespace Tests\Feature;

use App\Enums\TelegramTopicType;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TelegramAccount;
use App\Models\TelegramTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TelegramCreateTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Notification::fake();
    }

    public function test_it_creates_and_assigns_a_task_from_a_group_command_without_relaying_to_clients(): void
    {
        Http::fake([
            'https://api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 9001,
                    'chat' => ['id' => -10012345],
                ],
            ], 200),
        ]);

        [$project, $client] = $this->createTelegramProject();
        $sender = User::factory()->create([
            'name' => 'Sender User',
            'email' => 'sender@example.com',
        ]);
        $assignee = User::factory()->create([
            'name' => 'Zeeshan Sabri',
            'email' => 'zeeshan@example.com',
        ]);

        TelegramAccount::create([
            'telegram_id' => '1001',
            'telegramable_id' => $sender->id,
            'telegramable_type' => User::class,
            'username' => 'sender_user',
            'first_name' => 'Sender',
        ]);

        TelegramAccount::create([
            'telegram_id' => '2002',
            'telegramable_id' => $assignee->id,
            'telegramable_type' => User::class,
            'username' => 'Zeeshan',
            'first_name' => 'Zeeshan',
            'last_name' => 'Sabri',
        ]);

        TelegramAccount::create([
            'telegram_id' => '3003',
            'telegramable_id' => $client->id,
            'telegramable_type' => Client::class,
            'username' => 'linked_client',
            'first_name' => 'Linked',
        ]);

        TelegramTopic::create([
            'project_id' => $project->id,
            'name' => 'Client Communication',
            'type' => TelegramTopicType::PROXY->value,
            'telegram_thread_id' => 99,
            'is_private' => false,
        ]);

        $response = $this->postJson('/api/telegram/wh', [
            'message' => [
                'message_id' => 501,
                'message_thread_id' => 99,
                'text' => '/create-task "Telegram proxy task" @Zeeshan',
                'chat' => [
                    'id' => -10012345,
                    'title' => 'Acme Project Group',
                ],
                'from' => [
                    'id' => 1001,
                    'first_name' => 'Sender',
                    'username' => 'sender_user',
                ],
            ],
        ]);

        $response->assertOk();

        $task = Task::query()->where('name', 'Telegram proxy task')->first();

        $this->assertNotNull($task);
        $this->assertSame($assignee->id, $task->assigned_to_user_id);
        $this->assertSame(TaskStatus::ToDo->value, $task->status->value);
        $this->assertSame('telegram', $task->source);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains($request['text'], 'Task created')
                && str_contains($request['text'], 'Zeeshan Sabri');
        });
    }

    public function test_it_resolves_text_mentions_to_linked_crm_users(): void
    {
        Http::fake([
            'https://api.telegram.org/*/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 9002,
                    'chat' => ['id' => -10012345],
                ],
            ], 200),
        ]);

        [$project] = $this->createTelegramProject();
        $sender = User::factory()->create([
            'name' => 'Sender User',
            'email' => 'sender2@example.com',
        ]);
        $assignee = User::factory()->create([
            'name' => 'Zeeshan Sabri',
            'email' => 'zeeshan2@example.com',
        ]);

        TelegramAccount::create([
            'telegram_id' => '1001',
            'telegramable_id' => $sender->id,
            'telegramable_type' => User::class,
            'username' => 'sender_user',
            'first_name' => 'Sender',
        ]);

        TelegramAccount::create([
            'telegram_id' => '2002',
            'telegramable_id' => $assignee->id,
            'telegramable_type' => User::class,
            'username' => null,
            'first_name' => 'Zeeshan',
            'last_name' => 'Sabri',
        ]);

        $response = $this->postJson('/api/telegram/wh', [
            'message' => [
                'message_id' => 777,
                'text' => '/create-task "Task" Zeeshan',
                'entities' => [
                    [
                        'offset' => 0,
                        'length' => 12,
                        'type' => 'bot_command',
                    ],
                    [
                        'offset' => 20,
                        'length' => 7,
                        'type' => 'text_mention',
                        'user' => [
                            'id' => 2002,
                            'is_bot' => false,
                            'first_name' => 'Zeeshan',
                        ],
                    ],
                ],
                'chat' => [
                    'id' => -10012345,
                    'title' => 'Acme Project Group',
                ],
                'from' => [
                    'id' => 1001,
                    'first_name' => 'Sender',
                    'username' => 'sender_user',
                ],
            ],
        ]);

        $response->assertOk();

        $task = Task::query()->where('name', 'Task')->first();

        $this->assertNotNull($task);
        $this->assertSame($assignee->id, $task->assigned_to_user_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains($request['text'], 'Task created')
                && str_contains($request['text'], 'Zeeshan Sabri');
        });
    }

    private function createTelegramProject(): array
    {
        $client = Client::create([
            'name' => 'Acme Client',
            'email' => 'client@example.com',
        ]);

        $project = Project::create([
            'name' => 'Acme Website',
            'client_id' => $client->id,
            'telegram_group_id' => '-10012345',
            'telegram_group_name' => 'Acme Project Group',
        ]);

        $project->clients()->attach($client->id, ['role' => 'Primary']);

        return [$project, $client];
    }
}