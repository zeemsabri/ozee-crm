/**
 * The 240px filter rail: views, project, categories and refinements.
 *
 * Design source: Inbox.dc.html, the <aside> inside the sc-if="sidebarOpen" block.
 *
 * Counts come from the server and already respect the project/category/search
 * refinement, so a badge always matches what clicking it will show. The one exception is
 * the view itself, which the count is obviously not filtered by.
 */

import { Button, Checkbox, Chips, Counter, Dropdown, Icon, TextField } from '../ds';
import { VIEW_DEFS, categoryColour } from './format';

function SectionLabel({ children }) {
    return (
        <div
            style={{
                font: '600 12px/16px Figtree, sans-serif',
                color: 'var(--secondary-text-color)',
                textTransform: 'uppercase',
                letterSpacing: '.4px',
            }}
        >
            {children}
        </div>
    );
}

export function FilterRail({
    filters,
    counts,
    overdueCount,
    options,
    hasFilters,
    onView,
    onChange,
    onClear,
}) {
    const selected = new Set(filters.category_ids.map(Number));

    const toggleCategory = (id) => {
        const next = new Set(selected);
        next.has(id) ? next.delete(id) : next.add(id);
        onChange({ category_ids: [...next] });
    };

    return (
        <aside
            style={{
                width: 240,
                flex: 'none',
                background: 'var(--primary-background-color)',
                borderInlineEnd: '1px solid var(--layout-border-color)',
                overflowY: 'auto',
                overflowX: 'hidden',
                padding: '16px 12px',
                display: 'flex',
                flexDirection: 'column',
                gap: 20,
            }}
        >
            <div style={{ display: 'flex', flexDirection: 'column', gap: 2, flexShrink: 0 }}>
                {VIEW_DEFS.map((view) => {
                    const active = filters.view === view.key;
                    const count = counts[view.key] || 0;
                    // Only the reply queue turns red, and only when something in it has
                    // actually breached — a permanently red badge stops meaning anything.
                    const urgent = view.key === 'needsReply' && overdueCount > 0;

                    return (
                        <button
                            key={view.key}
                            type="button"
                            title={view.hint}
                            aria-current={active ? 'true' : undefined}
                            onClick={() => onView(view.key)}
                            onMouseEnter={(e) => {
                                if (!active)
                                    e.currentTarget.style.background =
                                        'var(--primary-background-hover-color)';
                            }}
                            onMouseLeave={(e) => {
                                if (!active) e.currentTarget.style.background = 'transparent';
                            }}
                            style={{
                                height: 36,
                                padding: '0 8px',
                                borderRadius: 4,
                                border: 'none',
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                cursor: 'pointer',
                                textAlign: 'start',
                                background: active ? 'var(--primary-selected-color)' : 'transparent',
                                color: active ? 'var(--primary-color)' : 'var(--primary-text-color)',
                                font: `${active ? 600 : 400} 14px/20px Figtree, sans-serif`,
                                transition: 'background 100ms cubic-bezier(.4,0,.2,1)',
                            }}
                        >
                            <Icon name={view.icon} size={16} color="currentColor" />
                            <span
                                style={{
                                    flex: 1,
                                    minWidth: 0,
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                }}
                            >
                                {view.label}
                            </span>
                            {count > 0 ? (
                                <Counter
                                    count={count}
                                    kind={urgent ? 'fill' : 'line'}
                                    color={urgent ? 'negative' : 'dark'}
                                    size="small"
                                />
                            ) : null}
                        </button>
                    );
                })}
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, flexShrink: 0 }}>
                <SectionLabel>Project</SectionLabel>
                <Dropdown
                    options={options.projects || []}
                    value={filters.project_id}
                    onChange={(value) => onChange({ project_id: value })}
                    size="small"
                    searchable
                />
            </div>

            {options.categories?.length ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, flexShrink: 0 }}>
                    <SectionLabel>Categories</SectionLabel>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                        {options.categories.map((category) => {
                            const on = selected.has(Number(category.id));
                            return (
                                <Chips
                                    key={category.id}
                                    label={category.name}
                                    size="small"
                                    selected={on}
                                    color={on ? categoryColour(category.name) : 'neutral'}
                                    onClick={() => toggleCategory(Number(category.id))}
                                    title={on ? `Remove ${category.name}` : `Filter by ${category.name}`}
                                />
                            );
                        })}
                    </div>
                    {selected.size > 1 ? (
                        <div
                            style={{
                                font: '400 12px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            Showing threads in all {selected.size} categories.
                        </div>
                    ) : null}
                </div>
            ) : null}

            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 10,
                    // The rail scrolls AND is a flex column, so its sections would
                    // otherwise compress instead of overflowing on a short window.
                    flexShrink: 0,
                    paddingTop: 16,
                    borderTop: '1px solid var(--om-hairline)',
                }}
            >
                <SectionLabel>Refine</SectionLabel>
                <Checkbox
                    label="Unread only"
                    checked={filters.unread_only}
                    onChange={(value) => onChange({ unread_only: value })}
                />
                <Checkbox
                    label="Overdue to reply only"
                    checked={filters.overdue_only}
                    onChange={(value) => onChange({ overdue_only: value })}
                />
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 0 }}>
                    <TextField
                        size="small"
                        type="date"
                        label="From"
                        value={filters.from}
                        onChange={(e) => onChange({ from: e.target.value })}
                    />
                    <TextField
                        size="small"
                        type="date"
                        label="To"
                        value={filters.to}
                        onChange={(e) => onChange({ to: e.target.value })}
                    />
                </div>
                {hasFilters ? (
                    <Button kind="tertiary" size="small" onClick={onClear}>
                        Clear filters
                    </Button>
                ) : null}
            </div>
        </aside>
    );
}
