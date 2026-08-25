/**
 * The two things the composer adds for you: the greeting at the top and the sign-off at
 * the bottom.
 *
 * Both were already happening and neither was on screen. A custom email posted to
 * `POST /api/emails` has `greeting_name` prepended by HandlesEmailCreation, and every
 * outgoing message is wrapped by `resources/views/emails/email_template.blade.php`, which
 * renders the branded signature block from `config('branding')`. Someone composing here
 * saw neither, so they either typed their own "Hi Sarah," and shipped it twice, or wrote
 * a sign-off the layout then repeated underneath.
 *
 * The greeting options mirror `Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue`
 * — Full name / First name / Last name / Custom — with one addition: "No greeting". The
 * Vue composer has no such option because it always sends one; a reply may legitimately
 * have none, and an AI draft writes its own opening (see InboxAiService::draftReply), so
 * the option has to exist here.
 *
 * greetingTextFor() is exported and used for BOTH the preview and the posted value, so
 * what is shown and what is sent cannot drift.
 */

import { useState } from 'react';
import { Dropdown, Icon, Popover, TextField, useAnchoredPopover } from '../ds';

// `label`, not `text`: ds/Dropdown reads `option.label`, while ds/ButtonGroup reads
// `option.text`. Both appear in this folder — check which control you are feeding.
export const GREETING_MODES = [
    { value: 'full_name', label: 'Full name' },
    { value: 'first_name', label: 'First name' },
    { value: 'last_name', label: 'Last name' },
    { value: 'custom', label: 'Something else' },
    { value: 'none', label: 'No greeting' },
];

/** "Sarah Chen" → first "Sarah", last "Chen". A one-word name is both. */
function part(name, which) {
    const bits = String(name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (bits.length === 0) return '';
    if (which === 'first') return bits[0];

    return bits.length > 1 ? bits[bits.length - 1] : bits[0];
}

/**
 * The literal opening line, or '' for none.
 *
 * Several recipients are joined with "&", matching the Vue composer: "Hi Sarah & Tom,".
 * An unknown name falls back to "Hi there," rather than an empty greeting, because the
 * server's own fallback is the same string and a silent difference between the preview
 * and the sent mail is the thing this component exists to prevent.
 */
export function greetingTextFor({ mode, customName, names }) {
    if (mode === 'none') return '';

    if (mode === 'custom') {
        const typed = String(customName || '').trim();

        return `Hi ${typed || 'there'},`;
    }

    const resolved = (names || [])
        .map((name) => {
            if (mode === 'first_name') return part(name, 'first');
            if (mode === 'last_name') return part(name, 'last');

            return String(name || '').trim();
        })
        .filter(Boolean);

    if (resolved.length === 0) return 'Hi there,';

    return `Hi ${resolved.join(' & ')},`;
}

/**
 * The picker plus the resolved line.
 *
 * `names` are display names, never addresses — for a reply they come from the recipients
 * endpoint (`recipient_names`, see Correspondent::namesFor), for a new email from the
 * clients already loaded for the recipient chips.
 */
export function GreetingPicker({ mode, customName, names, onMode, onCustomName, disabled, allowNone = false }) {
    const text = greetingTextFor({ mode, customName, names });

    /*
     * "No greeting" is offered on replies and withheld on new emails, because on a new
     * email it would be a lie: HandlesEmailCreation prepends `greeting_name` and falls
     * back to a literal "Hi there" when it is empty, and the block builder unshifts the
     * greeting as its first block — an empty one renders as a bare comma. The server
     * contract for a first message is that it opens with something, so the control does
     * not pretend otherwise.
     */
    const options = allowNone ? GREETING_MODES : GREETING_MODES.filter((o) => o.value !== 'none');

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8, flexWrap: 'wrap' }}>
                <Dropdown
                    label="Address them by"
                    size="small"
                    options={options}
                    value={mode}
                    disabled={disabled}
                    onChange={(value) => onMode(value)}
                    style={{ minWidth: 160 }}
                />
                {mode === 'custom' ? (
                    <TextField
                        label="Name"
                        size="small"
                        placeholder="e.g. Sarah"
                        value={customName || ''}
                        disabled={disabled}
                        onChange={(e) => onCustomName(e.target.value)}
                        style={{ minWidth: 160 }}
                    />
                ) : null}
            </div>

            <div
                style={{
                    font: '400 13px/20px Figtree, sans-serif',
                    color: text ? 'var(--primary-text-color)' : 'var(--secondary-text-color)',
                }}
            >
                {text ? (
                    <>
                        <span style={{ color: 'var(--secondary-text-color)' }}>Opens with </span>
                        <strong style={{ font: '600 13px/20px Figtree, sans-serif' }}>{text}</strong>
                    </>
                ) : (
                    'No greeting — your message starts with whatever you type.'
                )}
            </div>
        </div>
    );
}

/**
 * The greeting as the letter shows it — the opening line itself, editable in place.
 *
 * Same state and the same greetingTextFor() as GreetingPicker (which ReplyBox still
 * uses), different clothes: the composer's letter card shows the literal "Hi Alan,"
 * the client will read, with a dashed underline and a chevron to say it can change.
 * Clicking it opens the mode list; "Something else" reveals the name field inside the
 * panel. No "No greeting" here for the same reason GreetingPicker withholds it on new
 * emails: HandlesEmailCreation always prepends one.
 */
