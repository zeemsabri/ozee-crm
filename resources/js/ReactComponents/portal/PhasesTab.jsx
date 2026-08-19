/**
 * "Work phases" tab — one card per active milestone, with its deliverables collapsed
 * behind a toggle, plus a card for deliverables that aren't tied to a phase.
 *
 * Progress is derived on the client from deliverable checklists (steps done / total),
 * because the backend has no stored progress figure for a milestone.
 */

import { Button, Counter, Icon, Label, ProgressBar } from '../ds';
import { deliverableTone, milestoneTone, money, plural, proposalTone, statusLabel } from './format';

function Checklist({ items }) {
    if (!items?.length) return null;
    return (
        <div style={{ marginTop: 8, display: 'flex', flexWrap: 'wrap', gap: '6px 20px' }}>
            {items.map((c, i) => (
                <span
                    key={`${c.name}-${i}`}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        font: 'var(--font-text3-normal)',
                        color: c.completed ? 'var(--positive-color)' : 'var(--secondary-text-color)',
                    }}
                >
                    <Icon
                        name={c.completed ? 'Check' : 'Item'}
                        size={14}
                        color={c.completed ? 'var(--positive-color)' : 'var(--secondary-text-color)'}
                    />
                    <span style={{ textDecoration: c.completed ? 'line-through' : 'none' }}>{c.name}</span>
                </span>
            ))}
        </div>
    );
}

