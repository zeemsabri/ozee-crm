<script setup>
import { onMounted } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { Loader2 } from 'lucide-vue-next';

const store = useWorkflowStore();

onMounted(() => {
  // Load first page of workflows
  store.fetchWorkflows();
});

const openWorkflow = async (wf) => {
  await store.fetchWorkflow(wf.id);
};
</script>

<template>
  <div class="h-full flex flex-col border-r border-gray-200 bg-white">
    <div class="p-3 border-b border-gray-200">
      <h2 class="text-sm font-semibold text-gray-700">Workflows</h2>
    </div>

    <div class="flex-1 overflow-y-auto">
      <div v-if="store.isLoading" class="p-4 text-gray-500 flex items-center gap-2">
        <Loader2 class="w-4 h-4 animate-spin" /> Loading...
      </div>

      <ul>
        <li
          v-for="wf in store.workflows"
          :key="wf.id"
          class="px-3 py-2 cursor-pointer hover:bg-gray-50"
          :class="{
            'bg-blue-50': store.activeWorkflow && store.activeWorkflow.id === wf.id,
          }"
          @click="openWorkflow(wf)"
        >
          <div class="text-sm font-medium text-gray-800 truncate">{{ wf.name }}</div>
          <div class="text-xs text-gray-500 truncate" v-if="wf.description">{{ wf.description }}</div>
        </li>
      </ul>

      <div v-if="!store.isLoading && (!store.workflows || store.workflows.length === 0)" class="p-4 text-gray-500">
        No workflows yet.
      </div>
    </div>
  </div>
</template>
