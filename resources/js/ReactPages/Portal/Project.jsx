/**
 * A single project in the portal.
 *
 * Served two ways, deliberately with the same component:
 *   - signed in, at /portal/projects/{id} (PortalController::show)
 *   - signed out, at the emailed share link (PublicProjectController) — `account` is
 *     null, the sidebar becomes a sign-in panel, and the brief is still readable
 *
 * Design: Redesign/proposal/Guest Project Proposals.dc.html.
 */

import { useMemo, useState } from 'react';
import '../../../css/ozee-ds/index.css';

import { AttentionBox, Tabs, Toast } from '../../ReactComponents/ds';
import { PortalShell } from '../../ReactComponents/portal/PortalChrome';
import { ProjectHero } from '../../ReactComponents/portal/ProjectHero';
import { PhasesTab } from '../../ReactComponents/portal/PhasesTab';
import { ProposalsTab } from '../../ReactComponents/portal/ProposalsTab';
import { BillsTab } from '../../ReactComponents/portal/BillsTab';
import { SignInPanel } from '../../ReactComponents/portal/SignInPanel';
import { ProposalsPanel } from '../../ReactComponents/portal/ProposalsPanel';
import { ProposalModal } from '../../ReactComponents/portal/ProposalModal';
import { BillModal } from '../../ReactComponents/portal/BillModal';
import { usePortalActions, useSignIn } from '../../ReactComponents/portal/usePortal';

/**
 * Escape hatch to the pre-redesign page, shown while the new portal is being proven.
 *
 * A plain <a>, deliberately, not an Inertia <Link>: the classic page is Vue and this
 * one is React, and the two only hand over on a full page load (see resources/js/app.js).
 * An Inertia visit would fetch a Vue component into the React runtime and throw.
 *
 * Disappears on its own when PORTAL_CLASSIC is switched off — the server stops sending
 * `classic_url` at the same moment it unregisters the classic routes, so the link can
 * never point at a 404.
 */
function ClassicVersionNotice({ url }) {
    if (!url) return null;

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 'var(--space-8)',
                flexWrap: 'wrap',
                padding: 'var(--space-8) var(--space-16)',
                borderRadius: 'var(--border-radius-8)',
                border: 'var(--border-width) var(--border-style) var(--layout-border-color)',
                background: 'var(--grey-background-color)',
                color: 'var(--secondary-text-color)',
                font: 'var(--font-text2-normal)',
            }}
        >
            <span>You&rsquo;re on the new version of this page.</span>
            <a
                href={url}
                style={{
                    color: 'var(--link-color)',
                    font: 'var(--font-text2-medium)',
                    whiteSpace: 'nowrap',
                }}
            >
                Go to classic version
            </a>
        </div>
    );
}

