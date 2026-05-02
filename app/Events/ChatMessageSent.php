<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ChatMessageSent implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $projectId;
    public ?int $senderId;
    public array $messagePayload;
    public ?int $topicId;

    public function __construct(ChatMessage $chatMessage)
    {
        $chatMessage->loadMissing(['user', 'client', 'parent.user', 'parent.client']);

        $this->projectId = (int)$chatMessage->project_id;
        $this->senderId  = $chatMessage->user_id ?? $chatMessage->client_id;
        $this->topicId   = $chatMessage->telegram_topic_id ? (int)$chatMessage->telegram_topic_id : null;

        $parent = $chatMessage->parent;
        $senderName = $chatMessage->user?->name ?? ($chatMessage->client?->name ?? 'System');
        $initials = strtoupper(substr($senderName, 0, 2));

        $this->messagePayload = [
            'id'         => $chatMessage->id,
            'type'       => $chatMessage->type ?? 'text',
            'user'       => $senderName,
            'user_id'    => $chatMessage->user_id,
            'client_id'  => $chatMessage->client_id,
            'initials'   => $initials,
            'color'      => $chatMessage->client_id ? 'bg-sky-600' : 'bg-indigo-600',
            'message'    => $chatMessage->message,
            'parent'     => $parent ? [
                'id'      => $parent->id,
                'user'    => $parent->user?->name ?? ($parent->client?->name ?? 'System'),
                'message' => Str::limit($parent->message, 50),
            ] : null,
            'reads'      => [],
            'time'       => $chatMessage->created_at->diffForHumans(),
            'created_at' => $chatMessage->created_at->toDateTimeString(),
            'sender_id'  => $this->senderId,
            'source'     => $chatMessage->source,
        ];
    }

    /**
     * Broadcast on a private per-project channel.
     * e.g.  private-project.42
     * Authorization is registered in routes/channels.php.
     */
    public function broadcastOn(): Channel|array
    {
        $channels = [
            new PrivateChannel("project.{$this->projectId}")
        ];

        if ($this->topicId) {
            $channels[] = new PrivateChannel("topic.{$this->topicId}");
        }

        return $channels;
    }

    /**
     * The event name heard on the client: .ChatMessageSent
     */
    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }
}
