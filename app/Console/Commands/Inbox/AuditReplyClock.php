<?php

namespace App\Console\Commands\Inbox;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Explains why the "Needs reply" view says what it says. Reads only — writes nothing.
 *
 * ## The thing to understand before reading the output
 *
 * "Needs reply" is not stored anywhere. No column was added for it. It is derived, per
 * conversation, from data that has been in the `emails` table for years:
 *
 *   last_inbound  = MAX(COALESCE(sent_at, created_at))  WHERE type='received'
 *   last_outbound = MAX(COALESCE(sent_at, created_at))  WHERE type='sent' AND status='sent'
 *   needs reply   = last_inbound IS NOT NULL AND (last_outbound IS NULL OR last_outbound < last_inbound)
 *
 * So there is nothing to backfill into a new column. What happened is that the inbox
 * started asking a question the historical data cannot answer, and the honest reading of
 * a thread that shows as unanswered is "this system has no record of us replying" — which
 * is not the same claim as "nobody replied".
 *
 * There are four distinct reasons a genuinely-answered thread can read as unanswered, and
 * they need different remedies. This command sizes each one so the remedy is chosen
 * against real numbers instead of a guess. Every section below names the remedy it
 * argues for.
 */
class AuditReplyClock extends Command
{
    protected $signature = 'inbox:audit-reply-clock
        {--since= : Test a cutover date without setting it, e.g. --since=2026-08-20}
        {--type-backfill-before=2025-07-22 : Rows created before this date predate the emails.type column}';

    protected $description = 'Explain the Needs-reply backlog: how big it is, and which cause accounts for what';

    public function handle(): int
    {
        $this->line('');
        $this->info('Needs-reply audit — read-only, nothing is modified.');
        $this->line('');

        $this->shape();
        $this->backlog();
        $this->causes();
        $this->remedies();

        $this->line('');
        $this->comment('Nothing was written. Re-run after any fix to see the numbers move.');
        $this->line('');

        return self::SUCCESS;
    }

    // ----------------------------------------------------------------- sections

