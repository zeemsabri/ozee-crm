/**
 * "Projects shared with you" — the portal's home.
 *
 * Rendered by App\Http\Controllers\Portal\PortalController::index at /portal.
 * The list comes from PortalAccessService, which treats team membership, an emailed
 * share invite, a past sign-in and an existing proposal as equally valid ways of having
 * access — so a supplier and a team member both land somewhere useful.
 *
 * Design: Redesign/proposal/Guest Project Proposals.dc.html, "Projects shared with you".
 */

import { Link } from '@inertiajs/react';
import '../../../css/ozee-ds/index.css';

import { Button, Counter, Label } from '../../ReactComponents/ds';
import { PortalShell } from '../../ReactComponents/portal/PortalChrome';
import { usePortalActions } from '../../ReactComponents/portal/usePortal';
import { milestoneTone, plural, statusLabel } from '../../ReactComponents/portal/format';

function ProjectCard({ project }) {
    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                padding: 16,
                display: 'flex',
                flexDirection: 'column',
                gap: 12,
                animation: 'dcRise var(--motion-expressive-long) var(--motion-timing-enter) both',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                    {project.client ? (
                        <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                            {project.client}
                        </div>
                    ) : null}
                    <h2
                        style={{
                            margin: '2px 0 0',
                            font: 'var(--font-h3-medium)',
                            letterSpacing: 'var(--letter-spacing-h3-normal)',
                        }}
                    >
                        {project.name}
                    </h2>
                </div>
                <Label text={statusLabel(project.status)} color={milestoneTone(project.status)} size="small" />
            </div>

            {project.summary ? (
                <p
                    style={{
                        font: 'var(--font-text2-normal)',
                        color: 'var(--secondary-text-color)',
                        textWrap: 'pretty',
                    }}
                >
                    {project.summary}
                </p>
            ) : null}

            <div
                style={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: '8px 20px',
                    font: 'var(--font-text3-normal)',
                    color: 'var(--secondary-text-color)',
                }}
            >
                <span>{plural(project.phase_count, 'active phase')}</span>
                <span>
                    {project.proposal_count
                        ? `${plural(project.proposal_count, 'proposal')} from you`
                        : 'No proposals yet'}
                </span>
                {project.target ? <span>Target {project.target}</span> : null}
            </div>

            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 8,
                    flexWrap: 'wrap',
                    marginTop: 'auto',
                    paddingTop: 8,
                    borderTop: '1px solid var(--om-hairline)',
                }}
            >
                <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    {project.activity}
                </span>
                <div style={{ marginInlineStart: 'auto' }}>
                    <Link href={`/portal/projects/${project.id}`} style={{ textDecoration: 'none' }}>
                        <Button size="small">Open project</Button>
                    </Link>
                </div>
            </div>
        </section>
    );
}

export default function Projects({ projects, account, branding }) {
    const portal = usePortalActions({ account, projectId: null });

    return (
        <PortalShell
            title="Projects shared with you"
            branding={branding}
            account={portal.profile}
            current="projects"
            onSignOut={portal.signOut}
        >
            <div className="ozds-portal-page">
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4, flexWrap: 'wrap' }}>
                    <h1 style={{ font: 'var(--font-h1-medium)', letterSpacing: 'var(--letter-spacing-h1-normal)' }}>
                        Projects shared with you
                    </h1>
                    <Counter count={projects.length} kind="line" color="dark" size="small" />
                </div>
                <p
                    style={{
                        margin: '0 0 20px',
                        font: '400 16px/24px var(--font-family)',
                        color: 'var(--secondary-text-color)',
                        maxWidth: '70ch',
                    }}
                >
                    Open a project to read the brief and quote for its phases. Your proposals stay with each project.
                </p>

                {projects.length ? (
                    <div className="ozds-portal-cards">
                        {projects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                ) : (
                    <section
                        style={{
                            background: 'var(--primary-background-color)',
                            border: '1px solid var(--layout-border-color)',
                            borderRadius: 'var(--border-radius-medium)',
                            padding: 24,
                            textAlign: 'center',
                        }}
                    >
                        <div style={{ font: 'var(--font-text1-medium)' }}>Nothing shared with you yet</div>
                        <div
                            style={{
                                marginTop: 4,
                                font: 'var(--font-text2-normal)',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            When someone sends you a project link it will appear here.
                        </div>
                    </section>
                )}
            </div>
        </PortalShell>
    );
}
