/**
 * Filters, as a bottom sheet.
 *
 * Design source: Redesign/inbox-mobile/Inbox Mobile.dc.html — the `filtersOpen` sheet and
 * the `projectPickerOpen` screen behind it.
 *
 * This sheet is also where the phone reaches the VIEWS. The tab bar is the mock's shape
 * literally — Inbox, Alerts, compose, Sent, Portal — which covers three of the eight views
 * the desktop rail lists, and the other five are not optional extras: "Waiting approval"
 * is the manager's queue and "With AI" is where a submitted reply actually sits. So the
 * mock's "Status" section is wired to the real view list rather than to a made-up one, and
 * every view is two taps away with its own count on it.
 *
 * Counts come from the server and already respect the project, category and search
 * refinement, so a badge always matches what tapping it will show.
 */

import { useMemo, useState } from 'react';

import { Button, Chips, Icon, Search, TextField, Toggle } from '../../ds';
import { VIEW_DEFS, categoryColour, plural } from '../format';
import { PushHeader, PushScreen, Sheet } from './Sheet';

/** The mock's date chips. Computed locally, then posted as plain YYYY-MM-DD. */
const DATE_PRESETS = [
    { key: 'any', label: 'Any time' },
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'week', label: 'This week' },
    { key: 'last7', label: 'Last 7 days' },
    { key: 'month', label: 'This month' },
];

const ymd = (date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * A preset's [from, to].
 *
 * Deliberately built from the DEVICE's clock rather than asked for from the server. The
 * server compares against `emails.created_at` in the app timezone, and the difference only
 * shows up for someone travelling — at which point "Today" meaning their today is the more
 * defensible answer.
 */
function presetRange(key) {
    const now = new Date();
    const start = new Date(now);

    switch (key) {
        case 'today':
            return [ymd(now), ymd(now)];
        case 'yesterday':
            start.setDate(start.getDate() - 1);
            return [ymd(start), ymd(start)];
        case 'week':
            // Monday-based, matching the mock.
            start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
            return [ymd(start), ymd(now)];
        case 'last7':
            start.setDate(start.getDate() - 6);
            return [ymd(start), ymd(now)];
        case 'month':
            start.setDate(1);
            return [ymd(start), ymd(now)];
        default:
            return ['', ''];
    }
}

function Section({ label, children, hint }) {
    return (
        <div style={{ padding: '14px 16px 0' }}>
            <div
                style={{
                    font: '700 11px/16px Figtree, sans-serif',
                    color: 'var(--secondary-text-color)',
                    textTransform: 'uppercase',
                    letterSpacing: '.4px',
                    marginBottom: 8,
                }}
            >
                {label}
            </div>
            {children}
            {hint ? (
                <div
                    style={{
                        marginTop: 6,
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {hint}
                </div>
            ) : null}
        </div>
    );
}

/** The mock's pill. A real button, not a styled span — this is the primary control here. */
function Pill({ label, count, active, dot, onClick }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                height: 34,
                padding: '0 12px',
                border: `1px solid ${active ? 'var(--primary-color)' : 'var(--ui-border-color)'}`,
                borderRadius: 17,
                background: active ? 'var(--primary-selected-color)' : 'var(--primary-background-color)',
                color: active ? 'var(--primary-color)' : 'var(--primary-text-color)',
                font: '600 12px/16px Figtree, sans-serif',
                cursor: 'pointer',
            }}
        >
            {dot ? <span style={{ width: 8, height: 8, borderRadius: '50%', background: dot }} /> : null}
            {label}
            {count > 0 ? (
                <span
                    style={{
                        minWidth: 18,
                        height: 18,
                        padding: '0 5px',
                        borderRadius: 9,
                        background: active ? 'var(--primary-color)' : 'var(--allgrey-background-color)',
                        color: active ? 'var(--text-color-on-primary)' : 'var(--secondary-text-color)',
                        font: '700 10px/18px Figtree, sans-serif',
                        textAlign: 'center',
                    }}
                >
                    {count > 99 ? '99+' : count}
                </span>
            ) : null}
        </button>
    );
}

