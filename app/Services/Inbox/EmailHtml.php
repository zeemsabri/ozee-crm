<?php

namespace App\Services\Inbox;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns whatever is in `emails.body` into HTML that is safe and readable in a thread.
 *
 * There is no single answer, because that column holds four different things depending on
 * how the row was made — and the redesigned inbox originally treated them all as HTML:
 *
 *  1. **Received mail — PLAIN TEXT.** `EmailReceiveController::cleanEmailBody` turns
 *     `<br>` into "\n", block-closing tags into "\n\n", then runs `strip_tags` and
 *     `html_entity_decode`. Nothing HTML survives. Injected as HTML, every one of those
 *     newlines collapses and a client's email becomes one run-on paragraph. This is the
 *     bug that made incoming mail unreadable.
 *  2. **Custom replies from the redesigned composer — PLAIN TEXT.** `ReplyBox` is a
 *     textarea. (The send path nl2br's it on the way out, so the client's copy has the
 *     line breaks the inbox was not showing.)
 *  3. **Custom emails from the legacy composer — HTML.** `CustomComposeEmailContent.vue`
 *     uses a rich editor, and `HandlesEmailCreation` prepends "greeting<br/>".
 *  4. **Block-built emails — HTML.** Our own fragment from BlockRenderer.
 *
 * Templated emails are a fifth case that never reaches `body` at all: the text lives in
 * `email_templates.body_html` and is rendered on read. That one is always HTML.
 *
 * So: decide, then format. `looksLikeHtml` is the decision, and everything else here
 * follows from it.
 *
 * Nothing in this class is about sending. It only prepares text for a screen.
 */
class EmailHtml
{
    /**
     * Tags that are stripped outright, contents and all.
     *
     * `style` is the one that matters day to day: a `<style>` block dropped into the page
     * with innerHTML is applied GLOBALLY, so one email carrying `body { font-size: 30px }`
     * restyles the whole inbox around it. Received mail cannot do this any more (it is
     * plain text by the time it is stored), but template bodies are editable in the admin
     * and go through the same renderer.
     */
    private const STRIP_ENTIRELY = [
        'script', 'style', 'link', 'meta', 'base', 'title', 'iframe',
        'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea',
    ];

    /** URL schemes a link or an image may point at. Everything else is dropped. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Where a quoted reply chain starts.
     *
     * Ordered by how reliable each marker is. The first match wins, and only a match that
     * leaves some actual message above it counts — an email that is nothing but a quote is
     * shown in full rather than folded away to nothing.
     */
    private const QUOTE_MARKERS = [
        // Our own, from ReplyThreading.
        '/^\s*On .{4,80}? wrote:\s*$/mu',
        // Outlook, in several localisations' English.
        '/^\s*-{2,}\s*Original Message\s*-{2,}\s*$/mui',
        '/^\s*_{5,}\s*$/mu',
        // Gmail's plain-text form.
        '/^\s*On .{4,120}?, .{2,80}? <[^>]+> wrote:\s*$/mu',
        // Apple Mail / generic.
        '/^\s*Begin forwarded message:\s*$/mui',
        '/^\s*From:\s.+\nSent:\s.+$/mu',
    ];

    /** A run of quoted lines is also a chain start when it is the tail of the message. */
    private const QUOTED_LINE = '/^\s*>/';

    /**
     * Does this body carry real HTML, or is it text that merely contains an angle bracket?
     *
     * `str_contains($body, '<')` — the test the trait's commented-out code reached for —
     * says yes to "profit < cost" and to any email quoting an address as `<a@b.com>`,
     * which is most of them. So this looks for an actual element: a known tag name, or any
     * tag with an attribute.
     */
    public function looksLikeHtml(?string $body): bool
    {
        if ($body === null || trim($body) === '') {
            return false;
        }

        return (bool) preg_match(
            '/<(p|div|br|span|table|tr|td|th|tbody|thead|ul|ol|li|h[1-6]|a|img|strong|em|b|i|u|blockquote|pre|code|hr|font|center)\b[^>]*>/i',
            $body
        );
    }

    /**
     * Format a stored body for display, and split off its quoted chain.
     *
     * @return array{body: string, quote: ?string} both already safe to inject
     */
    public function display(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return ['body' => '', 'quote' => null];
        }

