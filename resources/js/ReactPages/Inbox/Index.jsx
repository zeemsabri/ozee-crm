/**
 * The redesigned inbox.
 *
 * Route:  GET /inbox/beta  →  App\Http\Controllers\InboxBetaController  →  'React/Inbox/Index'
 * Design: Redesign/Multi-proposal milestone submission page/Inbox.dc.html
 *
 * Runs ALONGSIDE the original Vue inbox at /inbox, which is unchanged and stays the
 * default. Both read the same tables and write the same read markers, so someone can move
 * between them mid-session; the link at the top right goes back. Because only one of Vue
 * and React boots per page load, that link is a plain <a>, never an Inertia <Link>.
 *
 * The page is a shell: it owns state and orchestration, and every piece of UI lives in
 * ReactComponents/inbox. Data comes from /api/inbox/* — see useInbox.js.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import axios from 'axios';

import { AppShell } from '../../ReactComponents/app/AppShell';
import { useToasts } from '../../ReactComponents/app/useToasts';
import { AlertBanner, Button } from '../../ReactComponents/ds';

import { FilterRail } from '../../ReactComponents/inbox/FilterRail';
import { ThreadList } from '../../ReactComponents/inbox/ThreadList';
import { ThreadView } from '../../ReactComponents/inbox/ThreadView';
import {
    CategoriseModal,
    ComposeRedirect,
    RejectModal,
    TaskModal,
} from '../../ReactComponents/inbox/modals';
import { inboxActions, useInbox, useThread } from '../../ReactComponents/inbox/useInbox';
import { plural } from '../../ReactComponents/inbox/format';

const BACK_LABELS = {
    needsReply: 'Back to needs reply',
    new: 'Back to new mail',
    withAi: 'Back to the AI queue',
    approval: 'Back to approvals',
    received: 'Back to received',
    sent: 'Back to sent',
    drafts: 'Back to drafts',
    all: 'Back to all mail',
};

export default function InboxIndex({ settings, initialThreadId }) {
    const { toasts, push, dismiss } = useToasts();

    const notify = useCallback((message) => push(message, { type: 'positive' }), [push]);
    const warn = useCallback((message) => push(message, { type: 'negative', ttl: 7000 }), [push]);

    const inbox = useInbox({ onError: warn });
    const thread = useThread({ onError: warn });
    const actions = useMemo(() => inboxActions({ onError: warn, onToast: notify }), [warn, notify]);

    const [selectedIds, setSelectedIds] = useState([]);
    const [recipients, setRecipients] = useState(null);
    const [replyOpen, setReplyOpen] = useState(false);
    // Set when the reply box is editing an EXISTING pending draft rather than composing a
    // new reply. Carries the email id so the send goes to that email, not a new one.
    const [replyEditing, setReplyEditing] = useState(null);
    const [replyBusy, setReplyBusy] = useState(false);
    const [noteOpen, setNoteOpen] = useState(false);
    const [noteText, setNoteText] = useState('');
    const [rejectFor, setRejectFor] = useState(null);
    const [taskFor, setTaskFor] = useState(null);
    const [categoriseOpen, setCategoriseOpen] = useState(false);
    const [composeOpen, setComposeOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [users, setUsers] = useState([]);

    const isManager = settings?.is_manager;
    const slaMinutes = settings?.sla_minutes || 60;

    // --------------------------------------------------------------- opening

    const openThread = useCallback(
        async (id) => {
            setReplyOpen(false);
            setReplyEditing(null);
            setNoteOpen(false);
            setNoteText('');
            setRecipients(null);
            await thread.open(id);

            const list = await actions.recipients(id);
            if (list) setRecipients(list);

            // The unread badge is now wrong for this row — the server marked it read on
            // open. Refresh so the sidebar counts and the row agree.
            inbox.refresh();
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [actions]
    );

    // Deep link: /inbox/beta?thread=123, e.g. from a notification.
    useEffect(() => {
        if (initialThreadId) openThread(initialThreadId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Assignees for the task dialog. Loaded once, and a failure is silent — the dialog
    // still works with an unassigned task, so a broken lookup should not raise an alarm.
    useEffect(() => {
        axios
            .get('/api/users')
            .then(({ data }) => {
                const rows = Array.isArray(data) ? data : data?.data || [];
                setUsers(rows.map((u) => ({ value: u.id, label: u.name })));
            })
            .catch(() => setUsers([]));
    }, []);

    const closeThread = useCallback(() => {
        thread.close();
        setReplyOpen(false);
        setReplyEditing(null);
        setNoteOpen(false);
        setRecipients(null);
        inbox.refresh();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [thread.close]);

    // --------------------------------------------------------------- actions

    const runBusy = async (fn) => {
        setBusy(true);
        try {
            return await fn();
        } finally {
            setBusy(false);
        }
    };

    /**
     * Release screened inbound mail, or clear an AI hold, across a whole thread.
     *
     * This does NOT send outbound drafts — see approveAndSend for that. The distinction
     * matters: releasing inbound mail only changes who may read something already
     * delivered, while approving an outbound draft puts a message in front of a client.
     * The list's Approve button is the former; the thread view's "Approve & send" is the
     * latter and goes through the real send path.
     */
    const releaseThread = async (id) => {
        const result = await runBusy(() => actions.bulk('approve', [id]));
        if (!result) return;

        if (result.affected === 0) {
            warn('Nothing on that thread was yours to release.');
        } else {
            notify(`${plural(result.affected, 'message')} released`);
        }

        inbox.refresh();
        if (thread.openId.current === id) thread.reload();
    };

    /**
     * Approve an outbound draft and actually send it.
     *
     * Goes straight to the existing POST /api/emails/{id}/edit-and-approve, resending the
     * draft's own subject and body unchanged. That endpoint is what the legacy inbox uses
     * to send, so an email approved here goes out through exactly the same rendering and
     * Gmail path as one approved there.
     */
    const approveAndSend = async () => {
        const approval = thread.thread?.approval;
        if (!approval) return;

        if (approval.kind === 'screening') {
            await releaseThread(thread.thread.id);
            return;
        }

        if (approval.is_template) {
            warn('That draft was built from a template — approve it on the classic inbox.');
            return;
        }

        if (!approval.subject || approval.body == null) {
            warn('That draft cannot be read from here — approve it on the classic inbox.');
            return;
        }

        const sent = await runBusy(() =>
            actions.sendCreated(approval.email_id, {
                subject: approval.subject,
                body: approval.body,
            })
        );

        if (sent) {
            notify('Approved and sent');
            closeThread();
        }
    };

    /** Open the draft in the editor so it can be changed before it goes out. */
    const openEditApprove = () => {
        const approval = thread.thread?.approval;
        if (!approval || approval.kind === 'screening') return;

        if (approval.is_template) {
            warn('That draft was built from a template — edit it on the classic inbox.');
            return;
        }

        if (!approval.subject || approval.body == null) {
            warn('That draft cannot be read from here — edit it on the classic inbox.');
            return;
        }

        setReplyEditing({
            emailId: approval.email_id,
            subject: approval.subject,
            body: approval.body,
        });
        setReplyOpen(true);
    };

    const sendReply = async (payload, { asDraft = false } = {}) => {
        const id = thread.thread?.id;
        if (!id) return;

        setReplyBusy(true);
        try {
            // Editing an existing draft: approve THAT email. Creating a second one would
            // leave the original pending and risk the client getting both.
            if (payload.emailId) {
                const sent = await actions.sendCreated(payload.emailId, {
                    subject: payload.subject,
                    body: payload.body,
                });

                if (sent) {
                    notify('Approved and sent');
                    setReplyOpen(false);
                    setReplyEditing(null);
                    closeThread();
                }
                return;
            }

            const created = await actions.reply(id, { ...payload, save_as_draft: asDraft });
            if (!created) return;

            if (asDraft) {
                notify('Saved to drafts');
            } else if (created.awaiting_ai) {
                notify('Submitted — the AI checker has it now');
            } else if (created.can_send) {
                // Two-step by design: create here, send through the existing approve
                // endpoint. See InboxReplyController.
                const sent = await actions.sendCreated(created.email_id, {
                    subject: payload.subject,
                    body: payload.body,
                });
                notify(sent ? 'Sent — the reply clock has stopped' : 'Saved, but waiting in approvals');
            } else {
                notify('Submitted for approval');
            }

            setReplyOpen(false);
            setReplyEditing(null);
            await thread.reload();
            inbox.refresh();
        } finally {
            setReplyBusy(false);
        }
    };

    const saveNote = async () => {
        const id = thread.thread?.id;
        if (!id || !noteText.trim()) return;

        const created = await runBusy(() => actions.addNote(id, noteText.trim()));
        if (!created) return;

        setNoteOpen(false);
        setNoteText('');
        thread.reload();
    };

    const runBulk = async (action) => {
        if (!selectedIds.length) return;

        if (action === 'categorise') {
            setCategoriseOpen(true);
            return;
        }

        const result = await runBusy(() => actions.bulk(action, selectedIds));
        if (!result) return;

        // Report what actually happened, not what was asked for — a selection can include
        // threads this person cannot act on, and "12 approved" when 3 were would be a lie.
        const label = {
            read: 'marked as read',
            unread: 'marked as unread',
            approve: 'approved',
            delete: 'deleted locally — Gmail copies kept',
        }[action];

        if (result.affected === 0) {
            warn(`None of the ${result.requested} selected threads could be ${label}.`);
        } else if (result.affected < result.requested) {
            notify(`${result.affected} of ${result.requested} ${label}.`);
        } else {
            notify(`${plural(result.affected, 'thread')} ${label}.`);
        }

        setSelectedIds([]);
        inbox.refresh();
    };

    const applyCategories = async (categoryIds) => {
        const result = await runBusy(() =>
            actions.bulk('categorise', selectedIds, { category_ids: categoryIds })
        );
        setCategoriseOpen(false);

        if (result) {
            notify(`Categories applied to ${plural(result.affected, 'thread')}.`);
            setSelectedIds([]);
            inbox.refresh();
        }
    };

    const createTask = async (form) => {
        const emailId =
            [...(thread.thread?.timeline || [])].reverse().find((i) => i.kind === 'message')?.id;

        if (!emailId) {
            warn('There is no message on this thread to attach a task to.');
            return;
        }

        const done = await runBusy(() => actions.createTasks(emailId, [form]));
        if (done) setTaskFor(null);
    };

    const togglePrivacy = async (message) => {
        const done = await actions.togglePrivacy(message.id, !message.is_private);
        if (done) thread.reload();
    };

    const resendToAi = async (target) => {
        // A thread-detail payload carries ai.checking.email_id; a list row carries
        // flags.ai_email_id. Both are EMAIL ids — target.id is the CONVERSATION id and
        // must never be used here, or the request acts on an unrelated email.
        const emailId = target?.ai?.checking?.email_id ?? target?.flags?.ai_email_id;

        if (!emailId) {
            warn('There is nothing with the checker on that thread.');
            return;
        }

        const done = await actions.resendToAi(emailId);
        if (done) {
            inbox.refresh();
            if (thread.thread) thread.reload();
        }
    };

    const regenerateDraft = async () => {
        const inbound = [...(thread.thread?.timeline || [])]
            .reverse()
            .find((i) => i.kind === 'message' && i.direction === 'in');

        if (!inbound) return;

        const done = await actions.requestDraft(inbound.id);
        // The draft is written by a queued job, so give it a moment then re-read rather
        // than holding the request open for a model round trip.
        if (done) setTimeout(() => thread.reload(), 4000);
    };

    const onMore = async (value) => {
        const id = thread.thread?.id;
        if (!id) return;

        if (value === 'unread') {
            await actions.markRead(id, true);
            closeThread();
            return;
        }

        if (value === 'print') window.print();
    };

    const deleteThread = async () => {
        const id = thread.thread?.id;
        if (!id) return;

        const done = await runBusy(() => actions.deleteThread(id));
        if (done) closeThread();
    };

    // --------------------------------------------------------------- render

    const toggleSelect = (id) =>
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((c) => c !== id) : [...current, id]
        );

    const selectAll = () =>
        setSelectedIds((current) =>
            current.length === inbox.threads.length ? [] : inbox.threads.map((t) => t.id)
        );

    const railBadges = inbox.overdueCount ? { inbox: String(inbox.overdueCount) } : undefined;

    const headerExtra = (
        <>
            {/* Plain anchor: crossing back to the Vue page needs a full reload. */}
            <a href={settings.classic_url} style={{ textDecoration: 'none' }}>
                <Button kind="tertiary" size="small">
                    Classic inbox
                </Button>
            </a>
        </>
    );

    return (
        <AppShell
            title="Inbox"
            activeKey="inbox"
            railBadges={railBadges}
            headerExtra={headerExtra}
            toasts={toasts}
            onDismissToast={dismiss}
            showFooter={false}
            headerSearch={{
                value: inbox.filters.search,
                placeholder: 'Search mail, projects, clients',
                onChange: (e) => inbox.setFilters({ search: e.target.value }),
                onClear: () => inbox.setFilters({ search: '' }),
            }}
        >
            {inbox.overdueCount > 0 && !inbox.filters.overdue_only ? (
                <div style={{ flex: 'none', animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both' }}>
                    <AlertBanner
                        type="negative"
                        action={{
                            text: 'Show them',
                            onClick: () => {
                                thread.close();
                                inbox.setFilters({ view: 'needsReply', overdue_only: true });
                            },
                        }}
                    >
                        {plural(inbox.overdueCount, 'client thread')}{' '}
                        {inbox.overdueCount === 1 ? 'has' : 'have'} passed the reply rule
                    </AlertBanner>
                </div>
            ) : null}

            <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
                <FilterRail
                    filters={inbox.filters}
                    counts={inbox.counts}
                    overdueCount={inbox.overdueCount}
                    options={inbox.options}
                    hasFilters={inbox.hasFilters}
                    onView={(view) => {
                        thread.close();
                        setSelectedIds([]);
                        inbox.setView(view);
                    }}
                    onChange={(patch) => {
                        setSelectedIds([]);
                        inbox.setFilters(patch);
                    }}
                    onClear={inbox.clearFilters}
                />

                <main style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
                    {thread.thread || thread.loading ? (
                        <ThreadView
                            thread={thread.thread}
                            loading={thread.loading}
                            recipients={recipients}
                            replyOpen={replyOpen}
                            replyBusy={replyBusy}
                            noteOpen={noteOpen}
                            noteText={noteText}
                            isManager={isManager}
                            backLabel={BACK_LABELS[inbox.filters.view] || 'Back'}
                            onBack={closeThread}
                            onOpenReply={() => setReplyOpen(true)}
                            replyEditing={replyEditing}
                            onCloseReply={() => {
                                setReplyOpen(false);
                                setReplyEditing(null);
                            }}
                            onSendReply={(payload) => sendReply(payload)}
                            onSaveDraft={(payload) => sendReply(payload, { asDraft: true })}
                            onRegenerateDraft={regenerateDraft}
                            onOpenNote={() => setNoteOpen(true)}
                            onNoteText={setNoteText}
                            onSaveNote={saveNote}
                            onCancelNote={() => {
                                setNoteOpen(false);
                                setNoteText('');
                            }}
                            onApprove={approveAndSend}
                            onEditApprove={openEditApprove}
                            onReject={() => setRejectFor(thread.thread?.approval?.email_id)}
                            onResendAi={() => resendToAi(thread.thread)}
                            onDelete={deleteThread}
                            onMore={onMore}
                            onTogglePrivacy={togglePrivacy}
                            onCreateTask={(suggestion) => setTaskFor(suggestion || {})}
                        />
                    ) : (
                        <ThreadList
                            threads={inbox.threads}
                            meta={inbox.meta}
                            loading={inbox.loading}
                            filters={inbox.filters}
                            overdueCount={inbox.overdueCount}
                            slaMinutes={slaMinutes}
                            selectedIds={selectedIds}
                            isManager={isManager}
                            onToggleSelect={toggleSelect}
                            onSelectAll={selectAll}
                            onSort={(sort) => inbox.setFilters({ sort })}
                            onOpen={openThread}
                            onReply={async (id) => {
                                await openThread(id);
                                setReplyOpen(true);
                            }}
                            onRelease={releaseThread}
                            onResendAi={resendToAi}
                            onBulk={runBulk}
                            onCompose={() => setComposeOpen(true)}
                            onPage={inbox.setPage}
                        />
                    )}
                </main>
            </div>

            <RejectModal
                open={!!rejectFor}
                busy={busy}
                onClose={() => setRejectFor(null)}
                onConfirm={async (reason) => {
                    const done = await runBusy(() => actions.reject(rejectFor, reason));
                    setRejectFor(null);
                    if (done) {
                        closeThread();
                    }
                }}
            />

            <TaskModal
                open={!!taskFor}
                busy={busy}
                suggestion={taskFor}
                users={users}
                onClose={() => setTaskFor(null)}
                onCreate={createTask}
            />

            <CategoriseModal
                open={categoriseOpen}
                busy={busy}
                count={selectedIds.length}
                categories={inbox.options.categories || []}
                onClose={() => setCategoriseOpen(false)}
                onApply={applyCategories}
            />

            <ComposeRedirect
                open={composeOpen}
                classicUrl={settings.classic_url}
                onClose={() => setComposeOpen(false)}
            />
        </AppShell>
    );
}
