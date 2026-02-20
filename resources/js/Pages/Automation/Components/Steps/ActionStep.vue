<script setup>
import { computed, watch, ref, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';
import { PlusIcon, TrashIcon, ClockIcon } from 'lucide-vue-next';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import axios from 'axios';
import RelationshipPathPicker from '@/Components/RelationshipPathPicker.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
});

const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

// Cache for per-field value dictionaries fetched from the backend
const dictCache = ref({}); // key: `${model}:${field}` -> [{value,label}]

function dictKey(model, field) {
    return `${model || ''}:${field || ''}`;
}

async function fetchDictionary(model, field) {
    if (!model || !field) return null;
    const key = dictKey(model, field);
    if (Array.isArray(dictCache.value[key]) && dictCache.value[key].length) {
        return dictCache.value[key];
    }
    try {
        const { data } = await axios.get(`/api/value-dictionaries/${encodeURIComponent(model)}/${encodeURIComponent(field)}`);
        let options = null;
        if (Array.isArray(data)) {
            options = data;
        } else if (data && Array.isArray(data.values)) {
            options = data.values;
        }
        if (Array.isArray(options) && options.length) {
            dictCache.value[key] = options;
            return options;
        }
    } catch (e) {
        // API is permission-protected; ignore failures and fall back to schema
        // console.debug('No dictionary for', model, field, e?.response?.status);
    }
    return null;
}

const actionTypes = [
    { value: 'SEND_EMAIL', label: 'Send Email' },
    { value: 'PROCESS_EMAIL', label: 'Process Email' },
    { value: 'CREATE_RECORD', label: 'Create Record' },
    { value: 'UPDATE_RECORD', label: 'Update Record' },
    { value: 'SYNC_RELATIONSHIP', label: 'Sync Relationship' },
    { value: 'FETCH_API_DATA', label: 'Fetch API Data' },
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
    const tm = actionConfig.value.target_model;
    if (!tm) return null;
    // Try exact name match
    let found = (automationSchema.value || []).find(m => m.name === tm);
    if (found) return found;
    // Try full_class match
    found = (automationSchema.value || []).find(m => m.full_class === tm);
    if (found) return found;
    // Try basename of provided value
    const base = typeof tm === 'string' ? tm.split('\\').pop().split('/').pop() : tm;
    return (automationSchema.value || []).find(m => m.name === base) || null;
});

const columnsForSelectedModel = computed(() => {
    const model = selectedModel.value;
    if (!model) return [];
    return (model.columns || []).map(col => {
        if (typeof col === 'string') return { name: col, label: humanize(col), is_required: false, allowed_values: null, description: null, ui: null };
        return {
            name: col.name,
            label: col.label || humanize(col.name),
            is_required: !!col.is_required,
            allowed_values: col.allowed_values ?? null,
            description: col.description || null,
            ui: col.ui || null,
        };
    });
});

function getColumnMeta(columnName) {
    return columnsForSelectedModel.value.find(c => c.name === columnName) || null;
}

function isMorphTypeColumn(name) {
    return typeof name === 'string' && name.endsWith('_type');
}
function isIdColumn(name) {
    return typeof name === 'string' && name.endsWith('_id');
}
function shouldOfferRelationshipPicker(field) {
    const col = getColumnMeta(field.column);
    return isIdColumn(field.column) || (isMorphTypeColumn(field.column) && (col?.ui === 'morph_type' || Array.isArray(col?.allowed_values)));
}
function getPickerMode(field) {
    return isMorphTypeColumn(field.column) ? 'type' : 'id';
}

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

    // Helper: does this field have allowed values (enum/model/options) from schema?
    const hasAllowedValues = (() => {
        const cols = columnsForSelectedModel.value || [];
        const col = cols.find(c => c.name === field);
        return !!(col && Array.isArray(col.allowed_values) && col.allowed_values.length > 0);
    })();

    // For CREATE_RECORD, avoid auto-prefilling any field that has allowed_values so the UI shows a dropdown
    // (e.g., status, task_type_id, type, etc.).
    if (actionConfig.value.action_type === 'CREATE_RECORD' && hasAllowedValues) {
        return '';
    }

    // Otherwise, prefer inheriting from trigger when a sensible default exists.
    if (defaults[field] !== undefined && defaults[field] !== null) {
        return ctxPath;
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
    // Prefetch dictionaries for required fields, so dropdowns render immediately when applicable
    const model = actionConfig.value.target_model;
    if (model) {
        for (const field of req) {
            fetchDictionary(model, field);
        }
    }
}

