<?php

namespace App\Services\Inbox;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Email;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Makes a reply look like a reply — to Gmail, and to the person receiving it.
 *
 * Two separate jobs, done at SEND time rather than at compose time:
 *
 * 1. **Threading headers.** Gmail groups messages by the RFC 5322 `In-Reply-To` and
 *    `References` headers. Both must carry the parent's **Message-ID header**
 *    (`<CAF…@mail.gmail.com>`) — NOT `emails.message_id`, which holds Gmail's API message
 *    id and matches nothing. That is why `rfc_message_id` exists as its own column.
 *
 * 2. **The quoted thread.** The previous messages are appended to the outgoing HTML here,
 *    and are deliberately NOT stored in `emails.body`.
 *
 * Why the quote is not stored, which is the point of this class:
 *  - The AI checker reads `emails.body`. A stored quote would mean re-sending the entire
 *    conversation to the model on every reply, so the token cost of a thread grows with
 *    its length while adding nothing — the checker is judging the new text, not the
 *    history.
 *  - It keeps the row a record of what someone actually wrote, so the thread view is not
 *    every message repeated inside every later message.
 *  - The client still receives the full quoted chain, because it is built here on the way
 *    out.
 *
 * The trade-off, stated plainly: what we store is no longer byte-identical to what was
 * sent. The reply as delivered can always be reconstructed from the row plus its
 * ancestors, and Gmail keeps the sent copy, so nothing is lost — but a diff of "what we
 * stored" against "what they got" will show the quote.
 */
class ReplyThreading
{
    public function __construct(private readonly EmailBodyRenderer $bodies) {}

    /** How many earlier messages to quote. Beyond this the chain is bulk, not context. */
    private const MAX_QUOTED = 6;

    /**
     * Outbound statuses that may appear in a quote.
     *
     * A thread accumulates drafts, rejected drafts and pending-approval drafts, all sitting
     * on the conversation with ordinary timestamps. Quoting by recency alone would send a
     * client the draft a manager rejected — `is_private` does not cover it, because a
     * rejected draft is not private, it is just not something anybody approved. Only what
     * actually went out can be quoted back.
     */
    private const QUOTABLE_OUTBOUND_STATUSES = [
        EmailStatus::Sent->value,
    ];

    /**
     * Inbound statuses that may NOT be quoted.
     *
     * Note the inversion: inbound mail is allow-everything-except, because
     * EmailReceiveController stores ordinary client mail as
     * `type=received, status=draft` — "draft" on an inbound row means "arrived, not yet
     * processed", NOT "unsent". An allow-list of [sent, received] therefore silently
     * dropped every real client message from the quote, which is the whole history a
     * reply is supposed to carry.
     *
     * Screened inbound mail is excluded not because the client should not see it — they
     * wrote it — but because it has not been released internally, and quoting it would
     * put it in front of whoever is composing the reply.
     */
    private const UNQUOTABLE_INBOUND_STATUSES = [
        EmailStatus::PendingApprovalReceived->value,
    ];

    /**
     * `In-Reply-To` / `References` for a reply, or an empty array when this email is not
     * one (or the parent predates rfc_message_id being captured).
     *
     * `References` carries the ancestry oldest-first and MUST end with the parent, per RFC
     * 5322 §3.6.4 — clients rebuild the thread tree from it, and a chain that omits the
     * immediate parent mis-parents the reply.
     *
     * @return array<string,string>
     */
    public function headersFor(Email $email): array
    {
        $parent = $this->parent($email);

        if (! $parent) {
            return [];
        }

        $ancestry = $this->ancestry($parent);

        /*
         * The message we thread onto is not always the parent.
         *
         * Only two kinds of email carry an rfc_message_id: inbound mail (captured from the
         * header) and outbound replies (stamped by us on the way out). An ordinary
         * outbound email sent before this existed — or one sent by the classic composer,
         * which does not stamp one — has none. Replying to such a message with no headers
         * at all would start a brand-new Gmail conversation.
         *
         * So we walk back to the newest ancestor that does have one, which on any real
         * thread is the client's own message. Gmail groups by the References chain, so
         * threading onto that still lands the reply in the right conversation.
         */
        $anchor = $parent->rfc_message_id
            ? $parent
            : $ancestry->last(fn (Email $e) => (bool) $e->rfc_message_id);

        if (! $anchor) {
            return [];
        }

        $references = $ancestry
            ->pluck('rfc_message_id')
            ->filter()
            ->reject(fn ($id) => $id === $anchor->rfc_message_id)
            ->unique()
            ->values()
            // The message being answered goes last, always — see the RFC note above.
            ->push($anchor->rfc_message_id);

        return [
            'In-Reply-To' => $anchor->rfc_message_id,
            'References' => $references->implode(' '),
        ];
    }

