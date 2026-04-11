<?php

namespace Tests\Unit;

use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\MentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_mentions_as_plain_text_for_external_channels(): void
    {
        $service = app(MentionService::class);

        $user = User::factory()->create(['name' => 'Usama Saeed']);
        TelegramAccount::create([
            'telegram_id' => '6549701399',
            'telegramable_id' => $user->id,
            'telegramable_type' => User::class,
            'username' => 'ezysoft_solutions',
            'first_name' => 'Usama',
            'last_name' => 'Saeed',
        ]);

        $formatted = $service->renderPlainText("@{{$user->id}:Usama Saeed} Test Message with mention");

        $this->assertSame('@ezysoft_solutions Test Message with mention', $formatted);
    }

    public function test_it_falls_back_to_plain_name_when_telegram_username_is_missing(): void
    {
        $user = User::factory()->create(['name' => 'Usama Saeed']);

        $service = app(MentionService::class);

        $formatted = $service->renderPlainText("@{{$user->id}:Usama Saeed} Test Message with mention");

        $this->assertSame('Usama Saeed Test Message with mention', $formatted);
    }

    public function test_it_renders_task_tokens_as_plain_text_for_external_channels(): void
    {
        $service = app(MentionService::class);

        $formatted = $service->renderPlainText('Please review #{617:OZ617:Homepage Fix} today');

        $this->assertSame('Please review #OZ617 Homepage Fix today', $formatted);
    }

    public function test_it_leaves_plain_text_messages_unchanged(): void
    {
        $service = app(MentionService::class);

        $formatted = $service->renderPlainText('Plain message without tokens');

        $this->assertSame('Plain message without tokens', $formatted);
    }
}