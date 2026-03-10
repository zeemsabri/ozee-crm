<script setup>
/**
 * ConditionConfig.vue
 * Configuration panel for CONDITION step type (If/Else logic).
 */
import { computed, ref, watch } from 'vue';
import { useAutomationsV2Store } from '../../Store/storeV2';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/solid';
import DataTokenInserter from '../DataTokenInserter.vue';
import TokenInputField from '../TokenInputField.vue';

const props = defineProps({
    step:           { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step']);
const store = useAutomationsV2Store();
const schema = computed(() => store.automationSchema || []);

const config = computed({
    get: () => props.step.step_config || { rules: [], logic: 'AND' },
    set: (v) => emit('update:step', { ...props.step, step_config: v }),
});

function set(key, val) { config.value = { ...config.value, [key]: val }; }

const rules = computed(() => config.value.rules || []);

function addRule() {
    set('rules', [...rules.value, { left: '', operator: '==', right: '' }]);
}

function removeRule(i) {
    set('rules', rules.value.filter((_, idx) => idx !== i));
}

function updateRule(i, key, val) {
    const r = [...rules.value];
    r[i] = { ...r[i], [key]: val };
    set('rules', r);
}

function parseTokenString(str) {
    if (!str) return { type: 'literal', value: '' };
    const match = str.match(/(^{{.+}}$)/);
    if (match) return { type: 'var', path: match[1].replace(/^{{|}}$/g, '') }; // {{trigger.id}} -> var, trigger.id
    return { type: 'literal', value: str };
}

function stringifyTokenObj(obj) {
    if (!obj) return '';
    if (typeof obj === 'string') return obj;
    if (obj.type === 'var') return `{{${obj.path}}}`;
    return obj.value || '';
}

// Fallback for old style: mapped in rules computed
const mappedRules = computed(() => {
    return rules.value.map(r => {
        let leftObj = r.left;
        if (r.field && !leftObj) leftObj = { type: 'var', path: `trigger.${r.field}` };
        
        let rightObj = r.right;
        if (r.value !== undefined && !rightObj) rightObj = { type: 'literal', value: r.value };

        return {
            ...r,
            leftStr: stringifyTokenObj(leftObj),
            rightStr: stringifyTokenObj(rightObj)
        };
    });
});


const OPERATORS = [
    { value: '==',           label: 'equals' },
    { value: '!=',           label: 'not equals' },
    { value: '>',            label: 'greater than' },
    { value: '<',            label: 'less than' },
    { value: '>=',           label: 'greater or equal' },
    { value: '<=',           label: 'less or equal' },
    { value: 'contains',     label: 'contains' },
    { value: 'starts_with',  label: 'starts with' },
    { value: 'ends_with',    label: 'ends with' },
    { value: 'is_null',      label: 'is null' },
    { value: 'is_not_null',  label: 'is not null' },
    { value: 'in',           label: 'in list' },
];
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <label class="field-label mb-0">Rules</label>
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Logic</span>
                <select :value="config.logic || 'AND'" @change="set('logic', $event.target.value)" class="text-xs border rounded-md px-2 py-1 bg-white font-bold text-indigo-600 focus:ring-0">
                    <option value="AND">AND</option>
                    <option value="OR">OR</option>
                </select>
            </div>
        </div>

        <div class="space-y-3">
            <div v-for="(rule, idx) in mappedRules" :key="idx" class="p-3 bg-gray-50 rounded-xl border border-gray-200 relative group">
                <button @click="removeRule(idx)" class="absolute -top-2 -right-2 w-6 h-6 bg-white shadow-sm border rounded-full flex items-center justify-center text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">
                    <TrashIcon class="w-3 h-3" />
                </button>

                <!-- Left side -->
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Input</label>
                    <TokenInputField 
                        :model-value="rule.leftStr" 
                        @update:modelValue="updateRule(idx, 'left', parseTokenString($event))"
                        :all-steps-before="allStepsBefore" 
                        placeholder="e.g. {{trigger.status}}" 
                    />
                </div>

                <!-- Operator -->
                <div class="my-2">
                    <select :value="rule.operator" @change="updateRule(idx, 'operator', $event.target.value)" class="v2-select !py-1 text-center font-semibold text-indigo-600">
                        <option v-for="op in OPERATORS" :key="op.value" :value="op.value">{{ op.label }}</option>
                    </select>
                </div>

                <!-- Right side -->
                <div v-if="rule.operator !== 'is_null' && rule.operator !== 'is_not_null'">
                    <label class="text-[10px] font-bold text-gray-400 uppercase mb-1 block">Compare With</label>
                    <TokenInputField 
                        :model-value="rule.rightStr" 
                        @update:modelValue="updateRule(idx, 'right', parseTokenString($event))"
                        :all-steps-before="allStepsBefore" 
                        placeholder="Value or {{token}}" 
                    />
                </div>

            </div>

            <button @click="addRule" class="v2-btn-outline w-full py-2 border-dashed">
                <PlusIcon class="w-4 h-4" /> Add Rule
            </button>
        </div>

        <div v-if="rules.length === 0" class="text-center py-8">
            <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-2 text-amber-500">
                <PlusIcon class="w-6 h-6" />
            </div>
            <p class="text-xs text-gray-400">No rules defined. This step will always follow the FALSE branch if no rules exist.</p>
        </div>
    </div>
</template>
