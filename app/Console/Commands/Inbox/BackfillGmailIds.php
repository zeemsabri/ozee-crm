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
            // Either route is enough: the Message-ID header, or the Gmail thread we
            // recorded at send time. See GmailCopy for why the header alone is not.
            ->where(fn ($q) => $q->whereNotNull('rfc_message_id')->orWhereNotNull('gmail_thread_id'))
            // Only mail that actually reached Gmail. A draft has no copy there, and a
            // rejected draft never will.
            ->whereIn('status', [EmailStatus::Sent->value, EmailStatus::Approved->value]);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to do — every sent email with a Message-ID already has a Gmail id.');

            return self::SUCCESS;
        }

        $this->line("{$total} sent email(s) have no Gmail id.");
        $this->line($dryRun ? 'Dry run — nothing will be written.' : "Attempting up to {$limit}.");
        $this->newLine();

        $rows = $query->orderByDesc('id')->limit($limit)->get();

        $resolved = 0;
        $missing = 0;
        // Which route answered. Worth reporting rather than just counting: if `header` is
        // zero and `thread` is not, Gmail is replacing the Message-ID we mint on the way
        // out, and anything that relies on matching that header — IngestSentMail's
        // enrichment in particular — is not doing what it looks like it does.
        $byRoute = ['header' => 0, 'thread' => 0];

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $email) {
            $via = null;

            // A dry run still does the real lookups — the whole question is how many Gmail
            // can actually find, and which way. Only the write is skipped.
            $found = $dryRun
                ? $this->probe($email, $via)
                : $gmail->idFor($email, $via) !== null;

            if ($found) {
                $resolved++;

                if ($via && isset($byRoute[$via])) {
                    $byRoute[$via]++;
                }
            } else {
                $missing++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'Would resolve: ' : 'Resolved: ')."{$resolved}");

        if ($resolved) {
            $this->line("  by Message-ID search: {$byRoute['header']}");
            $this->line("  by Gmail thread:      {$byRoute['thread']}");

            if ($byRoute['header'] === 0 && $byRoute['thread'] > 0) {
                $this->newLine();
                $this->warn('  Every one of those was found by thread, none by Message-ID.');
                $this->line('  That means Gmail is REPLACING the Message-ID we set when it sends,');
                $this->line('  so emails.rfc_message_id does not name anything in the mailbox.');
                $this->line('  Consequence worth knowing: IngestSentMail matches our own sent');
                $this->line('  mail on that header, so its "enriched" branch never fires.');
            }
        }

        if ($missing) {
            $this->warn("Not found in Gmail: {$missing}");
            $this->line('  Already in the Gmail bin, sent from outside this system, or sent');
            $this->line('  before we recorded a thread id. Nothing is wrong with the rows.');
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
     * GmailCopy::idFor persists what it finds, which is right everywhere except here.
     * Rather than give that method a flag nobody else would ever pass, the dry run repeats
     * the same two searches and discards the result. Kept deliberately parallel to
     * GmailCopy — if the matching rule there changes, change it here too or the dry run
     * stops predicting the real one.
     */
    private function probe(Email $email, ?string &$via = null): bool
    {
        $gmail = app(\App\Services\GmailService::class);
        $addrSpec = trim((string) $email->rfc_message_id, '<> ');

        if ($addrSpec !== '') {
            try {
                if ($gmail->listMessages(1, 'rfc822msgid:'.$addrSpec) !== []) {
                    $via = 'header';

                    return true;
                }
            } catch (\Throwable) {
                // Fall through to the thread route.
            }
        }

        $threadId = trim((string) $email->gmail_thread_id);

        if ($threadId === '') {
            return false;
        }

        try {
            // Only asking whether the thread is still there and has anything in it. The
            // precise which-message-is-ours rule is GmailCopy's, and a dry run does not
            // need to reproduce its one-hour bound to answer "is this findable".
            if ($gmail->getThread($threadId) !== []) {
                $via = 'thread';

                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
