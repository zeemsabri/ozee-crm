<script setup>
import { computed, watch } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
});

const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const actionTypes = [
    { value: 'SEND_EMAIL', label: 'Send Email' },
    { value: 'CREATE_RECORD', label: 'Create Record' },
    { value: 'UPDATE_RECORD', label: 'Update Record' },
    { value: 'CHECK_MILESTONE_COMPLETION', label: 'Check for Milestone Completion & Update' },
];

const actionConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

function handleConfigChange(key, value) {
    actionConfig.value = { ...actionConfig.value, [key]: value };
}

function handleActionTypeChange(newType) {
    actionConfig.value = { action_type: newType };
}

function insertToken(fieldName, token) {
    const currentValue = actionConfig.value[fieldName] || '';
    handleConfigChange(fieldName, `${currentValue}${token}`);
}

const availableModels = computed(() => automationSchema.value.map(m => m.name));

const modelOptions = computed(() => (automationSchema.value || []).map(m => ({ label: m.name, value: m.name })));

function humanize(name) {
    if (!name || typeof name !== 'string') return '';
    let lower = name.toLowerCase();
    if (lower === 'id') return 'ID';
    if (lower.endsWith('_id')) lower = lower.slice(0, -3);
    lower = lower.replace(/[_-]+/g, ' ');
    let label = lower.replace(/\b\w/g, (c) => c.toUpperCase());
    label = label.replace(/\bId\b/g, 'ID').replace(/\bUrl\b/g, 'URL');
    return label;
}

const selectedModel = computed(() => {
    if (!actionConfig.value.target_model) return null;
    return automationSchema.value.find(m => m.name === actionConfig.value.target_model) || null;
});

const columnsForSelectedModel = computed(() => {
    const model = selectedModel.value;
    if (!model) return [];
    return (model.columns || []).map(col => {
        if (typeof col === 'string') return { name: col, label: humanize(col), is_required: false };
        return { name: col.name, label: col.label || humanize(col.name), is_required: !!col.is_required };
    });
});

const requiredFields = computed(() => (selectedModel.value?.required_on_create || []));
const defaultsOnCreate = computed(() => selectedModel.value?.defaults_on_create || {});

function isRequiredColumn(name) {
    return requiredFields.value.includes(name);
}

function suggestTemplateFor(field) {
    const defaults = defaultsOnCreate.value || {};
    const model = actionConfig.value.target_model;
    const modelKey = model ? model.toLowerCase() : '';
    const ctxPath = `{{trigger.${modelKey}.${field}}}`;
    if (defaults[field] !== undefined && defaults[field] !== null) {
        return ctxPath; // prefer trigger inheritance when default present
    }
    if (field.endsWith('_id')) {
        return ctxPath;
    }
    return '';
}

function ensureRequiredPrefill() {
    const req = requiredFields.value || [];
    if (!req.length) return;
    const currentFields = Array.isArray(actionConfig.value.fields) ? [...actionConfig.value.fields] : [];
    const presentMap = new Map();
    currentFields.forEach(f => {
        const key = f.column || f.field || f.name;
        if (!key) return;
        presentMap.set(key, true);
    });
    const toAdd = [];
    for (const field of req) {
        if (!presentMap.has(field)) {
            toAdd.push({ column: field, value: suggestTemplateFor(field) });
        }
    }
    if (toAdd.length) {
        handleConfigChange('fields', [...currentFields, ...toAdd]);
    }
}

function onTargetModelChange(val) {
    handleConfigChange('target_model', val);
    // Seed minimum required fields
    ensureRequiredPrefill();
}

watch(() => actionConfig.value.target_model, (n, o) => {
    if (n && n !== o) {
        // When user changes model, recheck required seeds
        ensureRequiredPrefill();
    }
});

function addField() {
    const currentFields = actionConfig.value.fields || [];
    handleConfigChange('fields', [...currentFields, { column: '', value: '' }]);
}

function removeField(index) {
    const currentFields = actionConfig.value.fields || [];
    handleConfigChange('fields', currentFields.filter((_, i) => i !== index));
}

function updateField(index, key, value) {
    const currentFields = actionConfig.value.fields || [];
    const newFields = [...currentFields];
    newFields[index] = { ...newFields[index], [key]: value };
    handleConfigChange('fields', newFields);
}

function insertTokenForField(index, token) {
    const currentFields = actionConfig.value.fields || [];
    const currentFieldValue = currentFields[index].value || '';
    updateField(index, 'value', currentFieldValue + token);
}

const missingRequired = computed(() => {
    const req = requiredFields.value || [];
    if (!req.length) return [];
    const map = new Map();
    const fields = Array.isArray(actionConfig.value.fields) ? actionConfig.value.fields : [];
    fields.forEach(f => {
        const key = f.column || f.field || f.name;
        const val = (f.value ?? '').toString().trim();
        if (key && val) map.set(key, true);
    });
    return req.filter(r => !map.has(r));
});

