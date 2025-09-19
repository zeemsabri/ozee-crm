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

function clearSource() {
    handleConfigChange('sourceArray', '');
}
</script>

<template>
    <StepCard icon="🔄" title="For Each Loop" :onDelete="() => emit('delete')">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-2">Loop over this data</label>
            <div class="flex items-center gap-2">
                <!-- Plus button opens token picker; no free-text input -->
                <DataTokenInserter
                    :all-steps-before="allStepsBefore"
                    :infer-loop-from-nearest="false"
                    @insert="insertToken('sourceArray', $event)"
                />
                <button v-if="loopConfig.sourceArray" @click="clearSource" class="px-2 py-1 text-xs rounded-md border text-gray-600 hover:bg-gray-50">Clear</button>
            </div>
            <div v-if="loopConfig.sourceArray" class="mt-2 text-xs text-gray-700">
                Selected: <code class="px-1 py-0.5 bg-gray-100 border rounded" v-pre>{{ loopConfig.sourceArray }}</code>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Inside the loop, use the <strong>Current Loop Item (from For Each)</strong> data source to access properties of the current item (e.g., <code v-pre>{{loop.item.name}}</code>).
            </p>
        </div>
    </StepCard>
</template>
