<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useWorkflowStore } from '@/Pages/Automation/Store/workflowStore';
import { success, error } from '@/Utils/notification';
import PromptEditor from './PromptEditor.vue'; // <-- Import the new editor component
import { PlusIcon } from 'lucide-vue-next';

const store = useWorkflowStore();

// --- STATE MANAGEMENT ---
const view = ref('library'); // 'library' or 'editor'
const editingPrompt = ref(null);
const search = ref('');

// --- DATA FETCHING & FILTERING ---
onMounted(() => {
    if (!store.prompts.length) {
        store.fetchPrompts();
    }
});

const prompts = computed(() => store.prompts || []);

const groupedPrompts = computed(() => {
    const q = search.value.toLowerCase();
    const filtered = q
        ? prompts.value.filter(p => p.name?.toLowerCase().includes(q) || p.category?.toLowerCase().includes(q))
        : prompts.value;

    const groups = new Map();
    filtered.forEach(p => {
        if (!groups.has(p.name)) {
            groups.set(p.name, []);
        }
        groups.get(p.name).push(p);
    });

    return Array.from(groups.values()).map(versions => {
        versions.sort((a, b) => b.version - a.version);
        return { name: versions[0].name, latest: versions[0], allVersions: versions };
    });
});

// --- UI ACTIONS ---
function handleCreate() {
    editingPrompt.value = {
        name: 'New Prompt',
        category: 'General',
        version: 1,
        system_prompt_text: "You are a helpful AI assistant.\n\nUse the provided template variables like {{example_variable}} to craft your response.",
        model_name: 'gemini-2.5-flash-preview-05-20',
        generation_config: { temperature: 0.7, maxOutputTokens: 2048, responseMimeType: 'application/json' },
        template_variables: ['example_variable'],
        status: 'draft'
    };
    view.value = 'editor';
}

function handleEdit(prompt) {
    // Pass a deep copy to the editor to avoid modifying the list directly
    editingPrompt.value = JSON.parse(JSON.stringify(prompt));
    view.value = 'editor';
}

async function handleSave(promptToSave) {
    try {
        if (promptToSave.id && !promptToSave.isNewVersion) {
            await store.updatePrompt(promptToSave.id, promptToSave);
            success('Prompt updated successfully!');
        } else {
            // Logic for creating a new prompt or a new version of an existing one
            const { id, isNewVersion, ...payload } = promptToSave;
            await store.createPrompt(payload);
            success('Prompt created successfully!');
        }
        view.value = 'library';
        editingPrompt.value = null;
    } catch(e) {
        error('Failed to save prompt.');
        console.error(e);
    }
}

function handleCancel() {
    view.value = 'library';
    editingPrompt.value = null;
}

// --- HELPERS ---
const StatusBadge = ({ status }) => {
    const styles = {
        active: 'bg-green-100 text-green-800',
        draft: 'bg-yellow-100 text-yellow-800',
        archived: 'bg-gray-100 text-gray-600'
    };
    return `<span class="px-2 py-1 text-xs font-medium rounded-full ${styles[status]}">${status}</span>`;
};

const timeSince = (date) => {
    if (!date) return 'N/A';
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + " years ago";
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + " months ago";
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + " days ago";
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + " hours ago";
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + " minutes ago";
    return "just now";
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="p-4 sm:p-6 lg:p-8 font-sans">
            <!-- Editor View -->
            <div v-if="view === 'editor'">
                <PromptEditor
                    :prompt="editingPrompt"
                    @save="handleSave"
                    @cancel="handleCancel"
                />
            </div>

            <!-- Library View -->
            <div v-if="view === 'library'" class="max-w-7xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h1 class="text-3xl font-bold text-gray-900">Prompt Library</h1>
                    <button @click="handleCreate" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md shadow-sm hover:bg-indigo-700">
                        <PlusIcon class="h-5 w-5" />
                        Create New Prompt
                    </button>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="p-4">
                        <input v-model="search" type="text" placeholder="Search prompts by name or category..." class="w-full md:w-1/3 border-gray-300 rounded-md shadow-sm text-sm" />
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Latest Version</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Updated</th>
                                <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="store.isLoading">
                                <td colspan="6" class="text-center p-4 text-sm text-gray-500">Loading prompts...</td>
                            </tr>
                            <tr v-else-if="groupedPrompts.length === 0">
                                <td colspan="6" class="text-center p-4 text-sm text-gray-500">No prompts found.</td>
                            </tr>
                            <tr v-for="group in groupedPrompts" :key="group.name">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ group.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ group.latest.category }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">v{{ group.latest.version }}</td>
                                <td class="px-6 py-4 whitespace-nowrap" v-html="StatusBadge({ status: group.latest.status })"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ timeSince(group.latest.updated_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-4">
                                    <!-- A future 'View Versions' button could go here -->
                                    <button @click="handleEdit(group.latest)" class="font-semibold text-indigo-600 hover:text-indigo-800">Edit</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
