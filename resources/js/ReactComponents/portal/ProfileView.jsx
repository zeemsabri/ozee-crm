/**
 * Profile — the portal user's details and the payout methods they pick from when
 * uploading a bill.
 *
 * Follows the mock's Profile screen, including the "Verification details" block: the
 * date-of-birth / ID / address set banks ask for before an international transfer
 * clears. Those are held encrypted server-side (PortalProfileService) and, unlike
 * payout methods, are returned in full to their owner so the form can edit them.
 *
 * Payout methods are write-then-replace: the list shows a masked summary with
 * "Make default" and "Remove" but no edit, so raw account numbers never travel back to
 * the browser once stored.
 */

import { useMemo, useState } from 'react';
import { AttentionBox, Button, Counter, Dropdown, Icon, IconButton, Label, TextField } from '../ds';
import { COUNTRIES, METHOD_TYPES, fieldsFor, missingRequired, needsCountry, resolveStoredType } from './paymentMethods';

function Panel({ children, style }) {
    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                animation: 'dcRise var(--motion-expressive-long) var(--motion-timing-enter) both',
                ...style,
            }}
        >
            {children}
        </section>
    );
}

const VERIFICATION_LABELS = {
    dob: 'date of birth',
    id_number: 'ID number',
    city: 'city',
    address: 'residential address',
};

/** Placeholder and label follow the chosen document, as in the mock. */
function idNumberCopy(idType) {
    switch (idType) {
        case 'passport':
            return { label: 'Passport number', placeholder: 'AB1234567' };
        case 'drivers_licence':
            return { label: 'Licence number', placeholder: 'Licence number' };
        case 'aadhaar_pan':
            return { label: 'Aadhaar / PAN', placeholder: 'ABCDE1234F' };
        default:
            return { label: 'ID number', placeholder: '35202-1234567-3' };
    }
}

function YourDetails({ account, idTypes, busy, onSave }) {
    const [name, setName] = useState(account.name || '');
    const [phone, setPhone] = useState(account.phone || '');
    const [business, setBusiness] = useState(account.business_name || '');
    const [verification, setVerification] = useState(() => ({
        dob: '',
        id_type: 'national_id',
        id_number: '',
        city: '',
        address: '',
        postcode: '',
        ...(account.verification || {}),
        // A blank stored id_type would render the dropdown empty.
        id_type: account.verification?.id_type || 'national_id',
    }));
    const [saved, setSaved] = useState(false);

    const missing = account.verification_missing || [];
    const idCopy = idNumberCopy(verification.id_type);

    function setField(key, value) {
        setVerification((prev) => ({ ...prev, [key]: value }));
        setSaved(false);
    }

    async function save() {
        const ok = await onSave({ name, phone, business_name: business, verification });
        if (ok) {
            setSaved(true);
            setTimeout(() => setSaved(false), 3000);
        }
    }

    return (
        <Panel style={{ padding: 20 }}>
            <h1 style={{ font: 'var(--font-h2-medium)', letterSpacing: 'var(--letter-spacing-h2-normal)' }}>
                Your details
            </h1>
            <p style={{ margin: '4px 0 20px', font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                Used on every proposal and bill you send.
            </p>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 16 }}>
                <TextField
                    label="Full name"
                    iconName="Person"
                    required
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                />
                <TextField
                    label="Phone number"
                    iconName="Mobile"
                    required
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                />
                <TextField
                    label="Email address"
                    iconName="Email"
                    value={account.email}
                    readOnly
                    subText="Verified — this is how you sign in"
                />
                <TextField
                    label="Business or trading name"
                    placeholder="Optional"
                    value={business}
                    onChange={(e) => setBusiness(e.target.value)}
                />
            </div>

            {/* ---- Verification details ---- */}
            <div style={{ marginTop: 20, paddingTop: 16, borderTop: '1px solid var(--om-hairline)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4, flexWrap: 'wrap' }}>
                    <Icon name="Security" size={16} color="var(--secondary-text-color)" />
                    <span style={{ font: 'var(--font-text2-medium)' }}>Verification details</span>
                    <Label
                        text={
                            missing.length === 0
                                ? 'Complete'
                                : `${missing.length} ${missing.length === 1 ? 'field' : 'fields'} missing`
                        }
                        color={missing.length === 0 ? 'var(--positive-color)' : 'var(--color-working-orange)'}
                        kind="line"
                        size="small"
                    />
                </div>
                <div
                    style={{
                        marginBottom: 12,
                        font: 'var(--font-text3-normal)',
                        color: 'var(--secondary-text-color)',
                        textWrap: 'pretty',
                    }}
                >
                    Entered once and reused on every bank transfer — banks ask for these before an international
                    payment clears. Paid to the name above.
                    {missing.length ? (
                        <>
                            {' '}
                            Still needed: {missing.map((f) => VERIFICATION_LABELS[f] || f).join(', ')}.
                        </>
                    ) : null}
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 16 }}>
                    <TextField
                        label="Date of birth"
                        type="date"
                        value={verification.dob}
                        onChange={(e) => setField('dob', e.target.value)}
                    />
                    <Dropdown
                        label="ID document"
                        options={idTypes}
                        value={verification.id_type}
                        onChange={(v) => setField('id_type', v)}
                    />
                    <TextField
                        label={idCopy.label}
                        placeholder={idCopy.placeholder}
                        value={verification.id_number}
                        onChange={(e) => setField('id_number', e.target.value)}
                    />
                    <TextField
                        label="City"
                        placeholder="Lahore"
                        value={verification.city}
                        onChange={(e) => setField('city', e.target.value)}
                    />
                    <div style={{ gridColumn: '1 / -1' }}>
                        <TextField
                            label="Residential address"
                            placeholder="House 14, Street 8, Gulberg III"
                            value={verification.address}
                            onChange={(e) => setField('address', e.target.value)}
                        />
                    </div>
                    <TextField
                        label="Post code"
                        placeholder="54660"
                        value={verification.postcode}
                        onChange={(e) => setField('postcode', e.target.value)}
                    />
                </div>
            </div>

            <div
                style={{
                    marginTop: 20,
                    paddingTop: 16,
                    borderTop: '1px solid var(--om-hairline)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <Button onClick={save} disabled={!name.trim() || !phone.trim()} loading={busy}>
                    Save changes
                </Button>
                {saved ? (
                    <span
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                            font: 'var(--font-text3-medium)',
                            color: 'var(--positive-color)',
                        }}
                    >
                        <Icon name="Check" size={14} color="var(--positive-color)" />
                        Saved
                    </span>
                ) : (
                    <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                        Only the accounts team can see these.
                    </span>
                )}
            </div>
        </Panel>
    );
}