function DeliverableRow({ deliverable }) {
    const done = deliverable.checklist_done ?? 0;
    const total = deliverable.checklist_total ?? deliverable.checklist?.length ?? 0;

    return (
        <div
            style={{
                padding: '12px 16px',
                borderTop: '1px solid var(--om-hairline-soft)',
                display: 'flex',
                gap: 12,
                alignItems: 'flex-start',
            }}
        >
            <span
                style={{
                    flex: 'none',
                    marginTop: 5,
                    width: 10,
                    height: 10,
                    borderRadius: '50%',
                    background: deliverableTone(deliverable.status),
                }}
            />
            <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <span style={{ font: 'var(--font-text2-medium)' }}>{deliverable.name}</span>
                    <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                        {statusLabel(deliverable.status)}
                        {deliverable.due_date ? ` · due ${deliverable.due_date}` : ''}
                    </span>
                </div>
                {deliverable.description ? (
                    <div style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                        {deliverable.description}
                    </div>
                ) : null}
                <Checklist items={deliverable.checklist} />
            </div>
            {total ? (
                <span
                    style={{
                        flex: 'none',
                        font: 'var(--font-text3-normal)',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {done}/{total}
                </span>
            ) : null}
        </div>
    );
}

function PhaseCard({ milestone, deliverables, proposal, isOwnProposal, expanded, onToggle, onPropose, canPropose }) {
    const steps = deliverables.reduce((n, d) => n + (d.checklist_total ?? d.checklist?.length ?? 0), 0);
    const doneSteps = deliverables.reduce((n, d) => n + (d.checklist_done ?? 0), 0);
    const pct = steps ? Math.round((doneSteps / steps) * 100) : 0;
    const tone = milestoneTone(milestone.status);

    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                overflow: 'hidden',
                animation: 'dcRise var(--motion-expressive-long) var(--motion-timing-enter) both',
            }}
        >
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, padding: 16, borderInlineStart: `3px solid ${tone}` }}>
                <div style={{ flex: '1 1 320px', minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <h3 style={{ font: 'var(--font-h3-medium)', letterSpacing: 'var(--letter-spacing-h3-normal)' }}>
                            {milestone.name}
                        </h3>
                        <Label text={statusLabel(milestone.status)} color={tone} size="small" />
                    </div>

                    {milestone.description ? (
                        <p
                            style={{
                                marginTop: 6,
                                maxWidth: '70ch',
                                font: 'var(--font-text2-normal)',
                                color: 'var(--secondary-text-color)',
                                textWrap: 'pretty',
                                whiteSpace: 'pre-line',
                            }}
                        >
                            {milestone.description}
                        </p>
                    ) : null}

                    <div
                        style={{
                            marginTop: 8,
                            display: 'flex',
                            alignItems: 'center',
                            flexWrap: 'wrap',
                            gap: 6,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {milestone.completion_date ? (
                            <>
                                <Icon name="DueDate" size={14} color="var(--secondary-text-color)" />
                                <span>Target {milestone.completion_date}</span>
                                <span style={{ color: 'var(--ui-border-color)' }}>·</span>
                            </>
                        ) : null}
                        <span>{plural(deliverables.length, 'deliverable')}</span>
                    </div>

                    {steps ? (
                        <div style={{ marginTop: 10, maxWidth: 320, display: 'flex', alignItems: 'center', gap: 10 }}>
                            <ProgressBar
                                value={pct}
                                max={100}
                                color={pct === 100 ? 'positive' : 'primary'}
                                size="small"
                            />
                            <span
                                style={{
                                    font: 'var(--font-text3-medium)',
                                    color: 'var(--secondary-text-color)',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {doneSteps}/{steps} steps
                            </span>
                        </div>
                    ) : null}
                </div>

                <div
                    style={{
                        flex: 'none',
                        width: 200,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'flex-end',
                        gap: 8,
                    }}
                >
                    {proposal ? (
                        <div
                            style={{
                                width: '100%',
                                padding: '8px 10px',
                                borderRadius: 'var(--border-radius-small)',
                                background: 'var(--grey-background-color)',
                                textAlign: 'end',
                            }}
                        >
                            <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                                {isOwnProposal ? 'Your proposal' : 'Covered by your whole-project proposal'}
                            </div>
                            <div style={{ font: 'var(--font-text2-medium)' }}>
                                {money(proposal.amount, proposal.currency)} {proposal.currency}
                            </div>
                            <div style={{ marginTop: 4, display: 'flex', justifyContent: 'flex-end' }}>
                                <Label
                                    text={proposal.status}
                                    color={proposalTone(proposal.status)}
                                    kind="line"
                                    size="small"
                                />
                            </div>
                        </div>
                    ) : null}

                    {/* A whole-project quote doesn't preclude pricing this phase on its
                        own, so the action stays available until the phase itself is
                        quoted. */}
                    {!isOwnProposal && canPropose ? (
                        <Button kind="secondary" size="small" onClick={onPropose}>
                            {proposal ? 'Also quote this phase' : 'Propose for this phase'}
                        </Button>
                    ) : null}
                </div>
            </div>

            {deliverables.length ? (
                <div style={{ borderTop: '1px solid var(--om-hairline)', background: 'var(--allgrey-background-color)' }}>
                    <button
                        type="button"
                        aria-expanded={expanded}
                        onClick={onToggle}
                        style={{
                            width: '100%',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '8px 16px',
                            border: 'none',
                            background: 'transparent',
                            cursor: 'pointer',
                            font: 'var(--font-text3-medium)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <span
                            style={{
                                display: 'inline-flex',
                                transition: 'transform var(--motion-productive-long) var(--motion-timing-transition)',
                                transform: `rotate(${expanded ? 90 : 0}deg)`,
                            }}
                        >
                            <Icon name="DropdownChevronRight" size={16} color="currentColor" />
                        </span>
                        <span>
                            {expanded ? 'Hide ' : 'Show '}
                            {plural(deliverables.length, 'deliverable')}
                        </span>
                    </button>
                    {expanded
                        ? deliverables.map((d) => <DeliverableRow key={d.id} deliverable={d} />)
                        : null}
                </div>
            ) : null}
        </section>
    );
}

export function PhasesTab({
    milestones,
    deliverablesByMilestone,
    looseDeliverables,
    proposalByScope,
    expanded,
    onToggle,
    onPropose,
    canPropose,
}) {
    return (
        <>
            <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 8, marginTop: 8 }}>
                <h2 style={{ font: 'var(--font-h2-medium)', letterSpacing: 'var(--letter-spacing-h2-normal)' }}>
                    Work phases
                </h2>
                <Counter count={milestones.length} kind="line" color="dark" size="small" />
                <span
                    style={{
                        marginInlineStart: 'auto',
                        font: 'var(--font-text2-normal)',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    Completed and cancelled phases are hidden
                </span>
            </div>

            {milestones.length === 0 ? (
                <section
                    style={{
                        background: 'var(--primary-background-color)',
                        border: '1px solid var(--layout-border-color)',
                        borderRadius: 'var(--border-radius-medium)',
                        padding: 24,
                        textAlign: 'center',
                    }}
                >
                    <div style={{ font: 'var(--font-text1-medium)' }}>No active phases right now</div>
                    <div style={{ marginTop: 4, font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                        You can still quote for the whole project.
                    </div>
                </section>
            ) : null}

            {milestones.map((m) => (
                <PhaseCard
                    key={m.id}
                    milestone={m}
                    deliverables={deliverablesByMilestone[m.id] || []}
                    proposal={proposalByScope[m.id] || proposalByScope.project || null}
                    isOwnProposal={Boolean(proposalByScope[m.id])}
                    expanded={expanded[m.id] === true}
                    onToggle={() => onToggle(m.id)}
                    onPropose={() => onPropose(m.id)}
                    canPropose={canPropose}
                />
            ))}

            {looseDeliverables.length ? (
                <section
                    style={{
                        background: 'var(--primary-background-color)',
                        border: '1px solid var(--layout-border-color)',
                        borderRadius: 'var(--border-radius-medium)',
                        padding: 16,
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4 }}>
                        <Icon name="Item" size={16} color="var(--secondary-text-color)" />
                        <h3 style={{ font: 'var(--font-h3-medium)', letterSpacing: 'var(--letter-spacing-h3-normal)' }}>
                            Not tied to a phase
                        </h3>
                    </div>
                    {looseDeliverables.map((d) => (
                        <div
                            key={d.id}
                            style={{
                                padding: '10px 0',
                                borderTop: '1px solid var(--om-hairline-soft)',
                                display: 'flex',
                                gap: 12,
                                alignItems: 'center',
                                flexWrap: 'wrap',
                            }}
                        >
                            <span
                                style={{
                                    flex: 'none',
                                    width: 10,
                                    height: 10,
                                    borderRadius: '50%',
                                    background: deliverableTone(d.status),
                                }}
                            />
                            <div style={{ flex: '1 1 200px', minWidth: 0 }}>
                                <div style={{ font: 'var(--font-text2-medium)' }}>{d.name}</div>
                                {d.description ? (
                                    <div
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        {d.description}
                                    </div>
                                ) : null}
                            </div>
                            <span
                                style={{
                                    flex: 'none',
                                    font: 'var(--font-text3-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                {statusLabel(d.status)}
                                {d.due_date ? ` · due ${d.due_date}` : ''}
                            </span>
                        </div>
                    ))}
                </section>
            ) : null}
        </>
    );
}
