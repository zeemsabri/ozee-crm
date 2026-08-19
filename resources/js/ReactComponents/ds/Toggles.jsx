/**
 * Checkbox / Toggle — ported from the OZee design system bundle
 * (Redesign/.../_ds_bundle.js, components/forms/Checkbox.jsx).
 *
 * Kept in a separate module from Fields.jsx so the barrel stays readable; these are
 * boolean inputs rather than value-carrying fields.
 *
 * Deviation from the bundle: `onChange` is called with the *next boolean*, not the raw
 * change event. Every call site in this app wants the boolean, and the bundle's mock
 * pages never read the event, so this removes an `e.target.checked` at each usage.
 */

import { useState } from 'react';
import { Icon } from './Icon';

export function Checkbox({
    label,
    checked = false,
    indeterminate = false,
    disabled = false,
    onChange,
    ariaLabel,
    style,
}) {
    const on = checked || indeterminate;
    const [hover, setHover] = useState(false);

    return (
        <label
            onMouseEnter={() => setHover(true)}
            onMouseLeave={() => setHover(false)}
            style={{
                position: 'relative',
                display: 'inline-flex',
                alignItems: 'center',
                width: 'fit-content',
                cursor: disabled ? 'not-allowed' : 'pointer',
                ...style,
            }}
        >
            <input
                type="checkbox"
                checked={!!checked}
                disabled={disabled}
                aria-label={ariaLabel || (typeof label === 'string' ? label : undefined)}
                onChange={(e) => onChange && onChange(e.target.checked, e)}
                style={{ position: 'absolute', opacity: 0, width: 0, height: 0 }}
            />
            <span
                style={{
                    width: 16,
                    height: 16,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    border: '1px solid',
                    borderColor: disabled
                        ? 'var(--ui-border-color)'
                        : on
                          ? 'transparent'
                          : hover
                            ? 'var(--secondary-text-color)'
                            : 'var(--ui-border-color)',
                    borderRadius: 2,
                    background: disabled
                        ? 'var(--disabled-background-color)'
                        : on
                          ? hover
                              ? 'var(--primary-hover-color)'
                              : 'var(--primary-color)'
                          : 'var(--secondary-background-color)',
                    transition: 'transform var(--motion-productive-short) var(--motion-timing-enter)',
                    flex: 'none',
                }}
            >
                {indeterminate ? (
                    <span
                        style={{
                            width: 8,
                            height: 2,
                            background: disabled
                                ? 'var(--disabled-text-color)'
                                : 'var(--text-color-on-primary)',
                        }}
                    />
                ) : checked ? (
                    <Icon
                        name="Check"
                        size={14}
                        color={disabled ? 'var(--disabled-text-color)' : 'var(--text-color-on-primary)'}
                    />
                ) : null}
            </span>
            {label ? (
                <span
                    style={{
                        marginInlineStart: 'var(--space-8)',
                        font: 'var(--font-text2-normal)',
                        color: disabled ? 'var(--disabled-text-color)' : 'var(--primary-text-color)',
                        userSelect: 'none',
                    }}
                >
                    {label}
                </span>
            ) : null}
        </label>
    );
}

export function Toggle({
    checked = false,
    onChange,
    disabled = false,
    size = 'medium',
    areLabelsHidden = true,
    onLabel = 'On',
    offLabel = 'Off',
    ariaLabel,
    style,
}) {
    const dims =
        size === 'small'
            ? { w: 28, h: 16, c: 12, on: 14, off: 2 }
            : { w: 41, h: 24, c: 18, on: 20, off: 3 };

    return (
        <label
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 'var(--space-8)',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.4 : 1,
                ...style,
            }}
        >
            {!areLabelsHidden ? (
                <span style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                    {checked ? onLabel : offLabel}
                </span>
            ) : null}
            <input
                type="checkbox"
                checked={!!checked}
                disabled={disabled}
                aria-label={ariaLabel}
                onChange={(e) => onChange && onChange(e.target.checked, e)}
                style={{ position: 'absolute', opacity: 0, width: 0, height: 0 }}
            />
            <span
                style={{
                    position: 'relative',
                    width: dims.w,
                    height: dims.h,
                    borderRadius: 100,
                    background: checked ? 'var(--primary-color)' : 'var(--ui-border-color)',
                    transition:
                        'background-color var(--motion-productive-medium) var(--motion-timing-transition)',
                    flex: 'none',
                }}
            >
                <span
                    style={{
                        position: 'absolute',
                        width: dims.c,
                        height: dims.c,
                        borderRadius: '50%',
                        background: 'var(--primary-background-color)',
                        top: `calc(50% - ${dims.c / 2}px)`,
                        insetInlineStart: checked ? dims.on : dims.off,
                        transition:
                            'inset-inline-start var(--motion-productive-medium) var(--motion-timing-transition)',
                    }}
                />
            </span>
        </label>
    );
}
