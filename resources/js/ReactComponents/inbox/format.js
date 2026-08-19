/**
 * Presentation helpers for the redesigned inbox.
 *
 * Design source: Redesign/Multi-proposal milestone submission page/Inbox.dc.html —
 * the `decorate()` method in that file's script block is the origin of the tone rules
 * below, kept deliberately close to it so the two can be compared side by side.
 *
 * The one substantive change from the mock: the mock carries a `minutesAgo` integer per
 * email and recomputes from it. Here the server sends `reply.minutes_left` (negative once
 * breached) and `reply.due_at`, so the sign of one number decides overdue-vs-not and the
 * client never has to agree with the server about what "now" is.
 */

/** Tones used for the reply clock. Raw hex where the design mock used raw hex. */
const TONE = {
    neutral: {
        dot: 'var(--ui-border-color)',
        fg: 'var(--secondary-text-color)',
        bg: 'var(--allgrey-background-color)',
    },
    overdue: { dot: '#d83a52', fg: '#d83a52', bg: 'rgba(216,58,82,0.12)' },
    soon: { dot: '#fdab3d', fg: '#b5701a', bg: 'rgba(253,171,61,0.16)' },
    ok: { dot: '#00854d', fg: '#00854d', bg: 'rgba(0,133,77,0.12)' },
    ai: {
        dot: 'var(--primary-color)',
        fg: 'var(--primary-color)',
        bg: 'var(--primary-highlighted-color)',
    },
    pending: {
        dot: 'var(--color-working-orange)',
        fg: 'var(--primary-text-color)',
        bg: 'var(--allgrey-background-color)',
    },
};

/** Minutes left below which the clock turns amber. */
const WARN_MINUTES = 15;

/**
 * The status pill for one thread: colour plus the sentence in it.
 *
 * Precedence matters and is not arbitrary — a thread can be several of these at once, and
 * this is the order of "what does the person looking at the list need to do":
 *   with AI → awaiting a manager → unsent draft → reply clock → nothing owed.
 */
export function replyStatus(thread) {
    const flags = thread.flags || {};
    const reply = thread.reply || {};

    if (flags.with_ai) {
        const tone = flags.ai_stalled ? TONE.overdue : TONE.ai;
        return {
            ...tone,
            text: flags.ai_stalled ? 'AI check stalled' : 'With AI',
        };
    }

    if (flags.pending_approval || flags.screening) {
        return { ...TONE.pending, text: flags.screening ? 'Screening' : 'Needs a manager' };
    }

    if (flags.is_draft && !reply.needs_reply) {
        return { ...TONE.neutral, text: 'Not sent' };
    }

    if (!reply.needs_reply) {
        return { ...TONE.neutral, text: reply.answered_at ? 'Replied' : 'No reply owed' };
    }

    const left = reply.minutes_left;

    if (typeof left !== 'number') {
        return { ...TONE.neutral, text: 'Awaiting reply' };
    }

    if (left <= 0) return { ...TONE.overdue, text: `Overdue by ${formatMinutes(-left)}` };
    if (left <= WARN_MINUTES) return { ...TONE.soon, text: `${formatMinutes(left)} to reply` };

    return { ...TONE.ok, text: `${formatMinutes(left)} to reply` };
}

/** "45m", "2h 10m", "3d" — minutes are only useful up close. */
export function formatMinutes(total) {
    const mins = Math.max(0, Math.round(total));
    if (mins < 60) return `${mins}m`;

    const hours = Math.floor(mins / 60);
    if (hours < 24) {
        const rest = mins % 60;
        return rest ? `${hours}h ${rest}m` : `${hours}h`;
    }

    const days = Math.floor(hours / 24);
    return `${days}d`;
}

/** Clock time for today, weekday for this week, date beyond that. */
export function shortTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';

    const now = new Date();
    const sameDay = date.toDateString() === now.toDateString();

    if (sameDay) {
        return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }).toLowerCase();
    }

    const daysAgo = Math.floor((now - date) / 86400000);
    if (daysAgo === 1) return 'Yesterday';
    if (daysAgo < 7) return date.toLocaleDateString(undefined, { weekday: 'short' });

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

/** "Today, 9:18 am" — the fuller form used inside a thread. */
export function longTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';

    const time = date
        .toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
        .toLowerCase();
    const now = new Date();

    if (date.toDateString() === now.toDateString()) return `Today, ${time}`;

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) return `Yesterday, ${time}`;

    return `${date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })}, ${time}`;
}

/**
 * A stable colour per category name.
 *
 * Categories are user-created and carry no colour of their own, so the alternative to
 * hashing is every chip looking the same. Hashing the name (rather than the id) keeps a
 * category the same colour across environments where ids differ.
 */
const CATEGORY_COLOURS = [
    'var(--color-done-green)',
    'var(--color-working-orange)',
    'var(--primary-color)',
    'var(--color-explosive)',
    'var(--color-indigo)',
    'var(--color-dark-purple)',
];

export function categoryColour(name) {
    const key = String(name || '');
    let hash = 0;
    for (let i = 0; i < key.length; i += 1) hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    return CATEGORY_COLOURS[hash % CATEGORY_COLOURS.length];
}

export function initials(name) {
    return String(name || '?')
        .trim()
        .split(/\s+/)
        .filter((w) => !/^(dr|mr|mrs|ms|prof)\.?$/i.test(w))
        .map((w) => w[0])
        .filter(Boolean)
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

export function plural(count, one, many) {
    return `${count} ${count === 1 ? one : many || `${one}s`}`;
}

/** Bytes → "412 KB". Attachments come back in bytes or already-formatted; pass both through. */
export function fileSize(value) {
    if (value == null) return '';
    if (typeof value === 'string') return value;

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }
    return `${size < 10 && unit > 0 ? size.toFixed(1) : Math.round(size)} ${units[unit]}`;
}

export const VIEW_DEFS = [
    { key: 'needsReply', label: 'Needs reply', icon: 'Alert', hint: 'Inbound mail with the reply clock running' },
    { key: 'new', label: 'New', icon: 'Inbox', hint: 'Anything you have not read' },
    { key: 'withAi', label: 'With AI', icon: 'Robot', hint: 'Submitted drafts the checker is still verifying' },
    { key: 'approval', label: 'Waiting approval', icon: 'Security', hint: 'Drafts to approve, plus screened inbound mail to release' },
    { key: 'received', label: 'Received', icon: 'Email', hint: 'Everything from clients and leads' },
    { key: 'sent', label: 'Sent', icon: 'Send', hint: 'Approved and delivered' },
    { key: 'drafts', label: 'Drafts', icon: 'Doc', hint: 'Saved and unsent' },
    { key: 'all', label: 'All mail', icon: 'Archive', hint: 'Complete log for your projects' },
];

export function viewSubtitle(view, overdueCount, slaMinutes) {
    const rule = `${formatMinutes(slaMinutes)} reply rule`;

    switch (view) {
        case 'needsReply':
            return overdueCount
                ? `${plural(overdueCount, 'thread')} past the ${rule} · reply first, action later`
                : `All inside the ${rule} · reply first, action later`;
        case 'new':
            return 'Unread across your projects';
        case 'withAi':
            return 'Locked while the checker verifies them';
        case 'approval':
            return 'Drafts waiting on you, plus inbound mail to release';
        case 'received':
            return 'Everything from clients and leads';
        case 'sent':
            return 'Approved and delivered';
        case 'drafts':
            return 'Saved and unsent — pick up where you left off';
        default:
            return 'Complete log for your projects';
    }
}
