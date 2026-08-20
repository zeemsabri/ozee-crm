/**
 * State and server calls for the block builder.
 *
 * Design source: Redesign/.../EmailBlocks.dc.html.
 *
 * A block is one of four shapes, matching App\Services\Inbox\BlockRenderer exactly:
 *
 *   { key, type: 'text',    text }
 *   { key, type: 'bullets', text }            one <li> per line
 *   { key, type: 'link',    label, url }
 *   { key, type: 'image',   file_id, alt, url }
 *
 * `key` is client-only and is stripped before posting — React needs a stable identity
 * across reorders, and array index is not one: moving a block up would make React reuse
 * the wrong DOM node and the text you were typing would appear to jump rows.
 *
 * `url` on an image block is likewise client-only. It is the signed GCS URL returned by
 * the upload, kept so the editor can show a thumbnail; the server ignores it and
 * re-derives everything from `file_id`, because a stored signed URL would be expired
 * rubbish within a day.
 *
 * ## The preview comes from the server
 *
 * There is no JavaScript reimplementation of the renderer here, deliberately. The preview
 * posts the blocks to /api/inbox/blocks/preview and displays the HTML that comes back, so
 * what the composer shows is produced by the same BlockRenderer that produces the outgoing
 * email. A second implementation would drift the first time either side changed, and the
 * failure mode — a preview that quietly disagrees with what the client received — is one
 * nobody notices until a client does.
 */

import axios from 'axios';
import { useCallback, useEffect, useRef, useState } from 'react';

const PREVIEW_DEBOUNCE_MS = 500;

let sequence = 0;
const nextKey = () => `b${++sequence}`;

export function emptyBlock(type) {
    switch (type) {
        case 'bullets':
            return { key: nextKey(), type: 'bullets', text: '' };
        case 'link':
            return { key: nextKey(), type: 'link', label: '', url: '' };
        case 'image':
            return { key: nextKey(), type: 'image', file_id: null, alt: '', url: '' };
        default:
            return { key: nextKey(), type: 'text', text: '' };
    }
}

export const TYPE_LABEL = {
    text: 'Text',
    bullets: 'Bullets',
    link: 'Link',
    image: 'Image',
};

/** Strip the client-only fields. This is exactly what the server is sent. */
export function toPayload(blocks) {
    return blocks.map((b) => {
        if (b.type === 'image') return { type: 'image', file_id: b.file_id, alt: b.alt || '' };
        if (b.type === 'link') return { type: 'link', label: b.label || '', url: b.url || '' };
        return { type: b.type, text: b.text || '' };
    });
}

/**
 * A block is worth sending if it would render to something.
 *
 * Mirrors BlockRenderer's own emptiness rules rather than guessing at them: an empty text
 * block renders to nothing, a link with no URL renders to nothing, an image with no file
 * renders to nothing. Counting those towards "you have written something" would let
 * someone submit a completely blank update.
 */
export function isMeaningful(block) {
    if (block.type === 'image') return !!block.file_id;
    if (block.type === 'link') return !!(block.url || '').trim();
    return !!(block.text || '').trim();
}

/**
 * Split a pasted draft into blocks.
 *
 * Lines starting with a bullet marker are gathered into one bullets block, because that is
 * how people actually paste from notes and Slack — a run of "- item" lines is one list,
 * not five paragraphs. Blank lines separate paragraphs. A line that is nothing but a URL
 * becomes a link block, since a bare URL in prose reads as a mistake in an email.
 */
export function splitPaste(text) {
    const out = [];
    let bullets = [];

    const flush = () => {
        if (bullets.length) {
            out.push({ ...emptyBlock('bullets'), text: bullets.join('\n') });
            bullets = [];
        }
    };

    (text || '').split(/\r?\n/).forEach((raw) => {
        const line = raw.trim();

        if (!line) {
            flush();
            return;
        }

        const bullet = line.match(/^[-*•·]\s+(.*)$/);
        if (bullet) {
            bullets.push(bullet[1]);
            return;
        }

        flush();

        if (/^https?:\/\/\S+$/i.test(line)) {
            out.push({ ...emptyBlock('link'), label: '', url: line });
            return;
        }

        out.push({ ...emptyBlock('text'), text: line });
    });

    flush();

    return out;
}

