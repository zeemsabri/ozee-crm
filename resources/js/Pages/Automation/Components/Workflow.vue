<script setup>
import { computed } from 'vue';
import TriggerStep from './Steps/TriggerStep.vue';
import ConditionStep from './Steps/ConditionStep.vue';
import ActionStep from './Steps/ActionStep.vue';
import AIStep from './Steps/AIStep.vue';
import ForEachStep from './Steps/ForEachStep.vue';
import FetchRecordsStep from './Steps/FetchRecordsStep.vue';
import ScheduleTriggerStep from './Steps/ScheduleTriggerStep.vue';
import AddStepButton from './Steps/AddStepButton.vue';
import { useWorkflowStore } from '../Store/workflowStore';

const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const props = defineProps({
    steps: { type: Array, required: true },
    fullContextSteps: { type: Array, default: () => [] },
    loopContextSchema: { type: Object, default: null },
    allowTrigger: { type: Boolean, default: true },
});

const emit = defineEmits(['update:steps', 'add-trigger']);

// This map is now complete and correct. We are NOT using shallowRef.
const stepComponentMap = {
    TRIGGER: TriggerStep,
    SCHEDULE_TRIGGER: ScheduleTriggerStep,
    FETCH_RECORDS: FetchRecordsStep,
    CONDITION: ConditionStep,
    ACTION: ActionStep,
    AI_PROMPT: AIStep,
    FOR_EACH: ForEachStep,
};

const getStepComponent = (stepType) => stepComponentMap[stepType] || null;

function getLoopContextSchema(forEachStep) {
    const sourcePath = forEachStep.step_config?.sourceArray;
    if (!sourcePath) return null;
    const match = sourcePath.match(/{{step_(\w+)\.(.+)}}/);
    if (!match) return null;

    const sourceStepId = match[1];
    const sourceFieldName = match[2];

    const sourceStep = props.fullContextSteps.find(s => s.id == sourceStepId);

    // Case 1: Looping over AI Array of Objects
    if (sourceStep?.step_type === 'AI_PROMPT') {
        const sourceField = sourceStep.step_config?.responseStructure?.find(f => f.name === sourceFieldName);
        if (sourceField?.type !== 'Array of Objects') return null;
        return { name: 'Loop Item', columns: sourceField.schema || [] };
    }

    // Case 2: Looping over Fetch Records → records array
    if (sourceStep?.step_type === 'FETCH_RECORDS' && sourceFieldName === 'records') {
        const modelName = sourceStep.step_config?.model;
        if (!modelName) return null;
        const model = automationSchema.value.find(m => m.name === modelName);
        if (!model) return null;
        const cols = (model.columns || []).map(col => typeof col === 'string' ? { name: col } : col);
        return { name: 'Loop Item', columns: cols };
    }

    return null;
}

function handleUpdateStep(index, newStepData) {
    const newSteps = [...props.steps];
    newSteps[index] = newStepData;
    emit('update:steps', newSteps);
}

function handleAddStep(index, type) {
    const newStep = {
        id: `temp_${Date.now()}`,
        step_type: type,
        name: `New ${type.replace('_', ' ')} Step`,
        step_config: {},
    };

    // Prefill config for specific step types
    if (type === 'CONDITION') {
        newStep.if_true = [];
        newStep.if_false = [];
    }
    if (type === 'FOR_EACH') {
        newStep.children = [];
    }
    // UX: When adding another Fetch Records step, carry over the last selected model
    if (type === 'FETCH_RECORDS') {
        const priorSteps = props.steps.slice(0, index).slice().reverse();
        const lastFetch = priorSteps.find(s => s && s.step_type === 'FETCH_RECORDS' && s.step_config && s.step_config.model);
        if (lastFetch) {
            newStep.step_config = { model: lastFetch.step_config.model, conditions: [] };
        }
    }

    const newSteps = [...props.steps];
    newSteps.splice(index, 0, newStep);
    emit('update:steps', newSteps);
}

