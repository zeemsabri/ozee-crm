<?php

namespace App\Services\Inbox;

use App\Models\Email;
use App\Models\FileAttachment;
use Illuminate\Support\Str;

/**
 * Turns the block builder's JSON into email HTML.
 *
 * Design source: Redesign/.../EmailBlocks.dc.html — the "Project update — build it in
 * blocks" composer. A block is one of four shapes:
 *
 *   {type: 'text',    text}                 → a paragraph
 *   {type: 'bullets', text}                 → one <li> per line
 *   {type: 'link',    label, url}           → a standalone link line
 *   {type: 'image',   file_id, alt, url}    → an inline image
 *
 * Inside `text` and bullet lines, two inline forms are supported, matching the mock's
 * `parseInline`: `(Label)[https://…]` becomes a link and `*phrase*` becomes bold.
 *
 * ## Two render modes, and why
 *
 * `MODE_SEND` emits `<img src="cid:blk-…">`. The image bytes travel inside the message as
 * a `multipart/related` part (GmailService::sendMessage), so the client's copy is
 * permanent, works offline, and is never blocked by a mail client's remote-image privacy
 * setting. That is what lets our own stored copy expire — see `files.expires_at`.
 *
 * `MODE_PREVIEW` emits a signed GCS URL instead, because our own UI cannot resolve a
 * `cid:` reference. Both modes produce the same markup otherwise, so what the composer
 * previews is what the client receives.
 *
 * ## Email HTML, not web HTML
 * Everything is inline-styled with table-safe tags. Mail clients strip <style> blocks,
 * ignore most modern CSS, and Outlook renders through Word. Widths are fixed at 600px,
 * the standard email body width the mock specifies.
 */
class BlockRenderer
{
    public const MODE_SEND = 'send';
    public const MODE_PREVIEW = 'preview';

    /** The email body width every mail client agrees on. */
    private const WIDTH = 600;

    public function __construct(private readonly EmailImageStore $images) {}

    /**
     * @param  array<int,array>  $blocks
     * @return string HTML fragment (not a full document — the blade layout wraps it)
     */
    public function render(array $blocks, string $mode = self::MODE_PREVIEW, ?Email $email = null): string
    {
        $html = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';

            $html[] = match ($type) {
                'bullets' => $this->bullets($block),
                'link' => $this->link($block),
                'image' => $this->image($block, $mode, $email),
                default => $this->paragraph($block),
            };
        }

