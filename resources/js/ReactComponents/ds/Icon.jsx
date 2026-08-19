/**
 * Icon / IconButton — ported from the OZee design system bundle
 * (the `_ds_bundle.js` under Redesign/proposal/_ds/vibe-monday-…).
 *
 * The glyph set is the 90-icon Vibe library shipped as raw SVGs. Rather than
 * inlining each one as a component, we render them the way the design system does:
 * as a CSS mask filled with `background: currentColor`, so any glyph takes any
 * token colour. The SVGs live in public/ozee-ds/icons — copied from
 * Redesign/proposal/assets/icons.
 */

import { useState } from 'react';

const ICON_BASE = '/ozee-ds/icons';

const ICON_SIZES = {
    xs: 16,
    small: 20,
    medium: 24,
    large: 32,
};

export function Icon({ name, src, size = 20, color = 'currentColor', title, style, ...rest }) {
    const base = (typeof window !== 'undefined' && window.OZEE_ICON_BASE) || ICON_BASE;
    const url = src || `${base}/${name}.svg`;
    const px = typeof size === 'string' ? ICON_SIZES[size] || 20 : size;
    const mask = `url("${url}") center / contain no-repeat`;

    return (
        <span
            role={title ? 'img' : 'presentation'}
            aria-label={title}
            style={{
                display: 'inline-block',
                flex: 'none',
                width: px,
                height: px,
                background: color,
                WebkitMask: mask,
                mask,
                ...style,
            }}
            {...rest}
        />
    );
}

const IB_BOX = { xs: 24, small: 32, medium: 40, large: 48 };
const IB_GLYPH = { xs: 16, small: 16, medium: 20, large: 24 };

export function IconButton({
    icon,
    name,
    size = 'medium',
    kind = 'tertiary',
    active = false,
    disabled = false,
    ariaLabel,
    onClick,
    style,
    ...rest
}) {
    const [hover, setHover] = useState(false);
    const box = IB_BOX[size] || 40;
    const glyph = IB_GLYPH[size] || 20;

    return (
        <button
            type="button"
            aria-label={ariaLabel}
            disabled={disabled}
            onClick={disabled ? undefined : onClick}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            style={{
                width: box,
                height: box,
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                border: kind === 'secondary' ? '1px solid var(--ui-border-color)' : 'none',
                borderRadius: 'var(--border-radius-small)',
                background: active
                    ? 'var(--primary-selected-color)'
                    : hover && !disabled
                      ? 'var(--primary-background-hover-color)'
                      : 'transparent',
                color: disabled
                    ? 'var(--disabled-text-color)'
                    : active
                      ? 'var(--primary-color)'
                      : 'var(--icon-color)',
                cursor: disabled ? 'not-allowed' : 'pointer',
                transition:
                    'background-color var(--motion-productive-medium) var(--motion-timing-transition)',
                ...style,
            }}
            {...rest}
        >
            {icon || <Icon name={name} size={glyph} />}
        </button>
    );
}
