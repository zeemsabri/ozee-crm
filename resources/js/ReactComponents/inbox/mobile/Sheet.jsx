/**
 * The two overlay shapes the phone layout uses: a bottom sheet and a pushed screen.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the `filtersOpen` and
 * `previewOpen` blocks are sheets, `projectPickerOpen` is a pushed screen.
 *
 * Both are PORTALLED to document.body, and that is not a style choice. The screens behind
 * them animate in with `mPush`, which sets a transform — and a transformed ancestor becomes
 * the containing block for `position: fixed`, so a sheet rendered inside the thread screen
 * would be positioned against the thread screen rather than the viewport and sit slightly
 * wrong, or clip, depending on scroll. The mock never hits this because its whole app is
 * one non-scrolling 430px box.
 *
 * `.ozds` is reapplied on the portal root for the same reason Popover does it: outside the
 * page wrapper, the design system's type and resets no longer apply. `.om-app` carries the
 * safe-area custom properties and the tap-highlight reset.
 */

import { useEffect } from 'react';
import { createPortal } from 'react-dom';

import { Icon } from '../../ds';

/** Under Modal (10000) and Popover (10050), so a dropdown opened inside a sheet is on top. */
const SHEET_Z = 9000;

/** Escape closes, and the page behind stops scrolling while anything is over it. */
function useOverlayBehaviour(open, onClose) {
    useEffect(() => {
        if (!open) return undefined;

        const onKey = (e) => {
            if (e.key === 'Escape') onClose?.();
        };
        document.addEventListener('keydown', onKey);

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = previous;
        };
    }, [open, onClose]);
}

/**
 * A sheet that rises from the bottom edge.
 *
 * `height` is a cap, not a size — a sheet with three rows in it should be three rows tall.
 * The filter sheet passes 84% because its content is genuinely long; the preview passes a
 * fixed 80% because a preview that resizes as you scroll it is unpleasant.
 */
export function Sheet({ open, onClose, title, actions, footer, children, height, maxHeight = '84%' }) {
    useOverlayBehaviour(open, onClose);

    if (!open || typeof document === 'undefined') return null;

    return createPortal(
        <div className="ozds om-app" style={{ position: 'fixed', inset: 0, zIndex: SHEET_Z }}>
            <div
                onClick={onClose}
                style={{
                    position: 'absolute',
                    inset: 0,
                    background: 'var(--backdrop-color)',
                    animation: 'mFade 100ms both',
                }}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-label={typeof title === 'string' ? title : undefined}
                style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    bottom: 0,
                    height,
                    maxHeight,
                    display: 'flex',
                    flexDirection: 'column',
                    background: 'var(--primary-background-color)',
                    borderRadius: '16px 16px 0 0',
                    boxShadow: 'var(--box-shadow-large)',
                    animation: 'mSheet 150ms cubic-bezier(0,0,.35,1) both',
                }}
            >
                {/* The grab handle. Decorative — the sheet is not drag-dismissible, because
                    a half-completed filter set is not something to lose to a stray flick. */}
                <span
                    aria-hidden="true"
                    style={{
                        position: 'absolute',
                        left: '50%',
                        top: 6,
                        marginInlineStart: -18,
                        width: 36,
                        height: 4,
                        borderRadius: 2,
                        background: 'var(--layout-border-color)',
                    }}
                />

                {title ? (
                    <div
                        style={{
                            flex: 'none',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 8,
                            padding: '14px 16px 10px',
                            borderBottom: '1px solid var(--om-hairline)',
                        }}
                    >
                        <span
                            style={{
                                flex: 1,
                                minWidth: 0,
                                font: '700 16px/22px Poppins, sans-serif',
                                color: 'var(--primary-text-color)',
                            }}
                        >
                            {title}
                        </span>
                        {actions}
                        <button
                            type="button"
                            aria-label="Close"
                            onClick={onClose}
                            style={{
                                width: 32,
                                height: 32,
                                flex: 'none',
                                border: 'none',
                                background: 'transparent',
                                color: 'var(--icon-color)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                cursor: 'pointer',
                            }}
                        >
                            <Icon name="Close" size={18} color="currentColor" />
                        </button>
                    </div>
                ) : null}

                <div className="om-scroll" style={{ flex: 1, minHeight: 0, overflowY: 'auto' }}>
                    {children}
                </div>

                {footer ? (
                    <div
                        style={{
                            flex: 'none',
                            padding: '12px 16px',
                            paddingBottom: 'calc(12px + var(--om-safe-bottom, 0px))',
                            borderTop: '1px solid var(--om-hairline)',
                        }}
                    >
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>,
        document.body
    );
}

/**
 * A full-screen panel that slides in from the trailing edge — the phone equivalent of
 * navigating somewhere, used for the project picker and the composer.
 *
 * It covers the tab bar deliberately: while you are writing an email, switching to Sent is
 * not a thing you want one thumb-width from the keyboard.
 */
export function PushScreen({ open, onClose, header, footer, children, padded = false }) {
    useOverlayBehaviour(open, onClose);

    if (!open || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className="ozds om-app"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: SHEET_Z,
                display: 'flex',
                flexDirection: 'column',
                background: 'var(--grey-background-color)',
                animation: 'mPush 150ms cubic-bezier(0,0,.35,1) both',
                paddingTop: 'var(--om-safe-top, 0px)',
            }}
        >
            {header ? (
                <div
                    style={{
                        flex: 'none',
                        background: 'var(--primary-background-color)',
                        borderBottom: '1px solid var(--layout-border-color)',
                    }}
                >
                    {header}
                </div>
            ) : null}

            <div
                className="om-scroll"
                style={{
                    flex: 1,
                    minHeight: 0,
                    overflowY: 'auto',
                    padding: padded ? 12 : 0,
                }}
            >
                {children}
            </div>

            {footer ? (
                <div
                    style={{
                        flex: 'none',
                        background: 'var(--primary-background-color)',
                        borderTop: '1px solid var(--layout-border-color)',
                        padding: '10px 12px',
                        paddingBottom: 'calc(10px + var(--om-safe-bottom, 0px))',
                    }}
                >
                    {footer}
                </div>
            ) : null}
        </div>,
        document.body
    );
}

/**
 * The back/close row every pushed screen puts in its header.
 * `subtitle` is the one-line context the mock keeps under the title — "To Dr Alan Reid ·
 * Northshore Dental" — which is the only thing telling you which thread you are answering
 * once the timeline is off screen.
 */
export function PushHeader({ onBack, backIcon = 'NavigationChevronLeft', title, subtitle, actions }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 10px' }}>
            <button
                type="button"
                aria-label="Back"
                onClick={onBack}
                style={{
                    width: 36,
                    height: 36,
                    flex: 'none',
                    border: 'none',
                    borderRadius: 4,
                    background: 'transparent',
                    color: 'var(--icon-color)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    cursor: 'pointer',
                }}
            >
                <Icon name={backIcon} size={20} color="currentColor" />
            </button>
            <div style={{ flex: 1, minWidth: 0 }}>
                <div
                    style={{
                        font: '600 14px/18px Figtree, sans-serif',
                        color: 'var(--primary-text-color)',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}
                >
                    {title}
                </div>
                {subtitle ? (
                    <div
                        style={{
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {subtitle}
                    </div>
                ) : null}
            </div>
            {actions}
        </div>
    );
}
