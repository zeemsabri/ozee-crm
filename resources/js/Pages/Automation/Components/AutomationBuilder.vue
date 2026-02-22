<script setup>
import { ref, onMounted, computed } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import Workflow from './Workflow.vue';
import WorkflowMinimap from './WorkflowMinimap.vue';
import TriggerSelectionModal from './Steps/TriggerSelectionModal.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import { MapIcon } from 'lucide-vue-next';

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
const showTriggerModal = ref(false);
const showMinimap = ref(false);

onMounted(async () => {
    // Ensure the automation schema is loaded for both Event and Schedule flows
    try {
        if (!store.automationSchema.length) {
            await store.fetchAutomationSchema();
        }
    } catch (_) { /* no-op */ }

    if (props.automationId) {
        // For existing workflows, fetch it and then populate state
        await store.fetchWorkflow(props.automationId);
        if (store.activeWorkflow) {
            // Deep copy to prevent direct mutation of store state
            workflowSteps.value = JSON.parse(JSON.stringify(store.activeWorkflow.steps));
            workflowName.value = store.activeWorkflow.name;
        }
    } else {
        // For new workflows, start with an empty array and open trigger modal
        store.activeWorkflow = null;
        workflowName.value = 'Untitled Automation';
        workflowSteps.value = [];
        showTriggerModal.value = true;
    }
});

function handleTriggerSelect(type) {
    let newTriggerStep;
    if (type === 'SCHEDULE_TRIGGER') {
        newTriggerStep = {
            id: `temp_${Date.now()}`,
            step_type: 'SCHEDULE_TRIGGER',
            name: 'Starts on a Schedule',
            step_config: { trigger_event: 'schedule.run' },
        };
    } else {
        newTriggerStep = {
            id: `temp_${Date.now()}`,
            step_type: 'TRIGGER',
            name: 'New Event Trigger',
            step_config: {},
        };
    }
    workflowSteps.value = [newTriggerStep];
    showTriggerModal.value = false;
}

const isTriggerConfigured = computed(() => {
    const trigger = workflowSteps.value[0];
    if (!trigger) return false;
    if (trigger.step_type === 'SCHEDULE_TRIGGER') return true; // schedule trigger is implicitly configured
    return !!(trigger.step_config && trigger.step_config.trigger_event);
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
        const children = stepData.children;
        delete stepData.if_true;
        delete stepData.if_false;
        delete stepData.children;

        stepData.step_order = index + 1;
        // Ensure delay_minutes is explicitly preserved (defaults to 0 if not set)
        if (stepData.delay_minutes === undefined || stepData.delay_minutes === null) {
            stepData.delay_minutes = 0;
        }
        if (parentId) {
            stepData.step_config = stepData.step_config || {};
            stepData.step_config._parent_id = parentId;
            stepData.step_config._branch = branch; // null for FOR_EACH children
        }

        flatList.push(stepData);

        if (step.step_type === 'CONDITION') {
            flatList = [
                ...flatList,
                ...flattenSteps(if_true || [], step.id, 'yes'),
                ...flattenSteps(if_false || [], step.id, 'no')
            ];
        }
        if (step.step_type === 'FOR_EACH') {
            flatList = [
                ...flatList,
                ...flattenSteps(children || [], step.id, null)
            ];
        }
    });
    return flatList;
};

async function saveAndActivate() {
    const triggerStep = workflowSteps.value[0] || {};
    const payloadTriggerEvent = triggerStep.step_type === 'SCHEDULE_TRIGGER'
        ? 'schedule.run'
        : (triggerStep.step_config?.trigger_event || null);

    // Transform steps for backend compatibility: convert SCHEDULE_TRIGGER → TRIGGER
    const allSteps = flattenSteps(workflowSteps.value).map(s => {
        if (s.step_type === 'SCHEDULE_TRIGGER') {
            return {
                ...s,
                step_type: 'TRIGGER',
                step_config: { ...(s.step_config || {}), trigger_event: 'schedule.run' },
            };
        }
        return s;
    });

    const payload = {
        name: workflowName.value,
        trigger_event: payloadTriggerEvent,
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

/** Scroll the main canvas to a step card by its ID */
function jumpToStep(stepId) {
    showMinimap.value = false;
    // Allow the sidebar close transition to start, then scroll
    setTimeout(() => {
        const el = document.getElementById(`step-card-${stepId}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Briefly highlight it
            el.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2');
            setTimeout(() => el.classList.remove('ring-2', 'ring-indigo-500', 'ring-offset-2'), 1500);
        }
    }, 200);
}

/** Total step count (flat) used for the badge on the toggle button */
const totalStepCount = computed(() => flattenSteps(workflowSteps.value).length);
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
                <div class="flex space-x-2 flex-shrink-0">
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
            <div class="p-2 sm:p-6 pb-40 overflow-x-auto">
                <div class="inline-block min-w-max">
                    <Workflow
                        :steps="workflowSteps"
                        @update:steps="workflowSteps = $event"
                        @add-trigger="showTriggerModal = true"
                    />
                    <!-- Bottom spacer to ensure dropdowns/menus near the end remain visible -->
                    <div class="h-60"></div>
                </div>
            </div>

            <TriggerSelectionModal
                v-if="showTriggerModal"
                @close="showTriggerModal = false"
                @select="handleTriggerSelect"
            />

            <!-- Floating Minimap Toggle Button -->
            <button
                v-if="workflowSteps.length > 0"
                @click="showMinimap = true"
                class="fixed bottom-8 right-8 z-30 flex items-center gap-2 rounded-full bg-indigo-600 text-white px-4 py-2.5 shadow-lg hover:bg-indigo-700 transition-all text-sm font-semibold group"
                title="Open Workflow Map"
            >
                <MapIcon class="h-4 w-4" />
                <span>Workflow Map</span>
                <span class="ml-1 bg-indigo-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                    {{ totalStepCount }}
                </span>
            </button>

            <!-- Minimap Sidebar using RightSidebar -->
            <RightSidebar
                v-model:show="showMinimap"
                title="Workflow Map"
                :initial-width="28"
                :min-width="20"
                :max-width="50"
            >
                <template #content>
                    <div class="space-y-3">
                        <!-- Header hint -->
                        <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                            💡 Click any step to jump to it on the canvas. Drag the left edge of this panel to resize.
                        </p>

                        <!-- Step count summary -->
                        <div class="flex items-center gap-2 text-xs text-gray-600 font-medium">
                            <span class="bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-bold">{{ totalStepCount }}</span>
                            steps total
                        </div>

                        <!-- The minimap tree -->
                        <WorkflowMinimap
                            :steps="workflowSteps"
                            @jump="jumpToStep"
                        />
                    </div>
                </template>
            </RightSidebar>
        </template>
    </div>
</template>
