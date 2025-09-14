<script setup>
import { computed, watch, ref, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, XCircleIcon, TrashIcon } from 'lucide-vue-next';
import RelatedDataPicker from './RelatedDataPicker.vue';
import OverlayMultiSelect from '@/Components/OverlayMultiSelect.vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import PromptForm from '@/Pages/Automation/Prompts/PromptForm.vue';
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

// Prompts for selection / create-edit
const prompts = computed(() => store.prompts || []);
const sortedPrompts = computed(() => {
    const arr = Array.isArray(prompts.value) ? [...prompts.value] : [];
    arr.sort((a, b) => {
        const byName = (a.name || '').localeCompare(b.name || '');
        if (byName !== 0) return byName;
        return (b.version || 0) - (a.version || 0);
    });
    return arr;
});

const promptOptions = computed(() => {
    return sortedPrompts.value.map(p => ({ value: p.id, label: `${p.name} (v${p.version})` }));
});

const selectedPrompt = computed(() => {
    const id = aiConfig.value?.promptRef?.id;
    if (!id) return null;
    return sortedPrompts.value.find(pr => String(pr.id) === String(id)) || null;
});

onMounted(() => {
    if (!store.prompts.length) {
        store.fetchPrompts();
    }
});

const selectedPromptId = computed({
    get: () => aiConfig.value?.promptRef?.id || null,
    set: (id) => {
        const p = sortedPrompts.value.find(pr => String(pr.id) === String(id)) || null;
        if (p) {
            handleConfigChange('promptRef', { id: p.id, name: p.name, version: p.version });
        } else {
            handleConfigChange('promptRef', null);
        }
    }
});

const showPromptModal = ref(false);
const promptModalMode = ref('create'); // 'create' | 'edit'
const modalPrompt = ref(null);

function openCreatePrompt() {
    promptModalMode.value = 'create';
    modalPrompt.value = {
        name: 'New Prompt',
        category: 'General',
        version: 1,
        system_prompt_text: "You are a helpful AI assistant.\n\nUse the provided template variables like {{example_variable}} to craft your response.",
        model_name: 'gemini-2.5-flash-preview-05-20',
        generation_config: { temperature: 0.7, maxOutputTokens: 2048, responseMimeType: 'application/json' },
        template_variables: ['example_variable'],
        status: 'draft'
    };
    showPromptModal.value = true;
}

function openEditPrompt() {
    const id = selectedPromptId.value;
    const p = sortedPrompts.value.find(pr => pr.id === id);
    if (!p) return;
    promptModalMode.value = 'edit';
    modalPrompt.value = JSON.parse(JSON.stringify(p));
    showPromptModal.value = true;
}

async function handlePromptModalSave(promptToSave) {
    try {
        let saved;
        if (promptToSave.id && !promptToSave.isNewVersion) {
            saved = await store.updatePrompt(promptToSave.id, promptToSave);
        } else {
            const { id, isNewVersion, ...payload } = promptToSave;
            saved = await store.createPrompt(payload);
        }
        if (saved && saved.id) {
            selectedPromptId.value = saved.id;
        }
        showPromptModal.value = false;
        modalPrompt.value = null;
    } catch (e) {
        console.error('Failed to save prompt from modal', e);
    }
}

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
        return { name: 'Loop Item', columns: cols, modelName };
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

// Combined options for OverlayMultiSelect: Trigger fields and Current Loop Item fields
const dataToAnalyzeOptions = computed(() => {
    const opts = [];
    if (triggerSchema.value) {
        const modelLabel = triggerModelName.value || 'Trigger';
        (triggerSchema.value.columns || []).forEach(f => {
            const name = typeof f === 'string' ? f : (f.name || '');
            const label = typeof f === 'string' ? f : (f.label || f.name || '');
            if (name) opts.push({ value: `trigger:${name}`, label: `Trigger: ${modelLabel} — ${label}` });
        });
    }
    if (availableLoopFields.value.length > 0) {
        availableLoopFields.value.forEach(f => {
            const name = f.name;
            const label = f.label || f.name;
            if (name) opts.push({ value: `loop:${name}`, label: `Current Loop Item — ${label}` });
        });
    }
    return opts;
});

// Bridge current config to the multi-select's value format (prefixing legacy trigger fields)
const overlaySelected = computed({
    get: () => {
        const sel = Array.isArray(aiConfig.value.aiInputs) ? aiConfig.value.aiInputs : [];
        return sel.map(v => (typeof v === 'string' && v.includes(':')) ? v : `trigger:${v}`);
    },
    set: (arr) => {
        handleConfigChange('aiInputs', Array.isArray(arr) ? arr : []);
    },
});

