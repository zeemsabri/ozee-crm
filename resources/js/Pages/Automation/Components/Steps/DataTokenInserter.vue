<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import DataTokenPicker from '@/Components/DataTokenPicker.vue';

const props = defineProps({
    allStepsBefore: { type: Array, default: () => [] },
    // This new prop will be provided when the inserter is inside a loop
    loopContextSchema: { type: Object, default: null },
    // Controls whether we should auto-infer loop context from the nearest FOR_EACH when none is provided explicitly
    inferLoopFromNearest: { type: Boolean, default: true },
});

const emit = defineEmits(['insert']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

function humanize(name) {
    if (!name || typeof name !== 'string') return '';
    let lower = name.toLowerCase();
    if (lower === 'id') return 'ID';
    if (lower.endsWith('_id')) lower = lower.slice(0, -3);
    lower = lower.replace(/[_-]+/g, ' ');
    // Title case
    let label = lower.replace(/\b\w/g, (c) => c.toUpperCase());
    label = label.replace(/\bId\b/g, 'ID').replace(/\bUrl\b/g, 'URL');
    return label;
}

const dataSources = computed(() => {
    const sources = [];

    // Helper function to check if field is a List/Array
    const isListType = (field) => {
        const t = String(field?.type || '').toLowerCase();
        return t === 'array of objects' || t === 'array' || t === 'collection';
    };

    // Try to get loop schema either from prop or infer it from nearest FOR_EACH
    let loopSchema = props.loopContextSchema;

    if (!loopSchema && props.inferLoopFromNearest) {
        const forEach = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH' && s.step_config?.sourceArray);
        if (forEach) {
            const sourcePath = forEach.step_config.sourceArray;
            const match = typeof sourcePath === 'string' ? sourcePath.match(/{{step_(\w+)\.(.+)}}/) : null;
            if (match) {
                const sourceStepId = match[1];
                const sourceFieldName = match[2];
                const sourceStep = props.allStepsBefore.find(s => String(s.id) === String(sourceStepId));
                const cleanFieldName = sourceFieldName.replace(/^parsed\./, '');
                
                if (sourceStep?.step_type === 'AI_PROMPT' || (sourceStep?.step_type === 'ACTION' && sourceStep?.step_config?.action_type === 'FETCH_API_DATA')) {
                    const field = (sourceStep.step_config?.responseStructure || []).find(f => f.name === cleanFieldName);
                    if (isListType(field)) {
                        loopSchema = { name: 'Loop Item', columns: (field.schema || []) };
                    }
                }
                if (!loopSchema && sourceStep?.step_type === 'FETCH_RECORDS' && sourceFieldName === 'records') {
                    const modelName = sourceStep.step_config?.model;
                    const model = automationSchema.value.find(m => m.name === modelName);
                    if (model) {
                        const cols = (model.columns || []).map(col => typeof col === 'string' ? { name: col } : col);
                        loopSchema = { name: 'Loop Item', columns: cols };
                    }
                }
            }
        }
    }

    // If we are inside a loop, this is the most relevant context. Add it first.
    if (loopSchema && Array.isArray(loopSchema.columns)) {
        const loopItemFields = [
            // The complete object — useful for sending everything to an AI prompt
            { label: '⬤ Entire loop item (full object)', value: '{{loop.item}}' },
        ];

        // Individual named fields
        loopSchema.columns
            .filter(col => !!(col && col.name))
            .forEach(col => {
                loopItemFields.push({
                    label: col.name,
                    value: `{{loop.item.${col.name}}}`
                });
            });

        // Loop position metadata
        loopItemFields.push(
            { label: '— index', value: '{{loop.index}}' },
            { label: '— is_first', value: '{{loop.is_first}}' },
            { label: '— is_last', value: '{{loop.is_last}}' },
        );

        sources.push({
            name: 'Current Loop Item (from For Each)',
            fields: loopItemFields,
        });
    } else if (!loopSchema && props.loopContextSchema !== null) {
        // No schema known but we might still be inside a loop; expose generic loop tokens
        const genericLoopFields = [
            { label: '⬤ Entire loop item (full object)', value: '{{loop.item}}' },
            { label: '— index', value: '{{loop.index}}' },
            { label: '— is_first', value: '{{loop.is_first}}' },
            { label: '— is_last', value: '{{loop.is_last}}' },
        ];
        sources.push({
            name: 'Current Loop Item (from For Each)',
            fields: genericLoopFields,
        });
    }

    const triggerStep = props.allStepsBefore.find(s => s.step_type === 'TRIGGER');

    if (triggerStep && triggerStep.step_config?.model) {
        const modelSchema = automationSchema.value.find(m => m.name === triggerStep.step_config.model);
        if (modelSchema) {
            sources.push({
                name: `Trigger: ${triggerStep.step_config.model}`,
                fields: modelSchema.columns.map(col => {
                    const name = typeof col === 'string' ? col : (col.name || '');
                    const label = typeof col === 'string' ? humanize(col) : (col.label || col.name || '');
                    return {
                        label,
                        value: `{{trigger.${triggerStep.step_config.model.toLowerCase()}.${name}}}`
                    };
                })
            });
        }
    }

    props.allStepsBefore.forEach((step, index) => {
        const stepNameLabel = step.name || `Step ${index + 1}`;
        let stepTypeAbbr = step.step_type.split('_').pop().toLowerCase();
        if (step.step_type === 'AI_PROMPT') stepTypeAbbr = 'AI';
        else if (step.step_type === 'FETCH_RECORDS') stepTypeAbbr = 'Fetch';
        else if (step.step_type === 'ACTION' && step.step_config?.action_type === 'FETCH_API_DATA') stepTypeAbbr = 'API';
        const baseLabel = `${stepNameLabel} (${stepTypeAbbr})`;

        // Reusable recursive field collection
        const collectFields = (fieldsList, targetArr, parentPath = '', indentLevel = 0) => {
            (fieldsList || []).forEach(field => {
                if (!field?.name) return;
                const currentPath = parentPath ? `${parentPath}.${field.name}` : field.name;
                const indent = '  '.repeat(indentLevel);
                
                targetArr.push({
                    label: `${indent}${field.name}`,
                    value: `{{step_${step.id}.${currentPath}}}`
                });
                
                if (Array.isArray(field.schema)) {
                    if (isListType(field)) {
                        targetArr.push({
                            label: `${indent}${field.name} (Count)`,
                            value: `{{step_${step.id}.${currentPath}.count}}`
                        });
                    } else if (field.type === 'Object') {
                        collectFields(field.schema, targetArr, currentPath, indentLevel + 1);
                    }
                }
            });
        };

        if ((step.step_type === 'AI_PROMPT' || (step.step_type === 'ACTION' && step.step_config?.action_type === 'FETCH_API_DATA')) && step.step_config?.responseStructure?.length > 0) {
            const fields = [];
            collectFields(step.step_config.responseStructure, fields);
            sources.push({
                name: `${baseLabel}: Response`,
                fields,
            });
        }

        if (step.step_type === 'FETCH_RECORDS') {
            const fields = [
                { label: 'records (array)', value: `{{step_${step.id}.records}}` },
                { label: 'count', value: `{{step_${step.id}.count}}` },
            ];
            const modelName = step.step_config?.model;
            if (step.step_config?.single && modelName) {
                const model = automationSchema.value.find(m => m.name === modelName);
                if (model) {
                    (model.columns || []).forEach(col => {
                        const name = typeof col === 'string' ? col : (col.name || '');
                        const label = typeof col === 'string' ? humanize(col) : (col.label || col.name || '');
                        if (name) {
                            fields.push({ label: `record.${label}`, value: `{{step_${step.id}.record.${name}}}` });
                        }
                    });
                }
                fields.push({ label: 'record (object)', value: `{{step_${step.id}.record}}` });
            }
            sources.push({
                name: `${baseLabel}` + (modelName ? ` — ${modelName}` : ''),
                fields,
            });
        }

        if (step.step_type === 'TRANSFORM_CONTENT') {
            sources.push({
                name: `${baseLabel}: Output`,
                fields: [
                    { label: 'cleaned_body', value: `{{step_${step.id}.cleaned_body}}` },
                    { label: 'result', value: `{{step_${step.id}.result}}` },
                ],
            });
        }

        if (step.step_type === 'ACTION' && step.step_config?.action_type === 'CREATE_RECORD') {
            const modelName = step.step_config.target_model || '';
            sources.push({
                name: `${baseLabel}: Create Record` + (modelName ? ` — ${modelName}` : ''),
                fields: [
                    { label: 'new_record_id', value: `{{step_${step.id}.new_record_id}}` },
                    { label: 'id (legacy)', value: `{{step_${step.id}.id}}` },
                ],
            });
        }

        if (step.step_type === 'DEFINE_VARIABLE' && Array.isArray(step.step_config?.variables)) {
            const fields = step.step_config.variables
                .filter(v => !!v.name)
                .map(v => ({ label: v.name, value: `{{step_${step.id}.${v.name}}}` }));
            if (fields.length > 0) {
                sources.push({ name: `${baseLabel}: Variables`, fields });
            }
        }
    });

    return sources;
});

const hasAnyFields = computed(() => dataSources.value.some(g => Array.isArray(g.fields) && g.fields.length > 0));

function handlePick(token) {
    if (token) emit('insert', token);
}
</script>

<template>
    <DataTokenPicker
        :groups="dataSources"
        :disabled="!hasAnyFields"
        @select="handlePick"
    />
</template>
