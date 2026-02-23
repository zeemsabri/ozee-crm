<script setup>
import { computed } from 'vue';
import StepCard from './StepCard.vue';
import DataTokenInserter from './DataTokenInserter.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';

const props = defineProps({
    step: { type: Object, required: true },
    allStepsBefore: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
    onDelete: { type: Function, default: null },
});

const emit = defineEmits(['update:step', 'delete']);

const config = computed({
    get: () => {
        const c = props.step.step_config || {};
        if (!Array.isArray(c.variables)) {
            c.variables = [{ name: '', value: '' }];
        }
        return c;
    },
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

function addVariable() {
    config.value = {
        ...config.value,
        variables: [...config.value.variables, { name: '', value: '' }]
    };
}

function removeVariable(index) {
    const newVars = config.value.variables.filter((_, i) => i !== index);
    config.value = { ...config.value, variables: newVars };
}

function updateVariable(index, key, value) {
    const newVars = [...config.value.variables];
    newVars[index] = { ...newVars[index], [key]: value };
    config.value = { ...config.value, variables: newVars };
}

function insertToken(index, token) {
    const current = config.value.variables[index].value || '';
    updateVariable(index, 'value', current + token);
}
</script>

<template>
    <StepCard 
        icon="📝" 
        :title="props.step.name || 'Define Variables'" 
        :onDelete="onDelete"
        @update:title="newName => emit('update:step', { ...props.step, name: newName })"
    >
        <div class="space-y-4 p-2">
            <p class="text-sm text-gray-500">
                Extract data into named variables for use in later steps.
            </p>

            <div v-for="(variable, index) in config.variables" :key="index" class="space-y-2 p-3 border rounded-lg bg-gray-50 relative group">
                <button
                    v-if="config.variables.length > 1"
                    @click="removeVariable(index)"
                    class="absolute -top-2 -right-2 p-1 bg-white border rounded-full text-gray-400 hover:text-red-500 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                >
                    <TrashIcon class="w-3 h-3" />
                </button>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Variable Name</label>
                        <input
                            type="text"
                            :value="variable.name"
                            @input="updateVariable(index, 'name', $event.target.value)"
                            placeholder="e.g. project_id"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Value / Token</label>
                        <div class="relative">
                            <input
                                type="text"
                                :value="variable.value"
                                @input="updateVariable(index, 'value', $event.target.value)"
                                placeholder="{{loop.item.id}}"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-10"
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                                <DataTokenInserter
                                    :all-steps-before="allStepsBefore"
                                    :loop-context-schema="loopContextSchema"
                                    @insert="insertToken(index, $event)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button
                @click="addVariable"
                class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500"
            >
                <PlusIcon class="w-4 h-4" />
                Add another variable
            </button>
        </div>
    </StepCard>
</template>
