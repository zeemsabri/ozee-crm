/**
 * The bin.
 *
 * A list of deleted EMAILS, deliberately not threads — see InboxDeletedController for the
 * reasoning. Which is also why this is not ThreadList with a filter: the rows are a
 * different thing, they carry no reply clock, no unread state and no selection, and the
 * only action on them is Restore.
 *
 * Everything here is recoverable. The Gmail half of a delete is not, and the empty state
 * and the row footnote both say so — someone who ticked both boxes and comes here looking
 * for the Gmail copy should find out from the screen, not from the client.
 */

import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Button, EmptyState, Icon, Loader, Search } from '../ds';
import { shortTime } from './format';

const ROW = {
    display: 'flex',
    alignItems: 'center',
    gap: 12,
    padding: '12px 16px',
    borderBottom: '1px solid var(--om-hairline)',
};

export function DeletedList({ onError, onRestored, onOpenThread }) {
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [restoring, setRestoring] = useState(null);

    const load = useCallback(
        async (term) => {
            setLoading(true);
            try {
                const { data } = await axios.get('/api/inbox/deleted', {
                    params: term ? { search: term } : {},
                });
                setRows(data.data || []);
                setMeta(data.meta || null);
            } catch (e) {
                onError?.(
                    e?.response?.status === 403
                        ? 'You do not have permission to view deleted mail.'
                        : 'Could not load deleted mail.'
                );
            } finally {
                setLoading(false);
            }
        },
        [onError]
    );

    useEffect(() => {
        load('');
    }, [load]);

    // Debounced, so typing does not fire a request per keystroke against a table that has
    // no index on subject.
    useEffect(() => {
        const id = setTimeout(() => load(search.trim()), 300);
        return () => clearTimeout(id);
    }, [search, load]);

    const restore = async (row) => {
        setRestoring(row.id);
        try {
            await axios.post(`/api/inbox/emails/${row.id}/restore`);
            setRows((current) => current.filter((r) => r.id !== row.id));
            onRestored?.(row);
        } catch {
            onError?.('Could not restore that message.');
        } finally {
            setRestoring(null);
        }
    };

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
                <div style={{ font: '600 16px/24px Figtree, sans-serif' }}>Deleted</div>
                <div
                    style={{
                        font: '400 13px/20px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {meta?.total ? `${meta.total} message${meta.total === 1 ? '' : 's'}` : ''}
                </div>
                <span style={{ marginInlineStart: 'auto', minWidth: 220 }}>
                    <Search
                        value={search}
                        size="small"
                        placeholder="Search deleted subjects"
                        onChange={(e) => setSearch(e.target.value)}
                        onClear={() => setSearch('')}
                    />
                </span>
            </div>

            <div style={{ flex: 1, overflowY: 'auto', minHeight: 0 }}>
                {loading ? (
                    <div style={{ padding: 40, display: 'flex', justifyContent: 'center' }}>
                        <Loader ariaLabel="Loading deleted mail" />
                    </div>
                ) : rows.length === 0 ? (
                    <div style={{ padding: 40 }}>
                        <EmptyState
                            title={search ? 'Nothing matches that' : 'Nothing deleted'}
                            description={
                                search
                                    ? 'No deleted message has that in its subject.'
                                    : 'Deleting a message here keeps the row and puts it in this list. A Gmail copy deleted at the same time is not kept — that one is gone from the Gmail bin onwards.'
                            }
                        />
                    </div>
                ) : (
                    rows.map((row) => (
                        <div key={row.id} style={ROW}>
                            <Icon
                                name={row.direction === 'in' ? 'Email' : 'Send'}
                                size={18}
                                color="var(--secondary-text-color)"
                            />

                            <div style={{ flex: 1, minWidth: 0 }}>
                                <div
                                    style={{
                                        font: '600 14px/20px Figtree, sans-serif',
                                        overflow: 'hidden',
                                        textOverflow: 'ellipsis',
                                        whiteSpace: 'nowrap',
                                    }}
                                >
                                    {row.subject}
                                </div>
                                <div
                                    style={{
                                        font: '400 12px/16px Figtree, sans-serif',
                                        color: 'var(--secondary-text-color)',
                                        display: 'flex',
                                        gap: 8,
                                        flexWrap: 'wrap',
                                    }}
                                >
                                    <span>{row.correspondent}</span>
                                    {row.project ? <span>· {row.project}</span> : null}
                                    <span>· deleted {shortTime(row.deleted_at)}</span>
                                    {row.is_private ? (
                                        <span style={{ color: 'var(--warning-color)' }}>· private</span>
                                    ) : null}
                                    {/*
                                      A thread with nothing live left in it is not in the
                                      inbox list at all (ThreadQuery::base), so there is
                                      nowhere to send someone. Say what restoring will do
                                      rather than offering a link to a page that would be
                                      empty.
                                    */}
                                    {row.conversation?.live ? null : (
                                        <span>· restoring this brings the thread back</span>
                                    )}
                                </div>
                            </div>

                            {row.conversation?.live && onOpenThread ? (
                                <Button
                                    kind="tertiary"
                                    size="small"
                                    onClick={() => onOpenThread(row.conversation.id)}
                                >
                                    Open thread
                                </Button>
                            ) : null}

                            <Button
                                size="small"
                                kind="secondary"
                                loading={restoring === row.id}
                                disabled={!!restoring}
                                leftIcon={<Icon name="Update" size={16} />}
                                onClick={() => restore(row)}
                            >
                                Restore
                            </Button>
                        </div>
                    ))
                )}
            </div>

            {meta && meta.last_page > 1 ? (
                <div
                    style={{
                        padding: '8px 16px',
                        borderTop: '1px solid var(--layout-border-color)',
                        font: '400 12px/16px Figtree, sans-serif',
                        color: 'var(--secondary-text-color)',
                    }}
                >
                    {/*
                      Deliberately a note, not a pager. The bin is not somewhere people
                      browse — they come looking for something they deleted minutes ago,
                      which is on the first page because the list is newest-deleted first.
                      Search is the way to reach the rest.
                    */}
                    Showing the {rows.length} most recently deleted of {meta.total}. Search to
                    find an older one.
                </div>
            ) : null}
        </div>
    );
}
