<script setup>
/**
 * AIConfig.vue
 * Configuration panel for AI_PROMPT step type.
 */
import { computed, onMounted } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import DataTokenInserter from '../DataTokenInserter.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();

const config = computed({
    get: () => props.step.step_config || { promptRef: null, freeText: '' },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

onMounted(() => store.ensurePrompts());

const prompts = computed(() => store.prompts || []);
const promptOptions = computed(() => prompts.value.map(p => ({
    label: `${p.name} (v${p.version})`,
    value: p.id
})));

const selectedPromptId = computed({
    get: () => config.value.promptRef?.id || '',
    set: (id) => {
        const p = prompts.value.find(pr => String(pr.id) === String(id));
        if (p) {
            config.value = {
                ...config.value,
                promptRef: { id: p.id, name: p.name, version: p.version },
                responseStructure: p.response_variables || []
            };
        }
    }
});

function insertToken(token) {
    config.value = { ...config.value, freeText: (config.value.freeText || '') + token };
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="field-label">AI Prompt Template</label>
            <select v-model="selectedPromptId" class="v2-select">
                <option value="" disabled>— Select a prompt —</option>
                <option v-for="opt in promptOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p class="text-[10px] text-gray-400 mt-1">Select a predefined AI prompt from your library.</p>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="field-label mb-0">Custom Instructions / Context</label>
                <DataTokenInserter :all-steps-before="allStepsBefore" @insert="insertToken" />
            </div>
            <textarea 
                rows="6" 
                v-model="config.freeText"
                placeholder="Additional instructions for the AI or data to process..."
                class="v2-input !text-xs font-medium leading-relaxed resize-none"
            ></textarea>
        </div>

        <div v-if="config.responseStructure?.length" class="pt-4 border-t">
            <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Expected Response</h4>
            <div class="space-y-1 bg-gray-50 rounded-lg p-3 border border-gray-100">
                <div v-for="field in config.responseStructure" :key="field.id" class="flex items-center justify-between text-xs">
                    <span class="font-bold text-gray-700">{{ field.name }}</span>
                    <span class="text-gray-400 italic">{{ field.type }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
