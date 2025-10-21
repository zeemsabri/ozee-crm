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

    // Try to get loop schema either from prop or infer it from nearest FOR_EACH
    let loopSchema = props.loopContextSchema;

    const isArrayOfObjects = (field) => {
        const t = String(field?.type || '').toLowerCase();
        const it = String(field?.itemType || '').toLowerCase();
        return t === 'array of objects' || (t === 'array' && it === 'object');
    };

    if (!loopSchema && props.inferLoopFromNearest) {
        const forEach = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH' && s.step_config?.sourceArray);
        if (forEach) {
            const sourcePath = forEach.step_config.sourceArray;
            const match = typeof sourcePath === 'string' ? sourcePath.match(/{{step_(\w+)\.(.+)}}/) : null;
            if (match) {
                const sourceStepId = match[1];
                const sourceFieldName = match[2];
                const sourceStep = props.allStepsBefore.find(s => String(s.id) === String(sourceStepId));
                if (sourceStep?.step_type === 'AI_PROMPT') {
                    const field = (sourceStep.step_config?.responseStructure || []).find(f => f.name === sourceFieldName);
                    if (isArrayOfObjects(field)) {
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
        sources.push({
            name: 'Current Loop Item (from For Each)',
            fields: loopSchema.columns
                .filter(col => !!(col && col.name))
                .map(col => ({
                    label: col.name,
                    value: `{{loop.item.${col.name}}}`
                }))
        });
        // Also expose loop metadata
        sources.push({
            name: 'Loop Details',
            fields: [
                { label: 'index', value: '{{loop.index}}' },
                { label: 'is_first', value: '{{loop.is_first}}' },
                { label: 'is_last', value: '{{loop.is_last}}' },
            ]
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
        if (step.step_type === 'AI_PROMPT' && step.step_config?.responseStructure?.length > 0) {
            // Include top-level fields and their nested sub-fields
            const fields = [];
            
            const addFieldsRecursively = (fieldsList, parentPath = '', indentLevel = 0) => {
                (fieldsList || []).forEach(field => {
                    if (!field?.name) return;
                    
                    const currentPath = parentPath ? `${parentPath}.${field.name}` : field.name;
                    const indent = '  '.repeat(indentLevel);
                    
                    // Always include the current field
                    fields.push({
                        label: `${indent}${field.name}`,
                        value: `{{step_${step.id}.${currentPath}}}`
                    });
                    
                    // Handle nested fields based on field type
                    if (Array.isArray(field.schema)) {
                        if (isArrayOfObjects(field)) {
                            // Array of Objects: show nested fields with array access notation
                            field.schema.forEach(sub => {
                                if (!sub?.name) return;
                                fields.push({
                                    label: `${indent}  - ${sub.name} (from array item)`,
                                    value: `{{step_${step.id}.${currentPath}.${sub.name}}}`
                                });
                            });
                        } else if (field.type === 'Object') {
                            // Regular Object: show nested fields with direct access
                            addFieldsRecursively(field.schema, currentPath, indentLevel + 1);
                        }
                    }
                });
            };
            
            addFieldsRecursively(step.step_config.responseStructure);
            sources.push({
                name: `Step ${index + 1}: AI Response`,
                fields,
            });
        }

        // Include outputs from Fetch Records steps
        if (step.step_type === 'FETCH_RECORDS') {
            const fields = [
                { label: 'records (array)', value: `{{step_${step.id}.records}}` },
                { label: 'count', value: `{{step_${step.id}.count}}` },
            ];
            const modelName = step.step_config && step.step_config.model ? step.step_config.model : null;
            const groupLabel = `Step ${index + 1}: Fetch Records` + (modelName ? ` — ${modelName}` : '');

            // If this Fetch step is configured to return a single record, expose its fields
            if (step.step_config && step.step_config.single && modelName) {
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
                // Also expose the whole record object (useful for JSON serialization)
                fields.push({ label: 'record (object)', value: `{{step_${step.id}.record}}` });
            }

            sources.push({
                name: groupLabel,
                fields,
            });
        }

        // Include outputs from Transform Content steps
        if (step.step_type === 'TRANSFORM_CONTENT') {
            const fields = [
                { label: 'cleaned_body', value: `{{step_${step.id}.cleaned_body}}` },
                { label: 'result', value: `{{step_${step.id}.result}}` },
            ];
            sources.push({
                name: `Step ${index + 1}: Transformed Content`,
                fields,
            });
        }

        // Include outputs from Create Record actions
        if (step.step_type === 'ACTION' && step.step_config && step.step_config.action_type === 'CREATE_RECORD') {
            const modelName = step.step_config.target_model || '';
            const groupLabel = `Step ${index + 1}: Create Record` + (modelName ? ` — ${modelName}` : '');
            const fields = [
                { label: 'new_record_id', value: `{{step_${step.id}.new_record_id}}` },
                // legacy id for backward compatibility
                { label: 'id (legacy)', value: `{{step_${step.id}.id}}` },
            ];
            sources.push({
                name: groupLabel,
                fields,
            });
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
