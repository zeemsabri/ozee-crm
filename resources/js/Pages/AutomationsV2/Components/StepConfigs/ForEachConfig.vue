<script setup>
/**
 * ForEachConfig.vue
 * Configuration panel for FOR_EACH step type (loops).
 */
import { computed } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import DataTokenInserter from '../DataTokenInserter.vue';
import TokenInputField from '../TokenInputField.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);

const config = computed({
    get: () => props.step.step_config || { sourceArray: '' },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-3">
            <p class="text-xs text-indigo-800 leading-relaxed">
                🔁 Use this step to perform actions on each item in an array (e.g., from **Fetch Records**).
            </p>
        </div>

        <div>
            <label class="field-label">Array to loop over</label>
            <TokenInputField 
                v-model="config.sourceArray" 
                :all-steps-before="allStepsBefore" 
                placeholder="Click the '+' to select an array..." 
            />
            <p class="text-[10px] text-gray-400 mt-2 italic px-1">
                Inside the loop body, you can access the current item using the **Current Loop Item** data source.
            </p>
        </div>

    </div>
</template>
