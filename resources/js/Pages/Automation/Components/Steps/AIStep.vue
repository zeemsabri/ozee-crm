<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, XCircleIcon, TrashIcon } from 'lucide-vue-next';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

// --- COMPLETED COMPUTED PROPERTIES ---
const aiConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const triggerStep = computed(() => props.allStepsBefore.find(s => s.step_type === 'TRIGGER'));

const triggerSchema = computed(() => {
    if (!triggerStep.value || !triggerStep.value.step_config?.model) return null;
    return automationSchema.value.find(m => m.name === triggerStep.value.step_config.model);
});

const availableTriggerFields = computed(() => {
    if (!triggerSchema.value) return [];
    const selectedInputs = aiConfig.value.aiInputs || [];
    return (triggerSchema.value.columns || []).filter(col => !selectedInputs.includes(col.name || col));
});

// --- HANDLER FUNCTIONS ---
function handleConfigChange(key, value) {
    aiConfig.value = { ...aiConfig.value, [key]: value };
}
function handleAddInput(fieldName) {
    if (!fieldName) return;
    const currentInputs = aiConfig.value.aiInputs || [];
    if (!currentInputs.includes(fieldName)) {
        handleConfigChange('aiInputs', [...currentInputs, fieldName]);
    }
}
function handleRemoveInput(fieldName) {
    const currentInputs = aiConfig.value.aiInputs || [];
    handleConfigChange('aiInputs', currentInputs.filter(i => i !== fieldName));
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
                    <span class="text-sm font-medium text-indigo-700">{{ triggerStep.step_config.model }}: {{ input }}</span>
                    <button @click="handleRemoveInput(input)" class="text-gray-400 hover:text-red-500"><XCircleIcon class="h-4 w-4"/></button>
                </div>
                <select v-if="triggerSchema" @change="handleAddInput($event.target.value); $event.target.value='';" class="p-1.5 border border-gray-300 rounded-md bg-white w-full text-sm">
                    <option value="" disabled selected>+ Map data from trigger...</option>
                    <option v-for="field in availableTriggerFields" :key="field.name || field" :value="field.name || field">{{ field.label || field.name || field }}</option>
                </select>
            </div>
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
