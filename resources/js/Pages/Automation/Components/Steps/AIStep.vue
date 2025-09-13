<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, XCircleIcon, TrashIcon } from 'lucide-vue-next';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    // When inside a For Each, Workflow passes the loop item schema here
    loopContextSchema: { type: Object, default: null },
});
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);
const campaigns = computed(() => store.campaigns || []);

// --- COMPLETED COMPUTED PROPERTIES ---
const aiConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const triggerStep = computed(() => props.allStepsBefore.find(s => s.step_type === 'TRIGGER'));
const triggerModelName = computed(() => triggerStep.value?.step_config?.model || 'Trigger');

const triggerSchema = computed(() => {
    if (!triggerStep.value || !triggerStep.value.step_config?.model) return null;
    return automationSchema.value.find(m => m.name === triggerStep.value.step_config.model);
});

// Selected inputs are stored as strings. For backward compatibility, plain values
// (without a prefix) are treated as trigger fields. New values use prefixes:
//   'trigger:fieldName' or 'loop:fieldName'.
const selectedInputs = computed(() => Array.isArray(aiConfig.value.aiInputs) ? aiConfig.value.aiInputs : []);
const selectedTriggerFields = computed(() => selectedInputs.value
    .filter(v => typeof v === 'string' && (v.startsWith('trigger:') || !v.includes(':')))
    .map(v => v.startsWith('trigger:') ? v.slice('trigger:'.length) : v)
);
const selectedLoopFields = computed(() => selectedInputs.value
    .filter(v => typeof v === 'string' && v.startsWith('loop:'))
    .map(v => v.slice('loop:'.length))
);

const availableTriggerFields = computed(() => {
    if (!triggerSchema.value) return [];
    const taken = new Set(selectedTriggerFields.value);
    return (triggerSchema.value.columns || []).filter(col => !taken.has(col.name || col));
});

