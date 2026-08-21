/**
 * The phone chrome: header, breach bar, bottom tab bar, account sheet, toasts.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the <header>, the breach
 * button, the <nav> tab bar and the `profileOpen` menu.
 *
 * This is the counterpart to app/AppShell.jsx and deliberately not a variant of it. The
 * desktop shell's whole structure is a 64px icon rail plus a 56px header bar; the phone's
 * is a tab bar with a raised compose button and screens that push over each other. Wiring
 * one component to render both would leave every prop conditional on the other's layout.
 *
 * What it does NOT own: any inbox rule. Which tabs exist maps to views in MobileInbox;
 * whether Compose is offered comes from the page controller's permission gates.
 */

import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

import { Avatar, Icon, Search, Toast } from '../../ds';
import { useTheme, THEME_OPTIONS } from '../../app/useTheme';
import { usePermissions } from '../../app/usePermissions';
import { RAIL_ITEMS, url } from '../../app/navigation';
import { Sheet } from './Sheet';

const TAB_ICON_SIZE = 22;

function TabButton({ label, icon, active, count, countTone, onClick }) {
    return (
        <button
            type="button"
            aria-current={active ? 'page' : undefined}
            onClick={onClick}
            style={{
                position: 'relative',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: 3,
                padding: '4px 0',
                border: 'none',
                background: 'transparent',
                cursor: 'pointer',
                color: active ? 'var(--primary-color)' : 'var(--secondary-text-color)',
            }}
        >
            <Icon name={icon} size={TAB_ICON_SIZE} color="currentColor" />
            <span
                style={{
                    font: `${active ? 600 : 400} 11px/14px Figtree, sans-serif`,
                    color: 'inherit',
                }}
            >
                {label}
            </span>
            {count > 0 ? (
                <span
                    aria-hidden="true"
                    style={{
                        position: 'absolute',
                        top: 0,
                        left: '50%',
                        marginInlineStart: 8,
                        minWidth: 16,
                        height: 16,
                        padding: '0 4px',
                        borderRadius: 8,
                        background: countTone === 'negative' ? 'var(--negative-color)' : 'var(--primary-color)',
                        color: '#fff',
                        font: '700 10px/16px Figtree, sans-serif',
                        textAlign: 'center',
                        pointerEvents: 'none',
                    }}
                >
                    {count > 99 ? '99+' : count}
                </span>
            ) : null}
        </button>
    );
}

function AccountSheet({ open, onClose, user, classicUrl, can }) {
    const { theme, setTheme } = useTheme();

    const signOut = () => {
        router.post(url('logout', '/logout'), {}, {
            onFinish: () => {
                ['authToken', 'userRole', 'userId', 'userEmail', 'remembered'].forEach((k) => {
                    try {
                        localStorage.removeItem(k);
                    } catch {
                        /* ignore */
                    }
                });
            },
        });
    };

    const row = {
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        width: '100%',
        boxSizing: 'border-box',
        padding: '13px 16px',
        border: 'none',
        borderBottom: '1px solid var(--om-hairline-soft)',
        background: 'transparent',
        cursor: 'pointer',
        textAlign: 'start',
        font: '400 14px/20px Figtree, sans-serif',
        color: 'var(--primary-text-color)',
        textDecoration: 'none',
    };

    return (
        <Sheet open={open} onClose={onClose} title="Account" maxHeight="88%">
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    padding: '14px 16px',
                    borderBottom: '1px solid var(--om-hairline)',
                }}
            >
                <Avatar text={user?.name || user?.email || '?'} size="medium" />
                <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ font: '600 14px/20px Figtree, sans-serif' }}>{user?.name || 'Signed in'}</div>
                    <div
                        style={{
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                            whiteSpace: 'nowrap',
                        }}
                    >
                        {user?.email}
                    </div>
                </div>
            </div>

            {/*
              The rail, as a list.
              Same RAIL_ITEMS the desktop shell reads, same permission gate — the Vue app
              kept two hand-written nav lists that had already drifted, and repeating that
              here would be repeating it knowingly. Plain anchors: every one of these is a
              Vue page, so it needs a full reload for app.js to boot the other framework.
            */}
            {RAIL_ITEMS.filter((item) => !item.permission || can(item.permission)).map((item) => (
                <a key={item.key} href={url(item.route, item.href)} style={row}>
                    <Icon name={item.icon} size={18} color="var(--icon-color)" />
                    <span style={{ flex: 1 }}>{item.label}</span>
                    <Icon name="NavigationChevronRight" size={16} color="var(--icon-color)" />
                </a>
            ))}

            {classicUrl ? (
                <a href={classicUrl} style={row}>
                    <Icon name="Email" size={18} color="var(--icon-color)" />
                    <span style={{ flex: 1 }}>Classic inbox</span>
                    <Icon name="NavigationChevronRight" size={16} color="var(--icon-color)" />
                </a>
            ) : null}

            <div style={{ padding: '14px 16px', borderBottom: '1px solid var(--om-hairline-soft)' }}>
                <div
                    style={{
                        font: '700 10px/14px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                        textTransform: 'uppercase',
                        letterSpacing: '.4px',
                        marginBottom: 8,
                    }}
                >
                    Appearance
                </div>
                <div
                    style={{
                        display: 'flex',
                        gap: 4,
                        padding: 3,
                        borderRadius: 6,
                        background: 'var(--allgrey-background-color)',
                    }}
                >
                    {THEME_OPTIONS.map((option) => {
                        const on = theme === option.value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={on}
                                onClick={() => setTheme(option.value)}
                                style={{
                                    flex: 1,
                                    height: 34,
                                    border: 'none',
                                    borderRadius: 4,
                                    background: on ? 'var(--primary-background-color)' : 'transparent',
                                    color: on ? 'var(--primary-color)' : 'var(--secondary-text-color)',
                                    font: '600 12px/16px Figtree, sans-serif',
                                    cursor: 'pointer',
                                }}
                            >
                                {option.text}
                            </button>
                        );
                    })}
                </div>
            </div>

            <button type="button" onClick={signOut} style={{ ...row, color: 'var(--negative-color)' }}>
                <Icon name="Item" size={18} color="currentColor" />
                <span style={{ flex: 1 }}>Log out</span>
            </button>

            <div style={{ height: 'calc(12px + var(--om-safe-bottom, 0px))' }} />
        </Sheet>
    );
}

