/**
 * The Saved view — unfinished emails, waiting to be finished.
 *
 * A list of saved EMAILS (status 'saved'), deliberately not threads: a saved email has
 * no conversation yet — it joins one the moment its author submits it and the normal
 * compose endpoint runs. Same standalone shape as DeletedList, for the same reason.
 *
 * The rows come from the page's ONE useSavedEmails instance (via props), the same list
 * the rail badge and the composer's Drafts popover read — three surfaces, one truth.
 * Resume hands the draft to the page, which opens the composer prefilled; the two
 * buttons here are the only actions a saved email has.
 */

import { useMemo, useState } from 'react';
import { Button, EmptyState, Icon, Search } from '../ds';
import { draftAgeLabel } from './useComposeDrafts';

const ROW = {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
    padding: '12px 16px',
    borderBottom: '1px solid var(--om-hairline)',
};

export function SavedList({ saved, projects, currentUserName, onResume }) {
    const [search, setSearch] = useState('');

    const projectLabel = (id) =>
        (projects || []).find((p) => p.value === id)?.label || 'No project yet';

    const rows = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return saved.drafts;
        return saved.drafts.filter(
            (d) =>
                (d.subject || '').toLowerCase().includes(term) ||
                (d.body || '').toLowerCase().includes(term) ||
                projectLabel(d.meta?.project_id).toLowerCase().includes(term)
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [saved.drafts, search, projects]);

    return (
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0 }}>
            <div
                style={{
                    padding: '12px 16px',
                    borderBottom: '1px solid var(--layout-border-color)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 12,
                    flexWrap: 'wrap',
                }}
            >
                <div style={{ font: '600 16px/24px Figtree, sans-serif' }}>Saved</div>
                <div
                    style={{
                        font: '400 13px/20px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {saved.drafts.length
                        ? `${saved.drafts.length} unfinished email${saved.drafts.length === 1 ? '' : 's'}`
                        : ''}
                </div>
                <div style={{ marginInlineStart: 'auto', width: 260 }}>
                    <Search
                        size="small"
                        placeholder="Search saved emails"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onClear={() => setSearch('')}
                    />
                </div>
            </div>

            <div style={{ flex: 1, overflowY: 'auto' }}>
                {rows.length === 0 ? (
                    <EmptyState
                        iconName="Doc"
                        title={search ? 'Nothing matches' : 'Nothing saved'}
                        description={
                            search
                                ? 'No saved email matches that search.'
                                : 'Start an email and press Save — it waits here, and nothing goes to the AI checker or the client until you submit it.'
                        }
                    />
                ) : (
                    rows.map((d) => {
                        const mine = !d.sender?.name || d.sender?.name === currentUserName;
                        return (
                            <div key={d.id} style={ROW}>
                                <Icon name="Doc" size={18} color="var(--icon-color)" />
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 6,
                                            font: '600 14px/20px Figtree, sans-serif',
                                            color: 'var(--primary-text-color)',
                                        }}
                                    >
                                        <span
                                            style={{
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                            }}
                                        >
                                            {d.subject?.trim() || '(no subject)'}
                                        </span>
                                        {d.is_private ? (
                                            <Icon
                                                name="Hide"
                                                size={14}
                                                color="var(--secondary-text-color)"
                                                title="Private — hidden from anyone who cannot read private emails"
                                            />
                                        ) : null}
                                    </div>
                                    <div
                                        style={{
                                            font: '400 12px/17px Figtree, sans-serif',
                                            color: 'var(--secondary-text-color)',
                                            whiteSpace: 'nowrap',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                        }}
                                    >
                                        {projectLabel(d.meta?.project_id)}
                                        {mine ? '' : ` · by ${d.sender.name}`}
                                        {' · saved '}
                                        {d.updated_at ? draftAgeLabel(Date.parse(d.updated_at)) : 'recently'}
                                        {d.body?.trim() ? ` — ${d.body.trim().slice(0, 90)}` : ''}
                                    </div>
                                </div>
                                <Button size="small" onClick={() => onResume(d)}>
                                    Resume
                                </Button>
                                <Button
                                    size="small"
                                    kind="tertiary"
                                    color="negative"
                                    onClick={() => saved.remove(d.id)}
                                >
                                    Delete
                                </Button>
                            </div>
                        );
                    })
                )}
            </div>

            <div
                style={{
                    padding: '8px 16px',
                    borderTop: '1px solid var(--om-hairline)',
                    font: '400 12px/17px Figtree, sans-serif',
                    color: 'var(--secondary-text-color)',
                }}
            >
                Saved emails are visible to anyone who can compose on the project, and
                private ones follow the usual private-email rules. Attachments are not kept
                with a saved email — re-attach when you resume.
            </div>
        </div>
    );
}
