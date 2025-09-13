<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';

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

const columnsForSelectedModel = computed(() => {
    if (!actionConfig.value.target_model) return [];
    const model = automationSchema.value.find(m => m.name === actionConfig.value.target_model);
    return model ? model.columns.map(col => typeof col === 'string' ? col : col.name) : [];
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
                    <select :value="actionConfig.target_model || ''" @change="handleConfigChange('target_model', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                        <option value="" disabled>Select model...</option>
                        <option v-for="model in availableModels" :key="model" :value="model">{{ model }}</option>
                    </select>
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
                    <div v-if="actionConfig.fields && actionConfig.fields.length > 0" class="space-y-2">
                        <div v-for="(field, index) in actionConfig.fields" :key="index" class="p-2 border rounded-md bg-gray-50/50">
                            <div class="flex items-center justify-between gap-2">
                                <select :value="field.column" @change="updateField(index, 'column', $event.target.value)" class="w-full p-2 border border-gray-300 rounded-md text-sm" :disabled="!actionConfig.target_model">
                                    <option value="" disabled>Field...</option>
                                    <option v-for="col in columnsForSelectedModel" :key="col" :value="col">{{ col }}</option>
                                </select>
                                <button @click="removeField(index)" class="text-gray-400 hover:text-red-500 p-1 rounded-full hover:bg-red-50" title="Remove Field">
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
