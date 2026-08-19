<?php

namespace App\Jobs\Inbox;

use App\Models\Conversation;
use App\Services\Inbox\InboxAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the thread summary card and the task it suggests.
 *
 * Read-only as far as the mail is concerned — it only ever writes to the conversation's
 * ai_* columns, so it is safe to enable without watching the queue.
 *
 * `ai_summary_email_count` records how many messages the summary covers. The thread view
 * hides a summary once that count falls behind the real one, so a stale summary is
 * absent rather than wrong.
 */
class SummariseConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [30, 180];

    public function __construct(public int $conversationId)
    {
        $this->onQueue(config('inbox.ai.queue', 'emails'));
    }

    public function handle(InboxAiService $ai): void
    {
        if (! $ai->enabled() || ! config('inbox.ai.summarise')) {
            return;
        }

        $conversation = Conversation::with(['emails' => fn ($q) => $q->orderBy('created_at')])
            ->find($this->conversationId);

        if (! $conversation || $conversation->emails->isEmpty()) {
            return;
        }

        $count = $conversation->emails->count();

        // Nothing new since the last summary — skip the call rather than pay for an
        // identical answer. Several people opening the same thread is the common case.
        if ($conversation->hasCurrentAiSummary($count)) {
            return;
        }

        $result = $ai->summariseThread($conversation);

        if ($result === null) {
            return;
        }

        $conversation->forceFill([
            'ai_summary' => $result['summary'],
            'ai_summary_at' => now(),
            'ai_summary_email_count' => $count,
            'ai_task_suggestion' => $result['task'],
        ])->save();
    }
}
