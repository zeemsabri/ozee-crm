<script setup>
import { computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RightSidebar from '@/Components/RightSidebar.vue';

import WorkflowList from './Components/WorkflowList.vue';
import WorkflowCanvas from './Components/WorkflowCanvas.vue';

import TriggerConfig from './Components/Configuration/TriggerConfig.vue';
import AiPromptConfig from './Components/Configuration/AiPromptConfig.vue';
import ConditionConfig from './Components/Configuration/ConditionConfig.vue';

import { useWorkflowStore } from './Store/workflowStore';

const store = useWorkflowStore();

onMounted(() => {
  // Ensure workflows are loaded when visiting the page
  store.fetchWorkflows();
});

const sidebarTitle = computed(() => store.selectedStep?.name || 'Configure Step');
const configComponent = computed(() => {
  const type = (store.selectedStep?.step_type || '').toUpperCase();
  switch (type) {
    case 'TRIGGER':
      return TriggerConfig;
    case 'CONDITION':
      return ConditionConfig;
    case 'AI_PROMPT':
    default:
      return AiPromptConfig;
  }
});

const handleSidebarVisibility = (val) => {
  if (!val) store.selectStep(null);
};
</script>

<template>
  <AuthenticatedLayout>
    <div class="h-[calc(100vh-100px)] flex bg-gray-50">
      <!-- Left Panel: Workflow List -->
      <div class="w-72 border-r border-gray-200 bg-white">
        <WorkflowList />
      </div>

      <!-- Center Panel: Canvas -->
      <div class="flex-1">
        <WorkflowCanvas />
      </div>
    </div>

    <!-- Right Panel: Dynamic Config Sidebar -->
    <RightSidebar
      :show="!!store.selectedStep"
      :title="sidebarTitle"
      @update:show="handleSidebarVisibility"
      @close="() => handleSidebarVisibility(false)"
    >
      <template #content>
        <component v-if="store.selectedStep" :is="configComponent" />
      </template>
    </RightSidebar>
  </AuthenticatedLayout>
</template>
