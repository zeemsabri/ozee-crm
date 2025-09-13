<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import Workflow from './Workflow.vue';

const props = defineProps({
    automationId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['back']);

const store = useWorkflowStore();
const workflowSteps = ref([]);
const workflowName = ref('');

onMounted(async () => {
    if (props.automationId) {
        // For existing workflows, fetch it and then populate state
        await store.fetchWorkflow(props.automationId);
        if (store.activeWorkflow) {
            // Deep copy to prevent direct mutation of store state
            workflowSteps.value = JSON.parse(JSON.stringify(store.activeWorkflow.steps));
            workflowName.value = store.activeWorkflow.name;
        }
    } else {
        // For new workflows, initialize the state directly
        store.activeWorkflow = null;
        workflowName.value = 'Untitled Automation';
        workflowSteps.value = [{
            id: `temp_${Date.now()}`,
            step_type: 'TRIGGER',
            name: 'New Trigger',
            step_config: {},
        }];
    }
});

const isTriggerConfigured = computed(() => {
    const trigger = workflowSteps.value[0];
    return trigger && trigger.step_config && trigger.step_config.trigger_event;
});

const isReadyForSave = computed(() => {
    return isTriggerConfigured.value && workflowName.value.trim() !== '';
});

const flattenSteps = (steps, parentId = null, branch = null) => {
    let flatList = [];
    steps.forEach((step, index) => {
        const stepData = { ...step };
        const if_true = stepData.if_true;
        const if_false = stepData.if_false;
        delete stepData.if_true;
        delete stepData.if_false;

        stepData.step_order = index + 1;
        if (parentId) {
            stepData.step_config._parent_id = parentId;
            stepData.step_config._branch = branch;
        }

        flatList.push(stepData);

        if (step.step_type === 'CONDITION') {
            flatList = [
                ...flatList,
                ...flattenSteps(if_true || [], step.id, 'yes'),
                ...flattenSteps(if_false || [], step.id, 'no')
            ];
        }
    });
    return flatList;
};

async function saveAndActivate() {
    const triggerStep = workflowSteps.value[0];
    const allSteps = flattenSteps(workflowSteps.value);

    const payload = {
        name: workflowName.value,
        trigger_event: triggerStep.step_config.trigger_event,
        is_active: true,
        steps: allSteps,
    };

    try {
        if (props.automationId) {
            await store.updateWorkflow(props.automationId, payload);
        } else {
            await store.createWorkflow(payload);
        }
        emit('back');
    } catch (error) {
        console.error("Failed to save automation:", error);
        store.showAlert("Save Failed", "Could not save the automation.");
    }
}
</script>

<template>
    <div class="max-w-12xl mx-auto space-y-6">
        <div v-if="store.isLoading && automationId" class="text-center py-10">
            <p>Loading Automation...</p>
        </div>
        <template v-else>
            <div class="bg-white p-4 rounded-lg shadow-md border flex justify-between items-center sticky top-4 z-20">
                <input
                    type="text"
                    v-model="workflowName"
                    placeholder="Untitled Automation"
                    class="text-xl font-bold text-gray-800 focus:outline-none bg-transparent w-full"
                />
                <div class="flex space-x-2">
                    <button
                        @click="$emit('back')"
                        class="inline-flex items-center gap-x-2 rounded-md px-3.5 py-2 text-sm font-semibold shadow-sm bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="saveAndActivate"
                        :disabled="!isReadyForSave"
                        class="inline-flex items-center gap-x-2 rounded-md px-3.5 py-2 text-sm font-semibold shadow-sm bg-indigo-600 text-white hover:bg-indigo-500 focus-visible:outline-indigo-600 disabled:opacity-50"
                    >
                        Save and Activate
                    </button>
                </div>
            </div>
            <div class="p-2 sm:p-6">
                <Workflow :steps="workflowSteps" @update:steps="workflowSteps = $event" />
            </div>
        </template>
    </div>
</template>
