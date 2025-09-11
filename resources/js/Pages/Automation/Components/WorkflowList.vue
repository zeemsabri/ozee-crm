<script setup>
import { computed, onMounted, ref } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { Loader2, Plus } from 'lucide-vue-next';

const store = useWorkflowStore();

// Get the reactive list of workflows directly from the store.
const workflows = computed(() => store.workflows);

// When a user clicks a workflow, tell the store to fetch its details.
const openWorkflow = (workflow) => {
    store.fetchWorkflow(workflow.id);
};

// --- Create New Workflow Form Logic ---
const showCreateForm = ref(false);
const newWorkflowForm = ref({
    name: '',
    trigger_event: '',
    description: '',
    is_active: true,
});

const resetForm = () => {
    newWorkflowForm.value = {
        name: '',
        trigger_event: '',
        description: '',
        is_active: true,
    };
};

const handleCreateWorkflow = async () => {
    if (!newWorkflowForm.value.name || !newWorkflowForm.value.trigger_event) {
        alert('Name and Trigger Event are required.');
        return;
    }
    try {
        const newWorkflow = await store.createWorkflow(newWorkflowForm.value);
        // After creating, automatically open it on the canvas.
        await store.fetchWorkflow(newWorkflow.id);
        showCreateForm.value = false;
        resetForm();
    } catch (error) {
        console.error("Failed to create workflow:", error);
        alert('Could not create workflow. Please check the console.');
    }
};
</script>

<template>
    <div class="h-full flex flex-col">
        <!-- Header -->
        <div class="p-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Workflows</h2>
            <button @click="showCreateForm = !showCreateForm" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100">
                <Plus class="w-4 h-4" />
            </button>
        </div>

        <!-- Create New Workflow Form (collapsible) -->
        <div v-if="showCreateForm" class="p-3 border-b border-gray-200 bg-gray-50 space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Name</label>
                <input v-model="newWorkflowForm.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g. New Lead Outreach">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Trigger Event</label>
                <input v-model="newWorkflowForm.trigger_event" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g. lead.created">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600">Description</label>
                <textarea v-model="newWorkflowForm.description" rows="2" class="mt-1 w-full border rounded px-2 py-1 text-sm"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <button @click="handleCreateWorkflow" class="px-3 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">Create</button>
                <button @click="showCreateForm = false" class="px-3 py-1 text-xs rounded-md border text-gray-600 hover:bg-gray-100">Cancel</button>
            </div>
        </div>

        <!-- Workflow List -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="store.isLoading && workflows.length === 0" class="p-4 text-sm text-gray-500 flex items-center gap-2">
                <Loader2 class="w-4 h-4 animate-spin" /> Loading Workflows...
            </div>
            <ul v-else>
                <li
                    v-for="workflow in workflows"
                    :key="workflow.id"
                    @click="openWorkflow(workflow)"
                    class="px-3 py-2 cursor-pointer border-b border-gray-100"
                    :class="{
            'bg-blue-50 text-blue-800 font-semibold': store.activeWorkflow?.id === workflow.id,
            'hover:bg-gray-50': store.activeWorkflow?.id !== workflow.id
          }"
                >
                    <p class="text-sm">{{ workflow.name }}</p>
                    <p class="text-xs text-gray-500">{{ workflow.trigger_event }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>