    /** What is actually in the table. Everything else is interpretation of this. */
    private function shape(): void
    {
        $this->line('<options=bold>1. What is in the emails table</>');

        $rows = DB::table('emails')
            ->whereNull('deleted_at')
            ->selectRaw('type, status, COUNT(*) as rows_count')
            ->selectRaw('SUM(CASE WHEN sent_at IS NULL THEN 1 ELSE 0 END) as no_sent_at')
            ->selectRaw('MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->groupBy('type', 'status')
            ->orderByDesc('rows_count')
            ->get();

        $this->table(
            ['type', 'status', 'rows', 'sent_at null', 'first', 'last'],
            $rows->map(fn ($r) => [
                $r->type,
                $r->status,
                number_format($r->rows_count),
                number_format($r->no_sent_at),
                $this->day($r->first_seen),
                $this->day($r->last_seen),
            ])->all()
        );

        // A type outside the enum would break Eloquent hydration, not just this view.
        $types = DB::table('emails')->distinct()->pluck('type')->all();
        $unexpected = array_diff($types, ['sent', 'received']);

        if ($unexpected) {
            $this->error('  Unexpected values in emails.type: '.implode(', ', array_map(
                fn ($t) => $t === null ? 'NULL' : "'{$t}'",
                $unexpected
            )));
            $this->line('  The column is a plain varchar with no constraint, and EmailType is a');
            $this->line('  cast — these rows will throw on hydration, not just skew this view.');
        }

        $this->line('');
    }

    /** The headline number, and how much of it is even client mail. */
    private function backlog(): void
    {
        $this->line('<options=bold>2. The backlog</>');

        $needsReply = $this->countConversations($this->needsReplySql());

        // Not an EXISTS over the MAX() subquery: an aggregate with no GROUP BY always
        // returns exactly one row, so that EXISTS was unconditionally true and this line
        // silently reported the total conversation count instead of the inbound one.
        $withInbound = $this->countConversations(
            'EXISTS (SELECT 1 FROM emails e WHERE e.conversation_id = conversations.id'
            ." AND e.deleted_at IS NULL AND e.type = 'received')"
        );

        $this->row('Conversations, all', (int) DB::table('conversations')->count());
        $this->row('Conversations showing as needing a reply', $needsReply);
        $this->row('  of those, on no project (leads + unmatched mail)',
            $this->countConversations($this->needsReplySql().' AND conversations.project_id IS NULL'));
        $this->row('  of those, no project AND no client/lead attached',
            $this->countConversations(
                $this->needsReplySql()
                .' AND conversations.project_id IS NULL AND conversations.conversable_id IS NULL'
            ));
        $this->row('Conversations with any inbound mail at all', $withInbound);

        $this->line('');
    }

    /** Which cause accounts for what. These overlap; a thread can have two problems. */
    private function causes(): void
    {
        $this->line('<options=bold>3. Why they read as unanswered</>');
        $this->line('  (overlapping — one thread can be counted under more than one cause)');
        $this->line('');

        $before = $this->option('type-backfill-before');

        // A. Nothing outbound exists at all. Usually means the reply was sent from the
        //    Gmail web UI: the poller only asks Gmail for `is:inbox`, so a reply typed
        //    into Gmail never becomes a row here.
        $this->row('A. No outbound row of ANY status on the thread',
            $this->countConversations(
                $this->needsReplySql()
                .' AND NOT EXISTS (SELECT 1 FROM emails e2 WHERE e2.conversation_id = conversations.id'
                ." AND e2.deleted_at IS NULL AND e2.type = 'sent')"
            ));

        // B. An outbound row exists, is newer than the client's message, but never got
        //    promoted to `sent`. Every outbound row is born `draft`; only two code paths
        //    in the whole app promote it.
        $this->row('B. Outbound row IS newer, but its status is not "sent"',
            $this->countConversations(
                $this->needsReplySql()
                .' AND ('.$this->latest('sent', false).') > ('.$this->latest('received', false).')'
            ));

        $stuck = DB::table('emails')
            ->whereNull('deleted_at')
            ->where('type', 'sent')
            ->where('status', '!=', 'sent')
            ->selectRaw('status, COUNT(*) as rows_count')
            ->selectRaw('SUM(CASE WHEN approved_by IS NOT NULL THEN 1 ELSE 0 END) as approved')
            ->selectRaw('SUM(CASE WHEN gmail_thread_id IS NOT NULL THEN 1 ELSE 0 END) as threaded')
            ->groupBy('status')
            ->orderByDesc('rows_count')
            ->get();

        if ($stuck->isNotEmpty()) {
            $this->line('');
            $this->line('   Outbound rows not at status "sent", and what evidence they carry.');
            $this->line('');
            $this->line('   Note what is NOT here: emails.message_id. That column holds the Gmail API');
            $this->line('   id and is written ONLY by the inbound poller — no outbound row has ever');
            $this->line('   had one. So "did this go out?" cannot be answered from it.');
            $this->line('');
            $this->line('   approved_by      NOT proof of delivery. The reject and resubmit paths');
            $this->line('                    write it too, so on a rejected row it records who refused');
            $this->line('                    it. Only meaningful read next to the status.');
            $this->line('   gmail_thread_id  written only after a successful send, but only since the');
            $this->line('                    Aug-2026 threading migration — expect ~0 on old rows.');
            $this->table(
                ['status', 'rows', 'has approved_by', 'has gmail_thread_id'],
                $stuck->map(fn ($r) => [
                    $r->status,
                    number_format($r->rows_count),
                    number_format($r->approved),
                    number_format($r->threaded),
                ])->all()
            );
        }

        // C. emails.type was added 2025-07-22 as `varchar default 'sent'` with no
        //    backfill, so every row older than that — inbound included — says 'sent'.
        //    Those inbound rows are invisible to BOTH subqueries: not type='received',
        //    and not status='sent'.
        $mislabelled = DB::table('emails')
            ->whereNull('deleted_at')
            ->where('type', 'sent')
            ->whereIn('status', ['received', 'pending_approval_received', 'rejected_received'])
            ->count();

        $this->line('');
        $this->row("C. Inbound rows mislabelled type='sent' (predate the type column)", $mislabelled);

        if ($mislabelled > 0) {
            $oldest = DB::table('emails')
                ->whereNull('deleted_at')
                ->where('type', 'sent')
                ->whereIn('status', ['received', 'pending_approval_received', 'rejected_received'])
                ->max('created_at');

            $this->line("   Newest such row: {$this->day($oldest)} (the column landed {$before}).");
            $this->line('   These count as neither inbound nor outbound, so a thread answered for');
            $this->line('   years reads as never answered the moment one new client email arrives.');
        }

        // D. Ingested mail that was never a client asking a question.
        $this->line('');
        $this->row('D. Threads with no project AND no client/lead (newsletters, bounces, alerts)',
            $this->countConversations(
                $this->needsReplySql()
                .' AND conversations.project_id IS NULL AND conversations.conversable_id IS NULL'
            ));
        $this->line('   The poller filters on nothing but `is:inbox` and Gmail-id de-duplication —');
        $this->line('   no auto-reply, bounce, no-reply or self-address check — so these accumulate');
        $this->line('   forever and can never acquire a reply. This is a floor under the count.');

        $this->line('');
    }

    /** What each fix would actually clear, before anyone commits to one. */
    private function remedies(): void
    {
        $this->line('<options=bold>4. What each remedy would clear</>');
        $this->line('');

        $now = $this->countConversations($this->needsReplySql());

        // 1. Treat `approved` as delivered. Pure query change, no data written.
        $withApproved = $this->countConversations($this->needsReplySql(['sent', 'approved']));
        $this->row('Count today', $now);
        $this->row('If "approved" also counted as delivered', $withApproved, $now - $withApproved);

        /*
         * 2. Deliberately no approved_by-based remedy.
         *
         * An earlier version offered one, reasoning that approved_by is written in the same
         * update() as status=sent. That reasoning is wrong: the reject and resubmit paths
         * write it too, so on a rejected email it records who refused it. Counting those as
         * delivered would mark a thread answered by an email that was explicitly refused
         * and never sent. The table above shows the column so it can be read next to the
         * status; it is not a rule.
         */

        // 3. The most generous reading the data supports: any outbound row at all, of any
        //    status, counts as a reply. This is the CEILING — it is what you would get by
        //    assuming every draft ever written was actually sent, which is certainly false.
        //    Its value is as a bound: the gap between it and the count today is the total
        //    that any status-based remedy could ever recover.
        $anyOutbound = $this->countConversations(
            $this->needsReplyRaw(
                'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
                .' WHERE e.conversation_id = conversations.id AND e.deleted_at IS NULL'
                ." AND e.type = 'sent'"
                ." AND e.status NOT IN ('received','pending_approval_received','rejected_received')"
            )
        );
        $this->row('CEILING — if ANY outbound row counted, whatever its status', $anyOutbound, $now - $anyOutbound);
        $this->line('');
        $this->line('   Read the ceiling first. Whatever it does not clear cannot be recovered from');
        $this->line('   the emails table by any status rule — those threads have no outbound row at');
        $this->line('   all, because the reply was sent from Gmail directly and never came back.');

        // 4. A cutover date. The blunt instrument, and the only one that is certain.
        $this->line('');
        $this->line('   Cutover — ignore threads whose newest CLIENT message predates a date:');
        $this->line('');

        foreach (['-7 days', '-30 days', '-90 days', '-180 days'] as $window) {
            $this->cutoverLine(now()->modify($window)->toDateTimeString(), $now);
        }

        // The value actually in force right now, and any value passed to --since, so a
        // cutover can be checked before it is committed to and confirmed after.
        $configured = config('inbox.reply_clock.since');
        $asked = $this->option('since');

        $this->line('');

        if ($asked) {
            $this->line('   Your --since:');
            $this->cutoverLine(\Carbon\CarbonImmutable::parse($asked)->toDateTimeString(), $now, true);
        }

        if ($configured) {
            $this->line('   INBOX_REPLY_CLOCK_SINCE is currently SET — this is what the inbox shows:');
            $this->cutoverLine(\Carbon\CarbonImmutable::parse($configured)->toDateTimeString(), $now, true);
        } else {
            $this->line('   INBOX_REPLY_CLOCK_SINCE is not set, so no cutover is in force and the');
            $this->line('   inbox is showing the full count above.');
        }

        $this->line('');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string>  $sentStatuses  which outbound statuses count as delivered
     */
    private function needsReplySql(array $sentStatuses = ['sent']): string
    {
        $list = "'".implode("','", $sentStatuses)."'";

        return $this->needsReplyRaw(
            'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
            .' WHERE e.conversation_id = conversations.id AND e.deleted_at IS NULL'
            ." AND e.type = 'sent' AND e.status IN ({$list})"
        );
    }

    private function needsReplyRaw(string $outboundSql): string
    {
        $inbound = $this->latest('received', false);

        return "({$inbound}) IS NOT NULL AND ((({$outboundSql})) IS NULL OR (({$outboundSql})) < ({$inbound}))";
    }

    private function latest(string $type, bool $sentOnly): string
    {
        $status = $sentOnly ? " AND e.status = 'sent'" : '';

        return 'SELECT MAX(COALESCE(e.sent_at, e.created_at)) FROM emails e'
            .' WHERE e.conversation_id = conversations.id AND e.deleted_at IS NULL'
            ." AND e.type = '{$type}'{$status}";
    }

    private function countConversations(string $whereRaw): int
    {
        return (int) DB::table('conversations')->whereRaw($whereRaw)->count();
    }

    private function row(string $label, int $value, ?int $delta = null): void
    {
        $line = '  '.str_pad($label, 62, '.').' '.str_pad(number_format($value), 9, ' ', STR_PAD_LEFT);

        if ($delta !== null) {
            $line .= $delta > 0 ? "   (-{$delta})" : '   (no change)';
        }

        $this->line($line);
    }

    /** One "since X → N remain" row. */
    private function cutoverLine(string $since, int $now, bool $highlight = false): void
    {
        $remaining = $this->countConversations(
            $this->needsReplySql().' AND ('.$this->latest('received', false).") >= '{$since}'"
        );

        $line = sprintf(
            '     since %s  →  %s remain  (clears %s)',
            str_pad(substr($since, 0, 16), 18),
            str_pad(number_format($remaining), 8),
            number_format($now - $remaining)
        );

        $highlight ? $this->info($line) : $this->line($line);
    }

    private function day(?string $timestamp): string
    {
        return $timestamp ? substr((string) $timestamp, 0, 10) : '—';
    }
}
