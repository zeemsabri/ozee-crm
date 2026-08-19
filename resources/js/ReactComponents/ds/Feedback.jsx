/**
 * Feedback and overlay primitives — AttentionBox, Toast, Modal.
 * Ported from the OZee design system bundle.
 *
 * Modal additions over the bundle version, needed for a real page rather than a mock:
 * Escape closes it, body scroll is locked while it is open, and focus moves into the
 * dialog on mount. Everything visual is unchanged.
 */

import { useEffect, useRef } from 'react';
import { Icon } from './Icon';

const BOX_TONES = {
    primary: {
        bg: 'var(--primary-highlighted-color)',
        border: 'var(--primary-color)',
        icon: 'Info',
        iconColor: 'var(--primary-color)',
    },
    success: {
        bg: 'var(--positive-color-selected)',
        border: 'var(--positive-color)',
        icon: 'Check',
        iconColor: 'var(--positive-color)',
    },
    danger: {
        bg: 'var(--negative-color-selected)',
        border: 'var(--negative-color)',
        icon: 'Alert',
        iconColor: 'var(--negative-color)',
    },
    warning: {
        bg: 'var(--warning-color-selected)',
        border: 'var(--warning-color-hover)',
        icon: 'Warning',
        iconColor: 'var(--warning-color-hover)',
    },
    dark: {
        bg: 'var(--allgrey-background-color)',
        border: 'var(--layout-border-color)',
        icon: 'Info',
        iconColor: 'var(--secondary-text-color)',
    },
};

export function AttentionBox({ title, children, type = 'primary', withIcon = true, onClose, style }) {
    const tone = BOX_TONES[type] || BOX_TONES.primary;

    return (
        <div
            style={{
                display: 'flex',
                gap: 'var(--space-8)',
                padding: 'var(--space-12) var(--space-16)',
                background: tone.bg,
                border: `1px solid ${tone.border}`,
                borderRadius: 'var(--border-radius-small)',
                ...style,
            }}
        >
            {withIcon ? (
                <Icon name={tone.icon} size={18} color={tone.iconColor} style={{ marginTop: 2 }} />
            ) : null}
            <div style={{ flex: 1, minWidth: 0 }}>
                {title ? (
                    <div
                        style={{
                            font: 'var(--font-text2-bold)',
                            color: 'var(--primary-text-color)',
                            marginBottom: 2,
                        }}
                    >
                        {title}
                    </div>
                ) : null}
                <div style={{ font: 'var(--font-text2-normal)', color: 'var(--primary-text-color)' }}>
                    {children}
                </div>
            </div>
            {onClose ? (
                <button
                    type="button"
                    aria-label="Dismiss"
                    onClick={onClose}
                    style={{
                        background: 'transparent',
                        border: 'none',
                        cursor: 'pointer',
                        color: 'var(--icon-color)',
                        display: 'inline-flex',
                        height: 20,
                    }}
                >
                    <Icon name="CloseSmall" size={16} />
                </button>
            ) : null}
        </div>
    );
}

const TOAST_TONES = {
    primary: { bg: 'var(--primary-color)', fg: 'var(--fixed-light-color)', icon: 'Info' },
    positive: { bg: 'var(--positive-color)', fg: 'var(--fixed-light-color)', icon: 'Check' },
    negative: { bg: 'var(--negative-color)', fg: 'var(--fixed-light-color)', icon: 'Alert' },
    warning: { bg: 'var(--warning-color)', fg: 'var(--fixed-dark-color)', icon: 'Warning' },
    dark: { bg: 'var(--inverted-color-background)', fg: 'var(--text-color-on-inverted)', icon: 'Info' },
};

