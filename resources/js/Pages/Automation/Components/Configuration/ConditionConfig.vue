<script setup>
import { computed, watchEffect } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save, Plus, Trash2 } from 'lucide-vue-next';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// This ensures that condition_rules is always an array, preventing errors.
watchEffect(() => {
    if (step.value && !Array.isArray(step.value.condition_rules)) {
        step.value.condition_rules = [];
    }
});

const addRule = () => {
    if (!step.value) return;
    step.value.condition_rules.push({
        field: '',
        operator: 'equals',
        value: ''
    });
};

const removeRule = (index) => {
    if (!step.value) return;
    step.value.condition_rules.splice(index, 1);
};

const save = async () => {
    if (!step.value) return;
    // Filter out any empty rules before saving
    step.value.condition_rules = step.value.condition_rules.filter(rule => rule.field && rule.value);
    await store.persistStep(step.value);
};
</script>

<template>
    <div v-if="step" class="p-4 space-y-4">
        <!-- Name Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Name</label>
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Condition name" />
        </div>

        <!-- Rule Builder -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-gray-700">Rules</label>
                <button @click="addRule" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
                    <Plus class="w-3 h-3" />
                    Add Rule
                </button>
            </div>

            <div v-if="step.condition_rules && step.condition_rules.length > 0" class="space-y-2">
                <div v-for="(rule, index) in step.condition_rules" :key="index" class="p-2 border rounded-md bg-gray-50/50">
                    <div class="grid grid-cols-3 gap-2">
                        <input v-model="rule.field" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Field" />
                        <select v-model="rule.operator" class="w-full border rounded px-2 py-1 text-sm">
                            <option value="equals">equals</option>
                            <option value="not_equals">not equals</option>
                            <option value="contains">contains</option>
                            <option value="gt">&gt;</option>
                            <option value="lt">&lt;</option>
                        </select>
                        <input v-model="rule.value" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="Value" />
                    </div>
                    <div class="text-right mt-1">
                        <button @click="removeRule(index)" class="text-red-500 hover:text-red-700 p-1">
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-xs text-gray-500 py-4 border-2 border-dashed rounded-lg">
                No rules defined. Click "Add Rule" to start.
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
