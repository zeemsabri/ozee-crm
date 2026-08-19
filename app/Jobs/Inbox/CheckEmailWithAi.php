<?php

namespace App\Jobs\Inbox;

use App\Enums\EmailAiStatus;
use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\Inbox\InboxAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs an outbound draft past the AI checker before a person sees it.
 *
 * This is the ONE AI job that changes what happens to mail, so read the state machine
 * before enabling config('inbox.ai.check_outbound'):
 *
 *   submitted → ai_status=queued → checking → approved  → status stays PendingApproval
 *                                           → held      → status stays PendingApproval,
 *                                                          ai_reason set for the author
 *                                           → failed    → status stays PendingApproval
 *
 * Note what is NOT here: an approved check does not send the email. The mock auto-sends,
 * but auto-sending client mail off the back of a model's verdict is a decision to make
 * deliberately with the queue proven, not a side effect of a redesign. Approval only
 * removes the AI hold; a person still presses send. Flip that by acting on
 * EmailAiStatus::Approved in the approval flow when you are ready.
 *
 * Failure is not silent: `failed` shows in the UI as needing a human, which is the same
 * place a held email lands. An AI outage therefore degrades to "everything needs manual
 * approval", which is how the inbox behaved before this job existed.
 */
class CheckEmailWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    /** @var array<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $emailId)
    {
        $this->onQueue(config('inbox.ai.queue', 'emails'));
    }

    public function handle(InboxAiService $ai): void
    {
        if (! $ai->enabled() || ! config('inbox.ai.check_outbound')) {
            return;
        }

        $email = Email::find($this->emailId);

        if (! $email) {
            return;
        }

        // Only ever check something still awaiting approval. If a person approved,
        // rejected or edited it while this sat in the queue, their decision wins.
        $status = $email->status instanceof EmailStatus ? $email->status->value : (string) $email->status;

        if ($status !== EmailStatus::PendingApproval->value) {
            $email->forceFill(['ai_status' => null])->save();

            return;
        }

        $email->forceFill([
            'ai_status' => EmailAiStatus::Checking,
            'ai_checked_at' => now(),
        ])->save();

        $verdict = $ai->checkOutbound($email);

        if ($verdict === null) {
            $email->forceFill([
                'ai_status' => EmailAiStatus::Failed,
                'ai_reason' => 'The checker could not be reached, so this needs a manual look.',
                'ai_checked_at' => now(),
            ])->save();

            Log::warning('inbox.ai: check failed, email left for manual approval.', [
                'email_id' => $email->id,
            ]);

            return;
        }

        $email->forceFill([
            'ai_status' => $verdict['approved'] ? EmailAiStatus::Approved : EmailAiStatus::Held,
            'ai_reason' => $verdict['approved'] ? null : $verdict['reason'],
            'ai_checked_at' => now(),
        ])->save();
    }

    /** A dead job must not leave the email stuck at `checking` forever. */
    public function failed(?\Throwable $e): void
    {
        Email::where('id', $this->emailId)
            ->whereIn('ai_status', [EmailAiStatus::Queued->value, EmailAiStatus::Checking->value])
            ->update([
                'ai_status' => EmailAiStatus::Failed->value,
                'ai_reason' => 'The checker did not finish, so this needs a manual look.',
                'ai_checked_at' => now(),
            ]);
    }
}
