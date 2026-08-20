<?php

namespace App\Services\Inbox;

use App\Models\Email;
use App\Support\TemplateData;

/**
 * The one place that knows an email was written in the block builder.
 *
 * A block email is stored like any other custom email — `template_id` is null and `body`
 * holds ready-to-send HTML — with one addition: the block JSON is kept in
 * `template_data['blocks']` so the composer can be reopened and, more importantly, so the
 * send path can rebuild the CID map without re-parsing the HTML.
 *
 * ## Why the stored body already contains `cid:` references
 *
 * `emails.body` is what the automation workflow's AI prompt reads (workflow 17 runs the
 * body through a `remove_html` transform first). A `cid:blk-12-a1b2c3d4` reference is a
 * couple of dozen characters and `strip_tags` removes it entirely, so an email with ten
 * screenshots in it costs the model exactly as many tokens as one with none. The
 * alternative — inlining the images as `data:` URIs, or letting the model see signed URLs
 * — would put megabytes of base64 or a pile of opaque URLs in front of Gemini on every
 * single send. That is the whole reason CID was chosen over remote images.
 *
 * So there are three views of the same email and each has exactly one owner:
 *
 *   stored `body`   `cid:` refs        what the AI reads, what Gmail receives
 *   sent message    `cid:` + parts     inlinePartsFor() supplies the parts
 *   our own UI      signed GCS URLs    toPreviewHtml() rewrites the refs
 *
 * Nothing here is reachable for a classic email: every method short-circuits to empty
 * when `template_data['blocks']` is absent, which it is for every row that existed before
 * the block builder and every row the classic composer writes.
 */
class BlockComposition
{
    /** The key inside `template_data` that marks an email as block-built. */
    public const KEY = 'blocks';

    public function __construct(
        private readonly BlockRenderer $renderer,
        private readonly EmailImageStore $images,
    ) {}

    /**
     * The blocks stored on this email, or an empty array.
     *
     * Reads through TemplateData::decode because `template_data` is double-encoded — see
     * that class for why that shape is deliberate and must not be "fixed" here.
     *
     * @return array<int,array>
     */
    public function blocksFor(Email $email): array
    {
        $data = TemplateData::decode($email->template_data);
        $blocks = $data[self::KEY] ?? null;

        return is_array($blocks) ? array_values(array_filter($blocks, 'is_array')) : [];
    }

    public function isBlockEmail(Email $email): bool
    {
        return $this->blocksFor($email) !== [];
    }

    /**
     * Re-render the stored blocks into the HTML that actually goes out.
     *
     * The send paths call this rather than trusting `emails.body`, so the `cid:`
     * references in the message can never disagree with the parts attached beside them —
     * if a file was pruned between composing and sending, both the reference and the part
     * disappear together instead of leaving a broken image in a client's mailbox.
     *
     * Null when this is not a block email, which is the signal to leave `body` alone.
     */
    public function renderForSend(Email $email): ?string
    {
        $blocks = $this->blocksFor($email);

        return $blocks === []
            ? null
            : $this->renderer->render($blocks, BlockRenderer::MODE_SEND, $email);
    }

    /**
     * The `multipart/related` parts for this email, in the shape GmailService wants.
     *
     * Keyed by Content-ID without the angle brackets, matching the `cid:` URLs in the
     * body. A file whose bytes cannot be read is dropped rather than failing the send —
     * losing one image is recoverable, a bounced client email is not.
     *
     * @return array<string,array{filename:string,mime_type:string,bytes:string}>
     */
    public function inlinePartsFor(Email $email): array
    {
        $blocks = $this->blocksFor($email);

        if ($blocks === []) {
            return [];
        }

        $parts = [];

        foreach ($this->renderer->inlineImages($blocks) as $cid => $file) {
            $bytes = $this->images->bytes($file);

            if ($bytes === null) {
                continue;
            }

            $parts[$cid] = [
                'filename' => $file->filename ?: basename((string) $file->path),
                'mime_type' => $file->mime_type ?: 'application/octet-stream',
                'bytes' => $bytes,
            ];
        }

        return $parts;
    }

    /**
     * Swap `cid:` references for signed URLs so our own UI can display the images.
     *
     * A browser cannot resolve a Content-ID — it is only meaningful inside a MIME
     * message — so the thread view and the composer preview would show broken images
     * without this. The URLs are 24-hour GCS signatures and must never be written back
     * into `emails.body`; they are generated per-read and thrown away.
     *
     * Left untouched when the email is not block-built, so every classic body passes
     * through unchanged.
     */
    public function toPreviewHtml(?string $html, Email $email): ?string
    {
        if ($html === null || $html === '' || ! str_contains($html, 'cid:')) {
            return $html;
        }

        $blocks = $this->blocksFor($email);

        if ($blocks === []) {
            return $html;
        }

        $replacements = [];

        foreach ($this->renderer->inlineImages($blocks) as $cid => $file) {
            $url = $this->images->previewUrl($file);

            if ($url) {
                $replacements['cid:'.$cid] = e($url);
            }
        }

        return $replacements === [] ? $html : strtr($html, $replacements);
    }

    /**
     * The file ids referenced by a set of blocks, for re-parenting them to the email.
     *
     * @param  array<int,array>  $blocks
     * @return array<int,int>
     */
    public function imageIds(array $blocks): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'image' && ! empty($block['file_id'])) {
                $ids[] = (int) $block['file_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Normalise what the composer posted into the shape we store.
     *
     * Deliberately strict: an unknown block type, or an image block whose file does not
     * resolve to one of our own uploads, is dropped rather than stored. `url` is not kept
     * for image blocks — the client sends it so its preview works, but a stored signed URL
     * would be expired rubbish by the time anyone read the row.
     *
     * @param  array<int,mixed>  $input
     * @param  array<int>|null  $projectIds  restrict image blocks to files owned by these
     *                                       projects; always pass the caller's composable
     *                                       projects when the input came from a request
     * @return array<int,array>
     */
    public function sanitise(array $input, ?array $projectIds = null): array
    {
        $out = [];

        foreach ($input as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? 'text';

            if ($type === 'image') {
                $file = $this->images->find($block['file_id'] ?? null, $projectIds);

                if (! $file) {
                    continue;
                }

                $out[] = [
                    'type' => 'image',
                    'file_id' => (int) $file->id,
                    'alt' => mb_substr(trim((string) ($block['alt'] ?? '')), 0, 200),
                ];

                continue;
            }

            if ($type === 'link') {
                $url = trim((string) ($block['url'] ?? ''));

                if ($url === '') {
                    continue;
                }

                $out[] = [
                    'type' => 'link',
                    'url' => mb_substr($url, 0, 2000),
                    'label' => mb_substr(trim((string) ($block['label'] ?? '')), 0, 200),
                ];

                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $out[] = [
                'type' => in_array($type, ['text', 'bullets'], true) ? $type : 'text',
                'text' => $text,
            ];
        }

        return $out;
    }
}