function onTargetModelChange(val) {
    handleConfigChange('target_model', val);
    // Seed minimum required fields
    ensureRequiredPrefill();
}

watch(() => actionConfig.value.target_model, async (n, o) => {
    if (n && n !== o) {
        // When user changes model, recheck required seeds
        ensureRequiredPrefill();
        // Prefetch dictionaries for already-selected fields under the new model
        const fields = Array.isArray(actionConfig.value.fields) ? actionConfig.value.fields : [];
        for (const f of fields) {
            const name = f?.column || f?.field || f?.name;
            if (name) await fetchDictionary(n, name);
        }
    }
});

// Prefetch dictionaries when fields change (including on initial hydration when editing)
watch(() => actionConfig.value.fields, async (newFields) => {
    const model = actionConfig.value.target_model;
    if (!model || !Array.isArray(newFields)) return;
    for (const f of newFields) {
        const name = f?.column || f?.field || f?.name;
        if (name) await fetchDictionary(model, name);
    }
}, { deep: true, immediate: true });

onMounted(async () => {
    // Initialize delay visibility
    if ((props.step.delay_minutes ?? 0) > 0) {
        showDelay.value = true;
    }
    
    // Prefetch dictionaries
    const model = actionConfig.value.target_model;
    const fields = Array.isArray(actionConfig.value.fields) ? actionConfig.value.fields : [];
    if (model) {
        for (const f of fields) {
            const name = f?.column || f?.field || f?.name;
            if (name) await fetchDictionary(model, name);
        }
    }
});

