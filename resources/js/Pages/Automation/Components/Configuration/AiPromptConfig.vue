<script setup>
import { computed, onMounted, ref, watchEffect } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { fetchPrompts } from '../../Api/automationApi';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

const prompts = ref([]);
const isLoading = ref(false);

onMounted(async () => {
  try {
    isLoading.value = true;
    const page = await fetchPrompts({ per_page: 50 });
    prompts.value = page?.data || [];
  } finally {
    isLoading.value = false;
  }
});

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
      <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="AI Prompt name" />
    </div>

    <div>
      <label class="block text-xs font-medium text-gray-700">Prompt</label>
      <select v-model="step.prompt_id" class="mt-1 w-full border rounded px-2 py-1 text-sm">
        <option :value="null">Select a prompt…</option>
        <option v-for="p in prompts" :key="p.id" :value="p.id">{{ p.name }} v{{ p.version }}</option>
      </select>
      <p class="text-xs text-gray-500 mt-1">Choose which backend Prompt to use for this step.</p>
    </div>

    <div>
      <label class="block text-xs font-medium text-gray-700">Delay (minutes)</label>
      <input v-model.number="step.delay_minutes" type="number" min="0" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
    </div>

    <div>
      <label class="block text-xs font-medium text-gray-700">Notes</label>
      <textarea v-model="step.step_config.notes" rows="3" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Optional notes or variables…" />
    </div>

    <div class="pt-2">
      <button @click="save" type="button" class="px-3 py-1.5 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">Save Step</button>
    </div>
  </div>

  <div v-else class="text-gray-500 text-sm">No step selected.</div>
</template>
