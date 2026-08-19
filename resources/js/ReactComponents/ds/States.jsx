/**
 * AlertBanner / EmptyState / Loader / Skeleton / Divider — ported from the OZee design
 * system bundle (components/feedback/*, components/navigation/Divider.jsx).
 *
 * AlertBanner is the full-bleed strip the inbox uses for the reply-rule breach warning;
 * AttentionBox (Feedback.jsx) is the inline card variant. They are not interchangeable.
 */

import { Icon } from './Icon';

const BANNER_TONES = {
    primary: { bg: 'var(--primary-color)', fg: 'var(--fixed-light-color)' },
    positive: { bg: 'var(--positive-color)', fg: 'var(--fixed-light-color)' },
    negative: { bg: 'var(--negative-color)', fg: 'var(--fixed-light-color)' },
    warning: { bg: 'var(--warning-color)', fg: 'var(--fixed-dark-color)' },
    dark: { bg: 'var(--inverted-color-background)', fg: 'var(--text-color-on-inverted)' },
};

export function AlertBanner({ children, type = 'primary', onClose, action, style }) {
    const tone = BANNER_TONES[type] || BANNER_TONES.primary;

    return (
        <div
            role="status"
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 'var(--space-8)',
                minHeight: 40,
                padding: 'var(--space-4) var(--space-16)',
                background: tone.bg,
                color: tone.fg,
                font: 'var(--font-text2-normal)',
                ...style,
            }}
        >
            <div
                style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 'var(--space-8)',
                    textAlign: 'center',
                }}
            >
                {children}
                {action ? (
                    <button
                        type="button"
                        onClick={action.onClick}
                        style={{
                            background: 'transparent',
                            border: 'none',
                            color: 'inherit',
                            textDecoration: 'underline',
                            cursor: 'pointer',
                            font: 'var(--font-text2-medium)',
                        }}
                    >
                        {action.text}
                    </button>
                ) : null}
            </div>
            {onClose ? (
                <button
                    type="button"
                    aria-label="Close"
                    onClick={onClose}
                    style={{
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

export function EmptyState({
    title,
    description,
    action,
    illustrationSrc,
    iconName = 'Board',
    style,
}) {
    return (
        <div
            style={{
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                textAlign: 'center',
                gap: 'var(--space-8)',
                padding: 'var(--space-48) var(--space-24)',
                ...style,
            }}
        >
            {illustrationSrc ? (
                <img
                    src={illustrationSrc}
                    alt=""
                    style={{ width: 120, height: 'auto', opacity: 0.9, marginBottom: 'var(--space-8)' }}
                />
            ) : (
                <span
                    style={{
                        width: 56,
                        height: 56,
                        borderRadius: '50%',
                        background: 'var(--allgrey-background-color)',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        marginBottom: 'var(--space-8)',
                    }}
                >
                    <Icon name={iconName} size={26} color="var(--secondary-text-color)" />
                </span>
            )}
            <div style={{ font: 'var(--font-h3-medium)', color: 'var(--primary-text-color)' }}>
                {title}
            </div>
            {description ? (
                <div
                    style={{
                        font: 'var(--font-text2-normal)',
                        color: 'var(--secondary-text-color)',
                        maxWidth: 380,
                    }}
                >
                    {description}
                </div>
            ) : null}
            {action ? <div style={{ marginTop: 'var(--space-8)' }}>{action}</div> : null}
        </div>
    );
}

export function Loader({ size = 32, color = 'var(--primary-color)', ariaLabel = 'Loading', style }) {
    return (
        <span
            role="status"
            aria-label={ariaLabel}
            style={{
                display: 'inline-block',
                width: size,
                height: size,
                border: `${Math.max(2, size / 12)}px solid var(--ui-background-color)`,
                borderTopColor: color,
                borderRadius: '50%',
                animation: 'ozeeSpin 800ms linear infinite',
                ...style,
            }}
        />
    );
}

export function Skeleton({ type = 'rectangle', width, height, fullWidth = false, style }) {
    const dims =
        type === 'circle'
            ? { width: width || 40, height: height || 40, borderRadius: '50%' }
            : type === 'text'
              ? {
                    width: fullWidth ? '100%' : width || 162,
                    height: height || 16,
                    borderRadius: 'var(--border-radius-small)',
                }
              : {
                    width: fullWidth ? '100%' : width || 40,
                    height: height || 40,
                    borderRadius: 'var(--border-radius-small)',
                };

    return (
        <span
            aria-hidden="true"
            style={{
                display: 'block',
                background: 'var(--ui-background-color)',
                animation: 'ozeeShine 0.8s steps(10,end) infinite alternate',
                ...dims,
                ...style,
            }}
        />
    );
}

export function Divider({ direction = 'horizontal', style }) {
    return direction === 'vertical' ? (
        <span
            style={{ width: 1, alignSelf: 'stretch', background: 'var(--ui-border-color)', ...style }}
        />
    ) : (
        <span
            style={{ display: 'block', height: 1, width: '100%', background: 'var(--ui-border-color)', ...style }}
        />
    );
}
