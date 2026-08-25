/**
 * Data layer for the redesigned inbox.
 *
 * All reads and writes go through axios against /api/inbox/* (see routes/api.php). Inertia
 * is not used for these: the page is a shell and the list is refetched constantly, so
 * partial-reload semantics would fight the filter state rather than help it.
 *
 * Two behaviours worth knowing:
 *  - Every list request is sequence-stamped. A slow response for filters you have since
 *    changed is discarded rather than flashing stale rows in.
 *  - Filter changes are debounced together, so typing in the search box does not fire a
 *    request per keystroke, but clicking a view is immediate.
 */

import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const DEBOUNCE_MS = 350;

/**
 * The natural order for a view.
 *
 * Only ONE view wants oldest-first, and it is the one whose entire job is a queue: in
 * "Needs reply" the thread that has been waiting longest is the one to answer next, so it
 * belongs at the top. First come, first replied.
 *
 * Everywhere else, newest first. This is the fix for "I am seeing old emails everywhere":
 * `breach` was the default sort for every view, not just Needs reply, and it orders by
 * OLDEST inbound message. So Sent, Received, All mail and the rest all opened on the
 * oldest thing in the system — correct behaviour for a queue, wrong for a log.
 *
 * The sort buttons still override this. Switching view resets to the view's natural order,
 * which is predictable: clicking "Sent" should show what was sent most recently, not
 * inherit a queue ordering from the screen before.
 */
export function defaultSortFor(view) {
    return view === 'needsReply' ? 'breach' : 'date';
}

export const EMPTY_FILTERS = {
    view: 'needsReply',
    project_id: 'all',
    category_ids: [],
    search: '',
    from: '',
    to: '',
    unread_only: false,
    overdue_only: false,
    sort: defaultSortFor('needsReply'),
};

/** Params the server understands, with defaults stripped so URLs stay readable. */
function toParams(filters, page) {
    const params = { view: filters.view, sort: filters.sort, page };

    if (filters.project_id && filters.project_id !== 'all') params.project_id = filters.project_id;
    if (filters.category_ids?.length) params.category_ids = filters.category_ids;
    if (filters.search?.trim()) params.search = filters.search.trim();
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;
    if (filters.unread_only) params.unread_only = 1;
    if (filters.overdue_only) params.overdue_only = 1;

    return params;
}

export function useInbox({ onError } = {}) {
    const [filters, setFiltersState] = useState(EMPTY_FILTERS);
    const [page, setPage] = useState(1);

    const [threads, setThreads] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [counts, setCounts] = useState({});
    const [overdueCount, setOverdueCount] = useState(0);
    const [loading, setLoading] = useState(true);
    const [options, setOptions] = useState({ projects: [], categories: [], sla_minutes: 60 });

    // Monotonic request id: only the newest response is allowed to write state.
    const sequence = useRef(0);
    const debounce = useRef(null);

    // The current filters and page, mirrored into refs so `refresh` can stay
    // referentially stable. Without this, `refresh` changes identity on every filter
    // change, and any callback that captured an earlier one (the page's openThread and
    // closeThread do, deliberately, to avoid being rebuilt constantly) would refetch with
    // whatever filters were in force when it was created — silently replacing a filtered,
    // paginated list with page 1 of the defaults while the rail still showed the filters.
    const latest = useRef({ filters: EMPTY_FILTERS, page: 1 });
    latest.current = { filters, page };

    const fail = useCallback(
        (error, fallback) => {
            const message = error?.response?.data?.message || fallback;
            if (onError) onError(message);
        },
        [onError]
    );

    const load = useCallback(
        async (nextFilters, nextPage) => {
            /*
             * "deleted" is not a thread view — DeletedList fetches its own rows from
             * /api/inbox/deleted. Asking the thread endpoint for it would fall through
             * applyView's switch and quietly return EVERY thread, which is both a wasted
             * query on every keystroke in that screen's search box and a list nobody sees.
             */
            if (nextFilters?.view === 'deleted') {
                setLoading(false);
                return;
            }

            const id = ++sequence.current;
            setLoading(true);

            try {
                const { data } = await axios.get('/api/inbox/threads', {
                    params: toParams(nextFilters, nextPage),
                });

                if (id !== sequence.current) return; // a newer request has superseded this one

                setThreads(data.data || []);
                setMeta(data.meta || { current_page: 1, last_page: 1, total: 0 });
                setCounts(data.counts || {});
                setOverdueCount(data.overdue_count || 0);
            } catch (error) {
                if (id !== sequence.current) return;
                fail(error, 'Could not load the inbox.');
            } finally {
                if (id === sequence.current) setLoading(false);
            }
        },
        [fail]
    );

    // Filter options are static for the session — projects and categories do not change
    // while someone is triaging mail.
    useEffect(() => {
        axios
            .get('/api/inbox/filters')
            .then(({ data }) => setOptions(data))
            .catch((error) => fail(error, 'Could not load the filter options.'));
    }, [fail]);

    useEffect(() => {
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => load(filters, page), DEBOUNCE_MS);

        return () => clearTimeout(debounce.current);
    }, [filters, page, load]);

    /** Patch the filters. Anything but a page change resets to page 1. */
    const setFilters = useCallback((patch) => {
        setFiltersState((current) => ({ ...current, ...patch }));
        setPage(1);
    }, []);

    const setView = useCallback(
        (view) => setFilters({ view, sort: defaultSortFor(view) }),
        [setFilters]
    );

    const clearFilters = useCallback(() => {
        // The view and the chosen sort are not "filters" — clearing the project, the
        // categories and the search should not also throw you back to another list.
        setFiltersState((current) => ({ ...EMPTY_FILTERS, view: current.view, sort: current.sort }));
        setPage(1);
    }, []);

    const hasFilters = useMemo(
        () =>
            filters.project_id !== 'all' ||
            filters.category_ids.length > 0 ||
            !!filters.search ||
            !!filters.from ||
            !!filters.to ||
            filters.unread_only ||
            filters.overdue_only,
        [filters]
    );

    /**
     * Refetch whatever is currently on screen, after an action that changed it.
     *
     * Stable identity by design — it reads the live filters out of a ref rather than
     * closing over them, so callers can hold onto it indefinitely. See `latest` above.
     */
    const refresh = useCallback(
        () => load(latest.current.filters, latest.current.page),
        [load]
    );

    return {
        filters,
        setFilters,
        setView,
        clearFilters,
        hasFilters,
        page,
        setPage,
        threads,
        meta,
        counts,
        overdueCount,
        loading,
        options,
        refresh,
    };
}