export function Toast({ children, type = 'normal', open = true, onClose, action, withIcon = true, style }) {
    const tone = TOAST_TONES[type === 'normal' ? 'primary' : type] || TOAST_TONES.primary;
    if (!open) return null;

    return (
        <div
            role="status"
            aria-live="polite"
            style={{
                display: 'flex',
                alignItems: 'center',
                minWidth: 200,
                width: 'max-content',
                maxWidth: 'min(90vw, 520px)',
                padding: 'var(--space-8)',
                margin: 'var(--space-16)',
                borderRadius: 'var(--border-radius-small)',
                background: tone.bg,
                color: tone.fg,
                boxShadow: 'var(--box-shadow-medium)',
                font: 'var(--font-text2-normal)',
                ...style,
            }}
        >
            {withIcon ? (
                <Icon name={tone.icon} size={20} style={{ marginInlineStart: 'var(--space-8)' }} />
            ) : null}
            <div style={{ margin: '0 var(--space-8)', flex: 1 }}>{children}</div>
            {action ? (
                <button
                    type="button"
                    onClick={action.onClick}
                    style={{
                        background: 'transparent',
                        border: '1px solid currentColor',
                        color: 'inherit',
                        height: 24,
                        padding: '0 var(--space-8)',
                        borderRadius: 'var(--border-radius-small)',
                        font: 'var(--font-text2-normal)',
                        cursor: 'pointer',
                    }}
                >
                    {action.text}
                </button>
            ) : null}
            {onClose ? (
                <button
                    type="button"
                    aria-label="Close"
                    onClick={onClose}
                    style={{
                        marginInlineStart: 'var(--space-8)',
                        background: 'transparent',
                        border: 'none',
                        color: 'inherit',
                        cursor: 'pointer',
                        display: 'inline-flex',
                    }}
                >
                    <Icon name="Close" size={16} />
                </button>
            ) : null}
        </div>
    );
}

const MODAL_WIDTHS = { small: 480, medium: 580, large: 840, fullView: 'calc(100% - 80px)' };

export function Modal({
    open = true,
    onClose,
    title,
    description,
    children,
    footer,
    size = 'medium',
    showCloseButton = true,
    style,
}) {
    const dialogRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const onKeyDown = (e) => {
            if (e.key === 'Escape' && onClose) onClose();
        };
        document.addEventListener('keydown', onKeyDown);

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        // `.ozds` so the dialog carries the system's resets and type even when it is
        // portalled outside the page wrapper. The class sets no background, so the
        // backdrop below stays see-through.
        <div className="ozds" style={{ position: 'fixed', inset: 0, zIndex: 10000 }}>
            <div
                onClick={onClose}
                style={{ position: 'absolute', inset: 0, background: 'var(--backdrop-color)' }}
            />
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-label={typeof title === 'string' ? title : undefined}
                tabIndex={-1}
                style={{
                    position: 'absolute',
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%)',
                    display: 'flex',
                    flexDirection: 'column',
                    width: MODAL_WIDTHS[size] || 580,
                    maxWidth: 'calc(100vw - 32px)',
                    maxHeight: size === 'small' ? 'min(90vh, 640px)' : '85vh',
                    background: 'var(--modal-background-color)',
                    borderRadius: 'var(--border-radius-big)',
                    boxShadow: 'var(--box-shadow-large)',
                    overflow: 'hidden',
                    outline: 'none',
                    animation: 'ozeeModalIn 150ms cubic-bezier(0,0,0.4,1)',
                    ...style,
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'flex-start',
                        gap: 'var(--space-8)',
                        padding: 'var(--space-24) var(--space-32) var(--space-8)',
                    }}
                >
                    <div style={{ flex: 1, minWidth: 0 }}>
                        {title ? (
                            <h2
                                style={{
                                    margin: 0,
                                    font: 'var(--font-h3-medium)',
                                    letterSpacing: 'var(--letter-spacing-h3-bold)',
                                    color: 'var(--primary-text-color)',
                                }}
                            >
                                {title}
                            </h2>
                        ) : null}
                        {description ? (
                            <p
                                style={{
                                    margin: 'var(--space-4) 0 0',
                                    font: 'var(--font-text2-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {showCloseButton ? (
                        <button
                            type="button"
                            aria-label="Close"
                            onClick={onClose}
                            style={{
                                width: 32,
                                height: 32,
                                flex: 'none',
                                border: 'none',
                                borderRadius: 'var(--border-radius-small)',
                                background: 'transparent',
                                color: 'var(--icon-color)',
                                cursor: 'pointer',
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                            }}
                        >
                            <Icon name="Close" size={20} />
                        </button>
                    ) : null}
                </div>
                <div
                    style={{
                        padding: 'var(--space-8) var(--space-32)',
                        overflow: 'auto',
                        flex: 1,
                        font: 'var(--font-text2-normal)',
                        color: 'var(--primary-text-color)',
                    }}
                >
                    {children}
                </div>
                {footer ? (
                    <div
                        style={{
                            display: 'flex',
                            justifyContent: 'flex-end',
                            gap: 'var(--space-8)',
                            padding: 'var(--space-16) var(--space-32) var(--space-24)',
                            borderTop: '1px solid var(--om-hairline)',
                        }}
                    >
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
