<script setup>
/**
 * DataTokenInserter.vue
 * A simplified token picker for V2 builder.
 * Allows inserting {{trigger.field}} or {{step_123.field}} tokens into inputs.
 */
import { ref, computed } from 'vue';
import { PlusIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';
import { useAutomationsV2Store } from '../Store/storeV2';

const props = defineProps({
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['insert']);
const store = useAutomationsV2Store();
const schema = computed(() => store.automationSchema || []);

const isOpen = ref(false);

const triggerStep = computed(() => props.allStepsBefore.find(s => s.step_type === 'TRIGGER' || s.step_type === 'SCHEDULE_TRIGGER'));

const availableSources = computed(() => {
    const sources = [];

    // 1. Trigger Source
    if (triggerStep.value?.step_config?.model) {
        const modelName = triggerStep.value.step_config.model;
        const modelSchema = schema.value.find(m => m.name === modelName);
        if (modelSchema) {
            sources.push({
                id: 'trigger',
                label: `Trigger: ${modelName}`,
                fields: (modelSchema.columns || []).map(c => typeof c === 'string' ? c : c.name)
            });
        }
    }

    // 2. Previous Step Sources (API results, AI results, etc.)
    props.allStepsBefore.forEach(s => {
        if (s.step_type === 'FETCH_RECORDS') {
             sources.push({ id: `step_${s.id}`, label: `Fetch: ${s.name}`, fields: ['records', 'count'] });
        } else if (s.step_type === 'AI_PROMPT' && s.step_config?.responseStructure) {
             sources.push({ id: `step_${s.id}`, label: `AI: ${s.name}`, fields: s.step_config.responseStructure.map(f => f.name) });
        } else if (s.step_type === 'FETCH_API_DATA' && s.step_config?.responseStructure) {
             sources.push({ id: `step_${s.id}`, label: `API: ${s.name}`, fields: s.step_config.responseStructure.map(f => f.name) });
        } else if (s.step_type === 'DEFINE_VARIABLE' || s.step_type === 'TRANSFORM') {
             if (s.step_config?.variable_name) {
                 sources.push({ id: `step_${s.id}`, label: `Var: ${s.name}`, fields: [s.step_config.variable_name] });
             }
        }
    });

    // 3. Loop Context
    const nearestLoop = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH');
    if (nearestLoop) {
        sources.push({ id: 'loop', label: 'Current Loop Item', fields: ['item', 'index', 'key'] });
    }

    return sources;
});

function insert(sourceId, field) {
    emit('insert', `{{${sourceId}.${field}}}`);
    isOpen.value = false;
}
</script>

<template>
    <div class="relative inline-block">
        <button 
            type="button"
            @click="isOpen = !isOpen"
            class="p-1.5 rounded-md hover:bg-gray-100 text-gray-400 transition hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            title="Insert token"
        >
            <PlusIcon class="w-4 h-4" />
        </button>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div 
                v-if="isOpen" 
                v-click-outside="() => isOpen = false"
                class="absolute right-0 mt-2 w-64 max-h-96 overflow-y-auto bg-white rounded-xl shadow-2xl border border-gray-200 z-[100] p-1 custom-scrollbar"
            >
                <div v-if="!availableSources.length" class="p-4 text-center text-xs text-gray-400 italic">
                    No data sources available yet.
                </div>
                
                <div v-for="src in availableSources" :key="src.id" class="mb-2 last:mb-0">
                    <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest bg-gray-50 rounded-lg">
                        {{ src.label }}
                    </div>
                    <div class="mt-1 space-y-0.5">
                        <button 
                            v-for="field in src.fields" 
                            :key="field"
                            @click="insert(src.id, field)"
                            class="w-full text-left px-4 py-1.5 text-xs text-gray-600 hover:bg-indigo-50 hover:text-indigo-700 rounded-lg transition overflow-hidden truncate"
                        >
                            {{ field }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
