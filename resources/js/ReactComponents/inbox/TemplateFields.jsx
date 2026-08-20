/**
 * The template picker, its placeholder inputs, and the rendered preview.
 *
 * Five widget shapes, matching what the legacy `ComposeEmailContent.vue` renders — not
 * the reduced three in `EmailActionContent.vue`, which silently degrades repeatable-text
 * placeholders into an empty multiselect and makes link placeholders a raw text field you
 * have to hand-type `(Label)[URL]` into.
 *
 * The preview is an iframe rather than dangerouslySetInnerHTML because
 * /api/projects/{id}/email-preview returns a COMPLETE HTML document — doctype, head, the
 * branded wrapper — and nesting that inside the page is invalid and lets the email's CSS
 * escape into the app.
 */

import { useEffect, useState } from 'react';
import { Button, Dropdown, Icon, Label, Loader, TextField } from '../ds';
import {
    buildLinkString,
    emptyValueFor,
    inputPlaceholders,
    parseLinkString,
    placeholderKind,
} from './useTemplates';

/** A repeatable free-text placeholder: an editable list of lines. */
function RepeatableText({ placeholder, value, onChange, allowLinks }) {
    const items = Array.isArray(value) ? value : [];

    const setItem = (index, next) =>
        onChange(items.map((item, i) => (i === index ? next : item)));

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            {items.map((item, index) => {
                const link = allowLinks ? parseLinkString(item) : null;

                return (
                    <div key={index} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        {allowLinks ? (
                            <>
                                <div style={{ flex: 1 }}>
                                    <TextField
                                        size="small"
                                        placeholder="Label"
                                        value={link.label}
                                        onChange={(e) => setItem(index, buildLinkString(e.target.value, link.url))}
                                    />
                                </div>
                                <div style={{ flex: 1 }}>
                                    <TextField
                                        size="small"
                                        placeholder="https://…"
                                        value={link.url}
                                        onChange={(e) => setItem(index, buildLinkString(link.label, e.target.value))}
                                    />
                                </div>
                            </>
                        ) : (
                            <div style={{ flex: 1 }}>
                                <TextField
                                    size="small"
                                    value={item}
                                    onChange={(e) => setItem(index, e.target.value)}
                                />
                            </div>
                        )}
                        <Button
                            kind="tertiary"
                            size="small"
                            color="negative"
                            ariaLabel={`Remove item ${index + 1}`}
                            onClick={() => onChange(items.filter((_, i) => i !== index))}
                        >
                            Remove
                        </Button>
                    </div>
                );
            })}
            <div>
                <Button
                    kind="tertiary"
                    size="small"
                    leftIcon={<Icon name="Add" size={16} />}
                    onClick={() => onChange([...items, allowLinks ? buildLinkString('', '') : ''])}
                >
                    Add {placeholder.name.toLowerCase()}
                </Button>
            </div>
        </div>
    );
}

