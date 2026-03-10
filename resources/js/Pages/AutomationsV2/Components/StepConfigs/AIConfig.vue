<script setup>
/**
 * AIConfig.vue
 * Configuration panel for AI_PROMPT step type.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import TokenInputField from '../TokenInputField.vue';
import RelatedDataPickerV2 from './RelatedDataPickerV2.vue';
import { SparklesIcon, LinkIcon, BoltIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();
const automationSchema = computed(() => store.automationSchema || []);

const config = computed({
    get: () => props.step.step_config || { promptRef: null, freeText: '', aiInputs: [], relationships: {} },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function handleConfigChange(key, value) {
    config.value = { ...config.value, [key]: value };
}

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

// --- Logic for Data Inputs (adapted from V1) ---

const triggerStep = computed(() => props.allStepsBefore.find(s => s.step_type === 'TRIGGER' || s.step_type === 'SCHEDULE_TRIGGER'));
const triggerModelName = computed(() => triggerStep.value?.step_config?.model || 'Trigger');

const triggerSchema = computed(() => {
    if (!triggerStep.value || !triggerStep.value.step_config?.model) return null;
    return automationSchema.value.find(m => m.name === triggerStep.value.step_config.model);
});

const effectiveLoopSchema = computed(() => {
    const forEach = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH' && s.step_config?.sourceArray);
    if (!forEach) return null;
    const sourcePath = forEach.step_config.sourceArray;
    const match = typeof sourcePath === 'string' ? sourcePath.match(/{{\s*step_(\w+)\.(.+?)\s*}}/) : null;
    if (!match) return null;
    
    const sourceStepId = match[1];
    const sourceFieldName = match[2];
    const sourceStep = props.allStepsBefore.find(s => String(s.id) === String(sourceStepId));
    if (!sourceStep) return null;

    if (sourceStep.step_type === 'FETCH_RECORDS' && sourceFieldName === 'records') {
        const modelName = sourceStep.step_config?.model;
        if (!modelName) return null;
        const model = automationSchema.value.find(m => m.name === modelName);
        if (!model) return null;
        return { name: 'Loop Item', columns: model.columns || [], modelName };
    }
    return null;
});


// --- Relationship Context (sidebar picker) ---
const showRelatedPicker = ref(false);
const baseModelName = computed(() => {
    return effectiveLoopSchema.value?.modelName || triggerStep.value?.step_config?.model || null;
});
const relationsConfig = computed({
    get: () => config.value.relationships || { base_model: baseModelName.value || null, roots: [], nested: {}, fields: {} },
    set: (val) => handleConfigChange('relationships', val),
});
const aiInputs = computed({
    get: () => config.value.aiInputs || [],
    set: (val) => handleConfigChange('aiInputs', val),
});
watch(baseModelName, (val) => {
    if (!val) return;
    const cur = config.value.relationships || {};
    if (cur.base_model !== val) handleConfigChange('relationships', { ...cur, base_model: val });
}, { immediate: true });
</script>

<template>
    <div class="space-y-6">
        <!-- 1. AI Prompt Template -->
        <div class="animate-in" style="animation-delay: 0.1s">
            <label class="field-label">AI Prompt Template</label>
            <select v-model="selectedPromptId" class="v2-select">
                <option value="" disabled>— Select a prompt —</option>
                <option v-for="opt in promptOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p class="text-[10px] text-gray-400 mt-1.5 flex items-center gap-1 px-1">
                <SparklesIcon class="w-3 h-3 text-indigo-400" />
                Linked prompts provide pre-defined logic and response formats.
            </p>
        </div>

        <!-- 2. Data Payload Sidebar Trigger -->
        <div v-if="baseModelName" class="animate-in" style="animation-delay: 0.13s">
            <div class="flex items-center justify-between">
                <label class="field-label">Instruction Context (Data Payload)</label>
                <button
                    type="button"
                    @click="showRelatedPicker = true"
                    class="flex items-center gap-1.5 text-[11px] font-bold text-indigo-600 hover:text-indigo-700 transition-colors"
                >
                    <LinkIcon class="w-3.5 h-3.5" />
                    Configure Data
                </button>
            </div>

            <!-- Summary chips of selected roots and base fields -->
            <div class="mt-1.5">
                <div v-if="config.aiInputs?.length || relationsConfig.roots?.length" class="flex flex-wrap gap-1.5">
                    <!-- Base model fields summary -->
                    <span
                        v-if="config.aiInputs?.length"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-50 text-slate-600 text-[11px] font-bold rounded-lg border border-slate-200"
                    >
                        <BoltIcon class="w-2.5 h-2.5 opacity-60" />
                        {{ config.aiInputs.length }} base fields
                    </span>

                    <!-- Relationships summary -->
                    <span
                        v-for="root in relationsConfig.roots"
                        :key="root"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 text-[11px] font-bold rounded-lg border border-indigo-100"
                    >
                        <LinkIcon class="w-2.5 h-2.5 opacity-60" />
                        {{ root }}
                        <span class="text-indigo-400 font-normal">
                            · {{ relationsConfig.fields?.[root]?.includes('*') ? 'all' : (relationsConfig.fields?.[root]?.length || 0) + ' fields' }}
                        </span>
                    </span>
                </div>
                <p v-else class="text-[11px] text-slate-400 italic px-1 py-1">
                    No data fields included. Click Configure to pick data for the AI.
                </p>
            </div>

            <!-- The sidebar -->
            <RelatedDataPickerV2
                :show="showRelatedPicker"
                :base-model-name="baseModelName"
                :base-source="effectiveLoopSchema ? 'loop' : 'trigger'"
                v-model="relationsConfig"
                v-model:aiInputs="aiInputs"
                @close="showRelatedPicker = false"
            />
        </div>

        <!-- 3. Custom Instructions -->
        <div class="animate-in" style="animation-delay: 0.15s">
            <label class="field-label">Instruction / Additional Context</label>
            <TokenInputField 
                v-model="config.freeText" 
                :all-steps-before="allStepsBefore" 
                textarea 
                :rows="8" 
                placeholder="Instructions for the AI..." 
            />
            <p class="text-[10px] text-gray-400 mt-2 italic px-1">Use the '+' icon to insert specific data fields or related data the AI needs to answer.</p>
        </div>

        <!-- 3. Response Variable Structure -->
        <div v-if="config.responseStructure?.length" class="pt-2 border-t border-slate-100 animate-in" style="animation-delay: 0.3s">
            <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center justify-between">
                Expected Response JSON
                <SparklesIcon class="w-3 h-3 text-indigo-300" />
            </h4>
            <div class="grid grid-cols-1 gap-2">
                <div v-for="field in config.responseStructure" :key="field.id" class="flex items-center justify-between px-3 py-2 bg-indigo-50/50 rounded-xl border border-indigo-100/50">
                    <span class="text-xs font-bold text-indigo-700">{{ field.name }}</span>
                    <span class="text-[10px] font-medium text-indigo-400 uppercase tracking-tight">{{ field.type }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

