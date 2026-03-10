<script setup>
/**
 * TransformConfig.vue
 * Configuration panel for TRANSFORM, TRANSFORM_CONTENT, and DEFINE_VARIABLE step types.
 */
import { computed } from 'vue';
import TokenInputField from '../TokenInputField.vue';
import { 
    ScissorsIcon, 
    ArrowsRightLeftIcon, 
    TrashIcon,
    CommandLineIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);

const isVariable = computed(() => props.step.step_type === 'DEFINE_VARIABLE');
const isTransform = computed(() => ['TRANSFORM', 'TRANSFORM_CONTENT'].includes(props.step.step_type));

const config = computed({
    get: () => props.step.step_config || {},
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function set(key, val) { 
    config.value = { ...config.value, [key]: val }; 
}

// ---- DEFINE_VARIABLE: support v1 `variables` array format ----
// The backend stores variables as: { variables: [{name, value}] }
// The old UI used: { variable_name, value } — we normalise on read
const variables = computed(() => {
    const c = config.value;
    // v1 format: variables array
    if (Array.isArray(c.variables) && c.variables.length) return c.variables;
    // v2 legacy format: variable_name / value keys
    if (c.variable_name !== undefined || c.value !== undefined) {
        return [{ name: c.variable_name || '', value: c.value || '' }];
    }
    return [{ name: '', value: '' }];
});

function updateVariable(idx, key, val) {
    const updated = variables.value.map((v, i) => i === idx ? { ...v, [key]: val } : v);
    // Always write back in the v1 array format
    config.value = { ...config.value, variables: updated };
}

function addVariable() {
    config.value = { ...config.value, variables: [...variables.value, { name: '', value: '' }] };
}

function removeVariable(idx) {
    config.value = { ...config.value, variables: variables.value.filter((_, i) => i !== idx) };
}
// ---- end DEFINE_VARIABLE helpers ----

const transformationTypes = [
    { value: 'remove_after_marker', label: 'Remove After Marker', desc: 'Truncate text after a specific string.', icon: ScissorsIcon },
    { value: 'find_and_replace',    label: 'Find & Replace',      desc: 'Swap specific text strings.',         icon: ArrowsRightLeftIcon },
    { value: 'remove_html',         label: 'Plain Text Only',     desc: 'Strip HTML tags and entities.',      icon: TrashIcon },
];

const selectedType = computed(() => transformationTypes.find(t => t.value === config.value.type));

</script>

<template>
    <div class="space-y-6">
        
        <!-- CASE A: DEFINE VARIABLE -->
        <template v-if="isVariable">
            <div class="rounded-xl bg-indigo-50/50 border border-indigo-100 px-4 py-3 flex items-start gap-3 animate-in">
                <CommandLineIcon class="w-5 h-5 text-indigo-500 mt-0.5" />
                <div>
                    <p class="text-[11px] font-bold text-indigo-700 uppercase tracking-widest leading-none">Define Variables</p>
                    <p class="text-xs text-indigo-600/70 mt-1 font-medium">Store values to reuse in later steps.</p>
                </div>
            </div>

            <div class="space-y-3">
                <div 
                    v-for="(variable, idx) in variables" 
                    :key="idx"
                    class="p-3 bg-gray-50 rounded-xl border border-gray-100 space-y-3 relative group"
                >
                    <button 
                        v-if="variables.length > 1"
                        @click="removeVariable(idx)"
                        class="absolute -top-2 -right-2 w-5 h-5 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm opacity-0 group-hover:opacity-100 transition"
                    >
                        <TrashIcon class="w-3 h-3" />
                    </button>

                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Variable Name</label>
                        <input 
                            :value="variable.name" 
                            @input="updateVariable(idx, 'name', $event.target.value.replace(/\s+/g, '_').toLowerCase())"
                            placeholder="e.g. project_id"
                            class="v2-input font-mono !text-xs" 
                        />
                        <p class="text-[10px] text-gray-400 mt-1 italic font-medium px-1">Access later as <code class="bg-gray-100 px-1 rounded" v-text="'{{step_X.' + (variable.name || 'name') + '}}'"></code></p>
                    </div>

                    <div>
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Value / Formula</label>
                        <TokenInputField 
                            :model-value="variable.value" 
                            @update:modelValue="updateVariable(idx, 'value', $event)"
                            :all-steps-before="allStepsBefore" 
                            textarea 
                            :rows="2" 
                            placeholder="Static text or dynamic token..." 
                        />
                    </div>
                </div>

                <button @click="addVariable" class="v2-btn-outline w-full py-2 border-dashed">
                    <PlusIcon class="w-4 h-4" /> Add Variable
                </button>
            </div>
        </template>

        <!-- CASE B: TRANSFORM CONTENT -->
        <template v-else-if="isTransform">
            <div>
                <label class="field-label">Transformation Type</label>
                <div class="grid grid-cols-1 gap-2 mt-1">
                    <button
                        v-for="t in transformationTypes"
                        :key="t.value"
                        type="button"
                        @click="set('type', t.value)"
                        class="flex items-center gap-3 p-3 rounded-xl border-2 text-left transition-all"
                        :class="config.type === t.value 
                            ? 'bg-slate-900 border-slate-900 text-white shadow-lg' 
                            : 'bg-white border-slate-100 hover:border-slate-200 text-slate-600'"
                    >
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                             :class="config.type === t.value ? 'bg-white/20' : 'bg-slate-50'">
                            <component :is="t.icon" class="w-4 h-4" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold leading-none">{{ t.label }}</p>
                            <p class="text-[10px] mt-1 opacity-60 font-medium truncate">{{ t.desc }}</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Transformation Specific Fields -->
            <div v-if="config.type" class="animate-in space-y-5 pt-2">
                
                <!-- 1. Source Data (common for all) -->
                <div>
                    <label class="field-label">Source Content</label>
                    <TokenInputField 
                        v-model="config.source" 
                        :all-steps-before="allStepsBefore" 
                        placeholder="e.g. {{trigger.body}}"
                    />
                    <p class="text-[10px] text-slate-400 mt-1.5 font-medium px-1">Choose the data you want to transform.</p>
                </div>

                <!-- 2. Markers for REMOVE AFTER -->
                <div v-if="config.type === 'remove_after_marker'" class="animate-in">
                    <label class="field-label">Remove Everything After This</label>
                    <TokenInputField 
                        v-model="config.marker" 
                        :all-steps-before="allStepsBefore" 
                        placeholder="Marker string or token..."
                    />
                    <p class="text-[10px] text-slate-400 mt-1.5 font-medium px-1 italic">Handy for removing email signatures or footers.</p>
                </div>

                <!-- 3. Find & Replace -->
                <div v-if="config.type === 'find_and_replace'" class="animate-in space-y-4">
                    <div>
                        <label class="field-label">Find Text</label>
                        <TokenInputField 
                            v-model="config.find" 
                            :all-steps-before="allStepsBefore" 
                        />
                    </div>
                    <div>
                        <label class="field-label">Replace With</label>
                        <TokenInputField 
                            v-model="config.replace" 
                            :all-steps-before="allStepsBefore" 
                        />
                    </div>
                </div>

            </div>
            
            <div v-else class="py-12 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100 text-center">
                <ScissorsIcon class="w-8 h-8 text-slate-200 mx-auto mb-3" />
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Select a type above</p>
            </div>
        </template>

    </div>
</template>
