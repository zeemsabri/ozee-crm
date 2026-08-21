/**
 * One open thread: header, approval banner, AI summary, the message/note timeline, and
 * the reply box.
 *
 * Design source: Inbox.dc.html, the sc-if="isThreadView" block.
 *
 * Withheld messages: the server omits the body entirely for anything this viewer may not
 * read and sends `redacted` plus a reason instead. The card below renders that reason —
 * it never receives the text and so cannot leak it through a CSS mistake or the React
 * devtools. See ThreadPresenter for the two rules (screening and privacy).
 */

import { useState } from 'react';
import {
    Avatar,
    Button,
    Chips,
    Counter,
    Icon,
    IconButton,
    Label,
    Loader,
    MenuButton,
    TextArea,
} from '../ds';
import { EmailBody } from './EmailBody';
import { ReplyBox } from './ReplyBox';
import { categoryColour, exactTime, initials, longTime, replyStatus } from './format';

const MORE_ITEMS = [
    { value: 'unread', label: 'Mark as unread', icon: 'Email' },
    { divider: true },
    { value: 'print', label: 'Print thread', icon: 'Print' },
];

function Panel({ children, tone, style }) {
    return (
        <div
            style={{
                border: `1px solid ${tone || 'var(--layout-border-color)'}`,
                borderRadius: 8,
                background: 'var(--primary-background-color)',
                overflow: 'hidden',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

export function ApprovalBanner({ thread, onApprove, onEditApprove, onReject, onResendAi }) {
    const approval = thread.approval;
    const checking = thread.ai?.checking;

    if (!approval && !checking) return null;

    const stalled = checking?.stalled;
    const tone = checking
        ? stalled
            ? '#d83a52'
            : 'var(--primary-color)'
        : 'var(--color-working-orange)';

    const title = checking
        ? stalled
            ? 'AI check has been running longer than it should'
            : 'With the AI checker'
        : approval.kind === 'screening'
          ? 'Inbound mail held for screening'
          : approval.ai_reason
            ? 'The AI checker sent this back — it needs your approval'
            : `Draft from ${approval.author} waiting on approval`;

    const meta = checking
        ? `Submitted by ${checking.author} · nothing to do while the checker has it`
        : approval.kind === 'screening'
          ? 'The team cannot read this until it is released'
          : 'The client never sees it until approved';

    return (
        <Panel tone={tone} style={{ animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both' }}>
            <div style={{ padding: '14px 16px', display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <Icon name={checking ? 'Robot' : 'Alert'} size={20} color={tone} />
                <div style={{ flex: 1, minWidth: 200 }}>
                    <div style={{ font: '600 14px/20px Figtree, sans-serif' }}>{title}</div>
                    <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        {meta}
                    </div>
                </div>

                {approval?.can_act && !checking ? (
                    <span style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <Button size="small" leftIcon={<Icon name="Send" size={16} />} onClick={onApprove}>
                            {approval.kind === 'screening' ? 'Release to the team' : 'Approve & send'}
                        </Button>
                        {/*
                          "Edit & approve" opens a free-text editor, which is meaningless
                          for a templated draft: the send path re-renders from the template
                          and template_data, so anything typed here would be discarded.
                          Approving it as-is is fine — the body shown above IS the rendered
                          template. Editing the template FIELDS is still a classic-inbox job.
                        */}
                        {approval.kind !== 'screening' && !approval.is_template ? (
                            <Button
                                kind="secondary"
                                size="small"
                                leftIcon={<Icon name="Edit" size={16} />}
                                onClick={onEditApprove}
                            >
                                Edit &amp; approve
                            </Button>
                        ) : null}
                        {approval.kind !== 'screening' && approval.is_template ? (
                            <span
                                style={{
                                    font: '400 12px/16px Figtree, sans-serif',
                                    color: 'var(--secondary-text-color)',
                                    maxWidth: '38ch',
                                }}
                            >
                                Built from a template — change its fields on the classic inbox.
                            </span>
                        ) : null}
                        {approval.kind !== 'screening' ? (
                            <Button kind="secondary" size="small" color="negative" onClick={onReject}>
                                Send back
                            </Button>
                        ) : null}
                    </span>
                ) : null}

                {thread.can?.resend_to_ai && checking ? (
                    <Button
                        kind={stalled ? 'primary' : 'secondary'}
                        size="small"
                        leftIcon={<Icon name="Recurring" size={16} />}
                        onClick={onResendAi}
                    >
                        Send to AI again
                    </Button>
                ) : null}

                {!approval?.can_act && !thread.can?.resend_to_ai ? (
                    <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        {checking
                            ? 'The checker has it — nothing to do yet.'
                            : "Waiting on a manager — you'll get a notification either way."}
                    </span>
                ) : null}
            </div>

            {approval?.ai_reason ? (
                <div
                    style={{
                        padding: '12px 16px',
                        borderTop: '1px solid var(--om-hairline)',
                        background: 'var(--allgrey-background-color)',
                        display: 'flex',
                        gap: 10,
                        alignItems: 'flex-start',
                    }}
                >
                    <Icon name="Robot" size={16} color="var(--secondary-text-color)" style={{ marginTop: 2 }} />
                    <div>
                        <div style={{ font: '600 12px/16px Figtree, sans-serif' }}>Why the AI checker held it</div>
                        <div
                            style={{
                                marginTop: 2,
                                font: '400 14px/20px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                                maxWidth: '78ch',
                                textWrap: 'pretty',
                            }}
                        >
                            {approval.ai_reason}
                        </div>
                    </div>
                </div>
            ) : null}
        </Panel>
    );
}

/**
 * The thread summary, and the button that asks for one.
 *
 * Summarising used to happen on its own the moment a thread was opened. That is now an
 * explicit request — see InboxThreadController::summarise for why — so this panel has to
 * render before a summary exists, which it previously did not: `if (!ai.summary) return
 * null` meant there was nowhere to put the button.
 */
export function AiSummary({ ai, messageCount, onCreateTask, onSummarise, summarising }) {
    if (!ai?.enabled) return null;

    const working = summarising || ai.summarising;

    // Nothing to show and nothing to offer.
    if (!ai.summary && !working && !ai.can_summarise && !ai.summary_failed) return null;

    return (
        <div
            style={{
                border: '1px solid var(--layout-border-color)',
                borderRadius: 8,
                background: 'var(--primary-highlighted-color)',
                padding: '14px 16px',
                animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <Icon name="Robot" size={16} color="var(--primary-color)" />
                <span
                    style={{
                        font: '600 12px/16px Figtree, sans-serif',
                        color: 'var(--primary-color)',
                        textTransform: 'uppercase',
                        letterSpacing: '.4px',
                    }}
                >
                    Thread summary
                </span>
                <span
                    style={{
                        marginInlineStart: 'auto',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {ai.summary ? (
                        <span>
                            Generated from {ai.generated_from || messageCount} message
                            {(ai.generated_from || messageCount) === 1 ? '' : 's'}
                        </span>
                    ) : null}
                    {/*
                      "Summarise" for the first one, "Update" when the thread has moved on
                      since the existing summary was written. Hidden entirely when the
                      summary is current, because asking again would spend tokens to
                      produce the same paragraph — the endpoint refuses it too.
                    */}
                    {ai.can_summarise || ai.summary_stalled ? (
                        <Button
                            kind="tertiary"
                            size="small"
                            disabled={working && !ai.summary_stalled}
                            onClick={onSummarise}
                        >
                            {working && !ai.summary_stalled
                                ? 'Summarising…'
                                : ai.summary_failed || ai.summary_stalled
                                  ? 'Try again'
                                  : ai.summary
                                    ? 'Update summary'
                                    : 'Summarise thread'}
                        </Button>
                    ) : null}
                </span>
            </div>

            {ai.summary ? (
                <p
                    style={{
                        margin: '8px 0 0',
                        font: '400 14px/20px Figtree, sans-serif',
                        maxWidth: '80ch',
                        textWrap: 'pretty',
                    }}
                >
                    {ai.summary}
                </p>
            ) : (
                <p
                    style={{
                        margin: '8px 0 0',
                        font: '400 14px/20px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {working
                        ? 'Reading the thread…'
                        : ai.summary_stalled
                          ? 'That summary never came back. Try again.'
                          : ai.summary_failed
                            ? 'The AI could not summarise this thread.'
                            : 'No summary yet — summaries are only generated when you ask, so nothing is spent on threads nobody needs one for.'}
                </p>
            )}

            {ai.task_suggestion?.title ? (
                <div
                    style={{
                        marginTop: 12,
                        paddingTop: 12,
                        borderTop: '1px solid var(--om-hairline)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        flexWrap: 'wrap',
                    }}
                >
                    <Icon name="CheckList" size={16} color="var(--secondary-text-color)" />
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <div style={{ font: '600 14px/20px Figtree, sans-serif' }}>{ai.task_suggestion.title}</div>
                        <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                            Suggested {ai.task_suggestion.priority} priority
                            {ai.task_suggestion.reason ? ` · ${ai.task_suggestion.reason}` : ''}
                        </div>
                    </div>
                    <Button kind="secondary" size="small" onClick={() => onCreateTask(ai.task_suggestion)}>
                        Create this task
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

export function NoteCard({ note }) {
    return (
        <div
            style={{
                border: '1px solid var(--om-note-border, #f0d78a)',
                borderRadius: 8,
                background: 'var(--om-note-bg, #fff8db)',
                padding: '12px 14px',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <Icon name="Note" size={16} color="var(--primary-text-color)" />
                <span style={{ font: '600 14px/20px Figtree, sans-serif' }}>{note.author}</span>
                <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    Internal note · never sent to the client
                </span>
                <span
                    style={{
                        marginInlineStart: 'auto',
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    <span title={exactTime(note.created_at)}>{longTime(note.created_at)}</span>
                </span>
            </div>
            <p style={{ margin: '6px 0 0', font: '400 14px/20px Figtree, sans-serif', whiteSpace: 'pre-wrap', textWrap: 'pretty' }}>
                {note.body}
            </p>
        </div>
    );
}

function Withheld({ kind }) {
    const isPrivate = kind === 'private';

    return (
        <div
            style={{
                padding: 16,
                border: '1px dashed var(--ui-border-color)',
                borderRadius: 4,
                background: 'var(--allgrey-background-color)',
                display: 'flex',
                alignItems: 'center',
                gap: 10,
            }}
        >
            <Icon name={isPrivate ? 'Hide' : 'Security'} size={20} color="var(--secondary-text-color)" />
            <div>
                <div style={{ font: '600 14px/20px Figtree, sans-serif' }}>
                    {isPrivate ? 'Private message' : 'Held for screening'}
                </div>
                <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    {isPrivate
                        ? "A manager marked this one private, so it stays out of the project team's view."
                        : 'A manager needs to release this message before the team can read it.'}
                </div>
            </div>
        </div>
    );
}

export function MessageCard({
    message,
    open,
    onToggle,
    onTogglePrivacy,
    canTogglePrivacy,
    canReply,
    canForward,
    showSummary,
    onReply,
    onOpenNote,
    // Opens the full branded document — what the client actually receives — in a
    // sandboxed frame. Offered on outbound messages only; see EmailBody.
    onOpenClientView,
    // Phone layout. The card is the same card and every gate above it is the same gate —
    // this only drops the avatar-width indent on the body, which on a 390px screen left
    // the message itself about 300px wide, and shrinks the avatar to match the mock.
    compact = false,
}) {
    const inbound = message.direction === 'in';

    return (
        <Panel>
            <div
                onClick={onToggle}
                style={{
                    padding: compact ? '11px 12px' : '12px 14px',
                    display: 'flex',
                    gap: compact ? 10 : 12,
                    cursor: 'pointer',
                    alignItems: 'flex-start',
                }}
            >
                <Avatar
                    text={initials(message.author)}
                    size={compact ? 'small' : 'medium'}
                    backgroundColor={inbound ? 'var(--primary-color)' : 'var(--color-explosive)'}
                />
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <span style={{ font: '600 14px/20px Figtree, sans-serif' }}>{message.author}</span>
                        <span
                            style={{
                                font: '400 12px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                                minWidth: 0,
                                overflowWrap: 'anywhere',
                            }}
                        >
                            {message.to}
                        </span>
                        <Label
                            text={message.status_label}
                            kind="line"
                            color={inbound ? 'primary' : 'positive'}
                            size="small"
                        />
                        {message.is_private ? (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 4,
                                    padding: '1px 6px',
                                    borderRadius: 4,
                                    background: 'var(--allgrey-background-color)',
                                    border: '1px solid var(--ui-border-color)',
                                    font: '600 11px/16px Figtree, sans-serif',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                <Icon name="Hide" size={12} color="currentColor" />
                                <span>Private</span>
                            </span>
                        ) : null}
                        {message.receipt ? (
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 4,
                                    font: '400 12px/16px Figtree, sans-serif',
                                    color: message.receipt.opened
                                        ? 'var(--positive-color)'
                                        : 'var(--secondary-text-color)',
                                }}
                            >
                                <Icon name={message.receipt.opened ? 'Show' : 'Hide'} size={14} color="currentColor" />
                                <span>
                                    {message.receipt.opened ? `Opened ${longTime(message.receipt.at)}` : 'Not opened yet'}
                                </span>
                            </span>
                        ) : null}
                        <span
                            style={{
                                marginInlineStart: 'auto',
                                font: '400 12px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            <span title={exactTime(message.created_at)}>
                                {longTime(message.created_at)}
                            </span>
                        </span>
                    </div>

                    {!open ? (
                        <div
                            style={{
                                marginTop: 2,
                                font: '400 14px/20px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {message.snippet}
                        </div>
                    ) : null}
                </div>
            </div>

            {open ? (
                <div style={{ padding: compact ? '0 12px 12px' : '0 14px 14px 58px' }}>
                    {message.redacted ? (
                        <Withheld kind={message.redaction} />
                    ) : (
                        <>
                            {showSummary && message.summary ? (
                                <div
                                    style={{
                                        marginBottom: 12,
                                        padding: '8px 10px',
                                        borderInlineStart: '2px solid var(--primary-color)',
                                        background: 'var(--primary-highlighted-color)',
                                        borderRadius: '0 4px 4px 0',
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 8,
                                    }}
                                >
                                    <Icon name="Robot" size={14} color="var(--primary-color)" style={{ marginTop: 3 }} />
                                    <div>
                                        <span
                                            style={{
                                                font: '600 11px/16px Figtree, sans-serif',
                                                color: 'var(--primary-color)',
                                                textTransform: 'uppercase',
                                                letterSpacing: '.4px',
                                            }}
                                        >
                                            This message
                                        </span>
                                        <div
                                            style={{
                                                font: '400 13px/20px Figtree, sans-serif',
                                                color: 'var(--secondary-text-color)',
                                                maxWidth: '80ch',
                                                textWrap: 'pretty',
                                            }}
                                        >
                                            {message.summary}
                                        </div>
                                    </div>
                                </div>
                            ) : null}

                            {/*
                              The AI context the automation recorded for this email.

                              Separate from the block above: that one is our own optional
                              inbox AI (`inbox.ai.enabled`, off by default), this is the
                              Context row the approval workflow already writes on live
                              data. It was never rendered — the design has the slot and the
                              thread showed nothing in it.

                              Not gated on the AI feature switch, deliberately. That switch
                              decides whether WE call a model; letting it also decide
                              whether existing business records are visible would be a
                              different thing wearing the same name.
                            */}
                            {message.context?.summary ? (
                                <div
                                    style={{
                                        marginBottom: 12,
                                        padding: '8px 10px',
                                        borderInlineStart: '2px solid var(--color-indigo)',
                                        background: 'var(--allgrey-background-color)',
                                        borderRadius: '0 4px 4px 0',
                                        display: 'flex',
                                        alignItems: 'flex-start',
                                        gap: 8,
                                    }}
                                >
                                    <Icon
                                        name="Bolt"
                                        size={14}
                                        color="var(--color-indigo)"
                                        style={{ marginTop: 3 }}
                                    />
                                    <div style={{ minWidth: 0 }}>
                                        <span
                                            style={{
                                                font: '600 11px/16px Figtree, sans-serif',
                                                color: 'var(--color-indigo)',
                                                textTransform: 'uppercase',
                                                letterSpacing: '.4px',
                                            }}
                                        >
                                            Context
                                        </span>
                                        {message.context.created_at ? (
                                            <span
                                                title={exactTime(message.context.created_at)}
                                                style={{
                                                    marginInlineStart: 8,
                                                    font: '400 11px/16px Figtree, sans-serif',
                                                    color: 'var(--secondary-text-color)',
                                                }}
                                            >
                                                {/* Author only when a person wrote it. A
                                                    workflow-written context has no user,
                                                    and inventing one would misattribute a
                                                    machine's read to a colleague. */}
                                                {message.context.author
                                                    ? `${message.context.author} · ${longTime(message.context.created_at)}`
                                                    : longTime(message.context.created_at)}
                                            </span>
                                        ) : null}
                                        <div
                                            style={{
                                                font: '400 13px/20px Figtree, sans-serif',
                                                color: 'var(--secondary-text-color)',
                                                maxWidth: '80ch',
                                                textWrap: 'pretty',
                                            }}
                                        >
                                            {message.context.summary}
                                        </div>
                                    </div>
                                </div>
                            ) : null}

                            {message.rejection_reason ? (
                                <div
                                    style={{
                                        marginBottom: 12,
                                        padding: '8px 10px',
                                        borderRadius: 4,
                                        background: 'var(--negative-color-selected)',
                                        font: '400 13px/20px Figtree, sans-serif',
                                    }}
                                >
                                    <strong>Sent back:</strong> {message.rejection_reason}
                                </div>
                            ) : null}

                            {/*
                              Body, quoted chain and attachments.

                              One component, because `emails.body` holds four different
                              things — plain text from the ingest, plain text from the
                              redesigned composer's textarea, rich-editor HTML from the
                              legacy one, and our own block fragment — and this card used
                              to inject all four as HTML. EmailHtml decides server-side
                              which is which; EmailBody presents the result. Withheld
                              messages never get here: they arrive with body_html null and
                              are handled above.
                            */}
                            <EmailBody
                                message={message}
                                compact={compact}
                                onOpenClientView={onOpenClientView}
                            />

                            {/*
                              Per-message actions, as in the design. Replying from a
                              specific message is not just a shortcut to the composer at
                              the bottom: it sets THAT message as the reply's parent, so
                              In-Reply-To/References point at what is actually being
                              answered rather than always at the newest inbound one. On a
                              thread where a client asked two separate questions, that is
                              the difference between a correctly threaded answer and one
                              that looks like a reply to something else.
                            */}
                            {canReply || canTogglePrivacy ? (
                                <div
                                    style={{
                                        marginTop: 14,
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    {canReply ? (
                                        <>
                                            <Button
                                                kind="secondary"
                                                size="small"
                                                leftIcon={<Icon name="Reply" size={16} />}
                                                onClick={() => onReply(message, 'reply')}
                                            >
                                                Reply
                                            </Button>
                                            {/*
                                              No "Reply all" button. Recipients are the
                                              project's clients whichever mode you pick, so
                                              it addressed exactly the same people as Reply
                                              — two buttons, one behaviour, and an implied
                                              distinction that does not exist.
                                            */}
                                            {/* Needs `email_custom_recipients`: forwarding
                                                is the only way to send a client thread to
                                                an address that is not on the project, and
                                                the endpoint refuses it without that
                                                permission. */}
                                            {canForward ? (
                                                <Button
                                                    kind="secondary"
                                                    size="small"
                                                    leftIcon={<Icon name="Share" size={16} />}
                                                    onClick={() => onReply(message, 'forward')}
                                                >
                                                    Forward
                                                </Button>
                                            ) : null}
                                            <Button
                                                kind="tertiary"
                                                size="small"
                                                leftIcon={<Icon name="Note" size={16} />}
                                                onClick={onOpenNote}
                                            >
                                                Add team note
                                            </Button>
                                        </>
                                    ) : null}
                                    {canTogglePrivacy ? (
                                        <Button kind="tertiary" size="small" onClick={onTogglePrivacy}>
                                            {message.is_private ? 'Make visible to team' : 'Make private'}
                                        </Button>
                                    ) : null}
                                </div>
                            ) : null}
                        </>
                    )}
                </div>
            ) : null}
        </Panel>
    );
}

export function ThreadView({
    thread,
    loading,
    recipients,
    replyOpen,
    replyEditing,
    replyTarget,
    compose,
    replyBusy,
    noteOpen,
    noteText,
    // Whether this person may type an email address by hand. Drives the Forward button
    // only — replying needs no permission, because it can only ever reach the project's
    // clients. See InboxAccess::canAddressManually.
    canAddressManually,
    backLabel,
    onBack,
    // Manual refresh, plus whether one is in flight so the icon can spin.
    onRefresh,
    refreshing,
    // Summarising is an explicit request now — see InboxThreadController::summarise.
    onSummarise,
    summarising,
    onOpenReply,
    onReplyToMessage,
    onCloseReply,
    onSendReply,
    onRegenerateDraft,
    // Surfacing failures from the composer's own requests (image uploads, the block
    // preview) — they happen inside ReplyBox, not through the page's action layer.
    onError,
    onOpenNote,
    onNoteText,
    onSaveNote,
    onCancelNote,
    onApprove,
    onEditApprove,
    onReject,
    onResendAi,
    onDelete,
    onMore,
    onTogglePrivacy,
    onCreateTask,
    // Opens the full branded document for one outbound message.
    onOpenClientView,
}) {
    const [expanded, setExpanded] = useState({});

    if (loading && !thread) {
        return (
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Loader ariaLabel="Opening thread" />
            </div>
        );
    }

    if (!thread) return null;

    const status = replyStatus(thread);
    const messages = thread.timeline || [];
    const lastMessage = [...messages].reverse().find((i) => i.kind === 'message');

    const isOpen = (item) => (item.id in expanded ? expanded[item.id] : item.id === lastMessage?.id);

    return (
        <div
            style={{
                flex: 1,
                minHeight: 0,
                display: 'flex',
                flexDirection: 'column',
                animation: 'dcSlideIn 200ms cubic-bezier(0,0,.35,1) both',
            }}
        >
            <div
                style={{
                    flex: 'none',
                    padding: '12px 24px 14px',
                    background: 'var(--primary-background-color)',
                    borderBottom: '1px solid var(--layout-border-color)',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <Button
                        kind="tertiary"
                        size="small"
                        leftIcon={<Icon name="NavigationChevronLeft" size={16} />}
                        onClick={onBack}
                    >
                        {backLabel}
                    </Button>
                    <span style={{ color: 'var(--secondary-text-color)' }}>/</span>
                    <span
                        style={{
                            font: '400 14px/20px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {thread.project?.name}
                    </span>
                    <span style={{ marginInlineStart: 'auto', display: 'flex', alignItems: 'center', gap: 4 }}>
                        {/*
                          Manual refresh.

                          The thread already reloads itself once a minute while anything on
                          it is still moving (see `in_flight`), but "is it sent yet" is a
                          question people ask on their own schedule, and waiting up to 60
                          seconds to find out feels broken. The spinning state matters as
                          much as the reload: without it a fast, unchanged response looks
                          like the button did nothing.
                        */}
                        <IconButton
                            name="Update"
                            size="small"
                            ariaLabel={refreshing ? 'Checking for updates' : 'Check for updates'}
                            disabled={refreshing}
                            onClick={onRefresh}
                            style={refreshing ? { animation: 'ozeeSpin 900ms linear infinite' } : undefined}
                        />
                        {thread.can?.create_task ? (
                            <Button
                                kind="secondary"
                                size="small"
                                leftIcon={<Icon name="CheckList" size={16} />}
                                onClick={() => onCreateTask(null)}
                            >
                                Create task
                            </Button>
                        ) : null}
                        {thread.can?.delete ? (
                            <IconButton name="Delete" size="small" ariaLabel="Delete thread" onClick={onDelete} />
                        ) : null}
                        <MenuButton items={MORE_ITEMS} onSelect={onMore} iconName="MoreActions" size="small" ariaLabel="More actions" />
                    </span>
                </div>

                <div style={{ marginTop: 8, display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
                    <h2 style={{ margin: 0, font: '600 24px/30px Poppins, sans-serif', letterSpacing: '-0.1px' }}>
                        {thread.subject}
                    </h2>
                    <Counter count={thread.message_count} kind="line" color="dark" size="small" />
                    {(thread.categories || []).map((c) => (
                        <Chips key={c.id} label={c.name} color={categoryColour(c.name)} readOnly size="small" />
                    ))}
                </div>

                <div
                    style={{
                        marginTop: 8,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        padding: '8px 12px',
                        borderRadius: 4,
                        background: status.bg,
                        flexWrap: 'wrap',
                    }}
                >
                    <span style={{ width: 10, height: 10, borderRadius: '50%', background: status.dot }} />
                    <span style={{ font: '600 14px/20px Figtree, sans-serif', color: status.fg }}>{status.text}</span>
                    <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        {thread.reply?.needs_reply
                            ? 'Reply first, action later — a holding reply stops the clock'
                            : 'No reply owed on this thread'}
                    </span>
                </div>
            </div>

            <div style={{ flex: 1, minHeight: 0, overflow: 'auto', padding: '16px 24px 32px' }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    <ApprovalBanner
                        thread={thread}
                        onApprove={onApprove}
                        onEditApprove={onEditApprove}
                        onReject={onReject}
                        onResendAi={onResendAi}
                    />

                    <AiSummary
                        ai={thread.ai}
                        messageCount={thread.message_count}
                        onCreateTask={onCreateTask}
                        onSummarise={onSummarise}
                        summarising={summarising}
                    />

                    {messages.map((item) =>
                        item.kind === 'note' ? (
                            <NoteCard key={`note-${item.id}`} note={item} />
                        ) : (
                            <MessageCard
                                key={`msg-${item.id}`}
                                message={item}
                                open={isOpen(item)}
                                showSummary={thread.ai?.enabled}
                                canTogglePrivacy={thread.can?.toggle_privacy}
                                // Same gate as the composer at the bottom: no replying to
                                // a locked thread, and never from a withheld message.
                                // Replying "to" a draft or a rejected draft is meaningless:
                                // the client never saw it, so it cannot anchor a reply and
                                // it is excluded from the quote. Only a message the other
                                // party has actually seen can be answered.
                                canReply={
                                    thread.can?.reply &&
                                    !thread.reply_lock &&
                                    !item.redacted &&
                                    (item.direction === 'in' || item.status === 'sent')
                                }
                                canForward={canAddressManually}
                                onReply={onReplyToMessage}
                                onOpenNote={onOpenNote}
                                onOpenClientView={onOpenClientView}
                                onToggle={() =>
                                    setExpanded((current) => ({ ...current, [item.id]: !isOpen(item) }))
                                }
                                onTogglePrivacy={() => onTogglePrivacy(item)}
                            />
                        )
                    )}

                    {noteOpen ? (
                        <div
                            style={{
                                border: '1px solid var(--om-note-border, #f0d78a)',
                                borderRadius: 8,
                                background: 'var(--om-note-bg, #fff8db)',
                                padding: '12px 14px',
                                animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both',
                            }}
                        >
                            <div style={{ font: '600 14px/20px Figtree, sans-serif', marginBottom: 8 }}>
                                Team note — stays inside OZee
                            </div>
                            <TextArea
                                rows={3}
                                placeholder="What does the team need to know about this thread?"
                                value={noteText}
                                onChange={(e) => onNoteText(e.target.value)}
                            />
                            <div style={{ marginTop: 8, display: 'flex', gap: 8 }}>
                                <Button size="small" disabled={!noteText.trim()} onClick={onSaveNote}>
                                    Add note
                                </Button>
                                <Button kind="tertiary" size="small" onClick={onCancelNote}>
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    {thread.reply_lock ? (
                        <div
                            style={{
                                border: '1px dashed var(--ui-border-color)',
                                borderRadius: 8,
                                background: 'var(--allgrey-background-color)',
                                padding: '14px 16px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            <Icon name="Security" size={16} color="currentColor" />
                            <span style={{ font: '400 14px/20px Figtree, sans-serif' }}>{thread.reply_lock}</span>
                        </div>
                    ) : replyOpen ? (
                        <ReplyBox
                            thread={thread}
                            recipients={recipients}
                            editing={replyEditing}
                            replyTo={replyTarget}
                            compose={compose}
                            aiDraft={thread.ai?.draft}
                            aiEnabled={thread.ai?.enabled}
                            aiDrafting={thread.ai?.drafting}
                            aiDraftFailed={thread.ai?.draft_failed}
                            aiDraftStalled={thread.ai?.draft_stalled}
                            busy={replyBusy}
                            onSend={onSendReply}
                            onDiscard={onCloseReply}
                            onRegenerateDraft={onRegenerateDraft}
                            onError={onError}
                        />
                    ) : (
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <button
                                type="button"
                                onClick={onOpenReply}
                                style={{
                                    flex: 1,
                                    minWidth: 260,
                                    textAlign: 'start',
                                    border: '1px solid var(--layout-border-color)',
                                    borderRadius: 8,
                                    background: 'var(--primary-background-color)',
                                    padding: '14px 16px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    cursor: 'pointer',
                                    color: 'var(--secondary-text-color)',
                                    font: '400 14px/20px Figtree, sans-serif',
                                    transition: 'border-color 150ms cubic-bezier(.4,0,.2,1)',
                                }}
                                onMouseEnter={(e) => {
                                    e.currentTarget.style.borderColor = 'var(--primary-color)';
                                }}
                                onMouseLeave={(e) => {
                                    e.currentTarget.style.borderColor = 'var(--layout-border-color)';
                                }}
                            >
                                <Icon name="Reply" size={16} color="currentColor" />
                                <span>
                                    Reply to {thread.who}&apos;s latest message
                                    {thread.ai?.enabled && thread.ai?.draft ? ' — an AI draft is ready to edit' : ''}
                                </span>
                            </button>
                            {!noteOpen ? (
                                <Button
                                    kind="secondary"
                                    size="small"
                                    leftIcon={<Icon name="Note" size={16} />}
                                    onClick={onOpenNote}
                                >
                                    Add team note
                                </Button>
                            ) : null}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
