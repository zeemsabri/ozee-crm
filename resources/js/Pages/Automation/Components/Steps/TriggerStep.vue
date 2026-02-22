<script setup>
import { computed, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    step: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update:step']);
const store = useWorkflowStore();

// Fetch the schema if it's not already loaded
onMounted(() => {
    if (!store.automationSchema.length) {
        store.fetchAutomationSchema();
    }
});

const schema = computed(() => store.automationSchema || []);

// Options for SelectDropdown (models)
const modelOptions = computed(() =>
    schema.value.map(m => ({ label: m.name, value: m.name }))
);

// This computed property with a getter and setter makes syncing state easy.
const selectedModel = computed({
    get() {
        return props.step.step_config?.model || null;
    },
    set(newModel) {
        // When model changes, reset the event and build the new config
        const newConfig = {
            model: newModel,
            event: null,
            trigger_event: null,
        };
        emit('update:step', { ...props.step, step_config: newConfig });
    },
});

const selectedEvent = computed({
    get() {
        return props.step.step_config?.event || null;
    },
    set(newEvent) {
        const model = selectedModel.value;
        const newConfig = {
            ...props.step.step_config,
            event: newEvent,
            trigger_event: model && newEvent ? `${model.toLowerCase()}.${newEvent}` : null,
        };
        emit('update:step', { ...props.step, step_config: newConfig });
    },
});

const selectedModelSchema = computed(() =>
    selectedModel.value ? schema.value.find(m => m.name === selectedModel.value) : null
);

const availableEvents = computed(() => {
    if (!selectedModel.value) return [];
    const modelSchema = selectedModelSchema.value;
    return modelSchema ? modelSchema.events : [];
});

const isUnsupportedModel = computed(() => selectedModel.value && availableEvents.value.length === 0);

</script>

<template>
    <StepCard icon="⚡" title="1. When this happens... (Trigger)" :disable-drag="true">
        <div class="flex items-center flex-wrap gap-2 text-md">
            <span class="font-semibold text-gray-700">When a</span>

            <!-- Model Selector -->
            <SelectDropdown
                v-model="selectedModel"
                :options="modelOptions"
                placeholder="Select a model"
                class="w-56"
            />

            <!-- Event Selector -->
            <SelectDropdown
                v-if="selectedModel && availableEvents.length"
                v-model="selectedEvent"
                :options="availableEvents"
                placeholder="is..."
                class="w-56"
            />

            <!-- Unsupported Model Message -->
            <div v-if="isUnsupportedModel" class="w-full text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                Triggers are not available for “{{ selectedModel }}”. You can still use this model in conditions or actions. If you need a trigger for it, please contact your workspace admin to request support.
            </div>
        </div>
    </StepCard>
</template>
