/**
 * Button / ButtonGroup — ported from the OZee design system bundle.
 *
 * Sizes, fills and the signature `scale(0.95)` press squeeze are reproduced from
 * _ds_bundle.js so a React page is visually identical to the design mock.
 */

import { useState } from 'react';
import { Icon } from './Icon';

const SIZES = {
    xxs: { height: 16, padding: '2px var(--space-4)', font: 'var(--font-text2-normal)', line: '16px' },
    xs: { height: 24, padding: 'var(--space-4) var(--space-8)', font: 'var(--font-text2-normal)', line: '21px' },
    small: { height: 32, padding: 'var(--space-4) var(--space-8)', font: 'var(--font-text2-normal)', line: '24px' },
    medium: { height: 40, padding: 'var(--space-8) var(--space-16)', font: 'var(--font-text1-normal)', line: '22px' },
    large: { height: 48, padding: '12px var(--space-24)', font: 'var(--font-text1-normal)', line: '22px' },
};

const FILL = {
    primary: { bg: 'var(--primary-color)', hover: 'var(--primary-hover-color)', fg: 'var(--text-color-on-primary)' },
    brand: { bg: 'var(--brand-color)', hover: 'var(--brand-hover-color)', fg: 'var(--text-color-on-brand)' },
    positive: { bg: 'var(--positive-color)', hover: 'var(--positive-color-hover)', fg: 'var(--text-color-on-primary)' },
    negative: { bg: 'var(--negative-color)', hover: 'var(--negative-color-hover)', fg: 'var(--text-color-on-primary)' },
    inverted: { bg: 'var(--inverted-color-background)', hover: 'var(--placeholder-color)', fg: 'var(--text-color-on-inverted)' },
};

const OUTLINE_FG = {
    primary: 'var(--primary-text-color)',
    brand: 'var(--primary-text-color)',
    positive: 'var(--positive-color)',
    negative: 'var(--negative-color)',
    inverted: 'var(--primary-text-color)',
};

export function Button({
    children,
    kind = 'primary',
    color = 'primary',
    size = 'medium',
    disabled = false,
    active = false,
    loading = false,
    leftIcon = null,
    rightIcon = null,
    fullWidth = false,
    type = 'button',
    onClick,
    style,
    ...rest
}) {
    const [hover, setHover] = useState(false);
    const s = SIZES[size] || SIZES.medium;
    const tone = FILL[color] || FILL.primary;
    const isDisabled = disabled || loading;

    const base = {
        display: fullWidth ? 'flex' : 'inline-flex',
        width: fullWidth ? '100%' : undefined,
        alignItems: 'center',
        justifyContent: 'center',
        gap: 'var(--space-8)',
        height: s.height,
        padding: s.padding,
        font: s.font,
        lineHeight: s.line,
        borderRadius: 'var(--border-radius-small)',
        border: kind === 'secondary' ? '1px solid' : 'none',
        cursor: isDisabled ? 'not-allowed' : 'pointer',
        whiteSpace: 'nowrap',
        userSelect: 'none',
        transition:
            'var(--motion-productive-short) transform, var(--motion-productive-medium) background-color',
        transform: 'scale(1) translate3d(0,0,0)',
    };

    let skin;
    if (kind === 'primary') {
        skin = disabled
            ? { background: 'var(--disabled-background-color)', color: 'var(--disabled-text-color)' }
            : { background: hover ? tone.hover : tone.bg, color: tone.fg };
    } else if (kind === 'secondary') {
        skin = {
            background: active
                ? 'var(--primary-selected-color)'
                : hover && !isDisabled
                  ? 'var(--primary-background-hover-color)'
                  : 'transparent',
            borderColor: disabled
                ? 'var(--disabled-text-color)'
                : active
                  ? 'var(--primary-color)'
                  : color === 'positive'
                    ? 'var(--positive-color)'
                    : color === 'negative'
                      ? 'var(--negative-color)'
                      : 'var(--ui-border-color)',
            color: disabled ? 'var(--disabled-text-color)' : OUTLINE_FG[color],
        };
    } else {
        skin = {
            background: active
                ? 'var(--primary-selected-color)'
                : hover && !isDisabled
                  ? 'var(--primary-background-hover-color)'
                  : 'transparent',
            color: disabled ? 'var(--disabled-text-color)' : OUTLINE_FG[color],
        };
    }

    return (
        <button
            type={type}
            disabled={isDisabled}
            onClick={isDisabled ? undefined : onClick}
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            onMouseDown={(e) => {
                if (!isDisabled) e.currentTarget.style.transform = 'scale(0.95) translate3d(0,0,0)';
            }}
            onMouseUp={(e) => {
                e.currentTarget.style.transform = 'scale(1) translate3d(0,0,0)';
            }}
            style={{ ...base, ...skin, ...style }}
            {...rest}
        >
            {loading ? (
                <span
                    style={{
                        display: 'inline-block',
                        width: 16,
                        height: 16,
                        border: '2px solid currentColor',
                        borderTopColor: 'transparent',
                        borderRadius: '50%',
                        animation: 'ozeeSpin 800ms linear infinite',
                    }}
                />
            ) : (
                leftIcon
            )}
            {!loading && children}
            {!loading && rightIcon}
        </button>
    );
}

export function ButtonGroup({ options = [], value, onChange, size = 'small', style }) {
    return (
        <div
            role="group"
            style={{
                display: 'inline-flex',
                border: '1px solid var(--ui-border-color)',
                borderRadius: 'var(--border-radius-small)',
                overflow: 'hidden',
                ...style,
            }}
        >
            {options.map((o, i) => {
                const selected = o.value === value;
                return (
                    <button
                        key={o.value}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => onChange && onChange(o.value)}
                        style={{
                            height: size === 'small' ? 32 : 40,
                            padding: '0 var(--space-12)',
                            border: 'none',
                            borderInlineStart: i === 0 ? 'none' : '1px solid var(--ui-border-color)',
                            background: selected ? 'var(--primary-selected-color)' : 'transparent',
                            color: selected ? 'var(--primary-color)' : 'var(--primary-text-color)',
                            font: 'var(--font-text2-normal)',
                            cursor: 'pointer',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 'var(--space-4)',
                        }}
                    >
                        {o.icon ? <Icon name={o.icon} size={16} /> : null}
                        {o.text}
                    </button>
                );
            })}
        </div>
    );
}