function handleDeleteStep(index) {
    const newSteps = props.steps.filter((_, i) => i !== index);
    emit('update:steps', newSteps);
}
</script>

<template>
    <div class="flex flex-col items-center w-full space-y-4">
        <!-- NEW: Empty state for a blank canvas -->
        <div v-if="steps.length === 0" class="text-center p-8 border-2 border-dashed rounded-lg w-full max-w-md">
            <template v-if="allowTrigger">
                <h3 class="text-lg font-semibold text-gray-700">Start your Automation</h3>
                <p class="text-sm text-gray-500 mt-1 mb-4">Every workflow starts with a trigger. How should this one begin?</p>
                <button @click="$emit('add-trigger')" class="px-4 py-2 text-sm font-semibold rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                    Add Trigger
                </button>
            </template>
            <template v-else>
                <h3 class="text-lg font-semibold text-gray-700">Start this flow</h3>
                <p class="text-sm text-gray-500 mt-1 mb-4">Choose a step to add in this branch.</p>
                <div class="flex justify-center">
                    <AddStepButton @select="(type) => handleAddStep(0, type)" />
                </div>
            </template>
        </div>

        <template v-else>
            <template v-for="(step, index) in steps" :key="step.id">
                <div class="w-full flex flex-col items-center">
                    <!-- Renders the main card for the current step -->
                    <component
                        :is="getStepComponent(step.step_type)"
                        v-if="getStepComponent(step.step_type)"
                        :step="step"
                        :all-steps-before="[...fullContextSteps, ...steps.slice(0, index)]"
                        :loop-context-schema="loopContextSchema"
                        :onDelete="index > 0 ? () => handleDeleteStep(index) : null"
                        @update:step="handleUpdateStep(index, $event)"
                    />

                    <!-- Renders the nested branches for IF/ELSE -->
                    <div v-if="step.step_type === 'CONDITION'" class="w-full flex mt-4 space-x-4">
                        <div class="flex-1 bg-green-50/50 p-4 rounded-lg border border-green-200">
                            <p class="text-center font-bold text-green-700 mb-4">IF YES</p>
                            <Workflow
                                :steps="step.if_true || []"
                                @update:steps="handleUpdateStep(index, { ...step, if_true: $event })"
                                :full-context-steps="[...fullContextSteps, ...steps.slice(0, index + 1)]"
                                :loop-context-schema="loopContextSchema"
                                :allow-trigger="false"
                            />
                        </div>
                        <div class="flex-1 bg-red-50/50 p-4 rounded-lg border border-red-200">
                            <p class="text-center font-bold text-red-700 mb-4">IF NO</p>
                            <Workflow
                                :steps="step.if_false || []"
                                @update:steps="handleUpdateStep(index, { ...step, if_false: $event })"
                                :full-context-steps="[...fullContextSteps, ...steps.slice(0, index + 1)]"
                                :loop-context-schema="loopContextSchema"
                                :allow-trigger="false"
                            />
                        </div>
                    </div>

                    <!-- Renders the nested container for FOR EACH -->
                    <div v-if="step.step_type === 'FOR_EACH'" class="w-full mt-4 p-4 rounded-lg border border-purple-300 bg-purple-50/50">
                        <p class="text-center font-bold text-purple-700 mb-4">DO THIS FOR EACH ITEM</p>
                        <Workflow
                            :steps="step.children || []"
                            @update:steps="handleUpdateStep(index, { ...step, children: $event })"
                            :full-context-steps="[...fullContextSteps, ...steps.slice(0, index + 1)]"
                            :loop-context-schema="getLoopContextSchema(step)"
                            :allow-trigger="false"
                        />
                    </div>

                </div>
                <!-- Renders the "+" button to add the NEXT step -->
                <AddStepButton @select="(type) => handleAddStep(index + 1, type)" />
            </template>
        </template>
    </div>
</template>
