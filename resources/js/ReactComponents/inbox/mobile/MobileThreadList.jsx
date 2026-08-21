/**
 * The thread list on a phone.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the `onListScreen` block.
 *
 * A row here is a CONVERSATION, not an email, exactly as on the desktop: the mock's rows
 * carry one email's status, ours carry the thread's aggregate, which is why a card can say
 * "Needs a manager" and "3 messages" at once. Everything a row is allowed to offer comes
 * from `thread.can`, resolved server-side by EmailPolicy — never from `is_manager` here.
 *
 * Three gestures, and the choice of which is which is a safety decision:
 *  - swipe right  → mark read / unread. Reversible, and the mock's own right-hand hint.
 *  - swipe left   → open this row's actions. The mock archives on this swipe; we have no
 *                   archive, and the nearest thing we do have is a delete, which is not
 *                   something to hand to a thumb-flick. So the swipe opens a menu instead
 *                   of acting.
 *  - long press   → multi-select, which is what the desktop's checkbox column is for.
 */

import { useRef, useState } from 'react';

import { Avatar, Button, Chips, EmptyState, Icon, Loader } from '../../ds';
import {
    categoryColour,
    exactTime,
    initials,
    plural,
    replyStatus,
    shortTime,
    viewSubtitle,
    VIEW_DEFS,
} from '../format';
import { Sheet } from './Sheet';

/** How far a row has to travel before the gesture counts, and how far off-axis kills it. */
const SWIPE_COMMIT = 70;
const SWIPE_START = 6;
const SWIPE_OFF_AXIS = 26;
const LONG_PRESS_MS = 450;

/** Pull-to-refresh: how far the finger travels, and how tall the indicator grows. */
const PULL_TRIGGER = 90;
const PULL_MAX = 44;

function MetaBit({ icon, children, color }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 3,
                font: '400 11px/16px Figtree, sans-serif',
                color: color || 'var(--secondary-text-color)',
            }}
        >
            <Icon name={icon} size={12} color="currentColor" />
            <span>{children}</span>
        </span>
    );
}

function Pill({ text, bg, fg, dot }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                height: 20,
                padding: '0 6px',
                borderRadius: 4,
                background: bg,
                color: fg,
                font: '700 11px/16px Figtree, sans-serif',
                whiteSpace: 'nowrap',
            }}
        >
            {dot ? <span style={{ width: 6, height: 6, borderRadius: '50%', background: dot }} /> : null}
            {text}
        </span>
    );
}

