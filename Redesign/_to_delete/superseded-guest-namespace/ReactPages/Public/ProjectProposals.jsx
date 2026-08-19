/**
 * Public guest project page — the redesigned replacement for Pages/Public/ProjectView.vue.
 *
 * Rendered by App\Http\Controllers\Public\PublicProjectController as
 * Inertia::render('React/Public/ProjectProposals', ...). No auth: the visitor is
 * identified by the share token in the URL plus an emailed OTP.
 *
 * Design: Redesign/proposal/Guest Project Proposals.dc.html, on the monday.com-style
 * token set in resources/css/ozee-ds. That stylesheet is imported here rather than from
 * resources/css/app.css so it only loads on redesigned pages — see app.blade.php, which
 * emits this file as its own Vite entry.
 */

import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

import '../../../css/ozee-ds/index.css';

import { AttentionBox, Button, Tabs, Toast } from '../../ReactComponents/ds';
import { GuestFooter, GuestHeader } from '../../ReactComponents/guest/GuestChrome';
import { ProjectHero } from '../../ReactComponents/guest/ProjectHero';
import { PhasesTab } from '../../ReactComponents/guest/PhasesTab';
import { ProposalsTab } from '../../ReactComponents/guest/ProposalsTab';
import { BillsTab } from '../../ReactComponents/guest/BillsTab';
import { SignInPanel } from '../../ReactComponents/guest/SignInPanel';
import { ProposalsPanel } from '../../ReactComponents/guest/ProposalsPanel';
import { ProposalModal } from '../../ReactComponents/guest/ProposalModal';
import { BillModal } from '../../ReactComponents/guest/BillModal';
import { ProfileView } from '../../ReactComponents/guest/ProfileView';
import { useGuestSession } from '../../ReactComponents/guest/useGuestSession';
import { useTheme } from '../../ReactComponents/guest/useTheme';

