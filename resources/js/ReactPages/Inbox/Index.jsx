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
    RejectModal,
    TaskModal,
} from '../../ReactComponents/inbox/modals';
import { ComposeModal } from '../../ReactComponents/inbox/ComposeModal';
import { inboxActions, useInbox, useThread } from '../../ReactComponents/inbox/useInbox';
import { useTemplatePreview, useTemplates } from '../../ReactComponents/inbox/useTemplates';
import { longTime, plural } from '../../ReactComponents/inbox/format';

/** Short label for "which message am I answering", e.g. "Today, 9:18 am". */
const shortWhen = (iso) => longTime(iso);

const BACK_LABELS = {
    needsReply: 'Back to needs reply',
    new: 'Back to new mail',
    withAi: 'Back to the AI queue',
    approval: 'Back to approvals',
    received: 'Back to received',
    sent: 'Back to sent',
    drafts: 'Back to in review',
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
    // Which message a new reply answers, when it was started from a specific message's
    // Reply button rather than the composer at the bottom. Null means "the newest inbound
    // message", which is what the server falls back to.
    const [replyTarget, setReplyTarget] = useState(null);
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
    const canComposeTemplate = settings?.can_compose_template ?? false;
    const canComposeCustom = settings?.can_compose_custom ?? false;

    // Templates load once for the session, but only for someone who can actually use
    // them — no point fetching the list for a user who only ever composes free-form.
    const templates = useTemplates({ enabled: canComposeTemplate, onError: warn });

    const preview = useTemplatePreview({
        projectId: thread.thread?.project?.id,
        clientId: thread.thread?.preview_client_id,
        onError: warn,
    });

    const compose = useMemo(
        () => ({
            canTemplate: canComposeTemplate,
            canCustom: canComposeCustom,
            templates: templates.templates,
            templateOptions: templates.options,
            sourceData: templates.sourceData,
            loadSourceData: templates.loadSourceData,
            loading: templates.loading,
            preview,
            // For the block builder's preview pane only.
            signOff: settings?.sign_off,
        }),
        [canComposeTemplate, canComposeCustom, templates, preview, settings?.sign_off]
    );

    // --------------------------------------------------------------- opening

    const openThread = useCallback(
        async (id) => {
            setReplyOpen(false);
            setReplyEditing(null);
            setReplyTarget(null);
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
        setReplyTarget(null);
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

        if (!approval.subject || (approval.body == null && !approval.is_blocks)) {
            warn('That draft cannot be read from here — approve it on the classic inbox.');
            return;
        }

        const sent = await runBusy(() =>
            actions.sendCreated(approval.email_id, {
                subject: approval.subject,
                // A block email's content is rebuilt server-side from its stored blocks —
                // see BlockComposition. What is on screen here is the preview render, with
                // signed image URLs in it that must not be mailed to anyone, so it is
                // deliberately not posted back.
                body: approval.is_blocks ? undefined : approval.body,
                compositionType: approval.is_blocks ? 'blocks' : 'custom',
            })
        );

        if (sent) {
            notify('Approved and sent');
            closeThread();
        }
    };

    /**
     * Reply from a specific message in the thread.
     *
     * The message becomes the reply's parent, so In-Reply-To and References point at what
     * is actually being answered. The composer at the bottom leaves the target null and
     * the server falls back to the newest inbound message — which is the right default,
     * but wrong when a client asked two questions and you are answering the earlier one.
     */
    const replyToMessage = (message, mode = 'reply') => {
        setReplyEditing(null);
        setReplyTarget((current) => ({
            emailId: message.id,
            mode,
            author: message.author,
            when: shortWhen(message.created_at),
            // Changes on every click, including a repeat click on the same message in the
            // same mode. ReplyBox keys its mode-sync effect on this so pressing Reply
            // always resets the composer — see the note there.
            token: (current?.token ?? 0) + 1,
        }));
        setReplyOpen(true);
    };

    /** Open the draft in the editor so it can be changed before it goes out. */
    const openEditApprove = () => {
        const approval = thread.thread?.approval;
        if (!approval || approval.kind === 'screening') return;

        if (approval.is_template) {
            warn('That draft was built from a template — edit it on the classic inbox.');
            return;
        }

        if (approval.is_blocks) {
            // The editor here is a plain textarea over the body, and a block email's body
            // is regenerated from its blocks at send time — so anything typed into it
            // would be discarded without a word. Approve it or send it back instead.
            warn('This update was built in blocks, so it cannot be edited as text. Approve it, or send it back with a note.');
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

    /**
     * Submit a reply, or approve one that is already waiting.
     *
     * These are the two ends of the same pipeline and the branch below is the whole
     * difference between them:
     *
     *  - `payload.emailId` set — an existing email at pending_approval, which the AI
     *    refused or which was never AI-checked. A human with approval rights is deciding.
     *    That goes to edit-and-approve, the same endpoint the classic inbox approves
     *    through, and it sends immediately.
     *
     *  - no emailId — a new reply. One request. The server writes it as a draft and the
     *    automation workflow takes it from there: AI review, then either straight out to
     *    the client or parked at pending_approval for someone to approve by hand.
     *
     * There is no longer a create-then-approve chain for new replies. It existed, it
     * worked, and it was the one route in the product that put mail in front of a client
     * without the AI having seen it. See InboxReplyController.
     */
    const sendReply = async (payload) => {
        const id = thread.thread?.id;
        if (!id) return;

        setReplyBusy(true);
        try {
            // Editing an email that is already awaiting approval: approve THAT one.
            // Creating a second would leave the original pending and risk the client
            // getting both.
            if (payload.emailId) {
                const sent = await actions.sendCreated(payload.emailId, {
                    subject: payload.subject,
                    body: payload.body,
                    compositionType: payload.composition_type,
                    templateId: payload.template_id,
                    templateData: payload.template_data,
                });

                if (sent) {
                    notify('Approved and sent');
                    setReplyOpen(false);
                    setReplyEditing(null);
                    closeThread();
                }
                return;
            }

            const created = await actions.reply(id, payload);
            if (!created) return;

            notify('Submitted for review — it goes out once it is approved');

            setReplyOpen(false);
            setReplyEditing(null);
            setReplyTarget(null);
            preview.reset();
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
                            // Prefer the per-thread answer from /recipients; fall back to
                            // the page settings until it has loaded, so the Forward button
                            // never flashes in for someone who may not use it.
                            canAddressManually={
                                recipients?.can_address_manually ?? settings?.can_address_manually ?? false
                            }
                            backLabel={BACK_LABELS[inbox.filters.view] || 'Back'}
                            onBack={closeThread}
                            onOpenReply={() => {
                                setReplyTarget(null); // falls back to the newest inbound message
                                setReplyOpen(true);
                            }}
                            onReplyToMessage={replyToMessage}
                            replyTarget={replyTarget}
                            replyEditing={replyEditing}
                            compose={compose}
                            onCloseReply={() => {
                                setReplyOpen(false);
                                setReplyEditing(null);
                                setReplyTarget(null);
                            }}
                            onSendReply={(payload) => sendReply(payload)}
                            onError={warn}
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
                                setReplyTarget(null); // newest inbound message
                                setReplyOpen(true);
                            }}
                            onRelease={releaseThread}
                            onResendAi={resendToAi}
                            onBulk={runBulk}
                            canCompose={canComposeTemplate || canComposeCustom}
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

            {/*
              Mounted only while open. Resetting state in an effect instead painted one
              frame of the PREVIOUS email — recipients, template and the rendered preview
              iframe — before blanking, which flashes another client's correspondence.
            */}
            {composeOpen ? (
            <ComposeModal
                open
                compose={compose}
                projects={inbox.options.compose_projects}
                classicUrl={settings.classic_url}
                onError={warn}
                onCreated={(message) => {
                    notify(message);
                    // The new draft belongs in the Drafts view; refresh so the badge and
                    // the list agree with what was just created.
                    inbox.refresh();
                }}
                onClose={() => setComposeOpen(false)}
            />
            ) : null}
        </AppShell>
    );
}
