/**
 * "See it as the client does" — the full branded email, in a sandboxed frame.
 *
 * The thread shows a cleaned-up fragment: quoted history folded away, plain text given
 * paragraphs, sender markup sanitised. That is the right thing to read a conversation in.
 * It is the wrong thing to approve from — what leaves the building is that fragment inside
 * `emails/{template}.blade.php`, with the brand header, the signature block and the
 * footer. Approving a draft without ever seeing that is approving something you have not
 * read, which is why the classic inbox shows the whole document and this puts it one tap
 * away rather than dropping it.
 *
 * ## The sandbox, and why it is spelled this way
 *
 * `sandbox` WITHOUT `allow-scripts` means nothing in the document executes — no inline
 * handlers, no `<script>`, nothing. `allow-same-origin` is present only so the page can
 * measure the frame; on its own it grants no capability, because granting same-origin to a
 * document that cannot run code gives that document nothing. The pair to never write is
 * `allow-scripts allow-same-origin`, which does hand the frame the parent's origin.
 *
 * An iframe rather than another `dangerouslySetInnerHTML`: this document is a COMPLETE
 * page with its own `<style>` block, and a stylesheet injected into the app applies to the
 * app. The email needs its own white canvas and its own 600px column, which is exactly
 * what a frame is.
 */

import { useEffect, useState } from 'react';
import axios from 'axios';

import { Button, Loader, Modal } from '../ds';

export function ClientViewDialog({ open, message, fullScreen = false, style, classicUrl, onClose }) {
    const [html, setHtml] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const emailId = message?.id ?? null;

    useEffect(() => {
        if (!open || !emailId) return undefined;

        let live = true;
        setHtml(null);
        setError(null);
        setLoading(true);

        axios
            .get(`/api/inbox/emails/${emailId}/preview`)
            .then(({ data }) => {
                if (live) setHtml(data?.html || '');
            })
            .catch((e) => {
                if (live) {
                    setError(
                        e?.response?.data?.message ||
                            'That email could not be rendered here. Open it on the classic inbox.'
                    );
                }
            })
            .finally(() => {
                if (live) setLoading(false);
            });

        // Guards against a slow response for a message the reader has already closed
        // landing in a dialog now showing a different email.
        return () => {
            live = false;
        };
    }, [open, emailId]);

    if (!open || !message) return null;

    return (
        <Modal
            open
            onClose={onClose}
            size="large"
            dense={fullScreen}
            // The phone gets the same full-bleed override as the other dialogs; a desktop
            // caps its height so a long branded email does not push the frame off screen.
            style={style ?? { maxHeight: '90vh' }}
            title="As the client sees it"
            description={
                message.author ? `${message.author} · the full email, including signature and footer` : undefined
            }
            footer={
                <>
                    {classicUrl ? (
                        <a href={classicUrl} style={{ textDecoration: 'none' }}>
                            <Button kind="tertiary" size="small">
                                Classic inbox
                            </Button>
                        </a>
                    ) : null}
                    <Button kind="secondary" size="small" onClick={onClose}>
                        Close
                    </Button>
                </>
            }
        >
            {loading ? (
                <div style={{ display: 'flex', justifyContent: 'center', padding: '48px 0' }}>
                    <Loader ariaLabel="Rendering the email" />
                </div>
            ) : error ? (
                <div
                    style={{
                        padding: 16,
                        border: '1px dashed var(--ui-border-color)',
                        borderRadius: 4,
                        background: 'var(--allgrey-background-color)',
                        font: '400 14px/20px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {error}
                </div>
            ) : (
                <iframe
                    title="Email as the client sees it"
                    srcDoc={html || ''}
                    sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                    referrerPolicy="no-referrer"
                    /*
                     * A fixed height that scrolls internally, rather than measuring the
                     * document and growing to fit. Measuring means reaching into the frame
                     * on load and again on every image, and a branded email is a fixed
                     * 600px column whose length nobody needs to see all at once.
                     */
                    style={{
                        display: 'block',
                        width: '100%',
                        height: fullScreen ? 'calc(100dvh - 180px)' : '68vh',
                        border: '1px solid var(--layout-border-color)',
                        borderRadius: 4,
                        background: '#ffffff',
                    }}
                />
            )}
        </Modal>
    );
}
