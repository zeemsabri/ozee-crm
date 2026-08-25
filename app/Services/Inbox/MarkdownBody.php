<?php

namespace App\Services\Inbox;

use App\Models\Email;

/**
 * Renders the redesigned composer's markdown body to the HTML the client receives.
 *
 * ## Why emails.body holds markdown at all
 *
 * The AI approval step reads `emails.body`, and plain text was chosen deliberately to
 * keep that cheap — it is why templates exist. The Gmail-style composer therefore shows
 * rich text but STORES a small markdown subset (near plain text), and this class is the
 * other half of that bargain: at render time — send, preview, "see it as the client
 * does", the thread view — the markdown becomes real bold, italics, lists, quotes and
 * links. One stored version; what the AI reads IS what is sent, dressed.
 *
 * ## The subset (mirrors resources/js/.../MarkdownEditor.jsx — keep the two in sync)
 *
 *   **bold**   *italic*   ~~strike~~   [text](https://… | mailto:…)
 *   "- " / "* " bulleted lists   "1. " numbered lists   "> " quotes
 *   blank line = paragraph break, single newline = line break
 *
 * ## The input is not quite pure markdown
 *
 * HandlesEmailCreation prepends the greeting as `greeting.'<br/>'.$body`, so the stored
 * string opens with one literal <br/>. Every <br> variant is normalised to a newline
 * before parsing; everything else is escaped, so no other HTML can ride through a
 * markdown body.
 *
 * Which emails get this treatment is decided by the flag the composer posts
 * (`body_format: markdown` → `emails.draft_meta.body_format`), never by sniffing the
 * body: a legacy custom email is rich-editor HTML and escaping it would destroy it.
 */
class MarkdownBody
{
    public static function isMarkdown(Email $email): bool
    {
        return ($email->draft_meta['body_format'] ?? null) === 'markdown';
    }

    public static function render(?string $body): string
    {
        // The greeting join, and any stray break: <br>, <br/>, <br /> → newline.
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", (string) $body);
        $lines = preg_split('/\r\n|\r|\n/', $text);

        $out = [];
        $para = [];      // pending paragraph lines
        $quote = [];     // pending quote lines
        $list = null;    // ['tag' => 'ul'|'ol', 'items' => [...]]

        $flushPara = function () use (&$para, &$out) {
            if ($para === []) {
                return;
            }
            $out[] = '<p style="margin:0 0 12px 0">'.implode('<br>', $para).'</p>';
            $para = [];
        };
        $flushQuote = function () use (&$quote, &$out) {
            if ($quote === []) {
                return;
            }
            $out[] = '<blockquote style="margin:0 0 12px 0;padding-left:12px;border-left:2px solid #d0d4e4;color:#676879">'
                .implode('<br>', $quote)
                .'</blockquote>';
            $quote = [];
        };
        $flushList = function () use (&$list, &$out) {
            if ($list === null) {
                return;
            }
            $items = array_map(fn ($i) => '<li>'.$i.'</li>', $list['items']);
            $out[] = '<'.$list['tag'].' style="margin:0 0 12px 0;padding-left:24px">'
                .implode('', $items)
                .'</'.$list['tag'].'>';
            $list = null;
        };

        foreach ($lines as $raw) {
            $isBullet = preg_match('/^\s*[-*]\s+(.*)$/', $raw, $bullet);
            $isOrdered = ! $isBullet && preg_match('/^\s*\d+\.\s+(.*)$/', $raw, $ordered);
            $isQuoted = ! $isBullet && ! $isOrdered && preg_match('/^\s*>\s?(.*)$/', $raw, $quoted);

            if ($isBullet || $isOrdered) {
                $flushPara();
                $flushQuote();
                $tag = $isBullet ? 'ul' : 'ol';
                if ($list === null || $list['tag'] !== $tag) {
                    $flushList();
                    $list = ['tag' => $tag, 'items' => []];
                }
                $list['items'][] = self::inline(($isBullet ? $bullet : $ordered)[1]);

                continue;
            }

            $flushList();

            if ($isQuoted) {
                $flushPara();
                $quote[] = self::inline($quoted[1]);

                continue;
            }

            $flushQuote();

            if (trim($raw) === '') {
                $flushPara();
            } else {
                $para[] = self::inline($raw);
            }
        }

        $flushPara();
        $flushQuote();
        $flushList();

        return implode('', $out);
    }

    /** One line: escape everything, then apply the inline forms. Order matters. */
    private static function inline(string $line): string
    {
        $out = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');

        // [text](url) — http(s)/mailto only, matching the composer and EmailHtml's
        // scheme allow-list. The URL is already entity-escaped, so quotes cannot break
        // out of the attribute.
        $out = preg_replace(
            '/\[([^\]]+)\]\(((?:https?:\/\/|mailto:)[^\s)]+)\)/i',
            '<a href="$2">$1</a>',
            $out
        );
        $out = preg_replace('/~~([^~\n]+)~~/', '<del>$1</del>', $out);
        $out = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $out);
        // Single-star italic, but never the leftover half of a ** pair.
        $out = preg_replace('/(^|[^*])\*([^*\n]+)\*(?!\*)/', '$1<em>$2</em>', $out);

        return $out;
    }
}