// Watch for changes to delay_minutes to keep showDelay in sync
watch(() => props.step.delay_minutes, (newVal) => {
    if ((newVal ?? 0) > 0 && !showDelay.value) {
        showDelay.value = true;
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

function getAllowedOptions(fieldRow) {
    const model = actionConfig.value.target_model;
    if (!model || !fieldRow) return null;
    const fieldName = fieldRow.column || fieldRow.field || fieldRow.name;
    if (!fieldName) return null;
    // 1) Prefer schema-provided allowed_values when available
    const fromSchema = (columnsForSelectedModel.value || []).find(c => c.name === fieldName)?.allowed_values;
    if (Array.isArray(fromSchema) && fromSchema.length) return fromSchema;
    // 2) Fall back to fetched dictionary
    const key = dictKey(model, fieldName);
    const fromCache = dictCache.value[key];
    if (Array.isArray(fromCache) && fromCache.length) return fromCache;
    return null;
}

function shouldShowSelect(fieldRow) {
    const options = getAllowedOptions(fieldRow);
    if (!options || !options.length) return false;
    const val = (fieldRow.value || '').toString();
    // If value is a token expression, keep text input to allow expressions
    if (val.includes('{{')) return false;
    return true;
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
// Delay UI state and updater
const showDelay = ref(false);

function updateDelayMinutes(val) {
    const minutes = Math.max(0, parseInt(val ?? 0, 10) || 0);
    emit('update:step', { ...props.step, delay_minutes: minutes });
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

        <div class="mt-2 flex items-center gap-2 text-xs text-gray-600">
            <button type="button" @click="showDelay = !showDelay" class="p-1.5 rounded-md border text-gray-600 hover:bg-gray-50" title="Set delay (minutes)">
                <ClockIcon class="w-4 h-4" />
            </button>
            <div v-if="showDelay" class="flex items-center gap-2">
                <label>Delay</label>
                <input type="number" min="0" :value="props.step.delay_minutes || 0" @input="updateDelayMinutes($event.target.value)" class="w-20 p-1 border rounded" />
                <span>minutes</span>
            </div>
        </div>

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

            <!-- == PROCESS EMAIL CONFIG == -->
            <template v-if="actionConfig.action_type === 'PROCESS_EMAIL'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Email ID</label>
                    <div class="flex items-center gap-2">
                        <input 
                            type="text" 
                            :value="actionConfig.email_id || ''" 
                            @input="handleConfigChange('email_id', $event.target.value)" 
                            class="w-full p-2 border border-gray-300 rounded-md text-sm" 
                            :placeholder="'e.g., ' + '{{email.id}}' + ' or ' + '{{step_3.new_record_id}}'"
                        />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('email_id', $event)" />
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Specify the ID of the email to process. Use the token inserter to select from available context data.
                    </p>
                </div>
                
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Queue (Optional)</label>
                    <input 
                        type="text" 
                        :value="actionConfig.on_queue || ''" 
                        @input="handleConfigChange('on_queue', $event.target.value)" 
                        class="w-full p-2 border border-gray-300 rounded-md text-sm" 
                        placeholder="e.g., emails, default" 
                    />
                    <p class="mt-1 text-[11px] text-gray-500">
                        Optional queue name for processing the email. Leave blank to use default queue.
                    </p>
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
                    <div class="mb-2">
                        <label class="text-xs font-medium text-gray-600">Fields to set</label>
                    </div>

                    <!-- Helpful Tips -->
                    <div class="mb-3 p-2 bg-blue-50 border-l-2 border-blue-400 text-[11px] text-gray-700">
                        <p class="font-medium text-blue-800 mb-1">💡 Quick Tips:</p>
                        <ul class="space-y-0.5 ml-3">
                            <li>• Use <code class="px-1 bg-white rounded">NOW()</code> for current timestamp (e.g., deleted_at, published_at)</li>
                            <li>• Use <code class="px-1 bg-white rounded">TODAY()</code> for current date at midnight</li>
                            <li>• Use <code class="px-1 bg-white rounded">NULL</code> to clear optional fields</li>
                            <li>• Use the <strong>token inserter (➕)</strong> to access data from triggers & previous steps</li>
                        </ul>
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
                                <!-- If the selected column has allowed_values (enum), render a select dropdown; otherwise, use text input. -->
                                <template v-if="actionConfig.target_model">
                                    <template v-if="shouldShowSelect(field)">
                                        <select :value="field.value || ''" @change="updateField(index, 'value', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                            <option value="" disabled>Select value...</option>
                                            <option v-for="opt in (getAllowedOptions(field) || [])" :key="opt.value" :value="opt.value">
                                                {{ opt.label ?? opt.value }}
                                            </option>
                                        </select>
                                    </template>
                                    <template v-else>
                                        <input :value="field.value" @input="updateField(index, 'value', $event.target.value)" type="text" class="w-full border rounded px-2 py-2 text-sm" placeholder="Value..." />
                                    </template>
                                </template>
                                <template v-else>
                                    <input :value="field.value" @input="updateField(index, 'value', $event.target.value)" type="text" class="w-full border rounded px-2 py-2 text-sm" placeholder="Value..." />
                                </template>
                                <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertTokenForField(index, $event)" />
                                <RelationshipPathPicker
                                    v-if="shouldOfferRelationshipPicker(field)"
                                    :mode="getPickerMode(field)"
                                    :all-steps-before="allStepsBefore"
                                    :value="field.value"
                                    @select="val => updateField(index, 'value', val)" />

                            </div>
                            <p class="mt-1 text-[14px] text-gray-500">Selected Relationship: <strong>{{ field.value }}</strong></p>
                            <p v-if="getColumnMeta(field.column)?.description" class="mt-1 text-[11px] text-gray-500">
                                {{ getColumnMeta(field.column).description }}
                            </p>
                        </div>
                    </div>

                    <!-- Add Field Button at Bottom -->
                    <div class="mt-3">
                        <button @click="addField" class="w-full flex items-center justify-center gap-1 px-3 py-2 text-sm rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200">
                            <PlusIcon class="h-4 w-4" /> Add Field
                        </button>
                    </div>
                </div>
            </template>

            <!-- == SYNC RELATIONSHIP CONFIG == -->
            <template v-if="actionConfig.action_type === 'SYNC_RELATIONSHIP'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Target Model</label>
                    <SelectDropdown
                        :model-value="actionConfig.target_model || ''"
                        :options="modelOptions"
                        placeholder="Select model..."
                        @update:modelValue="onTargetModelChange"
                        class="w-full"
                    />
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Record ID</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.record_id || ''" @input="handleConfigChange('record_id', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g., {{trigger.task.id}}" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('record_id', $event)" />
                    </div>
                </div>

                <div v-if="selectedModel">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Relationship</label>
                    <select 
                        :value="actionConfig.relationship || ''"
                        @change="handleConfigChange('relationship', $event.target.value)"
                        class="w-full p-2 border border-gray-300 rounded-md text-sm bg-white shadow-sm"
                    >
                        <option value="" disabled>Select relationship...</option>
                        <option v-for="rel in selectedModel.relationships.filter(r => ['BelongsToMany', 'MorphToMany'].includes(r.type))" :key="rel.name" :value="rel.name">
                            {{ humanize(rel.name) }} ({{ rel.type }})
                        </option>
                    </select>
                    <p v-if="actionConfig.relationship" class="mt-1 text-[11px] text-gray-500">
                        This will sync the many-to-many relationship "{{ actionConfig.relationship }}" on the selected {{ actionConfig.target_model }} record.
                    </p>
                </div>

                <div v-if="actionConfig.relationship">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sync Mode</label>
                    <select 
                        :value="actionConfig.sync_mode || 'sync'"
                        @change="handleConfigChange('sync_mode', $event.target.value)"
                        class="w-full p-2 border border-gray-300 rounded-md text-sm bg-white shadow-sm"
                    >
                        <option value="sync">Sync (Replace all with new IDs)</option>
                        <option value="attach">Attach (Add new IDs without removing existing)</option>
                        <option value="detach">Detach (Remove specified IDs)</option>
                    </select>
                </div>

                <div v-if="actionConfig.relationship">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Related Record IDs</label>
                    <div class="flex items-center gap-2">
                        <input 
                            type="text" 
                            :value="actionConfig.related_ids || ''" 
                            @input="handleConfigChange('related_ids', $event.target.value)" 
                            class="w-full p-2 border border-gray-300 rounded-md text-sm" 
                            :placeholder="'e.g., ' + '{{step_1.category_ids}}' + ' or 1,2,3'"
                        />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('related_ids', $event)" />
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Comma-separated list of IDs to sync/attach/detach. Can use tokens from previous steps.
                    </p>
                </div>
            </template>

            <!-- == FETCH API DATA CONFIG == -->
            <template v-if="actionConfig.action_type === 'FETCH_API_DATA'">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">API Endpoint URL</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.api_url || ''" @input="handleConfigChange('api_url', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="https://api.example.com/v1/data" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('api_url', $event)" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">HTTP Method</label>
                        <select :value="actionConfig.api_method || 'GET'" @change="handleConfigChange('api_method', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="PATCH">PATCH</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Authentication</label>
                        <select :value="actionConfig.api_auth_type || 'NONE'" @change="handleConfigChange('api_auth_type', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm">
                            <option value="NONE">None</option>
                            <option value="BEARER">Bearer Token</option>
                            <option value="BASIC">Basic Auth</option>
                            <option value="CUSTOM_HEADER">Custom Header</option>
                        </select>
                    </div>
                </div>

                <!-- Auth Specific Inputs -->
                <div v-if="actionConfig.api_auth_type === 'BEARER'" class="mt-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bearer Token</label>
                    <div class="flex items-center gap-2">
                        <input type="text" :value="actionConfig.api_auth_token || ''" @input="handleConfigChange('api_auth_token', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g. {{secrets.api_key}}" />
                        <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('api_auth_token', $event)" />
                    </div>
                </div>

                <div v-if="actionConfig.api_auth_type === 'BASIC'" class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Username</label>
                        <div class="flex items-center gap-2">
                            <input type="text" :value="actionConfig.api_auth_username || ''" @input="handleConfigChange('api_auth_username', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Username" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Password</label>
                        <div class="flex items-center gap-2">
                            <input type="password" :value="actionConfig.api_auth_password || ''" @input="handleConfigChange('api_auth_password', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Password" />
                        </div>
                    </div>
                </div>

                <div v-if="actionConfig.api_auth_type === 'CUSTOM_HEADER'" class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Header Name</label>
                        <input type="text" :value="actionConfig.api_auth_header_name || ''" @input="handleConfigChange('api_auth_header_name', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g. X-Api-Key" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Header Value</label>
                        <div class="flex items-center gap-2">
                            <input type="text" :value="actionConfig.api_auth_header_value || ''" @input="handleConfigChange('api_auth_header_value', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="Value" />
                            <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('api_auth_header_value', $event)" />
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payload / Parameters (JSON)</label>
                    <div class="relative">
                        <textarea rows="4" :value="actionConfig.api_payload || ''" @input="handleConfigChange('api_payload', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm font-mono" placeholder='{"key": "{{trigger.value}}", "id": 123}'></textarea>
                        <div class="absolute top-2 right-2 bg-white rounded-md shadow-sm border border-gray-200">
                            <DataTokenInserter :all-steps-before="allStepsBefore" :loop-context-schema="loopContextSchema" @insert="insertToken('api_payload', $event)" />
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">
                        For GET/DELETE requests, these will be sent as query string parameters. For POST/PUT/PATCH, they will be sent as JSON body. Variables are supported!
                    </p>
                </div>
                
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Response Data Key (Optional)</label>
                    <input type="text" :value="actionConfig.api_response_key || ''" @input="handleConfigChange('api_response_key', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" placeholder="e.g. data.items" />
                    <p class="mt-1 text-[11px] text-gray-500">
                        Optional dot-notation path to extract specific data from the JSON response before saving to workflow context. Leave blank to return the whole response object.
                    </p>
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
