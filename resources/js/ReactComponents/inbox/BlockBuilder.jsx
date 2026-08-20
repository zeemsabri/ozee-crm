/**
 * "Project update — build it in blocks."
 *
 * Design source: Redesign/.../EmailBlocks.dc.html — a block list on the left, a live
 * client-eye preview on the right.
 *
 * ## One deliberate deviation from the mock
 *
 * The mock's image block offers "Image URL, or drop a file below". This only offers the
 * file. An image referenced by URL is a *remote* image in the sent email, and every major
 * mail client blocks those by default until the reader clicks "show images" — so the
 * screenshot the update was written around would arrive as an empty box for most people.
 * Uploaded files are embedded in the message itself as CID parts and always render. That
 * is also what lets our own stored copy expire after six months without the client's copy
 * changing. See App\Services\Inbox\BlockRenderer.
 *
 * ## The preview is server-rendered
 *
 * The panel on the right shows HTML produced by the same PHP renderer that produces the
 * outgoing email — see useBlocks.js for why there is no JavaScript copy of it. The chrome
 * around it (logo, greeting, signature) is the mock's, and is drawn here because it comes
 * from the mail layout at send time rather than from the blocks.
 */

import { useRef, useState } from 'react';
import { Button, Icon, IconButton, Label, MenuButton, TextArea, TextField } from '../ds';
import { TYPE_LABEL } from './useBlocks';

// Glyph names are from public/ozee-ds/icons — the mock's icGlobe/icBullets resolve to
// Globe and CheckList in the shipped set. There is no "Link" glyph; Globe is what the
// design system uses for one.
const ADD_BUTTONS = [
    { type: 'text', icon: 'Add', text: 'Text' },
    { type: 'bullets', icon: 'CheckList', text: 'Bullets' },
    { type: 'link', icon: 'Globe', text: 'Link' },
    { type: 'image', icon: 'Image', text: 'Image' },
];

const HINT =
    'Link a few words inside any line by typing (Label)[https://example.com]. Bold a phrase with *asterisks*.';

