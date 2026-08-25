/**
 * Saved (unfinished) emails — the client half.
 *
 * Server-backed since the 'saved' status landed: a saved email is a REAL emails row
 * with `status = 'saved'` and no conversation, written only by
 * Api\InboxSavedController with model events off — which is what keeps the workflow
 * automation (it fires on every eloquent created/updated) from ever seeing a
 * half-written email. The trap this design answers: status 'draft' means SUBMITTED in
 * this codebase, so "save for later" could never be a draft row.
 *
 * Submitting a saved email does NOT transition it. The composer posts to the normal
 * create endpoint — a fresh row, so the automation fires exactly as it always has —
 * and then deletes the saved row here. No second trigger path exists to go stale.
 *
 * Two hooks:
 * - useSavedEmails — the shared list. ONE instance, owned by useInboxPage, feeds the
 *   rail badge, the Saved view (SavedList) and the composer's Drafts popover, so they
 *   can never disagree.
 * - useComposeDrafts — the composer's autosave engine. Owns WHICH row this composer
 *   session is writing (create once, then update in place) and the saving/saved
 *   indicator. Calls back into useSavedEmails' refresh so the list follows along.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

/* ------------------------------------------------------------------ *
 *  The shared list
 * ------------------------------------------------------------------ */

export function useSavedEmails({ enabled = false, onError } = {}) {
    const [drafts, setDrafts] = useState([]);
    const [loading, setLoading] = useState(false);

    const refresh = useCallback(async () => {
        if (!enabled) return;
        setLoading(true);
        try {
            const { data } = await axios.get('/api/inbox/saved');
            setDrafts(data.data || []);
        } catch {
            // Quiet on load — the badge just stays stale; actions surface their own errors.
        } finally {
            setLoading(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    const remove = useCallback(
        async (id) => {
            try {
                await axios.delete(`/api/inbox/saved/${id}`);
                setDrafts((current) => current.filter((d) => d.id !== id));
            } catch {
                onError?.('Could not delete that saved email.');
            }
        },
        [onError]
    );

    return { drafts, loading, refresh, remove };
}

/* ------------------------------------------------------------------ *
 *  The composer's autosave engine
 * ------------------------------------------------------------------ */

/**
 * @param {{ getSnapshot: () => object|null, active: boolean, onSaved?: Function,
 *           onError?: Function }} opts
 *   getSnapshot returns {project_id, client_ids, subject, body, greeting_mode,
 *   greeting_name, is_private} or null when there is nothing worth keeping (an empty
 *   composer never creates a saved row — the server refuses one too). `active` gates
 *   autosave: false while the modal is closed or on a non-custom tab. `onSaved` fires
 *   after any successful write (the page refreshes its shared list there).
 */
export function useComposeDrafts({ getSnapshot, active, onSaved, onError }) {
    // 'idle' | 'saving' | 'error' | number (epoch ms of the last successful save)
    const [savedState, setSavedState] = useState('idle');
    const draftIdRef = useRef(null);
    const timerRef = useRef(null);
    const inFlightRef = useRef(false);

    // The parent hands a fresh closure every render; refs keep saveNow stable so the
    // autosave effect depends on CONTENT (the fingerprint) and nothing else — depending
    // on closure identity re-armed the timer every render, and each completed save
    // re-rendered, which was a save loop.
    const getSnapshotRef = useRef(getSnapshot);
    getSnapshotRef.current = getSnapshot;
    const onSavedRef = useRef(onSaved);
    onSavedRef.current = onSaved;

    const saveNow = useCallback(async () => {
        const snapshot = getSnapshotRef.current();
        if (!snapshot || inFlightRef.current) return null;

        inFlightRef.current = true;
        try {
            const id = draftIdRef.current;
            const { data } = id
                ? await axios.put(`/api/inbox/saved/${id}`, snapshot)
                : await axios.post('/api/inbox/saved', snapshot);

            draftIdRef.current = data?.data?.id ?? id ?? null;
            setSavedState(Date.now());
            onSavedRef.current?.();

            return draftIdRef.current;
        } catch (e) {
            setSavedState('error');
            // 403/404: the row was deleted elsewhere, or belongs to a project this
            // person lost — start a fresh row on the next save rather than looping.
            if ([403, 404].includes(e?.response?.status)) draftIdRef.current = null;
            onError?.('Could not save — your text is still here, but it is not saved yet.');
            return null;
        } finally {
            inFlightRef.current = false;
        }
    }, [onError]);

    /* Autosave: a couple of seconds after the latest change. Re-arms only when the
       CONTENT changes (see the refs above). */
    const fingerprint = active ? JSON.stringify(getSnapshot() ?? null) : null;

    useEffect(() => {
        if (!active || fingerprint === null || fingerprint === 'null') return undefined;

        setSavedState((s) => (s === 'idle' ? s : 'saving'));
        timerRef.current = setTimeout(() => {
            saveNow();
        }, 2000);

        return () => clearTimeout(timerRef.current);
        // saveNow is stable — content and activity are the deps.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [fingerprint, active]);

    const discardCurrent = useCallback(async () => {
        const id = draftIdRef.current;
        clearTimeout(timerRef.current);
        draftIdRef.current = null;
        setSavedState('idle');
        if (!id) return;
        try {
            await axios.delete(`/api/inbox/saved/${id}`);
            onSavedRef.current?.();
        } catch {
            // The row survives; it shows in Saved and can be deleted there.
        }
    }, []);

    return {
        savedState,
        /** Explicit "Save" — resolves to the row id, or null when there was nothing to keep. */
        saveNow,
        /** Point autosave at an existing saved row (resume). */
        adopt: (id) => {
            draftIdRef.current = id;
            setSavedState('idle');
        },
        /** The current saved row is spent: submitted, or deliberately discarded. */
        discardCurrent,
        /** Forget the autosave target without deleting anything (modal closed). */
        detach: () => {
            clearTimeout(timerRef.current);
            draftIdRef.current = null;
            setSavedState('idle');
        },
    };
}

/** "saved 2 min ago" for the footer indicator and the saved list. */
export function draftAgeLabel(savedAt) {
    const mins = Math.round((Date.now() - savedAt) / 60000);
    if (mins < 1) return 'just now';
    if (mins === 1) return '1 min ago';
    if (mins < 60) return `${mins} min ago`;
    const hours = Math.round(mins / 60);
    if (hours < 24) return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    const days = Math.round(hours / 24);
    return days === 1 ? 'yesterday' : `${days} days ago`;
}
