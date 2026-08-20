<?php

namespace App\Console\Commands\Inbox;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs inbound emails that are recorded as outbound.
 *
 * ## The bug
 *
 * `emails.type` was added on 2025-07-22 by
 * `2025_07_22_224552_add_type_column_in_emails_table.php`:
 *
 *     $table->string('type')->default('sent')->after('rejection_reason');
 *
 * A NOT NULL column with a default and **no backfill**. Every row that already existed —
 * years of client mail included — was silently stamped `type = 'sent'`. (I checked: the
 * only `UPDATE emails` statement in the entire migration set is commented out, in
 * `2025_07_22_231040_add_sender_type_to_emails_table.php`.)
 *
 * Those rows are invisible to both halves of the reply clock: not `type='received'`, so
 * they are not inbound; not a delivered status, so they are not outbound either. The
 * visible symptom is a thread the team answered for two years reading as never answered
 * the moment one new client email arrives — the new message registers as inbound, and
 * every reply that preceded it has been erased from the outbound side.
 *
 * ## Why this is safe to run
 *
 * The rows are self-identifying. `status` predates `type` by two years
 * (`2023_07_17_084838_update_emails_table_status.php`), and `received`,
 * `pending_approval_received` and `rejected_received` are only ever written to mail that
 * ARRIVED — by `EmailReceiveController` and by the inbound branch of the automation
 * workflow. Nothing in the codebase has ever written one of those three to an outbound
 * email. So `type='sent' AND status IN (...received)` is a contradiction that can only
 * have one cause, and the repair direction is unambiguous.
 *
 * It is also reversible: the same predicate that selects the rows describes exactly what
 * changed, and `--rollback` puts them back.
 *
 * Always run `--dry-run` first, and read the per-year breakdown — if it shows rows dated
 * AFTER the migration, something else is writing this combination and you should find out
 * what before repairing anything.
 */
class FixEmailTypes extends Command
{
    protected $signature = 'inbox:fix-email-types
        {--dry-run : Show what would change and change nothing}
        {--rollback : Put the rows this command changed back to type=sent}
        {--chunk=500 : Rows per update statement}';

    protected $description = 'Correct inbound emails stamped type=sent by the un-backfilled 2025-07-22 migration';

    /**
     * Statuses only ever written to mail that arrived.
     *
     * Deliberately NOT the full set of statuses inbound mail can carry. Inbound is also
     * stored at `draft` (EmailReceiveController's normal path) and `unknown`
     * (handleUnknownEmail), but those two are indistinguishable from a genuine unsent
     * outbound draft by status alone, so repairing on them would be a coin flip. Rule B
     * below catches those instead, using evidence rather than inference.
     */
    private const INBOUND_STATUSES = [
        'received',
        'pending_approval_received',
        'rejected_received',
    ];

    /** The migration that introduced the bug. Rows after this want explaining. */
    private const MIGRATION_DATE = '2025-07-22';

