/**
 * "Proposals" tab — the guest's own proposals for this project, each with the bills
 * raised against it.
 *
 * Once a proposal is accepted the guest can upload their own invoice against it. The
 * accounts team then adds the accounting details (Xero account code, tax type,
 * transaction type) in the admin panel before it can be approved, so from here a bill
 * is upload-then-watch: its status and payment progress update, but it isn't editable.
 */

import { Button, Icon, Label } from '../ds';
import { billTone, describePaymentTerms, money, plural, proposalTone, statusLabel } from './format';

function BillLine({ bill }) {
    const paid = Number(bill.paid_amount || 0);
    const partiallyPaid = paid > 0 && paid < Number(bill.amount || 0);

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'flex-start',
                gap: 8,
                animation: 'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
            }}
        >
            <Icon name="Doc" size={16} color="var(--secondary-text-color)" style={{ marginTop: 2 }} />
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        font: 'var(--font-text3-medium)',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}
                >
                    {bill.reference_number || bill.number}
                </div>
                <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    {money(bill.amount, bill.currency)} {bill.currency}
                    {bill.raised_at ? ` · uploaded ${bill.raised_at}` : ''}
                </div>
                {partiallyPaid ? (
                    <div style={{ font: 'var(--font-text3-normal)', color: 'var(--color-working-orange)' }}>
                        {money(paid, bill.currency)} paid · {money(bill.remaining_amount, bill.currency)} outstanding
                    </div>
                ) : null}
                {bill.last_paid_at ? (
                    <div style={{ font: 'var(--font-text3-normal)', color: 'var(--positive-color)' }}>
                        Last payment {bill.last_paid_at}
                    </div>
                ) : bill.due_date ? (
                    <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                        Due {bill.due_date}
                    </div>
                ) : null}
            </div>
            <Label text={statusLabel(bill.status)} color={billTone(bill.status)} kind="line" size="small" />
        </div>
    );
}

function ProposalCard({ proposal, onEdit, onUploadBill }) {
    const bills = proposal.bills || [];
    const billed = bills.reduce((n, b) => n + Number(b.amount || 0), 0);
    const remaining = Math.max(0, Number(proposal.amount || 0) - billed);
    const terms = describePaymentTerms(proposal.payment_terms, proposal.currency);

    return (
        <article
            style={{
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                background: 'var(--primary-background-color)',
                overflow: 'hidden',
                animation: 'dcSlideIn var(--motion-expressive-short) var(--motion-timing-enter) both',
            }}
        >
            <div style={{ padding: 12, display: 'flex', flexDirection: 'column', gap: 8 }}>
                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                    <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            {proposal.scope === 'project' ? 'Whole project' : 'Phase proposal'}
                            {proposal.number ? ` · ${proposal.number}` : ''}
                        </div>
                        <div style={{ font: 'var(--font-text2-medium)' }}>
                            {proposal.scope === 'project'
                                ? 'All active phases'
                                : proposal.milestone_name || 'Phase'}
                        </div>
                    </div>
                    <Label text={proposal.status} color={proposalTone(proposal.status)} size="small" />
                </div>

                <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
                    <span style={{ font: '700 20px/26px var(--font-family)' }}>
                        {money(proposal.amount, proposal.currency)}
                    </span>
                    <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                        {proposal.currency}
                        {proposal.submitted_at ? ` · sent ${proposal.submitted_at}` : ''}
                    </span>
                </div>

                {terms ? (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <Icon name="Update" size={14} color="var(--secondary-text-color)" />
                        <span>{terms}</span>
                    </div>
                ) : null}

                {proposal.description ? (
                    <div
                        style={{
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                            display: '-webkit-box',
                            WebkitLineClamp: 3,
                            WebkitBoxOrient: 'vertical',
                            overflow: 'hidden',
                        }}
                    >
                        {proposal.description}
                    </div>
                ) : null}

                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                    {proposal.can_edit ? (
                        <Button kind="tertiary" size="small" onClick={() => onEdit(proposal)}>
                            Edit
                        </Button>
                    ) : null}
                    {proposal.document?.url ? (
                        <Button
                            kind="tertiary"
                            size="small"
                            leftIcon={<Icon name="Download" size={16} />}
                            onClick={() => window.open(proposal.document.url, '_blank', 'noopener')}
                        >
                            {proposal.document.name || 'View document'}
                        </Button>
                    ) : proposal.document ? (
                        <span
                            style={{
                                alignSelf: 'center',
                                font: 'var(--font-text3-normal)',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            {proposal.document.name} attached
                        </span>
                    ) : null}
                </div>
            </div>

            <div
                style={{
                    padding: '10px 12px',
                    background: 'var(--grey-background-color)',
                    borderTop: '1px solid var(--om-hairline)',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 8,
                }}
            >
                {bills.map((b) => (
                    <BillLine key={b.id} bill={b} />
                ))}

                {bills.length ? (
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
                                flex: 1,
                                minWidth: 0,
                                font: 'var(--font-text3-normal)',
                                color: remaining > 0 ? 'var(--secondary-text-color)' : 'var(--positive-color)',
                            }}
                        >
                            {remaining > 0
                                ? `${plural(bills.length, 'bill')} · ${money(billed, proposal.currency)} of ${money(
                                      proposal.amount,
                                      proposal.currency,
                                  )} billed · ${money(remaining, proposal.currency)} left`
                                : `${plural(bills.length, 'bill')} · fully billed at ${money(
                                      billed,
                                      proposal.currency,
                                  )}`}
                        </span>
                        {proposal.can_bill ? (
                            <Button kind="tertiary" size="small" onClick={() => onUploadBill(proposal)}>
                                Add another bill
                            </Button>
                        ) : null}
                    </div>
                ) : proposal.can_bill ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <span
                            style={{
                                flex: '1 1 180px',
                                minWidth: 0,
                                font: 'var(--font-text3-normal)',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            Accepted — send your invoice when you're ready.
                        </span>
                        <Button kind="secondary" size="small" onClick={() => onUploadBill(proposal)}>
                            Upload bill
                        </Button>
                    </div>
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 6,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        <Icon name="Info" size={14} color="var(--secondary-text-color)" />
                        <span>
                            {proposal.status === 'Accepted'
                                ? 'Accepted — the accounts team raises the bill from here.'
                                : proposal.status === 'Rejected'
                                  ? 'This proposal was declined, so no bill is needed.'
                                  : 'Bills appear here once this proposal is accepted.'}
                        </span>
                    </div>
                )}
            </div>
        </article>
    );
}

export function ProposalsTab({ proposals, onEdit, onUploadBill }) {
    if (!proposals.length) {
        return (
            <section
                style={{
                    background: 'var(--primary-background-color)',
                    border: '1px solid var(--layout-border-color)',
                    borderRadius: 'var(--border-radius-medium)',
                    padding: 24,
                    textAlign: 'center',
                }}
            >
                <div style={{ font: 'var(--font-text1-medium)' }}>No proposals here yet</div>
                <div style={{ marginTop: 4, font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                    Quote a phase and it'll show up with its status.
                </div>
            </section>
        );
    }

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
                gap: 16,
            }}
        >
            {proposals.map((p) => (
                <ProposalCard key={p.id} proposal={p} onEdit={onEdit} onUploadBill={onUploadBill} />
            ))}
        </div>
    );
}
