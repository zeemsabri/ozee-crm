<script setup>
/**
 * FetchRecordsConfig.vue
 * Configuration panel for FETCH_RECORDS step type.
 */
import { ref, computed } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import { PlusIcon, TrashIcon, CubeIcon, ChevronRightIcon } from '@heroicons/vue/24/solid';
import TokenInputField from '../TokenInputField.vue';
import ModelPickerModalV2 from './ModelPickerModalV2.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();
const schema = computed(() => store.automationSchema || []);

const showPicker = ref(false);

const config = computed({
    get: () => props.step.step_config || { conditions: [], single: true },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function set(key, val) { config.value = { ...config.value, [key]: val }; }

function selectModel(m) {
    if (config.value.model !== m) {
        config.value = { ...config.value, model: m, conditions: [] };
    }
    showPicker.value = false;
}

const modelOptions = computed(() => schema.value.map(m => ({ label: m.name, value: m.name })));

const selectedModelObj = computed(() => 
    schema.value.find(m => m.name === config.value.model) || null
);

const columns = computed(() => (selectedModelObj.value?.columns || []).map(c => {
    if (typeof c === 'string') return { name: c, label: humanize(c) };
    return { ...c, label: c.label || humanize(c.name) };
}));

function humanize(n) {
    if (!n) return '';
    let s = n.toLowerCase().replace(/_id$/, '').replace(/[_-]+/g, ' ');
    return s.replace(/\b\w/g, c => c.toUpperCase());
}

function addCondition() {
    set('conditions', [...(config.value.conditions || []), { column: '', operator: '==', value: '' }]);
}

function removeCondition(i) {
    set('conditions', (config.value.conditions || []).filter((_, idx) => idx !== i));
}

function updateCondition(i, key, val) {
    const c = [...(config.value.conditions || [])];
    c[i] = { ...c[i], [key]: val };
    set('conditions', c);
}

const OPERATORS = [
    { value: '==', label: 'equals' },
    { value: '!=', label: 'not equals' },
    { value: '>',  label: '>' },
    { value: '<',  label: '<' },
    { value: '>=', label: '>=' },
    { value: '<=', label: '<=' },
    { value: 'contains', label: 'contains' },
    { value: 'is_null', label: 'is null' },
    { value: 'is_not_null', label: 'is not null' },
];
</script>

<template>
    <div class="space-y-4">
        <div>
            <label class="field-label">Fetch records from model</label>
            <button 
                @click="showPicker = true" 
                class="w-full flex items-center justify-between p-3.5 bg-white border-2 rounded-2xl transition-all group"
                :class="config.model ? 'border-slate-100 hover:border-indigo-200' : 'border-dashed border-slate-200 hover:border-slate-300'"
            >
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center group-hover:bg-indigo-50 transition-colors">
                        <CubeIcon class="w-5 h-5" :class="config.model ? 'text-indigo-500' : 'text-slate-300'" />
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-black text-slate-800 leading-none">{{ config.model || 'Select a model' }}</p>
                        <p class="text-[11px] text-slate-400 mt-1.5 font-bold uppercase tracking-wider">{{ config.model ? 'Click to change source' : 'Choose the data source' }}</p>
                    </div>
                </div>
                <ChevronRightIcon class="w-5 h-5 text-slate-300 group-hover:text-indigo-400 transition-all" />
            </button>
        </div>

        <ModelPickerModalV2 
            :show="showPicker"
            :schema="schema"
            :selected-model="config.model"
            @close="showPicker = false"
            @select="selectModel"
        />

        <div v-if="config.model">
            <div class="flex items-center justify-between mb-2">
                <label class="field-label mb-0">Where</label>
                <button @click="addCondition" class="text-xs text-indigo-600 font-bold hover:underline">+ Add Condition</button>
            </div>
            
            <div class="space-y-2">
                <div v-for="(cond, idx) in (config.conditions || [])" :key="idx" class="p-3 bg-gray-50 border border-gray-100 rounded-xl space-y-2 relative group">
                    <button @click="removeCondition(idx)" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 shadow-sm opacity-0 group-hover:opacity-100 transition">
                        <TrashIcon class="w-3 h-3" />
                    </button>

                    <div class="flex gap-2">
                        <select :value="cond.column" @change="updateCondition(idx, 'column', $event.target.value)" class="v2-select flex-1 !py-1 text-xs">
                            <option value="" disabled>Field…</option>
                            <option v-for="col in columns" :key="col.name" :value="col.name">{{ col.label }}</option>
                        </select>
                        <select :value="cond.operator" @change="updateCondition(idx, 'operator', $event.target.value)" class="v2-select w-28 !py-1 text-xs text-center font-bold text-indigo-600">
                             <option v-for="op in OPERATORS" :key="op.value" :value="op.value">{{ op.label }}</option>
                        </select>
                    </div>

                    <div v-if="cond.operator !== 'is_null' && cond.operator !== 'is_not_null'" class="flex gap-2">
                        <TokenInputField 
                            v-model="cond.value" 
                            :all-steps-before="allStepsBefore" 
                            placeholder="Value or {{token}}" 
                        />
                    </div>
                </div>
            </div>

            <div v-if="!(config.conditions?.length)" class="text-center py-4 text-xs text-gray-400 italic">
                No conditions set. This will fetch ALL records from the table.
            </div>

            <div class="pt-4 border-t mt-4">
                <label class="flex items-center gap-2 cursor-pointer group">
                    <div class="relative inline-flex items-center">
                        <input type="checkbox" class="sr-only peer" :checked="config.single" @change="set('single', $event.target.checked)">
                        <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                    </div>
                    <span class="text-xs font-semibold text-gray-700 select-none">Only fetch first matching record</span>
                </label>
                <p class="text-[10px] text-gray-400 mt-1 ml-11">If disabled, this returns an array of records to be used in a For Each loop.</p>
            </div>
        </div>
    </div>
</template>
