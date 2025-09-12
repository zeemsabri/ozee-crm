<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import StepRenderer from './StepRenderer.vue'; // Import our new component
import { Loader2 } from 'lucide-vue-next';

const store = useWorkflowStore();
const activeWorkflow = computed(() => store.activeWorkflow);

const addFirstStep = (type) => {
    // We tell the store to add the first step to the main `steps` array.
    store.addStep({ type, insertAfter: -1, parentArray: activeWorkflow.value.steps });
};
</script>

<template>
    <div class="h-full overflow-y-auto bg-gray-50 p-8">
        <div v-if="store.isLoading" class="h-full flex items-center justify-center text-gray-500">
            <Loader2 class="w-6 h-6 animate-spin mr-2" />
            <p>Loading Workflow...</p>
        </div>

        <div v-else-if="!activeWorkflow" class="h-full flex items-center justify-center text-gray-500">
            <p>Select a workflow from the left to begin building.</p>
        </div>

        <div v-else class="max-w-4xl mx-auto">
            <!-- The canvas now just uses the StepRenderer for the top-level steps -->
            <!-- ** THE FIX IS HERE ** -->
            <!-- This now checks that there's at least one step before rendering the list. -->
            <StepRenderer v-if="activeWorkflow.steps && activeWorkflow.steps.length > 0" :steps="activeWorkflow.steps" />

            <!-- "Empty Canvas" state for new workflows -->
            <div v-else class="text-center p-6 border-2 border-dashed rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700">Empty Workflow</h3>
                <p class="text-xs text-gray-500 mt-1 mb-3">Add the first step to get started.</p>
                <button @click="addFirstStep('TRIGGER')" class="px-3 py-1 text-xs font-semibold rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200">Add Trigger</button>
            </div>
        </div>
    </div>
</template>
