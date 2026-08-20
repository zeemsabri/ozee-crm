<?php

namespace App\Services\Inbox;

use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;
use App\Models\Email;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders an email's readable body for display.
 *
 * Exists because `emails.body` is NOT the content of a templated email. When
 * `template_id` is set the composer stores `body => null` and the real text lives in
 * `email_templates.body_html` plus the row's `template_data`; it is rendered on read by
 * `HandlesTemplatedEmails::renderEmailContent`. The first version of the redesigned inbox
 * read `body` directly, so every templated email appeared completely blank while custom
 * ones looked fine.
 *
 * Reuses the trait rather than reimplementing substitution: placeholder resolution pulls
 * from source models, handles repeatables, links and the magic-link special case, and a
 * second implementation would drift.
 *
 * **Always renders in preview mode** (`$isFinalSend = false`). That matters for two
 * reasons: it stops a magic link being minted and persisted every time somebody opens a
 * thread, and it means this class never touches `$this->magicLinkService`, which the
 * trait expects its host controller to provide and which does not exist here.
 *
 * Block-built emails get one extra step: their stored body carries `cid:` image
 * references, which are meaningful inside a MIME message and meaningless to a browser.
 * BlockComposition::toPreviewHtml swaps them for signed GCS URLs on the way out, per read
 * and never persisted. Every other body passes through untouched.
 */
class EmailBodyRenderer
{
    use HandlesTemplatedEmails;

    /** Rendered bodies, keyed by email id — a thread view asks more than once per email. */
    private array $cache = [];

    public function __construct(private readonly BlockComposition $blocks) {}

    /**
     * The email's body as HTML fragment, ready to display.
     *
     * Returns null rather than throwing. renderEmailContent() throws outright when a
     * templated email has no resolvable recipient (a deleted client, a lead conversation),
     * and a thread must still open with the rest of its messages readable.
     */
    public function body(Email $email): ?string
    {
        return $this->render($email)['body'] ?? null;
    }

    /** The email's subject — re-rendered from the template when there is one. */
    public function subject(Email $email): string
    {
        return $this->render($email)['subject'] ?? (string) $email->subject;
    }

    /** True when this email's text comes from a template rather than from `body`. */
    public function isTemplated(Email $email): bool
    {
        return $email->template_id !== null;
    }

    /**
     * @return array{subject:string,body:?string}
     */
    private function render(Email $email): array
    {
        if (array_key_exists($email->id, $this->cache)) {
            return $this->cache[$email->id];
        }

        if (! $email->template_id) {
            return $this->cache[$email->id] = [
                'subject' => (string) $email->subject,
                // No-op unless this email was built in the block builder.
                'body' => $this->blocks->toPreviewHtml($email->body, $email),
            ];
        }

        try {
            $rendered = $this->renderEmailContent($email, false);

            return $this->cache[$email->id] = [
                'subject' => (string) ($rendered['subject'] ?? $email->subject),
                'body' => $rendered['body'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::warning('inbox: could not render templated email body.', [
                'email_id' => $email->id,
                'template_id' => $email->template_id,
                'error' => $e->getMessage(),
            ]);

            return $this->cache[$email->id] = [
                'subject' => (string) $email->subject,
                // Null, not an error string: the caller shows a "cannot be rendered here"
                // placeholder rather than leaking an exception message into the thread.
                'body' => null,
            ];
        }
    }
}
