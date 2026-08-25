/**
 * The "letter" — the Gmail-style writing surface for the composer's Custom tab.
 *
 * Design source: gmail-style-composer canvas (Main + States boards).
 *
 * The one idea everything here serves: WHAT YOU SEE is rich text, WHAT IS STORED is
 * markdown. The AI approval step reads `emails.body`, and plain text was chosen
 * deliberately to keep that cheap (it is why templates exist at all) — so the toolbar
 * edits a contentEditable surface, and every change is serialised back to a small
 * markdown subset (**bold**, *italic*, ~~strike~~, [text](url), "- "/"1. " lists,
 * "> " quotes) that is near plain text. `POST /api/emails` receives that markdown
 * string as `body`, unchanged in shape from the old textarea. Rendering markdown to
 * HTML for the CLIENT's copy is the backend follow-up; until it lands the client
 * receives the markdown as readable text, which is exactly what the old composer sent
 * minus the asterisks.
 *
 * No editor library: the npm registry is blocked from the build VMs and a dependency
 * would sit unverified until the next Mac build anyway. contentEditable +
 * document.execCommand covers this subset in every browser we support, and native
 * undo/redo comes with it.
 *
 * Anchored panels (link editor, emoji, snippets) all go through ds/Popover —
 * position:absolute panels get clipped by the modal's scrolling body. See
 * design_system notes.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button, Icon, Popover, TextField, useAnchoredPopover } from '../ds';

/* ------------------------------------------------------------------ *
 *  Markdown <-> editor HTML
 * ------------------------------------------------------------------ */

const escapeHtml = (s) =>
    String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

/** One line of markdown → inline HTML. Order matters: links, strike, bold, italic. */
function inlineHtml(line) {
    let out = escapeHtml(line);

    // [text](url) — http(s)/mailto only, matching EmailHtml's allow-list.
    out = out.replace(
        /\[([^\]]+)\]\(((?:https?:\/\/|mailto:)[^\s)]+)\)/g,
        '<a href="$2">$1</a>'
    );
    out = out.replace(/~~([^~\n]+)~~/g, '<s>$1</s>');
    out = out.replace(/\*\*([^*\n]+)\*\*/g, '<b>$1</b>');
    // Single-star italic, but never the leftover half of a ** pair.
    out = out.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<i>$2</i>');

    return out;
}

/**
 * Markdown → the HTML the editable surface shows. One <div> per line (Chrome's own
 * Enter behaviour, so typed and loaded content have the same shape), <div><br></div>
 * for blank lines, real <ul>/<ol>/<blockquote> for the block forms.
 */
export function markdownToEditorHtml(md) {
    const lines = String(md || '').split('\n');
    const out = [];
    let list = null; // { tag, items }

    const flushList = () => {
        if (!list) return;
        out.push(
            `<${list.tag}>` + list.items.map((i) => `<li>${i}</li>`).join('') + `</${list.tag}>`
        );
        list = null;
    };

    lines.forEach((raw) => {
        const bullet = raw.match(/^\s*[-*+]\s+(.*)$/);
        const ordered = raw.match(/^\s*\d+\.\s+(.*)$/);
        const quoted = raw.match(/^\s*>\s?(.*)$/);

        if (bullet || ordered) {
            const tag = bullet ? 'ul' : 'ol';
            const item = inlineHtml((bullet || ordered)[1]);
            if (!list || list.tag !== tag) {
                flushList();
                list = { tag, items: [] };
            }
            list.items.push(item);
            return;
        }

        flushList();

        if (quoted) {
            out.push(`<blockquote>${inlineHtml(quoted[1]) || '<br>'}</blockquote>`);
        } else if (raw.trim() === '') {
            out.push('<div><br></div>');
        } else {
            out.push(`<div>${inlineHtml(raw)}</div>`);
        }
    });

    flushList();

    return out.join('');
}

