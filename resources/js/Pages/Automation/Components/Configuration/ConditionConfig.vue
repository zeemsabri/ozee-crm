<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save, Plus, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import VariablePicker from '../VariablePicker.vue';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);
const schema = computed(() => store.automationSchema);

// --- Modes ---
const advancedMode = ref(false);

// --- Component State & Computed Properties ---
const models = computed(() => schema.value?.models || []);
const localRules = ref([]); // Simple mode rules
const advancedRules = ref([]); // Advanced mode rules

// AND/OR logic at group level (stored in step.step_config.logic)
const conditionLogic = computed({
    get() {
        return step.value?.step_config?.logic || 'AND';
    },
    set(value) {
        if (step.value) {
            if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
                step.value.step_config = {};
            }
            step.value.step_config.logic = value;
        }
    }
});

// --- Load step into local state ---
watch(() => step.value?.id, () => {
    if (!step.value) {
        localRules.value = [];
        advancedRules.value = [];
        advancedMode.value = false;
        return;
    }

    const cfg = step.value.step_config || {};
    const newRules = Array.isArray(cfg.rules) ? cfg.rules : [];
    if (newRules.length) {
        // Use advanced mode
        advancedMode.value = true;
        advancedRules.value = newRules.map(r => ({
            leftPath: r.left?.path || r.field || '',
            operator: r.operator || r.op || '==',
            rightType: (r.right?.type || (r.value !== undefined ? 'literal' : 'var')) === 'var' ? 'var' : 'value',
            rightValue: r.right?.value ?? r.value ?? '',
            rightPath: r.right?.path || ''
        }));
        // Keep simple rules empty to avoid saving them accidentally
        localRules.value = [];
    } else {
        advancedMode.value = false;
        // Map legacy simple rules
        if (Array.isArray(step.value.condition_rules)) {
            localRules.value = step.value.condition_rules.map(rule => {
                const [model, column] = (rule.field || '').split('.');
                return {
                    selectedModel: model || null,
                    selectedColumn: column || null,
                    operator: rule.operator || '==',
                    value: rule.value || ''
                };
            });
        } else {
            localRules.value = [];
        }
        advancedRules.value = [];
    }
}, { immediate: true });

// Keep legacy condition_rules synced only in Simple mode
watch(localRules, (newRules) => {
    if (!step.value || advancedMode.value) return;
    step.value.condition_rules = newRules.map(localRule => ({
        field: (localRule.selectedModel && localRule.selectedColumn)
            ? `${localRule.selectedModel}.${localRule.selectedColumn}`
            : '',
        operator: localRule.operator,
        value: localRule.value
    }));
}, { deep: true });

// --- User Actions ---

const addRule = () => {
    if (advancedMode.value) {
        advancedRules.value.push({ leftPath: '', operator: '==', rightType: 'value', rightValue: '', rightPath: '' });
    } else {
        localRules.value.push({
            selectedModel: null,
            selectedColumn: null,
            operator: '==',
            value: ''
        });
    }
};

const removeRule = (index) => {
    if (advancedMode.value) {
        advancedRules.value.splice(index, 1);
    } else {
        localRules.value.splice(index, 1);
    }
};

const save = async () => {
    if (!step.value) return;

    // Ensure step_config is an object
    if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
        step.value.step_config = {};
    }

    if (advancedMode.value) {
        // Persist new rule shape
        const rules = advancedRules.value
            .filter(r => (r.leftPath || '').trim() !== '' && (r.rightType === 'var' ? (r.rightPath || '').trim() !== '' : true))
            .map(r => ({
                left: { type: 'var', path: r.leftPath },
                operator: r.operator,
                right: r.rightType === 'var' ? { type: 'var', path: r.rightPath } : { type: 'literal', value: r.rightValue }
            }));
        step.value.step_config.rules = rules;
        // Clear legacy when using advanced to avoid confusion
        step.value.condition_rules = [];
    } else {
        // Clean legacy simple rules before save
        step.value.condition_rules = (step.value.condition_rules || []).filter(rule => rule.field && (rule.operator === 'truthy' || (rule.value !== '' && rule.value !== null && rule.value !== undefined)));
        // Remove new rules
        step.value.step_config.rules = [];
    }

    await store.persistStep(step.value);
    toast.success('Step saved successfully!');
    store.selectStep(null);
};

