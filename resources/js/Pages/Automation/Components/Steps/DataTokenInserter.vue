<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';

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
                    if (field?.type === 'Array of Objects') {
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
            // Include top-level fields and, for Array of Objects, their sub-fields as indented entries
            const fields = [];
            (step.step_config.responseStructure || []).forEach(field => {
                // Always include the top-level field
                fields.push({
                    label: field.name,
                    value: `{{step_${step.id}.${field.name}}}`
                });
                // If it's an Array of Objects, also list its sub-fields
                if (field.type === 'Array of Objects' && Array.isArray(field.schema)) {
                    field.schema.forEach(sub => {
                        if (!sub?.name) return;
                        fields.push({
                            label: `- ${sub.name}`,
                            value: `{{step_${step.id}.${field.name}.${sub.name}}}`
                        });
                    });
                }
            });
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
            sources.push({
                name: groupLabel,
                fields,
            });
        }
    });

    return sources;
});

function handleInsert(event) {
    const token = event.target.value;
    if (token) {
        emit('insert', token);
        event.target.value = "";
    }
}
</script>

<template>
    <select
        @change="handleInsert"
        class="p-1 border border-gray-300 rounded-md bg-white text-xs focus:ring-indigo-500 focus:border-indigo-500"
    >
        <option value="" disabled selected>+ Insert Data</option>
        <template v-for="source in dataSources" :key="source.name">
            <optgroup :label="source.name">
                <option v-for="field in source.fields" :key="field.value" :value="field.value">
                    {{ field.label }}
                </option>
            </optgroup>
        </template>
    </select>
</template>
