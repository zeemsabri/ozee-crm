<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { GitBranch } from 'lucide-vue-next';

const props = defineProps({
  step: { type: Object, required: true },
});
const emit = defineEmits(['select']);

const store = useWorkflowStore();
const isSelected = computed(() => store.selectedStep && String(store.selectedStep.id) === String(props.step.id));

const onClick = () => emit('select', props.step);
</script>

<template>
  <div
    class="rounded-lg border bg-white shadow-sm p-4 cursor-pointer hover:border-blue-300 transition-colors"
    :class="{ 'border-blue-500 ring-2 ring-blue-200': isSelected }"
    @click="onClick"
  >
    <div class="flex items-center gap-2 mb-2">
      <GitBranch class="w-5 h-5 text-green-600" />
      <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'Condition' }}</h3>
    </div>
    <p class="text-xs text-gray-500">Evaluates rules to branch workflow.</p>
  </div>
</template>
