<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useWorkflowStore } from '../Store/workflowStore';
import { Loader2, Plus } from 'lucide-vue-next';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { Link } from '@inertiajs/vue3';
import { confirmPrompt } from '@/Utils/notification.js';

const store = useWorkflowStore();

const workflows = computed(() => store.workflows);

const openWorkflow = (workflow) => {
    store.fetchWorkflow(workflow.id);
};

// --- Create New Workflow Form Logic ---
const showCreateForm = ref(false);
const triggerType = ref('event'); // 'event' or 'schedule'
const newWorkflowForm = ref({
    name: '',
    trigger_event: '',
    description: '',
    is_active: true,
});

// MODIFIED: Model/Event selectors to build trigger_event like `task.completed`
const selectedModel = ref(null);
const selectedEvent = ref(null);

// MODIFIED: Get models directly from the store's schema array. The schema is now the array itself.
const models = computed(() => store.automationSchema || []);

// MODIFIED: This is now a dynamic computed property.
const availableEvents = computed(() => {
    if (!selectedModel.value) return [];
    const modelSchema = models.value.find(m => m.name === selectedModel.value);
    return modelSchema ? modelSchema.events : [];
});

// When the selected model changes, reset the selected event.
watch(selectedModel, () => {
    selectedEvent.value = null;
});


onMounted(() => {
    // MODIFIED: Check if the schema array has length
    if (!store.automationSchema.length) {
        store.fetchAutomationSchema();
    }
});

const computedTriggerEvent = computed(() => {
    const m = selectedModel.value ? String(selectedModel.value).toLowerCase() : '';
    // MODIFIED: The event value is now the machine-readable name e.g., 'completed'
    const e = selectedEvent.value ? String(selectedEvent.value).toLowerCase() : '';
    return m && e ? `${m}.${e}` : '';
});

const useManualTrigger = computed(() => (models.value?.length || 0) === 0);

const canCreate = computed(() => {
    const nameOk = (newWorkflowForm.value.name || '').trim().length > 0;
    let trig = '';
    if (triggerType.value === 'schedule') {
        trig = 'schedule.run';
    } else {
        trig = useManualTrigger.value
            ? (newWorkflowForm.value.trigger_event || '').trim()
            : computedTriggerEvent.value;
    }
    return nameOk && !!trig;
});

const resetForm = () => {
    newWorkflowForm.value = {
        name: '',
        trigger_event: '',
        description: '',
        is_active: true,
    };
    selectedModel.value = null;
    selectedEvent.value = null;
    triggerType.value = 'event';
};

const handleCreateWorkflow = async () => {
    let trigger = '';
    if (triggerType.value === 'schedule') {
        trigger = 'schedule.run';
    } else {
        trigger = useManualTrigger.value
            ? (newWorkflowForm.value.trigger_event || '').trim().toLowerCase()
            : computedTriggerEvent.value;
    }
    newWorkflowForm.value.trigger_event = trigger;

    if (!canCreate.value) {
        store.showAlert('Missing information', 'Name and Trigger Event are required.');
        return;
    }
    try {
        const newWorkflow = await store.createWorkflow(newWorkflowForm.value);
        if (newWorkflow && newWorkflow.id) {
            await store.fetchWorkflow(newWorkflow.id);
            showCreateForm.value = false;
            resetForm();
        }
    } catch (error) {
        console.error("Failed to create workflow:", error);
        const msg = error?.response?.data?.message || 'Could not create workflow.';
        store.showAlert('Create failed', msg);
    }
};

const handleToggleActive = async (workflow) => {
    await store.toggleWorkflowActive(workflow);
};

const handleDeleteWorkflow = async (workflow) => {
    const ok = await confirmPrompt(`Delete workflow "${workflow.name}"?`, { confirmText: 'Delete', cancelText: 'Cancel', variant: 'danger' });
    if (!ok) return;
    await store.deleteWorkflow(workflow.id);
};
</script>

