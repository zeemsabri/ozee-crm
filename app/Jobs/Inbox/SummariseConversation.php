<?php

namespace App\Jobs\Inbox;

use App\Enums\EmailDraftStatus;
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
        $conversation = Conversation::with(['emails' => fn ($q) => $q->orderBy('created_at')])
            ->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        /*
         * Every exit lands on a terminal state.
         *
         * These guards used to return quietly, leaving the conversation at whatever the
         * request set. The page then showed "summarising…" forever for a job that had
         * already decided to do nothing — no summary, and no way to tell none was coming.
         */
        if (! $ai->enabled() || ! config('inbox.ai.summarise') || $conversation->emails->isEmpty()) {
            $this->finish($conversation, EmailDraftStatus::Failed);

            return;
        }

        $count = $conversation->emails->count();

        // Nothing new since the last summary — skip the call rather than pay for an
        // identical answer. Several people opening the same thread is the common case.
        if ($conversation->hasCurrentAiSummary($count)) {
            $this->finish($conversation, EmailDraftStatus::Ready);

            return;
        }

        $conversation->forceFill(['ai_summary_status' => EmailDraftStatus::Writing])->save();

        $result = $ai->summariseThread($conversation);

        if ($result === null) {
            // The previous summary, if any, is left alone — it still describes the thread
            // accurately as of its own message count, and replacing it with nothing would
            // lose a good answer because a later call failed.
            $this->finish($conversation, EmailDraftStatus::Failed);

            return;
        }

        $conversation->forceFill([
            'ai_summary' => $result['summary'],
            'ai_summary_at' => now(),
            'ai_summary_email_count' => $count,
            'ai_task_suggestion' => $result['task'],
            'ai_summary_status' => EmailDraftStatus::Ready,
        ])->save();
    }

    /**
     * The job died for good. Laravel calls this after the final retry, so it is the last
     * chance to stop the page promising a summary that is not coming.
     */
    public function failed(?\Throwable $e = null): void
    {
        $conversation = Conversation::find($this->conversationId);

        if ($conversation) {
            $this->finish($conversation, EmailDraftStatus::Failed);
        }
    }

    private function finish(Conversation $conversation, EmailDraftStatus $status): void
    {
        $conversation->forceFill(['ai_summary_status' => $status])->save();
    }
}