        return implode("\n", array_filter($html));
    }

    /**
     * The CID map for a set of blocks: `cid => FileAttachment`.
     *
     * The send path needs this to build the `multipart/related` parts. Returned separately
     * from render() so the caller fetches each file's bytes exactly once, and so a missing
     * or expired file simply produces no part rather than a broken send.
     *
     * @param  array<int,array>  $blocks
     * @return array<string,FileAttachment>
     */
    public function inlineImages(array $blocks): array
    {
        $map = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) !== 'image') {
                continue;
            }

            $file = $this->images->find($block['file_id'] ?? null);

            if ($file) {
                $map[$this->cidFor($file)] = $file;
            }
        }

        return $map;
    }

    /**
     * A stable Content-ID for a file.
     *
     * Derived from the id rather than random, so re-rendering the same email (a preview,
     * then the real send, then a resend) always produces the same reference and the HTML
     * never disagrees with the parts attached alongside it.
     */
    public function cidFor(FileAttachment $file): string
    {
        return 'blk-'.$file->id.'-'.substr(md5((string) $file->path), 0, 8);
    }

    // ------------------------------------------------------------------ blocks

    private function paragraph(array $block): string
    {
        $text = trim((string) ($block['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        // A blank line inside one text block still means a new paragraph — people paste
        // prose, and collapsing it into a wall would misrepresent what they wrote.
        $paras = preg_split('/\n\s*\n/', $text) ?: [$text];

        return implode("\n", array_map(
            fn ($p) => '<p style="margin:0 0 14px;font:400 15px/24px Arial,Helvetica,sans-serif;color:#323338">'
                .$this->inline($p)
                .'</p>',
            array_filter(array_map('trim', $paras))
        ));
    }

    private function bullets(array $block): string
    {
        $lines = array_filter(array_map('trim', explode("\n", (string) ($block['text'] ?? ''))));

        if (! $lines) {
            return '';
        }

        $items = implode('', array_map(
            fn ($line) => '<li style="font:400 15px/24px Arial,Helvetica,sans-serif;color:#323338;margin:0 0 6px">'
                .$this->inline($line)
                .'</li>',
            $lines
        ));

        return '<ul style="margin:0 0 14px;padding-left:22px">'.$items.'</ul>';
    }

    private function link(array $block): string
    {
        $url = $this->safeUrl($block['url'] ?? '');
        $label = trim((string) ($block['label'] ?? '')) ?: $url;

        if ($url === '') {
            return '';
        }

        return '<p style="margin:0 0 14px">'
            .'<a href="'.e($url).'" style="font:600 15px/24px Arial,Helvetica,sans-serif;color:#1a73e8">'
            .e($label)
            .'</a></p>';
    }

    private function image(array $block, string $mode, ?Email $email): string
    {
        $file = $this->images->find($block['file_id'] ?? null);
        $alt = trim((string) ($block['alt'] ?? ''));

        if (! $file) {
            // A block whose file has been pruned, or was never uploaded. Rendering nothing
            // is better than a broken-image icon in a client's inbox.
            return $alt !== ''
                ? '<p style="margin:0 0 14px;font:italic 400 13px/20px Arial,Helvetica,sans-serif;color:#676879">['
                    .e($alt).']</p>'
                : '';
        }

        $src = $mode === self::MODE_SEND
            ? 'cid:'.$this->cidFor($file)
            : (string) $this->images->previewUrl($file);

        if ($src === '') {
            return '';
        }

        // width + max-width, and a display:block to kill the descender gap under images
        // in Outlook. Height auto so the aspect ratio survives the 600px clamp.
        return '<p style="margin:0 0 14px">'
            .'<img src="'.e($src).'" alt="'.e($alt).'" width="'.self::WIDTH.'"'
            .' style="display:block;width:100%;max-width:'.self::WIDTH.'px;height:auto;'
            .'border:1px solid #e6e9ef;border-radius:4px" />'
            .'</p>';
    }

    // ------------------------------------------------------------------ inline

    /**
     * `(Label)[url]` → link, `*phrase*` → bold. Everything else is escaped.
     *
     * Escaping happens per-segment rather than up front, so a URL or label containing an
     * ampersand is escaped once, not twice.
     */
    private function inline(string $text): string
    {
        $out = '';
        $offset = 0;
        $pattern = '/\(([^)]+)\)\[([^\]]+)\]|\*([^*\n]+)\*/';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $start = $m[0][1];
                $out .= nl2br(e(substr($text, $offset, $start - $offset)));

                if (! empty($m[3][0])) {
                    $out .= '<strong>'.e($m[3][0]).'</strong>';
                } else {
                    $url = $this->safeUrl($m[2][0]);
                    $out .= $url === ''
                        ? e($m[1][0])
                        : '<a href="'.e($url).'" style="color:#1a73e8">'.e($m[1][0]).'</a>';
                }

                $offset = $start + strlen($m[0][0]);
            }
        }

        return $out.nl2br(e(substr($text, $offset)));
    }

    /**
     * Only http(s) and mailto survive.
     *
     * These URLs are typed by staff and land in a client's mailbox; `javascript:` and
     * `data:` have no legitimate use here and both are live XSS in some mail clients and
     * in our own thread view, which renders this HTML back.
     */
    private function safeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        // Bare domains are the common case when someone pastes "example.com".
        if (! Str::contains($url, ':') && ! Str::startsWith($url, '/')) {
            $url = 'https://'.$url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : '';
    }
}
