/**
 * DialogContentContainer / Menu / MenuButton — ported from the OZee design system
 * bundle (components/overlays/DialogContentContainer.jsx, components/navigation/Menu.jsx).
 *
 * Menu items are plain objects: { value, label, icon?, shortcut?, disabled?, destructive? }
 * plus two structural forms — { divider: true } and { title: 'Section' }.
 *
 * Addition over the bundle: MenuButton closes on Escape as well as outside-click, and
 * aligns its popover to the start edge when `align="start"` so it can hang off a
 * left-aligned trigger without running off-screen.
 */

import { Icon } from './Icon';
import { Popover, useAnchoredPopover } from './Popover';

export function DialogContentContainer({ children, size = 'medium', style }) {
    return (
        <div
            style={{
                background: 'var(--dialog-background-color)',
                borderRadius:
                    size === 'large' ? 'var(--border-radius-medium)' : 'var(--border-radius-small)',
                boxShadow: 'var(--box-shadow-medium)',
                padding: 'var(--space-8)',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

export function Menu({ items = [], onSelect, style }) {
    return (
        <DialogContentContainer style={{ minWidth: 200, ...style }}>
            {items.map((it, i) =>
                it.divider ? (
                    <span
                        key={`d${i}`}
                        style={{
                            display: 'block',
                            height: 1,
                            background: 'var(--ui-border-color)',
                            margin: 'var(--space-4) 0',
                        }}
                    />
                ) : it.title ? (
                    <div
                        key={`t${i}`}
                        style={{
                            padding: 'var(--space-8) var(--space-8) var(--space-4)',
                            font: 'var(--font-text3-medium)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {it.title}
                    </div>
                ) : (
                    <button
                        key={it.value || it.label}
                        type="button"
                        disabled={it.disabled}
                        onClick={() => onSelect && onSelect(it.value || it.label, it)}
                        onMouseEnter={(e) => {
                            if (!it.disabled)
                                e.currentTarget.style.background = 'var(--primary-background-hover-color)';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.background = 'transparent';
                        }}
                        style={{
                            width: '100%',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 'var(--space-8)',
                            minHeight: 32,
                            padding: '0 var(--space-8)',
                            border: 'none',
                            borderRadius: 'var(--border-radius-small)',
                            background: 'transparent',
                            cursor: it.disabled ? 'not-allowed' : 'pointer',
                            font: 'var(--font-text2-normal)',
                            color: it.disabled
                                ? 'var(--disabled-text-color)'
                                : it.destructive
                                  ? 'var(--negative-color)'
                                  : 'var(--primary-text-color)',
                            textAlign: 'start',
                        }}
                    >
                        {it.icon ? <Icon name={it.icon} size={16} color="currentColor" /> : null}
                        <span style={{ flex: 1 }}>{it.label}</span>
                        {it.shortcut ? (
                            <span
                                style={{
                                    font: 'var(--font-text3-normal)',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                {it.shortcut}
                            </span>
                        ) : null}
                    </button>
                )
            )}
        </DialogContentContainer>
    );
}

const MB_BOX = { xs: 24, small: 32, medium: 40 };

export function MenuButton({
    items = [],
    onSelect,
    ariaLabel = 'More actions',
    iconName = 'MoreActions',
    size = 'small',
    align = 'end',
    children,
    style,
    menuStyle,
}) {
    // Portalled for the same reason as Dropdown — see Popover.jsx. Not currently clipped
    // anywhere, but a menu that silently loses its bottom half the first time someone puts
    // this inside a modal is not a failure worth waiting for.
    const { open, setOpen, anchorRef, panelRef, position } = useAnchoredPopover({
        preferredHeight: 240,
        align,
        minWidth: 200,
    });

    const box = MB_BOX[size] || 32;

    return (
        <span ref={anchorRef} style={{ position: 'relative', display: 'inline-flex', ...style }}>
            <button
                type="button"
                aria-label={ariaLabel}
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                style={{
                    width: children ? undefined : box,
                    height: box,
                    padding: children ? '0 var(--space-12)' : 0,
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: 'var(--space-4)',
                    border: 'none',
                    borderRadius: 'var(--border-radius-small)',
                    background: open ? 'var(--primary-background-hover-color)' : 'transparent',
                    color: 'var(--icon-color)',
                    cursor: 'pointer',
                    font: 'var(--font-text2-normal)',
                }}
            >
                {children}
                <Icon name={iconName} size={16} />
            </button>
            {open ? (
                <Popover position={position} panelRef={panelRef}>
                    <div role="menu" style={{ maxHeight: position?.maxHeight, overflow: 'auto' }}>
                        <Menu
                            items={items}
                            style={menuStyle}
                            onSelect={(v, it) => {
                                setOpen(false);
                                onSelect && onSelect(v, it);
                            }}
                        />
                    </div>
                </Popover>
            ) : null}
        </span>
    );
}
