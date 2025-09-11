<script setup>
import { computed, onMounted, ref } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import { Plus, Save } from 'lucide-vue-next';

const store = useWorkflowStore();
const step = computed(() => store.selectedStep);

// The list of available prompts is now sourced directly from our central store.
const prompts = computed(() => store.prompts);

// --- Logic for the "Create New Prompt" Modal ---
const showCreateModal = ref(false);
const newPromptForm = ref({
    name: '',
    version: 1,
    system_prompt_text: '',
});
const isCreating = ref(false);

const handleCreatePrompt = async () => {
    if (!newPromptForm.value.name || !newPromptForm.value.system_prompt_text) {
        alert('Name and Prompt Text are required.');
        return;
    }
    try {
        isCreating.value = true;
        // We call the store's action, which handles the API call and updates the state.
        const createdPrompt = await store.createPrompt(newPromptForm.value);

        // Automatically select the new prompt in the dropdown.
        if (step.value) {
            step.value.prompt_id = createdPrompt.id;
        }

        // Close the modal and reset the form.
        showCreateModal.value = false;
        newPromptForm.value = { name: '', version: 1, system_prompt_text: '' };
    } catch (error) {
        console.error("Failed to create prompt:", error);
        alert('Could not create prompt. Please check the console.');
    } finally {
        isCreating.value = false;
    }
};

// When this component is first shown, we ensure the global list of prompts is loaded.
onMounted(() => {
    if (store.prompts.length === 0) {
        store.fetchPrompts();
    }
});

const save = async () => {
    if (!step.value) return;
    await store.persistStep(step.value);
    // Optionally, you can close the sidebar after saving
    // store.selectStep(null);
};
</script>

<template>
    <div v-if="step" class="p-4 space-y-4">
        <!-- Name Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Name</label>
            <input v-model="step.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="AI Prompt step name" />
        </div>

        <!-- Prompt Selection Dropdown -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Prompt</label>
            <div class="flex items-center gap-2 mt-1">
                <select v-model="step.prompt_id" class="w-full border rounded px-2 py-1 text-sm">
                    <option :value="null">Select a prompt…</option>
                    <option v-for="p in prompts" :key="p.id" :value="p.id">{{ p.name }} v{{ p.version }}</option>
                </select>
                <button @click="showCreateModal = true" class="p-2 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200" title="Create New Prompt">
                    <Plus class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Delay Input -->
        <div>
            <label class="block text-xs font-medium text-gray-700">Delay (minutes)</label>
            <input v-model.number="step.delay_minutes" type="number" min="0" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
        </div>

        <!-- Save Button -->
        <div class="pt-2">
            <button @click="save" class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                <Save class="w-4 h-4" />
                Save Step
            </button>
        </div>
    </div>

    <!-- "Create New Prompt" Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 z-40 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
            <h3 class="text-lg font-semibold text-gray-800">Create New Prompt</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Prompt Name</label>
                    <input v-model="newPromptForm.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g., New Lead Welcome Email" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Version</label>
                    <input v-model.number="newPromptForm.version" type="number" class="mt-1 w-full border rounded px-2 py-1 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">System Prompt Text</label>
                    <textarea v-model="newPromptForm.system_prompt_text" rows="8" class="mt-1 w-full border rounded px-2 py-1 text-sm font-mono" placeholder="You are a helpful assistant..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button @click="showCreateModal = false" class="px-4 py-2 text-sm rounded-md border text-gray-700 hover:bg-gray-50">Cancel</button>
                <button @click="handleCreatePrompt" :disabled="isCreating" class="px-4 py-2 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-blue-300">
                    {{ isCreating ? 'Creating...' : 'Create Prompt' }}
                </button>
            </div>
        </div>
    </div>
</template>