/** Inline DOM → markdown for one line. `\n` may appear when a <br> sits mid-element. */
function inlineMarkdown(node) {
    let out = '';

    node.childNodes.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
            out += child.nodeValue.replace(/\u00a0/g, ' ');
            return;
        }
        if (child.nodeType !== Node.ELEMENT_NODE) return;

        const tag = child.tagName;
        if (tag === 'BR') {
            out += '\n';
            return;
        }

        const inner = inlineMarkdown(child);

        if (tag === 'B' || tag === 'STRONG') out += inner.trim() ? `**${inner}**` : inner;
        else if (tag === 'I' || tag === 'EM') out += inner.trim() ? `*${inner}*` : inner;
        else if (tag === 'S' || tag === 'DEL' || tag === 'STRIKE')
            out += inner.trim() ? `~~${inner}~~` : inner;
        else if (tag === 'A') {
            const href = child.getAttribute('href') || '';
            out += href ? `[${inner || href}](${href})` : inner;
        } else if (tag === 'UL' || tag === 'OL') {
            // Belt-and-braces: a list that still reaches inline context serialises as
            // marker-prefixed lines rather than dissolving into concatenated text.
            let n = 0;
            child.querySelectorAll(':scope > li').forEach((li) => {
                n += 1;
                out += `\n${tag === 'UL' ? '- ' : `${n}. `}${inlineMarkdown(li)}`;
            });
            out += '\n';
        } else if (tag === 'SPAN') {
            const isBold = child.style?.fontWeight === 'bold' || parseInt(child.style?.fontWeight, 10) >= 600;
            const isItalic = child.style?.fontStyle === 'italic';
            const isStrike = child.style?.textDecoration?.includes('line-through');
            let formatted = inner;
            if (isBold && formatted.trim()) formatted = `**${formatted}**`;
            if (isItalic && formatted.trim()) formatted = `*${formatted}*`;
            if (isStrike && formatted.trim()) formatted = `~~${formatted}~~`;
            out += formatted;
        } else out += inner;
    });

    return out;
}

const BLOCK_TAGS = new Set(['DIV', 'P', 'UL', 'OL', 'BLOCKQUOTE', 'LI']);

/**
 * The editable surface → markdown. This is what lands in `emails.body`, so it must
 * come out clean: no tags, no styling residue, one line per visual line.
 */
export function editorHtmlToMarkdown(root) {
    const lines = [];
    let buffer = null; // accumulating inline content at the current level

    const flush = () => {
        if (buffer === null) return;
        buffer.split('\n').forEach((l) => lines.push(l));
        buffer = null;
    };

    const pushBlockLines = (text, prefix = '') => {
        String(text)
            .split('\n')
            .forEach((l) => lines.push(prefix + l));
    };

    const walk = (node) => {
        node.childNodes.forEach((child) => {
            const isElement = child.nodeType === Node.ELEMENT_NODE;

            if (!isElement || !BLOCK_TAGS.has(child.tagName)) {
                if (isElement && child.tagName === 'BR') {
                    // A root-level <br> ends the current line.
                    buffer = buffer === null ? '' : buffer;
                    flush();
                    return;
                }
                const piece =
                    child.nodeType === Node.TEXT_NODE
                        ? child.nodeValue.replace(/\u00a0/g, ' ')
                        : isElement
                          ? inlineMarkdown({ childNodes: [child] })
                          : '';
                if (piece !== '') buffer = (buffer ?? '') + piece;
                return;
            }

            flush();

            const tag = child.tagName;
            if (tag === 'UL' || tag === 'OL') {
                let n = 0;
                child.querySelectorAll(':scope > li').forEach((li) => {
                    n += 1;
                    const marker = tag === 'UL' ? '- ' : `${n}. `;
                    pushBlockLines(inlineMarkdown(li) || '', marker);
                });
            } else if (tag === 'LI') {
                pushBlockLines(inlineMarkdown(child) || '', '- ');
            } else if (tag === 'BLOCKQUOTE') {
                pushBlockLines(inlineMarkdown(child), '> ');
            } else if (child.querySelector('ul, ol, blockquote, div, p')) {
                /*
                 * A DIV wrapping block content. execCommand sometimes builds the list
                 * INSIDE the caret line's own <div> instead of beside it, and flattening
                 * that through inlineMarkdown lost both the "- " markers and the line
                 * breaks — a real email went out with its bullet points concatenated
                 * into one run-on sentence. Recurse so the nested blocks serialise as
                 * blocks.
                 */
                walk(child);
                flush();
            } else {
                // DIV / P — one visual line (or several, when <br> split it).
                const text = inlineMarkdown(child);
                pushBlockLines(text);
            }
        });
    };

    walk(root);
    flush();

    return lines
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .replace(/[ \t]+$/gm, '')
        .replace(/\n+$/, '');
}