/**
 * One open thread.
 *
 * Kept separate from the list so opening a thread never re-runs the list query, and so
 * the list stays on screen behind it (the design's back button returns to the same
 * scroll position and filters).
 */
export function useThread({ onError } = {}) {
    const [thread, setThread] = useState(null);
    const [loading, setLoading] = useState(false);
    const openId = useRef(null);

    const fail = useCallback(
        (error, fallback) => {
            const message = error?.response?.data?.message || fallback;
            if (onError) onError(message);
        },
        [onError]
    );

    /**
     * Open a thread, or re-read the one already open.
     *
     * `silent` skips the loading state and leaves the previous content in place on
     * failure. That is for the background poll and the refresh button: flipping a thread
     * someone is reading into a skeleton once a minute would be worse than not refreshing
     * at all, and a transient network blip must not blank a thread and drop them back to
     * the list.
     */
    const open = useCallback(
        async (id, { silent = false } = {}) => {
            openId.current = id;
            if (!silent) setLoading(true);

            try {
                const { data } = await axios.get(`/api/inbox/threads/${id}`);
                if (openId.current !== id) return;
                setThread(data.data);
            } catch (error) {
                if (openId.current !== id) return;
                // A failed background refresh is not worth a toast, and definitely not
                // worth closing the thread — keep showing what we last had.
                if (silent) return;
                fail(error, 'Could not open that thread.');
                setThread(null);
                openId.current = null;
            } finally {
                if (!silent && openId.current === id) setLoading(false);
            }
        },
        [fail]
    );

    const close = useCallback(() => {
        openId.current = null;
        setThread(null);
    }, []);

    const reload = useCallback(
        (options = {}) => {
            if (openId.current) return open(openId.current, options);
            return Promise.resolve();
        },
        [open]
    );

    return { thread, setThread, loading, open, close, reload, openId };
}

