<script setup>
/**
 * DataTokenInserter.vue
 * A simplified token picker for V2 builder.
 * Allows inserting {{trigger.field}} or {{step_123.field}} tokens into inputs.
 */
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { 
    PlusIcon, 
    ChevronRightIcon, 
    MagnifyingGlassIcon,
    BoltIcon,
    ArrowPathIcon,
    CpuChipIcon,
    FolderIcon,
    VariableIcon,
    LinkIcon
} from '@heroicons/vue/24/outline';
import { useAutomationsV2Store } from '../Store/storeV2';
import TokenPickerModalV2 from './StepConfigs/TokenPickerModalV2.vue';

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
                icon: BoltIcon,
                fields: (modelSchema.columns || []).map(c => typeof c === 'string' ? c : c.name)
            });
        }
    }

    // 2. Previous Step Sources
    props.allStepsBefore.forEach(s => {
        if (s.step_type === 'FETCH_RECORDS') {
             sources.push({ id: `step_${s.id}`, label: `Fetch: ${s.name}`, icon: FolderIcon, fields: ['records', 'count'] });
        } else if (s.step_type === 'AI_PROMPT' && s.step_config?.responseStructure) {
             sources.push({ id: `step_${s.id}`, label: `AI: ${s.name}`, icon: CpuChipIcon, fields: s.step_config.responseStructure.map(f => f.name) });
        } else if (s.step_type === 'FETCH_API_DATA' && s.step_config?.responseStructure) {
             sources.push({ id: `step_${s.id}`, label: `API: ${s.name}`, icon: CpuChipIcon, fields: s.step_config.responseStructure.map(f => f.name) });
        } else if (s.step_type === 'DEFINE_VARIABLE') {
            // Support v1 `variables` array format AND legacy `variable_name` key
            const vars = Array.isArray(s.step_config?.variables)
                ? s.step_config.variables
                : (s.step_config?.variable_name ? [{ name: s.step_config.variable_name }] : []);
            if (vars.length) {
                sources.push({ id: `step_${s.id}`, label: `Var: ${s.name}`, icon: VariableIcon, fields: vars.map(v => v.name).filter(Boolean) });
            }
        } else if (s.step_type === 'TRANSFORM' || s.step_type === 'TRANSFORM_CONTENT') {
            sources.push({ id: `step_${s.id}`, label: `Transform: ${s.name}`, icon: VariableIcon, fields: ['result'] });
        }
    });

    // 3. Loop Context — expand loop.item into actual model fields
    const nearestLoop = [...props.allStepsBefore].reverse().find(s => s.step_type === 'FOR_EACH');
    if (nearestLoop) {
        const loopFields = ['index', 'key'];
        let loopModelSchema = null;

        // Strategy 1: parse sourceArray token to find the source FETCH_RECORDS step
        const sourceArray = nearestLoop.step_config?.sourceArray || '';
        const stepMatch = sourceArray.match(/step_(\d+)/);
        if (stepMatch) {
            const referencedStepId = stepMatch[1];
            const referencedStep = props.allStepsBefore.find(s => String(s.id) === referencedStepId);
            const modelName = referencedStep?.step_config?.model;
            if (modelName) {
                loopModelSchema = schema.value.find(m =>
                    m.name === modelName ||
                    m.full_class === modelName ||
                    (m.full_class || '').endsWith('\\' + modelName)
                );
            }
        }

        // Strategy 2: fall back to the nearest FETCH_RECORDS step before the loop
        if (!loopModelSchema) {
            const fetchStep = [...props.allStepsBefore]
                .reverse()
                .find(s => s.step_type === 'FETCH_RECORDS' && s.step_config?.model);
            if (fetchStep) {
                const modelName = fetchStep.step_config.model;
                loopModelSchema = schema.value.find(m =>
                    m.name === modelName ||
                    m.full_class === modelName ||
                    (m.full_class || '').endsWith('\\' + modelName)
                );
            }
        }

        if (loopModelSchema?.columns?.length) {
            // Expand loop.item.fieldName for each column
            loopModelSchema.columns.forEach(c => {
                const colName = typeof c === 'string' ? c : c.name;
                loopFields.push(`item.${colName}`);
            });
        } else {
            // Fallback: generic item reference
            loopFields.push('item');
        }

        sources.push({ id: 'loop', label: 'Current Loop Item', icon: ArrowPathIcon, fields: loopFields });
    }

    // 4. Related Data (with.*)
    // Find base models from triggers or fetches to see what relationships exist
    if (triggerStep.value?.step_config?.model) {
        const modelName = triggerStep.value.step_config.model;
        const modelSchema = schema.value.find(m => m.name === modelName);
        if (modelSchema && Array.isArray(modelSchema.relationships)) {
            const relFields = [];
            modelSchema.relationships.forEach(rel => {
                const childSchema = schema.value.find(m => m.name === rel.model);
                if (childSchema && Array.isArray(childSchema.columns)) {
                    childSchema.columns.forEach(c => {
                        const colName = typeof c === 'string' ? c : c.name;
                        relFields.push(`${rel.name}.${colName}`);
                    });
                } else {
                    relFields.push(rel.name);
                }
            });
            if (relFields.length > 0) {
                sources.push({
                    id: 'with',
                    label: `Related Data (${modelName})`,
                    icon: LinkIcon,
                    fields: relFields
                });
            }
        }
    }

    return sources;
});

function insertToken(token) {
    if (!token.startsWith('{{')) token = `{{${token}}}`;
    emit('insert', token);
    isOpen.value = false;
}

</script>

<template>
    <div class="relative inline-block">
        <button 
            type="button"
            @click.stop="isOpen = !isOpen"
            class="p-1 rounded-lg transition-all hover:bg-indigo-600 hover:text-white hover:shadow-md hover:scale-110 border border-transparent hover:border-indigo-500 focus:outline-none shrink-0 group/token-btn"
            :class="isOpen ? 'bg-indigo-600 text-white shadow-md scale-110' : 'text-slate-400 bg-slate-50'"
            title="Insert dynamic data field"
        >
            <PlusIcon class="w-4 h-4" />
        </button>

        <TokenPickerModalV2 
            :show="isOpen"
            :sources="availableSources"
            @close="isOpen = false"
            @select="insertToken"
        />

    </div>
</template>


<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>