export function BlockBuilder({ blocks, greeting, signOff, disabled }) {
    const [bulkOpen, setBulkOpen] = useState(false);
    const [bulkText, setBulkText] = useState('');
    const [dragKey, setDragKey] = useState(null);
    const [overKey, setOverKey] = useState(null);

    const count = blocks.blocks.length;

    return (
        <div
            style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))',
                gap: 16,
                alignItems: 'start',
            }}
        >
            {/* ------------------------------------------------------------ editor */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, minWidth: 0 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
                    {ADD_BUTTONS.map((b) => (
                        <Button
                            key={b.type}
                            kind="secondary"
                            size="small"
                            disabled={disabled}
                            leftIcon={<Icon name={b.icon} size={14} />}
                            onClick={() => blocks.add(b.type)}
                        >
                            {b.text}
                        </Button>
                    ))}
                    <Button
                        kind="tertiary"
                        size="small"
                        disabled={disabled}
                        leftIcon={<Icon name="Duplicate" size={14} />}
                        onClick={() => setBulkOpen((v) => !v)}
                    >
                        Bulk paste
                    </Button>
                    <span
                        style={{
                            marginInlineStart: 'auto',
                            font: '400 12px/16px Figtree, sans-serif',
                            color: 'var(--secondary-text-color)',
                        }}
                    >
                        {count === 0
                            ? 'No blocks yet'
                            : `${count} block${count === 1 ? '' : 's'}${
                                  blocks.meaningfulCount < count
                                      ? ` — ${count - blocks.meaningfulCount} still empty`
                                      : ''
                              }`}
                    </span>
                </div>

                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 6,
                        padding: '6px 10px',
                        borderRadius: 4,
                        background: 'var(--primary-highlighted-color)',
                    }}
                >
                    <Icon name="Info" size={14} color="var(--primary-color)" />
                    <span style={{ font: '400 12px/16px Figtree, sans-serif' }}>{HINT}</span>
                </div>

                {bulkOpen ? (
                    <div
                        style={{
                            padding: 10,
                            border: '1px solid var(--primary-color)',
                            borderRadius: 4,
                            background: 'var(--allgrey-background-color)',
                            animation: 'dcFade 150ms cubic-bezier(0,0,.35,1) both',
                        }}
                    >
                        <div style={{ font: '600 12px/16px Figtree, sans-serif', marginBottom: 8 }}>
                            Paste your draft — blank lines start a new paragraph, and a run of
                            &ldquo;- &rdquo; lines becomes one bullet list
                        </div>
                        <TextArea
                            rows={5}
                            placeholder="Paste from notes, Slack or a chat draft"
                            value={bulkText}
                            onChange={(e) => setBulkText(e.target.value)}
                        />
                        <div style={{ marginTop: 8, display: 'flex', gap: 8 }}>
                            <Button
                                size="small"
                                disabled={!bulkText.trim()}
                                onClick={() => {
                                    blocks.bulkPaste(bulkText);
                                    setBulkText('');
                                    setBulkOpen(false);
                                }}
                            >
                                Split into blocks
                            </Button>
                            <Button
                                kind="tertiary"
                                size="small"
                                onClick={() => {
                                    setBulkText('');
                                    setBulkOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                ) : null}

                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {blocks.blocks.map((block, index) => (
                        <BlockRow
                            key={block.key}
                            block={block}
                            index={index}
                            total={count}
                            blocks={blocks}
                            disabled={disabled}
                            isOver={overKey === block.key && dragKey !== block.key}
                            onDragStart={() => setDragKey(block.key)}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setOverKey(block.key);
                            }}
                            onDragLeave={() => setOverKey((k) => (k === block.key ? null : k))}
                            onDrop={(e) => {
                                e.preventDefault();
                                if (dragKey && dragKey !== block.key) blocks.move(dragKey, index);
                                setDragKey(null);
                                setOverKey(null);
                            }}
                            onDragEnd={() => {
                                setDragKey(null);
                                setOverKey(null);
                            }}
                        />
                    ))}

                    {count === 0 ? (
                        <div
                            style={{
                                padding: '18px 14px',
                                border: '1px dashed var(--ui-border-color)',
                                borderRadius: 4,
                                textAlign: 'center',
                                font: '400 13px/20px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            Start with a Text block, or paste a draft and let it split itself up.
                        </div>
                    ) : null}
                </div>
            </div>

            {/* ------------------------------------------------------------ preview */}
            <div
                style={{
                    border: '1px solid var(--layout-border-color)',
                    borderRadius: 8,
                    background: '#ffffff',
                    color: '#323338',
                    overflow: 'hidden',
                    minWidth: 0,
                }}
            >
                <div
                    style={{
                        padding: '10px 16px',
                        borderBottom: '1px solid #e6e9ef',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                    }}
                >
                    <span
                        style={{
                            font: '600 11px/14px Figtree, sans-serif',
                            color: '#676879',
                            textTransform: 'uppercase',
                            letterSpacing: '.4px',
                        }}
                    >
                        Live preview — what the client receives
                    </span>
                    {blocks.previewing ? (
                        <span
                            style={{
                                marginInlineStart: 'auto',
                                font: '400 11px/14px Figtree, sans-serif',
                                color: '#676879',
                            }}
                        >
                            updating…
                        </span>
                    ) : null}
                </div>

                <div style={{ padding: '20px 18px 24px', maxWidth: 600, margin: '0 auto' }}>
                    {greeting ? (
                        <div
                            style={{
                                font: '400 15px/24px Arial, Helvetica, sans-serif',
                                color: '#676879',
                                marginBottom: 12,
                            }}
                        >
                            {greeting}
                        </div>
                    ) : null}

                    {blocks.previewHtml ? (
                        /*
                          The HTML here is generated by our own BlockRenderer on the
                          server, from blocks this user just typed, and every author-
                          supplied string in it has been through e() — including the
                          hrefs, which safeUrl() has already restricted to http/https/
                          mailto. So this is our markup rendered back to its author, not
                          third-party HTML.
                        */
                        <div dangerouslySetInnerHTML={{ __html: blocks.previewHtml }} />
                    ) : (
                        <div
                            style={{
                                height: 140,
                                border: '1px dashed #c3c6d4',
                                borderRadius: 4,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                font: '400 13px/20px Figtree, sans-serif',
                                color: '#676879',
                                textAlign: 'center',
                                padding: '0 16px',
                            }}
                        >
                            Your update will appear here as the client will see it.
                        </div>
                    )}

                    <div
                        style={{
                            marginTop: 20,
                            paddingTop: 14,
                            borderTop: '1px solid #e6e9ef',
                            font: '400 13px/20px Arial, Helvetica, sans-serif',
                            color: '#676879',
                        }}
                    >
                        <div style={{ font: '600 14px/20px Arial, Helvetica, sans-serif', color: '#323338' }}>
                            {signOff?.name || 'Your name'}
                        </div>
                        {signOff?.role ? <div>{signOff.role}</div> : null}
                        <div>OZee Web &amp; Digital · OZeeWeb.com.au</div>
                    </div>

                    {/*
                      Which of these are actually added for you differs by composer, and
                      saying "both" everywhere would be a small lie with a visible
                      consequence: on a reply nothing prepends a greeting, so someone who
                      trusted this note would send an update that opens mid-sentence.
                      `greeting` is only passed when the server really will add one.
                    */}
                    <div
                        style={{
                            marginTop: 12,
                            font: 'italic 400 11px/16px Figtree, sans-serif',
                            color: '#9699a6',
                        }}
                    >
                        {greeting
                            ? 'The greeting and sign-off are added automatically — you do not need a block for them.'
                            : 'The sign-off is added automatically. A reply has no greeting of its own, so open with a Text block if you want one.'}
                    </div>
                </div>
            </div>
        </div>
    );
}