const isAllInputsSelected = computed(() => {
    const total = dataToAnalyzeOptions.value.length;
    const selected = new Set(overlaySelected.value || []);
    const allValues = new Set(dataToAnalyzeOptions.value.map(o => o.value));
    let count = 0; allValues.forEach(v => { if (selected.has(v)) count++; });
    return total > 0 && count === total;
});
function toggleSelectAllInputs() {
    if (isAllInputsSelected.value) {
        handleConfigChange('aiInputs', []);
    } else {
        handleConfigChange('aiInputs', dataToAnalyzeOptions.value.map(o => o.value));
    }
}

// ---- Relationship builder (dynamic, based on base model) ----
const baseModelName = computed(() => {
    return effectiveLoopSchema.value?.modelName || triggerStep.value?.step_config?.model || null;
});

const baseModelSchema = computed(() => {
    return baseModelName.value ? automationSchema.value.find(m => m.name === baseModelName.value) : null;
});

const relationsConfig = computed({
    get: () => aiConfig.value.relationships || { base_model: baseModelName.value || null, roots: [], nested: {}, fields: {} },
    set: (val) => handleConfigChange('relationships', val),
});

watch(baseModelName, (val) => {
    const cur = relationsConfig.value || {};
    if (cur.base_model !== val) {
        relationsConfig.value = { ...cur, base_model: val };
    }
}, { immediate: true });

function getModelSchemaByName(name) {
    return automationSchema.value.find(m => m.name === name) || null;
}

const rootRelationships = computed(() => (baseModelSchema.value?.relationships || []));

function isRootSelected(name) {
    return Array.isArray(relationsConfig.value.roots) && relationsConfig.value.roots.includes(name);
}
function toggleRoot(name) {
    const roots = Array.isArray(relationsConfig.value.roots) ? [...relationsConfig.value.roots] : [];
    const idx = roots.indexOf(name);
    if (idx >= 0) {
        roots.splice(idx, 1);
        // cleanup nested + fields under this path
        const nested = { ...(relationsConfig.value.nested || {}) };
        delete nested[name];
        const fields = { ...(relationsConfig.value.fields || {}) };
        Object.keys(fields).forEach(p => { if (p === name || p.startsWith(name + '.')) delete fields[p]; });
        relationsConfig.value = { ...relationsConfig.value, roots, nested, fields };
    } else {
        roots.push(name);
        relationsConfig.value = { ...relationsConfig.value, roots };
    }
}

function getRelatedModelNameForRoot(rootName) {
    const rel = (rootRelationships.value || []).find(r => r.name === rootName);
    return rel ? rel.model : null;
}

function fieldOptionsForModel(modelName) {
    const schema = getModelSchemaByName(modelName);
    const cols = (schema?.columns || []);
    return cols.map(c => ({ value: (typeof c === 'string' ? c : c.name), label: (typeof c === 'string' ? c : (c.label || c.name)) }));
}

function selectedFieldsForPath(path) {
    const f = relationsConfig.value.fields || {};
    return f[path] || [];
}

function isAllFieldsSelected(path) {
    const sel = selectedFieldsForPath(path);
    return Array.isArray(sel) && sel.includes('*');
}

function toggleSelectAll(path, targetModelName) {
    const fields = { ...(relationsConfig.value.fields || {}) };
    if (isAllFieldsSelected(path)) {
        // remove select all
        delete fields[path];
    } else {
        fields[path] = ['*'];
    }
    relationsConfig.value = { ...relationsConfig.value, fields };
}

function toggleField(path, field) {
    const fields = { ...(relationsConfig.value.fields || {}) };
    const current = Array.isArray(fields[path]) ? [...fields[path]] : [];
    // if currently select all, reset to empty then add specific
    const idxStar = current.indexOf('*');
    if (idxStar >= 0) current.splice(idxStar, 1);
    const idx = current.indexOf(field);
    if (idx >= 0) current.splice(idx, 1); else current.push(field);
    if (current.length === 0) delete fields[path]; else fields[path] = current;
    relationsConfig.value = { ...relationsConfig.value, fields };
}

function nestedRelationshipsForRoot(rootName) {
    const childModel = getRelatedModelNameForRoot(rootName);
    const childSchema = childModel ? getModelSchemaByName(childModel) : null;
    return childSchema?.relationships || [];
}