export default function ProjectProposals({ project, branding, currencies }) {
    const { theme, setTheme } = useTheme();
    const session = useGuestSession(project.token);

    const [view, setView] = useState('project'); // 'project' | 'profile'
    const [mainTab, setMainTab] = useState('phases');
    const [expanded, setExpanded] = useState({});
    const [modal, setModal] = useState(null); // null | { milestoneId?, proposal? }
    const [billFor, setBillFor] = useState(null); // the proposal being invoiced
    const [toast, setToast] = useState('');

    const milestones = project.milestones || [];
    const deliverables = project.deliverables || [];
    const proposals = session.proposals || [];

    const deliverablesByMilestone = useMemo(() => {
        const grouped = {};
        deliverables.forEach((d) => {
            if (!d.milestone_id) return;
            (grouped[d.milestone_id] ||= []).push(d);
        });
        return grouped;
    }, [deliverables]);

    const looseDeliverables = useMemo(() => deliverables.filter((d) => !d.milestone_id), [deliverables]);

    const deliverableCountByMilestone = useMemo(() => {
        const counts = {};
        milestones.forEach((m) => {
            counts[m.id] = (deliverablesByMilestone[m.id] || []).length;
        });
        return counts;
    }, [milestones, deliverablesByMilestone]);

    /**
     * Which proposal covers which scope. Keyed by milestone id, plus a 'project' key for
     * a whole-project quote. When a phase has both, its own phase proposal wins — the
     * phase card falls back to the project one only if there is no phase-specific quote.
     */
    const proposalByScope = useMemo(() => {
        const map = {};
        // `proposals` arrives newest-first, so the first one wins. A phase can end up
        // with more than one row (a re-quote after a decision creates a new proposal
        // rather than rewriting the old one), and the newest is the live one.
        proposals.forEach((p) => {
            const key = p.scope === 'project' ? 'project' : p.milestone_id;
            if (key && !map[key]) map[key] = p;
        });
        return map;
    }, [proposals]);

    // A whole-project quote does not stop them pricing an individual phase as well —
    // the two coexist — so this looks only at each phase's own proposal.
    const unquotedPhases = useMemo(
        () => milestones.filter((m) => !proposalByScope[m.id]),
        [milestones, proposalByScope],
    );

    // Currency for the sidebar and bills totals: whatever they last quoted in, as long
    // as it's still an offered option.
    const lastUsed = proposals[0]?.currency;
    const primaryCurrency = currencies?.includes(lastUsed) ? lastUsed : currencies?.[0] || 'AUD';

    const billCount = proposals.reduce((n, p) => n + (p.bills || []).length, 0);

    function toggleMilestone(id) {
        setExpanded((prev) => ({ ...prev, [id]: prev[id] !== true }));
    }

    function openProposal(milestoneId) {
        // Editing beats creating: if this phase already has an editable quote, reopen it.
        const existing = proposalByScope[milestoneId];
        if (existing && existing.can_edit) {
            setModal({ proposal: existing });
        } else {
            setModal({ milestoneId });
        }
    }

    async function handleSubmit(payload) {
        const result = await session.submitProposal(payload);
        if (!result.ok) {
            // A dead session needs the sign-in panel, which sits behind the modal.
            if (result.expired) setModal(null);
            return;
        }

        setModal(null);
        setMainTab('proposals');
        setToast(
            payload.editingId
                ? 'Proposal updated — the team can see your changes.'
                : payload.scope === 'milestones' && payload.milestoneIds.length > 1
                  ? `${payload.milestoneIds.length} proposals sent — one per phase.`
                  : 'Proposal sent. The team usually responds within two business days.',
        );
    }

    function openBill(proposal) {
        // Nowhere to be paid means the bill can't be completed — send them to set that
        // up first rather than into a form they can't submit.
        if (!session.paymentMethods.length) {
            setView('profile');
            setToast('Add a payment method first, then upload your bill.');
            return;
        }
        setBillFor(proposal);
    }

    async function handleBillSubmit({ reference, amount, dueDate, methodId, file }) {
        const result = await session.submitBill({
            proposalId: billFor.id,
            reference,
            amount,
            currency: billFor.currency,
            dueDate,
            methodId,
            file,
        });

        if (!result.ok) {
            if (result.expired) setBillFor(null);
            return;
        }

        setBillFor(null);
        setMainTab('bills');
        setToast(result.message || 'Bill uploaded — the accounts team has been notified.');
    }

    const tabs = [
        { value: 'phases', label: 'Work phases', count: milestones.length },
        { value: 'proposals', label: 'Proposals', count: proposals.length },
        { value: 'bills', label: 'Bills', count: billCount },
    ];

    return (
        <>
            <Head title={`${project.name} — proposals`} />

            <div
                className="ozds"
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    flexDirection: 'column',
                    background: 'var(--grey-background-color)',
                    color: 'var(--primary-text-color)',
                }}
            >
                <GuestHeader
                    branding={branding}
                    user={session.user}
                    isSignedIn={session.isSignedIn}
                    theme={theme}
                    onThemeChange={setTheme}
                    view={view}
                    onViewChange={setView}
                />

                {view === 'profile' && session.isSignedIn ? (
                    <ProfileView
                        user={session.user}
                        paymentMethods={session.paymentMethods}
                        busy={session.busy}
                        onSaveProfile={session.saveProfile}
                        onAddMethod={session.addPaymentMethod}
                        onRemoveMethod={session.removePaymentMethod}
                        onMakeDefault={session.makeDefaultPaymentMethod}
                    />
                ) : (
                <div className="ozds-guest-layout">
                    {/* ---- Main column ---- */}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
                        <ProjectHero project={project} />

                        <Tabs tabs={tabs} value={mainTab} onChange={setMainTab} />

                        {mainTab === 'phases' ? (
                            <PhasesTab
                                milestones={milestones}
                                deliverablesByMilestone={deliverablesByMilestone}
                                looseDeliverables={looseDeliverables}
                                proposalByScope={proposalByScope}
                                expanded={expanded}
                                onToggle={toggleMilestone}
                                onPropose={openProposal}
                                canPropose={session.isSignedIn}
                            />
                        ) : null}

                        {mainTab === 'proposals' ? (
                            session.isSignedIn ? (
                                <ProposalsTab
                                    proposals={proposals}
                                    onEdit={(proposal) => setModal({ proposal })}
                                    onUploadBill={openBill}
                                />
                            ) : (
                                <AttentionBox title="Sign in to see your proposals" type="dark">
                                    Verify your email in the panel on the right and anything you've sent for this
                                    project appears here.
                                </AttentionBox>
                            )
                        ) : null}

                        {mainTab === 'bills' ? (
                            session.isSignedIn ? (
                                <BillsTab
                                    proposals={proposals}
                                    currency={primaryCurrency}
                                    onUploadBill={openBill}
                                />
                            ) : (
                                <AttentionBox title="Sign in to see your bills" type="dark">
                                    Bills raised against your accepted proposals show up here once you've verified
                                    your email.
                                </AttentionBox>
                            )
                        ) : null}
                    </div>

                    {/* ---- Sidebar ---- */}
                    <div className="ozds-guest-sidebar">
                        {session.isSignedIn ? (
                            <ProposalsPanel
                                proposals={proposals}
                                unquotedPhases={unquotedPhases}
                                currency={primaryCurrency}
                                user={session.user}
                                onNewProposal={() =>
                                    setModal({ milestoneId: unquotedPhases[0]?.id ?? milestones[0]?.id ?? null })
                                }
                                onProposePhase={openProposal}
                                onGoToTab={setMainTab}
                                onSignOut={session.signOut}
                            />
                        ) : (
                            <SignInPanel session={session} companyName={branding?.company?.name} />
                        )}
                    </div>
                </div>
                )}

                <GuestFooter
                    branding={branding}
                    projectContact={project.contact}
                    guestEmail={session.isSignedIn ? session.user.email : null}
                />
            </div>

            <BillModal
                open={Boolean(billFor)}
                onClose={() => setBillFor(null)}
                onSubmit={handleBillSubmit}
                busy={session.busy}
                error={session.error}
                onDismissError={() => session.setError('')}
                proposal={billFor}
                paymentMethods={session.paymentMethods}
                onGoToProfile={() => {
                    setBillFor(null);
                    setView('profile');
                }}
            />

            <ProposalModal
                open={Boolean(modal)}
                onClose={() => setModal(null)}
                onSubmit={handleSubmit}
                busy={session.busy}
                error={session.error}
                onDismissError={() => session.setError('')}
                milestones={milestones}
                deliverableCountByMilestone={deliverableCountByMilestone}
                proposalByScope={proposalByScope}
                initial={modal}
                defaultCurrency={primaryCurrency}
                currencies={currencies}
            />

            {/* Toast sits above the page, bottom-centre, and auto-clears on dismiss. */}
            {toast ? (
                <div
                    className="ozds"
                    style={{
                        position: 'fixed',
                        inset: 'auto 0 0 0',
                        display: 'flex',
                        justifyContent: 'center',
                        zIndex: 10001,
                        pointerEvents: 'none',
                    }}
                >
                    <div style={{ pointerEvents: 'auto' }}>
                        <Toast type="positive" withIcon open onClose={() => setToast('')}>
                            {toast}
                        </Toast>
                    </div>
                </div>
            ) : null}

            {/* Layout only — everything else is inline so it stays next to the markup it
                styles, matching the design mock. Media queries can't be expressed inline. */}
            <style>{`
                .ozds-guest-layout {
                    max-width: 1240px;
                    margin: 0 auto;
                    padding: var(--space-24);
                    width: 100%;
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 404px;
                    gap: var(--space-24);
                    align-items: start;
                }
                .ozds-guest-sidebar {
                    position: sticky;
                    top: 80px;
                    display: flex;
                    flex-direction: column;
                    gap: var(--space-16);
                    min-width: 0;
                }
                @media (max-width: 1080px) {
                    .ozds-guest-layout {
                        grid-template-columns: minmax(0, 1fr);
                    }
                    /* Stacked, the sidebar leads — signing in is the first thing to do. */
                    .ozds-guest-sidebar {
                        position: static;
                        order: -1;
                    }
                }
                .ozds-profile-layout {
                    max-width: 1240px;
                    margin: 0 auto;
                    padding: var(--space-24);
                    width: 100%;
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 460px;
                    gap: var(--space-24);
                    align-items: start;
                }
                @media (max-width: 1080px) {
                    .ozds-profile-layout {
                        grid-template-columns: minmax(0, 1fr);
                    }
                }
                @media (max-width: 640px) {
                    .ozds-guest-layout,
                    .ozds-profile-layout {
                        padding: var(--space-16);
                    }
                }
            `}</style>
        </>
    );
}