// --- Lifecycle Hooks ---
onMounted(() => {
    if (!Object.keys(store.automationSchema).length) {
        store.fetchAutomationSchema();
    }
});
</script>

<template>
    <div v-if="step" class="p-4 space-y-4">
        <!-- Name Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Name</label>
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Condition name" />
        </div>

        <!-- Mode Toggle -->
        <div class="p-1 bg-gray-200 rounded-md flex text-xs">
            <button @click="advancedMode = false" class="w-1/2 py-1 rounded-md transition-colors" :class="!advancedMode ? 'bg-white text-gray-800 shadow' : 'bg-transparent text-gray-500 hover:bg-gray-300/50'">Simple</button>
            <button @click="advancedMode = true" class="w-1/2 py-1 rounded-md transition-colors" :class="advancedMode ? 'bg-white text-gray-800 shadow' : 'bg-transparent text-gray-500 hover:bg-gray-300/50'">Context</button>
        </div>

        <!-- AND/OR LOGIC TOGGLE (shown when > 1 rule in current mode) -->
        <div v-if="(!advancedMode && localRules.length > 1) || (advancedMode && advancedRules.length > 1)" class="p-1 bg-gray-200 rounded-md flex text-xs">
            <button
                @click="conditionLogic = 'AND'"
                class="w-1/2 py-1 rounded-md transition-colors"
                :class="conditionLogic === 'AND' ? 'bg-white text-gray-800 shadow' : 'bg-transparent text-gray-500 hover:bg-gray-300/50'"
            >
                Match ALL (AND)
            </button>
            <button
                @click="conditionLogic = 'OR'"
                class="w-1/2 py-1 rounded-md transition-colors"
                :class="conditionLogic === 'OR' ? 'bg-white text-gray-800 shadow' : 'bg-transparent text-gray-500 hover:bg-gray-300/50'"
            >
                Match ANY (OR)
            </button>
        </div>

        <!-- Simple Mode Builder -->
        <div v-if="!advancedMode" class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">If...</label>
                <button @click="addRule" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                    <Plus class="w-3 h-3" />
                    Add Rule
                </button>
            </div>

            <div v-if="localRules.length > 0" class="space-y-2">
                <div v-for="(rule, index) in localRules" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                    <div class="flex items-center gap-2">
                        <SelectDropdown
                            v-model="rule.selectedModel"
                            :options="models"
                            valueKey="name"
                            labelKey="name"
                            placeholder="Select Module..."
                            class="w-1/2"
                        />
                        <SelectDropdown
                            v-model="rule.selectedColumn"
                            :options="(() => {
                                const m = models.find(m => m.name === rule.selectedModel);
                                if (!m) return [];
                                const cols = (m.columns || []).map(c => ({ value: c, label: c }));
                                const relCols = (m.relationships || []).flatMap(rel => (rel.columns || []).map(c => ({ value: `${rel.name}.${c}`, label: `${rel.name}.${c}` })));
                                return [...cols, ...relCols];
                            })()"
                            placeholder="Select Field..."
                            class="w-1/2"
                            :disabled="!rule.selectedModel"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <SelectDropdown
                            v-model="rule.operator"
                            :options="[
                                { value: '==', label: 'equals (==)' },
                                { value: '!=', label: 'not equals (!=)' },
                                { value: '>', label: '>' },
                                { value: '>=', label: '>=' },
                                { value: '<', label: '<' },
                                { value: '<=', label: '<=' },
                                { value: 'in', label: 'in (comma separated)' },
                                { value: 'not_in', label: 'not in (comma separated)' },
                                { value: 'truthy', label: 'is truthy' }
                            ]"
                            class="w-1/2"
                        />
                        <div class="w-1/2 flex items-center gap-2">
                            <input v-model="rule.value" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Value or {{ variable }}" />
                            <VariablePicker @select="val => rule.value = (rule.value || '') + (rule.value ? ' ' : '') + val" />
                        </div>
                    </div>
                    <div class="text-right">
                        <button @click="removeRule(index)" class="text-red-500 hover:text-red-700 p-1" title="Remove Rule">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="text-center text-xs text-gray-500 py-4 border-2 border-dashed rounded-lg">
                No rules defined. This step will always follow the "YES" path.
            </div>
        </div>

        <!-- Advanced Context Mode Builder -->
        <div v-else class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">If...</label>
                <button @click="addRule" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                    <Plus class="w-3 h-3" />
                    Add Rule
                </button>
            </div>

            <div v-if="advancedRules.length > 0" class="space-y-2">
                <div v-for="(r, index) in advancedRules" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                    <!-- Left variable -->
                    <div class="flex items-center gap-2">
                        <input v-model="r.leftPath" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="e.g., trigger.task.milestone.completion_date" />
                        <VariablePicker label="Pick left" @select="val => { const v = val || ''; r.leftPath = v.replace(/^{{\s*/, '').replace(/\s*}}$/, ''); }" />
                    </div>

                    <!-- Operator -->
                    <div class="flex items-center gap-2">
                        <SelectDropdown
                            v-model="r.operator"
                            :options="[
                                { value: '==', label: 'equals (==' },
                                { value: '!=', label: 'not equals (!=)' },
                                { value: '>', label: '>' },
                                { value: '>=', label: '>=' },
                                { value: '<', label: '<' },
                                { value: '<=', label: '<=' },
                                { value: 'in', label: 'in' },
                                { value: 'not_in', label: 'not in' },
                                { value: 'contains', label: 'contains' },
                                { value: 'truthy', label: 'is truthy' },
                                { value: 'empty', label: 'is empty' },
                                { value: 'not_empty', label: 'is not empty' }
                            ]"
                            class="w-1/3"
                        />
                        <!-- Right side mode toggle -->
                        <div class="w-2/3 flex items-center gap-2">
                            <div class="text-xs text-gray-600">Right:</div>
                            <button @click="r.rightType = 'value'" class="px-2 py-1 text-xs rounded-md" :class="r.rightType === 'value' ? 'bg-white shadow border' : 'bg-gray-100'">Value</button>
                            <button @click="r.rightType = 'var'" class="px-2 py-1 text-xs rounded-md" :class="r.rightType === 'var' ? 'bg-white shadow border' : 'bg-gray-100'">Variable</button>
                        </div>
                    </div>

                    <!-- Right side input -->
                    <div class="flex items-center gap-2">
                        <template v-if="r.rightType === 'value'">
                            <input v-model="r.rightValue" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Literal value or {{ template }}" />
                            <VariablePicker label="Insert" @select="val => r.rightValue = (r.rightValue || '') + (r.rightValue ? ' ' : '') + val" />
                        </template>
                        <template v-else>
                            <input v-model="r.rightPath" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="e.g., trigger.task.completion_date" />
                            <VariablePicker label="Pick right" @select="val => { const v = val || ''; r.rightPath = v.replace(/^{{\s*/, '').replace(/\s*}}$/, ''); }" />
                        </template>
                    </div>

                    <div class="text-right">
                        <button @click="removeRule(index)" class="text-red-500 hover:text-red-700 p-1" title="Remove Rule">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="text-center text-xs text-gray-500 py-4 border-2 border-dashed rounded-lg">
                No rules defined. This step will always follow the "YES" path.
            </div>
        </div>

        <!-- Save Button -->
        <div class="pt-2">
            <button @click="save" class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                <Save class="w-4 h-4" />
                Save Step
            </button>
        </div>
    </div>
</template>

