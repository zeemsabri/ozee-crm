/**
 * Owns everything about the guest's identity and their proposals on this share link:
 * the passwordless email -> OTP -> profile flow, the cached session token, and the
 * list of proposals (with bills) the server returns once verified.
 *
 * All requests go to the public routes in routes/web.php (`public.projects.*`). They
 * sit in the `web` middleware group, so they need the CSRF token — axios sends it
 * automatically from the XSRF-TOKEN cookie thanks to resources/js/bootstrap.js.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

/** Same localStorage key the Vue page used, so live sessions survive the switch. */
const storageKey = (projectToken) => `project_session_${projectToken}`;

function readStoredToken(projectToken) {
    try {
        return localStorage.getItem(storageKey(projectToken)) || '';
    } catch {
        // Private browsing or storage disabled — the guest just re-verifies.
        return '';
    }
}

function writeStoredToken(projectToken, value) {
    try {
        if (value) localStorage.setItem(storageKey(projectToken), value);
        else localStorage.removeItem(storageKey(projectToken));
    } catch {
        /* nothing we can do, and nothing depends on it */
    }
}

function firstError(error, fallback) {
    const data = error?.response?.data;
    if (data?.errors) {
        const flat = Object.values(data.errors).flat();
        if (flat.length) return flat.join(' ');
    }
    return data?.message || fallback;
}

/**
 * @param {string} projectToken full 64-char public share token (not the 12-char URL code)
 */