/** Writes. Each resolves to true on success so callers can decide whether to refresh. */
export function inboxActions({ onError, onToast }) {
    const run = async (fn, successMessage, fallbackError) => {
        try {
            const result = await fn();
            if (successMessage && onToast) onToast(successMessage);
            return result ?? true;
        } catch (error) {
            const message = error?.response?.data?.message || fallbackError;
            if (onError) onError(message);
            return false;
        }
    };

    return {
        markRead: (id, unread = false) =>
            run(
                () => axios.post(`/api/inbox/threads/${id}/read`, { unread }),
                unread ? 'Marked as unread' : null,
                'Could not update the read state.'
            ),

        addNote: (id, body) =>
            run(
                () => axios.post(`/api/inbox/threads/${id}/notes`, { body }).then((r) => r.data.data),
                'Note added — only the project team can see it',
                'Could not add the note.'
            ),

        bulk: (action, conversationIds, extra = {}) =>
            run(
                () =>
                    axios
                        .post('/api/inbox/bulk', {
                            action,
                            conversation_ids: conversationIds,
                            ...extra,
                        })
                        .then((r) => r.data),
                null,
                'Could not complete that action.'
            ),

        /**
         * Submit a reply. Creates it as a draft, which is what starts the automation —
         * this does not send. See InboxReplyController.
         */
        reply: (id, payload) =>
            run(
                () => axios.post(`/api/inbox/threads/${id}/reply`, payload).then((r) => r.data.data),
                null,
                'Could not submit the reply.'
            ),

        recipients: (id) =>
            run(
                () => axios.get(`/api/inbox/threads/${id}/recipients`).then((r) => r.data),
                null,
                'Could not work out who to reply to.'
            ),

        /**
         * Approve an email that is sitting at pending_approval, and send it.
         *
         * This is the EXISTING endpoint the classic inbox approves through — the redesign
         * deliberately has no send path of its own. It is the human end of the automation:
         * the workflow's AI review refused the email (or never ran), so someone with
         * approval rights is deciding.
         *
         * It is NOT how a new reply is sent. Those are created as drafts and the workflow
         * takes them from there; see InboxReplyController.
         *
         * `composition_type` is passed through rather than hardcoded because
         * edit-and-approve branches on it. Telling a templated email it is custom would
         * make that endpoint overwrite the body with a null and send a blank message. A
         * block email sends no body at all — the server rebuilds it from the stored
         * blocks, and the body on screen is a preview containing signed image URLs that
         * must never be mailed.
         */
        sendCreated: (emailId, { subject, body, compositionType = 'custom', templateId, templateData }) =>
            run(
                () =>
                    /*
                     * The beta's own approve route, not emails/{id}/edit-and-approve
                     * directly.
                     *
                     * It adds the timing rule the shared endpoint has no concept of — the
                     * automation owns a submitted draft for its first half hour, and a
                     * person may only send inside that window once the machine has handed
                     * the email back — and then forwards this exact payload to
                     * edit-and-approve. Same rendering, same recipients, same Gmail call;
                     * the classic inbox keeps posting to that endpoint directly.
                     *
                     * A refusal comes back as 409 with the reason in `message`, which
                     * run() surfaces as the error toast.
                     */
                    axios.post(`/api/inbox/emails/${emailId}/approve`, {
                        subject,
                        composition_type: compositionType,
                        ...(compositionType === 'template'
                            ? { template_id: templateId, template_data: templateData }
                            : compositionType === 'blocks'
                              ? {}
                              : { body }),
                    }),
                null,
                'That email could not be sent. It is still waiting in approvals.'
            ),

        reject: (emailId, reason) =>
            run(
                () => axios.post(`/api/emails/${emailId}/reject`, { rejection_reason: reason }),
                'Sent back with your reason',
                'Could not send it back.'
            ),

        togglePrivacy: (emailId, isPrivate) =>
            run(
                () => axios.patch(`/api/emails/${emailId}/privacy`, { is_private: isPrivate }),
                isPrivate ? 'Message is private — managers only' : 'Message is visible to the project team',
                'Could not change the privacy of that message.'
            ),

        /*
         * Delete, with an explicit scope. `{ local, gmail }` comes from DeleteModal.
         *
         * No success toast baked in here any more: what actually happened depends on the
         * scope AND on whether Gmail could be reached, so the caller reads `gmail_errors`
         * off the response and says the true thing. A canned "deleted locally" was wrong
         * the moment the Gmail box existed.
         */
        deleteThreads: (ids, { local = true, gmail = false } = {}) =>
            run(
                () =>
                    axios
                        .post('/api/inbox/bulk', {
                            action: 'delete',
                            conversation_ids: ids,
                            delete_local: local,
                            delete_gmail: gmail,
                        })
                        .then((r) => r.data),
                null,
                'Could not delete that.'
            ),

        /*
         * One message. The classic endpoint, unchanged — it has taken these two flags
         * since before the redesign and authorises EmailPolicy::delete, so there is no
         * reason for a second one that does the same thing slightly differently.
         */
        deleteEmail: (emailId, { local = true, gmail = false } = {}) =>
            run(
                () =>
                    axios
                        .delete(`/api/emails/${emailId}`, {
                            data: { delete_local: local, delete_gmail: gmail },
                        })
                        .then((r) => r.data),
                null,
                'Could not delete that message.'
            ),

        resendToAi: (emailId) =>
            run(
                () => axios.post(`/api/inbox/emails/${emailId}/resend-to-ai`),
                "Sent to the checker again — you'll hear back either way",
                'Could not resubmit it to the checker.'
            ),

        /**
         * Ask for a thread summary. Explicit, because opening a thread no longer does it
         * — that spent tokens on every thread anyone glanced at, and once the thread
         * started polling itself, repeatedly on the same one.
         */
        summarise: (conversationId) =>
            run(
                () => axios.post(`/api/inbox/threads/${conversationId}/summarise`).then((r) => r.data),
                null,
                'Could not start the summary.'
            ),

        requestDraft: (emailId) =>
            run(
                () => axios.post(`/api/inbox/emails/${emailId}/draft`),
                'Writing a new draft — it will appear in a moment',
                'Could not ask for a draft.'
            ),

        createTasks: (emailId, tasks) =>
            run(
                () => axios.post(`/api/emails/${emailId}/tasks/bulk`, { tasks, email_id: emailId }),
                'Task created',
                'Could not create the task.'
            ),
    };
}
