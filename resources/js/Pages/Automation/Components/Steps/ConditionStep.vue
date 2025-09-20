<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';
import RelationshipPathPicker from '@/Components/RelationshipPathPicker.vue';


const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
});
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);
const morphMap = computed(() => store.morphMap || []);

const conditionConfig = computed({
    get: () => {
        const config = props.step.step_config || {};
        if (!Array.isArray(config.rules)) {
            config.rules = [{}];
        }
        if (!config.logic) {
            config.logic = 'AND';
        }
        return config;
    },
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

function updateRule(index, key, value) {
    const newRules = [...conditionConfig.value.rules];
    const newRule = { ...newRules[index] };

    if (key === 'field') {
        newRule.left = { type: 'var', path: value };
        delete newRule.operator;
        delete newRule.right;
    } else if (key === 'operator') {
        newRule.operator = value;
        delete newRule.right;
    } else if (key === 'value') {
        newRule.right = { type: 'literal', value: value };
    }

    newRules[index] = newRule;
    conditionConfig.value = { ...conditionConfig.value, rules: newRules };
}

function addRule() {
    const newRules = [...conditionConfig.value.rules, {}];
    conditionConfig.value = { ...conditionConfig.value, rules: newRules };
}

function removeRule(index) {
    const newRules = conditionConfig.value.rules.filter((_, i) => i !== index);
    conditionConfig.value = { ...conditionConfig.value, rules: newRules };
}

function setLogic(logic) {
    conditionConfig.value = { ...conditionConfig.value, logic: logic };
}


function getSelectedField(rule) {
    return rule?.left?.path || '';
}

function getSelectedOperator(rule) {
    return rule?.operator || '';
}

function getRuleValue(rule) {
    return rule?.right?.value ?? null;
}

// FIX: New helper function to safely clean the path for display in the template
function getCleanPath(path) {
    if (!path || typeof path !== 'string') return '';
    return path.replace(/{{|}}/g, '');
}

const triggerStep = computed(() => props.allStepsBefore.find(s => ['TRIGGER', 'SCHEDULE_TRIGGER'].includes(s.step_type)));

const availableFields = computed(() => {
    const fields = [];

    // 1. Workflow Context Fields
    fields.push({
        name: 'triggering_object_id',
        label: 'Triggering Object ID',
        type: 'Number',
        group: 'Workflow Context'
    });

    // 2. Trigger Fields
    const triggerModelName = triggerStep.value?.step_config?.model;
    if (triggerModelName) {
        const modelSchema = automationSchema.value.find(m => m.name === triggerModelName);
        if (modelSchema) {
            const modelKey = (triggerModelName.split('\\').pop() || '').toLowerCase();
            (modelSchema.columns || []).forEach(col => {
                const c = typeof col === 'string' ? { name: col } : col;
                fields.push({
                    name: `trigger.${modelKey}.${c.name}`,
                    label: `${triggerModelName}: ${c.label || c.name}`,
                    type: c.type || 'Text',
                    group: 'Trigger Data',
                    allowed_values: c.allowed_values,
                });
            });
        }
    } else if (triggerStep.value) {
        fields.push({ name: 'trigger.user.id', label: 'Triggering User ID', type: 'Number', group: 'Trigger Data' });
        fields.push({ name: 'trigger.email.type', label: 'Trigger Email Type', type: 'Text', group: 'Trigger Data' });
        fields.push({ name: 'trigger.email.status', label: 'Trigger Email Status', type: 'Text', group: 'Trigger Data' });
    }

    // 3. Previous Step Outputs
    props.allStepsBefore.forEach((s, index) => {
        if (s.step_type === 'AI_PROMPT' && s.step_config?.responseStructure?.length > 0) {
            s.step_config.responseStructure.forEach(field => {
                fields.push({
                    name: `step_${s.id}.${field.name}`,
                    label: `Step ${index + 1} (AI): ${field.name}`,
                    type: field.type,
                    group: `Step ${index + 1}: ${s.name}`,
                });
            });
        }
        if (s.step_type === 'FETCH_RECORDS') {
            fields.push({ name: `step_${s.id}.count`, label: `Step ${index + 1} (Fetch): Count`, type: 'Number', group: `Step ${index + 1}: ${s.name}`});
        }
    });

    return fields;
});

function getFieldSchema(fieldPath) {
    if (!fieldPath) return null;
    return availableFields.value.find(c => c.name === fieldPath)
        || { name: fieldPath, label: fieldPath, type: 'Text' };
}

// NEW: Helper to check if a path came from the relationship picker
function isRelationshipPath(path) {
    if (!path || typeof path !== 'string') return false;
    // A path is from the relationship picker if it's not a pre-defined field.
    return !availableFields.value.some(f => f.name === path);
}

// NEW: Helper to detect morph type columns for special UI handling
function isMorphTypeColumn(fieldPath) {
    if (!fieldPath || typeof fieldPath !== 'string') return false;
    // This is a heuristic: a column ending in `_type` is likely a morph type identifier.
    // A more robust solution would involve the backend schema explicitly flagging these columns.
    return fieldPath.endsWith('_type');
}

// NEW: Options for the morph type dropdown
const morphMapOptions = computed(() => (morphMap.value || []).map(m => ({ value: m.alias, label: m.label || m.alias })));


function getAvailableOperators(rule) {
    const path = getSelectedField(rule);

    if (isMorphTypeColumn(path)) {
        return [ { value: '==', label: 'is' }, { value: '!=', label: 'is not' }];
    }

    if (isRelationshipPath(path)) {
        const last = path.split('.').pop() || '';
        if (last.endsWith('_id') || last === 'id' || last.endsWith('_count')) {
            return operatorSets.Number;
        }
        return operatorSets.Text; // Default for unknown relationship fields
    }

    const type = getFieldSchema(path)?.type;
    return operatorSets[type] || operatorSets.Text;
}

const operatorSets = {
    'Array': [ { value: 'not_empty', label: 'is not empty' }, { value: 'empty', label: 'is empty' } ],
    'True/False': [ { value: '==', label: 'is' }, { value: '!=', label: 'is not' } ],
    'Number': [ { value: '==', label: 'equals' }, { value: '!=', label: 'does not equal' }, { value: '>', label: 'is greater than' }, { value: '<', label: 'is less than' }, { value: '>=', label: 'is greater than or equal to' }, { value: '<=', label: 'is less than or equal to' } ],
    'Date': [ { value: '==', label: 'is on' }, { value: '>', label: 'is after' }, { value: '<', label: 'is before' }, { value: 'in_past', label: 'is in the past' }, { value: 'in_future', label: 'is in the future' }, { value: 'today', label: 'is today' } ],
    'DateTime': [ { value: '==', label: 'is on' }, { value: '>', label: 'is after' }, { value: '<', label: 'is before' }, { value: 'in_past', label: 'is in the past' }, { value: 'in_future', label: 'is in the future' }, { value: 'today', label: 'is today' } ],
    'Text': [ { value: '==', label: 'is' }, { value: '!=', label: 'is not' }, { value: 'contains', label: 'contains' }, { value: 'not_empty', label: 'is not empty' }, { value: 'empty', label: 'is empty' } ],
};

function operatorRequiresValue(operator) {
    const op = String(operator || '').toLowerCase();
    return !['', 'empty', 'not_empty', 'in_past', 'in_future', 'today'].includes(op);
}

function getInputType(fieldPath) {
    const schema = getFieldSchema(fieldPath);
    if (!schema) return 'text';
    if (schema.type === 'Date') return 'date';
    if (schema.type === 'DateTime') return 'datetime-local';
    return 'text';
}
</script>

<template>
    <StepCard icon="🔀" title="If/Else Condition" :onDelete="() => emit('delete')">
        <div class="flex flex-col space-y-3 text-md p-2 bg-gray-50 rounded-md">
            <!-- AND/OR Logic Toggle -->
            <div class="flex items-center gap-2 bg-gray-100 p-1 rounded-md">
                <button @click="setLogic('AND')" :class="[conditionConfig.logic === 'AND' ? 'bg-white shadow-sm text-gray-800' : 'bg-transparent text-gray-500 hover:bg-gray-200']" class="flex-1 py-1 px-2 text-sm font-medium rounded-md transition-colors duration-150">
                    If ALL are true
                </button>
                <button @click="setLogic('OR')" :class="[conditionConfig.logic === 'OR' ? 'bg-white shadow-sm text-gray-800' : 'bg-transparent text-gray-500 hover:bg-gray-200']" class="flex-1 py-1 px-2 text-sm font-medium rounded-md transition-colors duration-150">
                    If ANY are true
                </button>
            </div>

            <!-- Rules Builder -->
            <div class="space-y-2">
                <div v-for="(rule, index) in conditionConfig.rules" :key="index" class="p-2 border rounded-md bg-white">
                    <div class="flex items-start gap-2">
                        <!-- Main Rule Inputs -->
                        <div class="flex-1 space-y-2">
                            <div class="flex items-center gap-2">
                                <SelectDropdown
                                    :model-value="getSelectedField(rule)"
                                    :options="availableFields"
                                    :group-by="'group'"
                                    placeholder="Select field..."
                                    @update:modelValue="val => updateRule(index, 'field', val)"
                                    class="w-full"
                                />
                                <RelationshipPathPicker
                                    mode="id"
                                    :all-steps-before="allStepsBefore"
                                    :value="getSelectedField(rule)"
                                    @select="val => updateRule(index, 'field', val)"
                                />
                            </div>

                            <!-- NEW: Display for relationship path to give user feedback -->
                            <p v-if="isRelationshipPath(getSelectedField(rule))" class="text-[11px] text-gray-600 font-mono mt-1 px-1 truncate" :title="getSelectedField(rule)">
                                Path: {{ getCleanPath(getSelectedField(rule)) }}
                            </p>

                            <div v-if="getSelectedField(rule)" class="grid grid-cols-2 gap-2">
                                <select :value="getSelectedOperator(rule)" @change="updateRule(index, 'operator', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm" :class="{ 'col-span-2': !operatorRequiresValue(getSelectedOperator(rule)) }">
                                    <option value="" disabled>Select condition...</option>
                                    <option v-for="op in getAvailableOperators(rule)" :key="op.value" :value="op.value">{{ op.label }}</option>
                                </select>

                                <template v-if="operatorRequiresValue(getSelectedOperator(rule))">
                                    <!-- NEW: Morph Type Dropdown -->
                                    <SelectDropdown
                                        v-if="isMorphTypeColumn(getSelectedField(rule))"
                                        :options="morphMapOptions"
                                        :model-value="getRuleValue(rule)"
                                        placeholder="Select type..."
                                        @update:modelValue="val => updateRule(index, 'value', val)"
                                    />
                                    <!-- Enum/status Dropdown -->
                                    <SelectDropdown
                                        v-else-if="getFieldSchema(getSelectedField(rule))?.allowed_values"
                                        :options="(getFieldSchema(getSelectedField(rule)).allowed_values || []).map(o => ({ value: o.value, label: o.label }))"
                                        :model-value="getRuleValue(rule)"
                                        placeholder="Select value..."
                                        @update:modelValue="val => updateRule(index, 'value', val)"
                                    />
                                    <!-- Boolean Dropdown -->
                                    <select v-else-if="getFieldSchema(getSelectedField(rule))?.type === 'True/False'" :value="getRuleValue(rule) ?? 'true'" @change="updateRule(index, 'value', $event.target.value)" class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm">
                                        <option value="true">True</option>
                                        <option value="false">False</option>
                                    </select>
                                    <!-- Standard Input -->
                                    <input v-else :type="getInputType(getSelectedField(rule))" :value="getRuleValue(rule) || ''" @input="updateRule(index, 'value', $event.target.value)" placeholder="Value..." class="p-2 border border-gray-300 rounded-md bg-white shadow-sm text-sm"/>
                                </template>
                            </div>
                        </div>

                        <!-- Delete Rule Button -->
                        <button @click="removeRule(index)" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full" title="Remove condition" :disabled="conditionConfig.rules.length <= 1" :class="{ 'opacity-50 cursor-not-allowed': conditionConfig.rules.length <= 1 }">
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Rule Button -->
            <div>
                <button @click="addRule" class="w-full flex items-center justify-center gap-1 px-2 py-1.5 text-xs rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600">
                    <PlusIcon class="h-3 w-3" />
                    Add Condition
                </button>
            </div>
        </div>
    </StepCard>
</template>

