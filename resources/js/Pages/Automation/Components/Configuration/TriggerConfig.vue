<script setup>
import { computed, watchEffect } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Save } from 'lucide-vue-next';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// This ensures that step_config is always an object, preventing errors.
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
    <div v-if="step" class="p-4 space-y-4">
        <!-- Name Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Name</label>
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="Trigger name" />
        </div>

        <!-- Trigger Event Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Trigger Event</label>
            <input v-model="step.step_config.trigger_event" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g. lead.created" />
            <p class="text-xs text-gray-500 mt-1">The system event that starts this workflow.</p>
        </div>

        <!-- Save Button -->
        <div class="pt-2">
            <button @click="save" class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                <Save class="w-4 h-4" />
                Save Step
            </button>
        </div>
    </div>
</template>