    /**
     * A Message-ID header for an outgoing email, so later replies can thread onto it.
     *
     * Generated by us rather than read back from Gmail: the send API returns its own
     * message id, not the header it stamped, and fetching the sent message afterwards to
     * read the header is a second round trip that can fail independently of the send.
     * Setting the header ourselves means the value we store is definitively the value that
     * went out.
     *
     * Pass the address the message is actually sent FROM. The domain in a Message-ID
     * should match the sending domain — a mismatch is a (mild) spam signal.
     */
    public function newMessageId(Email $email, ?string $fromAddress = null): string
    {
        $domain = 'ozeeweb.com.au';

        if ($fromAddress && str_contains($fromAddress, '@')) {
            $domain = Str::after($fromAddress, '@');
        }

        return '<'.Str::uuid()->toString().'.'.$email->id.'@'.$domain.'>';
    }

    /**
     * The outgoing HTML with the quoted chain added.
     *
     * Inserted before `</body>` when the body is a complete document, appended otherwise.
     * Concatenating after `</html>` produces a malformed document that strict clients
     * truncate — the quote would simply vanish for some recipients while looking correct
     * in Gmail.
     */
    public function withQuotedThread(Email $email, string $renderedBody): string
    {
        $parent = $this->parent($email);

        if (! $parent) {
            return $renderedBody;
        }

        $quoted = $this->quotableAncestry($parent)
            ->reverse()          // newest first, the way mail clients show a quote
            ->take(self::MAX_QUOTED)
            ->map(fn (Email $e) => $this->quoteBlock($e))
            ->implode('');

        if ($quoted === '') {
            return $renderedBody;
        }

        // gmail_quote is the class Gmail itself uses; clients that collapse quoted history
        // recognise it, so the reply reads as a reply rather than a wall of text.
        $block = '<div class="gmail_quote" style="margin-top:16px;padding-top:12px;'
            .'border-top:1px solid #e6e9ef">'
            .$quoted
            .'</div>';

        if (preg_match('/<\/body\s*>/i', $renderedBody)) {
            return preg_replace('/<\/body\s*>/i', $block.'</body>', $renderedBody, 1) ?? $renderedBody.$block;
        }

        return $renderedBody.$block;
    }

    /** True when this email is a reply we should thread and quote. */
    public function isReply(Email $email): bool
    {
        return $email->in_reply_to_email_id !== null;
    }

    // ---------------------------------------------------------------- internals

    private function parent(Email $email): ?Email
    {
        if (! $email->in_reply_to_email_id) {
            return null;
        }

        return Email::find($email->in_reply_to_email_id);
    }

    /**
     * The parent and the messages before it, oldest first — the NEWEST such messages.
     *
     * Ordered DESC in SQL and reversed in PHP on purpose. Taking the oldest N would, on a
     * long thread, quote a months-old exchange under a reply to today's email and leave
     * the parent out of `References` entirely.
     *
     * Walked by conversation and timestamp rather than by in_reply_to_email_id, because
     * only replies composed in the new inbox carry that link — anything older would
     * otherwise produce a one-message chain.
     *
     * @return Collection<int,Email>
     */
    private function ancestry(Email $parent): Collection
    {
        // Never later than the reply itself. An inbound email's sent_at comes from the
        // client's own Date: header, and a skewed-forward clock there would otherwise let
        // a reply quote itself.
        $cutoff = $parent->sent_at ?? $parent->created_at;

        return Email::query()
            ->where('conversation_id', $parent->conversation_id)
            ->whereRaw('COALESCE(sent_at, created_at) <= ?', [$cutoff])
            ->orderByRaw('COALESCE(sent_at, created_at) DESC')
            ->limit(self::MAX_QUOTED * 2)
            ->get()
            ->sortBy(fn (Email $e) => ($e->sent_at ?? $e->created_at)?->timestamp ?? 0)
            ->values();
    }

    /**
     * The ancestry, filtered down to what may actually be shown to the other party.
     *
     * @return Collection<int,Email>
     */
    private function quotableAncestry(Email $parent): Collection
    {
        return $this->ancestry($parent)
            // A manager marked these internal; quoting one would send it to the client,
            // which is the exact thing the flag exists to prevent.
            ->reject(fn (Email $e) => (bool) $e->is_private)
            // Only what the other party has actually seen. See the two constants above —
            // the rule is type-dependent, because `draft` means opposite things on an
            // inbound and an outbound row.
            ->filter(fn (Email $e) => $this->isQuotable($e))
            ->values();
    }

    /** Has the other party already seen this message? */
    private function isQuotable(Email $email): bool
    {
        $status = $this->statusOf($email);

        return $this->isInbound($email)
            ? ! in_array($status, self::UNQUOTABLE_INBOUND_STATUSES, true)
            : in_array($status, self::QUOTABLE_OUTBOUND_STATUSES, true);
    }

    private function isInbound(Email $email): bool
    {
        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        return $type === EmailType::Received->value;
    }

    private function statusOf(Email $email): string
    {
        return $email->status instanceof EmailStatus
            ? $email->status->value
            : strtolower((string) $email->status);
    }

