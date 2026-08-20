<?php

namespace App\Services\Inbox;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Email;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single definition of "has this thread been answered, and do we get to judge it".
 *
 * ThreadQuery asks this question in SQL, over a whole page of conversations at once.
 * ThreadPresenter asks it again in PHP, for the row it is about to render. Before this
 * class they each carried their own copy of the rule, which is the classic setup for a
 * list that says "overdue" next to a thread whose detail view says "answered".
 *
 * ## Why there is a cutover at all
 *
 * "Needs reply" is derived, not stored — no column was ever added for it:
 *
 *   last_inbound  = newest email with type='received'
 *   last_outbound = newest email with type='sent' AND a delivered status
 *   needs a reply = last_inbound exists AND (no last_outbound OR it is older)
 *
 * That is a correct reading of the table. The trouble is what the table omits. The Gmail
 * poller's only query has always been `is:inbox` — there is no `in:sent` anywhere in the
 * codebase — so a reply somebody typed into the Gmail web UI never became a row. For a
 * CRM whose own send path requires an approval workflow, replying from Gmail is the
 * obvious shortcut, and every thread answered that way reads as unanswered forever.
 *
 * So an old thread showing as unanswered means "this system holds no record of a reply",
 * which is emphatically not "nobody replied". The cutover is how we stop presenting the
 * first as if it were the second. It writes nothing and marks nothing as handled; it
 * narrows what the clock is willing to judge to the period where the data can actually
 * support a judgement. Widen it and the older threads come straight back.
 *
 * The ingestion gap itself is fixed separately — see IngestSentMail — but that only helps
 * from the day it is deployed. History stays unknowable, and a cutover is the honest way
 * to say so.
 */
class ReplyClock
{
    private ?CarbonImmutable $since;

    private bool $sinceResolved = false;

    /**
     * Outbound statuses that mean the client received it.
     *
     * @return array<string>
     */
    public function deliveredStatuses(): array
    {
        $configured = (array) config('inbox.reply_clock.delivered_statuses', []);

        $statuses = array_values(array_filter(array_map(
            fn ($s) => is_string($s) ? trim($s) : null,
            $configured
        )));

        // Never return an empty list: an empty IN () clause makes every thread look
        // unanswered, which is the exact failure this class exists to prevent. If someone
        // empties the config, fall back to the one status both live send paths write.
        return $statuses ?: [EmailStatus::Sent->value];
    }

    /**
     * The cutover, or null for "judge everything".
     *
     * Parsed once per request. A malformed value logs and degrades to null rather than
     * throwing — a typo in an env var should not take the inbox down, and "no cutover" is
     * the conservative direction to fail in: it shows more threads, not fewer.
     */
    public function since(): ?CarbonImmutable
    {
        if ($this->sinceResolved) {
            return $this->since;
        }

        $this->sinceResolved = true;
        $raw = config('inbox.reply_clock.since');

        if (! is_string($raw) || trim($raw) === '') {
            return $this->since = null;
        }

        try {
            return $this->since = CarbonImmutable::parse(trim($raw));
        } catch (Throwable $e) {
            Log::warning('inbox: INBOX_REPLY_CLOCK_SINCE could not be parsed, ignoring it.', [
                'value' => $raw,
                'error' => $e->getMessage(),
            ]);

            return $this->since = null;
        }
    }

    /**
     * Is this thread recent enough for the clock to have an opinion about it?
     *
     * Keyed on the newest INBOUND message, not on the thread's last activity: the clock
     * measures how long a client has been waiting, so the client's message is what decides
     * whether the question is in scope.
     */
    public function inScope(mixed $lastInboundAt): bool
    {
        $since = $this->since();

        if ($since === null) {
            return true;
        }

        if ($lastInboundAt === null || $lastInboundAt === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($lastInboundAt)->greaterThanOrEqualTo($since);
        } catch (Throwable) {
            // An unparseable timestamp is a data problem, not a reason to hide the thread.
            return true;
        }
    }

    /** Does this email count as "the client received it"? */
    public function isDelivered(Email $email): bool
    {
        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        if ($type !== EmailType::Sent->value) {
            return false;
        }

        $status = $email->status instanceof EmailStatus
            ? $email->status->value
            : (string) $email->status;

        return in_array($status, $this->deliveredStatuses(), true);
    }

    /**
     * SQL fragment: the newest delivered outbound timestamp for the correlated
     * conversation. Kept here so ThreadQuery cannot drift from isDelivered().
     */
    public function outboundSql(string $conversationsTable = 'conversations'): string
    {
        $list = "'".implode("','", $this->deliveredStatuses())."'";

        return 'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
            ." WHERE e.conversation_id = {$conversationsTable}.id"
            .' AND e.deleted_at IS NULL'
            ." AND e.type = '".EmailType::Sent->value."'"
            ." AND e.status IN ({$list})";
    }

    /** SQL fragment: the newest inbound timestamp for the correlated conversation. */
    public function inboundSql(string $conversationsTable = 'conversations'): string
    {
        return 'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
            ." WHERE e.conversation_id = {$conversationsTable}.id"
            .' AND e.deleted_at IS NULL'
            ." AND e.type = '".EmailType::Received->value."'";
    }
}
