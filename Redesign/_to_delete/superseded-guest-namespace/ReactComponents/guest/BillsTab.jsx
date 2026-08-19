/**
 * "Bills" tab — every bill this guest has uploaded on this project, flattened into one
 * table so they can see what's been invoiced and what's been paid.
 *
 * Uploading happens from a proposal (a bill always belongs to one), so this tab is a
 * ledger: status, how much has been paid, and when. Payment figures come from the bank
 * transactions the accounts team links to each bill — the same source the admin
 * transactions screen reads.
 */

import { Button, Label, ProgressBar } from '../ds';
import { billTone, money, plural, statusLabel, sumByCurrency } from './format';

const GRID = '1.4fr 1.2fr 0.9fr 1.1fr 0.8fr';

export function BillsTab({ proposals, currency, onUploadBill }) {
    const rows = proposals.flatMap((p) =>
        (p.bills || []).map((b) => ({
            ...b,
            scopeName: p.scope === 'project' ? 'Whole project' : p.milestone_name || 'Phase proposal',
            proposalNumber: p.number,
        })),
    );

    // Totals are per-currency: a guest may hold an AUD proposal and a PKR one, and
    // adding those into a single figure would be nonsense.
    const billedText = sumByCurrency(rows, currency);
    const billableProposals = proposals.filter((p) => p.can_bill);
    // What's accepted but not yet invoiced. Only bills in the contract's own currency
    // are subtracted — the figures aren't comparable otherwise — and voided bills don't
    // count against the contract at all.
    const unbilled = proposals
        .filter((p) => p.status === 'Accepted')
        .map((p) => {
            const billed = (p.bills || [])
                .filter((b) => b.status !== 'void' && b.currency === p.currency)
                .reduce((m, b) => m + Number(b.amount || 0), 0);
            return { amount: Math.max(0, Number(p.amount || 0) - billed), currency: p.currency };
        })
        .filter((r) => r.amount > 0);

    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                overflow: 'hidden',
            }}
        >
            {rows.length ? (
                <>
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: GRID,
                            gap: 12,
                            padding: '10px 16px',
                            borderBottom: '1px solid var(--layout-border-color)',
                            font: 'var(--font-text3-medium)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <span>Invoice</span>
                        <span>Proposal</span>
                        <span style={{ textAlign: 'end' }}>Amount</span>
                        <span>Payment</span>
                        <span style={{ textAlign: 'end' }}>Status</span>
                    </div>

                    {rows.map((b) => (
                        <div
                            key={b.id}
                            style={{
                                display: 'grid',
                                gridTemplateColumns: GRID,
                                gap: 12,
                                alignItems: 'center',
                                padding: '10px 16px',
                                borderBottom: '1px solid var(--om-hairline-soft)',
                            }}
                        >
                            <div style={{ minWidth: 0 }}>
                                <div
                                    style={{
                                        font: 'var(--font-text2-medium)',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {b.reference_number || b.number}
                                </div>
                                {b.raised_at ? (
                                    <div
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        Raised {b.raised_at}
                                    </div>
                                ) : null}
                            </div>
                            <div style={{ minWidth: 0 }}>
                                <div
                                    style={{
                                        font: 'var(--font-text2-normal)',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {b.scopeName}
                                </div>
                                <div
                                    style={{
                                        font: 'var(--font-text3-normal)',
                                        color: 'var(--secondary-text-color)',
                                    }}
                                >
                                    {b.proposalNumber}
                                </div>
                            </div>
                            <span style={{ textAlign: 'end', font: 'var(--font-text2-medium)' }}>
                                {money(b.amount, b.currency)}
                            </span>
                            <div style={{ minWidth: 0 }}>
                                {Number(b.paid_amount || 0) > 0 ? (
                                    <>
                                        <ProgressBar
                                            value={Math.min(100, (Number(b.paid_amount) / Math.max(0.01, Number(b.amount))) * 100)}
                                            max={100}
                                            size="small"
                                            color={Number(b.remaining_amount) <= 0 ? 'positive' : 'primary'}
                                        />
                                        <div
                                            style={{
                                                marginTop: 2,
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            {money(b.paid_amount, b.currency)} paid
                                            {b.last_paid_at ? ` · ${b.last_paid_at}` : ''}
                                        </div>
                                    </>
                                ) : (
                                    <span
                                        style={{
                                            font: 'var(--font-text3-normal)',
                                            color: 'var(--secondary-text-color)',
                                        }}
                                    >
                                        {b.due_date ? `Due ${b.due_date}` : 'Not paid yet'}
                                    </span>
                                )}
                            </div>
                            <span style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                <Label
                                    text={statusLabel(b.status)}
                                    color={billTone(b.status)}
                                    kind="line"
                                    size="small"
                                />
                            </span>
                        </div>
                    ))}
                </>
            ) : null}

            <div style={{ padding: '12px 16px', display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '8px 16px' }}>
                <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    {rows.length
                        ? `${plural(rows.length, 'bill')} · ${billedText} billed in total`
                        : 'No bills yet — once a proposal is accepted you can upload your invoice against it.'}
                </span>
                {billableProposals.length ? (
                    <Button kind="secondary" size="small" onClick={() => onUploadBill(billableProposals[0])}>
                        Upload a bill
                    </Button>
                ) : null}
                {unbilled.length ? (
                    <span
                        style={{
                            marginInlineStart: 'auto',
                            font: 'var(--font-text3-medium)',
                            color: 'var(--primary-color)',
                        }}
                    >
                        {sumByCurrency(unbilled, currency)} accepted but not yet billed
                    </span>
                ) : null}
            </div>
        </section>
    );
}