export function GreetingLine({ mode, customName, names, onMode, onCustomName, disabled }) {
    const pop = useAnchoredPopover({ preferredHeight: 300, align: 'start', minWidth: 260 });
    const [hover, setHover] = useState(false);
    const text = greetingTextFor({ mode, customName, names });
    const options = GREETING_MODES.filter((o) => o.value !== 'none');

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
            <span ref={pop.anchorRef} style={{ display: 'inline-flex' }}>
                <button
                    type="button"
                    disabled={disabled}
                    title="Change how the email opens"
                    onClick={() => pop.setOpen(!pop.open)}
                    onMouseEnter={() => setHover(true)}
                    onMouseLeave={() => setHover(false)}
                    style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: 6,
                        border: 'none',
                        background: hover && !disabled ? 'var(--primary-background-hover-color)' : 'transparent',
                        borderRadius: 'var(--border-radius-small)',
                        padding: '2px 6px 2px 2px',
                        margin: '-2px 0 -2px -2px',
                        cursor: disabled ? 'default' : 'pointer',
                        font: 'var(--font-text2-normal)',
                        color: 'var(--primary-text-color)',
                    }}
                >
                    <span style={{ borderBottom: '1px dashed var(--ui-border-color)', lineHeight: '22px' }}>
                        {text || 'Hi there,'}
                    </span>
                    <Icon name="DropdownChevronDown" size={14} color="var(--icon-color)" />
                </button>
            </span>
            <span
                style={{
                    marginInlineStart: 'auto',
                    font: 'var(--font-text3-normal)',
                    color: 'var(--secondary-text-color)',
                }}
            >
                greeting added for you
            </span>

            {pop.open ? (
                <Popover
                    position={pop.position}
                    panelRef={pop.panelRef}
                    style={{
                        background: 'var(--dialog-background-color)',
                        border: '1px solid var(--layout-border-color)',
                        borderRadius: 'var(--border-radius-medium)',
                        boxShadow: 'var(--box-shadow-medium)',
                        padding: 8,
                    }}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 2, width: 240 }}>
                        <span
                            style={{
                                font: 'var(--font-text3-medium)',
                                color: 'var(--secondary-text-color)',
                                padding: '4px 8px',
                            }}
                        >
                            Address them by
                        </span>
                        {options.map((o) => {
                            const on = o.value === mode;
                            return (
                                <button
                                    key={o.value}
                                    type="button"
                                    onClick={() => {
                                        onMode(o.value);
                                        if (o.value !== 'custom') pop.setOpen(false);
                                    }}
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 8,
                                        border: 'none',
                                        textAlign: 'start',
                                        background: on ? 'var(--primary-selected-color)' : 'transparent',
                                        color: on ? 'var(--primary-color)' : 'var(--primary-text-color)',
                                        borderRadius: 'var(--border-radius-small)',
                                        padding: '6px 8px',
                                        cursor: 'pointer',
                                        font: 'var(--font-text2-normal)',
                                    }}
                                >
                                    {o.label}
                                </button>
                            );
                        })}
                        {mode === 'custom' ? (
                            <div style={{ padding: '6px 8px 4px' }}>
                                <TextField
                                    label="Name"
                                    size="small"
                                    placeholder="e.g. Sarah"
                                    value={customName || ''}
                                    onChange={(e) => onCustomName(e.target.value)}
                                />
                            </div>
                        ) : null}
                    </div>
                </Popover>
            ) : null}
        </div>
    );
}

/**
 * What gets added under the message.
 *
 * Compact on purpose. The real block is `email_template.blade.php` rendering
 * `config('branding')` — logo, phone, website, social icons, the review prompt — and
 * reproducing all of that here would mean a second copy of that markup in JS, drifting
 * the moment anyone edits the Blade file. The name and role are the parts that change per
 * sender and are worth showing exactly; the rest is named rather than drawn.
 *
 * `signOff` is the page's `settings.sign_off` — the signed-in user's own name and project
 * role, which is what the send path resolves too (HandlesTemplatedEmails::getSenderDetails).
 */
export function SignOffPreview({ signOff }) {
    return (
        <div
            style={{
                borderTop: '1px solid var(--om-hairline)',
                paddingTop: 10,
                display: 'flex',
                flexDirection: 'column',
                gap: 2,
            }}
        >
            <div style={{ font: '400 13px/20px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                Best Regards
            </div>
            <div style={{ font: '600 13px/20px Figtree, sans-serif', color: 'var(--primary-text-color)' }}>
                {signOff?.name || 'Your name'}
            </div>
            {signOff?.role ? (
                <div style={{ font: '400 13px/20px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    {signOff.role}
                </div>
            ) : null}
            <div
                style={{
                    marginTop: 4,
                    font: 'italic 400 11px/16px Figtree, sans-serif',
                    color: 'var(--secondary-text-color)',
                }}
            >
                Added automatically, with our phone number, website, logo and social links —
                you do not need to type a sign-off.
            </div>
        </div>
    );
}
