<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useWorkflowStore } from '../../../Store/workflowStore';
import { Plus, Trash2 } from 'lucide-vue-next';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import VariablePicker from '../../VariablePicker.vue';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);
const schema = computed(() => store.automationSchema);

const models = computed(() => schema.value?.models || []);

// Local state for the fields to be updated
const localFields = ref([]);
const selectedModel = ref(null);
const targetId = ref('');

const columnsForModel = computed(() => {
    if (!selectedModel.value) return [];
    const model = models.value.find(m => m.name === selectedModel.value);
    return model ? model.columns.map(c => ({ value: c, label: c })) : [];
});

const addField = () => {
    localFields.value.push({
        selectedColumn: null,
        value: ''
    });
};

const removeField = (index) => {
    localFields.value.splice(index, 1);
};

// When the step changes, load the existing configuration
watch(step, (newStep) => {
    if (newStep && newStep.step_config) {
        selectedModel.value = newStep.step_config.target_model || null;
        targetId.value = newStep.step_config.record_id || '';
        if (newStep.step_config.fields && Array.isArray(newStep.step_config.fields)) {
            localFields.value = newStep.step_config.fields.map(field => ({
                selectedColumn: field.field || null,
                value: field.value || ''
            }));
        } else {
            localFields.value = [];
        }
    } else {
        selectedModel.value = null;
        targetId.value = '';
        localFields.value = [];
    }
}, { immediate: true });

// When the local state changes, sync it back to the step config
watch([selectedModel, targetId, localFields], ([newModel, newId, newFields]) => {
    if (step.value) {
        if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
            step.value.step_config = {};
        }
        step.value.step_config.target_model = newModel;
        step.value.step_config.record_id = newId;
        step.value.step_config.fields = newFields.filter(f => f.selectedColumn).map(f => ({
            field: f.selectedColumn,
            value: f.value
        }));
    }
}, { deep: true });

onMounted(() => {
    if (!Object.keys(store.automationSchema).length) {
        store.fetchAutomationSchema();
    }
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">Target Model</label>
            <SelectDropdown
                v-model="selectedModel"
                :options="models"
                valueKey="name"
                labelKey="name"
                placeholder="Select a module..."
                class="w-full mt-1"
            />
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700">Record ID to Update</label>
            <div class="mt-1 flex items-center gap-2">
                <input v-model="targetId" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="e.g., {{ trigger.task.id }}" />
                <VariablePicker @select="val => targetId = (targetId || '') + (targetId ? ' ' : '') + val" />
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">Fields to update</label>
                <button @click="addField" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                    <Plus class="w-3 h-3" />
                    Add Field
                </button>
            </div>
            <div v-if="localFields.length > 0" class="mt-2 space-y-2">
                <div v-for="(field, index) in localFields" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                    <div class="flex items-center gap-2">
                        <SelectDropdown
                            v-model="field.selectedColumn"
                            :options="columnsForModel"
                            placeholder="Select a field..."
                            class="w-full"
                            :disabled="!selectedModel"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <input v-model="field.value" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Value" />
                        <VariablePicker @select="val => field.value = (field.value || '') + (field.value ? ' ' : '') + val" />
                    </div>
                    <div class="text-right">
                        <button @click="removeField(index)" class="text-red-500 hover:text-red-700 p-1" title="Remove Field">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-xs text-gray-500 py-4 border-2 border-dashed rounded-lg mt-2">
                No fields defined.
            </div>
        </div>
    </div>
</template>
