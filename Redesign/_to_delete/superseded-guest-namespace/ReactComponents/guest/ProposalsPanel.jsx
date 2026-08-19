/**
 * Signed-in sidebar: a scoreboard of the guest's proposals, the phases they haven't
 * quoted yet, and who they're signed in as.
 */

import { AttentionBox, Avatar, Button, Counter, Icon } from '../ds';
import { proposalTone, sumByCurrency } from './format';

function StatusTile({ count, label, color, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                textAlign: 'start',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-small)',
                padding: '8px 10px',
                background: 'transparent',
                cursor: 'pointer',
                transition:
                    'border-color var(--motion-productive-medium) var(--motion-timing-transition), background var(--motion-productive-medium) var(--motion-timing-transition)',
            }}
            onMouseEnter={(e) => {
                e.currentTarget.style.borderColor = 'var(--ui-border-color)';
                e.currentTarget.style.background = 'var(--allgrey-background-color)';
            }}
            onMouseLeave={(e) => {
                e.currentTarget.style.borderColor = 'var(--layout-border-color)';
                e.currentTarget.style.background = 'transparent';
            }}
        >
            <div style={{ font: '700 20px/26px var(--font-family)', color }}>{count}</div>
            <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>{label}</div>
        </button>
    );
}

export function ProposalsPanel({
    proposals,
    unquotedPhases,
    currency,
    user,
    onNewProposal,
    onProposePhase,
    onGoToTab,
    onSignOut,
}) {
    const counts = proposals.reduce((acc, p) => {
        acc[p.status] = (acc[p.status] || 0) + 1;
        return acc;
    }, {});
    const billsSent = proposals.reduce((n, p) => n + (p.bills || []).length, 0);
    // Per-currency, for the same reason as the Bills tab.
    const billedText = sumByCurrency(
        proposals.flatMap((p) => p.bills || []),
        currency,
    );

    return (
        <>
            <section
                style={{
                    background: 'var(--primary-background-color)',
                    border: '1px solid var(--layout-border-color)',
                    borderRadius: 'var(--border-radius-medium)',
                    animation: 'dcRise var(--motion-expressive-long) var(--motion-timing-enter) both',
                }}
            >
                <div
                    style={{
                        padding: 16,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        borderBottom: '1px solid var(--om-hairline)',
                    }}
                >
                    <h2 style={{ font: 'var(--font-h3-medium)', letterSpacing: 'var(--letter-spacing-h3-normal)' }}>
                        Your proposals
                    </h2>
                    <Counter count={proposals.length} kind="fill" color="primary" size="small" />
                    <div style={{ marginInlineStart: 'auto' }}>
                        <Button size="small" onClick={onNewProposal}>
                            New proposal
                        </Button>
                    </div>
                </div>

                <div style={{ padding: '12px 16px', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                    <StatusTile
                        count={counts['Pending Approval'] || 0}
                        label="Under review"
                        color={proposalTone('Pending Approval')}
                        onClick={() => onGoToTab('proposals')}
                    />
                    <StatusTile
                        count={counts.Accepted || 0}
                        label="Accepted"
                        color="var(--positive-color)"
                        onClick={() => onGoToTab('proposals')}
                    />
                    <StatusTile
                        count={billsSent}
                        label="Bills raised"
                        color="var(--primary-text-color)"
                        onClick={() => onGoToTab('bills')}
                    />
                    <StatusTile
                        count={billedText}
                        label="Billed so far"
                        color="var(--primary-text-color)"
                        onClick={() => onGoToTab('bills')}
                    />
                </div>

                <div style={{ padding: '0 16px 12px' }}>
                    <AttentionBox title="One proposal per phase" type="primary" withIcon>
                        Quote one phase, several at once, or the whole project. Each phase is saved and reviewed as
                        its own proposal, and a pending one never blocks the next.
                    </AttentionBox>
                </div>

                {unquotedPhases.length ? (
                    <div style={{ padding: '0 16px 16px' }}>
                        <div
                            style={{
                                border: '1px dashed var(--ui-border-color)',
                                borderRadius: 'var(--border-radius-medium)',
                                padding: 12,
                            }}
                        >
                            <div style={{ font: 'var(--font-text2-medium)' }}>Phases you haven't quoted</div>
                            <div
                                style={{
                                    marginTop: 8,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 6,
                                    maxHeight: 184,
                                    overflowY: 'auto',
                                    overflowX: 'hidden',
                                }}
                            >
                                {unquotedPhases.map((m) => (
                                    <button
                                        key={m.id}
                                        type="button"
                                        onClick={() => onProposePhase(m.id)}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 8,
                                            width: '100%',
                                            padding: 8,
                                            border: 'none',
                                            borderRadius: 'var(--border-radius-small)',
                                            background: 'transparent',
                                            cursor: 'pointer',
                                            textAlign: 'start',
                                            font: 'var(--font-text2-normal)',
                                            color: 'var(--primary-text-color)',
                                            transition:
                                                'background var(--motion-productive-medium) var(--motion-timing-transition), padding-inline-start var(--motion-productive-medium) var(--motion-timing-transition)',
                                        }}
                                        onMouseEnter={(e) => {
                                            e.currentTarget.style.background = 'var(--primary-background-hover-color)';
                                            e.currentTarget.style.paddingInlineStart = '12px';
                                        }}
                                        onMouseLeave={(e) => {
                                            e.currentTarget.style.background = 'transparent';
                                            e.currentTarget.style.paddingInlineStart = '8px';
                                        }}
                                    >
                                        <Icon name="Add" size={16} color="var(--primary-color)" />
                                        <span style={{ flex: 1, minWidth: 0 }}>{m.name}</span>
                                        <Icon
                                            name="NavigationChevronRight"
                                            size={16}
                                            color="var(--secondary-text-color)"
                                        />
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                ) : null}
            </section>

            <section
                style={{
                    background: 'var(--primary-background-color)',
                    border: '1px solid var(--layout-border-color)',
                    borderRadius: 'var(--border-radius-medium)',
                    padding: 16,
                }}
            >
                <div style={{ font: 'var(--font-text2-medium)' }}>Signed in as</div>
                <div style={{ marginTop: 8, display: 'flex', alignItems: 'center', gap: 10 }}>
                    <Avatar text={user.name || user.email} size="large" />
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div
                            style={{
                                font: 'var(--font-text2-medium)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {user.name || 'Guest'}
                        </div>
                        <div
                            style={{
                                font: 'var(--font-text3-normal)',
                                color: 'var(--secondary-text-color)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {user.email}
                            {user.phone ? ` · ${user.phone}` : ''}
                        </div>
                    </div>
                    <Button kind="tertiary" size="small" onClick={onSignOut}>
                        Sign out
                    </Button>
                </div>
            </section>
        </>
    );
}
