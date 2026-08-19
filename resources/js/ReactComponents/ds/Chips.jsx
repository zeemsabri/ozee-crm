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
    readOnly = false,
    disabled = false,
    size = 'medium',
    title,
    style,
}) {
    const bg = CHIP_TONES[color] || color;
    const small = size === 'small';

    return (
        <span
            onClick={onClick}
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
                cursor: onClick ? 'pointer' : undefined,
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
