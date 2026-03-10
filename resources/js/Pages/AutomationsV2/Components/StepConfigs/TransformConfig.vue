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
    CommandLineIcon
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
                    <p class="text-[11px] font-bold text-indigo-700 uppercase tracking-widest leading-none">Global Variable</p>
                    <p class="text-xs text-indigo-600/70 mt-1 font-medium">Define a value to use in later steps.</p>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="field-label">Variable Name</label>
                    <input 
                        :value="config.variable_name" 
                        @input="set('variable_name', $event.target.value.replace(/\s+/g, '_').toLowerCase())"
                        placeholder="e.g. lead_score"
                        class="v2-input font-mono !text-xs" 
                    />
                    <p class="text-[10px] text-gray-400 mt-1.5 italic font-medium px-1">Lowercase and underscores only.</p>
                </div>

                <div>
                    <label class="field-label">Value / Formula</label>
                    <TokenInputField 
                        v-model="config.value" 
                        :all-steps-before="allStepsBefore" 
                        textarea 
                        :rows="3" 
                        placeholder="Static text or dynamic tokens..." 
                    />
                </div>
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
