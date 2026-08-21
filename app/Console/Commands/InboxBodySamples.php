<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Services\Inbox\EmailHtml;
use Illuminate\Console\Command;

/**
 * Show how the inbox will render real stored bodies, by type.
 *
 * `emails.body` holds four different things and the display path has to tell them apart:
 * received mail is plain text (EmailReceiveController::cleanEmailBody strips every tag
 * before storing), a reply from the redesigned composer is plain text (a textarea), a
 * legacy custom email is rich-editor HTML, and a block email is our own fragment. A
 * templated email keeps nothing in the column at all.
 *
 * That classification was read out of the code, not out of the data. This command checks
 * it against the data: for a handful of real rows of each shape it prints what EmailHtml
 * decided and what it produced, so a wrong guess shows up as escaped tags or a run-on
 * paragraph right here rather than in the thread view.
 *
 *   php artisan inbox:body-samples
 *   php artisan inbox:body-samples --per=5 --chars=600
 *   php artisan inbox:body-samples --id=1234
 */
class InboxBodySamples extends Command
{
    protected $signature = 'inbox:body-samples
        {--per=3 : How many rows of each kind}
        {--chars=400 : How much of each body to print}
        {--id= : Inspect one specific email id instead}';

    protected $description = 'Show how stored email bodies are classified and rendered by the redesigned inbox';

    public function handle(EmailHtml $html): int
    {
        $chars = (int) $this->option('chars');

        if ($id = $this->option('id')) {
            $email = Email::find($id);

            if (! $email) {
                $this->error("No email {$id}.");

                return self::FAILURE;
            }

            $this->dump($html, $email, $chars);

            return self::SUCCESS;
        }

        $per = (int) $this->option('per');

        $groups = [
            'received (inbound — expected PLAIN TEXT)' => Email::query()
                ->whereNull('template_id')
                ->where('type', 'received'),

            'templated (body lives in the template, not this column)' => Email::query()
                ->whereNotNull('template_id'),

            'outbound custom / blocks (mixed — textarea plain text OR editor HTML)' => Email::query()
                ->whereNull('template_id')
                ->where('type', '!=', 'received'),
        ];

        foreach ($groups as $label => $query) {
            $this->newLine();
            $this->line(str_repeat('=', 78));
            $this->info($label);
            $this->line(str_repeat('=', 78));

            $rows = $query->latest('id')->limit($per)->get();

            if ($rows->isEmpty()) {
                $this->warn('  (no rows)');

                continue;
            }

            foreach ($rows as $email) {
                $this->dump($html, $email, $chars);
            }
        }

        $this->newLine();
        $this->comment('Look for: escaped tags in the OUTPUT (text wrongly classified as HTML),');
        $this->comment('a single run-on paragraph (HTML wrongly classified as text), or a QUOTE');
        $this->comment('split that swallowed part of the real message.');

        return self::SUCCESS;
    }

    private function dump(EmailHtml $html, Email $email, int $chars): void
    {
        $raw = (string) $email->body;
        $looksHtml = $html->looksLikeHtml($raw);
        $parts = $html->display($raw);

        $this->newLine();
        $this->line(sprintf(
            '<fg=cyan>#%d</> type=%s template_id=%s stored=%d chars — classified as <fg=yellow>%s</>',
            $email->id,
            $email->type ?? '?',
            $email->template_id ?? '—',
            mb_strlen($raw),
            $looksHtml ? 'HTML' : 'PLAIN TEXT'
        ));

        $this->line('  <fg=gray>--- stored ---</>');
        $this->line('  '.$this->clip($raw, $chars));

        $this->line('  <fg=gray>--- rendered body ---</>');
        $this->line('  '.$this->clip($parts['body'], $chars));

        if ($parts['quote'] !== null) {
            $this->line('  <fg=gray>--- folded quote ('.mb_strlen($parts['quote']).' chars) ---</>');
            $this->line('  '.$this->clip($parts['quote'], 200));
        } else {
            $this->line('  <fg=gray>--- no quoted chain detected ---</>');
        }
    }

    private function clip(string $value, int $chars): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_strlen($value) > $chars
            ? mb_substr($value, 0, $chars).' …'
            : ($value === '' ? '(empty)' : $value);
    }
}