export function useBlocks({ projectId, onError } = {}) {
    const [blocks, setBlocks] = useState([]);
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewing, setPreviewing] = useState(false);
    const [uploading, setUploading] = useState(false);

    // Same monotonic-sequence guard the thread list uses: a slow preview for blocks you
    // have since edited must not overwrite a newer one.
    const seq = useRef(0);
    const timer = useRef(null);

    const fail = useCallback(
        (error, fallback) => {
            const message = error?.response?.data?.message || fallback;
            if (onError) onError(message);
        },
        [onError]
    );

    const meaningful = blocks.filter(isMeaningful);
    // Only the fields the renderer reads, so retyping in one block does not re-request a
    // preview when the rendered output could not have changed.
    const signature = JSON.stringify(toPayload(meaningful));

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);

        const payload = JSON.parse(signature);

        if (!payload.length) {
            seq.current += 1; // cancel any in-flight response
            setPreviewHtml('');
            setPreviewing(false);
            return undefined;
        }

        timer.current = setTimeout(async () => {
            const id = ++seq.current;
            setPreviewing(true);

            try {
                const { data } = await axios.post('/api/inbox/blocks/preview', { blocks: payload });
                if (id !== seq.current) return;
                setPreviewHtml(data.html || '');
            } catch (error) {
                if (id !== seq.current) return;
                fail(error, 'Could not build the preview.');
            } finally {
                if (id === seq.current) setPreviewing(false);
            }
        }, PREVIEW_DEBOUNCE_MS);

        return () => clearTimeout(timer.current);
    }, [signature, fail]);

    const patch = useCallback((key, fields) => {
        setBlocks((current) => current.map((b) => (b.key === key ? { ...b, ...fields } : b)));
    }, []);

    const add = useCallback((type) => {
        setBlocks((current) => [...current, emptyBlock(type)]);
    }, []);

    const remove = useCallback((key) => {
        setBlocks((current) => current.filter((b) => b.key !== key));
    }, []);

    const duplicate = useCallback((key) => {
        setBlocks((current) => {
            const index = current.findIndex((b) => b.key === key);
            if (index < 0) return current;
            const copy = { ...current[index], key: nextKey() };
            return [...current.slice(0, index + 1), copy, ...current.slice(index + 1)];
        });
    }, []);

    /** Move a block to a new index, clamped. Used by both the arrows and the drag handle. */
    const move = useCallback((key, to) => {
        setBlocks((current) => {
            const from = current.findIndex((b) => b.key === key);
            if (from < 0) return current;

            const target = Math.max(0, Math.min(current.length - 1, to));
            if (target === from) return current;

            const next = [...current];
            const [block] = next.splice(from, 1);
            next.splice(target, 0, block);
            return next;
        });
    }, []);

    const nudge = useCallback(
        (key, delta) =>
            setBlocks((current) => {
                const from = current.findIndex((b) => b.key === key);
                if (from < 0) return current;
                const to = Math.max(0, Math.min(current.length - 1, from + delta));
                if (to === from) return current;
                const next = [...current];
                const [block] = next.splice(from, 1);
                next.splice(to, 0, block);
                return next;
            }),
        []
    );

    /**
     * Convert between text and bullets without losing what was typed.
     *
     * Both store their content in `text`, so this is only a type change — which is the
     * point: someone writes a paragraph, realises it is a list, and should not have to
     * retype it.
     */
    const convert = useCallback((key, type) => {
        setBlocks((current) =>
            current.map((b) => (b.key === key ? { ...b, type } : b))
        );
    }, []);

    const bulkPaste = useCallback((text) => {
        const parsed = splitPaste(text);
        if (parsed.length) setBlocks((current) => [...current, ...parsed]);
        return parsed.length;
    }, []);

    /**
     * Upload a file and put it on a block.
     *
     * The upload happens before the email exists, so the server parents the file to the
     * project and re-points it once the reply is created. On a lead thread there is no
     * project, hence the guard — the server refuses these too, but failing here means the
     * person is told before they pick a file rather than after.
     */
    const upload = useCallback(
        async (key, file) => {
            if (!file) return false;

            if (!projectId) {
                if (onError) onError('Images need a project — this thread is a lead, so send the update as text.');
                return false;
            }

            const form = new FormData();
            form.append('project_id', projectId);
            form.append('images[]', file);

            setUploading(true);

            try {
                const { data } = await axios.post('/api/inbox/block-images', form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                const uploaded = data?.data?.[0];
                if (!uploaded) return false;

                patch(key, { file_id: uploaded.file_id, url: uploaded.url, filename: uploaded.filename });
                return true;
            } catch (error) {
                fail(error, 'Could not upload that image.');
                return false;
            } finally {
                setUploading(false);
            }
        },
        [projectId, patch, fail, onError]
    );

    const reset = useCallback(() => {
        seq.current += 1;
        setBlocks([]);
        setPreviewHtml('');
    }, []);

    return {
        blocks,
        setBlocks,
        add,
        patch,
        remove,
        duplicate,
        move,
        nudge,
        convert,
        bulkPaste,
        upload,
        uploading,
        reset,
        previewHtml,
        previewing,
        meaningfulCount: meaningful.length,
        payload: () => toPayload(meaningful),
    };
}