/* ------------------------------------------------------------------ *
 *  Snippets — reusable phrases, kept per-browser for now
 * ------------------------------------------------------------------ */

const SNIPPETS_KEY = 'ozee.inbox.snippets.v1';

function readSnippets() {
    try {
        const raw = JSON.parse(localStorage.getItem(SNIPPETS_KEY) || '[]');
        return Array.isArray(raw) ? raw.filter((s) => s && s.title && s.text) : [];
    } catch {
        return [];
    }
}

export function useSnippets() {
    const [snippets, setSnippets] = useState(readSnippets);

    const persist = (next) => {
        setSnippets(next);
        try {
            localStorage.setItem(SNIPPETS_KEY, JSON.stringify(next));
        } catch {
            /* storage full or blocked — the in-memory list still works this session */
        }
    };

    return {
        snippets,
        save: (title, text) =>
            persist([
                { id: `sn_${Date.now().toString(36)}`, title: title.trim(), text },
                ...snippets,
            ]),
        remove: (id) => persist(snippets.filter((s) => s.id !== id)),
    };
}

/* ------------------------------------------------------------------ *
 *  Toolbar chrome
 * ------------------------------------------------------------------ */

/**
 * A 28px toolbar button. `onMouseDown` + preventDefault, NOT onClick: a click would
 * move focus out of the editable surface and collapse the selection the command is
 * supposed to act on.
 */
function TBtn({ title, active, disabled, onAct, children }) {
    const [hover, setHover] = useState(false);

    return (
        <button
            type="button"
            title={title}
            aria-label={title}
            aria-pressed={active || undefined}
            disabled={disabled}
            onMouseDown={(e) => {
                e.preventDefault();
                if (!disabled) onAct(e);
            }}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: 28,
                height: 28,
                border: 'none',
                borderRadius: 'var(--border-radius-small)',
                background: active
                    ? 'var(--primary-selected-color)'
                    : hover && !disabled
                      ? 'var(--primary-background-hover-color)'
                      : 'transparent',
                color: disabled
                    ? 'var(--disabled-text-color)'
                    : active
                      ? 'var(--primary-color)'
                      : 'var(--icon-color)',
                cursor: disabled ? 'not-allowed' : 'pointer',
                padding: 0,
            }}
        >
            {children}
        </button>
    );
}

/** Stroke-based 16px glyphs, drawn inline — the Vibe icon set has no editor glyphs. */
function Glyph({ d, extra }) {
    return (
        <svg
            width="16"
            height="16"
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={d} />
            {extra || null}
        </svg>
    );
}

const GLYPHS = {
    bulleted: (
        <Glyph
            d="M6.5 4h7M6.5 8h7M6.5 12h7"
            extra={
                <>
                    <circle cx="3" cy="4" r="1" fill="currentColor" stroke="none" />
                    <circle cx="3" cy="8" r="1" fill="currentColor" stroke="none" />
                    <circle cx="3" cy="12" r="1" fill="currentColor" stroke="none" />
                </>
            }
        />
    ),
    numbered: <Glyph d="M7 4h6.5M7 8h6.5M7 12h6.5M2.2 3.2l1-.6v2.8M2.2 7.4h1.8l-1.8 2h1.8M2.4 11h1.4a.7.7 0 010 1.4H3a.7.7 0 00.8 1.4h.2" />,
    quote: <Glyph d="M3 3v10M6.5 5h7M6.5 8h7M6.5 11h5" />,
    link: <Glyph d="M6.5 9.5l3-3M7.5 4.8l1.2-1.2a2.4 2.4 0 013.4 3.4l-1.2 1.2M8.5 11.2l-1.2 1.2a2.4 2.4 0 01-3.4-3.4l1.2-1.2" />,
    clear: (
        <Glyph
            d="M5 3.5h8M9 3.5L6.5 12.5"
            extra={<path d="M2.5 13.5l11-11" stroke="var(--negative-color)" />}
        />
    ),
    emoji: (
        <Glyph
            d="M5.5 9.5a3.2 3.2 0 005 0"
            extra={
                <>
                    <circle cx="8" cy="8" r="6" />
                    <circle cx="6" cy="6.3" r="0.6" fill="currentColor" stroke="none" />
                    <circle cx="10" cy="6.3" r="0.6" fill="currentColor" stroke="none" />
                </>
            }
        />
    ),
    snippet: <Glyph d="M5.5 2.5h-2a1 1 0 00-1 1v9a1 1 0 001 1h2M10.5 2.5h2a1 1 0 011 1v9a1 1 0 01-1 1h-2M8 5v6M6 8h4" />,
    source: <Glyph d="M5.5 5L2.5 8l3 3M10.5 5l3 3-3 3" />,
};

