<?php

namespace App\Services\Inbox;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Email;
use App\Models\Lead;
use App\Models\User;
use App\Services\GmailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingests mail sent from the Gmail web UI, so a reply typed there is not invisible here.
 *
 * ## The gap this closes
 *
 * The poller's only Gmail query has always been `is:inbox`. There is no `in:sent`, no
 * `labelIds`, nowhere in the codebase — so nothing has ever created a `type='sent'` row
 * from ingested mail. Combine that with a send path that requires an approval workflow and
 * the outcome is predictable: people answer clients from Gmail because it is faster, and
 * the CRM never learns that the thread was answered.
 *
 * That is the single biggest reason the "Needs reply" view fills up with threads somebody
 * already dealt with. Fixing the backlog without fixing this just means the view refills.
 *
 * ## Three outcomes per message, and the middle one is the important one
 *
 *   ALREADY SEEN   `emails.message_id` (Gmail's API id) already exists → skip.
 *
 *   OURS           `emails.rfc_message_id` matches the message's `Message-ID` header → the
 *                  CRM sent this. Do NOT create a row; enrich the one we have with the
 *                  Gmail ids we did not previously know. This is why every send now stamps
 *                  a Message-ID rather than only replies: without one, every CRM email
 *                  would come back through this door as a duplicate.
 *
 *   THEIRS         Neither matched → somebody typed this into Gmail. Create the outbound
 *                  row, at `status = sent`, because it demonstrably went out.
 *
 * ## It will not invent conversations
 *
 * A "THEIRS" message is only ingested when it can be attached to a thread the CRM already
 * knows about — by Gmail thread id, by the `In-Reply-To` header, or by recipient. Anything
 * else is skipped. The SENT folder is full of internal mail, vendor correspondence and
 * personal replies, and a rule that created a conversation per unmatched message would
 * pour all of it into the inbox. Skipping is the conservative failure: a missed reply
 * leaves a thread looking unanswered, which is the situation we are already in.
 *
 * ## Never touches history
 *
 * The first run starts from `inbox.sent_ingest.first_run_since` (default: now), NOT from
 * the beginning of the mailbox. Pre-deployment CRM sends have no `rfc_message_id` — the
 * column did not exist — so they cannot be recognised as OURS and would every one of them
 * be ingested a second time. Backfilling further back is possible but needs a different,
 * fuzzier matcher, and is deliberately not this class's job.
 */
class IngestSentMail
{
    public function __construct(private readonly GmailService $gmail) {}

    /**
     * @return array{scanned:int,created:int,enriched:int,skipped:int,unmatched:int}
     */
    public function run(?Carbon $since = null, int $limit = 50, bool $dryRun = false): array
    {
        $stats = ['scanned' => 0, 'created' => 0, 'enriched' => 0, 'skipped' => 0, 'unmatched' => 0];

        $since ??= $this->watermark();
        $query = 'in:sent after:'.$since->copy()->subMinutes(5)->unix();

        try {
            $ids = $this->gmail->listMessages($limit, $query);
        } catch (Throwable $e) {
            Log::error('inbox: could not list sent mail from Gmail.', ['error' => $e->getMessage()]);

            return $stats;
        }

        foreach ($ids as $id) {
            $stats['scanned']++;

            try {
                $this->one($id, $stats, $dryRun);
            } catch (Throwable $e) {
                // One malformed message must not abort the batch — the next poll would
                // start from the same watermark and hit it again forever.
                $stats['skipped']++;
                Log::warning('inbox: could not ingest a sent message.', [
                    'gmail_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    // ----------------------------------------------------------------- one message

    private function one(string $gmailId, array &$stats, bool $dryRun): void
    {
        if (Email::withTrashed()->where('message_id', $gmailId)->exists()) {
            $stats['skipped']++;

            return;
        }

        $details = $this->gmail->getMessage($gmailId);
        $header = $details['messageIdHeader'] ?? null;

        // OURS — the CRM sent it. Fill in the Gmail ids we did not have and stop.
        if ($header && $ours = Email::where('rfc_message_id', $header)->first()) {
            $stats['enriched']++;

            if (! $dryRun) {
                $ours->forceFill(array_filter([
                    'message_id' => $ours->message_id ?: $gmailId,
                    'gmail_thread_id' => $ours->gmail_thread_id ?: ($details['threadId'] ?? null),
                ]))->save();
            }

            return;
        }

        $conversation = $this->conversationFor($details);

        if (! $conversation) {
            $stats['unmatched']++;

            return;
        }

        if ($dryRun) {
            $stats['created']++;

            return;
        }

        $sentAt = $this->when($details['date'] ?? null);
        $sender = $this->senderFor($details['from'] ?? null);

        $email = new Email([
            'conversation_id' => $conversation->id,
            'to' => $this->addresses($details['to'] ?? null),
            'subject' => $details['subject'] ?? '(no subject)',
            'body' => $details['body']['html'] ?: ($details['body']['plain'] ?? ''),
            'type' => EmailType::Sent->value,
            // Delivered, full stop — Gmail would not have it in SENT otherwise. This is
            // the whole point: it is what stops the thread reading as unanswered.
            'status' => EmailStatus::Sent->value,
            'message_id' => $gmailId,
            'rfc_message_id' => $header,
            'gmail_thread_id' => $details['threadId'] ?? null,
        ]);

        $email->sent_at = $sentAt;

        if ($sender) {
            $email->sender_id = $sender->id;
            $email->sender_type = User::class;
        }

        $email->save();

        // Only ever move the clock forward. A late-arriving older message must not drag
        // the thread's activity backwards and reshuffle the list.
        if ($conversation->last_activity_at === null || $sentAt->gt($conversation->last_activity_at)) {
            $conversation->forceFill(['last_activity_at' => $sentAt])->save();
        }

        $stats['created']++;
    }

    // ----------------------------------------------------------------- matching

    /**
     * Which existing thread this reply belongs to, or null to skip it.
     *
     * Three matchers, strongest first. None of them creates anything — see the class
     * docblock for why an unmatched sent message is dropped rather than given a new
     * conversation.
     */
    private function conversationFor(array $details): ?Conversation
    {
        // 1. Same Gmail thread as something we already hold. Exact, and survives subject
        //    changes and address changes within the thread.
        if ($threadId = $details['threadId'] ?? null) {
            $match = Email::where('gmail_thread_id', $threadId)
                ->whereNotNull('conversation_id')
                ->latest('id')
                ->first();

            if ($match?->conversation) {
                return $match->conversation;
            }
        }

        // 2. It answers a specific message we hold. Also exact — In-Reply-To carries the
        //    RFC Message-ID of the parent, which is the value we store.
        if ($inReplyTo = $details['inReplyTo'] ?? null) {
            $parent = Email::where('rfc_message_id', trim($inReplyTo))
                ->whereNotNull('conversation_id')
                ->first();

            if ($parent?->conversation) {
                return $parent->conversation;
            }
        }

        // 3. Fall back to the recipient. Weaker — it attaches to that contact's most
        //    recently active thread, which is wrong if two threads with the same client
        //    are live at once. Accepted because the alternative is dropping a genuine
        //    reply, and the cost of a slightly misfiled one is a stopped clock on the
        //    wrong thread rather than lost data.
        foreach ($this->addresses($details['to'] ?? null) as $address) {
            // whereHasMorph, not whereHas: `conversable` is a MorphTo, and whereHas cannot
            // constrain one — it has no single related table to build a subquery against.
            // The types are listed explicitly because that is what whereHasMorph needs, and
            // because '*' would make it scan every morph type ever recorded in the column.
            $conversation = Conversation::query()
                ->whereHasMorph(
                    'conversable',
                    [Client::class, Lead::class],
                    fn ($q) => $q->whereRaw('LOWER(email) = ?', [mb_strtolower($address)])
                )
                ->orderByDesc('last_activity_at')
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return null;
    }

    /**
     * Where to resume from: the newest sent mail we have ingested from Gmail.
     *
     * Keyed on `message_id`, which for an outbound row is only ever set by THIS class —
     * the send paths never populate it. So the watermark reflects ingestion progress and
     * is not dragged forward by the CRM's own sends.
     *
     * With nothing ingested yet it returns the configured first-run point, which defaults
     * to now. That is deliberate: see the class docblock on why this must not reach back
     * into history.
     */
    private function watermark(): Carbon
    {
        $newest = Email::query()
            ->where('type', EmailType::Sent->value)
            ->whereNotNull('message_id')
            ->max('sent_at');

        if ($newest) {
            return Carbon::parse($newest);
        }

        $configured = config('inbox.sent_ingest.first_run_since');

        try {
            return $configured ? Carbon::parse($configured) : Carbon::now();
        } catch (Throwable) {
            Log::warning('inbox: INBOX_SENT_INGEST_SINCE could not be parsed, starting from now.', [
                'value' => $configured,
            ]);

            return Carbon::now();
        }
    }

    // ----------------------------------------------------------------- parsing

    /** @return array<string> */
    private function addresses(?string $header): array
    {
        if (! $header) {
            return [];
        }

        $found = [];

        foreach (explode(',', $header) as $part) {
            // "Name <a@b.com>" or a bare address.
            if (preg_match('/<([^>]+)>/', $part, $m)) {
                $part = $m[1];
            }

            $part = trim($part);

            if (filter_var($part, FILTER_VALIDATE_EMAIL)) {
                $found[] = $part;
            }
        }

        return array_values(array_unique($found));
    }

    private function senderFor(?string $from): ?User
    {
        $addresses = $this->addresses($from);

        if (! $addresses) {
            return null;
        }

        // Usually the shared mailbox, which matches no user — that is fine, the row just
        // has no author. When a personal address does match, crediting it is worth the
        // lookup.
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($addresses[0])])->first();
    }

    private function when(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        try {
            return $date ? Carbon::parse($date)->setTimezone('UTC') : Carbon::now();
        } catch (Throwable) {
            return Carbon::now();
        }
    }
}