/** A repeatable source-model placeholder: pick several rows. */
function RepeatableSelect({ options, value, onChange }) {
    const selected = Array.isArray(value) ? value : [];

    if (!options.length) {
        return (
            <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                Nothing available to choose from.
            </div>
        );
    }

    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
            {options.map((option) => {
                const on = selected.includes(option.value);

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={on}
                        onClick={() =>
                            onChange(
                                on
                                    ? selected.filter((v) => v !== option.value)
                                    : [...selected, option.value]
                            )
                        }
                        style={{
                            height: 24,
                            padding: '0 8px',
                            borderRadius: 4,
                            cursor: 'pointer',
                            border: `1px solid ${on ? 'var(--primary-color)' : 'var(--ui-border-color)'}`,
                            background: on ? 'var(--primary-selected-color)' : 'transparent',
                            color: on ? 'var(--primary-color)' : 'var(--primary-text-color)',
                            font: 'var(--font-text2-normal)',
                        }}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

function PlaceholderField({ placeholder, value, options, onChange }) {
    const kind = placeholderKind(placeholder);

    const control = (() => {
        switch (kind) {
            case 'repeatable-text':
                return (
                    <RepeatableText
                        placeholder={placeholder}
                        value={value}
                        onChange={onChange}
                        allowLinks={Boolean(Number(placeholder.is_link))}
                    />
                );
            case 'repeatable-select':
                return <RepeatableSelect options={options} value={value} onChange={onChange} />;
            case 'select':
                return (
                    <Dropdown
                        options={options}
                        value={value}
                        onChange={onChange}
                        size="small"
                        searchable
                        placeholder="Choose one"
                    />
                );
            case 'link': {
                const link = parseLinkString(value);
                return (
                    <div style={{ display: 'flex', gap: 8 }}>
                        <div style={{ flex: 1 }}>
                            <TextField
                                size="small"
                                placeholder="Link text"
                                value={link.label}
                                onChange={(e) => onChange(buildLinkString(e.target.value, link.url))}
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <TextField
                                size="small"
                                placeholder="https://…"
                                value={link.url}
                                onChange={(e) => onChange(buildLinkString(link.label, e.target.value))}
                            />
                        </div>
                    </div>
                );
            }
            default:
                return (
                    <TextField
                        size="small"
                        value={value ?? ''}
                        onChange={(e) => onChange(e.target.value)}
                    />
                );
        }
    })();

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <span style={{ font: '600 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                {/* There is no `label` column on placeholder_definitions — the name IS the
                    label, and reading `.label` would always be undefined. */}
                {placeholder.name}
            </span>
            {placeholder.description ? (
                <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    {placeholder.description}
                </span>
            ) : null}
            {control}
        </div>
    );
}

export function TemplateFields({
    templates,
    templateOptions,
    templateId,
    templateData,
    sourceData,
    loadingTemplates,
    onTemplate,
    onData,
    preview,
    onRefreshPreview,
}) {
    const template = templates.find((t) => t.id === templateId) || null;
    const fields = inputPlaceholders(template);
    const [dirty, setDirty] = useState(false);

    // Load the option lists as soon as a template is chosen, so the selects are not empty
    // on first paint.
    useEffect(() => {
        if (template) onTemplate?.loadSourceData?.(template);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [templateId]);

    if (loadingTemplates) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', padding: 16 }}>
                <Loader size={24} ariaLabel="Loading templates" />
            </div>
        );
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ maxWidth: 360 }}>
                <Dropdown
                    label="Template"
                    options={templateOptions}
                    value={templateId}
                    onChange={(value) => {
                        const next = templates.find((t) => t.id === value);
                        // Reset the data whenever the template changes — the placeholder
                        // set is different, and carrying old keys over would submit
                        // values the new template has no field for.
                        const seeded = {};
                        inputPlaceholders(next).forEach((p) => {
                            seeded[p.name] = emptyValueFor(p);
                        });
                        onData(seeded);
                        onTemplate?.select?.(value);
                        setDirty(true);
                    }}
                    size="small"
                    searchable
                    placeholder="Choose a template"
                />
            </div>

            {fields.length ? (
                <div
                    style={{
                        padding: 12,
                        border: '1px solid var(--layout-border-color)',
                        borderRadius: 4,
                        background: 'var(--allgrey-background-color)',
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
                        gap: 12,
                    }}
                >
                    {fields.map((placeholder) => (
                        <PlaceholderField
                            key={placeholder.id}
                            placeholder={placeholder}
                            value={templateData?.[placeholder.name]}
                            options={sourceData?.[placeholder.name] || []}
                            onChange={(value) => {
                                onData({ ...templateData, [placeholder.name]: value });
                                setDirty(true);
                            }}
                        />
                    ))}
                </div>
            ) : template ? (
                <div style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                    This template has nothing to fill in — everything comes from the project and client.
                </div>
            ) : null}

            {template ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                        <Button
                            kind="secondary"
                            size="small"
                            disabled={!preview.canPreview}
                            loading={preview.loading}
                            leftIcon={<Icon name="Show" size={16} />}
                            onClick={() => {
                                onRefreshPreview();
                                setDirty(false);
                            }}
                        >
                            {preview.html ? 'Refresh preview' : 'Preview'}
                        </Button>

                        {!preview.canPreview ? (
                            <span style={{ font: '400 12px/16px Figtree, sans-serif', color: 'var(--secondary-text-color)' }}>
                                Previews need a client on the project — lead threads cannot preview.
                            </span>
                        ) : dirty && preview.html ? (
                            <Label text="Preview is out of date" color="var(--color-working-orange)" size="small" />
                        ) : null}
                    </div>

                    {preview.html ? (
                        // srcDoc, not dangerouslySetInnerHTML: the response is a whole
                        // document and the iframe also stops the email's CSS leaking out.
                        <iframe
                            title="Email preview"
                            srcDoc={preview.html}
                            sandbox=""
                            style={{
                                width: '100%',
                                height: 320,
                                border: '1px solid var(--layout-border-color)',
                                borderRadius: 4,
                                background: '#fff',
                            }}
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