const EMOJI = [
    '👍', '🙏', '😊', '🎉', '✅', '📞', '📅', '💡',
    '🚀', '👋', '😄', '🤝', '⏰', '📷', '❤️', '🌟',
    '☕', '🔧', '📌', '✍️', '💪', '🙌', '😅', '🤔',
];

/* ------------------------------------------------------------------ *
 *  The editor
 * ------------------------------------------------------------------ */

const COMMAND_STATES = ['bold', 'italic', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList'];

/**
 * LetterEditor — the whole card: greeting slot, editable body, sign-off slot, toolbar.
 *
 * `value` is markdown. The surface is uncontrolled while focused; the value prop wins
 * whenever it differs from what the editor last reported (reset on open, a resumed
 * draft, an edit made in the source view).
 */
export function LetterEditor({
    value,
    onChange,
    disabled = false,
    placeholder = 'Start with what you need to say — the greeting above and the sign-off below are added for you.',
    greeting = null,
    signOff = null,
    snippetsApi = null,
    onPickFiles = null,
    onPickImage = null,
    /*
     * The "</>" peek at the stored markdown is super-admin-only (user's call): everyone
     * else read it as a mode switch — "am I supposed to save the lightweight version?" —
     * when there is nothing to choose. The storage is identical either way; this flag
     * only decides whether the window into it is drawn.
     */
    canViewSource = false,
}) {
    const editorRef = useRef(null);
    const lastMd = useRef(null);
    const savedRange = useRef(null);
    const [showSource, setShowSource] = useState(false);
    // Never trust showSource alone — the peek can be withheld (canViewSource).
    const sourceOpen = showSource && canViewSource;
    const [empty, setEmpty] = useState(!String(value || '').trim());
    const [states, setStates] = useState({});

    /* -- value → surface ------------------------------------------------ */
    useEffect(() => {
        if (value === lastMd.current) return;
        lastMd.current = value;
        if (editorRef.current) {
            editorRef.current.innerHTML = markdownToEditorHtml(value);
        }
        setEmpty(!String(value || '').trim());
    }, [value]);

    useEffect(() => {
        // Divs, not <p>, so typed lines match what markdownToEditorHtml builds.
        try {
            document.execCommand('defaultParagraphSeparator', false, 'div');
        } catch {
            /* older engines — harmless */
        }
    }, []);

    /* -- surface → value ------------------------------------------------ */
    const sync = useCallback(() => {
        if (!editorRef.current) return;
        const md = editorHtmlToMarkdown(editorRef.current);
        lastMd.current = md;
        setEmpty(!md.trim());
        onChange(md);
    }, [onChange]);

    const refreshStates = useCallback(() => {
        const next = {};
        COMMAND_STATES.forEach((c) => {
            try {
                next[c] = document.queryCommandState(c);
            } catch {
                next[c] = false;
            }
        });
        setStates(next);
    }, []);

    useEffect(() => {
        const onSelection = () => {
            const sel = window.getSelection();
            if (
                sel &&
                sel.anchorNode &&
                editorRef.current &&
                editorRef.current.contains(sel.anchorNode)
            ) {
                savedRange.current = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
                refreshStates();
            }
        };
        document.addEventListener('selectionchange', onSelection);
        return () => document.removeEventListener('selectionchange', onSelection);
    }, [refreshStates]);

    const restoreSelection = () => {
        const range = savedRange.current;
        if (!range || !editorRef.current) return;
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    };

    const exec = (command, arg = null) => {
        if (disabled || sourceOpen) return;
        editorRef.current?.focus();
        restoreSelection();
        document.execCommand(command, false, arg);
        sync();
        refreshStates();
    };

    /* -- typing niceties ------------------------------------------------ */
    const onKeyDown = (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            // Gmail's link shortcut. Bold/italic are the browser's own.
            e.preventDefault();
            openLink();
            return;
        }

        // "- " / "1. " at the start of a line becomes a real list, like Gmail.
        if (e.key !== ' ' || sourceOpen) return;
        const sel = window.getSelection();
        if (!sel?.isCollapsed || !sel.anchorNode) return;
        const node = sel.anchorNode;
        if (node.nodeType !== Node.TEXT_NODE) return;
        if (node.parentElement?.closest('li, blockquote')) return;
        const before = node.nodeValue.slice(0, sel.anchorOffset);
        const isBullet = before === '-' || before === '*';
        const isNumbered = /^\d+\.$/.test(before);
        if (!isBullet && !isNumbered) return;
        // Only when the marker IS the whole line so far.
        if (node.previousSibling) return;

        e.preventDefault();
        node.nodeValue = node.nodeValue.slice(sel.anchorOffset);
        document.execCommand(isBullet ? 'insertUnorderedList' : 'insertOrderedList');
        sync();
    };

    const onPaste = (e) => {
        // Plain text only — pasted Word/Gmail HTML would sneak markup past the
        // markdown subset and serialise to mush.
        e.preventDefault();
        const text = e.clipboardData?.getData('text/plain') || '';
        document.execCommand('insertText', false, text);
        sync();
    };

    /* -- link popover ---------------------------------------------------- */
    const linkPop = useAnchoredPopover({ preferredHeight: 220, align: 'start', minWidth: 300 });
    const [linkText, setLinkText] = useState('');
    const [linkUrl, setLinkUrl] = useState('');
    const [selectionWasLink, setSelectionWasLink] = useState(false);

    const openLink = () => {
        const sel = window.getSelection();
        const text = sel && !sel.isCollapsed ? sel.toString() : '';
        const anchorEl =
            sel?.anchorNode == null
                ? null
                : sel.anchorNode.nodeType === 1 // element (instanceof Element, minus the global)
                  ? sel.anchorNode
                  : sel.anchorNode.parentElement;
        const anchor = anchorEl?.closest?.('a') || null;
        setLinkText(text || anchor?.textContent || '');
        setLinkUrl(anchor?.getAttribute('href') || '');
        setSelectionWasLink(!!anchor);
        linkPop.setOpen(true);
    };

    const applyLink = () => {
        let url = linkUrl.trim();
        if (!url) return;
        if (!/^(https?:\/\/|mailto:)/i.test(url)) url = `https://${url}`;
        const text = linkText.trim() || url;
        const html = `<a href="${escapeHtml(url)}">${escapeHtml(text)}</a>`;
        linkPop.setOpen(false);
        editorRef.current?.focus();
        restoreSelection();
        document.execCommand('insertHTML', false, html);
        sync();
    };

    const removeLink = () => {
        linkPop.setOpen(false);
        exec('unlink');
    };

    /* -- emoji popover ---------------------------------------------------- */
    const emojiPop = useAnchoredPopover({ preferredHeight: 200, align: 'start', minWidth: 232 });

    const insertText = (text) => {
        editorRef.current?.focus();
        restoreSelection();
        document.execCommand('insertText', false, text);
        sync();
    };

    /* -- snippets popover -------------------------------------------------- */
    const snipPop = useAnchoredPopover({ preferredHeight: 300, align: 'start', minWidth: 300 });
    const [savingSnippet, setSavingSnippet] = useState(false);
    const [snippetTitle, setSnippetTitle] = useState('');
    const [snippetText, setSnippetText] = useState('');

    const openSnippets = () => {
        const sel = window.getSelection();
        const text =
            sel && !sel.isCollapsed && editorRef.current?.contains(sel.anchorNode)
                ? sel.toString()
                : '';
        setSnippetText(text);
        setSavingSnippet(false);
        setSnippetTitle('');
        snipPop.setOpen(true);
    };

    /* -- render ----------------------------------------------------------- */
    const toolbarDisabled = disabled || sourceOpen;

    const popPanelStyle = {
        background: 'var(--dialog-background-color)',
        border: '1px solid var(--layout-border-color)',
        borderRadius: 'var(--border-radius-medium)',
        boxShadow: 'var(--box-shadow-medium)',
        padding: 12,
    };

    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                border: '1px solid var(--ui-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                overflow: 'hidden',
                background: 'var(--primary-background-color)',
            }}
        >
            {greeting ? <div style={{ padding: '12px 16px 0' }}>{greeting}</div> : null}

            {/* The writing surface (kept mounted under the source view so its DOM survives) */}
            <div style={{ position: 'relative', display: sourceOpen ? 'none' : 'block' }}>
                {empty && !disabled ? (
                    <span
                        aria-hidden="true"
                        style={{
                            position: 'absolute',
                            top: 10,
                            left: 16,
                            right: 16,
                            font: 'var(--font-text2-normal)',
                            lineHeight: '22px',
                            color: 'var(--placeholder-color)',
                            pointerEvents: 'none',
                        }}
                    >
                        {placeholder}
                    </span>
                ) : null}
                <div
                    ref={editorRef}
                    className="ozds-letter-body"
                    contentEditable={!disabled}
                    suppressContentEditableWarning
                    role="textbox"
                    aria-multiline="true"
                    aria-label="Message"
                    spellCheck
                    onInput={sync}
                    onKeyDown={onKeyDown}
                    onPaste={onPaste}
                    onBlur={sync}
                />
            </div>

            {sourceOpen ? (
                <div>
                    <div
                        style={{
                            padding: '8px 16px 0',
                            font: 'var(--font-text3-medium)',
                            color: 'var(--secondary-text-color)',
                            background: 'var(--allgrey-background-color)',
                        }}
                    >
                        What is stored — and what the AI reviewer reads. Edits here are kept.
                    </div>
                    <textarea
                        className="ozds-letter-source"
                        value={value}
                        disabled={disabled}
                        onChange={(e) => onChange(e.target.value)}
                        aria-label="Message source (markdown)"
                    />
                </div>
            ) : null}

            {signOff ? <div style={{ padding: '0 16px 12px' }}>{signOff}</div> : null}

            {/* ---------------- toolbar ---------------- */}
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 2,
                    flexWrap: 'wrap',
                    padding: '6px 10px',
                    borderTop: '1px solid var(--om-hairline)',
                    background: 'var(--allgrey-background-color)',
                }}
            >
                <TBtn title="Bold (Ctrl+B)" active={states.bold} disabled={toolbarDisabled} onAct={() => exec('bold')}>
                    <span style={{ font: '700 14px/1 Figtree, sans-serif' }}>B</span>
                </TBtn>
                <TBtn title="Italic (Ctrl+I)" active={states.italic} disabled={toolbarDisabled} onAct={() => exec('italic')}>
                    <span style={{ font: 'italic 600 14px/1 Georgia, serif' }}>I</span>
                </TBtn>
                <TBtn
                    title="Strikethrough"
                    active={states.strikeThrough}
                    disabled={toolbarDisabled}
                    onAct={() => exec('strikeThrough')}
                >
                    <span style={{ font: '400 13px/1 Figtree, sans-serif', textDecoration: 'line-through' }}>S</span>
                </TBtn>

                <span style={{ width: 1, height: 20, background: 'var(--layout-border-color)', margin: '0 4px' }} />

                <TBtn
                    title="Bulleted list"
                    active={states.insertUnorderedList}
                    disabled={toolbarDisabled}
                    onAct={() => exec('insertUnorderedList')}
                >
                    {GLYPHS.bulleted}
                </TBtn>
                <TBtn
                    title="Numbered list"
                    active={states.insertOrderedList}
                    disabled={toolbarDisabled}
                    onAct={() => exec('insertOrderedList')}
                >
                    {GLYPHS.numbered}
                </TBtn>
                <TBtn title="Quote" disabled={toolbarDisabled} onAct={() => exec('formatBlock', '<blockquote>')}>
                    {GLYPHS.quote}
                </TBtn>

                <span ref={linkPop.anchorRef} style={{ display: 'inline-flex' }}>
                    <TBtn title="Link (Ctrl+K)" disabled={toolbarDisabled} onAct={openLink}>
                        {GLYPHS.link}
                    </TBtn>
                </span>

                <TBtn
                    title="Clear formatting"
                    disabled={toolbarDisabled}
                    onAct={() => {
                        // One selection restore, three commands — calling exec() thrice
                        // would restore a stale range between commands.
                        if (disabled || sourceOpen) return;
                        editorRef.current?.focus();
                        restoreSelection();
                        document.execCommand('removeFormat');
                        document.execCommand('unlink');
                        document.execCommand('formatBlock', false, '<div>');
                        sync();
                        refreshStates();
                    }}
                >
                    {GLYPHS.clear}
                </TBtn>

                <span style={{ width: 1, height: 20, background: 'var(--layout-border-color)', margin: '0 4px' }} />

                <span ref={emojiPop.anchorRef} style={{ display: 'inline-flex' }}>
                    <TBtn title="Emoji" disabled={toolbarDisabled} onAct={() => emojiPop.setOpen(!emojiPop.open)}>
                        {GLYPHS.emoji}
                    </TBtn>
                </span>

                {onPickImage ? (
                    <TBtn title="Attach an image" disabled={disabled} onAct={onPickImage}>
                        <Icon name="Image" size={16} color="currentColor" />
                    </TBtn>
                ) : null}

                {snippetsApi ? (
                    <span ref={snipPop.anchorRef} style={{ display: 'inline-flex' }}>
                        <TBtn title="Snippets — reusable phrases" disabled={toolbarDisabled} onAct={openSnippets}>
                            {GLYPHS.snippet}
                        </TBtn>
                    </span>
                ) : null}

                {onPickFiles ? (
                    <TBtn title="Attach files" disabled={disabled} onAct={onPickFiles}>
                        <Icon name="Attach" size={16} color="currentColor" />
                    </TBtn>
                ) : null}

                <span
                    style={{
                        marginInlineStart: 'auto',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 8,
                    }}
                >
                    <span
                        style={{
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        Stored as lightweight text — the AI check stays fast
                    </span>
                    {canViewSource ? (
                        <TBtn
                            title={showSource ? 'Back to the letter' : 'Show what is stored'}
                            active={showSource}
                            disabled={disabled}
                            onAct={() => setShowSource((s) => !s)}
                        >
                            {GLYPHS.source}
                        </TBtn>
                    ) : null}
                </span>
            </div>

            {/* ---------------- link popover ---------------- */}
            {linkPop.open ? (
                <Popover position={linkPop.position} panelRef={linkPop.panelRef} style={popPanelStyle}>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10, width: 280 }}>
                        <TextField
                            label="Text"
                            size="small"
                            placeholder="What the reader sees"
                            value={linkText}
                            onChange={(e) => setLinkText(e.target.value)}
                        />
                        <TextField
                            label="Link"
                            size="small"
                            placeholder="https://…"
                            value={linkUrl}
                            onChange={(e) => setLinkUrl(e.target.value)}
                        />
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <Button size="small" disabled={!linkUrl.trim()} onClick={applyLink}>
                                Apply
                            </Button>
                            {selectionWasLink ? (
                                <Button size="small" kind="tertiary" color="negative" onClick={removeLink}>
                                    Remove link
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </Popover>
            ) : null}

            {/* ---------------- emoji popover ---------------- */}
            {emojiPop.open ? (
                <Popover position={emojiPop.position} panelRef={emojiPop.panelRef} style={popPanelStyle}>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(8, 26px)',
                            gap: 2,
                        }}
                    >
                        {EMOJI.map((e) => (
                            <button
                                key={e}
                                type="button"
                                onMouseDown={(ev) => {
                                    ev.preventDefault();
                                    emojiPop.setOpen(false);
                                    insertText(e);
                                }}
                                style={{
                                    width: 26,
                                    height: 26,
                                    border: 'none',
                                    background: 'transparent',
                                    borderRadius: 4,
                                    cursor: 'pointer',
                                    fontSize: 17,
                                    lineHeight: '26px',
                                    padding: 0,
                                }}
                            >
                                {e}
                            </button>
                        ))}
                    </div>
                </Popover>
            ) : null}

            {/* ---------------- snippets popover ---------------- */}
            {snipPop.open && snippetsApi ? (
                <Popover position={snipPop.position} panelRef={snipPop.panelRef} style={{ ...popPanelStyle, padding: 0 }}>
                    <div style={{ width: 300, display: 'flex', flexDirection: 'column' }}>
                        {savingSnippet ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, padding: 12 }}>
                                <TextField
                                    label="Snippet name"
                                    size="small"
                                    placeholder="e.g. Chasing content"
                                    value={snippetTitle}
                                    onChange={(e) => setSnippetTitle(e.target.value)}
                                />
                                <div
                                    style={{
                                        font: 'var(--font-text3-normal)',
                                        color: 'var(--secondary-text-color)',
                                        maxHeight: 60,
                                        overflow: 'hidden',
                                    }}
                                >
                                    {snippetText}
                                </div>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    <Button
                                        size="small"
                                        disabled={!snippetTitle.trim()}
                                        onClick={() => {
                                            snippetsApi.save(snippetTitle, snippetText);
                                            setSavingSnippet(false);
                                        }}
                                    >
                                        Save snippet
                                    </Button>
                                    <Button size="small" kind="tertiary" onClick={() => setSavingSnippet(false)}>
                                        Back
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div style={{ maxHeight: 220, overflowY: 'auto', padding: '6px 0' }}>
                                    {snippetsApi.snippets.length === 0 ? (
                                        <div
                                            style={{
                                                padding: '14px 12px',
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            No snippets yet. Select some text in the letter, open this
                                            menu, and save it as a reusable phrase.
                                        </div>
                                    ) : (
                                        snippetsApi.snippets.map((s) => (
                                            <div
                                                key={s.id}
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 8,
                                                    padding: '7px 12px',
                                                }}
                                            >
                                                <button
                                                    type="button"
                                                    onMouseDown={(ev) => {
                                                        ev.preventDefault();
                                                        snipPop.setOpen(false);
                                                        insertText(s.text);
                                                    }}
                                                    style={{
                                                        flex: 1,
                                                        minWidth: 0,
                                                        textAlign: 'start',
                                                        border: 'none',
                                                        background: 'transparent',
                                                        cursor: 'pointer',
                                                        padding: 0,
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            display: 'block',
                                                            font: 'var(--font-text2-medium)',
                                                            color: 'var(--primary-text-color)',
                                                        }}
                                                    >
                                                        {s.title}
                                                    </span>
                                                    <span
                                                        style={{
                                                            display: 'block',
                                                            font: 'var(--font-text3-normal)',
                                                            color: 'var(--secondary-text-color)',
                                                            whiteSpace: 'nowrap',
                                                            overflow: 'hidden',
                                                            textOverflow: 'ellipsis',
                                                        }}
                                                    >
                                                        {s.text}
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete snippet"
                                                    aria-label={`Delete snippet ${s.title}`}
                                                    onMouseDown={(ev) => {
                                                        ev.preventDefault();
                                                        snippetsApi.remove(s.id);
                                                    }}
                                                    style={{
                                                        border: 'none',
                                                        background: 'transparent',
                                                        color: 'var(--icon-color)',
                                                        cursor: 'pointer',
                                                        padding: 2,
                                                    }}
                                                >
                                                    <Icon name="CloseSmall" size={14} color="currentColor" />
                                                </button>
                                            </div>
                                        ))
                                    )}
                                </div>
                                <div
                                    style={{
                                        borderTop: '1px solid var(--om-hairline)',
                                        padding: '8px 12px',
                                    }}
                                >
                                    <button
                                        type="button"
                                        disabled={!snippetText.trim()}
                                        title={
                                            snippetText.trim()
                                                ? 'Save the selected text as a snippet'
                                                : 'Select some text in the letter first'
                                        }
                                        onMouseDown={(ev) => {
                                            ev.preventDefault();
                                            if (snippetText.trim()) setSavingSnippet(true);
                                        }}
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            border: 'none',
                                            background: 'transparent',
                                            cursor: snippetText.trim() ? 'pointer' : 'not-allowed',
                                            color: snippetText.trim()
                                                ? 'var(--primary-color)'
                                                : 'var(--disabled-text-color)',
                                            font: 'var(--font-text2-normal)',
                                            padding: 0,
                                        }}
                                    >
                                        <Icon name="Add" size={14} color="currentColor" />
                                        Save selection as a snippet
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </Popover>
            ) : null}
        </div>
    );
}