export default function Project({ project, proposals: initialProposals, account, paymentMethods, branding, currencies }) {
    const portal = usePortalActions({
        account,
        proposals: initialProposals,
        paymentMethods,
        projectId: project.id,
    });
    const signIn = useSignIn(project.share_code);

    const signedIn = Boolean(portal.profile);
    const proposals = portal.proposals;

    const [mainTab, setMainTab] = useState('phases');
    const [expanded, setExpanded] = useState({});
    const [modal, setModal] = useState(null); // null | { milestoneId?, proposal? }
    const [billFor, setBillFor] = useState(null); // the proposal being invoiced
    const [toast, setToast] = useState('');

    const milestones = project.milestones || [];
    const deliverables = project.deliverables || [];

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
     * Which proposal covers which scope. `proposals` arrives newest-first, so the first
     * match wins — a phase can legitimately have several rows, since re-quoting after a
     * decision creates a new proposal rather than rewriting the old one.
     */
    const proposalByScope = useMemo(() => {
        const map = {};
        proposals.forEach((p) => {
            const key = p.scope === 'project' ? 'project' : p.milestone_id;
            if (key && !map[key]) map[key] = p;
        });
        return map;
    }, [proposals]);

    // A whole-project quote doesn't stop them pricing an individual phase as well.
    const unquotedPhases = useMemo(
        () => milestones.filter((m) => !proposalByScope[m.id]),
        [milestones, proposalByScope],
    );

    const lastUsed = proposals[0]?.currency;
    const primaryCurrency = currencies?.includes(lastUsed) ? lastUsed : currencies?.[0] || 'AUD';
    const billCount = proposals.reduce((n, p) => n + (p.bills || []).length, 0);

    function toggleMilestone(id) {
        setExpanded((prev) => ({ ...prev, [id]: prev[id] !== true }));
    }

    function openProposal(milestoneId) {
        // Editing beats creating: if this phase already has an editable quote, reopen it.
        const existing = proposalByScope[milestoneId];
        setModal(existing && existing.can_edit ? { proposal: existing } : { milestoneId });
    }

    function openBill(proposal) {
        // Nowhere to be paid means the bill can't be completed — send them to set that
        // up first rather than into a form they can't submit.
        if (!portal.paymentMethods.length) {
            window.location.assign('/portal/profile');
            return;
        }
        setBillFor(proposal);
    }

    async function handleProposalSubmit(payload) {
        const result = await portal.submitProposal(payload);
        if (!result.ok) return;

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

    async function handleBillSubmit({ reference, amount, dueDate, methodId, file }) {
        const result = await portal.submitBill({
            proposalId: billFor.id,
            reference,
            amount,
            currency: billFor.currency,
            dueDate,
            methodId,
            file,
        });

        if (!result.ok) return;

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
        <PortalShell
            title={`${project.name} — proposals`}
            branding={branding}
            account={portal.profile}
            current="project"
            onSignOut={signedIn ? portal.signOut : undefined}
            projectContact={project.contact}
        >
            <div className="ozds-portal-page ozds-portal-split">
                {/* ---- Main column ---- */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
                    <ClassicVersionNotice url={project.classic_url} />

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
                            canPropose={signedIn}
                        />
                    ) : null}

                    {mainTab === 'proposals' ? (
                        signedIn ? (
                            <ProposalsTab
                                proposals={proposals}
                                onEdit={(proposal) => setModal({ proposal })}
                                onUploadBill={openBill}
                            />
                        ) : (
                            <AttentionBox title="Sign in to see your proposals" type="dark">
                                Verify your email in the panel on the right and anything you've sent for this project
                                appears here.
                            </AttentionBox>
                        )
                    ) : null}

                    {mainTab === 'bills' ? (
                        signedIn ? (
                            <BillsTab proposals={proposals} currency={primaryCurrency} onUploadBill={openBill} />
                        ) : (
                            <AttentionBox title="Sign in to see your bills" type="dark">
                                Bills raised against your accepted proposals show up here once you've verified your
                                email.
                            </AttentionBox>
                        )
                    ) : null}
                </div>

                {/* ---- Sidebar ---- */}
                <div className="ozds-portal-sidebar">
                    {signedIn ? (
                        <ProposalsPanel
                            proposals={proposals}
                            unquotedPhases={unquotedPhases}
                            currency={primaryCurrency}
                            user={portal.profile}
                            onNewProposal={() =>
                                setModal({ milestoneId: unquotedPhases[0]?.id ?? milestones[0]?.id ?? null })
                            }
                            onProposePhase={openProposal}
                            onGoToTab={setMainTab}
                            onSignOut={portal.signOut}
                        />
                    ) : (
                        <SignInPanel signIn={signIn} companyName={branding?.company?.name} />
                    )}
                </div>
            </div>

            <ProposalModal
                open={Boolean(modal)}
                onClose={() => setModal(null)}
                onSubmit={handleProposalSubmit}
                busy={portal.busy}
                error={portal.error}
                onDismissError={() => portal.setError('')}
                milestones={milestones}
                deliverableCountByMilestone={deliverableCountByMilestone}
                proposalByScope={proposalByScope}
                initial={modal}
                defaultCurrency={primaryCurrency}
                currencies={currencies}
            />

            <BillModal
                open={Boolean(billFor)}
                onClose={() => setBillFor(null)}
                onSubmit={handleBillSubmit}
                busy={portal.busy}
                error={portal.error}
                onDismissError={() => portal.setError('')}
                proposal={billFor}
                paymentMethods={portal.paymentMethods}
                onGoToProfile={() => window.location.assign('/portal/profile')}
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
        </PortalShell>
    );
}
