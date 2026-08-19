/**
 * Field schemas for a guest's saved payout methods.
 *
 * The keys here are the ones App\Services\GuestPaymentMethodService accepts, which are
 * in turn the keys BillController expects inside `bill_payment_details.details`. Keep
 * the two in step: a field the UI collects but the service doesn't allow is silently
 * dropped, and a field the service requires but the UI never asks for makes the form
 * unsubmittable.
 *
 * Bank transfers vary by country, so the country picks the fields — and, for a bank
 * transfer, which of the two bank method types the saved record becomes. That matters
 * because `bank_local` and `bank_wire` have different `required_if` rules on the bill.
 */

/** What the guest picks from. Bank transfer resolves to bank_local/bank_wire by country. */
export const METHOD_TYPES = [
    { value: 'bank', label: 'Bank transfer' },
    { value: 'paypal', label: 'PayPal' },
    { value: 'wise', label: 'Wise' },
    { value: 'payoneer', label: 'Payoneer' },
    { value: 'crypto', label: 'Crypto' },
];

export const COUNTRIES = [
    'Australia',
    'Pakistan',
    'India',
    'United Kingdom',
    'United States',
    'United Arab Emirates',
    'Other',
];

const ACCOUNT_NAME = { key: 'account_name', label: 'Account title', placeholder: 'Name exactly as the bank has it', span: '1 / -1' };
const BANK_NAME = { key: 'bank_name', label: 'Bank name', placeholder: 'e.g. Meezan Bank' };
const SWIFT = { key: 'swift_code', label: 'SWIFT / BIC', placeholder: 'MEZNPKKA' };

/**
 * Per-country bank fields. `local: true` means the country's domestic rails are used
 * (Australia's BSB), which maps to the `bank_local` method type; everything else is an
 * international wire.
 */
const BANK_SCHEMAS = {
    Australia: {
        local: true,
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'bsb', label: 'BSB', placeholder: '066-000' }, { key: 'account_number', label: 'Account number', placeholder: '12345678' }],
    },
    Pakistan: {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'iban', label: 'IBAN', placeholder: 'PK36MEZN0000001123456702' }, { key: 'branch_code', label: 'Branch code', placeholder: '0123' }, SWIFT],
    },
    India: {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'account_number', label: 'Account number', placeholder: '0000 1234 5678' }, { key: 'ifsc', label: 'IFSC code', placeholder: 'HDFC0001234' }, SWIFT],
    },
    'United Kingdom': {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'sort_code', label: 'Sort code', placeholder: '20-00-00' }, { key: 'account_number', label: 'Account number', placeholder: '12345678' }, { key: 'iban', label: 'IBAN', placeholder: 'GB29NWBK60161331926819' }, SWIFT],
    },
    'United States': {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'routing_number', label: 'Routing number (ABA)', placeholder: '021000021' }, { key: 'account_number', label: 'Account number', placeholder: '12345678' }, SWIFT],
    },
    'United Arab Emirates': {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'iban', label: 'IBAN', placeholder: 'AE070331234567890123456' }, SWIFT],
    },
    Other: {
        fields: [ACCOUNT_NAME, BANK_NAME, { key: 'account_number', label: 'Account number or IBAN', placeholder: 'Account identifier' }, SWIFT],
    },
};

const NON_BANK_SCHEMAS = {
    paypal: [{ key: 'paypal_email', label: 'PayPal email', placeholder: 'you@example.com', span: '1 / -1' }],
    wise: [{ key: 'wise_email', label: 'Wise email', placeholder: 'you@example.com', span: '1 / -1' }],
    payoneer: [{ key: 'payoneer_email', label: 'Payoneer email', placeholder: 'you@example.com', span: '1 / -1' }],
    crypto: [
        { key: 'coin_type', label: 'Coin / network', placeholder: 'e.g. USDT (TRC-20)' },
        { key: 'wallet_address', label: 'Wallet address', placeholder: 'Your receiving address', span: '1 / -1' },
    ],
};

/** The fields to ask for, given the picked type and (for banks) country. */
export function fieldsFor(type, country) {
    if (type !== 'bank') return NON_BANK_SCHEMAS[type] || [];
    return (BANK_SCHEMAS[country] || BANK_SCHEMAS.Other).fields;
}

/** The method type actually stored — what decides the bill's `required_if` rules. */
export function resolveStoredType(type, country) {
    if (type !== 'bank') return type;
    return (BANK_SCHEMAS[country] || BANK_SCHEMAS.Other).local ? 'bank_local' : 'bank_wire';
}

/**
 * Which of the collected fields must be filled, mirroring
 * GuestPaymentMethodService::REQUIRED_FIELDS so the guest sees the problem in the form
 * rather than as a 422 after saving.
 */
export function missingRequired(type, country, values) {
    const filled = (key) => String(values[key] || '').trim() !== '';
    const stored = resolveStoredType(type, country);

    if (stored === 'bank_local') {
        return ['account_name', 'account_number', 'bsb'].filter((k) => !filled(k));
    }
    if (stored === 'bank_wire') {
        const missing = ['account_name', 'swift_code'].filter((k) => !filled(k));
        // Either identifier will do — countries differ on which one they use.
        if (!filled('account_number') && !filled('iban')) missing.push('account_number');
        return missing;
    }
    if (stored === 'crypto') return ['coin_type', 'wallet_address'].filter((k) => !filled(k));
    return [`${stored}_email`].filter((k) => !filled(k));
}

/** Does this method type need a country? Only bank transfers do. */
export function needsCountry(type) {
    return type === 'bank';
}