function ProjectPicker({ open, onClose, options, value, onPick }) {
    const [query, setQuery] = useState('');

    const rows = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter((o) => String(o.label || '').toLowerCase().includes(q));
    }, [options, query]);

    return (
        <PushScreen
            open={open}
            onClose={onClose}
            header={
                <>
                    <PushHeader onBack={onClose} title="Choose a project" />
                    <div style={{ padding: '0 14px 12px' }}>
                        <Search
                            size="small"
                            placeholder="Search projects and clients"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onClear={() => setQuery('')}
                        />
                    </div>
                </>
            }
        >
            {rows.map((option) => {
                const selected = String(option.value) === String(value);
                return (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => {
                            onPick(option.value);
                            onClose();
                        }}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 12,
                            width: '100%',
                            boxSizing: 'border-box',
                            padding: '13px 16px',
                            border: 'none',
                            borderBottom: '1px solid var(--om-hairline-soft)',
                            background: selected
                                ? 'var(--primary-highlighted-color)'
                                : 'var(--primary-background-color)',
                            cursor: 'pointer',
                            textAlign: 'start',
                        }}
                    >
                        <span
                            style={{
                                flex: 1,
                                minWidth: 0,
                                font: `${selected ? 600 : 400} 14px/20px Figtree, sans-serif`,
                                color: 'var(--primary-text-color)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {option.label}
                        </span>
                        {selected ? <Icon name="Check" size={16} color="var(--primary-color)" /> : null}
                    </button>
                );
            })}

            {rows.length === 0 ? (
                <div
                    style={{
                        padding: '28px 24px',
                        textAlign: 'center',
                        font: '400 13px/18px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    No project matches that — try the client&rsquo;s name.
                </div>
            ) : null}
        </PushScreen>
    );
}

