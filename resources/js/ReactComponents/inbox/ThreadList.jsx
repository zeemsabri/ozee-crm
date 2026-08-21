/**
 * The thread list: header, bulk bar, and one card per conversation.
 *
 * Design source: Inbox.dc.html, the sc-if="isListView" block.
 *
 * The substantive difference from the mock: a row here is a CONVERSATION, not an email.
 * The mock's rows carry an email's status; these carry the thread's aggregate — which is
 * why a card can say "Needs a manager" and "3 messages" at once.
 */

import { Avatar, Button, Checkbox, Chips, EmptyState, Icon, Label, Loader } from '../ds';
import { EmailNumber } from './EmailNumber';
import {
    categoryColour,
    initials,
    plural,
    replyStatus,
    exactTime,
    shortTime,
    VIEW_DEFS,
    viewSubtitle,
} from './format';

function StatusPill({ status }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '2px 8px',
                borderRadius: 12,
                background: status.bg,
                color: status.fg,
                font: '600 12px/16px Figtree, sans-serif',
                whiteSpace: 'nowrap',
            }}
        >
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: status.dot }} />
            <span>{status.text}</span>
        </span>
    );
}

function MetaBit({ icon, children, color }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 4,
                font: '400 12px/16px Figtree, sans-serif',
                color: color || 'var(--secondary-text-color)',
            }}
        >
            <Icon name={icon} size={14} color="currentColor" />
            <span>{children}</span>
        </span>
    );
}

