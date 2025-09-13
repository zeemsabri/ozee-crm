<script setup>
import { computed } from 'vue';
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

const config = computed({
    get: () => props.step.step_config || { conditions: [] },
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const availableModels = computed(() => automationSchema.value.map(m => m.name));
const modelOptions = computed(() => availableModels.value.map(m => ({ value: m, label: m })));

const modelSchema = computed(() => {
    if (!config.value.model) return null;
    return automationSchema.value.find(m => m.name === config.value.model) || null;
});

const columnsForSelectedModel = computed(() => {
    if (!modelSchema.value) return [];
    return (modelSchema.value.columns || []).map(col => typeof col === 'string' ? { name: col, type: 'Text' } : col);
});
const columnOptions = computed(() => columnsForSelectedModel.value.map(col => ({ value: col.name, label: `${col.name}${col.type ? ` (${col.type})` : ''}` })));

function getColumnMeta(columnName) {
    return columnsForSelectedModel.value.find(c => c.name === columnName) || null;
}

function operatorOptionsFor(columnName) {
    const meta = getColumnMeta(columnName);
    const t = meta?.type || 'Text';
    switch (t) {
        case 'True/False':
            return [
                { value: '==', label: 'is' },
                { value: '!=', label: 'is not' },
            ];
        case 'Number':
            return [
                { value: '==', label: 'equals' },
                { value: '>', label: '>' },
                { value: '<', label: '<' },
                { value: '>=', label: '>=' },
                { value: '<=', label: '<=' },
            ];
        case 'Date':
        case 'DateTime':
            return [
                { value: '==', label: 'on' },
                { value: '>', label: 'after' },
                { value: '<', label: 'before' },
            ];
        default:
            return [
                { value: '==', label: 'is' },
                { value: '!=', label: 'is not' },
                { value: 'contains', label: 'contains' },
            ];
    }
}

function addCondition() {
    const newConditions = [...(config.value.conditions || []), { column: '', operator: '==', value: '' }];
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
                <SelectDropdown
                    :options="modelOptions"
                    :model-value="config.model || null"
                    placeholder="Select model..."
                    @update:modelValue="(val) => config = { ...config, model: val, conditions: [] }"
                />
            </div>
            <div v-if="config.model">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-600">Where conditions match</label>
                    <button @click="addCondition" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add</button>
                </div>
                <div class="space-y-2">
                    <div v-for="(cond, index) in config.conditions" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                         <div class="grid grid-cols-3 gap-2 items-center">
                            <SelectDropdown
                                :options="columnOptions"
                                :model-value="cond.column || null"
                                placeholder="Field..."
                                @update:modelValue="(val) => updateCondition(index, 'column', val)"
                                class="col-span-2"
                            />
                             <div class="flex items-center justify-end">
                                <button @click="removeCondition(index)" class="p-1 text-gray-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                             </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <SelectDropdown
                                :options="operatorOptionsFor(cond.column)"
                                :model-value="cond.operator || '=='"
                                placeholder="Operator"
                                @update:modelValue="(val) => updateCondition(index, 'operator', val)"
                            />
                            <div class="flex items-center gap-1">
                               <!-- Value control adapts to field type -->
                               <template v-if="getColumnMeta(cond.column)?.allowed_values">
                                   <SelectDropdown
                                       :options="(getColumnMeta(cond.column).allowed_values || []).map(o => ({ value: o.value, label: o.label }))"
                                       :model-value="cond.value || null"
                                       placeholder="Select value..."
                                       @update:modelValue="val => updateCondition(index, 'value', val)"
                                   />
                               </template>
                               <template v-else-if="getColumnMeta(cond.column)?.type === 'True/False'">
                                   <select :value="cond.value ?? 'true'" @change="updateCondition(index, 'value', $event.target.value)" class="w-full border rounded px-2 py-2 text-sm">
                                       <option value="true">True</option>
                                       <option value="false">False</option>
                                   </select>
                               </template>
                               <template v-else>
                                   <input :type="getColumnMeta(cond.column)?.type === 'Date' ? 'date' : (getColumnMeta(cond.column)?.type === 'DateTime' ? 'datetime-local' : 'text')" :value="cond.value" @input="updateCondition(index, 'value', $event.target.value)" placeholder="Value" class="w-full border rounded px-2 py-2 text-sm" />
                               </template>

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