export function MobileFilterSheet({
    open,
    onClose,
    filters,
    counts,
    overdueCount,
    options,
    hasFilters,
    resultCount,
    onView,
    onChange,
    onClear,
}) {
    const [pickerOpen, setPickerOpen] = useState(false);

    const projects = options.projects || [];
    const projectLabel =
        projects.find((p) => String(p.value) === String(filters.project_id))?.label || 'All my projects';

    const selectedCategories = new Set((filters.category_ids || []).map(Number));

    const toggleCategory = (id) => {
        const next = new Set(selectedCategories);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        onChange({ category_ids: [...next] });
    };

    const datePreset = useMemo(() => {
        if (!filters.from && !filters.to) return 'any';
        const match = DATE_PRESETS.find((p) => {
            if (p.key === 'any') return false;
            const [from, to] = presetRange(p.key);
            return from === filters.from && to === filters.to;
        });
        return match?.key || null;
    }, [filters.from, filters.to]);

    return (
        <>
            <Sheet
                open={open}
                onClose={onClose}
                title="Filters"
                maxHeight="88%"
                actions={
                    hasFilters ? (
                        <Button kind="tertiary" size="small" onClick={onClear}>
                            Clear all
                        </Button>
                    ) : null
                }
                footer={
                    <Button size="medium" fullWidth onClick={onClose}>
                        {resultCount == null ? 'Done' : `Show ${plural(resultCount, 'thread')}`}
                    </Button>
                }
            >
                <Section label="Status">
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {VIEW_DEFS.map((view) => (
                            <Pill
                                key={view.key}
                                label={view.label}
                                count={counts?.[view.key] || 0}
                                active={filters.view === view.key}
                                onClick={() => onView(view.key)}
                            />
                        ))}
                    </div>
                </Section>

                <Section
                    label="Breach risk"
                    hint={
                        overdueCount
                            ? `${plural(overdueCount, 'thread')} past the reply rule right now.`
                            : 'Nothing has passed the reply rule.'
                    }
                >
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        {/*
                          Two chips, not the mock's three. The mock splits the clock into
                          past / under 20 minutes / comfortable, but the server only filters
                          on `overdue_only` — the middle band would have to be computed on
                          whatever page happened to be loaded, and would then disagree with
                          the count on the chip beside it. The remaining time is on every
                          row's pill, which is where it can be true.
                        */}
                        <Pill
                            label="Any"
                            active={!filters.overdue_only}
                            dot="var(--ui-border-color)"
                            onClick={() => onChange({ overdue_only: false })}
                        />
                        <Pill
                            label="Past the reply rule"
                            active={filters.overdue_only}
                            dot="#d83a52"
                            count={overdueCount}
                            onClick={() => onChange({ overdue_only: true })}
                        />
                    </div>
                </Section>

                <Section label="Order">
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                        <Pill
                            label="Longest waiting"
                            active={filters.sort === 'breach'}
                            onClick={() => onChange({ sort: 'breach' })}
                        />
                        <Pill
                            label="Newest first"
                            active={filters.sort === 'date'}
                            onClick={() => onChange({ sort: 'date' })}
                        />
                    </div>
                </Section>

                <Section label="Project">
                    <button
                        type="button"
                        onClick={() => setPickerOpen(true)}
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            width: '100%',
                            boxSizing: 'border-box',
                            height: 44,
                            padding: '0 12px',
                            border: '1px solid var(--ui-border-color)',
                            borderRadius: 4,
                            background: 'var(--primary-background-color)',
                            cursor: 'pointer',
                            textAlign: 'start',
                        }}
                    >
                        <span
                            style={{
                                flex: 1,
                                minWidth: 0,
                                font: '400 14px/20px Figtree, sans-serif',
                                color: 'var(--primary-text-color)',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                            }}
                        >
                            {projectLabel}
                        </span>
                        <Icon name="NavigationChevronRight" size={16} color="var(--icon-color)" />
                    </button>
                </Section>

                {options.categories?.length ? (
                    <Section
                        label="Categories"
                        hint={
                            selectedCategories.size > 1
                                ? `Showing threads in all ${selectedCategories.size} categories.`
                                : null
                        }
                    >
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                            {options.categories.map((category) => {
                                const on = selectedCategories.has(Number(category.id));
                                return (
                                    <Chips
                                        key={category.id}
                                        label={category.name}
                                        size="small"
                                        selected={on}
                                        color={on ? categoryColour(category.name) : 'neutral'}
                                        onClick={() => toggleCategory(Number(category.id))}
                                    />
                                );
                            })}
                        </div>
                    </Section>
                ) : null}

                <Section label="Date range">
                    <div className="om-scroll" style={{ display: 'flex', gap: 8, overflowX: 'auto', paddingBottom: 10 }}>
                        {DATE_PRESETS.map((preset) => (
                            <span key={preset.key} style={{ flex: 'none' }}>
                                <Pill
                                    label={preset.label}
                                    active={datePreset === preset.key}
                                    onClick={() => {
                                        const [from, to] = presetRange(preset.key);
                                        onChange({ from, to });
                                    }}
                                />
                            </span>
                        ))}
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <TextField
                                size="small"
                                type="date"
                                label="From"
                                value={filters.from}
                                onChange={(e) => onChange({ from: e.target.value })}
                            />
                        </div>
                        <div style={{ flex: 1, minWidth: 0 }}>
                            <TextField
                                size="small"
                                type="date"
                                label="To"
                                value={filters.to}
                                onChange={(e) => onChange({ to: e.target.value })}
                            />
                        </div>
                    </div>
                </Section>

                <Section label="Refine">
                    <label
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: 10,
                            padding: '4px 0 16px',
                            font: '400 14px/20px Figtree, sans-serif',
                        }}
                    >
                        <Toggle
                            checked={filters.unread_only}
                            onChange={(value) => onChange({ unread_only: value })}
                            ariaLabel="Unread only"
                        />
                        <span>Unread only</span>
                    </label>
                </Section>
            </Sheet>

            <ProjectPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                options={projects}
                value={filters.project_id}
                onPick={(value) => onChange({ project_id: value })}
            />
        </>
    );
}
