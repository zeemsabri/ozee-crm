/**
 * The reply composer inside a thread.
 *
 * Design source: Inbox.dc.html, the sc-if="replyOpen" block.
 *
 * Two jobs, one component:
 *
 *  - NEW REPLY (default). Sending is two requests, deliberately:
 *      1. POST /api/inbox/threads/{id}/reply  — creates the email as pending_approval
 *      2. POST /api/emails/{id}/edit-and-approve — the EXISTING send path, only if this
 *         person is allowed to approve
 *    A manager sees "Send now" and both fire; anyone else sees "Submit for approval" and
 *    only the first does. If step 2 fails the email survives in approvals rather than
 *    vanishing, and the toast says so.
 *
 *  - EDITING AN EXISTING DRAFT (`editing` prop set, from "Edit & approve"). One request:
 *    step 2 against the draft that is already there. This must NOT go through step 1 —
 *    creating a new email would leave the original draft pending forever, and approving
 *    both later would send the client the same thing twice.
 *
 * See InboxReplyController for why there is no second send path in either case.
 *
 * The AI draft is a starting point that is always editable, and nothing generated is ever
 * sent without someone pressing the button.
 */

import { useEffect, useState } from 'react';
import { Button, ButtonGroup, Chips, Icon, TextArea, TextField } from '../ds';

const MODES = [
    { value: 'reply', text: 'Reply' },
    { value: 'replyAll', text: 'Reply all' },
    { value: 'forward', text: 'Forward' },
];

