<script setup>
import { computed, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    // When this condition is inside a For Each, the parent Workflow passes the loop schema.
    // If not provided, we will infer it from the nearest FOR_EACH step.
    loopContextSchema: { type: Object, default: null },
});
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const conditionConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

// Infer loop schema from the nearest FOR_EACH if not explicitly provided
const effectiveLoopSchema = computed(() => {
    if (props.loopContextSchema && Array.isArray(props.loopContextSchema.columns) && props.loopContextSchema.columns.length > 0) {
        return props.loopContextSchema;
    }
    // Find nearest FOR_EACH with a sourceArray token like {{step_<id>.records}} or AI array
    const forEach = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH' && s.step_config?.sourceArray);
    if (!forEach) return null;
    const sourcePath = forEach.step_config.sourceArray;
    const match = typeof sourcePath === 'string' ? sourcePath.match(/{{\s*step_(\w+)\.(.+?)\s*}}/) : null;
    if (!match) return null;
    const sourceStepId = match[1];
    const sourceFieldName = match[2];
    const sourceStep = props.allStepsBefore.find(s => String(s.id) === String(sourceStepId));
    if (!sourceStep) return null;

    // Case: AI Array of Objects
    if (sourceStep.step_type === 'AI_PROMPT') {
        const field = (sourceStep.step_config?.responseStructure || []).find(f => f.name === sourceFieldName);
        if (field?.type === 'Array of Objects') {
            return { name: 'Loop Item', columns: field.schema || [] };
        }
        return null;
    }

    // Case: Fetch Records → records array
    if (sourceStep.step_type === 'FETCH_RECORDS' && sourceFieldName === 'records') {
        const modelName = sourceStep.step_config?.model;
        if (!modelName) return null;
        const model = automationSchema.value.find(m => m.name === modelName);
        if (!model) return null;
        const cols = (model.columns || []).map(col => typeof col === 'string' ? { name: col } : col);
        return { name: 'Loop Item', columns: cols };
    }
    return null;
});

onMounted(() => {
    if (!conditionConfig.value.sourceId) {
        // Prefer the current loop item if we can detect it; otherwise default to Trigger if present
        const hasLoop = !!(effectiveLoopSchema.value && Array.isArray(effectiveLoopSchema.value.columns) && effectiveLoopSchema.value.columns.length > 0);
        if (hasLoop) {
            handleConfigChange('sourceId', 'loop');
        } else if (props.allStepsBefore.some(s => s.step_type === 'TRIGGER')) {
            handleConfigChange('sourceId', 'trigger');
        }
    } else {
        // If user had a stale selection but we detect loop now, auto-switch to loop for clarity
        const hasLoop = !!(effectiveLoopSchema.value && Array.isArray(effectiveLoopSchema.value.columns) && effectiveLoopSchema.value.columns.length > 0);
        if (hasLoop && conditionConfig.value.sourceId !== 'loop') {
            handleConfigChange('sourceId', 'loop');
        }
    }
});

const availableDataSources = computed(() => {
    const sources = [];

    // Add current loop item first if available
    if (effectiveLoopSchema.value && Array.isArray(effectiveLoopSchema.value.columns) && effectiveLoopSchema.value.columns.length > 0) {
        const cols = effectiveLoopSchema.value.columns.map(col => {
            if (typeof col === 'string') return { name: col, type: 'Text' };
            return { name: col.name, type: col.type || 'Text' };
        });
        sources.push({
            id: 'loop',
            name: 'Current Loop Item (from For Each)',
            schema: { name: 'LoopItem', columns: cols },
        });
    }

    // Trigger source
    const triggerStep = props.allStepsBefore.find(s => s.step_type === 'TRIGGER');
    if (triggerStep && triggerStep.step_config?.model) {
        const modelSchema = automationSchema.value.find(m => m.name === triggerStep.step_config.model);
        if (modelSchema) {
            sources.push({
                id: 'trigger',
                name: `Trigger: ${triggerStep.step_config.model}`,
                schema: modelSchema,
            });
        }
    }

    // AI Response and Fetch Records sources
    props.allStepsBefore.forEach((s, index) => {
        if (s.step_type === 'AI_PROMPT' && s.step_config?.responseStructure?.length > 0) {
            sources.push({
                id: s.id,
                name: `Step ${index + 1}: AI Response`,
                schema: {
                    name: `Step_${s.id}_Response`,
                    columns: s.step_config.responseStructure.map(field => ({
                        name: field.name,
                        type: field.type === 'Array of Objects' ? 'Array' : field.type,
                        schema: field.schema,
                    })),
                }
            });
        }
        // Fetch Records sources (records array + count)
        if (s.step_type === 'FETCH_RECORDS') {
            // When inside or inferred loop, prefer the Current Loop Item; hide raw Fetch Records arrays to avoid confusion
            const hasLoop = !!(effectiveLoopSchema.value && Array.isArray(effectiveLoopSchema.value.columns) && effectiveLoopSchema.value.columns.length > 0);
            if (!hasLoop) {
                const modelName = s.step_config && s.step_config.model ? s.step_config.model : null;
                const label = `Step ${index + 1}: Fetch Records` + (modelName ? ` — ${modelName}` : '');
                sources.push({
                    id: `fetch_${s.id}`,
                    name: label,
                    schema: {
                        name: `Step_${s.id}_Fetch`,
                        columns: [
                            { name: 'records', type: 'Array' },
                            { name: 'count', type: 'Number' },
                        ],
                    }
                });
            }
        }
    });
    return sources;
});

