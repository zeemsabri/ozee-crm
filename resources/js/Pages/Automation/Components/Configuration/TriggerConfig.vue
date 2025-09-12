<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Using your custom component

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// --- Local state for our dropdowns ---
const selectedModel = ref(null);
const selectedEvent = ref(null);

// Get the schema from the store
const schema = computed(() => store.automationSchema);
const models = computed(() => store.automationSchema?.models || []);

// Static options for the event dropdown, formatted for SelectDropdown.vue
const eventOptions = ref([
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
]);

// When the component is first created, fetch the schema if it's not already loaded.
onMounted(() => {
    if (!Object.keys(store.automationSchema).length) {
        store.fetchAutomationSchema();
    }
});

// When the step changes, parse the trigger_event to pre-fill the dropdowns.
watch(step, (newStep) => {
    if (newStep && newStep.step_config?.trigger_event) {
        const [model, event] = newStep.step_config.trigger_event.split('.');
        selectedModel.value = model || null;
        selectedEvent.value = event || null;
    } else {
        selectedModel.value = null;
        selectedEvent.value = null;
    }
}, { immediate: true });


// When dropdowns change, combine them into the string the backend needs.
watch([selectedModel, selectedEvent], ([model, event]) => {
    if (step.value) {
        if (!step.value.step_config) step.value.step_config = {};
        if (model && event) {
            step.value.step_config.trigger_event = `${model}.${event}`;
        }
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
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Trigger name" />
        </div>

        <!-- Intuitive Trigger Builder -->
        <div>
            <label class="block text-xs font-medium text-gray-700">When...</label>
            <div class="flex items-center gap-2 mt-1">
                <!-- Module Dropdown using your custom component -->
                <SelectDropdown
                    v-model="selectedModel"
                    :options="models"
                    valueKey="name"
                    labelKey="name"
                    placeholder="Select a Module..."
                    class="w-1/2"
                />

                <!-- Event Dropdown using your custom component -->
                <SelectDropdown
                    v-model="selectedEvent"
                    :options="eventOptions"
                    placeholder="Select an Event..."
                    class="w-1/2"
                />
            </div>
            <p class="text-xs text-gray-500 mt-1">Define the event that starts this workflow.</p>
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

