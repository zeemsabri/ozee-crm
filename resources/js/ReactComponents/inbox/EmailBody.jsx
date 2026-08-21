/**
 * One message's content: the body, the quoted chain behind a disclosure, and attachments.
 *
 * Split out of ThreadView's MessageCard because the desktop and the phone render the same
 * card, and because "how an email body is displayed" turned out to be four different
 * problems wearing one `dangerouslySetInnerHTML`:
 *
 *  - Received mail is stored as PLAIN TEXT — `EmailReceiveController::cleanEmailBody`
 *    strips every tag before it reaches the database — so injecting it as HTML collapsed
 *    every line break and turned a client's email into one run-on paragraph.
 *  - A reply written in the redesigned composer is also plain text (a textarea).
 *  - A legacy custom email is rich-editor HTML.
 *  - A templated email is the template's HTML, which had `nl2br` run over it and so
 *    carried a stray `<br>` for every newline in its own source.
 *
 * All four are now decided server-side by `App\Services\Inbox\EmailHtml`, which is also
 * where sanitising happens. This file trusts `body_html` and `quoted_html` to be safe and
 * concerns itself only with presentation.
 */

import { useState } from 'react';

import { Button, Icon } from '../ds';
import { fileSize } from './format';

const isImage = (file) => typeof file?.mime_type === 'string' && file.mime_type.startsWith('image/');

/**
 * Attachments.
 *
 * Images get a thumbnail. An inbound email's inline pictures are not `cid:` references by
 * the time we store them — the ingest saves each part as a FileAttachment row and strips
 * the markup — so the only place a reader will ever see them is here, and a filename in a
 * grey box is not seeing them.
 */
function Attachments({ files }) {
    if (!files?.length) return null;

    const images = files.filter(isImage);
    const others = files.filter((f) => !isImage(f));

    return (
        <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 8 }}>
            {images.length ? (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {images.map((file) => (
                        <a
                            key={file.id}
                            href={file.url || undefined}
                            target="_blank"
                            rel="noopener noreferrer"
                            title={`${file.name} · ${fileSize(file.size)}`}
                            style={{
                                display: 'block',
                                width: 120,
                                borderRadius: 4,
                                overflow: 'hidden',
                                border: '1px solid var(--layout-border-color)',
                                background: 'var(--allgrey-background-color)',
                                textDecoration: 'none',
                            }}
                        >
                            <img
                                src={file.url}
                                alt={file.name}
                                loading="lazy"
                                style={{ display: 'block', width: '100%', height: 90, objectFit: 'cover' }}
                            />
                            <span
                                style={{
                                    display: 'block',
                                    padding: '4px 6px',
                                    font: '400 11px/16px Figtree, sans-serif',
                                    color: 'var(--secondary-text-color)',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {file.name}
                            </span>
                        </a>
                    ))}
                </div>
            ) : null}

            {others.length ? (
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {others.map((file) => (
                        <div
                            key={file.id}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '8px 10px',
                                border: '1px solid var(--layout-border-color)',
                                borderRadius: 4,
                                background: 'var(--allgrey-background-color)',
                                maxWidth: '100%',
                            }}
                        >
                            <Icon name="File" size={16} color="var(--secondary-text-color)" />
                            <div style={{ minWidth: 0 }}>
                                <div
                                    style={{
                                        font: '600 12px/16px Figtree, sans-serif',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                        maxWidth: 220,
                                    }}
                                >
                                    {file.name}
                                </div>
                                <div
                                    style={{
                                        font: '400 12px/16px Figtree, sans-serif',
                                        color: 'var(--secondary-text-color)',
                                    }}
                                >
                                    {fileSize(file.size)}
                                </div>
                            </div>
                            {file.url ? (
                                <a href={file.url} download aria-label={`Download ${file.name}`}>
                                    <Icon name="Download" size={16} />
                                </a>
                            ) : null}
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

export function EmailBody({ message, compact = false, onOpenClientView }) {
    const [quoteOpen, setQuoteOpen] = useState(false);

    if (message.render_failed) {
        return (
            <div
                style={{
                    padding: 12,
                    border: '1px dashed var(--ui-border-color)',
                    borderRadius: 4,
                    background: 'var(--allgrey-background-color)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 10,
                    font: '400 13px/20px Figtree, sans-serif',
                    color: 'var(--secondary-text-color)',
                }}
            >
                <Icon name="Warning" size={16} color="currentColor" />
                <span>
                    This template could not be rendered here — usually a missing client or project.
                    Open it on the classic inbox to read it.
                </span>
            </div>
        );
    }

    const hasBody = !!(message.body_html && message.body_html.trim());

    return (
        <>
            {hasBody ? (
                <div
                    className="ozds-email-body"
                    style={{ maxWidth: compact ? '100%' : '78ch' }}
                    dangerouslySetInnerHTML={{ __html: message.body_html }}
                />
            ) : (
                <div
                    style={{
                        font: '400 13px/20px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                        fontStyle: 'italic',
                    }}
                >
                    No message text — check the attachments below.
                </div>
            )}

            {/*
              The quoted chain.

              The mobile design draws exactly this control — a "···" button that reveals
              `m.quote` — and nothing implemented it, so inbound mail carried its entire
              history inline in a thread that already lists those same messages.
            */}
            {message.quoted_html ? (
                <div style={{ marginTop: hasBody ? 10 : 0 }}>
                    <button
                        type="button"
                        aria-expanded={quoteOpen}
                        onClick={() => setQuoteOpen((open) => !open)}
                        title={quoteOpen ? 'Hide the quoted messages' : 'Show the quoted messages'}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            height: 22,
                            padding: '0 8px',
                            border: '1px solid var(--layout-border-color)',
                            borderRadius: 11,
                            background: 'var(--allgrey-background-color)',
                            color: 'var(--secondary-text-color)',
                            font: '700 11px/1 Figtree, sans-serif',
                            cursor: 'pointer',
                        }}
                    >
                        {quoteOpen ? 'Hide quoted' : '···'}
                    </button>

                    {quoteOpen ? (
                        <div
                            className="ozds-email-body ozds-email-quote"
                            style={{ marginTop: 8, maxWidth: compact ? '100%' : '78ch' }}
                            dangerouslySetInnerHTML={{ __html: message.quoted_html }}
                        />
                    ) : null}
                </div>
            ) : null}

            <Attachments files={message.files} />

            {/*
              Outbound only.

              The branded wrapper — logo, signature, footer — is OURS. Wrapping a client's
              message in it, which the classic page does because it renders every email
              through the same Blade view, shows a letter the client never sent in a shell
              they have never seen.
            */}
            {onOpenClientView && message.direction === 'out' ? (
                <div style={{ marginTop: 12 }}>
                    <Button
                        kind="tertiary"
                        size="small"
                        leftIcon={<Icon name="Show" size={16} />}
                        onClick={() => onOpenClientView(message)}
                    >
                        See it as the client does
                    </Button>
                </div>
            ) : null}
        </>
    );
}