function AddMethodForm({ busy, currencies, onAdd }) {
    const [type, setType] = useState('bank');
    const [label, setLabel] = useState('');
    const [country, setCountry] = useState('Australia');
    const [currency, setCurrency] = useState(currencies?.[0] || 'AUD');
    const [values, setValues] = useState({});

    const fields = useMemo(() => fieldsFor(type, country), [type, country]);
    const missing = missingRequired(type, country, values);
    const ready = label.trim() !== '' && missing.length === 0;

    const hint = !label.trim()
        ? 'Name this method so you can pick it later'
        : missing.length
          ? `${missing.length} account ${missing.length === 1 ? 'field' : 'fields'} still needed`
          : 'Only the accounts team can see these.';

    async function add() {
        // The picker offers "Bank transfer"; the server stores bank_local/bank_wire,
        // which is what decides the resulting bill's required_if rules.
        const ok = await onAdd({
            type: resolveStoredType(type, country),
            label,
            country: needsCountry(type) ? country : null,
            currency,
            fields: values,
        });
        if (ok) {
            setLabel('');
            setValues({});
        }
    }

    return (
        <div
            style={{
                padding: '12px 16px 16px',
                borderTop: '1px solid var(--om-hairline)',
                display: 'flex',
                flexDirection: 'column',
                gap: 16,
            }}
        >
            <div>
                <span style={{ font: 'var(--font-text2-medium)' }}>Add a method</span>
                <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    Fields follow the country — an international transfer needs more than a local one.
                </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: 12 }}>
                <Dropdown
                    label="Method type"
                    options={METHOD_TYPES.map((t) => ({ value: t.value, label: t.label }))}
                    value={type}
                    onChange={(v) => {
                        setType(v);
                        setValues({});
                    }}
                    size="small"
                />
                <TextField
                    label="Name it"
                    size="small"
                    placeholder="e.g. Business account"
                    value={label}
                    onChange={(e) => setLabel(e.target.value)}
                />
                {needsCountry(type) ? (
                    <Dropdown
                        label="Account country"
                        options={COUNTRIES.map((c) => ({ value: c, label: c }))}
                        value={country}
                        onChange={(v) => {
                            setCountry(v);
                            setValues({});
                        }}
                        size="small"
                        searchable
                    />
                ) : null}
                <Dropdown
                    label="Account currency"
                    options={(currencies || ['AUD']).map((c) => ({ value: c, label: c }))}
                    value={currency}
                    onChange={setCurrency}
                    size="small"
                />
            </div>

            <div>
                <div style={{ font: 'var(--font-text3-medium)', marginBottom: 8, color: 'var(--secondary-text-color)' }}>
                    {needsCountry(type) ? `Account details — ${country}` : 'Account details'}
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 12 }}>
                    {fields.map((f) => (
                        <div key={f.key} style={{ gridColumn: f.span || 'auto' }}>
                            <TextField
                                label={f.label}
                                size="small"
                                placeholder={f.placeholder}
                                value={values[f.key] || ''}
                                onChange={(e) => setValues((prev) => ({ ...prev, [f.key]: e.target.value }))}
                            />
                        </div>
                    ))}
                </div>
            </div>

            <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                <Button kind="secondary" size="small" disabled={!ready} loading={busy} onClick={add}>
                    Add method
                </Button>
                <span
                    style={{
                        font: 'var(--font-text3-normal)',
                        color: ready ? 'var(--secondary-text-color)' : 'var(--negative-color)',
                    }}
                >
                    {hint}
                </span>
            </div>
        </div>
    );
}

