<script setup>
/**
 * ForEachConfig.vue
 * Configuration panel for FOR_EACH step type (loops).
 */
import { computed } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import DataTokenInserter from '../DataTokenInserter.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);

const config = computed({
    get: () => props.step.step_config || { sourceArray: '' },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function insertToken(token) {
    config.value = { ...config.value, sourceArray: token };
}
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
            <div class="flex gap-2">
                <input 
                    readonly
                    :value="config.sourceArray" 
                    placeholder="Click the '+' to select an array..."
                    class="v2-input flex-1 bg-gray-50 border-dashed cursor-default" 
                />
                <DataTokenInserter :all-steps-before="allStepsBefore" @insert="insertToken" />
            </div>
            <p class="text-[10px] text-gray-400 mt-2 italic px-1">
                Inside the loop body, you can access the current item using the **Current Loop Item** data source.
            </p>
        </div>
    </div>
</template>
