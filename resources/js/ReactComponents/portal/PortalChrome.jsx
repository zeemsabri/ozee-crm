/**
 * Header, footer and page shell for the portal.
 *
 * The nav is real links now rather than client-side view switching, so every portal
 * screen has its own URL and the browser's back button behaves. Inertia's <Link> is
 * safe between these pages because they're all React — only a hop to a legacy Vue page
 * would need a plain <a>.
 */

import { Head, Link } from '@inertiajs/react';
import { Avatar, Button, ButtonGroup } from '../ds';
import { THEME_OPTIONS, useTheme } from './useTheme';

/** Strip any scheme the branding config happens to include, then link over https. */
function websiteHref(website) {
    if (!website) return null;
    return `https://${String(website).replace(/^https?:\/\//i, '')}`;
}

function NavLink({ href, active, children }) {
    return (
        <Link href={href} style={{ textDecoration: 'none' }}>
            <Button kind="tertiary" size="small" active={active}>
                {children}
            </Button>
        </Link>
    );
}

export function PortalHeader({ branding, account, current, onSignOut }) {
    const { theme, setTheme } = useTheme();
    const company = branding?.company || {};
    const href = websiteHref(company.website);

    return (
        <header
            style={{
                minHeight: 56,
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                gap: '8px 12px',
                padding: '8px 24px',
                background: 'var(--primary-background-color)',
                borderBottom: '1px solid var(--layout-border-color)',
                position: 'sticky',
                top: 0,
                zIndex: 20,
            }}
        >
            {company.logo_url ? (
                <img src={company.logo_url} alt={company.name || ''} data-brandmark="true" style={{ height: 26 }} />
            ) : null}
            <div style={{ display: 'flex', flexDirection: 'column', lineHeight: 1 }}>
                <span style={{ font: 'var(--font-text2-medium)' }}>{company.name}</span>
                {href ? (
                    <a href={href} target="_blank" rel="noreferrer" style={{ font: 'var(--font-text3-normal)' }}>
                        {company.website}
                    </a>
                ) : null}
            </div>

            {account ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginInlineStart: 8 }}>
                    <NavLink href="/portal" active={current === 'projects'}>
                        All projects
                    </NavLink>
                    <NavLink href="/portal/profile" active={current === 'profile'}>
                        Profile
                    </NavLink>
                </div>
            ) : null}

            <div
                style={{
                    marginInlineStart: 'auto',
                    display: 'flex',
                    flexWrap: 'wrap',
                    alignItems: 'center',
                    justifyContent: 'flex-end',
                    gap: 8,
                    minWidth: 0,
                }}
            >
                {account ? (
                    <>
                        <span
                            style={{
                                font: 'var(--font-text2-normal)',
                                color: 'var(--secondary-text-color)',
                                maxWidth: 220,
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {account.email}
                        </span>
                        <Avatar text={account.name || account.email} size="medium" />
                        {onSignOut ? (
                            <Button kind="tertiary" size="small" onClick={onSignOut}>
                                Sign out
                            </Button>
                        ) : null}
                    </>
                ) : (
                    <span style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                        Not signed in
                    </span>
                )}
                <div style={{ width: 1, height: 24, background: 'var(--layout-border-color)' }} />
                <ButtonGroup options={THEME_OPTIONS} value={theme} onChange={setTheme} size="small" />
            </div>
        </header>
    );
}

