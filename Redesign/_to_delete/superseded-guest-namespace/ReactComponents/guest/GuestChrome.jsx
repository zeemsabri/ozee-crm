/**
 * Header and footer for the public guest pages.
 *
 * Both are driven entirely by config('branding') passed through from
 * PublicProjectController, so changing the company details there updates the page.
 */

import { Avatar, Button, ButtonGroup } from '../ds';
import { THEME_OPTIONS } from './useTheme';

/** Strip any scheme the branding config happens to include, then link over https. */
function websiteHref(website) {
    if (!website) return null;
    return `https://${String(website).replace(/^https?:\/\//i, '')}`;
}

export function GuestHeader({ branding, user, isSignedIn, theme, onThemeChange, view, onViewChange }) {
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

            {isSignedIn ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginInlineStart: 8 }}>
                    <Button
                        kind="tertiary"
                        size="small"
                        active={view === 'project'}
                        onClick={() => onViewChange('project')}
                    >
                        Project
                    </Button>
                    <Button
                        kind="tertiary"
                        size="small"
                        active={view === 'profile'}
                        onClick={() => onViewChange('profile')}
                    >
                        Profile
                    </Button>
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
                {isSignedIn ? (
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
                            {user.email}
                        </span>
                        <Avatar text={user.name || user.email} size="medium" />
                    </>
                ) : (
                    <span style={{ font: 'var(--font-text2-normal)', color: 'var(--secondary-text-color)' }}>
                        Guest — not signed in
                    </span>
                )}
                <div style={{ width: 1, height: 24, background: 'var(--layout-border-color)' }} />
                <ButtonGroup options={THEME_OPTIONS} value={theme} onChange={onThemeChange} size="small" />
            </div>
        </header>
    );
}

export function GuestFooter({ branding, projectContact, guestEmail }) {
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
                    <div style={{ font: 'var(--font-text3-medium)', marginBottom: 8 }}>This link</div>
                    <div
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 4,
                            font: 'var(--font-text3-normal)',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {guestEmail ? <span>Shared with {guestEmail}</span> : <span>A private share link.</span>}
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
                    {branding?.tagline ? (
                        <span style={{ marginInlineStart: 'auto' }}>{branding.tagline}</span>
                    ) : null}
                </div>
            </div>
        </footer>
    );
}
