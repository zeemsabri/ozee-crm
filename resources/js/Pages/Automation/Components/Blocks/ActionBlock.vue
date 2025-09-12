<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Play, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    step: { type: Object, required: true },
});
const emit = defineEmits(['select']);

const store = useWorkflowStore();
const isSelected = computed(() => store.selectedStep && String(store.selectedStep.id) === String(props.step.id));

// A simple summary for the action block
const actionSummary = computed(() => {
    const actionType = props.step.step_config?.action_type;
    const actionLabels = {
        'CREATE_RECORD': 'Create Record',
        'UPDATE_RECORD': 'Update Record',
        'SEND_EMAIL': 'Send Email',
    };
    return actionType ? actionLabels[actionType] || actionType : 'Not configured';
});

const onClick = () => emit('select', props.step);

const onDelete = () => {
    store.showConfirm(
        'Delete Step?',
        'Are you sure you want to delete this step? This cannot be undone.',
        () => store.deleteStep(props.step)
    );
};
</script>

<template>
    <div
        class="relative rounded-lg border bg-white shadow-sm p-3 cursor-pointer hover:border-blue-400 transition-colors drag-handle"
        :class="{ 'border-blue-600 ring-2 ring-blue-200': isSelected }"
        @click="onClick"
    >
        <div class="flex items-center gap-3">
            <!-- Icon for the Action block -->
            <div class="p-1 bg-blue-100 rounded-md">
                <Play class="w-5 h-5 text-blue-600" />
            </div>
            <div>
                <h3 class="text-sm font-semibold text-gray-800">{{ step.name || 'Action' }}</h3>
                <p class="text-xs text-gray-500">
                    Type: <span class="font-medium text-gray-700">{{ actionSummary }}</span>
                </p>
            </div>
        </div>
        <!-- Delete Button -->
        <button @click.stop="onDelete" class="absolute top-1 right-1 p-1 text-gray-400 hover:text-red-600 rounded-full hover:bg-red-100/50" title="Delete Step">
            <Trash2 class="w-3 h-3" />
        </button>
    </div>
</template>
