<script setup>
import { computed, watchEffect } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

watchEffect(() => {
  if (step.value && !step.value.step_config) {
    step.value.step_config = {};
  }
});

const save = async () => {
  if (!step.value) return;
  await store.persistStep(step.value);
};
</script>

<template>
  <div v-if="step" class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-gray-700">Name</label>
      <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Trigger name" />
    </div>

    <div>
      <label class="block text-xs font-medium text-gray-700">Trigger Event</label>
      <input v-model="step.step_config.trigger_event" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g. new_lead_created" />
      <p class="text-xs text-gray-500 mt-1">Define the event that starts this workflow.</p>
    </div>

    <div class="pt-2">
      <button @click="save" type="button" class="px-3 py-1.5 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">Save Step</button>
    </div>
  </div>

  <div v-else class="text-gray-500 text-sm">No step selected.</div>
</template>
