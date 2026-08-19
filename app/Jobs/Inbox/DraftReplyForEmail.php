<?php

namespace App\Jobs\Inbox;

use App\Enums\EmailType;
use App\Models\Email;
use App\Services\Inbox\InboxAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pre-writes a reply to an inbound client email, and a one-line summary of it.
 *
 * The draft is only ever loaded into the reply box for a person to edit — nothing here
 * sends anything. Both writes are additive columns on the email, so this job is safe to
 * enable independently of the checker.
 *
 * Private emails are skipped outright: a draft generated from one would put its contents
 * in front of whoever opens the reply box.
 */
class DraftReplyForEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [30, 180];

    public function __construct(public int $emailId, public ?string $signOffName = null) {
        $this->onQueue(config('inbox.ai.queue', 'emails'));
    }

    public function handle(InboxAiService $ai): void
    {
        if (! $ai->enabled()) {
            return;
        }

        $email = Email::find($this->emailId);

        if (! $email || $email->is_private) {
            return;
        }

        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        if ($type !== EmailType::Received->value) {
            return;
        }

        $changes = [];

        if (config('inbox.ai.summarise') && ! $email->ai_summary) {
            $summary = $ai->summariseMessage($email);
            if ($summary) {
                $changes['ai_summary'] = $summary;
            }
        }

        if (config('inbox.ai.draft_replies') && ! $email->ai_draft) {
            $draft = $ai->draftReply($email, $this->signOffName);
            if ($draft) {
                $changes['ai_draft'] = $draft;
                $changes['ai_draft_at'] = now();
            }
        }

        if ($changes) {
            $email->forceFill($changes)->save();
        }
    }
}
