/**
 * One open thread, on a phone.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the `onThreadScreen` block.
 *
 * The mock's thread screen is a header, a summary card, collapsible messages and a Reply
 * bar. What it does not have is the approval queue, the AI-check banner, screened inbound
 * mail, rejection reasons, the workflow's own Context row, per-message forwarding or the
 * privacy toggle — all of which exist on live data and half of which are the reason a
 * manager opens the inbox on a phone at all.
 *
 * So the cards themselves are IMPORTED from ThreadView.jsx rather than redrawn here.
 * ApprovalBanner, AiSummary, NoteCard and MessageCard are the same components the desktop
 * renders, with the same gates — `thread.can`, `thread.reply_lock`, `item.redacted`. This
 * file owns the phone's navigation and its bottom bar, and nothing else. A withheld
 * message arrives from the server with no body at all, so there is nothing here that could
 * leak one.
 */

import { useRef, useState } from 'react';

import { Button, Icon, Loader, MenuButton, TextArea } from '../../ds';
import { ApprovalBanner, AiSummary, MessageCard, NoteCard } from '../ThreadView';

/** A horizontal flick moves to the next thread; this is how far, and how straight. */
const SWIPE_COMMIT = 90;
const SWIPE_OFF_AXIS = 50;

export function MobileThreadView({
    thread,
    loading,
    recipients,
    settings,
    noteOpen,
    noteText,
    summarising,
    refreshing,
    onBack,
    onStep,
    onRefresh,
    onSummarise,
    onOpenReply,
    onReplyToMessage,
    onOpenNote,
    onNoteText,
    onSaveNote,
    onCancelNote,
    onApprove,
    onEditApprove,
    onReject,
    onResendAi,
    onDelete,
    onDeleteMessage,
    onMore,
    onTogglePrivacy,
    onCreateTask,
    onOpenClientView,
}) {
    const [expanded, setExpanded] = useState({});
    const swipe = useRef(null);
    /**
     * A horizontal swipe that starts and ends on the same control still fires that
     * control's click. Over a message header that is a stray collapse; over the approval
     * banner it is an "Approve & send" nobody asked for, on a client-facing draft. So a
     * step swipe swallows exactly one click on the way out.
     */
    const swallowClick = useRef(false);

    if (loading && !thread) {
        return (
            <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Loader ariaLabel="Opening thread" />
            </div>
        );
    }

    if (!thread) return null;

    const messages = thread.timeline || [];
    const lastMessage = [...messages].reverse().find((i) => i.kind === 'message');
    const isOpen = (item) => (item.id in expanded ? expanded[item.id] : item.id === lastMessage?.id);

    // Prefer the per-thread answer from /recipients; fall back to the page settings until
    // it has loaded, so the Forward button never flashes in for someone who may not use it.
    const canAddressManually =
        recipients?.can_address_manually ?? settings?.can_address_manually ?? false;

    const approval = thread.approval;
    const canApproveHere = !!approval?.can_act && !thread.ai?.checking;

    const menuItems = [
        { value: 'refresh', label: refreshing ? 'Checking…' : 'Check for updates', icon: 'Update' },
        ...(thread.can?.create_task ? [{ value: 'task', label: 'Create task', icon: 'CheckList' }] : []),
        { value: 'unread', label: 'Mark as unread', icon: 'Email' },
        ...(thread.can?.delete
            ? [{ divider: true }, { value: 'delete', label: 'Delete from the CRM', icon: 'Delete', destructive: true }]
            : []),
    ];

    const onMenu = (value) => {
        if (value === 'refresh') return onRefresh();
        if (value === 'task') return onCreateTask(null);
        if (value === 'delete') return onDelete();
        return onMore(value);
    };

    return (
        <div
            style={{
                flex: 1,
                minHeight: 0,
                display: 'flex',
                flexDirection: 'column',
                background: 'var(--grey-background-color)',
                animation: 'mPush 150ms cubic-bezier(0,0,.35,1) both',
            }}
        >
            <div
                style={{
                    flex: 'none',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 4,
                    padding: '8px 6px 8px 4px',
                    background: 'var(--primary-background-color)',
                    borderBottom: '1px solid var(--layout-border-color)',
                }}
            >
                <button
                    type="button"
                    aria-label="Back to the list"
                    onClick={onBack}
                    style={{
                        width: 36,
                        height: 36,
                        flex: 'none',
                        border: 'none',
                        borderRadius: 4,
                        background: 'transparent',
                        color: 'var(--icon-color)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                    }}
                >
                    <Icon name="NavigationChevronLeft" size={20} color="currentColor" />
                </button>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <div
                        style={{
                            font: '600 14px/18px Figtree, sans-serif',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {thread.subject}
                    </div>
                    <div
                        style={{
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {[thread.project?.short || thread.project?.name, thread.who].filter(Boolean).join(' · ')}
                    </div>
                </div>

                <button
                    type="button"
                    aria-label="Previous thread"
                    onClick={() => onStep(-1)}
                    style={{
                        width: 30,
                        height: 36,
                        flex: 'none',
                        border: 'none',
                        background: 'transparent',
                        color: 'var(--icon-color)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                    }}
                >
                    <Icon name="NavigationChevronUp" size={16} color="currentColor" />
                </button>
                <button
                    type="button"
                    aria-label="Next thread"
                    onClick={() => onStep(1)}
                    style={{
                        width: 30,
                        height: 36,
                        flex: 'none',
                        border: 'none',
                        background: 'transparent',
                        color: 'var(--icon-color)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        cursor: 'pointer',
                    }}
                >
                    <Icon name="NavigationChevronDown" size={16} color="currentColor" />
                </button>

                <MenuButton items={menuItems} onSelect={onMenu} ariaLabel="More actions" size="small" />
            </div>

            {/*
              A scroll container must NOT also be the flex column.

              This element was both, and `flex-shrink` defaults to 1 — so instead of
              overflowing and scrolling, every card compressed to share whatever height was
              left. Open a second message and the first one visibly squashed; a long email
              was clipped by the Panel's `overflow: hidden` and there was no way to scroll
              to the rest of it.

              So: the scroller is a plain block, and the flex column lives inside it and is
              free to grow past its parent. That is what ThreadView does on the desktop,
              and flattening the two into one element here is what broke it.
            */}
            <div
                className="om-scroll"
                style={{
                    flex: 1,
                    minHeight: 0,
                    overflowY: 'auto',
                    padding: 12,
                    touchAction: 'pan-y',
                }}
                onPointerDown={(e) => {
                    swallowClick.current = false;
                    swipe.current = { x: e.clientX, y: e.clientY };
                }}
                onPointerUp={(e) => {
                    const start = swipe.current;
                    swipe.current = null;
                    if (!start) return;
                    const dx = e.clientX - start.x;
                    if (Math.abs(dx) > SWIPE_COMMIT && Math.abs(e.clientY - start.y) < SWIPE_OFF_AXIS) {
                        swallowClick.current = true;
                        onStep(dx < 0 ? 1 : -1);
                    }
                }}
                onPointerCancel={() => {
                    swipe.current = null;
                }}
                onClickCapture={(e) => {
                    if (!swallowClick.current) return;
                    swallowClick.current = false;
                    e.stopPropagation();
                    e.preventDefault();
                }}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                    {/*
                      The approval and AI-check banner, and everything it can do: approve and
                      send, edit and approve, send back, release screened inbound mail, resend
                      to the checker — each already gated on `approval.can_act` and
                      `thread.can.resend_to_ai`, both resolved by EmailPolicy server-side.
                    */}
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
                                compact
                                message={item}
                                open={isOpen(item)}
                                showSummary={thread.ai?.enabled}
                                canTogglePrivacy={thread.can?.toggle_privacy}
                                // Same gate as the composer: no replying to a locked thread,
                                // and never from a withheld message. Replying "to" a draft or
                                // a rejected draft is meaningless — the client never saw it, so
                                // it cannot anchor a reply.
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
                                onDeleteMessage={() => onDeleteMessage(item)}
                            />
                        )
                    )}

                    {noteOpen ? (
                        <div
                            style={{
                                border: '1px solid var(--om-note-border)',
                                borderRadius: 8,
                                background: 'var(--om-note-bg)',
                                padding: '12px 14px',
                                animation: 'mFade 150ms both',
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
                    ) : null}

                    <div style={{ height: 4 }} />
                </div>
            </div>

            {/*
              The bottom bar.

              The approve action is repeated from the banner on purpose: the banner is at
              the top of a thread that can be a dozen messages long, and scrolling back up
              to press it is the difference between clearing the queue on a phone and not
              bothering. Repeated BUTTON, single handler — the decision is still
              `approval.can_act`, resolved once server-side.
            */}
            {!thread.reply_lock && (thread.can?.reply || canApproveHere) ? (
                <div
                    style={{
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        padding: '10px 12px',
                        paddingBottom: 'calc(10px + var(--om-safe-bottom, 0px))',
                        background: 'var(--primary-background-color)',
                        borderTop: '1px solid var(--layout-border-color)',
                    }}
                >
                    {thread.can?.reply ? (
                        <Button
                            size="medium"
                            leftIcon={<Icon name="Reply" size={16} />}
                            onClick={onOpenReply}
                            style={{ flex: 1 }}
                        >
                            {thread.ai?.enabled && thread.ai?.draft ? 'Reply — draft ready' : 'Reply'}
                        </Button>
                    ) : null}

                    {canApproveHere ? (
                        <Button
                            kind="secondary"
                            size="medium"
                            leftIcon={<Icon name="Send" size={16} />}
                            onClick={onApprove}
                            style={{ flex: 'none' }}
                        >
                            {approval.kind === 'screening' ? 'Release' : 'Approve & send'}
                        </Button>
                    ) : null}

                    {!noteOpen ? (
                        <Button
                            kind="tertiary"
                            size="medium"
                            aria-label="Add team note"
                            leftIcon={<Icon name="Note" size={18} />}
                            onClick={onOpenNote}
                            style={{ flex: 'none', paddingInline: 10 }}
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
