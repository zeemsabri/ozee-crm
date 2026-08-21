/**
 * Everything the inbox page DOES, with nothing about how it looks.
 *
 * Extracted from ReactPages/Inbox/Index.jsx when the phone layout arrived. The reason is
 * the whole point of this file: the desktop tree and the mobile tree are two different
 * arrangements of ONE set of rules — who may approve, what a reply posts, which endpoint
 * sends — and the fastest way to get those wrong is to write them twice. A phone layout
 * that resolved its own permissions would drift from the desktop the first time either
 * changed, and the drift would be invisible until a contractor sent a client something
 * they should not have.
 *
 * So: this hook owns state and orchestration. DesktopInbox.jsx and mobile/MobileInbox.jsx
 * own layout, and neither one may talk to /api/inbox directly.
 *
 * Data comes from useInbox.js. Dialogs shared by both layouts are in InboxDialogs.jsx.
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';

import { useToasts } from '../app/useToasts';
import { defaultSortFor, inboxActions, useInbox, useThread } from './useInbox';
import { useTemplatePreview, useTemplates } from './useTemplates';
import { longTime, plural } from './format';

/** Short label for "which message am I answering", e.g. "Today, 9:18 am". */
const shortWhen = (iso) => longTime(iso);

/**
 * How often an open thread re-checks itself while something on it is still moving.
 *
 * One minute, matching how the automation actually behaves: the workflow is queued, the AI
 * call takes seconds, and the scheduler runs on a minute tick — so a faster poll would
 * mostly return the same answer, and a slower one would leave people staring at "In
 * review" after it had already gone out.
 */
const POLL_MS = 60_000;

/**
 * The faster tick, used only while an AI draft is being written.
 *
 * A minute is right for the approval workflow — queued job, scheduler on a minute tick,
 * nobody staring at it. It is wrong for "Draft for me", where somebody pressed a button
 * and is watching the box: a model call finishes in a few seconds and then they wait up to
 * another 55 for the page to notice. This window is short and self-closing, so the extra
 * requests are bounded by how long the draft takes.
 */
const DRAFT_POLL_MS = 5_000;

