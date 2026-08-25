<?php

namespace App\Console\Commands\Inbox;

use App\Enums\EmailStatus;
use App\Models\Email;
use App\Services\Inbox\GmailCopy;
use Illuminate\Console\Command;

/**
 * Fills in `emails.message_id` for sent mail that only has a Message-ID header.
 *
 * ## Why rows are missing it
 *
 * Gmail's send response does not tell us the id the message lands under in SENT, so the
 * send path (`EmailProcessingService`, `EmailController::editAndApprove`) writes
 * `rfc_message_id` — the RFC 5322 Message-ID header we mint ourselves — and leaves
 * `message_id` null. `IngestSentMail` back-fills it later by walking SENT and matching on
 * that header.
 *
 * That pass is capped at `inbox.sent_ingest.limit` messages per run and starts from
 * `inbox.sent_ingest.first_run_since`, so a busy period leaves a scatter of sent emails
 * with a header and no id — usually a minority, and never in an obvious pattern. The
 * visible symptom is a delete that refuses to bin the Gmail copy of *some* messages.
 *
 * ## What this does differently
 *
 * Instead of walking SENT and hoping to meet the row, it goes the other way: takes each
 * row that is missing an id and asks Gmail for that exact message with `rfc822msgid:`.
 * One lookup per email, targeted, and it can be pointed at the whole backlog.
 *
 * ## Safe to run
 *
 * Reads from Gmail and writes one column. It never sends, never trashes, never touches a
 * row that already has an id, and `--dry-run` shows the counts without writing. Re-running
 * it costs a lookup for whatever is still unresolvable and nothing else.
 */
class BackfillGmailIds extends Command
{
    protected $signature = 'inbox:backfill-gmail-ids
        {--limit=200 : How many emails to attempt in this run}
        {--dry-run : Report what would be resolved without writing}';

    protected $description = 'Resolve emails.message_id from rfc_message_id via Gmail search';

    public function handle(GmailCopy $gmail): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $query = Email::query()
            ->whereNull('message_id')
            ->whereNotNull('rfc_message_id')
            // Only mail that actually reached Gmail. A draft has no copy there, and a
            // rejected draft never will.
            ->whereIn('status', [EmailStatus::Sent->value, EmailStatus::Approved->value]);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to do — every sent email with a Message-ID already has a Gmail id.');

            return self::SUCCESS;
        }

        $this->line("{$total} sent email(s) have a Message-ID but no Gmail id.");
        $this->line($dryRun ? 'Dry run — nothing will be written.' : "Attempting up to {$limit}.");
        $this->newLine();

        $rows = $query->orderByDesc('id')->limit($limit)->get();

        $resolved = 0;
        $missing = 0;

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $email) {
            // A dry run still does the real lookup — the whole question is how many Gmail
            // can actually find. Only the write is skipped.
            $found = $dryRun
                ? $this->probe($email)
                : $gmail->idFor($email) !== null;

            $found ? $resolved++ : $missing++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'Would resolve: ' : 'Resolved: ')."{$resolved}");

        if ($missing) {
            $this->warn("Not found in Gmail: {$missing}");
            $this->line('  Those are usually already in the Gmail bin, or were sent from');
            $this->line('  outside this system. Nothing is wrong with the rows themselves.');
        }

        $remaining = $total - $rows->count();

        if ($remaining > 0) {
            $this->newLine();
            $this->line("{$remaining} still queued — run again, or raise --limit.");
        }

        return self::SUCCESS;
    }

    /**
     * A lookup with the write suppressed, for --dry-run.
     *
     * GmailCopy::idFor persists what it finds, which is the right behaviour everywhere
     * except here. Rather than give that method a flag nobody else would ever pass, the
     * dry run does its own search and discards the result.
     */
    private function probe(Email $email): bool
    {
        $addrSpec = trim((string) $email->rfc_message_id, '<> ');

        if ($addrSpec === '') {
            return false;
        }

        try {
            return app(\App\Services\GmailService::class)
                ->listMessages(1, 'rfc822msgid:'.$addrSpec) !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}