    public function handle(): int
    {
        $rollback = (bool) $this->option('rollback');
        $dryRun = (bool) $this->option('dry-run');

        $from = $rollback ? 'received' : 'sent';
        $to = $rollback ? 'sent' : 'received';

        /*
         * Two independent detection rules, OR'd together.
         *
         * A — status is one of the three that only ever describe mail that arrived.
         *
         * B — the row carries a `message_id`. That column holds Gmail's API id and is
         *     written by exactly two lines in the codebase, both in EmailReceiveController,
         *     both inbound. No outbound row has ever had one. So `type='sent'` plus a
         *     message_id is a contradiction with a single possible cause, whatever the
         *     status says — and it catches the inbound rows sitting at `draft` or
         *     `unknown` that rule A cannot safely touch.
         *
         *     Bounded to rows created before the type column landed. From 2026 onward
         *     IngestSentMail legitimately writes message_id onto genuine outbound rows,
         *     and those must never be flipped to inbound.
         */
        $base = fn () => DB::table('emails')
            ->where('type', $from)
            ->where(function ($q) {
                $q->whereIn('status', self::INBOUND_STATUSES)
                    ->orWhere(fn ($e) => $e
                        ->whereNotNull('message_id')
                        ->where('created_at', '<', self::MIGRATION_DATE));
            });

        $total = (clone $base())->count();

        $this->line('');

        if ($total === 0) {
            if ($rollback) {
                $this->info('Nothing to roll back.');
                $this->line('');

                return self::SUCCESS;
            }

            $this->info('Nothing to fix — no outbound row looks like mislabelled inbound mail.');
            $this->line('');
            $this->line('  Checked both rules:');
            $this->line('    A  type=sent with an inbound-only status');
            $this->line('    B  type=sent carrying a Gmail message_id from before '.self::MIGRATION_DATE);
            $this->line('');
            $this->line('  So the 2025-07-22 migration either was backfilled by hand, or every');
            $this->line('  inbound row predating it has since been corrected. Either way this is');
            $this->line('  not contributing to your Needs-reply backlog — rule it out and move on.');
            $this->line('');
            $this->comment('  Run `php artisan inbox:audit-reply-clock` for what IS contributing.');
            $this->line('');

            return self::SUCCESS;
        }

        $this->warn(sprintf(
            '%s %s rows: type=%s → type=%s',
            $dryRun ? 'Would change' : 'Changing',
            number_format($total),
            $from,
            $to
        ));

        $byRule = [
            'A — inbound-only status ('.implode(', ', self::INBOUND_STATUSES).')' => (clone $base())
                ->whereIn('status', self::INBOUND_STATUSES)->count(),
            'B — has a Gmail message_id, created before '.self::MIGRATION_DATE => (clone $base())
                ->whereNotNull('message_id')
                ->where('created_at', '<', self::MIGRATION_DATE)
                ->whereNotIn('status', self::INBOUND_STATUSES)
                ->count(),
        ];

        $this->line('');
        foreach ($byRule as $label => $count) {
            $this->line('  '.str_pad($label, 68, '.').' '.number_format($count));
        }

        $this->breakdown($base());

        if (! $rollback) {
            $after = (clone $base())
                ->whereIn('status', self::INBOUND_STATUSES)
                ->where('created_at', '>=', self::MIGRATION_DATE)
                ->count();

            if ($after > 0) {
                $this->line('');
                $this->error(sprintf(
                    '  %s of these were created AFTER %s, when the column already existed.',
                    number_format($after),
                    self::MIGRATION_DATE
                ));
                $this->line('  The un-backfilled migration cannot explain those, so something is');
                $this->line('  actively writing an inbound status onto an outbound row. Find out what');
                $this->line('  before repairing — this command would paper over a live bug.');
            }
        }

        if ($dryRun) {
            $this->line('');
            $this->comment('Dry run — nothing was written. Re-run without --dry-run to apply.');
            $this->line('');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && ! $this->confirm("Apply this to {$total} rows?", false)) {
            $this->line('Aborted, nothing written.');

            return self::SUCCESS;
        }

        $changed = $this->apply($from, $to);

        $this->line('');
        $this->info("Updated {$changed} rows.");
        $this->line('');
        $this->comment('Re-run `php artisan inbox:audit-reply-clock` to see the effect.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Chunked by primary key so a large table does not become one enormous lock.
     *
     * Re-selects ids each pass rather than paginating: every update removes rows from the
     * predicate, so an OFFSET would skip over rows as the set shrinks under it.
     */
    private function apply(string $from, string $to): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $changed = 0;

        $bar = $this->output->createProgressBar($this->selector($from)->count());
        $bar->start();

        while (true) {
            $ids = $this->selector($from)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (! $ids) {
                break;
            }

            // No `updated_at` touch. These rows are being corrected, not modified — and
            // several views sort on it, so bumping thousands of years-old emails to today
            // would reorder history to announce a bug fix.
            $changed += DB::table('emails')->whereIn('id', $ids)->update(['type' => $to]);

            $bar->advance(count($ids));
        }

        $bar->finish();

        return $changed;
    }

    /** The detection predicate, in one place so counting and updating cannot diverge. */
    private function selector(string $type)
    {
        return DB::table('emails')
            ->where('type', $type)
            ->where(function ($q) {
                $q->whereIn('status', self::INBOUND_STATUSES)
                    ->orWhere(fn ($e) => $e
                        ->whereNotNull('message_id')
                        ->where('created_at', '<', self::MIGRATION_DATE));
            });
    }

    /** Per-year counts, so the shape of the damage is visible before it is repaired. */
    private function breakdown($query): void
    {
        $rows = $query
            ->selectRaw('YEAR(created_at) as yr, status, COUNT(*) as rows_count')
            ->groupBy('yr', 'status')
            ->orderBy('yr')
            ->get();

        $this->line('');
        $this->table(
            ['year', 'status', 'rows'],
            $rows->map(fn ($r) => [$r->yr, $r->status, number_format($r->rows_count)])->all()
        );
    }
}