/** One row of the editor. */
function BlockRow({
    block,
    index,
    total,
    blocks,
    disabled,
    isOver,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
    onDragEnd,
}) {
    const fileInput = useRef(null);
    const [dropping, setDropping] = useState(false);

    const menuItems = [
        { id: 'duplicate', label: 'Duplicate', iconName: 'Duplicate' },
        block.type === 'text' ? { id: 'toBullets', label: 'Turn into bullets', iconName: 'CheckList' } : null,
        block.type === 'bullets' ? { id: 'toText', label: 'Turn into a paragraph', iconName: 'Doc' } : null,
        { id: 'delete', label: 'Delete', iconName: 'Delete', color: 'negative' },
    ].filter(Boolean);

    const onMenu = (item) => {
        const id = item?.id ?? item;
        if (id === 'duplicate') blocks.duplicate(block.key);
        if (id === 'toBullets') blocks.convert(block.key, 'bullets');
        if (id === 'toText') blocks.convert(block.key, 'text');
        if (id === 'delete') blocks.remove(block.key);
    };

    const pickFile = (file) => {
        if (file) blocks.upload(block.key, file);
    };

    return (
        <div
            draggable={!disabled}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
            onDragEnd={onDragEnd}
            style={{
                border: `1px solid ${isOver ? 'var(--primary-color)' : 'var(--ui-border-color)'}`,
                borderRadius: 4,
                background: 'var(--primary-background-color)',
                padding: '8px 10px',
                display: 'flex',
                gap: 8,
                alignItems: 'flex-start',
                transition: 'border-color 100ms cubic-bezier(.4,0,.2,1)',
            }}
        >
            <span
                title="Drag to reorder"
                style={{ paddingTop: 4, cursor: 'grab', color: 'var(--icon-color)', display: 'flex' }}
            >
                <Icon name="Drag" size={16} color="currentColor" />
            </span>

            <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <Label text={TYPE_LABEL[block.type] || 'Text'} kind="line" color="dark" size="small" />
                    <span
                        style={{
                            marginInlineStart: 'auto',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 2,
                        }}
                    >
                        <IconButton
                            name="NavigationChevronUp"
                            size="xs"
                            ariaLabel="Move up"
                            disabled={disabled || index === 0}
                            onClick={() => blocks.nudge(block.key, -1)}
                        />
                        <IconButton
                            name="NavigationChevronDown"
                            size="xs"
                            ariaLabel="Move down"
                            disabled={disabled || index === total - 1}
                            onClick={() => blocks.nudge(block.key, 1)}
                        />
                        <MenuButton
                            items={menuItems}
                            onSelect={onMenu}
                            iconName="MoreActions"
                            size="xs"
                            ariaLabel="Block options"
                        />
                    </span>
                </div>

                {block.type === 'text' || block.type === 'bullets' ? (
                    <TextArea
                        rows={block.type === 'bullets' ? 3 : 2}
                        placeholder={
                            block.type === 'bullets' ? 'One bullet per line' : 'One line or paragraph'
                        }
                        value={block.text || ''}
                        disabled={disabled}
                        onChange={(e) => blocks.patch(block.key, { text: e.target.value })}
                    />
                ) : null}

                {block.type === 'link' ? (
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.4fr', gap: 8 }}>
                        <TextField
                            size="small"
                            placeholder="Label, e.g. Review on staging"
                            value={block.label || ''}
                            disabled={disabled}
                            onChange={(e) => blocks.patch(block.key, { label: e.target.value })}
                        />
                        <TextField
                            size="small"
                            placeholder="https://"
                            value={block.url || ''}
                            disabled={disabled}
                            onChange={(e) => blocks.patch(block.key, { url: e.target.value })}
                        />
                    </div>
                ) : null}

                {block.type === 'image' ? (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                        <TextField
                            size="small"
                            placeholder="Alt text — what the image shows, for anyone who cannot see it"
                            value={block.alt || ''}
                            disabled={disabled}
                            onChange={(e) => blocks.patch(block.key, { alt: e.target.value })}
                        />

                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/gif"
                            style={{ display: 'none' }}
                            onChange={(e) => {
                                pickFile(e.target.files?.[0]);
                                // Reset so choosing the same file twice still fires onChange.
                                e.target.value = '';
                            }}
                        />

                        {block.url ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                <img
                                    src={block.url}
                                    alt={block.alt || ''}
                                    style={{
                                        width: 96,
                                        height: 64,
                                        objectFit: 'cover',
                                        borderRadius: 4,
                                        border: '1px solid var(--ui-border-color)',
                                    }}
                                />
                                <div style={{ minWidth: 0, flex: 1 }}>
                                    <div
                                        style={{
                                            font: '400 12px/16px Figtree, sans-serif',
                                            color: 'var(--secondary-text-color)',
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {block.filename || 'Uploaded image'}
                                    </div>
                                    <Button
                                        kind="tertiary"
                                        size="small"
                                        disabled={disabled || blocks.uploading}
                                        onClick={() => fileInput.current?.click()}
                                    >
                                        Replace
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setDropping(true);
                                }}
                                onDragLeave={() => setDropping(false)}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    setDropping(false);
                                    pickFile(e.dataTransfer?.files?.[0]);
                                }}
                                onClick={() => !disabled && fileInput.current?.click()}
                                role="button"
                                tabIndex={0}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') fileInput.current?.click();
                                }}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 8,
                                    padding: '10px 12px',
                                    border: `1px dashed ${
                                        dropping ? 'var(--primary-color)' : 'var(--ui-border-color)'
                                    }`,
                                    borderRadius: 4,
                                    color: 'var(--secondary-text-color)',
                                    cursor: disabled ? 'not-allowed' : 'pointer',
                                }}
                            >
                                <Icon name="Image" size={16} color="currentColor" />
                                <span style={{ font: '400 12px/16px Figtree, sans-serif' }}>
                                    {blocks.uploading
                                        ? 'Uploading…'
                                        : 'Drop a screenshot here, or click to choose one — JPEG, PNG or GIF'}
                                </span>
                            </div>
                        )}

                        {/* Worth stating where the image actually ends up: it travels
                            inside the message, so the client keeps it even after our
                            copy expires. */}
                        <span
                            style={{
                                font: '400 11px/16px Figtree, sans-serif',
                                color: 'var(--secondary-text-color)',
                            }}
                        >
                            Embedded in the email at 600px wide. The client&rsquo;s copy is permanent;
                            ours is cleaned up after six months.
                        </span>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
