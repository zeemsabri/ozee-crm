/**
 * Formatting and status-tone helpers for the guest project pages.
 *
 * Status tones map onto the design system's 32-colour board palette rather than the
 * four semantic roles, because that's how the mock expresses milestone/proposal/bill
 * state. Every value below is a CSS custom property from resources/css/ozee-ds.
 */

export const TONE = {
    pending: 'var(--color-working-orange)',
    progress: 'var(--color-working-orange)',
    review: 'var(--primary-color)',
    accepted: 'var(--positive-color)',
    declined: 'var(--negative-color)',
    done: 'var(--color-done-green)',
    overdue: 'var(--color-stuck-red)',
    neutral: 'var(--secondary-text-color)',
    idle: 'var(--color-explosive)',
};

/** Turn "in progress" / "pending_approval" / "PendingApproval" into a comparable key. */
function normalise(status) {
    return String(status || '')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .trim()
        .toLowerCase();
}

/** Sentence-case a status for display: "in progress" -> "In progress". */
export function statusLabel(status) {
    const s = normalise(status);
    if (!s) return '';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

/** Milestone / project status -> board colour. */
export function milestoneTone(status) {
    switch (normalise(status)) {
        case 'overdue':
        case 'expired':
            return TONE.overdue;
        case 'in progress':
            return TONE.progress;
        case 'pending approval':
        case 'pending review':
            return TONE.review;
        case 'completed':
        case 'approved':
            return TONE.accepted;
        case 'rejected':
            return TONE.declined;
        case 'canceled':
        case 'cancelled':
            return TONE.neutral;
        default:
            return TONE.pending;
    }
}

/** ProjectExpendableStatus -> board colour. */
export function proposalTone(status) {
    switch (normalise(status)) {
        case 'accepted':
            return TONE.accepted;
        case 'rejected':
            return TONE.declined;
        case 'completed':
            return TONE.done;
        case 'shortlisted':
            return 'var(--color-bright-blue)';
        default:
            // 'Pending Approval' — the team hasn't decided yet.
            return TONE.review;
    }
}

/** BillStatus -> board colour. */
export function billTone(status) {
    switch (normalise(status)) {
        case 'paid':
            return TONE.accepted;
        case 'partial paid':
            return TONE.progress;
        case 'approved':
            return TONE.review;
        case 'void':
            return TONE.neutral;
        default:
            return TONE.pending;
    }
}

/** Deliverable status -> board colour. */
export function deliverableTone(status) {
    switch (normalise(status)) {
        case 'in progress':
            return TONE.progress;
        case 'completed':
            return TONE.accepted;
        case 'canceled':
        case 'cancelled':
            return TONE.neutral;
        default:
            return TONE.pending;
    }
}

/** "1 bill" / "3 bills" — the mock's helper, kept because the copy relies on it. */
export function plural(n, word) {
    return `${n} ${word}${Number(n) === 1 ? '' : 's'}`;
}

/**
 * Strip anything that isn't a number out of a user-typed amount.
 * Guests paste "$4,200.00" and "4200 AUD" into amount fields constantly.
 */
export function num(value) {
    const n = Number(String(value ?? '').replace(/[^0-9.]/g, ''));
    return Number.isFinite(n) ? n : 0;
}

export function money(amount, currency = 'AUD') {
    const n = Number(amount) || 0;
    const fractionDigits = Number.isInteger(n) ? 0 : 2;
    try {
        return new Intl.NumberFormat('en-AU', {
            style: 'currency',
            currency,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        }).format(n);
    } catch {
        // Unknown currency code, or a browser without narrowSymbol support.
        return `${currency} ${n.toLocaleString('en-AU', {
            minimumFractionDigits: fractionDigits,
            maximumFractionDigits: fractionDigits,
        })}`;
    }
}

export const CURRENCIES = ['AUD', 'USD', 'EUR', 'GBP', 'PKR', 'INR'];

/**
 * Total a list of `{amount, currency}` rows without ever adding two currencies
 * together. Returns one formatted figure per currency, joined — so a guest with an
 * AUD proposal and a PKR one sees "A$5,000 + ₨500,000", not a meaningless sum.
 */
export function sumByCurrency(items, fallbackCurrency = 'AUD') {
    const totals = new Map();
    items.forEach((i) => {
        const c = i.currency || fallbackCurrency;
        totals.set(c, (totals.get(c) || 0) + Number(i.amount || 0));
    });
    if (totals.size === 0) return money(0, fallbackCurrency);
    return [...totals.entries()].map(([c, n]) => money(n, c)).join(' + ');
}

/**
 * Payment terms are stored in project_expendables.payment_terms as a JSON string.
 * The shape is set by the existing Vue PaymentTermsBuilder and is read by the admin
 * side, so it must not change:
 *   fixed | installments | milestone -> { type, installments: [{ label, percentage }] }
 *   retainer                         -> { type, months, monthly_amount }
 *   hourly                           -> { type, hourly_rate, estimated_hours }
 * We add an optional `notes` key for the mock's "extra conditions" field; the Vue
 * builder ignores unknown keys, so older readers are unaffected.
 */
export const PAY_TYPES = [
    { value: 'fixed', label: 'Fixed price — full on completion' },
    { value: 'installments', label: 'Custom installments' },
    { value: 'milestone', label: 'Milestone based' },
    { value: 'retainer', label: 'Monthly retainer' },
    { value: 'hourly', label: 'Hourly' },
];

export function parsePaymentTerms(raw) {
    if (!raw) return null;
    if (typeof raw === 'object') return raw;
    try {
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        // Legacy rows stored free text before the builder existed.
        return { type: 'notes', notes: String(raw) };
    }
}

/** One-line summary of payment terms, for proposal cards. */
export function describePaymentTerms(raw, currency = 'AUD') {
    const terms = parsePaymentTerms(raw);
    if (!terms) return '';

    if (terms.type === 'retainer') {
        const months = num(terms.months);
        return `${money(terms.monthly_amount, currency)}/month for ${plural(months, 'month')}`;
    }
    if (terms.type === 'hourly') {
        return `${money(terms.hourly_rate, currency)}/hour × ${num(terms.estimated_hours)} hours`;
    }
    if (Array.isArray(terms.installments) && terms.installments.length) {
        if (terms.installments.length === 1) {
            return `${num(terms.installments[0].percentage)}% on ${terms.installments[0].label || 'completion'}`;
        }
        const shares = terms.installments.map((i) => `${num(i.percentage)}%`).join(' / ');
        return `${shares} across ${plural(terms.installments.length, 'stage')}`;
    }
    if (terms.notes) return terms.notes;
    return '';
}

/** Even split of 100% across n rows, with the remainder on the last row. */
export function evenPercentages(count) {
    if (count < 1) return [];
    const each = Math.floor(100 / count);
    return Array.from({ length: count }, (_, i) => (i === count - 1 ? 100 - each * (count - 1) : each));
}
