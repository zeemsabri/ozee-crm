<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useWorkflowStore } from './Store/workflowStore';

// We will create these two components in the next steps.
import AutomationHub from './Components/AutomationHub.vue';
import AutomationBuilder from './Components/AutomationBuilder.vue';

const store = useWorkflowStore();
const view = ref('hub'); // Can be 'hub' or 'builder'
const selectedAutomationId = ref(null);

// When the component first loads, we need to fetch the list of workflows for the hub.
onMounted(() => {
  store.fetchWorkflows();
});

function showBuilder(automationId = null) {
  selectedAutomationId.value = automationId; // If null, it's a new automation
  view.value = 'builder';
}

function showHub() {
  selectedAutomationId.value = null;
  view.value = 'hub';
  // Re-fetch workflows in case one was just saved
  store.fetchWorkflows();
}
</script>

<template>
  <AuthenticatedLayout>
    <!-- The main content area will now dynamically switch between the hub and the builder -->
    <div class="p-4 sm:p-6 lg:p-8 font-sans">
      <template v-if="view === 'hub'">
        <AutomationHub @new="showBuilder()" @edit="showBuilder" />
      </template>
      <template v-else-if="view === 'builder'">
        <AutomationBuilder
            :automation-id="selectedAutomationId"
            @back="showHub"
        />
      </template>
    </div>
  </AuthenticatedLayout>
</template>
