<script setup>
import { computed } from 'vue';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] }
});
const emit = defineEmits(['update:step', 'delete']);

const loopConfig = computed({
    get: () => props.step.step_config || {},
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

function handleConfigChange(key, value) {
    loopConfig.value = { ...loopConfig.value, [key]: value };
}

function insertToken(fieldName, token) {
    handleConfigChange(fieldName, token);
}
</script>

<template>
    <StepCard icon="🔄" title="For Each Loop" :onDelete="() => emit('delete')">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Loop over this data</label>
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    :value="loopConfig.sourceArray || ''"
                    @input="handleConfigChange('sourceArray', $event.target.value)"
                    class="w-full p-2 border border-gray-300 rounded-md text-sm"
                    placeholder="e.g., {{step_1.tasks}}"
                />
                <DataTokenInserter :all-steps-before="allStepsBefore" :infer-loop-from-nearest="false" @insert="insertToken('sourceArray', $event)" />
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Inside the loop, use the <strong>Current Loop Item (from For Each)</strong> data source to access properties of the current item (e.g., <code v-pre>{{loop.item.name}}</code>).
            </p>
        </div>
    </StepCard>
</template>
