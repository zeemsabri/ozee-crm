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

// A simple computed property to provide a summary of the rules.
const rulesSummary = computed(() => {
    const count = props.step.condition_rules?.length || 0;
    if (count === 0) return 'No rules defined';
    if (count === 1) return '1 rule';
    return `${count} rules`;
});

const onClick = () => emit('select', props.step);
</script>

<template>
    <div
        class="rounded-lg border bg-white shadow-sm p-3 cursor-pointer hover:border-blue-400 transition-colors drag-handle"
        :class="{ 'border-blue-600 ring-2 ring-blue-200': isSelected }"
        @click="onClick"
    >
        <div class="flex items-center gap-3">
            <div class="p-1 bg-green-100 rounded-md">
                <GitBranch class="w-5 h-5 text-green-600" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'Condition' }}</h3>
                <p class="text-xs text-gray-500">
                    Logic: <span class="font-medium text-gray-700">{{ rulesSummary }}</span>
                </p>
            </div>
        </div>
    </div>
</template>
