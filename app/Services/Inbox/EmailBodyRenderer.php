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
 * ## Why nl2br is off here
 *
 * The trait applies `nl2br` unconditionally, and it has to: a custom email is composed in
 * a textarea, so that call is the only reason the client's copy has line breaks at all.
 * But a TEMPLATE's body is already HTML, and running nl2br over its source inserts a
 * `<br>` after every newline between its tags — which is the stray double-spacing on
 * templated emails. So this class asks for the raw render and hands the result to
 * EmailHtml, which decides per body whether it is text or markup.
 *
 * That means the inbox shows a templated email slightly tighter than the client receives
 * it, until the same conditional is applied to the send path too. "As the client sees it"
 * goes through the real send render, so the exact spacing stays one click away.
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

    public function __construct(
        private readonly BlockComposition $blocks,
        private readonly EmailHtml $html,
    ) {}

    /**
     * The email's body as an HTML fragment, ready to display, with any quoted reply chain
     * taken off the end. `quote()` returns the part that was removed.
     *
     * Returns null rather than throwing. renderEmailContent() throws outright when a
     * templated email has no resolvable recipient (a deleted client, a lead conversation),
     * and a thread must still open with the rest of its messages readable.
     */
    public function body(Email $email): ?string
    {
        return $this->render($email)['body'] ?? null;
    }

    /**
     * The quoted chain folded off the end of the body, or null when there was none.
     *
     * Inbound mail carries the whole history inline — a three-message exchange arrives as
     * the new reply plus every earlier message re-quoted underneath. Leaving that in a
     * thread which ALREADY lists those messages means reading each one several times over.
     */
    public function quote(Email $email): ?string
    {
        return $this->render($email)['quote'] ?? null;
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
     * @return array{subject:string,body:?string,quote:?string}
     */
    private function render(Email $email): array
    {
        if (array_key_exists($email->id, $this->cache)) {
            return $this->cache[$email->id];
        }

        if (! $this->isTemplated($email)) {
            // No-op unless this email was built in the block builder.
            $raw = $this->blocks->toPreviewHtml($email->body, $email);

            /*
             * Four things arrive here and only two of them are HTML: received mail is
             * plain text (EmailReceiveController::cleanEmailBody strips every tag before
             * storing), a reply from the redesigned composer is plain text (a textarea), a
             * legacy custom email is rich-editor HTML, and a block email is our own
             * fragment. EmailHtml decides which — guessing wrong in either direction gives
             * you a run-on wall of text or escaped tags on screen, and this column has
             * produced both.
             */
            $parts = $this->html->display($raw);

            return $this->cache[$email->id] = [
                'subject' => (string) $email->subject,
                'body' => $parts['body'],
                'quote' => $parts['quote'],
            ];
        }

        try {
            // nl2br off — the template body is already HTML. See the class note above.
            $rendered = $this->renderEmailContent($email, false, false);
            $parts = $this->html->display($rendered['body'] ?? null);

            return $this->cache[$email->id] = [
                'subject' => (string) ($rendered['subject'] ?? $email->subject),
                'body' => $parts['body'],
                'quote' => $parts['quote'],
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
                'quote' => null,
            ];
        }
    }
}