    /** One quoted message, in the shape mail clients expect. */
    private function quoteBlock(Email $email): string
    {
        $who = e($this->senderLabel($email));
        $when = ($email->sent_at ?? $email->created_at)?->format('D, j M Y \a\t g:i a') ?? '';
        // Rendered: a sent templated email has body = null, and quoting the raw column
        // produced an "On …, X wrote:" header above an empty blockquote.
        $body = $this->sanitiseForQuote($this->bodies->body($email));

        // Nothing readable to quote — skip the block entirely rather than emit a header
        // with nothing under it.
        if (trim(strip_tags($body)) === '') {
            return '';
        }

        return '<div style="margin:0 0 12px">'
            .'<div style="color:#676879;font:12px/18px Arial,sans-serif;margin-bottom:4px">'
            .'On '.e($when).', '.$who.' wrote:'
            .'</div>'
            .'<blockquote style="margin:0;padding-left:12px;border-left:2px solid #d0d4e4;color:#404149">'
            .$body
            .'</blockquote>'
            .'</div>';
    }

    private function senderLabel(Email $email): string
    {
        if ($email->sender?->name) {
            return $email->sender->name;
        }

        $type = $email->type instanceof EmailType ? $email->type->value : (string) $email->type;

        return $type === EmailType::Received->value
            ? ($email->conversation?->conversable?->name ?? 'they')
            : 'OZee Web & Digital';
    }

    /**
     * Turn a stored body into something safe to nest inside an outgoing email.
     *
     * Two quite different inputs arrive here:
     *
     *  - **Plain text**, from inbound mail. EmailReceiveController::cleanEmailBody runs
     *    strip_tags() and THEN html_entity_decode(), so an encoded `&lt;img src=x&gt;` in
     *    the original comes out of storage as live `<img src=x>` markup. Escaping is
     *    therefore not optional, and it also fixes the formatting: without nl2br the whole
     *    message renders as one run-on paragraph.
     *  - **HTML**, from our own templated sends, sometimes a whole document.
     *
     * Anything that looks like plain text is escaped wholesale. Anything that looks like
     * HTML is reduced to its body and stripped of the tags that have no business in a
     * quote.
     */
    private function sanitiseForQuote(?string $html): string
    {
        $body = trim((string) $html);

        if ($body === '') {
            return '';
        }

        if (! $this->looksLikeHtml($body)) {
            return nl2br(e($this->trimQuotedTail($body)), false);
        }

        // Keep only the inner body of a full document — nesting <html>/<head>/<style>
        // inside a reply breaks the outer document and lets the quote's CSS repaint it.
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $body, $m)) {
            $body = $m[1];
        }

        $body = preg_replace(
            '/<(script|style|head|title|iframe|object|embed|form|noscript)\b[^>]*>.*?<\/\1>/is',
            '',
            $body
        ) ?? $body;

        $body = preg_replace(
            '/<\/?(html|head|body|meta|link|base|script|style|iframe|object|embed|form|input|button)\b[^>]*>/i',
            '',
            $body
        ) ?? $body;

        // Drop a quote already inside this message, so quoting does not compound down a
        // long thread.
        $body = preg_replace('/<div class="gmail_quote".*$/is', '', $body) ?? $body;
        $body = preg_replace('/<blockquote[^>]*>.*$/is', '', $body) ?? $body;

        // Inline event handlers and javascript: URLs, which have no business in a quote.
        $body = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $body) ?? $body;
        $body = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*/i', '$1=$2#', $body) ?? $body;

        return trim($body);
    }

    /**
     * Has this body got real markup in it, or is it plain text that happens to contain a
     * stray angle bracket? Deliberately conservative: when in doubt, treat it as plain
     * text and escape it.
     */
    private function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/<(p|div|br|table|span|a|ul|ol|li|h[1-6]|body|html)\b[^>]*>/i', $body);
    }

    /**
     * Cut a plain-text body at the point where it starts quoting the thread back at us.
     *
     * Inbound plain text usually carries the entire prior conversation inline, under an
     * "On … wrote:" line or behind `>` markers. Quoting six such messages would repeat the
     * thread six times over.
     */
    private function trimQuotedTail(string $text): string
    {
        $patterns = [
            '/^On .{0,120}wrote:\s*$/mi',
            '/^-{2,}\s*Original Message\s*-{2,}\s*$/mi',
            '/^_{5,}\s*$/m',
            '/^From:\s.+$/mi',
        ];

        $cut = mb_strlen($text);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $cut = min($cut, mb_strlen(substr($text, 0, $m[0][1])));
            }
        }

        $trimmed = rtrim(mb_substr($text, 0, $cut));

        // Also drop a leading run of `>` quote markers if that is all that is left.
        $lines = array_filter(
            explode("\n", $trimmed),
            fn ($line) => ! preg_match('/^\s*>/', $line)
        );

        $result = trim(implode("\n", $lines));

        // If trimming ate everything, the message really was only a quote — fall back to
        // the original rather than showing an empty block.
        return $result !== '' ? $result : trim($text);
    }
}
