<?php

namespace App\Console\Commands\Inbox;

use App\Services\Inbox\IngestSentMail;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Pulls mail sent from the Gmail web UI into the CRM.
 *
 * Runs alongside FetchEmails, which only ever asked Gmail for `is:inbox`. Without this,
 * a reply somebody typed into Gmail never becomes a row, and the thread reads as
 * unanswered forever. See App\Services\Inbox\IngestSentMail for the matching rules and
 * for why it will not create conversations it does not already recognise.
 */
class FetchSentMail extends Command
{
    protected $signature = 'inbox:fetch-sent
        {--since= : Start from this point instead of the last ingested message (e.g. "-2 hours")}
        {--limit=50 : Maximum Gmail messages to examine in one pass}
        {--dry-run : Report what would happen and write nothing}';

    protected $description = 'Ingest replies sent from the Gmail web UI so the reply clock sees them';

    public function handle(IngestSentMail $ingest): int
    {
        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $ingest->run($since, (int) $this->option('limit'), $dryRun);

        $this->line('');
        $this->line(sprintf(
            '%sscanned %d · created %d · enriched %d · skipped %d · unmatched %d',
            $dryRun ? '[dry run] ' : '',
            $stats['scanned'],
            $stats['created'],
            $stats['enriched'],
            $stats['skipped'],
            $stats['unmatched']
        ));

        if ($stats['created'] > 0) {
            $this->info(sprintf(
                '  %d reply/replies were sent from Gmail rather than the CRM — those threads',
                $stats['created']
            ));
            $this->line('  now read as answered.');
        }

        if ($stats['unmatched'] > 0) {
            $this->line('');
            $this->comment(sprintf(
                '  %d sent message(s) matched no known thread and were skipped — internal mail,',
                $stats['unmatched']
            ));
            $this->line('  vendor correspondence, or a client whose conversation is not in the CRM.');
            $this->line('  Skipping is deliberate; see IngestSentMail.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
