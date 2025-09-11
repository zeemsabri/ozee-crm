<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Bot } from 'lucide-vue-next';

const props = defineProps({
    step: { type: Object, required: true },
});
const emit = defineEmits(['select']);

const store = useWorkflowStore();
const isSelected = computed(() => store.selectedStep && String(store.selectedStep.id) === String(props.step.id));

// This computed property looks up the prompt's name from the central store.
const promptName = computed(() => {
    if (!props.step.prompt_id) return 'Not set';
    const prompt = store.prompts.find(p => String(p.id) === String(props.step.prompt_id));
    return prompt ? `${prompt.name} v${prompt.version}` : `Prompt #${props.step.prompt_id}`;
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
            <div class="p-1 bg-purple-100 rounded-md">
                <Bot class="w-5 h-5 text-purple-600" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'AI Prompt' }}</h3>
                <p class="text-xs text-gray-500">
                    Prompt: <span class="font-medium text-gray-700">{{ promptName }}</span>
                </p>
            </div>
        </div>
    </div>
</template>

