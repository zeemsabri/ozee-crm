<script setup>
import { computed } from 'vue';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step', 'delete']);

const transformConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

function handleConfigChange(key, value) {
    transformConfig.value = { ...transformConfig.value, [key]: value };
}

// Define the available transformation types with friendly labels
const transformationTypes = [
    { value: 'remove_after_marker', label: 'Remove content after a marker' },
    { value: 'find_and_replace', label: 'Find and replace text' },
    // Add other transformation options here
];
</script>

<template>
    <StepCard icon="✂️" title="Transform Content" :onDelete="() => emit('delete')">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Choose a transformation</label>
                <select
                    :value="transformConfig.type || ''"
                    @change="handleConfigChange('type', $event.target.value)"
                    class="w-full p-2 border border-gray-300 rounded-md text-sm mt-1"
                >
                    <option value="" disabled>Select a transformation...</option>
                    <option v-for="type in transformationTypes" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
            </div>

            <template v-if="transformConfig.type === 'remove_after_marker'">
                <div>
                    <label class="block text-sm font-medium text-gray-700">In this data:</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input
                            type="text"
                            :value="transformConfig.source || ''"
                            @input="handleConfigChange('source', $event.target.value)"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm"
                            placeholder="Select the email body or another text field."
                        />
                        <DataTokenInserter
                            :all-steps-before="allStepsBefore"
                            @insert="token => handleConfigChange('source', token)"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Remove everything after this marker:</label>
                    <div class="flex items-center gap-2 mt-1">
                        <input
                            type="text"
                            :value="transformConfig.marker || ''"
                            @input="handleConfigChange('marker', $event.target.value)"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm"
                            placeholder="Select the signature marker from the AI step."
                        />
                        <DataTokenInserter
                            :all-steps-before="allStepsBefore"
                            @insert="token => handleConfigChange('marker', token)"
                        />
                    </div>
                </div>
            </template>
        </div>
    </StepCard>
</template>
