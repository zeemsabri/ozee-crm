<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save, Plus, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);
const schema = computed(() => store.automationSchema);

// --- Component State & Computed Properties ---
const models = computed(() => schema.value?.models || []);
const localRules = ref([]);

// This computed property safely gets and sets the AND/OR logic type.
const conditionLogic = computed({
    get() {
        return step.value?.step_config?.logic || 'AND';
    },
    set(value) {
        if (step.value) {
            // ** THE FIX IS HERE **
            // This is a more robust check that ensures step_config is always an object,
            // even if the API returns an empty array [].
            if (!step.value.step_config || typeof step.value.step_config !== 'object' || Array.isArray(step.value.step_config)) {
                step.value.step_config = {};
            }
            step.value.step_config.logic = value;
        }
    }
});

// --- Data Synchronization Logic ---

watch(() => step.value?.id, (newId) => {
    if (newId && step.value && Array.isArray(step.value.condition_rules)) {
        localRules.value = step.value.condition_rules.map(rule => {
            const [model, column] = (rule.field || '').split('.');
            return {
                selectedModel: model || null,
                selectedColumn: column || null,
                operator: rule.operator || 'equals',
                value: rule.value || ''
            };
        });
    } else {
        localRules.value = [];
    }
}, { immediate: true });

watch(localRules, (newRules) => {
    if (step.value) {
        step.value.condition_rules = newRules.map(localRule => ({
            field: (localRule.selectedModel && localRule.selectedColumn)
                ? `${localRule.selectedModel}.${localRule.selectedColumn}`
                : '',
            operator: localRule.operator,
            value: localRule.value
        }));
    }
}, { deep: true });

// --- User Actions ---

const addRule = () => {
    localRules.value.push({
        selectedModel: null,
        selectedColumn: null,
        operator: 'equals',
        value: ''
    });
};

const removeRule = (index) => {
    localRules.value.splice(index, 1);
};

const save = async () => {
    if (!step.value) return;
    step.value.condition_rules = step.value.condition_rules.filter(rule => rule.field && rule.value !== '');
    await store.persistStep(step.value);
    toast.success("Step saved successfully!");
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

        <!-- Intuitive Rule Builder -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">If...</label>
                <button @click="addRule" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                    <Plus class="w-3 h-3" />
                    Add Rule
                </button>
            </div>

            <!-- ** NEW AND/OR LOGIC TOGGLE ** -->
            <div v-if="localRules.length > 1" class="p-1 bg-gray-200 rounded-md flex text-xs">
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


            <div v-if="localRules.length > 0" class="space-y-2">
                <!-- Rule Item -->
                <div v-for="(rule, index) in localRules" :key="index" class="p-2 border rounded-md bg-gray-50/50 space-y-2">
                    <div class="flex items-center gap-2">
                        <!-- Model Dropdown -->
                        <SelectDropdown
                            v-model="rule.selectedModel"
                            :options="models"
                            valueKey="name"
                            labelKey="name"
                            placeholder="Select Module..."
                            class="w-1/2"
                        />
                        <!-- Column Dropdown -->
                        <SelectDropdown
                            v-model="rule.selectedColumn"
                            :options="models.find(m => m.name === rule.selectedModel)?.columns.map(c => ({ value: c, label: c })) || []"
                            placeholder="Select Field..."
                            class="w-1/2"
                            :disabled="!rule.selectedModel"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Operator Dropdown -->
                        <SelectDropdown
                            v-model="rule.operator"
                            :options="[{value: 'equals', label: 'equals'}, {value: 'not_equals', label: 'not equals'}, {value: 'contains', label: 'contains'}]"
                            class="w-1/2"
                        />
                        <!-- Value Input -->
                        <input v-model="rule.value" type="text" class="w-1/2 border rounded px-2 py-1 text-sm" placeholder="Value" />
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