export function ReplyBox({
    thread,
    recipients,
    aiDraft,
    aiEnabled,
    isManager,
    busy,
    editing,
    onSend,
    onSaveDraft,
    onDiscard,
    onRegenerateDraft,
}) {
    const isEditing = !!editing;

    const [mode, setMode] = useState('reply');
    const [subject, setSubject] = useState(editing?.subject || recipients?.subject || '');
    const [body, setBody] = useState(editing?.body || '');
    const [usedAi, setUsedAi] = useState(false);
    const [ccOpen, setCcOpen] = useState(false);
    const [cc, setCc] = useState('');
    const [bcc, setBcc] = useState('');
    const [forwardTo, setForwardTo] = useState('');

    // Load the AI draft once, and only into an untouched box — refetching must never
    // overwrite something the person has started typing. Never when editing an existing
    // draft: that box is showing someone's actual words.
    useEffect(() => {
        if (!isEditing && aiDraft && !body.trim()) {
            setBody(aiDraft);
            setUsedAi(true);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aiDraft, isEditing]);

    // Seed from whichever source applies. Editing wins — the draft's own subject is the
    // one being approved, not a generated "Re: …".
    useEffect(() => {
        if (isEditing) {
            setSubject(editing.subject || '');
            setBody(editing.body || '');
            return;
        }
        if (recipients?.subject) setSubject(recipients.subject);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isEditing, editing?.emailId, recipients?.subject]);

    // An existing draft already has its recipients; the reply modes do not apply to it.
    const to = isEditing
        ? editing.to || recipients?.reply || []
        : mode === 'forward'
          ? forwardTo.split(',').map((s) => s.trim()).filter(Boolean)
          : recipients?.[mode] || recipients?.reply || [];

    // When editing, the draft's recipients are already on the record server-side, so an
    // empty display list must not block approving it.
    const canSend = (isEditing || to.length > 0) && subject.trim() && body.trim() && !busy;

    const payload = () => ({
        emailId: editing?.emailId,
        mode,
        subject: subject.trim(),
        body: body.trim(),
        // Only forwarding sends addresses. For reply and reply-all the server resolves
        // the recipients from the conversation and ignores anything posted — the chips
        // above may be masked labels rather than real addresses, and a thread reply must
        // not be redirectable from the client. See InboxReplyController::resolveRecipients.
        to: mode === 'forward' ? to : undefined,
        cc: cc.split(',').map((s) => s.trim()).filter(Boolean),
        bcc: bcc.split(',').map((s) => s.trim()).filter(Boolean),
        in_reply_to_email_id: recipients?.last_inbound_email_id || null,
    });

    return (
        <div
            style={{
                border: '1px solid var(--primary-color)',
                borderRadius: 8,
                background: 'var(--primary-background-color)',
                overflow: 'hidden',
                animation: 'dcRise 200ms cubic-bezier(0,0,.35,1) both',
            }}
        >
            <div
                style={{
                    padding: '10px 14px',
                    borderBottom: '1px solid var(--om-hairline)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                }}
            >
                {isEditing ? (
                    <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                        Editing the draft before it goes out
                    </span>
                ) : (
                    <ButtonGroup
                        options={isManager ? MODES : MODES.filter((m) => m.value !== 'forward')}
                        value={mode}
                        onChange={setMode}
                        size="small"
                    />
                )}
                {thread.reply?.needs_reply ? (
                    <span
                        style={{
                            marginInlineStart: 'auto',
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        Sending stops the reply clock
                    </span>
                ) : null}
            </div>

            <div style={{ padding: '12px 14px', display: 'flex', flexDirection: 'column', gap: 10 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        To
                    </span>
                    {isEditing && !to.length ? (
                        <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                            Already addressed on the draft
                        </span>
                    ) : mode === 'forward' && !isEditing ? (
                        <div style={{ flex: 1, minWidth: 220 }}>
                            <TextField
                                size="small"
                                placeholder="Comma-separated addresses to forward to"
                                value={forwardTo}
                                onChange={(e) => setForwardTo(e.target.value)}
                            />
                        </div>
                    ) : to.length ? (
                        to.map((address) => (
                            <Chips
                                key={address}
                                label={address}
                                color="primary"
                                size="small"
                                readOnly
                                title={
                                    recipients?.masked
                                        ? 'Partly hidden because you cannot view client contact details — the reply still reaches them.'
                                        : address
                                }
                            />
                        ))
                    ) : (
                        <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--negative-color)' }}>
                            No address on file for this thread — forward it instead.
                        </span>
                    )}
                    {!ccOpen && !isEditing ? (
                        <Button kind="tertiary" size="small" onClick={() => setCcOpen(true)}>
                            Add Cc / Bcc
                        </Button>
                    ) : null}
                </div>

                {ccOpen ? (
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 8,
                            animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both',
                        }}
                    >
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                                Cc
                            </span>
                            <div style={{ flex: 1 }}>
                                <TextField
                                    size="small"
                                    placeholder="Comma-separated addresses"
                                    value={cc}
                                    onChange={(e) => setCc(e.target.value)}
                                />
                            </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                                Bcc
                            </span>
                            <div style={{ flex: 1 }}>
                                <TextField
                                    size="small"
                                    placeholder="Hidden recipients"
                                    value={bcc}
                                    onChange={(e) => setBcc(e.target.value)}
                                />
                            </div>
                        </div>
                        {/* Honest about a real gap: the emails table has no cc/bcc column,
                            so these are recorded as a team note rather than silently lost. */}
                        <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                            Cc and Bcc are recorded as a note on this thread for now — the send path does not
                            carry them yet.
                        </span>
                    </div>
                ) : null}

                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ width: 32, font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        Subj
                    </span>
                    <div style={{ flex: 1 }}>
                        <TextField size="small" value={subject} onChange={(e) => setSubject(e.target.value)} />
                    </div>
                </div>

                {aiEnabled && !isEditing ? (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '6px 10px',
                            borderRadius: 4,
                            background: 'var(--primary-highlighted-color)',
                            flexWrap: 'wrap',
                        }}
                    >
                        <Icon name="Wand" size={14} color="var(--primary-color)" />
                        <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                            {usedAi ? 'AI draft — edit before it goes out' : 'Your own words'}
                        </span>
                        <span style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                            <Button kind="tertiary" size="small" onClick={onRegenerateDraft}>
                                {usedAi ? 'Try another' : 'Draft for me'}
                            </Button>
                            {usedAi ? (
                                <Button
                                    kind="tertiary"
                                    size="small"
                                    onClick={() => {
                                        setBody('');
                                        setUsedAi(false);
                                    }}
                                >
                                    Write my own
                                </Button>
                            ) : null}
                        </span>
                    </div>
                ) : null}

                <TextArea
                    rows={8}
                    placeholder="Write your reply"
                    value={body}
                    onChange={(e) => {
                        setBody(e.target.value);
                        if (usedAi && e.target.value !== aiDraft) setUsedAi(false);
                    }}
                />
            </div>

            <div
                style={{
                    padding: '10px 14px',
                    borderTop: '1px solid var(--om-hairline)',
                    background: 'var(--allgrey-background-color)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                }}
            >
                <Button
                    size="small"
                    disabled={!canSend}
                    loading={busy}
                    leftIcon={<Icon name="Send" size={16} />}
                    onClick={() => onSend(payload())}
                >
                    {isEditing ? 'Approve & send' : isManager ? 'Send now' : 'Submit for approval'}
                </Button>
                {!isEditing ? (
                    <Button kind="tertiary" size="small" disabled={busy} onClick={() => onSaveDraft(payload())}>
                        Save draft
                    </Button>
                ) : null}
                <Button kind="tertiary" size="small" color="negative" disabled={busy} onClick={onDiscard}>
                    Discard
                </Button>
                <span
                    style={{
                        marginInlineStart: 'auto',
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    <Icon name="Note" size={14} color="currentColor" />
                    {/*
                      Worth saying out loud, because the box only shows what you typed:
                      the earlier messages are attached on the way out, not stored on the
                      reply. That is what keeps the thread out of the AI checker's prompt.
                    */}
                    <span>The earlier messages are quoted automatically when this goes out</span>
                </span>
            </div>
        </div>
    );
}