export function useGuestSession(projectToken) {
    const base = `/projects/public/${projectToken}`;

    // 'loading' only while we re-validate a token found in localStorage.
    const [step, setStep] = useState(() => (readStoredToken(projectToken) ? 'loading' : 'email'));
    const [sessionToken, setSessionToken] = useState(() => readStoredToken(projectToken));
    const [user, setUser] = useState({ name: '', email: '', phone: '', business_name: '' });
    const [proposals, setProposals] = useState([]);
    const [paymentMethods, setPaymentMethods] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const isSignedIn = step === 'ready';
    const hydrated = useRef(false);

    const persist = useCallback(
        (token) => {
            setSessionToken(token);
            writeStoredToken(projectToken, token);
        },
        [projectToken],
    );

    /** Tracking must never block or break the guest's flow, so failures are swallowed. */
    const track = useCallback(
        async (event, token = sessionToken) => {
            if (!token) return;
            try {
                await axios.post(`${base}/track`, { session_token: token, event });
            } catch {
                /* ignore */
            }
        },
        [base, sessionToken],
    );

    const applyPayload = useCallback((data) => {
        if (data?.user) {
            setUser({
                name: data.user.name || '',
                email: data.user.email || '',
                phone: data.user.phone || '',
                business_name: data.user.business_name || '',
            });
        }
        if (Array.isArray(data?.proposals)) setProposals(data.proposals);
        if (Array.isArray(data?.payment_methods)) setPaymentMethods(data.payment_methods);
    }, []);

    // On mount, revive a cached session if there is one.
    useEffect(() => {
        if (hydrated.current) return;
        hydrated.current = true;

        const stored = readStoredToken(projectToken);
        if (!stored) return;

        (async () => {
            try {
                const { data } = await axios.post(`${base}/session`, { session_token: stored });
                applyPayload(data);
                setStep(data?.needs_profile ? 'profile' : 'ready');
                track('link_open', stored);
            } catch {
                persist('');
                setStep('email');
            }
        })();
    }, [base, projectToken, applyPayload, persist, track]);

    const sendCode = useCallback(
        async (email) => {
            if (!email.trim()) {
                setError('Enter your email address so we can send you a code.');
                return false;
            }
            setError('');
            setBusy(true);
            try {
                await axios.post(`${base}/otp`, { email });
                setUser((u) => ({ ...u, email }));
                setStep('otp');
                return true;
            } catch (e) {
                setError(firstError(e, "We couldn't send that code. Check the address and try again."));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base],
    );

    const verifyCode = useCallback(
        async (email, otp) => {
            if (otp.length !== 6) {
                setError('The code is 6 digits.');
                return false;
            }
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.post(`${base}/otp/verify`, { email, otp });
                persist(data.session_token);
                applyPayload(data);
                setStep(data.needs_profile ? 'profile' : 'ready');
                track('link_open', data.session_token);
                return true;
            } catch (e) {
                setError(firstError(e, 'That code is invalid or has expired. Send yourself a new one.'));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base, persist, applyPayload, track],
    );

    const saveProfile = useCallback(
        async ({ name, phone, business_name: businessName }) => {
            if (!name.trim()) {
                setError('Add your full name — it goes on every proposal you send.');
                return false;
            }
            if (!phone.trim()) {
                setError('Add a phone number so the team can reach you.');
                return false;
            }
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.post(`${base}/profile`, {
                    session_token: sessionToken,
                    name,
                    phone,
                    ...(businessName === undefined ? {} : { business_name: businessName }),
                });
                applyPayload(data);
                setStep('ready');
                return true;
            } catch (e) {
                setError(firstError(e, "We couldn't save your details. Try again."));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken, applyPayload],
    );

    /**
     * Saved payout methods. The server returns the whole masked list after every change,
     * so there is never a partial view to reconcile.
     */
    const addPaymentMethod = useCallback(
        async (payload) => {
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.post(`${base}/payment-methods`, {
                    session_token: sessionToken,
                    ...payload,
                });
                setPaymentMethods(data.payment_methods || []);
                return true;
            } catch (e) {
                setError(firstError(e, "We couldn't save that payment method."));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken],
    );

    const removePaymentMethod = useCallback(
        async (id) => {
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.delete(`${base}/payment-methods/${id}`, {
                    data: { session_token: sessionToken },
                });
                setPaymentMethods(data.payment_methods || []);
                return true;
            } catch (e) {
                setError(firstError(e, "We couldn't remove that payment method."));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken],
    );

    const makeDefaultPaymentMethod = useCallback(
        async (id) => {
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.post(`${base}/payment-methods/${id}/default`, {
                    session_token: sessionToken,
                });
                setPaymentMethods(data.payment_methods || []);
                return true;
            } catch (e) {
                setError(firstError(e, "We couldn't change your default payment method."));
                return false;
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken],
    );

    /**
     * Upload an invoice against an accepted proposal. The server returns the refreshed
     * proposal list, so the Bills tab updates without a second round trip.
     */
    const submitBill = useCallback(
        async ({ proposalId, reference, amount, currency, dueDate, methodId, file }) => {
            setError('');
            setBusy(true);
            try {
                const form = new FormData();
                form.append('session_token', sessionToken);
                form.append('proposal_id', String(proposalId));
                form.append('payment_method_id', methodId);
                form.append('reference_number', reference);
                form.append('amount', String(amount));
                form.append('currency', currency);
                if (dueDate) form.append('due_date', dueDate);
                form.append('document', file);

                const { data } = await axios.post(`${base}/bills`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                applyPayload(data);
                return { ok: true, message: data?.message };
            } catch (e) {
                if (e?.response?.status === 401) {
                    persist('');
                    setStep('email');
                    setError('Your session expired before that could be sent. Verify your email again and re-upload.');
                    return { ok: false, expired: true };
                }
                setError(firstError(e, "We couldn't upload that bill. Try again."));
                return { ok: false };
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken, applyPayload, persist],
    );

    /**
     * Send (or update) one or more proposals.
     * @param {object} payload
     * @param {'milestone'|'milestones'|'project'} payload.scope
     * @param {number|null} payload.milestoneId       for scope 'milestone'
     * @param {number[]} payload.milestoneIds         for scope 'milestones'
     * @param {Record<number, string|number>} payload.amounts  per-milestone, for scope 'milestones'
     * @returns {Promise<{ok: boolean, message?: string}>}
     */
    const submitProposal = useCallback(
        async ({ scope, milestoneId, milestoneIds = [], amount, amounts = {}, description, currency, paymentTerms, document }) => {
            setError('');
            setBusy(true);
            try {
                const form = new FormData();
                form.append('session_token', sessionToken);
                form.append('proposal_scope', scope);
                form.append('description', description);
                form.append('currency', currency);
                if (paymentTerms) form.append('payment_terms', paymentTerms);
                if (document) form.append('document', document);

                if (scope === 'milestone') {
                    form.append('milestone_id', String(milestoneId));
                    form.append('amount', String(amount));
                } else if (scope === 'milestones') {
                    milestoneIds.forEach((id) => {
                        form.append('milestone_ids[]', String(id));
                        form.append(`amounts[${id}]`, String(amounts[id] ?? ''));
                    });
                } else {
                    form.append('amount', String(amount));
                }

                const { data } = await axios.post(`${base}/proposals`, form, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                applyPayload(data);
                track('proposal_submitted');
                return { ok: true, message: data?.message };
            } catch (e) {
                if (e?.response?.status === 401) {
                    persist('');
                    setStep('email');
                    setError('Your session expired before that could be sent. Verify your email again and re-enter the proposal.');
                    // Flagged so the page can close the composer — otherwise the modal
                    // covers the sign-in panel the message is pointing at.
                    return { ok: false, expired: true };
                }
                const message = firstError(e, "We couldn't send that proposal. Try again.");
                setError(message);
                return { ok: false, message };
            } finally {
                setBusy(false);
            }
        },
        [base, sessionToken, applyPayload, persist, track],
    );

    const signOut = useCallback(() => {
        persist('');
        setUser({ name: '', email: '', phone: '', business_name: '' });
        setProposals([]);
        setPaymentMethods([]);
        setError('');
        setStep('email');
    }, [persist]);

    return {
        step,
        setStep,
        isSignedIn,
        user,
        proposals,
        paymentMethods,
        busy,
        error,
        setError,
        sendCode,
        verifyCode,
        saveProfile,
        submitProposal,
        submitBill,
        addPaymentMethod,
        removePaymentMethod,
        makeDefaultPaymentMethod,
        signOut,
        track,
    };
}
