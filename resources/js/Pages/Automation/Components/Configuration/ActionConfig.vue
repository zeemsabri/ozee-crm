<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import SelectDropdown from '@/Components/SelectDropdown.vue';

// Import our specific action config components
import CreateRecordConfig from './Actions/CreateRecordConfig.vue';
import UpdateRecordConfig from './Actions/UpdateRecordConfig.vue';
import SendEmailConfig from './Actions/SendEmailConfig.vue';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// Local state for the action type dropdown
const selectedActionType = ref(null);

// Options for the action type dropdown
const actionTypeOptions = ref([
    { value: 'CREATE_RECORD', label: 'Create Record' },
    { value: 'UPDATE_RECORD', label: 'Update Record' },
    { value: 'SEND_EMAIL', label: 'Send Email' },
]);

// A computed property to dynamically load the correct config component
const configComponent = computed(() => {
    switch (selectedActionType.value) {
        case 'CREATE_RECORD':
            return CreateRecordConfig;
        case 'UPDATE_RECORD':
            return UpdateRecordConfig;
        case 'SEND_EMAIL':
            return SendEmailConfig;
        default:
            return null;
    }
});

// When the selected step changes, update the local state from the store
watch(step, (newStep) => {
    if (newStep && newStep.step_config?.action_type) {
        selectedActionType.value = newStep.step_config.action_type;
    } else {
        selectedActionType.value = null;
    }
}, { immediate: true });

// When the dropdown value changes, update the step's config in the store
watch(selectedActionType, (newType) => {
    if (step.value) {
        if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
            step.value.step_config = {};
        }
        step.value.step_config.action_type = newType;
    }
});

// We need the schema to populate the model/field dropdowns in our action configs
onMounted(() => {
    if (!Object.keys(store.automationSchema).length) {
        store.fetchAutomationSchema();
    }
});

const save = async () => {
    if (!step.value) return;
    await store.persistStep(step.value);
    toast.success("Step saved successfully!");
    store.selectStep(null); // Close the sidebar
};
</script>

<template>
    <div v-if="step" class="p-4 space-y-4">
        <!-- Name Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Name</label>
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Action name" />
        </div>

        <!-- Action Type Selector -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Action Type</label>
            <SelectDropdown
                v-model="selectedActionType"
                :options="actionTypeOptions"
                placeholder="Select an action..."
                class="w-full mt-1"
            />
            <p class="text-xs text-gray-500 mt-1">Define the task this step will perform.</p>
        </div>

        <!-- The dynamically rendered config component -->
        <div v-if="configComponent" class="pt-4 border-t mt-4">
            <component :is="configComponent" />
        </div>

        <!-- Save Button -->
        <div class="pt-2">
            <button @click="save" class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                <Save class="w-4 h-4" />
                Save Step
            </button>
        </div>
    </div>

</template>
