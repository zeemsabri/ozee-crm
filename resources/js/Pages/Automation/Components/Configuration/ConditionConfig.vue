<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

const addRule = () => {
  if (!step.value) return;
  if (!Array.isArray(step.value.condition_rules)) step.value.condition_rules = [];
  step.value.condition_rules.push({ field: '', operator: 'equals', value: '' });
};

const removeRule = (idx) => {
  if (!step.value) return;
  step.value.condition_rules.splice(idx, 1);
};

const save = async () => {
  if (!step.value) return;
  await store.persistStep(step.value);
};
</script>

<template>
  <div v-if="step" class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-gray-700">Name</label>
      <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Condition name" />
    </div>

    <div class="space-y-2">
      <div class="flex items-center justify-between">
        <label class="text-xs font-medium text-gray-700">Rules</label>
        <button type="button" class="text-xs px-2 py-1 rounded bg-gray-100 hover:bg-gray-200" @click="addRule">+ Add Rule</button>
      </div>

      <div v-if="step.condition_rules?.length" class="space-y-2">
        <div v-for="(rule, idx) in step.condition_rules" :key="idx" class="flex items-center gap-2">
          <input v-model="rule.field" type="text" class="w-1/3 border rounded px-2 py-1 text-sm" placeholder="Field" />
          <select v-model="rule.operator" class="w-1/3 border rounded px-2 py-1 text-sm">
            <option value="equals">equals</option>
            <option value="not_equals">not equals</option>
            <option value="contains">contains</option>
            <option value="gt">&gt;</option>
            <option value="lt">&lt;</option>
          </select>
          <input v-model="rule.value" type="text" class="w-1/3 border rounded px-2 py-1 text-sm" placeholder="Value" />
          <button type="button" class="text-xs text-red-600 hover:underline" @click="removeRule(idx)">Remove</button>
        </div>
      </div>

      <div v-else class="text-xs text-gray-500">No rules yet.</div>
    </div>

    <div class="pt-2">
      <button @click="save" type="button" class="px-3 py-1.5 text-sm rounded bg-blue-600 text-white hover:bg-blue-700">Save Step</button>
    </div>
  </div>

  <div v-else class="text-gray-500 text-sm">No step selected.</div>
</template>
