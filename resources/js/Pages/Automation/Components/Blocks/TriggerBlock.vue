<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Bolt } from 'lucide-vue-next';

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
        class="rounded-lg border bg-white shadow-sm p-3 cursor-pointer hover:border-blue-400 transition-colors drag-handle"
        :class="{ 'border-blue-600 ring-2 ring-blue-200': isSelected }"
        @click="onClick"
    >
        <div class="flex items-center gap-3">
            <div class="p-1 bg-amber-100 rounded-md">
                <Bolt class="w-5 h-5 text-amber-600" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'Trigger' }}</h3>
                <p class="text-xs text-gray-500">
                    Event: <span class="font-medium text-gray-700">{{ step.step_config?.trigger_event || 'Not set' }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