export function MobileShell({
    title,
    search,
    onOpenFilters,
    filterCount = 0,
    activeFilters = [],
    breach,
    tabs = [],
    canCompose,
    onCompose,
    showChrome = true,
    classicUrl,
    toasts = [],
    onDismissToast,
    children,
}) {
    const page = usePage();
    const user = page?.props?.auth?.user || null;
    const { can } = usePermissions();
    const [accountOpen, setAccountOpen] = useState(false);

    // The shell owns the theme attribute exactly as AppShell does — the phone layout is
    // never inside AppShell, so without this call a redesigned page would render with the
    // tokens' light values regardless of the setting.
    useTheme();

    return (
        <div
            className="ozds om-app"
            style={{
                height: '100dvh',
                display: 'flex',
                flexDirection: 'column',
                overflow: 'hidden',
                fontFamily: 'Figtree, sans-serif',
                color: 'var(--primary-text-color)',
                background: 'var(--grey-background-color)',
            }}
        >
            {title ? <Head title={title} /> : null}

            {showChrome ? (
                <header
                    style={{
                        flex: 'none',
                        background: 'var(--primary-background-color)',
                        borderBottom: '1px solid var(--layout-border-color)',
                        padding: 'calc(10px + var(--om-safe-top, 0px)) 12px 0',
                    }}
                >
                    <>
                        <div style={{ height: 44, display: 'flex', alignItems: 'center', gap: 10 }}>
                            <img
                                src="/ozee-ds/ozee-logo-sm.png"
                                alt="OZee Web &amp; Digital"
                                data-brandmark="true"
                                style={{ height: 22, width: 'auto', display: 'block' }}
                            />
                            <span
                                style={{
                                    flex: 1,
                                    minWidth: 0,
                                    font: '700 18px/24px Poppins, sans-serif',
                                    letterSpacing: '-.1px',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {title}
                            </span>
                            <button
                                type="button"
                                aria-label={user?.name ? `Account menu for ${user.name}` : 'Account menu'}
                                onClick={() => setAccountOpen(true)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    height: 32,
                                    padding: '0 8px 0 4px',
                                    border: '1px solid var(--ui-border-color)',
                                    borderRadius: 16,
                                    background: 'var(--primary-background-color)',
                                    cursor: 'pointer',
                                }}
                            >
                                <Avatar text={user?.name || user?.email || '?'} size="small" />
                                <Icon name="NavigationChevronDown" size={14} color="var(--icon-color)" />
                            </button>
                        </div>

                        {search ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '2px 0 10px' }}>
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <Search
                                        size="small"
                                        placeholder={search.placeholder || 'Search'}
                                        value={search.value}
                                        onChange={search.onChange}
                                        onClear={search.onClear}
                                    />
                                </div>
                                <button
                                    type="button"
                                    aria-label={
                                        filterCount ? `Filters — ${filterCount} active` : 'Filters'
                                    }
                                    onClick={onOpenFilters}
                                    style={{
                                        position: 'relative',
                                        flex: 'none',
                                        width: 36,
                                        height: 36,
                                        borderRadius: 4,
                                        border: '1px solid var(--ui-border-color)',
                                        background: 'var(--primary-background-color)',
                                        color: 'var(--icon-color)',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        cursor: 'pointer',
                                    }}
                                >
                                    <Icon name="Filter" size={18} color="currentColor" />
                                    {filterCount > 0 ? (
                                        <span
                                            style={{
                                                position: 'absolute',
                                                top: -4,
                                                right: -4,
                                                minWidth: 16,
                                                height: 16,
                                                padding: '0 4px',
                                                borderRadius: 8,
                                                background: 'var(--primary-color)',
                                                color: '#fff',
                                                font: '700 10px/16px Figtree, sans-serif',
                                                textAlign: 'center',
                                            }}
                                        >
                                            {filterCount}
                                        </span>
                                    ) : null}
                                </button>
                            </div>
                        ) : null}

                        {activeFilters.length ? (
                            <div
                                className="om-scroll"
                                style={{ display: 'flex', gap: 6, overflowX: 'auto', padding: '0 0 10px' }}
                            >
                                {activeFilters.map((f) => (
                                    <button
                                        key={f.key}
                                        type="button"
                                        onClick={f.onRemove}
                                        style={{
                                            flex: 'none',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 4,
                                            height: 26,
                                            padding: '0 8px',
                                            border: 'none',
                                            borderRadius: 13,
                                            background: 'var(--primary-selected-color)',
                                            color: 'var(--primary-color)',
                                            font: '600 12px/16px Figtree, sans-serif',
                                            cursor: 'pointer',
                                        }}
                                    >
                                        {f.label}
                                        <Icon name="CloseSmall" size={12} color="currentColor" />
                                    </button>
                                ))}
                            </div>
                        ) : null}
                    </>
                </header>
            ) : null}

            {breach ? (
                <button
                    type="button"
                    onClick={breach.onClick}
                    style={{
                        flex: 'none',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        width: '100%',
                        padding: '10px 14px',
                        border: 'none',
                        borderBottom: '1px solid var(--negative-color)',
                        background: 'var(--negative-color-selected)',
                        color: 'var(--negative-color)',
                        font: '600 13px/18px Figtree, sans-serif',
                        textAlign: 'start',
                        cursor: 'pointer',
                        animation: 'mFade 150ms both',
                    }}
                >
                    <Icon name="Warning" size={16} color="currentColor" />
                    <span style={{ flex: 1, minWidth: 0 }}>{breach.text}</span>
                    <Icon name="NavigationChevronRight" size={16} color="currentColor" />
                </button>
            ) : null}

            <div style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column' }}>{children}</div>

            {showChrome && tabs.length ? (
                <nav
                    aria-label="Primary"
                    style={{
                        flex: 'none',
                        display: 'grid',
                        // Two tabs, the raised compose button, two tabs — the mock's shape.
                        gridTemplateColumns: '1fr 1fr 76px 1fr 1fr',
                        alignItems: 'center',
                        background: 'var(--primary-background-color)',
                        borderTop: '1px solid var(--layout-border-color)',
                        padding: '6px 4px',
                        paddingBottom: 'calc(10px + var(--om-safe-bottom, 0px))',
                    }}
                >
                    {tabs.slice(0, 2).map((tab) => (
                        <TabButton key={tab.key} {...tab} />
                    ))}

                    {/*
                      Hidden outright when this person cannot compose at all, rather than
                      opening a dialog whose only content is a permission error — the same
                      call the desktop list makes with its New email button. The grid keeps
                      its 76px column either way so the four tabs do not shuffle.
                    */}
                    {canCompose ? (
                        <button
                            type="button"
                            aria-label="New email"
                            onClick={onCompose}
                            style={{
                                justifySelf: 'center',
                                width: 56,
                                height: 56,
                                marginTop: -22,
                                border: '3px solid var(--primary-background-color)',
                                borderRadius: '50%',
                                background: 'var(--primary-color)',
                                color: 'var(--text-color-on-primary)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                cursor: 'pointer',
                                boxShadow: 'var(--box-shadow-medium)',
                            }}
                        >
                            <Icon name="Edit" size={22} color="currentColor" />
                        </button>
                    ) : (
                        <span />
                    )}

                    {tabs.slice(2).map((tab) =>
                        tab.href ? (
                            // A plain anchor, because it leaves React entirely.
                            <a
                                key={tab.key}
                                href={tab.href}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: 3,
                                    padding: '4px 0',
                                    textDecoration: 'none',
                                    color: 'var(--secondary-text-color)',
                                }}
                            >
                                <Icon name={tab.icon} size={TAB_ICON_SIZE} color="currentColor" />
                                <span style={{ font: '400 11px/14px Figtree, sans-serif', color: 'inherit' }}>
                                    {tab.label}
                                </span>
                            </a>
                        ) : (
                            <TabButton key={tab.key} {...tab} />
                        )
                    )}
                </nav>
            ) : null}

            <AccountSheet
                open={accountOpen}
                onClose={() => setAccountOpen(false)}
                user={user}
                classicUrl={classicUrl}
                can={can}
            />

            {toasts.length ? (
                <div
                    style={{
                        position: 'fixed',
                        left: 8,
                        right: 8,
                        bottom: 'calc(78px + var(--om-safe-bottom, 0px))',
                        zIndex: 10001,
                        display: 'flex',
                        flexDirection: 'column',
                        alignItems: 'stretch',
                        pointerEvents: 'none',
                    }}
                >
                    {toasts.map((t) => (
                        <Toast
                            key={t.id}
                            open
                            type={t.type}
                            withIcon
                            style={{ width: 'auto', maxWidth: 'none', margin: '4px 0', pointerEvents: 'auto' }}
                            onClose={() => onDismissToast && onDismissToast(t.id)}
                        >
                            {t.message}
                        </Toast>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
