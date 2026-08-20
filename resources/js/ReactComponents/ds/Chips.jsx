/**
 * Chips — ported from the OZee design system bundle (components/display/Chips.jsx).
 *
 * Used for category pills, recipient tokens and attachment tokens. `color` accepts one
 * of the named tones below or any raw CSS colour, so pages can pass a token straight
 * through (the inbox passes --color-done-green etc. for categories).
 */

import { Icon } from './Icon';

const CHIP_TONES = {
    primary: 'var(--primary-selected-color)',
    positive: 'var(--positive-color-selected)',
    negative: 'var(--negative-color-selected)',
    warning: 'var(--warning-color-selected)',
    neutral: 'var(--ui-background-color)',
};

export function Chips({
    label,
    color = 'primary',
    leftIcon,
    leftAvatar,
    onDelete,
    onClick,
    /** Renders as a toggle when set — drives aria-pressed. */
    selected,
    readOnly = false,
    disabled = false,
    size = 'medium',
    title,
    style,
}) {
    const bg = CHIP_TONES[color] || color;
    const small = size === 'small';

    /*
     * A chip with an onClick is a control, so it gets the semantics of one.
     *
     * The bundle renders a bare <span onClick>, which is fine for a static label and
     * wrong the moment it does something: selecting recipients and filtering by category
     * are both chip-only interactions in this app, and as spans they were unreachable by
     * keyboard and invisible to a screen reader.
     */
    const interactive = !!onClick && !disabled;

    const keyActivate = (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onClick(e);
        }
    };

    return (
        <span
            onClick={disabled ? undefined : onClick}
            onKeyDown={interactive ? keyActivate : undefined}
            role={interactive ? 'button' : undefined}
            tabIndex={interactive ? 0 : undefined}
            aria-pressed={interactive && selected !== undefined ? !!selected : undefined}
            aria-disabled={disabled || undefined}
            title={title}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: small ? 'var(--space-2)' : 'var(--space-4)',
                height: small ? 20 : 24,
                padding: small ? '0 6px' : '0 8px',
                borderRadius: 4,
                background: bg,
                color: disabled ? 'var(--disabled-text-color)' : 'var(--primary-text-color)',
                font: 'var(--font-text2-normal)',
                userSelect: readOnly ? 'text' : 'none',
                cursor: interactive ? 'pointer' : disabled ? 'not-allowed' : undefined,
                // Selection has to survive being read without colour — the tone alone is
                // a single low-contrast cue on a 20px chip.
                boxShadow: interactive && selected ? 'inset 0 0 0 1px var(--primary-color)' : undefined,
                flex: 'none',
                maxWidth: 220,
                ...style,
            }}
        >
            {leftAvatar ? (
                <img
                    src={leftAvatar}
                    alt=""
                    style={{ width: 18, height: 18, borderRadius: '50%', objectFit: 'cover' }}
                />
            ) : null}
            {leftIcon ? <Icon name={leftIcon} size={small ? 14 : 16} /> : null}
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {label}
            </span>
            {onDelete && !readOnly ? (
                <span
                    role="button"
                    aria-label={`Remove ${label}`}
                    onClick={(e) => {
                        e.stopPropagation();
                        onDelete(e);
                    }}
                    style={{ display: 'inline-flex', cursor: 'pointer' }}
                >
                    <Icon name="CloseSmall" size={14} />
                </span>
            ) : null}
        </span>
    );
}
