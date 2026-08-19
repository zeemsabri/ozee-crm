/**
 * Portal state and actions.
 *
 * The portal is server-rendered now: every page arrives with the account, proposals and
 * payment methods already in its Inertia props. These hooks hold that data as local
 * state so an action can update the page without a round trip, and expose the writes.
 *
 * Identity is a cookie the server sets after the emailed code is verified, so nothing
 * here passes a session token around — see App\Services\PortalSessionService.
 */

import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';

function firstError(error, fallback) {
    const data = error?.response?.data;
    if (data?.errors) {
        const flat = Object.values(data.errors).flat();
        if (flat.length) return flat.join(' ');
    }
    return data?.message || fallback;
}

/**
 * Sign-in for the share link: email -> 6-digit code -> the server sets the session
 * cookie and tells us where to go.
 *
 * @param {string} shareToken the project's full share token, from the page props
 */
export function useSignIn(shareToken) {
    const base = `/projects/public/${shareToken}`;

    const [step, setStep] = useState('email');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [email, setEmail] = useState('');

    const sendCode = useCallback(
        async (address) => {
            if (!address.trim()) {
                setError('Enter your email address so we can send you a code.');
                return false;
            }
            setError('');
            setBusy(true);
            try {
                await axios.post(`${base}/otp`, { email: address });
                setEmail(address);
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
        async (address, otp) => {
            if (otp.length !== 6) {
                setError('The code is 6 digits.');
                return false;
            }
            setError('');
            setBusy(true);
            try {
                const { data } = await axios.post(`${base}/otp/verify`, { email: address, otp });
                // A full visit rather than an Inertia one: the session cookie was only
                // just set, and this is the moment the page stops being anonymous.
                window.location.assign(data.redirect_to);
                return true;
            } catch (e) {
                setError(firstError(e, 'That code is invalid or has expired. Send yourself a new one.'));
                setBusy(false);
                return false;
            }
        },
        [base],
    );

    return { step, setStep, busy, error, setError, email, setEmail, sendCode, verifyCode };
}

/**
 * Everything a signed-in portal user can change.
 *
 * @param {object} initial          the page's Inertia props
 * @param {number|null} projectId   set on the project page; proposal and bill writes are
 *                                  scoped to it
 */
export function usePortalActions({ account, proposals: initialProposals, paymentMethods: initialMethods, projectId }) {
    const [proposals, setProposals] = useState(initialProposals || []);
    const [paymentMethods, setPaymentMethods] = useState(initialMethods || []);
    const [profile, setProfile] = useState(account || null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    // Inertia reuses the component across visits, so adopt fresh props when they change.
    useEffect(() => setProposals(initialProposals || []), [initialProposals]);
    useEffect(() => setPaymentMethods(initialMethods || []), [initialMethods]);
    useEffect(() => setProfile(account || null), [account]);

    /** Wraps a write: one busy flag, one error, consistent failure handling. */
    const run = useCallback(async (fn, fallbackMessage) => {
        setError('');
        setBusy(true);
        try {
            return { ok: true, data: await fn() };
        } catch (e) {
            if (e?.response?.status === 401) {
                // The portal session expired underneath us. Reload so the server can
                // send them back to the front door rather than failing silently.
                window.location.reload();
                return { ok: false, expired: true };
            }
            const message = firstError(e, fallbackMessage);
            setError(message);
            return { ok: false, message };
        } finally {
            setBusy(false);
        }
    }, []);

    const saveProfile = useCallback(
        async (payload) => {
            const result = await run(
                () => axios.post('/portal/profile', payload).then((r) => r.data),
                "We couldn't save your details.",
            );
            if (result.ok) setProfile(result.data.account);
            return result.ok;
        },
        [run],
    );

    const addPaymentMethod = useCallback(
        async (payload) => {
            const result = await run(
                () => axios.post('/portal/payment-methods', payload).then((r) => r.data),
                "We couldn't save that payment method.",
            );
            if (result.ok) setPaymentMethods(result.data.paymentMethods || []);
            return result.ok;
        },
        [run],
    );

    const removePaymentMethod = useCallback(
        async (id) => {
            const result = await run(
                () => axios.delete(`/portal/payment-methods/${id}`).then((r) => r.data),
                "We couldn't remove that payment method.",
            );
            if (result.ok) setPaymentMethods(result.data.paymentMethods || []);
            return result.ok;
        },
        [run],
    );

    const makeDefaultPaymentMethod = useCallback(
        async (id) => {
            const result = await run(
                () => axios.post(`/portal/payment-methods/${id}/default`).then((r) => r.data),
                "We couldn't change your default payment method.",
            );
            if (result.ok) setPaymentMethods(result.data.paymentMethods || []);
            return result.ok;
        },
        [run],
    );

    const submitProposal = useCallback(
        async ({ scope, milestoneId, milestoneIds = [], amount, amounts = {}, description, currency, paymentTerms, document }) => {
            const result = await run(() => {
                const form = new FormData();
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

                return axios
                    .post(`/portal/projects/${projectId}/proposals`, form, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    })
                    .then((r) => r.data);
            }, "We couldn't send that proposal. Try again.");

            if (result.ok) setProposals(result.data.proposals || []);
            return result;
        },
        [run, projectId],
    );

    const submitBill = useCallback(
        async ({ proposalId, reference, amount, currency, dueDate, methodId, file }) => {
            const result = await run(() => {
                const form = new FormData();
                form.append('proposal_id', String(proposalId));
                form.append('payment_method_id', methodId);
                form.append('reference_number', reference);
                form.append('amount', String(amount));
                form.append('currency', currency);
                if (dueDate) form.append('due_date', dueDate);
                form.append('document', file);

                return axios
                    .post(`/portal/projects/${projectId}/bills`, form, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    })
                    .then((r) => r.data);
            }, "We couldn't upload that bill. Try again.");

            if (result.ok) setProposals(result.data.proposals || []);
            return { ...result, message: result.data?.message };
        },
        [run, projectId],
    );

    /**
     * Ends the emailed-code session server-side, then lands on the front page.
     *
     * Only offered when identity came from that cookie. Someone browsing the portal
     * because they are signed into the main app arrives with `via: 'login'`, and for
     * them this button would clear nothing — the next request would re-resolve them
     * from the web session and they would still be here, which reads as broken.
     */
    const signOutRequest = useCallback(() => router.post('/portal/sign-out'), []);
    const signOut = profile?.via === 'session' ? signOutRequest : null;

    return {
        profile,
        proposals,
        paymentMethods,
        busy,
        error,
        setError,
        saveProfile,
        addPaymentMethod,
        removePaymentMethod,
        makeDefaultPaymentMethod,
        submitProposal,
        submitBill,
        signOut,
    };
}
