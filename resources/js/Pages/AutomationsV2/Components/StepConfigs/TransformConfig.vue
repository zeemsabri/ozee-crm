<script setup>
/**
 * TransformConfig.vue
 * Configuration panel for TRANSFORM and DEFINE_VARIABLE step types.
 */
import { computed } from 'vue';
import DataTokenInserter from '../DataTokenInserter.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);

const config = computed({
    get: () => props.step.step_config || { variable_name: '', value: '' },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function set(key, val) { config.value = { ...config.value, [key]: val }; }

function insertToken(token) {
    set('value', (config.value.value || '') + token);
}
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
            <p class="text-xs text-slate-700 leading-relaxed">
                🛠 Define a variable that you can use later in the automation.
            </p>
        </div>

        <div>
            <label class="field-label">Variable Name</label>
            <input 
                :value="config.variable_name" 
                @input="set('variable_name', $event.target.value.replace(/\s+/g, '_').toLowerCase())"
                placeholder="e.g. order_total"
                class="v2-input font-mono" 
            />
            <p class="text-[10px] text-gray-400 mt-1 italic">Use lowercase and underscores only.</p>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="field-label mb-0">Value / Formula</label>
                <DataTokenInserter :all-steps-before="allStepsBefore" @insert="insertToken" />
            </div>
            <textarea 
                rows="4" 
                :value="config.value"
                @input="set('value', $event.target.value)"
                placeholder="Data or formula..."
                class="v2-input !text-xs font-medium resize-none"
            ></textarea>
        </div>
    </div>
</template>
