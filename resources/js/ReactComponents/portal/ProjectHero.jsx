/**
 * The project brief at the top of the guest page: what the job is, when it's due,
 * how much of it is still open, and who to talk to.
 *
 * Every stat is optional — a project with no milestones, no dates or no assigned
 * manager still renders a sensible card rather than a row of dashes.
 */

import { Label } from '../ds';
import { milestoneTone, statusLabel } from './format';

function Stat({ label, value }) {
    if (value === null || value === undefined || value === '') return null;
    return (
        <div>
            <div style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>{label}</div>
            <div style={{ font: 'var(--font-text2-medium)' }}>{value}</div>
        </div>
    );
}

export function ProjectHero({ project }) {
    const stats = [
        { label: 'Target completion', value: project.target_completion },
        {
            label: 'Active phases',
            value:
                project.total_phase_count && project.total_phase_count !== project.phase_count
                    ? `${project.phase_count} of ${project.total_phase_count}`
                    : project.phase_count || null,
        },
        { label: 'Open deliverables', value: project.deliverable_count || null },
        { label: 'Contact', value: project.contact },
    ].filter((s) => s.value !== null && s.value !== undefined && s.value !== '');

    return (
        <section
            style={{
                background: 'var(--primary-background-color)',
                border: '1px solid var(--layout-border-color)',
                borderRadius: 'var(--border-radius-medium)',
                padding: 20,
                animation: 'dcRise var(--motion-expressive-long) var(--motion-timing-enter) both',
            }}
        >
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8, flexWrap: 'wrap' }}>
                <Label text={statusLabel(project.status)} color={milestoneTone(project.status)} size="small" />
                <span style={{ font: 'var(--font-text3-normal)', color: 'var(--secondary-text-color)' }}>
                    Open for proposals
                </span>
            </div>

            <h1
                style={{
                    font: 'var(--font-h1-medium)',
                    letterSpacing: 'var(--letter-spacing-h1-normal)',
                }}
            >
                {project.name}
            </h1>

            {project.description ? (
                <p
                    style={{
                        marginTop: 8,
                        maxWidth: '74ch',
                        font: '400 16px/24px var(--font-family)',
                        color: 'var(--secondary-text-color)',
                        textWrap: 'pretty',
                        whiteSpace: 'pre-line',
                    }}
                >
                    {project.description}
                </p>
            ) : null}

            {stats.length ? (
                <div
                    style={{
                        marginTop: 16,
                        paddingTop: 16,
                        borderTop: '1px solid var(--om-hairline)',
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 32,
                    }}
                >
                    {stats.map((s) => (
                        <Stat key={s.label} label={s.label} value={s.value} />
                    ))}
                </div>
            ) : null}
        </section>
    );
}