function ThreadCard({ thread, checked, onCheck, onOpen, onReply, onRelease, onResendAi }) {
    const status = replyStatus(thread);
    const unread = thread.is_unread;
    const inbound = thread.direction === 'in';
    const can = thread.can || {};
    const flags = thread.flags || {};

    return (
        <article
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 8,
                display: 'flex',
                overflow: 'hidden',
                animation: 'dcRise 300ms cubic-bezier(0,0,.35,1) both',
                transition:
                    'box-shadow 150ms cubic-bezier(.4,0,.2,1), border-color 150ms cubic-bezier(.4,0,.2,1), transform 150ms cubic-bezier(.4,0,.2,1)',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.boxShadow = '0 4px 10px rgba(0,0,0,.07)';
                e.currentTarget.style.borderColor = 'var(--ui-border-color)';
                e.currentTarget.style.transform = 'translateY(-1px)';
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.boxShadow = 'none';
                e.currentTarget.style.borderColor = 'var(--layout-border-color)';
                e.currentTarget.style.transform = 'none';
            }}
        >
            <div style={{ width: 3, flex: 'none', background: status.dot }} />

            <div style={{ padding: '14px 12px 14px 14px', flex: 'none', display: 'flex', alignItems: 'flex-start' }}>
                <Checkbox
                    checked={checked}
                    onChange={onCheck}
                    ariaLabel={`Select thread: ${thread.subject}`}
                />
            </div>

            <div style={{ flex: 1, minWidth: 0, padding: '14px 16px 14px 0', display: 'flex', gap: 12 }}>
                <Avatar
                    text={initials(thread.who)}
                    size="medium"
                    backgroundColor={inbound ? 'var(--primary-color)' : 'var(--color-explosive)'}
                />

                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <button
                            type="button"
                            onClick={onOpen}
                            style={{
                                border: 'none',
                                background: 'transparent',
                                padding: 0,
                                cursor: 'pointer',
                                textAlign: 'start',
                                color: 'inherit',
                                font: `${unread ? 700 : 600} 14px/20px Figtree, sans-serif`,
                                maxWidth: 220,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {thread.who}
                        </button>

                        <Label
                            text={inbound ? 'Inbound' : flags.is_draft ? 'Draft' : 'Outbound'}
                            kind="line"
                            color={inbound ? 'primary' : 'dark'}
                            size="small"
                        />

                        {flags.pending_approval || flags.screening || flags.with_ai ? (
                            <Label
                                text={
                                    flags.screening
                                        ? 'Screening'
                                        : flags.with_ai
                                          ? 'With AI'
                                          : 'Needs approval'
                                }
                                color="var(--color-working-orange)"
                                size="small"
                            />
                        ) : null}

                        <span
                            style={{
                                marginInlineStart: 'auto',
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 8,
                            }}
                        >
                            <StatusPill status={status} />
                            <span
                                // The compact form drops whatever is inferable — no year
                                // for this year, a weekday inside the last week. Hovering
                                // gives the unambiguous one.
                                title={exactTime(thread.last_message_at)}
                                style={{
                                    font: '400 12px/16px Figtree, sans-serif',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                {shortTime(thread.last_message_at)}
                            </span>
                        </span>
                    </div>

                    <button
                        type="button"
                        onClick={onOpen}
                        style={{
                            display: 'block',
                            width: '100%',
                            border: 'none',
                            background: 'transparent',
                            padding: 0,
                            marginTop: 4,
                            cursor: 'pointer',
                            textAlign: 'start',
                            color: 'inherit',
                            font: `${unread ? 700 : 400} 16px/22px Figtree, sans-serif`,
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {thread.subject}
                    </button>

                    <div
                        onClick={onOpen}
                        style={{
                            marginTop: 4,
                            cursor: 'pointer',
                            font: '400 14px/20px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            display: '-webkit-box',
                            WebkitLineClamp: 2,
                            WebkitBoxOrient: 'vertical',
                            overflow: 'hidden',
                            textWrap: 'pretty',
                        }}
                    >
                        {thread.preview}
                    </div>

                    <div
                        style={{
                            marginTop: 10,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            flexWrap: 'wrap',
                        }}
                    >
                        <Label text={thread.project?.short || 'Lead'} kind="line" color="dark" size="small" />

                        {/*
                          The newest message's number, not the thread's — a conversation
                          has none. Muted so it reads as a reference, not a status.
                        */}
                        <EmailNumber number={thread.number} muted />

                        {(thread.categories || []).map((c) => (
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
                            <MetaBit icon="Note">{plural(thread.message_count, 'message')}</MetaBit>
                        ) : null}

                        {flags.has_private ? <MetaBit icon="Hide">Has a private message</MetaBit> : null}

                        {thread.last_receipt ? (
                            <MetaBit
                                icon={thread.last_receipt.opened ? 'Show' : 'Hide'}
                                color={
                                    thread.last_receipt.opened
                                        ? 'var(--positive-color)'
                                        : 'var(--secondary-text-color)'
                                }
                            >
                                {thread.last_receipt.opened ? 'Opened' : 'Not opened yet'}
                            </MetaBit>
                        ) : null}

                        <span
                            style={{ marginInlineStart: 'auto', display: 'flex', alignItems: 'center', gap: 4 }}
                        >
                            {can.reply ? (
                                <Button kind="secondary" size="small" leftIcon={<Icon name="Reply" size={16} />} onClick={onReply}>
                                    Reply
                                </Button>
                            ) : null}
                            {/*
                              Release only. Approving an OUTBOUND draft means sending to a
                              client, and nobody should do that from a list where the body
                              is a two-line preview — that button lives in the thread view,
                              next to the text it will send.
                            */}
                            {can.release ? (
                                <Button size="small" onClick={onRelease}>
                                    {flags.screening ? 'Release' : 'Clear AI hold'}
                                </Button>
                            ) : can.approve && flags.pending_approval ? (
                                <Button kind="secondary" size="small" onClick={onOpen}>
                                    Review draft
                                </Button>
                            ) : null}
                            {can.resend_to_ai ? (
                                <Button
                                    kind={flags.ai_stalled ? 'primary' : 'secondary'}
                                    size="small"
                                    onClick={onResendAi}
                                >
                                    Send to AI again
                                </Button>
                            ) : null}
                        </span>
                    </div>
                </div>
            </div>
        </article>
    );
}

export function ThreadList({
    threads,
    meta,
    loading,
    filters,
    overdueCount,
    slaMinutes,
    selectedIds,
    isManager,
    onToggleSelect,
    onSelectAll,
    onSort,
    onOpen,
    onReply,
    onRelease,
    onResendAi,
    onBulk,
    onCompose,
    canCompose = true,
    onPage,
}) {
    const view = VIEW_DEFS.find((v) => v.key === filters.view);
    const allChecked = threads.length > 0 && selectedIds.length === threads.length;
    const someChecked = selectedIds.length > 0 && selectedIds.length < threads.length;

    return (
        <div style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column' }}>
            <div
                style={{
                    flex: 'none',
                    padding: '16px 24px 12px',
                    background: 'var(--primary-background-color)',
                    borderBottom: '1px solid var(--layout-border-color)',
                }}
            >
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 16, flexWrap: 'wrap' }}>
                    <div style={{ flex: 1, minWidth: 200 }}>
                        <h1 style={{ margin: 0, font: '600 24px/30px Poppins, sans-serif', letterSpacing: '-0.1px' }}>
                            {view?.label || 'Inbox'}
                        </h1>
                        <div
                            style={{
                                marginTop: 2,
                                font: '400 14px/20px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            {viewSubtitle(filters.view, overdueCount, slaMinutes)}
                        </div>
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        {/*
                          Two orders, and the labels now say which way each one runs.

                          Every view except "Needs reply" defaults to Newest first. The
                          queue ordering is oldest-first by design — longest-waiting client
                          at the top — and having it as the silent default everywhere is
                          what made the whole inbox look like it was showing ancient mail.
                        */}
                        <Button
                            kind={filters.sort === 'breach' ? 'primary' : 'secondary'}
                            size="small"
                            title="Unanswered first, longest wait at the top"
                            onClick={() => onSort('breach')}
                        >
                            Longest waiting
                        </Button>
                        <Button
                            kind={filters.sort === 'date' ? 'primary' : 'secondary'}
                            size="small"
                            title="Most recent activity first"
                            onClick={() => onSort('date')}
                        >
                            Newest first
                        </Button>
                        {/* Hidden outright rather than opening a dialog whose only
                            content is a permission error. */}
                        {canCompose ? (
                            <Button size="small" leftIcon={<Icon name="Add" size={16} />} onClick={onCompose}>
                                New email
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div
                    style={{
                        marginTop: 12,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        minHeight: 32,
                        flexWrap: 'wrap',
                    }}
                >
                    <Checkbox
                        label={`${meta.total ?? threads.length} in this view`}
                        checked={allChecked}
                        indeterminate={someChecked}
                        onChange={onSelectAll}
                    />

                    {selectedIds.length > 0 ? (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '4px 8px',
                                borderRadius: 4,
                                background: 'var(--primary-selected-color)',
                                animation: 'dcFade 100ms cubic-bezier(0,0,.35,1) both',
                                flexWrap: 'wrap',
                            }}
                        >
                            <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--primary-color)' }}>
                                {selectedIds.length} selected
                            </span>
                            <Button kind="tertiary" size="small" onClick={() => onBulk('read')}>
                                Mark as read
                            </Button>
                            <Button kind="tertiary" size="small" onClick={() => onBulk('unread')}>
                                Mark as unread
                            </Button>
                            {/* Release, not send — see the per-row button above. */}
                            {isManager ? (
                                <Button kind="tertiary" size="small" onClick={() => onBulk('approve')}>
                                    Release held mail
                                </Button>
                            ) : null}
                            <Button
                                kind="tertiary"
                                size="small"
                                leftIcon={<Icon name="Tags" size={16} />}
                                onClick={() => onBulk('categorise')}
                            >
                                Categorise
                            </Button>
                            {isManager ? (
                                <Button
                                    kind="tertiary"
                                    size="small"
                                    color="negative"
                                    leftIcon={<Icon name="Delete" size={16} />}
                                    onClick={() => onBulk('delete')}
                                >
                                    Delete
                                </Button>
                            ) : null}
                        </div>
                    ) : null}

                    {filters.sort === 'breach' ? (
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
                            <Icon name="Sort" size={14} color="var(--secondary-text-color)" />
                            <span>Closest to breaching the reply rule first</span>
                        </span>
                    ) : null}
                </div>
            </div>

            <div style={{ flex: 1, minHeight: 0, overflow: 'auto', padding: '16px 24px 32px' }}>
                {loading && threads.length === 0 ? (
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '64px 0' }}>
                        <Loader ariaLabel="Loading threads" />
                    </div>
                ) : null}

                {!loading && threads.length === 0 ? (
                    <div style={{ padding: '64px 0' }}>
                        <EmptyState
                            iconName="Inbox"
                            title={filters.view === 'needsReply' ? 'Nothing waiting on a reply' : 'Nothing here'}
                            description={
                                filters.view === 'needsReply'
                                    ? 'Every client email has been answered inside the rule.'
                                    : 'Try a different view or clear your filters.'
                            }
                        />
                    </div>
                ) : null}

                <div
                    style={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 8,
                        opacity: loading && threads.length ? 0.6 : 1,
                        transition: 'opacity 120ms linear',
                    }}
                >
                    {threads.map((thread) => (
                        <ThreadCard
                            key={thread.id}
                            thread={thread}
                            checked={selectedIds.includes(thread.id)}
                            onCheck={() => onToggleSelect(thread.id)}
                            onOpen={() => onOpen(thread.id)}
                            onReply={() => onReply(thread.id)}
                            onRelease={() => onRelease(thread.id)}
                            onResendAi={() => onResendAi(thread)}
                        />
                    ))}
                </div>

                {meta.last_page > 1 ? (
                    <div
                        style={{
                            marginTop: 20,
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
                            Page {meta.current_page} of {meta.last_page}
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
            </div>
        </div>
    );
}
