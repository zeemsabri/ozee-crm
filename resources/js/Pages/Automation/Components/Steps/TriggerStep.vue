<script setup>
import { computed, onMounted } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';

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

const availableEvents = computed(() => {
    if (!selectedModel.value) return [];
    const modelSchema = schema.value.find(m => m.name === selectedModel.value);
    return modelSchema ? modelSchema.events : [];
});

</script>

<template>
    <StepCard icon="⚡" title="1. When this happens... (Trigger)">
        <div class="flex items-center space-x-2 text-md">
            <span class="font-semibold text-gray-700">When a</span>

            <!-- Model Selector -->
            <select v-model="selectedModel" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <option :value="null" disabled>Select...</option>
                <option v-for="model in schema" :key="model.name" :value="model.name">
                    {{ model.name }}
                </option>
            </select>

            <!-- Event Selector -->
            <select v-if="selectedModel" v-model="selectedEvent" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                <option :value="null" disabled>is...</option>
                <option v-for="event in availableEvents" :key="event.value" :value="event.value">
                    {{ event.label }}
                </option>
            </select>
        </div>
    </StepCard>
</template>