function canRemove(index) {
    const fields = Array.isArray(actionConfig.value.fields) ? actionConfig.value.fields : [];
    const col = fields[index]?.column || fields[index]?.field || fields[index]?.name;
    if (!col) return true;
    if (!isRequiredColumn(col)) return true;
    // Count how many times this required column is mapped
    const count = fields.filter(f => (f.column || f.field || f.name) === col).length;
    return count > 1; // allow removal only if duplicate exists
}
</script>

<template>
    <StepCard icon="⚙️" title="Perform an Action" :onDelete="() => emit('delete')">
        <select
            :value="actionConfig.action_type || ''"
            @change="handleActionTypeChange($event.target.value)"
            class="p-2 border border-gray-300 rounded-md bg-white shadow-sm w-full text-sm"
        >
            <option value="" disabled>Select an action...</option>
            <option v-for="action in actionTypes" :key="action.value" :value="action.value">
                {{ action.label }}
            </option>
        </select>

        <div v-if="actionConfig.action_type" class="space-y-4 mt-4 border-t pt-4">

            <!-- == SEND EMAIL CONFIG (Restored & Compact) == -->
            <template v-if="actionConfig.action_type === 'SEND_EMAIL'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.to || ''" @input="handleConfigChange('to', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g., {{trigger.email.sender}}" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('to', $event)" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Subject</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.subject || ''" @input="handleConfigChange('subject', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g., AI says: {{step_2.category}}" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('subject', $event)" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Body</label>
                    <div class="relative">
                        <textarea rows="5" :value="actionConfig.body || ''" @input="handleConfigChange('body', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                        <div class="absolute top-2 right-2">
                            <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('body', $event)" />
                        </div>
                    </div>
                </div>
            </template>

            <!-- == CREATE/UPDATE RECORD CONFIG (Compact Layout) == -->
            <template v-if="actionConfig.action_type === 'CREATE_RECORD' || actionConfig.action_type === 'UPDATE_RECORD'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        {{ actionConfig.action_type === 'CREATE_RECORD' ? 'Model to Create' : 'Model to Update' }}
                    </label>
                    <SelectDropdown
                        :model-value="actionConfig.target_model || ''"
                        :options="modelOptions"
                        placeholder="Select model..."
                        @update:modelValue="onTargetModelChange"
                        class="w-full"
                    />
                </div>

                <div v-if="actionConfig.action_type === 'UPDATE_RECORD'">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Record ID</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.record_id || ''" @input="handleConfigChange('record_id', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g., {{trigger.task.id}}" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('record_id', $event)" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium text-gray-600">Fields to set</label>
                        <button @click="addField" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                            <PlusIcon class="h-3 w-3" /> Add
                        </button>
                    </div>

                    <div v-if="requiredFields.length" class="mb-2 text-[11px]">
                        <span class="text-gray-600">Required for {{ actionConfig.target_model }}:</span>
                        <span class="ml-1" v-for="(rf, i) in requiredFields" :key="rf">
                            <span class="px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700">{{ humanize(rf) }}</span>
                            <span v-if="i < requiredFields.length - 1">, </span>
                        </span>
                        <div v-if="missingRequired.length" class="mt-1 text-red-600">Missing: {{ missingRequired.map(humanize).join(', ') }}</div>
                    </div>

                    <div v-if="actionConfig.fields && actionConfig.fields.length > 0" class="space-y-2">
                        <div v-for="(field, index) in actionConfig.fields" :key="index" class="p-2 border rounded-md bg-gray-50/50">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex-1 flex items-center gap-2">
                                    <select :value="field.column" @change="updateField(index, 'column', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" :disabled="!actionConfig.target_model">
                                        <option value="" disabled>Field...</option>
                                        <option v-for="col in columnsForSelectedModel" :key="col.name" :value="col.name">{{ col.label }}</option>
                                    </select>
                                    <span v-if="isRequiredColumn(field.column)" class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 border border-amber-200 text-amber-700">Required</span>
                                </div>
                                <button @click="canRemove(index) && removeField(index)" :class="[canRemove(index) ? 'text-gray-400 hover:text-red-500 hover:bg-red-50' : 'text-gray-300 cursor-not-allowed']" class="p-1 rounded-full" :title="canRemove(index) ? 'Remove Field' : 'Cannot remove required field'">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <input :value="field.value" @input="updateField(index, 'value', $event.target.value)" type="text" class="w-full border rounded px-2 py-2 text-sm" placeholder="Value..." />
                                <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertTokenForField(index, $event)" />
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- == "SMART ACTION" PLACEHOLDER == -->
            <template v-if="actionConfig.action_type === 'CHECK_MILESTONE_COMPLETION'">
                <div class="p-3 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm rounded-r-md">
                    <p class="font-semibold">This is a smart action.</p>
                    <p>It will automatically use the context from the trigger to check if all tasks in the milestone are complete. If so, it will mark the milestone as completed.</p>
                </div>
            </template>

        </div>
    </StepCard>
</template>