<template>
    <div class="h-full flex flex-col">
        <div class="p-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Workflows</h2>
            <button @click="showCreateForm = !showCreateForm" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100">
                <Plus class="w-4 h-4" />
            </button>
        </div>

        <div v-if="showCreateForm" class="p-3 border-b border-gray-200 bg-gray-50 space-y-3">
            <div>
                <label class="text-xs font-medium text-gray-600">Name</label>
                <input v-model="newWorkflowForm.name" type="text" class="mt-1 w-full border rounded px-2 py-1 text-sm" placeholder="e.g. New Lead Outreach">
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Trigger Type</label>
                <div class="flex items-center gap-2 mt-1 rounded-md bg-gray-200 p-1">
                    <button @click="triggerType = 'event'" :class="{'bg-white shadow': triggerType === 'event', 'text-gray-600': triggerType !== 'event'}" class="w-1/2 text-center text-xs py-1 rounded-md transition-colors">On an Event</button>
                    <button @click="triggerType = 'schedule'" :class="{'bg-white shadow': triggerType === 'schedule', 'text-gray-600': triggerType !== 'schedule'}" class="w-1/2 text-center text-xs py-1 rounded-md transition-colors">On a Schedule</button>
                </div>
            </div>

            <div v-if="triggerType === 'event'">
                <label class="text-xs font-medium text-gray-600">When...</label>
                <div v-if="!useManualTrigger" class="flex items-center gap-2 mt-1">
                    <SelectDropdown
                        v-model="selectedModel"
                        :options="models"
                        valueKey="name"
                        labelKey="name"
                        placeholder="Select a Model..."
                        class="w-1/2"
                    />
                    <SelectDropdown
                        v-model="selectedEvent"
                        :options="availableEvents"
                        valueKey="value"
                        labelKey="label"
                        placeholder="Select an Event..."
                        class="w-1/2"
                        :disabled="!selectedModel"
                    />
                </div>
                <p v-if="!useManualTrigger" class="text-[11px] text-gray-500 mt-1">This builds the trigger event for you. Preview: <span class="font-mono">{{ computedTriggerEvent || '—' }}</span></p>
                <div v-else class="mt-1">
                    <input v-model="newWorkflowForm.trigger_event" type="text" class="w-full border rounded px-2 py-1 text-sm" placeholder="e.g., user.created" />
                    <p class="text-[11px] text-amber-700 mt-1">Models list is empty. Type the trigger event manually (e.g., <span class="font-mono">user.updated</span>). Ensure it matches the global event name.</p>
                </div>
            </div>
            <div v-else class="text-xs text-gray-600 p-2 bg-blue-50 rounded-md border border-blue-200">
                This workflow must be attached to a Schedule to run. Its first step should be "Fetch Records".
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600">Description</label>
                <textarea v-model="newWorkflowForm.description" rows="2" class="mt-1 w-full border rounded px-2 py-1 text-sm"></textarea>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-xs text-gray-700">
                    <input type="checkbox" v-model="newWorkflowForm.is_active" class="border rounded" />
                    Active
                </label>
                <div class="flex items-center gap-2">
                    <button @click="handleCreateWorkflow" :disabled="!canCreate" class="px-3 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-blue-300">Create</button>
                    <button @click="showCreateForm = false" class="px-3 py-1 text-xs rounded-md border text-gray-600 hover:bg-gray-100">Cancel</button>
                </div>
            </div>
        </div>

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
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm flex items-center gap-2">
                                {{ workflow.name }}
                                <span class="text-[10px] px-1.5 py-0.5 rounded" :class="workflow.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'">
                                    {{ workflow.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-500">{{ workflow.trigger_event }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link @click.stop :href="route('schedules.create', { type: 'workflow', id: workflow.id })" class="text-xs px-2 py-1 rounded-md border text-gray-600 hover:bg-gray-100">Schedule</Link>
                            <button @click.stop="handleToggleActive(workflow)" class="text-xs px-2 py-1 rounded-md border text-gray-600 hover:bg-gray-100">
                                {{ workflow.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <button @click.stop="handleDeleteWorkflow(workflow)" class="text-xs px-2 py-1 rounded-md border text-red-600 hover:bg-red-50">Delete</button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