function handleConfigChange(key, value) {
    const newConfig = { ...conditionConfig.value, [key]: value };
    if (key === 'sourceId') {
        delete newConfig.field;
        delete newConfig.operator;
        delete newConfig.value;
    }
    if (key === 'field') {
        delete newConfig.operator;
        delete newConfig.value;
    }
    conditionConfig.value = newConfig;
}

const selectedSource = computed(() => availableDataSources.value.find(s => s.id == conditionConfig.value.sourceId));

// Available fields for the selected source
const availableFields = computed(() => {
    if (!selectedSource.value?.schema?.columns) return [];
    // Handle both string arrays and object arrays
    return selectedSource.value.schema.columns.map(col => {
        if (typeof col === 'string') {
            return { name: col, label: col, type: 'Text' }; // Assume Text for simple strings
        }
        return { name: col.name, label: col.label || col.name, type: col.type || 'Text', allowed_values: col.allowed_values || null };
    });
});

const selectedFieldSchema = computed(() => {
    if (!conditionConfig.value.field) return null;
    return availableFields.value.find(c => c.name === conditionConfig.value.field);
});

const availableOperators = computed(() => {
    const type = selectedFieldSchema.value?.type;
    switch (type) {
        case 'Array':
            return [
                { value: 'not_empty', label: 'is not empty' },
                { value: 'empty', label: 'is empty' },
            ];
        case 'True/False':
            return [
                { value: '==', label: 'is' },
                { value: '!=', label: 'is not' },
            ];
        case 'Number':
            return [
                { value: '==', label: 'equals' },
                { value: '>', label: 'is greater than' },
                { value: '<', label: 'is less than' },
                { value: '>=', label: 'is on or after' },
                { value: '<=', label: 'is on or before' },
            ];
        case 'Date':
        case 'DateTime':
            return [
                { value: '==', label: 'is on' },
                { value: '>', label: 'is after' },
                { value: '<', label: 'is before' },
                { value: 'in_past', label: 'is in the past' },
                { value: 'in_future', label: 'is in the future' },
                { value: 'today', label: 'is today' },
            ];
        default:
            // Text (including enums/status where UI will provide dropdown)
            return [
                { value: '==', label: 'is' },
                { value: '!=', label: 'is not' },
                { value: 'contains', label: 'contains' },
            ];
    }
});

const operatorRequiresValue = computed(() => {
    const op = String(conditionConfig.value?.operator || '').toLowerCase();
    return !['', 'empty', 'not_empty', 'in_past', 'in_future', 'today'].includes(op);
});

const inputType = computed(() => {
    const t = selectedFieldSchema.value?.type;
    if (t === 'Date') return 'date';
    if (t === 'DateTime') return 'datetime-local';
    return 'text';
});
</script>

<template>
    <StepCard icon="🔀" title="If/Else Condition" :onDelete="() => emit('delete')">
        <div class="flex flex-col space-y-2 text-md p-2 bg-gray-50 rounded-md">
            <span class="font-semibold text-gray-700">If...</span>
            <div class="grid grid-cols-2 gap-2">
                <select :value="conditionConfig.sourceId || ''" @change="handleConfigChange('sourceId', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm col-span-2 text-sm">
                    <option value="" disabled>Select data source (Trigger, Current Loop Item, AI)...</option>
                    <option v-for="source in availableDataSources" :key="source.id" :value="source.id">{{ source.name }}</option>
                </select>

                <select v-if="selectedSource" :value="conditionConfig.field || ''" @change="handleConfigChange('field', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm col-span-2 text-sm">
                    <option value="" disabled>Select field...</option>
                    <option v-for="field in availableFields" :key="field.name" :value="field.name">
                        {{ field.label }} ({{ field.type }})
                    </option>
                </select>

                <template v-if="conditionConfig.field">
                    <select :value="conditionConfig.operator || ''" @change="handleConfigChange('operator', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm" :class="{ 'col-span-2': selectedFieldSchema?.type === 'Array' }">
                        <option value="" disabled>Select condition...</option>
                        <option v-for="op in availableOperators" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>

                    <!-- Enum/status dropdown -->
                    <SelectDropdown
                        v-if="selectedFieldSchema?.allowed_values && operatorRequiresValue"
                        :options="(selectedFieldSchema.allowed_values || []).map(o => ({ value: o.value, label: o.label }))"
                        :model-value="conditionConfig.value ?? null"
                        placeholder="Select value..."
                        @update:modelValue="val => handleConfigChange('value', val)"
                    />

                    <!-- Boolean dropdown -->
                    <select v-else-if="selectedFieldSchema?.type === 'True/False' && operatorRequiresValue" :value="conditionConfig.value ?? 'true'" @change="handleConfigChange('value', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm">
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>

                    <!-- Date/DateTime/Text input -->
                    <input v-else-if="selectedFieldSchema?.type !== 'Array' && operatorRequiresValue" :type="inputType" :value="conditionConfig.value || ''" @input="handleConfigChange('value', $event.target.value)" :placeholder="inputType === 'text' ? 'Value' : ''" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm"/>
                </template>
            </div>
        </div>
    </StepCard>
</template>
