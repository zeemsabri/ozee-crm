/**
 * The proposal composer.
 *
 * Scope is the thing that makes this more than a form: a guest can quote one phase,
 * several phases at once, or the whole project. "Several phases" is sent as one
 * proposal per phase — each keeps its own amount and is reviewed on its own — which
 * matches how project_expendables are modelled (one row per milestone).
 *
 * Payment terms are serialised into the JSON shape the existing Vue PaymentTermsBuilder
 * uses, so admin-side readers of project_expendables.payment_terms keep working.
 * See resources/js/ReactComponents/portal/format.js for that contract.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import { AttentionBox, Button, Dropdown, Icon, IconButton, Modal, TextArea, TextField } from '../ds';
import { CURRENCIES, PAY_TYPES, evenPercentages, money, num, plural } from './format';

const SCOPE_OPTIONS = [
    { value: 'milestone', text: 'One phase' },
    { value: 'milestones', text: 'Several phases' },
    { value: 'project', text: 'Whole project' },
];

const MAX_DOC_BYTES = 10 * 1024 * 1024;

function ScopeSwitch({ value, onChange, disabled }) {
    return (
        <div
            role="group"
            style={{
                display: 'inline-flex',
                border: '1px solid var(--ui-border-color)',
                borderRadius: 'var(--border-radius-small)',
                overflow: 'hidden',
            }}
        >
            {SCOPE_OPTIONS.map((o, i) => {
                const selected = o.value === value;
                return (
                    <button
                        key={o.value}
                        type="button"
                        aria-pressed={selected}
                        disabled={disabled}
                        onClick={() => onChange(o.value)}
                        style={{
                            height: 32,
                            padding: '0 var(--space-12)',
                            border: 'none',
                            borderInlineStart: i === 0 ? 'none' : '1px solid var(--ui-border-color)',
                            background: selected ? 'var(--primary-selected-color)' : 'transparent',
                            color: disabled
                                ? 'var(--disabled-text-color)'
                                : selected
                                  ? 'var(--primary-color)'
                                  : 'var(--primary-text-color)',
                            font: 'var(--font-text2-normal)',
                            cursor: disabled ? 'not-allowed' : 'pointer',
                        }}
                    >
                        {o.text}
                    </button>
                );
            })}
        </div>
    );
}

function InfoStrip({ children, style }) {
    return (
        <div
            style={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                gap: '8px 16px',
                padding: '10px 12px',
                borderRadius: 'var(--border-radius-small)',
                background: 'var(--allgrey-background-color)',
                border: '1px solid var(--om-hairline)',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

const emptyTerms = () => ({
    type: 'fixed',
    rows: [{ label: 'Full payment', pct: '100' }],
    monthlyRate: '',
    months: '6',
    hourlyRate: '',
    hours: '',
    notes: '',
});

/**
 * Retainer and hourly derive the proposal's value from their own inputs (rate × months,
 * rate × hours) rather than from a separate Amount field, so the figure the guest is
 * shown is the figure that reaches project_expendables.amount.
 */
const DERIVED_AMOUNT_TYPES = ['retainer', 'hourly'];

