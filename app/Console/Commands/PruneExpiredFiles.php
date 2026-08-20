<?php

namespace App\Console\Commands;

use App\Actions\Files\DeleteFileAttachmentAction;
use App\Models\FileAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deletes files whose `expires_at` has passed, and their GCS objects.
 *
 * Only touches rows that explicitly opted in by setting `expires_at`. Everything that
 * predates that column — task attachments, invoices, chat, inbound email attachments —
 * has a null value and is never considered.
 *
 * Today the only opted-in files are the inbox's block-builder images. Deleting one does
 * NOT affect any email already sent: those images travel inside the message as CID parts,
 * so the client's copy is permanent. What is lost is our own working copy, which means the
 * composer and our thread view fall back to a placeholder for that image.
 *
 * Goes through DeleteFileAttachmentAction rather than `$file->delete()` — that action is
 * the only thing in the codebase that removes the GCS object as well as the row, and
 * deleting the row directly leaks the object forever.
 */
class PruneExpiredFiles extends Command
{
    protected $signature = 'files:prune-expired {--dry-run : List what would go, delete nothing}';

    protected $description = 'Delete files past their expires_at, and their storage objects';

    public function handle(DeleteFileAttachmentAction $delete): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expired = FileAttachment::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            // Chunked, unlike the vault:prune precedent — this table holds every
            // attachment in the app and a bad TTL could select a very large set.
            ->lazyById(200);

        $deleted = 0;
        $failed = 0;

        foreach ($expired as $file) {
            if ($dryRun) {
                $this->line("would delete #{$file->id}  {$file->filename}  ({$file->path})");
                $deleted++;

                continue;
            }

            $result = $delete->execute($file);

            if ($result['success']) {
                $deleted++;
            } else {
                $failed++;
                $this->warn("failed #{$file->id}: {$result['error']}");
            }
        }

        if ($deleted > 0 || $failed > 0) {
            $summary = $dryRun
                ? "Would prune {$deleted} expired file(s)."
                : "Pruned {$deleted} expired file(s)".($failed ? ", {$failed} failed." : '.');

            $this->info($summary);

            if (! $dryRun) {
                Log::info("Files: {$summary}");
            }
        }

        return self::SUCCESS;
    }
}
