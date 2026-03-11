<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $projectId;
    public int $senderId;
    public array $messagePayload;

    public function __construct(ChatMessage $chatMessage)
    {
        $chatMessage->loadMissing(['user', 'parent.user']);

        $this->projectId = $chatMessage->project_id;
        $this->senderId  = $chatMessage->user_id;

        $parent = $chatMessage->parent;

        $this->messagePayload = [
            'id'         => $chatMessage->id,
            'type'       => 'text',
            'user'       => $chatMessage->user?->name ?? 'System',
            'user_id'    => $chatMessage->user_id,
            'initials'   => strtoupper(substr($chatMessage->user?->name ?? 'S', 0, 2)),
            'color'      => 'bg-indigo-600',
            'message'    => $chatMessage->message,
            'parent'     => $parent ? [
                'id'      => $parent->id,
                'user'    => $parent->user?->name ?? 'System',
                'message' => Str::limit($parent->message, 50),
            ] : null,
            'reads'      => [],
            'time'       => $chatMessage->created_at->diffForHumans(),
            'created_at' => $chatMessage->created_at->toDateTimeString(),
            // 'is_me' is resolved client-side per subscriber
            'sender_id'  => $chatMessage->user_id,
        ];
    }

    /**
     * Broadcast on a private per-project channel.
     * e.g.  private-project.42
     * Authorization is registered in routes/channels.php.
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel("project.{$this->projectId}");
    }

    /**
     * The event name heard on the client: .ChatMessageSent
     */
    public function broadcastAs(): string
    {
        return 'ChatMessageSent';
    }
}