export function ProposalModal({
    open,
    onClose,
    onSubmit,
    busy,
    error,
    onDismissError,
    milestones,
    deliverableCountByMilestone,
    proposalByScope,
    initial,
    defaultCurrency = 'AUD',
    // Server-provided so the page can only offer what storeProposal will accept.
    currencies = CURRENCIES,
}) {
    const [scope, setScope] = useState('milestone');
    const [milestoneId, setMilestoneId] = useState(null);
    const [milestoneIds, setMilestoneIds] = useState([]);
    const [description, setDescription] = useState('');
    const [amount, setAmount] = useState('');
    const [phaseAmounts, setPhaseAmounts] = useState({});
    const [splitTotal, setSplitTotal] = useState('');
    const [currency, setCurrency] = useState(defaultCurrency);
    const [terms, setTerms] = useState(emptyTerms);
    const [doc, setDoc] = useState(null);
    const [docError, setDocError] = useState('');
    const fileInput = useRef(null);

    const editingId = initial?.proposal?.id ?? null;

    // Seed the form each time the modal opens. `initial` carries either a proposal to
    // edit or a milestone the guest clicked "propose" on.
    useEffect(() => {
        if (!open) return;

        const existing = initial?.proposal || null;
        setDocError('');
        setDoc(null);
        setSplitTotal('');
        setPhaseAmounts({});

        if (existing) {
            setScope(existing.scope === 'project' ? 'project' : 'milestone');
            setMilestoneId(existing.milestone_id ?? null);
            setMilestoneIds(existing.milestone_id ? [existing.milestone_id] : []);
            setDescription(existing.description || '');
            setAmount(existing.amount != null ? String(Number(existing.amount)) : '');
            setCurrency(existing.currency || defaultCurrency);
            setTerms(termsFromStored(existing.payment_terms));
            return;
        }

        const target = initial?.milestoneId ?? null;
        const quotable = milestones.filter((m) => !proposalByScope[m.id] || proposalByScope[m.id].can_edit);
        setScope(target ? 'milestone' : milestones.length ? 'milestone' : 'project');
        setMilestoneId(
            target ??
                milestones.find((m) => !proposalByScope[m.id])?.id ??
                quotable[0]?.id ??
                milestones[0]?.id ??
                null,
        );
        setMilestoneIds(target ? [target] : []);
        setDescription('');
        setAmount('');
        setCurrency(defaultCurrency);
        setTerms(emptyTerms());
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initial]);

    const selectedPhases = useMemo(
        () => milestones.filter((m) => milestoneIds.includes(m.id)),
        [milestones, milestoneIds],
    );
    const currentPhase = milestones.find((m) => m.id === milestoneId) || null;

    const phaseOptions = milestones.map((m) => {
        const existing = proposalByScope[m.id];
        const accepted = existing?.status === 'Accepted';
        return {
            value: m.id,
            label: `${m.name}${accepted ? '  ·  accepted' : existing ? '  ·  quoted' : ''}`,
            color: accepted ? 'var(--positive-color)' : existing ? 'var(--primary-color)' : undefined,
        };
    });

    /**
     * Phases whose existing proposal has been decided on (accepted, rejected, completed,
     * shortlisted). The server refuses to overwrite those rows, so quoting one again
     * would silently create a second proposal — block it in every entry point, not just
     * the single-phase dropdown.
     */
    const lockedIds = useMemo(() => {
        const set = new Set();
        milestones.forEach((m) => {
            const p = proposalByScope[m.id];
            if (p && !p.can_edit) set.add(m.id);
        });
        return set;
    }, [milestones, proposalByScope]);

    const multiPhaseOptions = milestones
        .filter((m) => !lockedIds.has(m.id))
        .map((m) => ({
            value: m.id,
            label: `${m.name}${proposalByScope[m.id] ? '  ·  quoted' : ''}`,
        }));

    const isMulti = scope === 'milestones';
    const isProject = scope === 'project';
    const phaseLocked = Boolean(currentPhase && lockedIds.has(currentPhase.id) && !editingId);

    // A proposal may carry a currency the server no longer offers (admin-created, or
    // predating the current list). Keep it selectable so editing doesn't silently blank
    // the field.
    const currencyOptions = useMemo(() => {
        const list = currencies.includes(currency) ? currencies : [...currencies, currency];
        return list.filter(Boolean).map((c) => ({ value: c, label: c }));
    }, [currencies, currency]);

    const derivesAmount = !isMulti && DERIVED_AMOUNT_TYPES.includes(terms.type);
    const total = isMulti
        ? selectedPhases.reduce((n, m) => n + num(phaseAmounts[m.id]), 0)
        : terms.type === 'retainer'
          ? num(terms.monthlyRate) * num(terms.months)
          : terms.type === 'hourly'
            ? num(terms.hourlyRate) * num(terms.hours)
            : num(amount);

    // Count characters, not UTF-16 code units — the server's min:20 uses mb_strlen, so
    // `description.length` would let a cover letter of 12 emoji through and then 422.
    const descLength = [...description].length;
    const descTooShort = descLength > 0 && descLength < 20;

    const isSplitTerms = terms.type === 'installments' || terms.type === 'milestone';
    const pctTotal = Math.round(terms.rows.reduce((n, r) => n + num(r.pct), 0) * 10) / 10;
    const pctOk = pctTotal === 100;

    // The server's floor is `min:1` per amount, so mirror that exactly rather than > 0.
    const amountsIncomplete =
        isMulti && (selectedPhases.length === 0 || selectedPhases.some((m) => num(phaseAmounts[m.id]) < 1));

    const termsIncomplete =
        (terms.type === 'retainer' && (num(terms.monthlyRate) < 1 || num(terms.months) < 1)) ||
        (terms.type === 'hourly' && (num(terms.hourlyRate) <= 0 || num(terms.hours) <= 0)) ||
        (isSplitTerms && !pctOk);

    const canSubmit =
        !busy &&
        descLength >= 20 &&
        !termsIncomplete &&
        (isMulti ? !amountsIncomplete : total >= 1) &&
        (isMulti
            ? !selectedPhases.some((m) => lockedIds.has(m.id))
            : isProject || (Boolean(milestoneId) && !phaseLocked));

    function setRow(i, patch) {
        setTerms((t) => ({ ...t, rows: t.rows.map((r, j) => (j === i ? { ...r, ...patch } : r)) }));
    }

    function changePayType(value) {
        setTerms((t) => {
            if (value === 'fixed') return { ...t, type: value, rows: [{ label: 'Full payment', pct: '100' }] };
            if (value === 'installments') {
                return {
                    ...t,
                    type: value,
                    rows: [
                        { label: 'Kickoff', pct: '50' },
                        { label: 'Final delivery', pct: '50' },
                    ],
                };
            }
            if (value === 'milestone') {
                // One stage per phase in scope, evenly split.
                const inScope = isMulti ? selectedPhases : isProject ? milestones : currentPhase ? [currentPhase] : [];
                const pcts = evenPercentages(inScope.length || 1);
                return {
                    ...t,
                    type: value,
                    rows: inScope.length
                        ? inScope.map((m, i) => ({ label: m.name, pct: String(pcts[i]) }))
                        : [{ label: 'Full payment', pct: '100' }],
                };
            }
            if (value === 'retainer') {
                return { ...t, type: value, monthlyRate: t.monthlyRate || amount || '' };
            }
            return { ...t, type: value };
        });
    }

    function pickFile(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        if (file.type !== 'application/pdf') {
            setDoc(null);
            setDocError('That needs to be a PDF.');
            event.target.value = '';
            return;
        }
        if (file.size > MAX_DOC_BYTES) {
            setDoc(null);
            setDocError('That file is over 10MB.');
            event.target.value = '';
            return;
        }
        setDocError('');
        setDoc(file);
    }

    function splitEvenly() {
        if (!selectedPhases.length) return;
        const target = num(splitTotal) || selectedPhases.reduce((n, m) => n + num(phaseAmounts[m.id]), 0);
        if (!target) return;
        const each = Math.floor((target / selectedPhases.length) * 100) / 100;
        const next = { ...phaseAmounts };
        selectedPhases.forEach((m, i) => {
            next[m.id] =
                i === selectedPhases.length - 1
                    ? String(Math.round((target - each * (selectedPhases.length - 1)) * 100) / 100)
                    : String(each);
        });
        setPhaseAmounts(next);
    }

    function submit() {
        onSubmit({
            scope,
            milestoneId,
            milestoneIds,
            // `total` rather than the raw field: retainer and hourly derive their value.
            amount: total,
            amounts: phaseAmounts,
            description,
            currency,
            paymentTerms: termsToStored(terms, total, currency),
            document: doc,
            editingId,
        });
    }

    const scopeCountLabel = `${plural(milestones.length, 'active phase')} · ${
        milestones.filter((m) => proposalByScope[m.id]).length
    } already quoted`;

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="large"
            title={editingId ? 'Update your proposal' : 'New proposal'}
            description="Quote one phase, several at once, or the whole project. Several phases are saved as one proposal each, so every phase carries its own price."
            footer={
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, width: '100%' }}>
                    <span
                        style={{
                            flex: 1,
                            minWidth: 0,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {isMulti && selectedPhases.length > 1
                            ? `${plural(selectedPhases.length, 'proposal')} will be sent — one per phase.`
                            : ''}
                    </span>
                    <Button kind="tertiary" onClick={onClose} disabled={busy}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={!canSubmit} loading={busy}>
                        {editingId
                            ? 'Update proposal'
                            : isMulti && selectedPhases.length > 1
                              ? `Send ${plural(selectedPhases.length, 'proposal')}`
                              : 'Send proposal'}
                    </Button>
                </div>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                {error ? (
                    <AttentionBox type="danger" onClose={onDismissError}>
                        {error}
                    </AttentionBox>
                ) : null}

                {/* ---- Scope ---- */}
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8, flexWrap: 'wrap' }}>
                        <span style={{ font: 'var(--font-text2-medium)' }}>What does this proposal cover?</span>
                        <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            {scopeCountLabel}
                        </span>
                    </div>

                    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                        <ScopeSwitch
                            value={scope}
                            onChange={(v) => {
                                setScope(v);
                                if (v === 'milestones') {
                                    // Carry the single selection over, but never a phase
                                    // that can't be re-quoted.
                                    const carried = milestoneIds.length
                                        ? milestoneIds
                                        : milestoneId
                                          ? [milestoneId]
                                          : [];
                                    setMilestoneIds(carried.filter((id) => !lockedIds.has(id)));
                                }
                                if (v === 'milestone' && !milestoneId && milestoneIds.length) {
                                    setMilestoneId(milestoneIds[0]);
                                }
                            }}
                            disabled={Boolean(editingId) || milestones.length === 0}
                        />

                        {scope === 'milestone' ? (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                    animation: 'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                                }}
                            >
                                <Dropdown
                                    options={phaseOptions}
                                    value={milestoneId}
                                    onChange={setMilestoneId}
                                    placeholder="Pick a phase"
                                    searchable
                                    // The server matches an existing proposal by its
                                    // phase, so moving one to a different phase would
                                    // create a second proposal instead of updating this
                                    // one. Withdraw and re-quote instead.
                                    disabled={Boolean(editingId)}
                                />
                                {currentPhase ? (
                                    <InfoStrip>
                                        {currentPhase.completion_date ? (
                                            <span
                                                style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: 6,
                                                    font: 'var(--font-text3-normal)',
                                                    color: 'var(--secondary-text-color)',
                                                }}
                                            >
                                                <Icon name="DueDate" size={14} color="var(--secondary-text-color)" />
                                                <span>Target {currentPhase.completion_date}</span>
                                            </span>
                                        ) : null}
                                        <span
                                            style={{
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            {plural(
                                                deliverableCountByMilestone[currentPhase.id] || 0,
                                                'deliverable',
                                            )}{' '}
                                            in this phase
                                        </span>
                                        <span
                                            style={{
                                                marginInlineStart: 'auto',
                                                font: 'var(--font-text3-medium)',
                                                color: phaseLocked
                                                    ? 'var(--negative-color)'
                                                    : proposalByScope[currentPhase.id]
                                                      ? 'var(--primary-color)'
                                                      : 'var(--secondary-text-color)',
                                            }}
                                        >
                                            {phaseLocked
                                                ? 'Already accepted — pick another phase'
                                                : proposalByScope[currentPhase.id]
                                                  ? 'Replaces your pending proposal'
                                                  : 'No proposal from you yet'}
                                        </span>
                                    </InfoStrip>
                                ) : null}
                            </div>
                        ) : null}

                        {isMulti ? (
                            <div
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 8,
                                    animation: 'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                                }}
                            >
                                <Dropdown
                                    options={multiPhaseOptions}
                                    value={milestoneIds}
                                    onChange={(v) => setMilestoneIds(Array.isArray(v) ? v : v ? [v] : [])}
                                    placeholder="Pick the phases this covers"
                                    multi
                                    searchable
                                    clearable
                                />
                                <InfoStrip>
                                    <span style={{ font: 'var(--font-text3-medium)' }}>
                                        {selectedPhases.length
                                            ? `${selectedPhases.length} of ${milestones.length} phases selected`
                                            : 'No phases selected yet'}
                                    </span>
                                    <span
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        {plural(
                                            selectedPhases.reduce(
                                                (n, m) => n + (deliverableCountByMilestone[m.id] || 0),
                                                0,
                                            ),
                                            'deliverable',
                                        )}{' '}
                                        in scope
                                    </span>
                                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 4 }}>
                                        <Button
                                            kind="tertiary"
                                            size="xs"
                                            onClick={() =>
                                                setMilestoneIds(
                                                    milestones
                                                        .filter(
                                                            (m) => proposalByScope[m.id]?.status !== 'Accepted',
                                                        )
                                                        .map((m) => m.id),
                                                )
                                            }
                                        >
                                            Select all
                                        </Button>
                                        <Button kind="tertiary" size="xs" onClick={() => setMilestoneIds([])}>
                                            Clear
                                        </Button>
                                    </div>
                                </InfoStrip>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        font: 'var(--font-text3-normal)',
                                        color: 'var(--secondary-text-color)',
                                    }}
                                >
                                    <Icon name="Info" size={14} color="var(--secondary-text-color)" />
                                    <span>
                                        Sent as one proposal per phase — each keeps its own amount, sharing this cover
                                        letter and payment terms.
                                    </span>
                                </div>
                            </div>
                        ) : null}

                        {isProject ? (
                            <InfoStrip
                                style={{
                                    animation: 'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                                }}
                            >
                                <Icon name="Info" size={16} color="var(--secondary-text-color)" />
                                <span
                                    style={{
                                        font: 'var(--font-text3-normal)',
                                        color: 'var(--secondary-text-color)',
                                    }}
                                >
                                    One price covering all {plural(milestones.length, 'active phase')}. Any phase
                                    quotes you've already sent stay as they are.
                                </span>
                            </InfoStrip>
                        ) : null}
                    </div>
                </div>

                {/* ---- Cover letter ---- */}
                <TextArea
                    label="Cover letter"
                    placeholder="How you'd approach this work, what's included, and how long it will take."
                    rows={6}
                    maxLength={5000}
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    validation={descTooShort ? 'error' : undefined}
                    subText={
                        descTooShort
                            ? 'At least 20 characters'
                            : `${descLength.toLocaleString('en-AU')} / 5,000 characters`
                    }
                />

                {/* ---- Amount ---- */}
                {!isMulti ? (
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 140px', gap: 12, alignItems: 'start' }}>
                        {derivesAmount ? (
                            <TextField
                                label="Proposal value"
                                value={money(total, currency)}
                                readOnly
                                subText={
                                    terms.type === 'retainer'
                                        ? 'Monthly rate × number of months, set in Payment terms below'
                                        : 'Hourly rate × estimated hours, set in Payment terms below'
                                }
                            />
                        ) : (
                            <TextField
                                label="Amount"
                                placeholder="0.00"
                                inputMode="decimal"
                                required
                                value={amount}
                                onChange={(e) => setAmount(e.target.value.replace(/[^0-9.]/g, ''))}
                            />
                        )}
                        <Dropdown
                            label="Currency"
                            options={currencyOptions}
                            value={currency}
                            onChange={setCurrency}
                        />
                    </div>
                ) : (
                    <div>
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                marginBottom: 8,
                                flexWrap: 'wrap',
                            }}
                        >
                            <span style={{ font: 'var(--font-text2-medium)' }}>Amount per phase</span>
                            <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                                Each phase is priced and reviewed on its own
                            </span>
                            <div style={{ marginInlineStart: 'auto', width: 120 }}>
                                <Dropdown
                                    options={currencyOptions}
                                    value={currency}
                                    onChange={setCurrency}
                                    size="small"
                                />
                            </div>
                        </div>

                        <div
                            style={{
                                border: '1px solid var(--layout-border-color)',
                                borderRadius: 'var(--border-radius-small)',
                                background: 'var(--allgrey-background-color)',
                                padding: 12,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 8,
                            }}
                        >
                            {selectedPhases.map((m) => (
                                <div
                                    key={m.id}
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 140px',
                                        gap: 12,
                                        alignItems: 'center',
                                        animation:
                                            'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                                    }}
                                >
                                    <div style={{ minWidth: 0 }}>
                                        <div style={{ font: 'var(--font-text2-medium)' }}>{m.name}</div>
                                        <div
                                            style={{
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            {m.completion_date ? `Target ${m.completion_date} · ` : ''}
                                            {plural(deliverableCountByMilestone[m.id] || 0, 'deliverable')}
                                        </div>
                                    </div>
                                    <TextField
                                        size="small"
                                        placeholder="0.00"
                                        inputMode="decimal"
                                        value={phaseAmounts[m.id] || ''}
                                        validation={num(phaseAmounts[m.id]) <= 0 ? 'error' : undefined}
                                        onChange={(e) =>
                                            setPhaseAmounts((prev) => ({
                                                ...prev,
                                                [m.id]: e.target.value.replace(/[^0-9.]/g, ''),
                                            }))
                                        }
                                    />
                                </div>
                            ))}

                            {selectedPhases.length === 0 ? (
                                <div style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                                    Pick the phases above and their prices appear here.
                                </div>
                            ) : null}

                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    flexWrap: 'wrap',
                                    paddingTop: 8,
                                    borderTop: '1px solid var(--om-hairline)',
                                }}
                            >
                                {selectedPhases.length ? (
                                    <>
                                        <span
                                            style={{
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            Total across {plural(selectedPhases.length, 'proposal')}
                                        </span>
                                        <span style={{ font: 'var(--font-text1-bold)' }}>
                                            {money(total, currency)} {currency}
                                        </span>
                                    </>
                                ) : null}
                                <div
                                    style={{
                                        marginInlineStart: 'auto',
                                        display: 'flex',
                                        flexWrap: 'wrap',
                                        alignItems: 'center',
                                        gap: 8,
                                    }}
                                >
                                    <div style={{ width: 130 }}>
                                        <TextField
                                            size="small"
                                            placeholder="Total to split"
                                            inputMode="decimal"
                                            value={splitTotal}
                                            onChange={(e) => setSplitTotal(e.target.value.replace(/[^0-9.]/g, ''))}
                                        />
                                    </div>
                                    <Button
                                        kind="secondary"
                                        size="small"
                                        onClick={splitEvenly}
                                        disabled={!selectedPhases.length}
                                    >
                                        Split evenly
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ---- Payment terms ---- */}
                <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8, flexWrap: 'wrap' }}>
                        <span style={{ font: 'var(--font-text2-medium)' }}>Payment terms</span>
                        <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            {isMulti
                                ? "Saved on every proposal in this batch — shares apply to each phase's own amount"
                                : "How you'd like to be paid"}
                        </span>
                    </div>

                    <div
                        style={{
                            border: '1px solid var(--layout-border-color)',
                            borderRadius: 'var(--border-radius-small)',
                            background: 'var(--allgrey-background-color)',
                            padding: 12,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 12,
                        }}
                    >
                        <Dropdown
                            options={
                                isMulti
                                    ? // Per-phase amounts already define the value, so the
                                      // whole-engagement pricing models don't apply here.
                                      PAY_TYPES.filter((t) => ['fixed', 'installments'].includes(t.value))
                                    : PAY_TYPES
                            }
                            value={terms.type}
                            onChange={changePayType}
                        />

                        {terms.type === 'fixed' ? (
                            <div
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 10,
                                    padding: '10px 12px',
                                    borderRadius: 'var(--border-radius-small)',
                                    background: 'var(--primary-background-color)',
                                    border: '1px solid var(--om-hairline)',
                                }}
                            >
                                <Icon name="Info" size={16} color="var(--secondary-text-color)" />
                                <span style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                                    100% of{' '}
                                    <strong style={{ color: 'var(--primary-text-color)' }}>
                                        {money(total, currency)} {currency}
                                    </strong>{' '}
                                    due on final delivery and sign-off.
                                </span>
                            </div>
                        ) : null}

                        {isSplitTerms ? (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 88px 110px 32px',
                                        gap: 8,
                                        font: 'var(--font-text3-medium)',
                                        color: 'var(--secondary-text-color)',
                                    }}
                                >
                                    <span>Stage</span>
                                    <span>Share</span>
                                    <span style={{ textAlign: 'end' }}>{isMulti ? 'Of each' : 'Amount'}</span>
                                    <span />
                                </div>

                                {terms.rows.map((r, i) => (
                                    <div
                                        key={i}
                                        style={{
                                            display: 'grid',
                                            gridTemplateColumns: '1fr 88px 110px 32px',
                                            gap: 8,
                                            alignItems: 'center',
                                            animation:
                                                'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                                        }}
                                    >
                                        <TextField
                                            size="small"
                                            placeholder="e.g. Kickoff"
                                            value={r.label}
                                            onChange={(e) => setRow(i, { label: e.target.value })}
                                        />
                                        <TextField
                                            size="small"
                                            placeholder="0 %"
                                            inputMode="decimal"
                                            value={r.pct}
                                            validation={num(r.pct) <= 0 ? 'error' : undefined}
                                            onChange={(e) =>
                                                setRow(i, { pct: e.target.value.replace(/[^0-9.]/g, '').slice(0, 5) })
                                            }
                                        />
                                        <span style={{ textAlign: 'end', font: 'var(--font-text2-medium)' }}>
                                            {isMulti
                                                ? `${num(r.pct)}% of each`
                                                : money(Math.round(total * num(r.pct)) / 100, currency)}
                                        </span>
                                        <IconButton
                                            name="Delete"
                                            size="small"
                                            kind="tertiary"
                                            ariaLabel={`Remove ${r.label || 'stage'}`}
                                            disabled={terms.rows.length <= 1}
                                            onClick={() =>
                                                setTerms((t) => ({
                                                    ...t,
                                                    rows: t.rows.filter((_, j) => j !== i),
                                                }))
                                            }
                                        />
                                    </div>
                                ))}

                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                        paddingTop: 8,
                                        borderTop: '1px solid var(--om-hairline)',
                                    }}
                                >
                                    <span
                                        style={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            padding: '3px 8px',
                                            borderRadius: 'var(--border-radius-small)',
                                            background: pctOk ? 'rgba(0,133,77,0.12)' : 'rgba(216,58,82,0.12)',
                                            color: pctOk ? 'var(--positive-color)' : 'var(--negative-color)',
                                            font: 'var(--font-text3-medium)',
                                        }}
                                    >
                                        <Icon name={pctOk ? 'Check' : 'Warning'} size={14} color="currentColor" />
                                        <span>{pctTotal}% of 100%</span>
                                    </span>
                                    <span
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        {pctOk
                                            ? 'Adds up — each stage is invoiced on sign-off.'
                                            : pctTotal > 100
                                              ? `${Math.round((pctTotal - 100) * 10) / 10}% over`
                                              : `${Math.round((100 - pctTotal) * 10) / 10}% left to allocate`}
                                    </span>
                                    <div style={{ marginInlineStart: 'auto', display: 'flex', gap: 8 }}>
                                        <Button
                                            kind="tertiary"
                                            size="small"
                                            onClick={() =>
                                                setTerms((t) => {
                                                    const pcts = evenPercentages(t.rows.length);
                                                    return {
                                                        ...t,
                                                        rows: t.rows.map((r, i) => ({ ...r, pct: String(pcts[i]) })),
                                                    };
                                                })
                                            }
                                        >
                                            Distribute evenly
                                        </Button>
                                        <Button
                                            kind="secondary"
                                            size="small"
                                            onClick={() =>
                                                setTerms((t) => ({ ...t, rows: [...t.rows, { label: '', pct: '0' }] }))
                                            }
                                        >
                                            Add stage
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ) : null}

                        {terms.type === 'retainer' ? (
                            <>
                                <div
                                    style={{
                                        display: 'grid',
                                        gridTemplateColumns: '1fr 1fr',
                                        gap: 12,
                                        alignItems: 'start',
                                    }}
                                >
                                    <TextField
                                        label="Monthly rate"
                                        placeholder="0.00"
                                        inputMode="decimal"
                                        required
                                        value={terms.monthlyRate}
                                        validation={num(terms.monthlyRate) < 1 ? 'error' : undefined}
                                        onChange={(e) =>
                                            setTerms((t) => ({
                                                ...t,
                                                monthlyRate: e.target.value.replace(/[^0-9.]/g, ''),
                                            }))
                                        }
                                    />
                                    <TextField
                                        label="Number of months"
                                        placeholder="6"
                                        inputMode="numeric"
                                        value={terms.months}
                                        validation={num(terms.months) < 1 ? 'error' : undefined}
                                        onChange={(e) =>
                                            setTerms((t) => ({
                                                ...t,
                                                months: e.target.value.replace(/[^0-9]/g, '').slice(0, 3),
                                            }))
                                        }
                                    />
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'baseline',
                                        gap: 8,
                                        padding: '10px 12px',
                                        borderRadius: 'var(--border-radius-small)',
                                        background: 'var(--primary-background-color)',
                                        border: '1px solid var(--om-hairline)',
                                    }}
                                >
                                    <span
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        Total contract value
                                    </span>
                                    <span style={{ marginInlineStart: 'auto', font: 'var(--font-h3-bold)' }}>
                                        {money(total, currency)} {currency}
                                    </span>
                                </div>
                            </>
                        ) : null}

                        {terms.type === 'hourly' ? (
                            <>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                    <TextField
                                        label="Hourly rate"
                                        placeholder="0.00"
                                        inputMode="decimal"
                                        value={terms.hourlyRate}
                                        onChange={(e) =>
                                            setTerms((t) => ({
                                                ...t,
                                                hourlyRate: e.target.value.replace(/[^0-9.]/g, ''),
                                            }))
                                        }
                                    />
                                    <TextField
                                        label="Estimated hours"
                                        placeholder="0"
                                        inputMode="decimal"
                                        value={terms.hours}
                                        onChange={(e) =>
                                            setTerms((t) => ({
                                                ...t,
                                                hours: e.target.value.replace(/[^0-9.]/g, '').slice(0, 6),
                                            }))
                                        }
                                    />
                                </div>
                                <div
                                    style={{
                                        display: 'flex',
                                        alignItems: 'baseline',
                                        gap: 8,
                                        padding: '10px 12px',
                                        borderRadius: 'var(--border-radius-small)',
                                        background: 'var(--primary-background-color)',
                                        border: '1px solid var(--om-hairline)',
                                    }}
                                >
                                    <span
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        Estimated total
                                    </span>
                                    <span style={{ marginInlineStart: 'auto', font: 'var(--font-h3-bold)' }}>
                                        {money(num(terms.hourlyRate) * num(terms.hours), currency)} {currency}
                                    </span>
                                </div>
                            </>
                        ) : null}

                        <TextArea
                            label="Extra conditions (optional)"
                            placeholder="e.g. 7-day invoice terms, expenses billed at cost."
                            rows={2}
                            maxLength={2000}
                            value={terms.notes}
                            onChange={(e) => setTerms((t) => ({ ...t, notes: e.target.value }))}
                        />
                    </div>
                </div>

                {/* ---- Supporting document ---- */}
                <div>
                    <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, marginBottom: 4, flexWrap: 'wrap' }}>
                        <span style={{ font: 'var(--font-text2-medium)' }}>Supporting document (optional)</span>
                        <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            Backs up this quote — nothing is signed here
                        </span>
                    </div>
                    <div
                        style={{
                            marginBottom: 8,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                            textWrap: 'pretty',
                        }}
                    >
                        Attach a detailed quote, scope of work or rate card you'd like the team to review. Not the
                        place for an invoice — bills are raised against a proposal once it's accepted.
                    </div>

                    <div
                        style={{
                            border: '1px dashed',
                            borderColor: docError ? 'var(--negative-color)' : 'var(--ui-border-color)',
                            borderRadius: 'var(--border-radius-small)',
                            padding: 16,
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            background: 'var(--allgrey-background-color)',
                        }}
                    >
                        <Icon name="Upload" size={20} color="var(--secondary-text-color)" />
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <div
                                style={{
                                    font: 'var(--font-text2-medium)',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {doc?.name ||
                                    initial?.proposal?.document?.name ||
                                    'No file chosen'}
                            </div>
                            <div
                                style={{
                                    font: 'var(--font-text3-normal)',
                                    color: docError ? 'var(--negative-color)' : 'var(--secondary-text-color)',
                                }}
                            >
                                {docError || 'PDF only, up to 10MB'}
                            </div>
                        </div>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="application/pdf"
                            onChange={pickFile}
                            style={{ display: 'none' }}
                        />
                        <Button kind="secondary" size="small" onClick={() => fileInput.current?.click()}>
                            Choose file
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

/** Local form state -> the JSON string stored in project_expendables.payment_terms. */
function termsToStored(terms, total, currency) {
    const notes = terms.notes?.trim() || undefined;

    if (terms.type === 'retainer') {
        return JSON.stringify({
            type: 'retainer',
            months: num(terms.months),
            monthly_amount: num(terms.monthlyRate),
            currency,
            notes,
        });
    }
    if (terms.type === 'hourly') {
        return JSON.stringify({
            type: 'hourly',
            hourly_rate: num(terms.hourlyRate),
            estimated_hours: num(terms.hours),
            currency,
            notes,
        });
    }
    return JSON.stringify({
        type: terms.type,
        installments: terms.rows.map((r) => ({
            label: r.label?.trim() || 'Payment',
            percentage: num(r.pct),
        })),
        notes,
    });
}

/** The stored JSON string -> local form state, tolerant of legacy free-text rows. */
function termsFromStored(raw) {
    const base = emptyTerms();
    if (!raw) return base;

    let parsed;
    try {
        parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch {
        return { ...base, notes: String(raw) };
    }
    if (!parsed || typeof parsed !== 'object') return base;

    const next = { ...base, notes: parsed.notes || '' };

    if (parsed.type === 'retainer') {
        return {
            ...next,
            type: 'retainer',
            months: String(parsed.months ?? '6'),
            monthlyRate: String(parsed.monthly_amount ?? ''),
        };
    }
    if (parsed.type === 'hourly') {
        return {
            ...next,
            type: 'hourly',
            hourlyRate: String(parsed.hourly_rate ?? ''),
            hours: String(parsed.estimated_hours ?? ''),
        };
    }
    if (Array.isArray(parsed.installments) && parsed.installments.length) {
        return {
            ...next,
            type: ['fixed', 'installments', 'milestone'].includes(parsed.type) ? parsed.type : 'installments',
            rows: parsed.installments.map((i) => ({
                label: i.label || '',
                pct: String(i.percentage ?? 0),
            })),
        };
    }
    return next;
}
