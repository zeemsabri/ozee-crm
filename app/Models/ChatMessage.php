<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'user_id',
        'client_id',
        'telegram_topic_id',
        'parent_id',
        'telegram_message_id',
        'message',
        'source',
        'type',
        'meta_data',
    ];

    protected static function booted()
    {


        static::created(function ($message) {
            if ($message->user_id) {
                \App\Models\UserInteraction::firstOrCreate([
                    'user_id'          => $message->user_id,
                    'interactable_id'  => $message->id,
                    'interactable_type' => static::class,
                    'interaction_type' => 'read',
                ]);
            }
            
            \Illuminate\Support\Facades\Log::info("Preparing to broadcast ChatMessageSent for Project ID: {$message->project_id}, Message ID: {$message->id}");
            
            try {
                // Broadcast to all project members via Reverb so the message appears
                // in real-time for everyone without a page refresh.
                \App\Events\ChatMessageSent::dispatch($message->load(['user', 'parent.user']));
                \Illuminate\Support\Facades\Log::info("Successfully dispatched ChatMessageSent for Message ID: {$message->id}");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to broadcast ChatMessageSent for Message ID: {$message->id}. Error: " . $e->getMessage());
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(ChatMessage::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ChatMessage::class, 'parent_id');
    }

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function telegramTopic()
    {
        return $this->belongsTo(TelegramTopic::class);
    }

    public function interactions()
    {
        return $this->morphMany(UserInteraction::class, 'interactable');
    }

    /**
     * Add a Telegram API response result to the message metadata.
     * This tracks all instances of the message across group topics and client DMs.
     */
    public function addTelegramResponse(array $result, ?string $type = null): self
    {
        $meta = $this->meta_data ?? [];
        $responses = $meta['telegram_responses'] ?? [];

        $responses[] = [
            'message_id' => $result['message_id'] ?? null,
            'chat_id'    => $result['chat']['id'] ?? null,
            'type'       => $type,
            'sent_at'    => now()->toDateTimeString(),
        ];

        $meta['telegram_responses'] = $responses;
        $this->meta_data = $meta;

        // Ensure the primary message ID is set if not already
        if (!$this->telegram_message_id) {
            $this->telegram_message_id = $result['message_id'] ?? null;
        }

        return $this;
    }

    /**
     * Delete this message from all recorded Telegram locations.
     */
    public function deleteFromTelegram(): array
    {
        $telegramService = app(\App\Services\TelegramService::class);
        $meta = $this->meta_data ?? [];
        $responses = $meta['telegram_responses'] ?? [];

        $results = [];

        foreach ($responses as $index => $res) {
            if (isset($res['chat_id']) && isset($res['message_id'])) {
                $ok = $telegramService->deleteMessage($res['chat_id'], $res['message_id']);
                $results[] = [
                    'chat_id' => $res['chat_id'],
                    'message_id' => $res['message_id'],
                    'success' => $ok
                ];

                // Mark as deleted in metadata if successful
                if ($ok) {
                    $responses[$index]['deleted_at'] = now()->toDateTimeString();
                }
            }
        }

        $meta['telegram_responses'] = $responses;
        $this->meta_data = $meta;
        $this->save();

        return $results;
    }
}
