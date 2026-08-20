/**
 * Email templates and their placeholders, for the composer.
 *
 * Reuses the existing endpoints the legacy composer uses — `/api/email-templates`,
 * `/api/source-models/{model}` and `/api/projects/{id}/email-preview` — rather than adding
 * parallel ones, so a template behaves identically composed from either inbox.
 *
 * The shapes those endpoints return, because they are not obvious:
 *  - `/api/email-templates` is a BARE ARRAY (no `data` wrapper), each item carrying an
 *    eager-loaded `placeholders` array.
 *  - The placeholder boolean flags come back as 0/1 ints, not booleans — the models
 *    declare no casts. Coerce before testing.
 *  - `/api/projects/{id}/email-preview` returns `body_html` as a COMPLETE HTML document
 *    (doctype, head, the branded wrapper), which is why the preview renders in an iframe
 *    rather than through dangerouslySetInnerHTML.
 */

import axios from 'axios';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

/** Placeholders the person actually fills in. The rest resolve server-side. */
export function inputPlaceholders(template) {
    if (!template?.placeholders) return [];

    return template.placeholders.filter(
        (p) => Number(p.is_dynamic) || Number(p.is_repeatable) || Number(p.is_selectable)
    );
}

/** Which of the five widget shapes a placeholder needs. Order matters. */
export function placeholderKind(p) {
    const dynamic = Number(p.is_dynamic);
    const repeatable = Number(p.is_repeatable);
    const selectable = Number(p.is_selectable);
    const link = Number(p.is_link);

    if (repeatable && dynamic) return 'repeatable-text';
    if (repeatable) return 'repeatable-select';
    if (selectable) return 'select';
    if (dynamic && link) return 'link';
    return 'text';
}

/** The backend parses exactly this shape for link placeholders. */
export const buildLinkString = (label, url) => `(${label || ''})[${url || ''}]`;

export function parseLinkString(value) {
    const m = /^\((.*?)\)\[(.*?)\]$/.exec(value || '');
    return m ? { label: m[1], url: m[2] } : { label: '', url: '' };
}

/** The empty value a placeholder starts at, by kind. */
export function emptyValueFor(p) {
    const kind = placeholderKind(p);
    if (kind === 'repeatable-text' || kind === 'repeatable-select') return [];
    if (kind === 'select') return null;
    return '';
}

export function useTemplates({ enabled = true, onError } = {}) {
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(false);
    const [sourceData, setSourceData] = useState({}); // placeholderName -> [{value,label}]
    // model name -> raw rows. Cached by MODEL because that is what the request is keyed
    // on, but mapped per PLACEHOLDER below, because two placeholders can share a model
    // and label it with different source_attributes.
    const modelRows = useRef(new Map());

    useEffect(() => {
        if (!enabled) return;

        setLoading(true);
        axios
            .get('/api/email-templates')
            .then(({ data }) => setTemplates(Array.isArray(data) ? data : data?.data || []))
            .catch((e) => onError?.(e?.response?.data?.message || 'Could not load the email templates.'))
            .finally(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled]);

    /**
     * Fetch the option lists a template's select/multiselect placeholders need.
     *
     * One request per distinct source_model, but the results are keyed by PLACEHOLDER
     * name — two placeholders can share a model and differ in `source_attribute`, so they
     * need different labels off the same rows.
     */
    const loadSourceData = useCallback(
        async (template) => {
            const needed = (template?.placeholders || []).filter(
                (p) => (Number(p.is_selectable) || Number(p.is_repeatable)) && p.source_model
            );

            if (!needed.length) return;

            const missing = [...new Set(needed.map((p) => p.source_model))].filter(
                (m) => !modelRows.current.has(m)
            );

            await Promise.all(
                missing.map((model) =>
                    axios
                        .get(`/api/source-models/${encodeURIComponent(model)}`)
                        .then(({ data }) => modelRows.current.set(model, Array.isArray(data) ? data : []))
                        // A failed lookup leaves that placeholder with an empty list rather
                        // than blocking the whole composer.
                        .catch(() => modelRows.current.set(model, []))
                )
            );

            // Map for EVERY needed placeholder, not just the ones whose model was fetched
            // on this pass. Skipping the already-cached ones meant that switching to a
            // second template whose placeholder reused an earlier model left that field
            // permanently empty — with no error, so the person previews and sends a
            // template with an unfilled placeholder.
            setSourceData((current) => {
                const next = { ...current };

                needed.forEach((p) => {
                    const rows = modelRows.current.get(p.source_model) || [];
                    next[p.name] = rows.map((row) => ({
                        value: row.id,
                        label: row[p.source_attribute] || row.name || `ID: ${row.id}`,
                    }));
                });

                return next;
            });
        },
        []
    );

    const byId = useMemo(() => {
        const map = new Map();
        templates.forEach((t) => map.set(t.id, t));
        return map;
    }, [templates]);

    const options = useMemo(
        () => templates.map((t) => ({ value: t.id, label: t.name })),
        [templates]
    );

    return { templates, byId, options, loading, sourceData, loadSourceData };
}

/**
 * The rendered preview for a template + its filled-in data.
 *
 * Manual, not automatic — same as the legacy composer. Every refresh is a full blade
 * render server-side, so firing one per keystroke would be wasteful and jumpy.
 */
export function useTemplatePreview({ projectId, clientId, onError }) {
    const [html, setHtml] = useState('');
    const [subject, setSubject] = useState('');
    const [loading, setLoading] = useState(false);

    const canPreview = !!projectId && !!clientId;

    const refresh = useCallback(
        async (templateId, templateData) => {
            if (!canPreview || !templateId) return null;

            setLoading(true);
            try {
                const { data } = await axios.post(`/api/projects/${projectId}/email-preview`, {
                    template_id: templateId,
                    client_id: clientId,
                    template_data: templateData || {},
                });

                setHtml(data.body_html || '');
                setSubject(data.subject || '');
                return data;
            } catch (e) {
                onError?.(e?.response?.data?.message || 'Could not build the preview.');
                return null;
            } finally {
                setLoading(false);
            }
        },
        [canPreview, projectId, clientId, onError]
    );

    const reset = useCallback(() => {
        setHtml('');
        setSubject('');
    }, []);

    return { html, subject, loading, canPreview, refresh, reset };
}
