/**
 * Data-display primitives — Label, Counter, Tabs, ProgressBar, Avatar.
 * Ported from the OZee design system bundle.
 *
 * Note on `Label`: `color` accepts either a system role name ("primary", "positive",
 * "negative", "dark") or a raw CSS colour/var(), because status tones on this page
 * come from the 32-colour board palette rather than the four semantic roles.
 */

import { Icon } from './Icon';

export function Label({ text, kind = 'fill', color = 'primary', size = 'medium', style }) {
    const roles = {
        primary: 'var(--primary-color)',
        dark: 'var(--inverted-color-background)',
        positive: 'var(--positive-color)',
        negative: 'var(--negative-color)',
    };
    const fill = roles[color] || color;
    const small = size === 'small';
    const base = {
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: small ? 2 : 'var(--border-radius-small)',
        padding: small ? '0 var(--space-4)' : '2px var(--space-8)',
        font: small ? 'var(--font-text3-medium)' : 'var(--font-text2-normal)',
        whiteSpace: 'nowrap',
        ...style,
    };

    if (kind === 'line') {
        return (
            <span
                style={{
                    ...base,
                    border: '1px solid currentColor',
                    padding: small ? '0 var(--space-4)' : '1px var(--space-8)',
                    color: fill,
                }}
            >
                {text}
            </span>
        );
    }

    return (
        <span style={{ ...base, background: fill, color: 'var(--text-color-on-primary)' }}>{text}</span>
    );
}

export function Counter({ count = 0, kind = 'fill', color = 'primary', size = 'small', maxDigits = 3, style }) {
    const shown = String(count).length > maxDigits ? `${'9'.repeat(maxDigits)}+` : count;
    const fills = {
        primary: ['var(--primary-color)', 'var(--fixed-light-color)'],
        dark: ['var(--inverted-color-background)', 'var(--text-color-on-inverted)'],
        negative: ['var(--negative-color)', 'var(--fixed-light-color)'],
        light: ['var(--ui-background-color)', 'var(--primary-text-color)'],
    };
    const [bg, fg] = fills[color] || fills.primary;
    const large = size === 'large';
    const base = {
        display: 'inline-flex',
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 30,
        minWidth: large ? 24 : 18,
        lineHeight: large ? '20px' : '18px',
        padding: large ? '2px var(--space-8)' : '0 var(--space-8)',
        font:
            size === 'xs'
                ? 'var(--font-text3-normal)'
                : large
                  ? 'var(--font-text2-normal)'
                  : 'var(--font-text3-medium)',
        ...style,
    };

    if (kind === 'line') {
        return <span style={{ ...base, color: bg, boxShadow: '0 0 0 1px currentColor inset' }}>{shown}</span>;
    }
    return <span style={{ ...base, background: bg, color: fg }}>{shown}</span>;
}

export function Tabs({ tabs = [], value, onChange, size = 'medium', style }) {
    return (
        <div
            role="tablist"
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--space-4)',
                borderBottom: '1px solid var(--layout-border-color)',
                ...style,
            }}
        >
            {tabs.map((t) => {
                const active = t.value === value;
                return (
                    <button
                        key={t.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        onClick={() => !t.disabled && onChange && onChange(t.value)}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 'var(--space-8)',
                            height: size === 'small' ? 32 : 40,
                            padding: '0 var(--space-12)',
                            border: 'none',
                            background: 'transparent',
                            cursor: t.disabled ? 'not-allowed' : 'pointer',
                            font: active ? 'var(--font-text2-medium)' : 'var(--font-text2-normal)',
                            color: t.disabled
                                ? 'var(--disabled-text-color)'
                                : active
                                  ? 'var(--primary-color)'
                                  : 'var(--secondary-text-color)',
                            boxShadow: active ? 'inset 0 -2px 0 var(--primary-color)' : 'none',
                            transition: 'color var(--motion-productive-medium)',
                        }}
                    >
                        {t.icon ? <Icon name={t.icon} size={16} /> : null}
                        {t.label}
                        {typeof t.count === 'number' ? (
                            <span
                                style={{
                                    minWidth: 18,
                                    padding: '0 6px',
                                    borderRadius: 30,
                                    background: active
                                        ? 'var(--primary-selected-color)'
                                        : 'var(--ui-background-color)',
                                    color: active ? 'var(--primary-color)' : 'var(--secondary-text-color)',
                                    font: 'var(--font-text3-medium)',
                                }}
                            >
                                {t.count}
                            </span>
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}

export function ProgressBar({ value = 0, max = 100, color = 'primary', size = 'medium', showLabel = false, style }) {
    const pct = Math.max(0, Math.min(100, (value / max) * 100));
    const fill =
        {
            primary: 'var(--primary-color)',
            positive: 'var(--positive-color)',
            negative: 'var(--negative-color)',
            warning: 'var(--warning-color)',
        }[color] || 'var(--primary-color)';
    const h = size === 'small' ? 4 : size === 'large' ? 12 : 8;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-8)', width: '100%', ...style }}>
            <div
                style={{
                    flex: 1,
                    height: h,
                    borderRadius: h / 2,
                    background: 'var(--ui-background-color)',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: `${pct}%`,
                        height: '100%',
                        background: fill,
                        borderRadius: h / 2,
                        transition: 'width var(--motion-expressive-short) var(--motion-timing-transition)',
                    }}
                />
            </div>
            {showLabel ? (
                <span
                    style={{
                        font: 'var(--font-text3-medium)',
                        color: 'var(--secondary-text-color)',
                        minWidth: 32,
                        textAlign: 'end',
                    }}
                >
                    {Math.round(pct)}%
                </span>
            ) : null}
        </div>
    );
}

const AVATAR_SIZES = { xs: 16, small: 24, medium: 32, large: 40 };
const AVATAR_PALETTE = [
    'var(--color-indigo)',
    'var(--color-dark-purple)',
    'var(--color-navy)',
    'var(--color-teal)',
    'var(--color-berry)',
    'var(--color-brown)',
    'var(--color-dark-blue)',
    'var(--color-grass-green)',
];

function initials(text) {
    if (!text) return '';
    return String(text)
        .trim()
        .split(/\s+/)
        .map((w) => w[0])
        .filter(Boolean)
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

export function Avatar({ src, text, ariaLabel, size = 'medium', square = false, backgroundColor, style, ...rest }) {
    const px = AVATAR_SIZES[size] || 32;
    const seedIndex = text ? String(text).charCodeAt(0) % AVATAR_PALETTE.length : 0;
    const bg = backgroundColor || AVATAR_PALETTE[seedIndex];

    return (
        <span
            style={{ position: 'relative', display: 'inline-block', flex: 'none', width: px, height: px, ...style }}
            {...rest}
        >
            <span
                aria-label={ariaLabel || text}
                style={{
                    width: '100%',
                    height: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    borderRadius: square ? 'var(--border-radius-small)' : '50%',
                    overflow: 'hidden',
                    border: '1px solid',
                    borderColor: src ? 'var(--primary-background-color)' : 'var(--layout-border-color)',
                    background: src ? 'transparent' : bg,
                    color: 'var(--text-color-on-primary)',
                    font:
                        px <= 24
                            ? 'var(--font-text3-medium)'
                            : px <= 32
                              ? 'var(--font-text2-medium)'
                              : 'var(--font-text1-medium)',
                }}
            >
                {src ? (
                    <img src={src} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                ) : (
                    initials(text)
                )}
            </span>
        </span>
    );
}
