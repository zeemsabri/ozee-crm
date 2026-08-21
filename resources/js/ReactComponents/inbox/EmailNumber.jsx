/**
 * EmailNumber — the "OZE123" reference, click to copy.
 *
 * The number is Email::getEmailNumberAttribute ('OZE'.id), the same string
 * GlobalSearchController puts in front of every email result. People quote it in
 * tickets and paste it back into search, so the chip's whole job is to be readable
 * and copyable — it is not a link, because the thread it belongs to is already open
 * (thread view) or one click away (list row).
 *
 * A conversation has no number of its own. In the list the chip shows the NEWEST
 * message's, which is what `ThreadPresenter::row()` sends as `thread.number`.
 */

import { useEffect, useRef, useState } from 'react';
import { Icon } from '../ds';

/**
 * navigator.clipboard is undefined outside a secure context, and this app is served
 * over plain HTTP on at least some internal hosts — so the execCommand path is the
 * one that actually runs there, not dead code.
 */
async function copyToClipboard(text) {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch {
        // Permission denied or not focused — fall through to the legacy path.
    }

    try {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.top = '-1000px';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(el);
        return ok;
    } catch {
        return false;
    }
}

export function EmailNumber({ number, muted = false, style }) {
    const [copied, setCopied] = useState(false);
    const timer = useRef(null);

    useEffect(() => () => clearTimeout(timer.current), []);

    if (!number) return null;

    const onCopy = async (e) => {
        // Every one of these chips sits inside something clickable — a thread row that
        // opens the thread, a message header that expands it. Copying must not do that too.
        e.preventDefault();
        e.stopPropagation();

        const ok = await copyToClipboard(number);
        if (!ok) return;

        setCopied(true);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => setCopied(false), 1400);
    };

    return (
        <button
            type="button"
            onClick={onCopy}
            title={copied ? 'Copied' : `Copy ${number}`}
            aria-label={copied ? `${number} copied` : `Copy email number ${number}`}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                padding: '1px 6px',
                borderRadius: 4,
                cursor: 'pointer',
                background: copied ? 'var(--positive-color-selected, transparent)' : 'transparent',
                border: '1px solid var(--ui-border-color)',
                font: '600 11px/16px Figtree, sans-serif',
                fontVariantNumeric: 'tabular-nums',
                letterSpacing: '0.02em',
                color: copied
                    ? 'var(--positive-color)'
                    : muted
                      ? 'var(--secondary-text-color)'
                      : 'var(--primary-text-color)',
                ...style,
            }}
        >
            <span>{number}</span>
            <Icon name={copied ? 'Check' : 'Duplicate'} size={12} color="currentColor" />
        </button>
    );
}

export default EmailNumber;