export function useInboxPage({ settings, initialThreadId }) {
    const { toasts, push, dismiss } = useToasts();

    const notify = useCallback((message) => push(message, { type: 'positive' }), [push]);
    const warn = useCallback((message) => push(message, { type: 'negative', ttl: 7000 }), [push]);

    const inbox = useInbox({ onError: warn });
    const thread = useThread({ onError: warn });
    const actions = useMemo(() => inboxActions({ onError: warn, onToast: notify }), [warn, notify]);

    const [selectedIds, setSelectedIds] = useState([]);
    const [recipients, setRecipients] = useState(null);
    const [refreshing, setRefreshing] = useState(false);
    const [summarising, setSummarising] = useState(false);
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
    /**
     * An explicit target list for a bulk action, when it did not come from the selection.
     *
     * The phone's per-row menu runs the same bulk endpoints against a single thread. It
     * used to do that by selecting the row first and then calling runBulk — which read
     * `selectedIds` from the render it was created in, saw the pre-selection value, and
     * returned at the `!length` guard. Categorise and Delete did nothing at all, and the
     * two-tap delete confirm made that look like a successful delete.
     */
    const [bulkIds, setBulkIds] = useState(null);
    const [composeOpen, setComposeOpen] = useState(false);
    /**
     * The message whose full branded document is on screen.
     *
     * Holds the message, not just its id, so the dialog can title itself without
     * hunting back through the timeline for a row the caller already had.
     */
    const [clientViewFor, setClientViewFor] = useState(null);
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

    /**
     * Keep an open thread current while anything on it is still moving.
     *
     * Submitting a reply hands it to the automation: the workflow runs the AI check and
     * then either sends it or parks it for a human. None of that produces a page event, so
     * without this the thread sits on "In review" until someone navigates away and back.
     *
     * Three things make this cheap rather than a permanent background poll:
     *  - It only runs while `thread.in_flight` is true. Once everything is sent, rejected
     *    or received, the server says so and the interval is torn down.
     *  - It pauses when the tab is hidden. A backgrounded tab polling a Laravel route once
     *    a minute all afternoon is pure waste, and browsers throttle the timer anyway, so
     *    the behaviour would be unpredictable as well as wasteful.
     *  - It reloads only the open thread, never the list.
     *
     * `silent` so the reload does not flip the thread into its loading skeleton every
     * minute — the content would blank out under whoever is reading it.
     */
    const inFlight = !!thread.thread?.in_flight;
    const openThreadId = thread.thread?.id;
    // Someone is watching this one, so check more often. Falls back to the slow tick the
    // moment the draft lands or fails.
    const drafting = !!thread.thread?.ai?.drafting || !!thread.thread?.ai?.summarising;
    const pollMs = drafting ? DRAFT_POLL_MS : POLL_MS;

    useEffect(() => {
        if (!inFlight || !openThreadId) return undefined;

        let timer = null;

        const stop = () => {
            if (timer) clearInterval(timer);
            timer = null;
        };

        const start = () => {
            if (timer) return;
            timer = setInterval(() => thread.reload({ silent: true }), pollMs);
        };

        const onVisibility = () => {
            if (document.hidden) {
                stop();
            } else {
                // Catch up immediately on return — a minute of a hidden tab usually means
                // the answer is already waiting.
                thread.reload({ silent: true });
                start();
            }
        };

        if (!document.hidden) start();
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            stop();
            document.removeEventListener('visibilitychange', onVisibility);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [inFlight, openThreadId, pollMs]);

    /*
     * Tell the person when a requested draft arrives, or when it is not coming.
     *
     * The poll updates the thread silently, which is right — the content must not blank
     * out under someone reading it — but silent means a draft can appear in a collapsed
     * composer with nothing to mark the moment. This watches the transition rather than
     * the value, so it fires once per request and not on every reload.
     */
    const wasDrafting = useRef(false);

    useEffect(() => {
        const ai = thread.thread?.ai;

        if (!ai) return;

        if (ai.drafting) {
            wasDrafting.current = true;
            return;
        }

        if (!wasDrafting.current) return;
        wasDrafting.current = false;

        if (ai.draft) {
            notify('Draft ready — edit it before it goes out');
            setReplyOpen(true);
        } else if (ai.draft_failed) {
            warn('The AI could not write a draft for this one. Write it yourself, or try again.');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [thread.thread?.ai?.drafting, thread.thread?.ai?.draft, thread.thread?.ai?.draft_failed]);

    /**
     * Ask for a thread summary.
     *
     * Opening a thread no longer does this on its own. The reload immediately after picks
     * up the queued state so the panel switches to "Reading the thread…" now, and from
     * there `ai.summarising` keeps the fast poll running until it lands.
     */
    const summariseThread = async () => {
        const id = thread.thread?.id;
        if (!id) return;

        setSummarising(true);
        try {
            const result = await actions.summarise(id);

            if (result?.message) notify(result.message);
            if (result) await thread.reload({ silent: true });
        } finally {
            setSummarising(false);
        }
    };

    /** The header's refresh button. Same reload, but visible. */
    const refreshThread = useCallback(async () => {
        if (!thread.openId.current) return;
        setRefreshing(true);
        try {
            await thread.reload({ silent: true });
            inbox.refresh();
        } finally {
            setRefreshing(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [thread.reload, inbox.refresh]);

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

    /**
     * Move to the neighbouring thread in the current list.
     *
     * The phone's thread screen has up/down chevrons and a horizontal swipe, both of which
     * mean "the next one in the list I came from" — so this walks `inbox.threads`, the
     * exact rows on screen behind it, rather than re-deriving an order. It deliberately
     * does not page: running off the end says so instead of silently fetching page 2,
     * because a chevron that sometimes costs a network round trip and sometimes does not
     * is worse than one that admits where the list stops.
     */
    const stepThread = useCallback(
        async (direction) => {
            const list = inbox.threads || [];
            const index = list.findIndex((t) => t.id === thread.thread?.id);

            // Opened from a deep link, or the list has moved on under it. Saying so beats
            // a chevron that silently does nothing.
            if (index < 0) {
                notify('This thread is not in the list behind it — go back to step through.');
                return;
            }

            const next = list[index + direction];
            if (!next) {
                notify(direction > 0 ? 'That is the last one in this view' : 'That is the first one');
                return;
            }

            await openThread(next.id);
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [inbox.threads, thread.thread?.id, openThread, notify]
    );

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

    /**
     * Run a bulk action over `ids`, or over the current selection when none is given.
     *
     * Categorise is the odd one: it needs a dialog first, so the targets are parked in
     * `bulkIds` for applyCategories to pick up rather than being resolved twice.
     */
    const runBulk = async (action, ids) => {
        const targets = ids?.length ? ids : selectedIds;
        if (!targets.length) return;

        if (action === 'categorise') {
            setBulkIds(ids?.length ? ids : null);
            setCategoriseOpen(true);
            return;
        }

        const result = await runBusy(() => actions.bulk(action, targets));
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
            warn(`None of the ${result.requested} threads could be ${label}.`);
        } else if (result.affected < result.requested) {
            notify(`${result.affected} of ${result.requested} ${label}.`);
        } else {
            notify(`${plural(result.affected, 'thread')} ${label}.`);
        }

        setSelectedIds([]);
        setBulkIds(null);
        inbox.refresh();
    };

    /**
     * Flip one row's read state, for the phone's swipe-right gesture.
     *
     * Goes through the same /read endpoint the desktop's bulk bar uses. `unread` is the
     * state being MOVED TO, so the caller passes the opposite of what the row shows.
     */
    const toggleRead = async (id, unread) => {
        const done = await actions.markRead(id, unread);
        if (!done) return;
        if (!unread) notify('Marked as read');
        inbox.refresh();
    };

    const applyCategories = async (categoryIds) => {
        const targets = bulkIds?.length ? bulkIds : selectedIds;

        const result = await runBusy(() =>
            actions.bulk('categorise', targets, { category_ids: categoryIds })
        );
        setCategoriseOpen(false);
        setBulkIds(null);

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

    /**
     * Ask the AI for a draft reply to the newest inbound message.
     *
     * This used to fire the request and then `setTimeout(reload, 4000)` — one reload, once.
     * A queued job plus a model round trip almost never finishes inside four seconds, so
     * the reload landed early, found nothing, and never tried again. The draft was usually
     * written moments later and simply never displayed, which is indistinguishable from
     * the feature not working.
     *
     * Now the request marks the email `queued` server-side, and this reload picks that up.
     * From there `ai.drafting` keeps `in_flight` true, the poll runs on the fast tick, and
     * the transition watcher above announces the result — however long it takes, and
     * whether it succeeds or fails.
     */
    const regenerateDraft = async () => {
        const inbound = [...(thread.thread?.timeline || [])]
            .reverse()
            .find((i) => i.kind === 'message' && i.direction === 'in');

        if (!inbound) return;

        const done = await actions.requestDraft(inbound.id);

        if (done) {
            // Immediately, so the composer switches to "writing…" now rather than on the
            // next tick — the button having visibly done something is most of the point.
            await thread.reload({ silent: true });
        }
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

    // --------------------------------------------------------------- selection

    const toggleSelect = (id) =>
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((c) => c !== id) : [...current, id]
        );

    const selectAll = () =>
        setSelectedIds((current) =>
            current.length === inbox.threads.length ? [] : inbox.threads.map((t) => t.id)
        );

    const clearSelection = () => setSelectedIds([]);

    return {
        // page props, resolved
        settings,
        isManager,
        slaMinutes,
        canCompose: canComposeTemplate || canComposeCustom,
        compose,
        users,

        // data
        inbox,
        thread,
        recipients,
        toasts,
        dismissToast: dismiss,

        // notices — layouts may need their own, e.g. "no portal on this device"
        notify,
        warn,

        // opening and moving between threads
        openThread,
        closeThread,
        stepThread,
        refreshThread,
        refreshing,
        summariseThread,
        summarising,

        // reply composer
        replyOpen,
        replyEditing,
        replyTarget,
        replyBusy,
        openReply: () => {
            setReplyTarget(null); // falls back to the newest inbound message
            setReplyOpen(true);
        },
        replyToMessage,
        closeReply: () => {
            setReplyOpen(false);
            setReplyEditing(null);
            setReplyTarget(null);
        },
        sendReply,
        regenerateDraft,

        // team notes
        noteOpen,
        setNoteOpen,
        noteText,
        setNoteText,
        saveNote,
        cancelNote: () => {
            setNoteOpen(false);
            setNoteText('');
        },

        // approvals
        approveAndSend,
        openEditApprove,
        releaseThread,
        resendToAi,
        togglePrivacy,
        deleteThread,
        onMore,

        // list selection and bulk
        selectedIds,
        toggleSelect,
        selectAll,
        clearSelection,
        runBulk,
        toggleRead,

        // dialogs
        busy,
        rejectFor,
        setRejectFor,
        taskFor,
        setTaskFor,
        categoriseOpen,
        setCategoriseOpen,
        // How many threads the open categorise dialog will act on — the row menu's single
        // thread, or the whole selection.
        bulkCount: bulkIds?.length || selectedIds.length,
        composeOpen,
        setComposeOpen,
        clientViewFor,
        openClientView: (message) => setClientViewFor(message),
        closeClientView: () => setClientViewFor(null),
        applyCategories,
        createTask,
        rejectDraft: async (reason) => {
            const done = await runBusy(() => actions.reject(rejectFor, reason));
            setRejectFor(null);
            if (done) closeThread();
        },

        // the banner's "show me" jump, shared by both layouts
        showOverdue: () => {
            thread.close();
            // setFilters, not setView — this also switches the overdue filter on — so the
            // view's natural order has to be named explicitly here. Longest-waiting first
            // is the whole point of the banner.
            inbox.setFilters({
                view: 'needsReply',
                overdue_only: true,
                sort: defaultSortFor('needsReply'),
            });
        },
    };
}
