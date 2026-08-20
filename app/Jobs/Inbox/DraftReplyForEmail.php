<?php

namespace App\Jobs\Inbox;

use App\Enums\EmailDraftStatus;
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
 *
 * ## Every exit reports itself
 *
 * The job used to return quietly on each of its guard clauses — AI off, wrong type, model
 * returned nothing — leaving the email in whatever state the request put it in. The
 * composer then spun forever on a job that had already decided to do nothing, which is the
 * worst of both: no draft, and no way to tell that none was coming.
 *
 * So every path below lands on a terminal EmailDraftStatus. `failed` is not only for
 * exceptions; "the model gave us nothing usable" is a failure the person needs to see,
 * because their alternative is to stop waiting and write it themselves.
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
        $email = Email::find($this->emailId);

        // Nothing to report to — the row is gone.
        if (! $email) {
            return;
        }

        if (! $ai->enabled() || ! config('inbox.ai.draft_replies')) {
            $this->finish($email, EmailDraftStatus::Failed);

            return;
        }

        if ($email->is_private) {
            $this->finish($email, EmailDraftStatus::Failed);

            return;
        }

        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        if ($type !== EmailType::Received->value) {
            $this->finish($email, EmailDraftStatus::Failed);

            return;
        }

        // Visible progress. A model call takes long enough that "queued" and "being
        // written" are genuinely different things to be looking at.
        $email->forceFill(['ai_draft_status' => EmailDraftStatus::Writing])->save();

        $changes = [];

        if (config('inbox.ai.summarise') && ! $email->ai_summary) {
            $summary = $ai->summariseMessage($email);
            if ($summary) {
                $changes['ai_summary'] = $summary;
            }
        }

        $draft = $ai->draftReply($email, $this->signOffName);

        if ($draft) {
            $changes['ai_draft'] = $draft;
            $changes['ai_draft_at'] = now();
            $changes['ai_draft_status'] = EmailDraftStatus::Ready;
        } else {
            // ai_draft_at is deliberately untouched, so it keeps meaning "when we last
            // successfully produced one" even after a failed retry.
            $changes['ai_draft_status'] = EmailDraftStatus::Failed;
        }

        $email->forceFill($changes)->save();
    }

    /**
     * The job died for good — say so rather than leaving the composer spinning.
     *
     * Laravel calls this after the final retry, so it is the last chance to move the email
     * out of a working state. Without it a queue outage leaves every requested draft
     * showing "writing…" indefinitely.
     */
    public function failed(?\Throwable $e = null): void
    {
        $email = Email::find($this->emailId);

        if ($email) {
            $this->finish($email, EmailDraftStatus::Failed);
        }
    }

    private function finish(Email $email, EmailDraftStatus $status): void
    {
        $email->forceFill(['ai_draft_status' => $status])->save();
    }
}
