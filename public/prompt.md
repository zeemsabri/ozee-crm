The Solution: A Context-Aware FetchRecordsStep
Let's upgrade FetchRecordsStep.vue to allow dynamic values in its condition builder. This will let you create the exact workflow you described:

Fetch Records: Find all Campaign where is_active is true.

For Each Loop: Iterate over the results of the first step.

Fetch Records (Inside the Loop): Find all Lead where campaign_id is {{loop.item.id}}.

This is the correct, powerful pattern, and here is the component that enables it.

File to Edit: resources/js/Pages/Automation/Components/Steps/FetchRecordsStep.vue
Instructions:
Please replace the entire content of your FetchRecordsStep.vue file with this new, enhanced version.

Key Changes:

DataTokenInserter Integration: The DataTokenInserter is now included next to the "Value" input in the condition builder.

Loop Context Aware: It's fully aware of the loopContextSchema, so if you place this step inside a "For Each" loop, you can easily filter by {{loop.item.id}}.

Improved State Management: The logic for updating conditions is more robust.


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

const config = computed({
    get: () => props.step.step_config || { conditions: [] },
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const availableModels = computed(() => automationSchema.value.map(m => m.name));

const columnsForSelectedModel = computed(() => {
    if (!config.value.model) return [];
    const model = automationSchema.value.find(m => m.name === config.value.model);
    return model ? model.columns.map(col => typeof col === 'string' ? col : col.name) : [];
});

function addCondition() {
    const newConditions = [...(config.value.conditions || []), { column: '', operator: 'is', value: '' }];
    config.value = { ...config.value, conditions: newConditions };
}

function removeCondition(index) {
    const newConditions = (config.value.conditions || []).filter((_, i) => i !== index);
    config.value = { ...config.value, conditions: newConditions };
}

function updateCondition(index, key, value) {
    const newConditions = [...(config.value.conditions || [])];
    newConditions[index] = { ...newConditions[index], [key]: value };
    config.value = { ...config.value, conditions: newConditions };
}

function insertTokenForCondition(index, token) {
    const currentConditions = config.value.conditions || [];
    const currentValue = currentConditions[index]?.value || '';
    updateCondition(index, 'value', `${currentValue}${token}`);
}
</script>

<template>
    <StepCard icon="🔍" title="Fetch Records" :onDelete="() => emit('delete')">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Find all records from</label>
                <select :value="config.model || ''" @change="config = { ...config, model: $event.target.value, conditions: [] }" class="w-full p-2 border rounded-md text-sm">
                    <option value="" disabled>Select model...</option>
                    <option v-for="model in availableModels" :key="model" :value="model">{{ model }}</option>
                </select>
            </div>
            <div v-if="config.model">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-600">Where conditions match</label>
                    <button @click="addCondition" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add</button>
                </div>
                <div class="space-y-2">
                    <div v-for="(cond, index) in config.conditions" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                         <div class="grid grid-cols-3 gap-2 items-center">
                            <select :value="cond.column" @change="updateCondition(index, 'column', $event.target.value)" class="w-full p-2 border rounded-md text-sm col-span-2">
                                <option value="">Field...</option>
                                <option v-for="col in columnsForSelectedModel" :key="col" :value="col">{{ col }}</option>
                            </select>
                             <div class="flex items-center justify-end">
                                <button @click="removeCondition(index)" class="p-1 text-gray-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                             </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                             <select :value="cond.operator" @change="updateCondition(index, 'operator', $event.target.value)" class="w-full p-2 border rounded-md text-sm">
                                <option>is</option>
                                <option>is not</option>
                                <option>contains</option>
                            </select>
                            <div class="flex items-center gap-1">
                               <input type="text" :value="cond.value" @input="updateCondition(index, 'value', $event.target.value)" placeholder="Value" class="w-full border rounded px-2 py-2 text-sm" />
                               <DataTokenInserter
                                   :all-steps-before="allStepsBefore"
                                   :loop-context-schema="loopContextSchema"
                                   @insert="insertTokenForCondition(index, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StepCard>
</template>