export function ProfileView({
    account,
    paymentMethods,
    idTypes,
    currencies,
    busy,
    onSaveProfile,
    onAddMethod,
    onRemoveMethod,
    onMakeDefault,
}) {
    return (
        <div className="ozds-portal-profile">
            <YourDetails account={account} idTypes={idTypes} busy={busy} onSave={onSaveProfile} />

            <Panel>
                <div
                    style={{
                        padding: 16,
                        borderBottom: '1px solid var(--om-hairline)',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                    }}
                >
                    <h2 style={{ font: 'var(--font-h3-medium)', letterSpacing: 'var(--letter-spacing-h3-normal)' }}>
                        Payment methods
                    </h2>
                    <Counter count={paymentMethods.length} kind="fill" color="primary" size="small" />
                </div>

                <div style={{ padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {paymentMethods.map((pm) => (
                        <div
                            key={pm.id}
                            style={{
                                border: '1px solid var(--layout-border-color)',
                                borderRadius: 'var(--border-radius-small)',
                                padding: '10px 12px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 10,
                                flexWrap: 'wrap',
                                animation: 'dcFade var(--motion-productive-long) var(--motion-timing-enter) both',
                            }}
                        >
                            <div style={{ flex: '1 1 200px', minWidth: 0 }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                    <span style={{ font: 'var(--font-text2-medium)' }}>{pm.label}</span>
                                    {pm.is_default ? <Label text="Default" color="primary" size="small" /> : null}
                                </div>
                                <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                                    {pm.detail_line}
                                </div>
                            </div>
                            {!pm.is_default ? (
                                <Button kind="tertiary" size="small" onClick={() => onMakeDefault(pm.id)}>
                                    Make default
                                </Button>
                            ) : null}
                            <IconButton
                                name="Delete"
                                size="small"
                                kind="tertiary"
                                ariaLabel={`Remove ${pm.label}`}
                                onClick={() => onRemoveMethod(pm.id)}
                            />
                        </div>
                    ))}

                    {paymentMethods.length === 0 ? (
                        <div
                            style={{
                                padding: 12,
                                border: '1px dashed var(--ui-border-color)',
                                borderRadius: 'var(--border-radius-small)',
                                font: 'var(--font-text2-normal)',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            No payment methods yet — add one so your bills can be paid.
                        </div>
                    ) : null}
                </div>

                <div style={{ padding: '0 16px' }}>
                    <AttentionBox type="dark" withIcon>
                        Stored against your account and only ever shown to the accounts team. We show you a masked
                        summary, so to change an account you remove it and add it again.
                    </AttentionBox>
                </div>

                <AddMethodForm busy={busy} currencies={currencies} onAdd={onAddMethod} />
            </Panel>
        </div>
    );
}