// Prefer explicit loopContextSchema; otherwise infer from nearest FOR_EACH like ConditionStep/DataTokenInserter
const effectiveLoopSchema = computed(() => {
    // If provided explicitly, use it
    if (props.loopContextSchema && Array.isArray(props.loopContextSchema.columns) && props.loopContextSchema.columns.length > 0) {
        return props.loopContextSchema;
    }
    // Infer from nearest FOR_EACH in allStepsBefore
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

    // Case: Fetch Records → records
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

const availableLoopFields = computed(() => {
    const schema = effectiveLoopSchema.value;
    if (!schema || !Array.isArray(schema.columns) || schema.columns.length === 0) return [];
    const taken = new Set(selectedLoopFields.value);
    return schema.columns
        .map(c => (typeof c === 'string' ? { name: c, label: c } : { name: c.name, label: c.label || c.name }))
        .filter(c => c.name && !taken.has(c.name));
});

// --- HANDLER FUNCTIONS ---
function handleConfigChange(key, value) {
    aiConfig.value = { ...aiConfig.value, [key]: value };
}
function handleAddInput(value, source = 'trigger') {
    if (!value) return;
    const current = selectedInputs.value;
    // If the provided value already contains a prefix, keep as-is; otherwise prefix
    const stored = value.includes(':') ? value : `${source}:${value}`;
    if (!current.includes(stored)) {
        handleConfigChange('aiInputs', [...current, stored]);
    }
}
function handleRemoveInput(storedValue) {
    const current = selectedInputs.value;
    handleConfigChange('aiInputs', current.filter(i => i !== storedValue));
}

// --- FIELD HANDLERS WITH NESTING SUPPORT ---
function handleAddField(parentField = null) {
    const newField = { id: Date.now(), name: '', type: 'Text' };
    if (parentField) {
        parentField.schema = [...(parentField.schema || []), newField];
        // Trigger reactivity for the parent change
        handleConfigChange('responseStructure', [...aiConfig.value.responseStructure]);
    } else {
        const currentStructure = aiConfig.value.responseStructure || [];
        handleConfigChange('responseStructure', [...currentStructure, newField]);
    }
}

function handleDeleteField(fieldId, parentField = null) {
    if (parentField) {
        parentField.schema = (parentField.schema || []).filter(f => f.id !== fieldId);
        handleConfigChange('responseStructure', [...aiConfig.value.responseStructure]);
    } else {
        const currentStructure = aiConfig.value.responseStructure || [];
        handleConfigChange('responseStructure', currentStructure.filter(f => f.id !== fieldId));
    }
}

function handleUpdateField(fieldId, key, value, parentField = null) {
    let structure = parentField ? parentField.schema || [] : aiConfig.value.responseStructure || [];

    const newStructure = structure.map(field => {
        if (field.id !== fieldId) return field;

        const updatedField = { ...field, [key]: value };
        if (key === 'type' && value === 'Array of Objects' && !updatedField.schema) {
            updatedField.schema = [];
        }
        return updatedField;
    });

    if (parentField) {
        parentField.schema = newStructure;
        handleConfigChange('responseStructure', [...aiConfig.value.responseStructure]);
    } else {
        handleConfigChange('responseStructure', newStructure);
    }
}

const generatedJsonPrompt = computed(() => {
    const structure = aiConfig.value.responseStructure || [];
    if (structure.length === 0) return "";

    const generateSchemaString = (fields) => {
        return (fields || [])
            .filter(f => f.name)
            .map(f => {
                if (f.type === 'Array of Objects') {
                    return `"${f.name}": [{ ${generateSchemaString(f.schema || [])} }]`;
                }
                return `"${f.name}": <${f.type.toLowerCase()}>`;
            })
            .join(', ');
    };

    return `Respond with JSON in this exact format: { ${generateSchemaString(structure)} }`;
});
</script>

<template>
    <StepCard icon="🧠" title="Analyze with AI" :onDelete="() => emit('delete')">
        <div>
            <label class="block text-sm font-medium text-gray-700">System Prompt</label>
            <textarea
                rows="4"
                class="w-full p-2 mt-1 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                placeholder="e.g., You are a support ticket analyst."
                :value="aiConfig.prompt || ''"
                @input="handleConfigChange('prompt', $event.target.value)"
            />
            <p v-if="generatedJsonPrompt" class="text-xs text-gray-500 mt-1 italic">{{ generatedJsonPrompt }}</p>
        </div>

        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-700">Data to Analyze</h4>
            <div class="p-2 bg-gray-50 rounded-md border space-y-2">
                <div v-for="input in (aiConfig.aiInputs || [])" :key="input" class="flex items-center justify-between bg-white p-1 rounded border">
                    <span class="text-sm font-medium text-indigo-700">
                        <template v-if="(typeof input === 'string') && input.startsWith('loop:')">
                            Current Loop Item: {{ input.slice('loop:'.length) }}
                        </template>
                        <template v-else>
                            {{ triggerModelName }}: {{ (typeof input === 'string' && input.startsWith('trigger:')) ? input.slice('trigger:'.length) : input }}
                        </template>
                    </span>
                    <button @click="handleRemoveInput(input)" class="text-gray-400 hover:text-red-500"><XCircleIcon class="h-4 w-4"/></button>
                </div>
                <!-- Trigger fields selector (if available) -->
                <SelectDropdown
                    v-if="triggerSchema"
                    :options="(availableTriggerFields || []).map(f => ({ value: `trigger:${(f.name || f)}`, label: (f.label || f.name || f) }))"
                    :model-value="null"
                    placeholder="+ Map data from trigger..."
                    @update:modelValue="(val) => handleAddInput(val, 'trigger')"
                />
                <!-- Loop item fields selector (if inside a For Each) -->
                <SelectDropdown
                    v-if="availableLoopFields.length > 0"
                    :options="availableLoopFields.map(f => ({ value: `loop:${f.name}`, label: f.label || f.name }))"
                    :model-value="null"
                    placeholder="+ Map data from current loop item..."
                    @update:modelValue="(val) => handleAddInput(val, 'loop')"
                />
            </div>
        </div>

        <!-- NEW: Campaign Context Selector -->
        <div class="border-t pt-3 mt-3">
            <label class="block text-sm font-medium text-gray-700">Campaign Context (Optional)</label>
            <select
                :value="aiConfig.campaign_id || ''"
                @change="handleConfigChange('campaign_id', $event.target.value)"
                class="w-full p-2 mt-1 border rounded-md text-sm"
            >
                <option value="">None</option>
                <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                    {{ campaign.name }}
                </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
                Data from the selected campaign will be available to the AI.
            </p>
        </div>

        <div class="space-y-2">
            <h4 class="text-sm font-medium text-gray-700">Define AI Response Structure</h4>
            <div v-for="field in (aiConfig.responseStructure || [])" :key="field.id" class="p-2 border rounded-md bg-gray-50/50">
                <div class="flex items-center space-x-2">
                    <input type="text" placeholder="Field Name" :value="field.name" @input="handleUpdateField(field.id, 'name', $event.target.value)" class="p-1.5 border border-gray-300 rounded-md w-full text-sm"/>
                    <select :value="field.type" @change="handleUpdateField(field.id, 'type', $event.target.value)" class="p-1.5 border border-gray-300 rounded-md bg-white text-sm">
                        <option>Text</option>
                        <option>Number</option>
                        <option>True/False</option>
                        <option>Array of Objects</option>
                    </select>
                    <button @click="handleDeleteField(field.id)" class="text-gray-400 hover:text-red-500 p-1"><TrashIcon class="h-4 w-4" /></button>
                </div>
                <div v-if="field.type === 'Array of Objects'" class="ml-4 mt-2 pt-2 border-l-2 pl-4 space-y-2">
                    <div v-for="subField in (field.schema || [])" :key="subField.id" class="flex items-center space-x-2">
                        <input type="text" placeholder="Sub-field Name" :value="subField.name" @input="handleUpdateField(subField.id, 'name', $event.target.value, field)" class="p-1.5 border border-gray-300 rounded-md w-full text-sm"/>
                        <select :value="subField.type" @change="handleUpdateField(subField.id, 'type', $event.target.value, field)" class="p-1.5 border border-gray-300 rounded-md bg-white text-sm">
                            <option>Text</option><option>Number</option><option>True/False</option>
                        </select>
                        <button @click="handleDeleteField(subField.id, field)" class="text-gray-400 hover:text-red-500 p-1"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                    <button @click="handleAddField(field)" class="text-xs flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add Sub-field</button>
                </div>
            </div>
            <button @click="handleAddField()" class="text-xs flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add Field</button>
        </div>
    </StepCard>
</template>
