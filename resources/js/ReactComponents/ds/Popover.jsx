/**
 * A panel anchored to a trigger, rendered in a portal on document.body.
 *
 * Why a portal rather than `position: absolute` next to the trigger, which is what the
 * design-system bundle does: an absolutely-positioned panel is clipped by any ancestor
 * with `overflow: hidden` or `overflow: auto`, and this app has several in the exact
 * places dropdowns live — Modal's body scrolls (`Feedback.jsx`), Modal's dialog hides
 * overflow, and the reply composer hides overflow to keep its rounded corners. A template
 * picker near the bottom of a dialog was being cut off with no way to scroll to the rest
 * of the list.
 *
 * Being on document.body also means the panel is no longer inside the trigger's DOM
 * subtree, so any outside-click handler has to test BOTH elements. `useAnchoredPopover`
 * below returns the refs to make that hard to get wrong.
 *
 * Behaviour:
 *  - Flips above the trigger when there is not enough room below and more room above.
 *  - Caps its own height to the space available, so it always ends on screen.
 *  - Follows the trigger on scroll and resize (capture phase, so it also tracks scrolling
 *    inside a modal body rather than detaching from the field).
 */

import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/** Gap between the trigger and the panel, and the minimum breathing room at the viewport edge. */
const GAP = 4;
const EDGE = 8;

/** Below the modal is pointless — this has to sit above it. Modal uses 10000. */
const POPOVER_Z = 10050;

function measure(anchor, { preferredHeight, align, matchWidth, minWidth }) {
    const rect = anchor.getBoundingClientRect();
    const below = window.innerHeight - rect.bottom - GAP - EDGE;
    const above = rect.top - GAP - EDGE;

    // Only flip when below genuinely cannot hold it AND above is roomier — flipping into
    // an equally cramped space just moves the problem.
    const flip = below < Math.min(preferredHeight, 160) && above > below;
    const maxHeight = Math.max(120, Math.floor(flip ? above : below));

    // A field's list should be exactly as wide as the field; a 32px icon button's menu
    // should not be 32px wide, so it hangs off one edge at its natural width instead.
    const horizontal = matchWidth
        ? { left: Math.round(rect.left), width: Math.round(rect.width) }
        : align === 'start'
          ? { left: Math.round(rect.left), minWidth }
          : { right: Math.round(window.innerWidth - rect.right), minWidth };

    return {
        position: 'fixed',
        maxHeight,
        ...horizontal,
        ...(flip
            ? { bottom: Math.round(window.innerHeight - rect.top + GAP) }
            : { top: Math.round(rect.bottom + GAP) }),
    };
}

/**
 * State + refs for a trigger/panel pair.
 *
 * @returns {{open:boolean, setOpen:Function, anchorRef:object, panelRef:object, position:object}}
 */
export function useAnchoredPopover({
    preferredHeight = 260,
    /** 'start' | 'end' — which edge of the trigger the panel hangs off. Ignored when matchWidth. */
    align = 'start',
    /** Size the panel to the trigger's width. Right for fields, wrong for icon buttons. */
    matchWidth = false,
    minWidth,
    onClose,
} = {}) {
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState(null);
    const anchorRef = useRef(null);
    const panelRef = useRef(null);

    const reposition = useCallback(() => {
        if (anchorRef.current) {
            setPosition(measure(anchorRef.current, { preferredHeight, align, matchWidth, minWidth }));
        }
    }, [preferredHeight, align, matchWidth, minWidth]);

    // Layout effect so the panel is placed before the browser paints — measuring in a
    // plain effect shows one frame at the wrong position.
    useLayoutEffect(() => {
        if (!open) {
            setPosition(null);
            return undefined;
        }

        reposition();

        // Capture phase: scroll events from a scrolling ancestor (a modal body) do not
        // bubble to window, so without capture the panel detaches from its field.
        const onScroll = () => reposition();
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onScroll);

        return () => {
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onScroll);
        };
    }, [open, reposition]);

    // Outside click + Escape. The panel is portalled, so it is NOT inside anchorRef —
    // both have to be excluded or clicking an option closes the panel before it fires.
    useEffect(() => {
        if (!open) return undefined;

        const onDown = (e) => {
            const inAnchor = anchorRef.current?.contains(e.target);
            const inPanel = panelRef.current?.contains(e.target);
            if (!inAnchor && !inPanel) {
                setOpen(false);
                onClose?.();
            }
        };

        const onKey = (e) => {
            if (e.key === 'Escape') {
                setOpen(false);
                onClose?.();
                // Return focus to the field, or the tab order restarts from the top.
                anchorRef.current?.querySelector('[tabindex],button,input')?.focus?.();
            }
        };

        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    return { open, setOpen, anchorRef, panelRef, position };
}

/**
 * Renders `children` at `position`, on document.body.
 *
 * `.ozds` is reapplied because the panel leaves the page's own wrapper and would
 * otherwise lose the design system's type and resets.
 */
export function Popover({ position, panelRef, children, style }) {
    if (typeof document === 'undefined' || !position) return null;

    return createPortal(
        <div
            ref={panelRef}
            className="ozds"
            style={{ ...position, zIndex: POPOVER_Z, ...style }}
        >
            {children}
        </div>,
        document.body
    );
}
