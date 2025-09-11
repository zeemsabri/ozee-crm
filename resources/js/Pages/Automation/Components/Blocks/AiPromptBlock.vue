<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { MessageSquare } from 'lucide-vue-next';

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
      <MessageSquare class="w-5 h-5 text-purple-600" />
      <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'AI Prompt' }}</h3>
    </div>
    <div class="text-xs text-gray-600">
      <div>Prompt: <span class="font-medium">{{ step.step_config?.prompt_name || (step.prompt_id ? `#${step.prompt_id}` : 'Not set') }}</span></div>
      <div v-if="step.delay_minutes && step.delay_minutes > 0" class="mt-1 text-gray-500">Delay: {{ step.delay_minutes }} min</div>
    </div>
  </div>
</template>