function isNestedSelected(rootName, childName) {
    const nestedForRoot = relationsConfig.value.nested?.[rootName] || [];
    return nestedForRoot.includes(childName);
}
function toggleNested(rootName, childName) {
    const nested = { ...(relationsConfig.value.nested || {}) };
    const arr = Array.isArray(nested[rootName]) ? [...nested[rootName]] : [];
    const idx = arr.indexOf(childName);
    if (idx >= 0) {
        arr.splice(idx, 1);
        // cleanup fields for this path
        const full = `${rootName}.${childName}`;
        const fields = { ...(relationsConfig.value.fields || {}) };
        Object.keys(fields).forEach(p => { if (p === full || p.startsWith(full + '.')) delete fields[p]; });
        nested[rootName] = arr;
        relationsConfig.value = { ...relationsConfig.value, nested, fields };
    } else {
        arr.push(childName);
        nested[rootName] = arr;
        relationsConfig.value = { ...relationsConfig.value, nested };
    }
}

function getNestedModelName(rootName, childName) {
    const first = getRelatedModelNameForRoot(rootName);
    const schema = first ? getModelSchemaByName(first) : null;
    const rel = schema?.relationships?.find(r => r.name === childName);
    return rel ? rel.model : null;
}

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
const showRelatedPicker = ref(false);
</script>

<template>
    <StepCard icon="🧠" title="Analyze with AI" :onDelete="() => emit('delete')">
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700">Prompt</label>
            <div class="flex gap-2 mt-1 items-center">
                <div class="flex-1">
                    <SelectDropdown :options="promptOptions" v-model="selectedPromptId" placeholder="— Select a prompt —" />
                </div>
                <button type="button" @click="openCreatePrompt" class="px-2 py-1 text-xs font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Create</button>
                <button type="button" @click="openEditPrompt" :disabled="!selectedPromptId" class="px-2 py-1 text-xs font-semibold bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">Edit</button>
            </div>
            <p v-if="selectedPrompt" class="text-[11px] text-gray-500 mt-1">Linked: {{ selectedPrompt.name }} (v{{ selectedPrompt.version }})</p>
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
                <div class="flex items-center justify-between">
                    <label class="text-xs inline-flex items-center gap-1">
                        <input type="checkbox" :checked="isAllInputsSelected" @change="toggleSelectAllInputs" class="rounded border-gray-300" />
                        Select all
                    </label>
                </div>
                <OverlayMultiSelect
                    :options="dataToAnalyzeOptions"
                    :model-value="overlaySelected"
                    placeholder="Select fields from Trigger and/or Current Loop Item..."
                    @update:modelValue="(arr) => overlaySelected = arr"
                />
            </div>
        </div>

        <!-- Related Data Builder (moved into a modal for cleaner UX) -->
        <div class="border-t pt-3 mt-3" v-if="baseModelSchema">
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700">Include Related Data from {{ baseModelName }}</label>
                <button @click="showRelatedPicker = true" type="button" class="text-xs px-2 py-1 rounded-md bg-white ring-1 ring-gray-300 hover:bg-gray-50">Choose…</button>
            </div>
            <div v-if="relationsConfig && ((relationsConfig.roots && relationsConfig.roots.length) || (Object.keys(relationsConfig.fields || {}).length))" class="mt-2 text-xs text-gray-700">
                <p class="font-medium">Summary</p>
                <ul class="list-disc ml-5 space-y-0.5">
                    <li v-for="r in (relationsConfig.roots || [])" :key="'sum-'+r">
                        {{ r }}
                        <template v-if="relationsConfig.fields && relationsConfig.fields[r] && relationsConfig.fields[r].length && !relationsConfig.fields[r].includes('*')">
                            — fields: {{ relationsConfig.fields[r].join(', ') }}
                        </template>
                        <template v-if="relationsConfig.nested && relationsConfig.nested[r] && relationsConfig.nested[r].length">
                            — also: {{ relationsConfig.nested[r].map(n => r + '.' + n).join(', ') }}
                        </template>
                    </li>
                </ul>
                <p class="text-[11px] text-gray-500 mt-1">Selected related records will be available in your prompt under the with key (e.g., with.campaign, with.campaign.leads).</p>
            </div>
            <RelatedDataPicker
                :show="showRelatedPicker"
                :base-model-name="baseModelName"
                v-model="relationsConfig"
                @close="showRelatedPicker = false"
            />
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

    <BaseFormModal
        :show="showPromptModal"
        :title="promptModalMode === 'create' ? 'Create Prompt' : 'Edit Prompt'"
        :formData="modalPrompt || {}"
        :showFooter="false"
        @close="showPromptModal = false"
    >
        <PromptForm v-if="modalPrompt" :prompt="modalPrompt" @save="handlePromptModalSave" @cancel="showPromptModal = false; modalPrompt = null" />
    </BaseFormModal>
</template>
