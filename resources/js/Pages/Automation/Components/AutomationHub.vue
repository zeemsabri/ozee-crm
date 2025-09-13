<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { PlusIcon, CheckCircleIcon, XCircleIcon, PencilIcon, TrashIcon } from 'lucide-vue-next';

const store = useWorkflowStore();
const workflows = computed(() => store.workflows);

const emit = defineEmits(['new', 'edit']);

const handleToggleActive = async (workflow) => {
    await store.toggleWorkflowActive(workflow);
};

const handleDeleteWorkflow = async (workflow) => {
    store.showConfirm(
        `Delete "${workflow.name}"?`,
        'This action cannot be undone.',
        async () => {
            await store.deleteWorkflow(workflow.id);
        }
    );
};
</script>

<template>
    <div class="max-w-5xl mx-auto space-y-8">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">Automation Hub</h1>
            <button
                @click="$emit('new')"
                class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
            >
                <PlusIcon class="h-5 w-5" />
                <span>Create New Automation</span>
            </button>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 border border-gray-200">
            <h2 class="text-xl font-bold text-gray-800 mb-4">My Automations</h2>
            <div class="space-y-3">
                <p v-if="!workflows.length && !store.isLoading" class="text-sm text-gray-500 text-center py-4">
                    You haven't created any automations yet.
                </p>
                <p v-if="store.isLoading" class="text-sm text-gray-500 text-center py-4">
                    Loading automations...
                </p>
                <div v-for="workflow in workflows" :key="workflow.id" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full" :class="workflow.is_active ? 'bg-green-100' : 'bg-gray-200'">
                            <span class="text-xl">🤖</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">{{ workflow.name }}</p>
                            <p class="text-xs text-gray-500 flex items-center space-x-1.5">
                                <span v-if="workflow.is_active" class="text-green-600 flex items-center gap-1"><CheckCircleIcon class="h-4 w-4" /> Active</span>
                                <span v-else class="text-gray-600 flex items-center gap-1"><XCircleIcon class="h-4 w-4" /> Inactive</span>
                                <span class="text-gray-400">&bull;</span>
                                <span>Trigger: {{ workflow.trigger_event }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <label :for="`toggle-${workflow.id}`" class="flex items-center cursor-pointer">
                            <div class="relative">
                                <input
                                    type="checkbox"
                                    :id="`toggle-${workflow.id}`"
                                    class="sr-only"
                                    :checked="workflow.is_active"
                                    @change="handleToggleActive(workflow)"
                                />
                                <div class="block bg-gray-300 w-12 h-6 rounded-full"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
                            </div>
                        </label>
                        <button @click="$emit('edit', workflow.id)" class="p-2 text-gray-500 hover:text-indigo-600 rounded-md hover:bg-gray-100" title="Edit">
                            <PencilIcon class="h-4 w-4" />
                        </button>
                        <button @click="handleDeleteWorkflow(workflow)" class="p-2 text-gray-500 hover:text-red-600 rounded-md hover:bg-gray-100" title="Delete">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- MODIFIED: Style block is now a top-level element -->
<style scoped>
input:checked ~ .dot {
    transform: translateX(1.5rem);
}
input:checked + .block {
    background-color: #4f46e5;
}
</style>