export function PortalFooter({ branding, projectContact, accountEmail }) {
    const company = branding?.company || {};
    const href = websiteHref(company.website);
    const supportEmail = branding?.support_email;

    return (
        <footer
            style={{
                marginTop: 'auto',
                background: 'var(--primary-background-color)',
                borderTop: '1px solid var(--layout-border-color)',
            }}
        >
            <div
                style={{
                    maxWidth: 1240,
                    margin: '0 auto',
                    padding: 24,
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                    gap: 24,
                }}
            >
                <div>
                    {company.logo_url ? (
                        <img src={company.logo_url} alt="" data-brandmark="true" style={{ height: 24 }} />
                    ) : null}
                    <div style={{ marginTop: 8, font: 'var(--font-text2-medium)' }}>{company.name}</div>
                    {company.address ? (
                        <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            {company.address}
                        </div>
                    ) : null}
                </div>

                <div>
                    <div style={{ font: 'var(--font-text3-medium)', marginBottom: 8 }}>Get in touch</div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 4,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {company.phone ? <a href={`tel:${company.phone.replace(/\s+/g, '')}`}>{company.phone}</a> : null}
                        {href ? (
                            <a href={href} target="_blank" rel="noreferrer">
                                {company.website}
                            </a>
                        ) : null}
                        {projectContact ? <span>Project contact: {projectContact}</span> : null}
                    </div>
                </div>

                <div>
                    <div style={{ font: 'var(--font-text3-medium)', marginBottom: 8 }}>Your access</div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 4,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {accountEmail ? <span>Signed in as {accountEmail}</span> : <span>A private share link.</span>}
                        <span>Only you and the {company.name || 'project'} team can see your proposals.</span>
                    </div>
                </div>

                <div>
                    <div style={{ font: 'var(--font-text3-medium)', marginBottom: 8 }}>Need a hand?</div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 4,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {supportEmail ? <a href={`mailto:${supportEmail}`}>{supportEmail}</a> : null}
                        <span>Reply to the invite email and it reaches the project team.</span>
                    </div>
                </div>
            </div>

            <div style={{ borderTop: '1px solid var(--om-hairline)' }}>
                <div
                    style={{
                        maxWidth: 1240,
                        margin: '0 auto',
                        padding: '12px 24px',
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: '8px 16px',
                        font: 'var(--font-text3-normal)',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    <span>
                        © {new Date().getFullYear()} {company.name}
                    </span>
                    {branding?.tagline ? <span style={{ marginInlineStart: 'auto' }}>{branding.tagline}</span> : null}
                </div>
            </div>
        </footer>
    );
}

/**
 * The frame every portal page sits in: theme attributes, header, footer, and the layout
 * CSS that can't be expressed inline (media queries).
 */
export function PortalShell({ title, branding, account, current, onSignOut, projectContact, children }) {
    return (
        <>
            <Head title={title} />

            <div
                className="ozds"
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    flexDirection: 'column',
                    background: 'var(--grey-background-color)',
                    color: 'var(--primary-text-color)',
                }}
            >
                <PortalHeader branding={branding} account={account} current={current} onSignOut={onSignOut} />

                {children}

                <PortalFooter branding={branding} projectContact={projectContact} accountEmail={account?.email} />
            </div>

            <style>{`
                .ozds-portal-page {
                    max-width: 1240px;
                    margin: 0 auto;
                    padding: var(--space-24);
                    width: 100%;
                }
                .ozds-portal-split {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 404px;
                    gap: var(--space-24);
                    align-items: start;
                }
                .ozds-portal-sidebar {
                    position: sticky;
                    top: 80px;
                    display: flex;
                    flex-direction: column;
                    gap: var(--space-16);
                    min-width: 0;
                }
                .ozds-portal-profile {
                    display: grid;
                    grid-template-columns: minmax(0, 1fr) 460px;
                    gap: var(--space-24);
                    align-items: start;
                }
                .ozds-portal-cards {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                    gap: var(--space-16);
                }
                @media (max-width: 1080px) {
                    .ozds-portal-split,
                    .ozds-portal-profile {
                        grid-template-columns: minmax(0, 1fr);
                    }
                    /* Stacked, the sidebar leads — signing in is the first thing to do. */
                    .ozds-portal-sidebar {
                        position: static;
                        order: -1;
                    }
                }
                @media (max-width: 640px) {
                    .ozds-portal-page {
                        padding: var(--space-16);
                    }
                }
            `}</style>
        </>
    );
}
