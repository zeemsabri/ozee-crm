/**
 * Bill upload, following the mock's "Upload your bill" modal.
 *
 * The guest supplies only what they can know — their invoice, its number, the amount and
 * where to be paid. The Xero account code, tax type and transaction type are the
 * accounts team's job on the admin bills screen, and the bill can't be approved until
 * at least the account code is filled in.
 */

import { useEffect, useMemo, useRef, useState } from 'react';
import { AttentionBox, Button, Icon, Modal, RadioButton, TextField } from '../ds';
import { money, num } from './format';

const MAX_BILL_BYTES = 10 * 1024 * 1024;

export function BillModal({
    open,
    onClose,
    onSubmit,
    busy,
    error,
    onDismissError,
    proposal,
    paymentMethods,
    onGoToProfile,
}) {
    const [reference, setReference] = useState('');
    const [amount, setAmount] = useState('');
    const [dueDate, setDueDate] = useState('');
    const [methodId, setMethodId] = useState(null);
    const [file, setFile] = useState(null);
    const [fileError, setFileError] = useState('');
    const fileInput = useRef(null);

    const remaining = Number(proposal?.billable_remaining ?? 0);
    const currency = proposal?.currency || 'AUD';

    const defaultMethodId = useMemo(
        () => paymentMethods.find((m) => m.is_default)?.id || paymentMethods[0]?.id || null,
        [paymentMethods],
    );

    // Fresh each time it opens: pre-fill the amount with what's left to bill, which is
    // the common case (one final invoice for the whole accepted amount).
    useEffect(() => {
        if (!open) return;
        setReference('');
        setAmount(remaining > 0 ? String(remaining) : '');
        setDueDate('');
        setFile(null);
        setFileError('');
        setMethodId(defaultMethodId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, proposal?.id]);

    // If methods arrive (or the default changes) while the modal is open, adopt it.
    useEffect(() => {
        if (open && !methodId && defaultMethodId) setMethodId(defaultMethodId);
    }, [open, methodId, defaultMethodId]);

    function pickFile(event) {
        const picked = event.target.files?.[0];
        if (!picked) return;
        if (picked.type !== 'application/pdf') {
            setFile(null);
            setFileError('That needs to be a PDF.');
            event.target.value = '';
            return;
        }
        if (picked.size > MAX_BILL_BYTES) {
            setFile(null);
            setFileError('That file is over 10MB.');
            event.target.value = '';
            return;
        }
        setFileError('');
        setFile(picked);
    }

    const overRemaining = remaining > 0 && num(amount) > remaining;
    const canSubmit =
        !busy &&
        Boolean(file) &&
        Boolean(methodId) &&
        reference.trim() !== '' &&
        num(amount) >= 0.01 &&
        !overRemaining;

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="small"
            title="Upload your bill"
            description={
                proposal
                    ? `${proposal.scope === 'project' ? 'Whole project' : proposal.milestone_name || 'Phase proposal'} · ${money(
                          proposal.amount,
                          currency,
                      )} accepted`
                    : ''
            }
            footer={
                <>
                    <Button kind="tertiary" onClick={onClose} disabled={busy}>
                        Cancel
                    </Button>
                    <Button onClick={() => onSubmit({ reference, amount, dueDate, methodId, file })} disabled={!canSubmit} loading={busy}>
                        Upload bill
                    </Button>
                </>
            }
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                {error ? (
                    <AttentionBox type="danger" onClose={onDismissError}>
                        {error}
                    </AttentionBox>
                ) : null}

                {/* ---- The invoice ---- */}
                <div
                    style={{
                        border: '1px dashed',
                        borderColor: fileError ? 'var(--negative-color)' : 'var(--ui-border-color)',
                        borderRadius: 'var(--border-radius-small)',
                        padding: 20,
                        textAlign: 'center',
                        background: 'var(--allgrey-background-color)',
                    }}
                >
                    <Icon name="Doc" size={24} color="var(--secondary-text-color)" />
                    <div style={{ marginTop: 8, font: 'var(--font-text2-medium)', wordBreak: 'break-word' }}>
                        {file?.name || 'No file chosen'}
                    </div>
                    <div
                        style={{
                            font: 'var(--font-text3-normal)',
                            color: fileError ? 'var(--negative-color)' : 'var(--secondary-text-color)',
                        }}
                    >
                        {fileError || 'PDF invoice, up to 10MB'}
                    </div>
                    <input
                        ref={fileInput}
                        type="file"
                        accept="application/pdf"
                        onChange={pickFile}
                        style={{ display: 'none' }}
                    />
                    <div style={{ marginTop: 12, display: 'flex', justifyContent: 'center' }}>
                        <Button kind="secondary" size="small" onClick={() => fileInput.current?.click()}>
                            Choose file
                        </Button>
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                    <TextField
                        label="Invoice number"
                        placeholder="INV-1043"
                        required
                        value={reference}
                        onChange={(e) => setReference(e.target.value)}
                    />
                    <TextField
                        label={`Amount billed (${currency})`}
                        placeholder="0.00"
                        inputMode="decimal"
                        required
                        value={amount}
                        onChange={(e) => setAmount(e.target.value.replace(/[^0-9.]/g, ''))}
                        validation={overRemaining ? 'error' : undefined}
                        subText={
                            overRemaining
                                ? `More than the ${money(remaining, currency)} left to bill`
                                : remaining > 0
                                  ? `${money(remaining, currency)} left to bill on this proposal`
                                  : undefined
                        }
                    />
                </div>

                <TextField
                    label="Payment due (optional)"
                    type="date"
                    value={dueDate}
                    onChange={(e) => setDueDate(e.target.value)}
                    subText="Leave blank and standard terms apply"
                />

                {/* ---- Where to be paid ---- */}
                <div>
                    <div style={{ font: 'var(--font-text2-medium)', marginBottom: 8 }}>Pay this bill to</div>

                    {paymentMethods.length ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                            {paymentMethods.map((pm) => {
                                const selected = pm.id === methodId;
                                return (
                                    <div
                                        key={pm.id}
                                        onClick={() => setMethodId(pm.id)}
                                        style={{
                                            border: '1px solid',
                                            borderColor: selected ? 'var(--primary-color)' : 'var(--ui-border-color)',
                                            background: selected
                                                ? 'var(--primary-highlighted-color)'
                                                : 'var(--primary-background-color)',
                                            borderRadius: 'var(--border-radius-small)',
                                            padding: '10px 12px',
                                            cursor: 'pointer',
                                            transition:
                                                'border-color var(--motion-productive-medium) var(--motion-timing-transition), background var(--motion-productive-medium) var(--motion-timing-transition)',
                                        }}
                                    >
                                        <RadioButton
                                            label={pm.label}
                                            name="bill-payment-method"
                                            value={pm.id}
                                            checked={selected}
                                            onChange={() => setMethodId(pm.id)}
                                        />
                                        <div
                                            style={{
                                                marginTop: 2,
                                                paddingInlineStart: 26,
                                                font: 'var(--font-text3-normal)',
                                                color: 'var(--secondary-text-color)',
                                            }}
                                        >
                                            {pm.detail_line}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                flexWrap: 'wrap',
                                gap: 10,
                                padding: '10px 12px',
                                borderRadius: 'var(--border-radius-small)',
                                background: 'var(--allgrey-background-color)',
                                border: '1px solid var(--om-hairline)',
                            }}
                        >
                            <span
                                style={{
                                    flex: '1 1 200px',
                                    font: 'var(--font-text3-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                No payment methods saved yet — add one and it'll be offered here.
                            </span>
                            <Button kind="secondary" size="small" onClick={onGoToProfile}>
                                Add one
                            </Button>
                        </div>
                    )}
                </div>

                <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    The accounts team is notified as soon as your bill lands. They add the accounting details their
                    end — payment status shows here once it's processed.
                </div>
            </div>
        </Modal>
    );
}