        return $this->looksLikeHtml($body)
            ? $this->splitHtml($this->sanitise($body))
            : $this->splitText($body);
    }

    // ------------------------------------------------------------------ plain text

    /**
     * Escape, linkify, and turn blank lines into paragraphs.
     *
     * Paragraphs rather than a wall of `<br>`: a plain-text email uses a blank line to
     * separate thoughts, and `nl2br` renders that as two identical line breaks with no
     * spacing, so the structure the sender typed is lost even though the text is intact.
     */
    public function fromPlainText(string $text): string
    {
        // CRLF from Windows senders would otherwise leave a stray \r inside every line and
        // produce a phantom blank line at each break.
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $paragraphs = preg_split('/\n{2,}/', $text) ?: [];
        $html = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph, "\n");
            if ($paragraph === '') {
                continue;
            }

            $lines = array_map(
                fn (string $line) => $this->linkify(e($line)),
                explode("\n", $paragraph)
            );

            $html[] = '<p>'.implode('<br>', $lines).'</p>';
        }

        return implode('', $html);
    }

    /**
     * Make URLs and bare addresses clickable.
     *
     * Runs on ALREADY-ESCAPED text, which is why the pattern excludes `&` boundaries
     * loosely rather than matching raw URL syntax — `&amp;` inside a query string must stay
     * part of the link, and a trailing `&quot;` must not.
     */
    private function linkify(string $escaped): string
    {
        // Trailing punctuation is almost never part of the URL: "see https://x.com/a."
        $escaped = preg_replace_callback(
            '~(?<![\w@/])(https?://[^\s<]+?)(?<![.,;:!?)\]])(?=[\s<]|$)~i',
            fn (array $m) => '<a href="'.$m[1].'" target="_blank" rel="noopener noreferrer">'.$m[1].'</a>',
            $escaped
        ) ?? $escaped;

        return preg_replace_callback(
            '~(?<![\w.+-])([\w.+-]+@[\w-]+\.[\w.-]+[\w])(?![\w.-])~',
            fn (array $m) => '<a href="mailto:'.$m[1].'">'.$m[1].'</a>',
            $escaped
        ) ?? $escaped;
    }

    /**
     * Find where the quoted chain starts in a plain-text body, and format both halves.
     *
     * @return array{body: string, quote: ?string}
     */
    private function splitText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $offset = $this->quoteOffset($text);

        if ($offset === null) {
            return ['body' => $this->fromPlainText($text), 'quote' => null];
        }

        $visible = rtrim(substr($text, 0, $offset));
        $quoted = substr($text, $offset);

        return [
            'body' => $this->fromPlainText($visible),
            // The `>` prefixes are stripped: they are a transport convention, and once the
            // chain is behind a disclosure the reader wants the text, not the plumbing.
            'quote' => $this->fromPlainText(
                implode("\n", array_map(
                    fn (string $line) => preg_replace('/^\s*>\s?/', '', $line) ?? $line,
                    explode("\n", $quoted)
                ))
            ),
        ];
    }

    /** Byte offset where the quote begins, or null when there is nothing to fold. */
    private function quoteOffset(string $text): ?int
    {
        $best = null;

        foreach (self::QUOTE_MARKERS as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $offset = $m[0][1];
                if ($best === null || $offset < $best) {
                    $best = $offset;
                }
            }
        }

        // A tail of `>` lines with no header above it — some clients quote without one.
        if ($best === null) {
            $lines = explode("\n", $text);
            $start = null;

            foreach ($lines as $index => $line) {
                if (preg_match(self::QUOTED_LINE, $line)) {
                    $start ??= $index;
                } elseif (trim($line) !== '') {
                    // Real content after the quoted run: this was an inline quote, not a
                    // trailing chain, and folding from here would hide the reply itself.
                    $start = null;
                }
            }

            if ($start !== null && $start > 0) {
                $best = strlen(implode("\n", array_slice($lines, 0, $start))) + 1;
            }
        }

        // Nothing above the marker means the whole message is a quote. Show it.
        if ($best === null || trim(substr($text, 0, $best)) === '') {
            return null;
        }

        return $best;
    }

    // ------------------------------------------------------------------ html

    /**
     * Strip a fragment down to what is safe to inject.
     *
     * Takes the contents of `<body>` when handed a whole document — a full
     * `<html><head>…` string set as innerHTML has its wrapper tags discarded by the parser
     * but KEEPS what was in the head, which is how a stylesheet ends up applying to the
     * page around the email.
     */
    public function sanitise(string $html): string
    {
        $document = new DOMDocument;

        // Without the meta the parser assumes ISO-8859-1 and mangles every non-ASCII
        // character; the flags stop it inventing a doctype and an <html> wrapper on output.
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="ozee-email-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        if (! $loaded) {
            // Unparseable: fall back to treating it as text rather than passing it through.
            return $this->fromPlainText(strip_tags($html));
        }

        $xpath = new DOMXPath($document);

        foreach (self::STRIP_ENTIRELY as $tag) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        foreach (iterator_to_array($xpath->query('//*') ?: []) as $node) {
            if ($node instanceof DOMElement) {
                $this->cleanAttributes($node);
            }
        }

        $root = $document->getElementById('ozee-email-root');

        return $root ? $this->innerHtml($root) : '';
    }

    private function cleanAttributes(DOMElement $node): void
    {
        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = $attribute->nodeValue ?? '';

            // Every inline event handler, in one rule.
            if (str_starts_with($name, 'on')) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, ['href', 'src', 'action', 'background', 'formaction'], true)
                && ! $this->safeUrl($value)) {
                $node->removeAttribute($attribute->nodeName);

                continue;
            }

            // `position: fixed` in an email body escapes the card it is in and floats over
            // the app. Nothing legitimate in mail needs it.
            if ($name === 'style' && preg_match('/position\s*:\s*(fixed|sticky)/i', $value)) {
                $node->setAttribute('style', preg_replace('/position\s*:\s*(fixed|sticky)\s*;?/i', '', $value) ?? '');
            }
        }

        // Links leave the app, so they open away from it and cannot reach back through
        // window.opener.
        if (strtolower($node->nodeName) === 'a' && $node->hasAttribute('href')) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function safeUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        // Relative, anchor, or protocol-relative — no scheme to object to.
        if (! preg_match('~^([a-z][a-z0-9+.-]*):~i', $value, $m)) {
            return true;
        }

        $scheme = strtolower($m[1]);

        // `cid:` survives sanitising and is dealt with separately: an inbound email's
        // inline images are stored as FileAttachment rows, so the reference is resolved
        // against those rather than guessed at here.
        if ($scheme === 'cid') {
            return true;
        }

        // A data: image is how the block builder's own preview embeds thumbnails.
        if ($scheme === 'data') {
            return (bool) preg_match('~^data:image/(png|jpe?g|gif|webp);base64,~i', $value);
        }

        return in_array($scheme, self::SAFE_SCHEMES, true);
    }

    /**
     * Split an HTML body at its quoted chain.
     *
     * Only the shapes mail clients actually emit: Gmail's `.gmail_quote`, Outlook's
     * `#appendonsend` / `div[style*=border-top]`, and a trailing `<blockquote>`. A
     * blockquote in the MIDDLE of a message is somebody quoting a line on purpose and is
     * left where it is.
     *
     * @return array{body: string, quote: ?string}
     */
    private function splitHtml(string $html): array
    {
        if (trim($html) === '') {
            return ['body' => '', 'quote' => null];
        }

        $document = new DOMDocument;
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="ozee-email-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $root = $loaded ? $document->getElementById('ozee-email-root') : null;

        if (! $root) {
            return ['body' => $html, 'quote' => null];
        }

        $xpath = new DOMXPath($document);
        $marker = $this->quoteNode($xpath, $root);

        if (! $marker) {
            return ['body' => $html, 'quote' => null];
        }

        // Everything from the marker to the end of its level is the chain.
        $quoted = [];
        for ($node = $marker; $node !== null; $node = $next) {
            $next = $node->nextSibling;
            $quoted[] = $node;
        }

        $quoteHtml = '';
        foreach ($quoted as $node) {
            $quoteHtml .= $document->saveHTML($node);
            $node->parentNode?->removeChild($node);
        }

        $visible = $this->innerHtml($root);

        // Folding away everything is worse than not folding.
        if (trim(strip_tags($visible)) === '') {
            return ['body' => $html, 'quote' => null];
        }

        return ['body' => $visible, 'quote' => $quoteHtml];
    }

    /** The first node that begins a quoted chain, or null. */
    private function quoteNode(DOMXPath $xpath, DOMNode $root): ?DOMNode
    {
        $queries = [
            './/*[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
            './/*[@id="appendonsend"]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " moz-cite-prefix ")]',
            './/*[contains(concat(" ", normalize-space(@class), " "), " yahoo_quoted ")]',
        ];

        foreach ($queries as $query) {
            $found = $xpath->query($query, $root);
            if ($found && $found->length > 0) {
                // Climb to the child of root that contains it, so the whole chain goes and
                // not just the inner marker.
                return $this->topLevelAncestor($found->item(0), $root);
            }
        }

        // A blockquote that runs to the end of the message, with something above it.
        $blockquotes = $xpath->query('.//blockquote', $root);
        if ($blockquotes && $blockquotes->length > 0) {
            $candidate = $this->topLevelAncestor($blockquotes->item($blockquotes->length - 1), $root);

            if ($candidate && $this->onlyWhitespaceAfter($candidate) && $candidate->previousSibling !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function topLevelAncestor(?DOMNode $node, DOMNode $root): ?DOMNode
    {
        while ($node && $node->parentNode && $node->parentNode !== $root) {
            $node = $node->parentNode;
        }

        return $node?->parentNode === $root ? $node : null;
    }

    private function onlyWhitespaceAfter(DOMNode $node): bool
    {
        for ($next = $node->nextSibling; $next !== null; $next = $next->nextSibling) {
            if (trim($next->textContent ?? '') !== '') {
                return false;
            }
        }

        return true;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }
}