function ThreadRow({ thread, selecting, checked, onOpen, onSelect, onSwipeRead, onSwipeActions }) {
    const rowRef = useRef(null);
    const gesture = useRef(null);
    const pressTimer = useRef(null);
    /**
     * The tap that ends a swipe or a long press must not also open the thread.
     *
     * This was a transform check — "did the row move?" — which is wrong in both
     * directions: `settle()` runs on pointerup, before click, so the row had already been
     * put back; and the inline transform it leaves behind (`translateX(0)`) then swallowed
     * the NEXT tap too, which reads as the row simply not opening.
     */
    const consumeClick = useRef(false);

    const status = replyStatus(thread);
    const unread = thread.is_unread;
    const inbound = thread.direction === 'in';
    const flags = thread.flags || {};

    const settle = () => {
        const el = rowRef.current;
        if (!el) return;
        el.classList.add('om-row-settle');
        el.style.transform = '';
        window.setTimeout(() => el?.classList.remove('om-row-settle'), 180);
    };

    const cancelPress = () => {
        if (pressTimer.current) {
            window.clearTimeout(pressTimer.current);
            pressTimer.current = null;
        }
    };

    const onPointerDown = (e) => {
        // Cleared here, not only in handleClick.
        //
        // A pointercancel produces NO click — and Android fires one on a long press,
        // right after the timer below has set the flag — so a flag only cleared by a
        // click survives into the next gesture and eats a legitimate tap. Which is the
        // same bug this flag exists to fix, one gesture later.
        consumeClick.current = false;
        gesture.current = { x: e.clientX, y: e.clientY, moved: false, id: e.pointerId };

        // Without capture, a finger that leaves the row stops delivering pointermove to it
        // and the card is left sitting mid-swipe with no event to put it back.
        try {
            e.currentTarget.setPointerCapture(e.pointerId);
        } catch {
            /* Not supported, or the pointer is already gone — the gesture still works. */
        }

        // Long press opens multi-select. Skipped once selecting — at that point a plain
        // tap already toggles, and holding a row would fight the tap.
        if (!selecting) {
            cancelPress();
            pressTimer.current = window.setTimeout(() => {
                pressTimer.current = null;
                gesture.current = null;
                consumeClick.current = true;
                settle();
                onSelect();
            }, LONG_PRESS_MS);
        }
    };

    const onPointerMove = (e) => {
        const g = gesture.current;
        if (!g) return;

        const dx = e.clientX - g.x;
        const dy = e.clientY - g.y;

        if (Math.abs(dx) > 4 || Math.abs(dy) > 4) cancelPress();

        // A mostly-vertical drag is a scroll, not a swipe — leave it to the container.
        if (Math.abs(dx) < SWIPE_START || Math.abs(dy) > SWIPE_OFF_AXIS) return;
        if (selecting) return;

        g.moved = true;
        const el = rowRef.current;
        if (el) el.style.transform = `translateX(${dx}px)`;
    };

    const onPointerUp = (e) => {
        const g = gesture.current;
        gesture.current = null;
        cancelPress();

        try {
            e.currentTarget.releasePointerCapture(e.pointerId);
        } catch {
            /* Already released. */
        }

        if (!g) return;

        const dx = e.clientX - g.x;
        settle();

        if (!g.moved) return;

        consumeClick.current = true;
        if (dx > SWIPE_COMMIT) onSwipeRead();
        else if (dx < -SWIPE_COMMIT) onSwipeActions();
    };

    const handleClick = () => {
        if (consumeClick.current) {
            consumeClick.current = false;
            return;
        }
        if (selecting) onSelect();
        else onOpen();
    };

    return (
        <div
            style={{
                position: 'relative',
                margin: '0 12px 10px',
                borderRadius: 8,
                overflow: 'hidden',
                background: 'var(--allgrey-background-color)',
            }}
        >
            {/* What the swipe reveals underneath. Sits behind the card, never interactive —
                the gesture is the control. */}
            <div
                aria-hidden="true"
                style={{
                    position: 'absolute',
                    inset: 0,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '0 16px',
                    font: '600 12px/16px Figtree, sans-serif',
                    color: 'var(--secondary-text-color)',
                }}
            >
                <span style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'var(--positive-color)' }}>
                    <Icon name={unread ? 'Show' : 'Hide'} size={14} color="currentColor" />
                    {unread ? 'Mark read' : 'Mark unread'}
                </span>
                <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    Actions
                    <Icon name="MoreActions" size={14} color="currentColor" />
                </span>
            </div>

            <div
                ref={rowRef}
                className="om-row"
                role="button"
                tabIndex={0}
                aria-pressed={selecting ? checked : undefined}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={onPointerUp}
                onPointerCancel={onPointerUp}
                onContextMenu={(e) => e.preventDefault()}
                onClick={handleClick}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleClick();
                    }
                }}
                style={{
                    position: 'relative',
                    background: 'var(--primary-background-color)',
                    border: `1px solid ${checked ? 'var(--primary-color)' : 'var(--layout-border-color)'}`,
                    borderInlineStart: `3px solid ${status.dot}`,
                    borderRadius: 8,
                    padding: '12px 14px',
                    display: 'flex',
                    gap: 10,
                    cursor: 'pointer',
                }}
            >
                <div
                    style={{
                        flex: 'none',
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'center',
                        gap: 6,
                        paddingTop: 2,
                    }}
                >
                    {selecting ? (
                        <span
                            style={{
                                width: 32,
                                height: 32,
                                borderRadius: '50%',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                background: checked ? 'var(--primary-color)' : 'var(--allgrey-background-color)',
                                color: checked ? 'var(--text-color-on-primary)' : 'var(--icon-color)',
                                border: checked ? 'none' : '1px solid var(--ui-border-color)',
                            }}
                        >
                            <Icon name="Check" size={16} color="currentColor" />
                        </span>
                    ) : (
                        <Avatar
                            text={initials(thread.who)}
                            size="medium"
                            backgroundColor={inbound ? 'var(--primary-color)' : 'var(--color-explosive)'}
                        />
                    )}
                </div>

                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                        <span
                            style={{
                                flex: 1,
                                minWidth: 0,
                                font: `${unread ? 700 : 600} 14px/20px Figtree, sans-serif`,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {thread.who}
                        </span>
                        <span
                            title={exactTime(thread.last_message_at)}
                            style={{
                                flex: 'none',
                                font: '400 12px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            {shortTime(thread.last_message_at)}
                        </span>
                        {unread ? (
                            <span
                                aria-label="Unread"
                                style={{
                                    flex: 'none',
                                    width: 8,
                                    height: 8,
                                    borderRadius: '50%',
                                    background: 'var(--primary-color)',
                                }}
                            />
                        ) : null}
                    </div>

                    <div
                        style={{
                            marginTop: 1,
                            font: `${unread ? 600 : 400} 14px/20px Figtree, sans-serif`,
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {thread.subject}
                    </div>

                    <div
                        style={{
                            marginTop: 2,
                            font: '400 13px/18px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            display: '-webkit-box',
                            WebkitLineClamp: 2,
                            WebkitBoxOrient: 'vertical',
                            overflow: 'hidden',
                        }}
                    >
                        {thread.preview}
                    </div>

                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            flexWrap: 'wrap',
                            marginTop: 8,
                        }}
                    >
                        <span
                            style={{
                                display: 'inline-flex',
                                alignItems: 'center',
                                height: 20,
                                padding: '0 6px',
                                border: '1px solid var(--layout-border-color)',
                                borderRadius: 4,
                                font: '600 11px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                                maxWidth: 160,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {thread.project?.short || 'Lead'}
                        </span>

                        {/*
                          The reply clock and the workflow state, in the desktop's own
                          precedence: with AI → awaiting a manager → unsent draft → clock.
                          `replyStatus` decides, so the two layouts cannot label the same
                          thread differently.
                        */}
                        <Pill text={status.text} bg={status.bg} fg={status.fg} dot={status.dot} />

                        {(thread.categories || []).slice(0, 2).map((c) => (
                            <Chips
                                key={c.id}
                                label={c.name}
                                color={categoryColour(c.name)}
                                readOnly
                                size="small"
                            />
                        ))}

                        {thread.attachment_count > 0 ? (
                            <MetaBit icon="Attach">{plural(thread.attachment_count, 'file')}</MetaBit>
                        ) : null}

                        {thread.message_count > 1 ? (
                            <MetaBit icon="Note">{thread.message_count}</MetaBit>
                        ) : null}

                        {flags.has_private ? <MetaBit icon="Hide">Private message</MetaBit> : null}

                        {thread.last_receipt ? (
                            <MetaBit
                                icon={thread.last_receipt.opened ? 'Show' : 'Hide'}
                                color={
                                    thread.last_receipt.opened
                                        ? 'var(--positive-color)'
                                        : 'var(--secondary-text-color)'
                                }
                            >
                                {thread.last_receipt.opened ? 'Opened' : 'Not opened'}
                            </MetaBit>
                        ) : null}
                    </div>
                </div>
            </div>
        </div>
    );
}

/** The per-row menu the left swipe opens. Every entry is gated on the server's answer. */
function RowActions({ thread, onClose, actions }) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const can = thread?.can || {};
    const flags = thread?.flags || {};

    const item = (destructive) => ({
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        width: '100%',
        boxSizing: 'border-box',
        padding: '14px 16px',
        border: 'none',
        borderBottom: '1px solid var(--om-hairline-soft)',
        background: 'transparent',
        cursor: 'pointer',
        textAlign: 'start',
        font: '400 15px/20px Figtree, sans-serif',
        color: destructive ? 'var(--negative-color)' : 'var(--primary-text-color)',
    });

    return (
        <Sheet
            open={!!thread}
            onClose={() => {
                setConfirmDelete(false);
                onClose();
            }}
            title={thread?.subject}
        >
            <button type="button" style={item(false)} onClick={actions.open}>
                <Icon name="Email" size={18} color="var(--icon-color)" />
                <span style={{ flex: 1 }}>Open thread</span>
            </button>

            {can.reply ? (
                <button type="button" style={item(false)} onClick={actions.reply}>
                    <Icon name="Reply" size={18} color="var(--icon-color)" />
                    <span style={{ flex: 1 }}>Reply</span>
                </button>
            ) : null}

            {/*
              Release, never send. Approving an OUTBOUND draft means putting a message in
              front of a client, and nobody should do that from a list where the body is a
              two-line preview — that button lives in the thread screen, next to the text
              it will send.
            */}
            {can.release ? (
                <button type="button" style={item(false)} onClick={actions.release}>
                    <Icon name="Security" size={18} color="var(--icon-color)" />
                    <span style={{ flex: 1 }}>
                        {flags.screening ? 'Release to the team' : 'Clear the AI hold'}
                    </span>
                </button>
            ) : null}

            {can.resend_to_ai ? (
                <button type="button" style={item(false)} onClick={actions.resendAi}>
                    <Icon name="Recurring" size={18} color="var(--icon-color)" />
                    <span style={{ flex: 1 }}>Send to the AI checker again</span>
                </button>
            ) : null}

            <button type="button" style={item(false)} onClick={actions.toggleRead}>
                <Icon name={thread?.is_unread ? 'Show' : 'Hide'} size={18} color="var(--icon-color)" />
                <span style={{ flex: 1 }}>{thread?.is_unread ? 'Mark as read' : 'Mark as unread'}</span>
            </button>

            <button type="button" style={item(false)} onClick={actions.categorise}>
                <Icon name="Tags" size={18} color="var(--icon-color)" />
                <span style={{ flex: 1 }}>Categorise</span>
            </button>

            <button type="button" style={item(false)} onClick={actions.select}>
                <Icon name="Check" size={18} color="var(--icon-color)" />
                <span style={{ flex: 1 }}>Select more</span>
            </button>

            {can.delete ? (
                <button
                    type="button"
                    style={item(true)}
                    onClick={() => {
                        if (!confirmDelete) {
                            setConfirmDelete(true);
                            return;
                        }
                        setConfirmDelete(false);
                        actions.remove();
                    }}
                >
                    <Icon name="Delete" size={18} color="currentColor" />
                    <span style={{ flex: 1 }}>
                        {/* Two taps, because a swipe followed by one tap is how a client
                            thread disappears by accident. The Gmail copy survives either
                            way, but the local record is what everyone here reads. */}
                        {confirmDelete ? 'Tap again to delete locally' : 'Delete from the CRM'}
                    </span>
                </button>
            ) : null}

            <div style={{ height: 'calc(12px + var(--om-safe-bottom, 0px))' }} />
        </Sheet>
    );
}

export function MobileThreadList({
    threads,
    meta,
    loading,
    filters,
    counts,
    overdueCount,
    slaMinutes,
    selectedIds,
    isManager,
    onOpen,
    onReply,
    onRelease,
    onResendAi,
    onToggleRead,
    onToggleSelect,
    onClearSelection,
    onBulk,
    onPage,
    onRefresh,
}) {
    const scrollRef = useRef(null);
    const pullRef = useRef(null);
    const pull = useRef(null);
    const [refreshing, setRefreshing] = useState(false);
    const [rowActionsFor, setRowActionsFor] = useState(null);

    const selecting = selectedIds.length > 0;
    const view = VIEW_DEFS.find((v) => v.key === filters.view);

    // ------------------------------------------------------------ pull to refresh

    /**
     * `animate` off while the finger is down.
     *
     * The indicator carried a permanent `transition: height 150ms`, which meant it trailed
     * the finger by a sixth of a second the whole way down — the one place in a pull
     * gesture where lag is unmistakable. The easing belongs on the release, not the drag.
     */
    const setPullHeight = (px, animate = false) => {
        const el = pullRef.current;
        if (!el) return;
        el.style.transition = animate ? 'height 150ms' : 'none';
        el.style.height = `${px}px`;
    };

    const onPullStart = (e) => {
        if (refreshing) return;
        const el = scrollRef.current;
        if (!el || el.scrollTop > 0) return;
        pull.current = { y: e.clientY, x: e.clientX };
    };

    const onPullMove = (e) => {
        if (!pull.current || refreshing) return;

        const dy = e.clientY - pull.current.y;
        const dx = e.clientX - pull.current.x;

        // A horizontal drag is a row swipe; do not also start pulling.
        if (Math.abs(dx) > Math.abs(dy)) {
            pull.current = null;
            setPullHeight(0, true);
            return;
        }

        if (dy <= 0) return;
        setPullHeight(Math.min(dy * 0.5, PULL_MAX));

        if (dy > PULL_TRIGGER) {
            pull.current = null;
            setPullHeight(PULL_MAX);
            setRefreshing(true);
            Promise.resolve(onRefresh()).finally(() => {
                setRefreshing(false);
                setPullHeight(0, true);
            });
        }
    };

    const onPullEnd = () => {
        pull.current = null;
        if (!refreshing) setPullHeight(0, true);
    };

    // ------------------------------------------------------------ row menu wiring

    const closeRowActions = () => setRowActionsFor(null);

    const rowActions = {
        open: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            onOpen(id);
        },
        reply: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            onReply(id);
        },
        release: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            onRelease(id);
        },
        resendAi: () => {
            const row = rowActionsFor;
            closeRowActions();
            onResendAi(row);
        },
        toggleRead: () => {
            const row = rowActionsFor;
            closeRowActions();
            onToggleRead(row.id, !row.is_unread);
        },
        categorise: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            // Passed explicitly. Selecting the row first and letting onBulk read the
            // selection does not work: the state update has not applied when onBulk runs
            // in the same handler, so it saw an empty list and returned.
            onBulk('categorise', [id]);
        },
        select: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            onToggleSelect(id);
        },
        remove: () => {
            const id = rowActionsFor.id;
            closeRowActions();
            onBulk('delete', [id]);
        },
    };

    return (
        <>
            {selecting ? (
                <div
                    className="om-scroll"
                    style={{
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        padding: '8px 12px',
                        overflowX: 'auto',
                        background: 'var(--primary-selected-color)',
                        borderBottom: '1px solid var(--layout-border-color)',
                        animation: 'mFade 100ms both',
                    }}
                >
                    <span
                        style={{
                            flex: 'none',
                            font: '600 12px/16px Figtree, sans-serif',
                            color: 'var(--primary-color)',
                        }}
                    >
                        {selectedIds.length} selected
                    </span>
                    <Button kind="tertiary" size="small" onClick={() => onBulk('read')}>
                        Read
                    </Button>
                    <Button kind="tertiary" size="small" onClick={() => onBulk('unread')}>
                        Unread
                    </Button>
                    {/* Release, not send — same rule as the row menu above. */}
                    {isManager ? (
                        <Button kind="tertiary" size="small" onClick={() => onBulk('approve')}>
                            Release
                        </Button>
                    ) : null}
                    <Button kind="tertiary" size="small" onClick={() => onBulk('categorise')}>
                        Categorise
                    </Button>
                    {isManager ? (
                        <Button kind="tertiary" size="small" color="negative" onClick={() => onBulk('delete')}>
                            Delete
                        </Button>
                    ) : null}
                    <Button kind="tertiary" size="small" onClick={onClearSelection}>
                        Cancel
                    </Button>
                </div>
            ) : null}

            <div
                ref={scrollRef}
                className="om-scroll"
                style={{ flex: 1, minHeight: 0, overflowY: 'auto', paddingBottom: 12 }}
                onPointerDown={onPullStart}
                onPointerMove={onPullMove}
                onPointerUp={onPullEnd}
                onPointerCancel={onPullEnd}
            >
                <div
                    ref={pullRef}
                    style={{
                        height: 0,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        overflow: 'hidden',
                    }}
                >
                    <span
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            font: '600 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <span
                            style={{
                                width: 14,
                                height: 14,
                                borderRadius: '50%',
                                border: '2px solid var(--primary-selected-color)',
                                borderTopColor: 'var(--primary-color)',
                                animation: 'mSpin 700ms linear infinite',
                            }}
                        />
                        {refreshing ? 'Checking for new mail…' : 'Release to refresh'}
                    </span>
                </div>

                <div style={{ padding: '10px 14px 6px' }}>
                    <div style={{ font: '600 13px/18px Figtree, sans-serif' }}>{view?.label || 'Inbox'}</div>
                    <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                        {viewSubtitle(filters.view, overdueCount, slaMinutes)}
                    </div>
                </div>

                {loading && threads.length === 0 ? (
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '48px 0' }}>
                        <Loader ariaLabel="Loading threads" />
                    </div>
                ) : null}

                {!loading && threads.length === 0 ? (
                    <div style={{ padding: '32px 16px' }}>
                        <EmptyState
                            iconName="Inbox"
                            title={
                                filters.search
                                    ? 'Nothing matches that search'
                                    : filters.view === 'needsReply'
                                      ? 'Nothing waiting on a reply'
                                      : 'Nothing here'
                            }
                            description={
                                filters.search
                                    ? 'Try a client name, a subject or a project.'
                                    : filters.view === 'needsReply'
                                      ? 'Every client email has been answered inside the rule.'
                                      : 'Clear a filter, or pull down to refresh.'
                            }
                        />
                    </div>
                ) : null}

                <div
                    style={{
                        opacity: loading && threads.length ? 0.6 : 1,
                        transition: 'opacity 120ms linear',
                    }}
                >
                    {threads.map((thread) => (
                        <ThreadRow
                            key={thread.id}
                            thread={thread}
                            selecting={selecting}
                            checked={selectedIds.includes(thread.id)}
                            onOpen={() => onOpen(thread.id)}
                            onSelect={() => onToggleSelect(thread.id)}
                            onSwipeRead={() => onToggleRead(thread.id, !thread.is_unread)}
                            onSwipeActions={() => setRowActionsFor(thread)}
                        />
                    ))}
                </div>

                {meta.last_page > 1 ? (
                    <div
                        style={{
                            margin: '4px 12px 8px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 12,
                        }}
                    >
                        <Button
                            kind="secondary"
                            size="small"
                            disabled={meta.current_page <= 1}
                            onClick={() => onPage(meta.current_page - 1)}
                        >
                            Previous
                        </Button>
                        <span
                            style={{
                                font: '400 12px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            {meta.current_page} / {meta.last_page}
                        </span>
                        <Button
                            kind="secondary"
                            size="small"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => onPage(meta.current_page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                ) : null}

                <div
                    style={{
                        padding: '0 16px 8px',
                        textAlign: 'center',
                        font: '400 11px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {counts?.[filters.view] != null ? `${counts[filters.view]} in this view` : null}
                </div>
            </div>

            <RowActions thread={rowActionsFor} onClose={closeRowActions} actions={rowActions} />
        </>
    );
}
