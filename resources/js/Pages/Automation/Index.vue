<script setup>
import { computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RightSidebar from '@/Components/RightSidebar.vue';

// The child components that make up our page
import WorkflowList from './Components/WorkflowList.vue';
import WorkflowCanvas from './Components/WorkflowCanvas.vue';

// The different configuration forms for the sidebar
import TriggerConfig from './Components/Configuration/TriggerConfig.vue';
import AiPromptConfig from './Components/Configuration/AiPromptConfig.vue';
import ConditionConfig from './Components/Configuration/ConditionConfig.vue';
import ActionConfig from './Components/Configuration/ActionConfig.vue'; // <-- New import

// The custom modal for alerts and confirmations
import ConfirmModal from './Components/ConfirmModal.vue';

// Import our new, stable Pinia store
import { useWorkflowStore } from './Store/workflowStore';

const store = useWorkflowStore();

// When the page first loads, tell the store to fetch the list of workflows.
onMounted(() => {
    store.fetchWorkflows();
});

// A computed property to dynamically set the title of the sidebar.
const sidebarTitle = computed(() => store.selectedStep?.name || 'Configure Step');

// This is the "magic" that determines which config component to show.
// It looks at the `step_type` of the selected step and returns the
// correct component to render.
const configComponent = computed(() => {
    if (!store.selectedStep) return null;

    const type = store.selectedStep.step_type?.toUpperCase();
    switch (type) {
        case 'TRIGGER':
            return TriggerConfig;
        case 'CONDITION':
            return ConditionConfig;
        case 'ACTION': // <-- New case for the action config form
            return ActionConfig;
        case 'AI_PROMPT':
        case 'ACTION_AI_PROMPT':
        default:
            return AiPromptConfig;
    }
});

// When the sidebar is closed, we tell the store to deselect any active step.
const handleSidebarVisibility = (isVisible) => {
    if (!isVisible) {
        store.selectStep(null);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="h-[calc(100vh-100px)] flex bg-gray-50">

            <!-- Left Panel: The list of all workflows -->
            <div class="w-72 border-r border-gray-200 bg-white">
                <WorkflowList />
            </div>

            <!-- Center Panel: The visual builder canvas -->
            <div class="flex-1">
                <WorkflowCanvas />
            </div>

        </div>

        <!-- Right Panel: The dynamic configuration sidebar -->
        <RightSidebar
            :show="!!store.selectedStep"
            :title="sidebarTitle"
            @update:show="handleSidebarVisibility"
        >
            <template #content>
                <!-- The `component :is` tag renders the correct config component -->
                <component :is="configComponent" v-if="configComponent" :step="store.selectedStep" />
            </template>
        </RightSidebar>

        <!-- Our custom confirmation modal, managed by the store -->
        <ConfirmModal />
    </AuthenticatedLayout>
</template>
