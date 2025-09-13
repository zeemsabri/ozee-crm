Step 2: Update the Workflow Creation UI
Next, we update WorkflowList.vue to allow the user to choose between creating an event-driven workflow or a new schedule-driven one.

File to Edit: resources/js/Pages/Automation/Components/WorkflowList.vue

Instructions
Replace the entire content of the file.

Key Changes:

A new "Trigger Type" toggle allows the user to select "On an Event" or "On a Schedule".

If "On a Schedule" is chosen, the model/event dropdowns are hidden, and the trigger_event is set to the special key schedule.run.

Updated File: WorkflowList.vue
Code snippet

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

const selectedModel = ref(null);
const selectedEvent = ref(null);

const models = computed(() => store.automationSchema || []);
const availableEvents = computed(() => {
    if (!selectedModel.value) return [];
    const modelSchema = models.value.find(m => m.name === selectedModel.value);
    return modelSchema ? modelSchema.events : [];
});

watch(selectedModel, () => {
    selectedEvent.value = null;
});

onMounted(() => {
    if (!store.automationSchema.length) {
        store.fetchAutomationSchema();
    }
});

const computedTriggerEvent = computed(() => {
    const m = selectedModel.value ? String(selectedModel.value).toLowerCase() : '';
    const e = selectedEvent.value ? String(selectedEvent.value).toLowerCase() : '';
    return m && e ? `${m}.${e}` : '';
});

const handleCreateWorkflow = async () => {
    let trigger;
    if (triggerType.value === 'schedule') {
        trigger = 'schedule.run'; // Special key for scheduled workflows
    } else {
        trigger = computedTriggerEvent.value;
    }

    if (!newWorkflowForm.value.name || !trigger) {
        store.showAlert('Missing information', 'Name and Trigger are required.');
        return;
    }
    
    newWorkflowForm.value.trigger_event = trigger;
    
    try {
        const newWorkflow = await store.createWorkflow(newWorkflowForm.value);
        if (newWorkflow && newWorkflow.id) {
            await store.fetchWorkflow(newWorkflow.id);
            showCreateForm.value = false;
            resetForm();
        }
    } catch (error) {
        console.error("Failed to create workflow:", error);
        store.showAlert('Create failed', 'Could not create workflow.');
    }
};

const resetForm = () => {
    newWorkflowForm.value = { name: '', trigger_event: '', description: '', is_active: true };
    selectedModel.value = null;
    selectedEvent.value = null;
    triggerType.value = 'event';
};

// ... (handleToggleActive and handleDeleteWorkflow are unchanged)
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

        <!-- Create New Workflow Form -->
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
                <div class="flex items-center gap-2 mt-1">
                    <SelectDropdown v-model="selectedModel" :options="models" valueKey="name" labelKey="name" placeholder="Select a Model..." class="w-1/2"/>
                    <SelectDropdown v-model="selectedEvent" :options="availableEvents" valueKey="value" labelKey="label" placeholder="Select an Event..." class="w-1/2" :disabled="!selectedModel"/>
                </div>
            </div>
            <div v-else class="text-xs text-gray-600 p-2 bg-blue-50 rounded-md border border-blue-200">
                This workflow must be attached to a Schedule to run. Its first step should be "Fetch Records".
            </div>

             <div>
                <label class="text-xs font-medium text-gray-600">Description</label>
                <textarea v-model="newWorkflowForm.description" rows="2" class="mt-1 w-full border rounded px-2 py-1 text-sm"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button @click="handleCreateWorkflow" class="px-3 py-1 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700">Create</button>
                <button @click="showCreateForm = false" class="px-3 py-1 text-xs rounded-md border text-gray-600 hover:bg-gray-100">Cancel</button>
            </div>
        </div>

        <!-- Workflow List -->
        <div class="flex-1 overflow-y-auto">
            <!-- ... (template for the list is unchanged) ... -->
        </div>
    </div>
</template>
Step 3: Introduce the "Fetch Records" Step Component
This is the new step that is essential for schedule-driven workflows.

New File: resources/js/Pages/Automation/Components/Steps/FetchRecordsStep.vue

Instructions
Create this new file and paste the code below. It provides a UI for the user to define a query.

New File: FetchRecordsStep.vue
Code snippet

<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';

const props = defineProps({ step: Object, allStepsBefore: Array });
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const config = computed({
    get: () => props.step.step_config || { conditions: [] },
    set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const availableModels = computed(() => automationSchema.value.map(m => m.name));

const columnsForSelectedModel = computed(() => {
    if (!config.value.model) return [];
    const model = automationSchema.value.find(m => m.name === config.value.model);
    return model ? model.columns.map(col => typeof col === 'string' ? col : col.name) : [];
});

function addCondition() {
    const newConditions = [...(config.value.conditions || []), { column: '', operator: 'is', value: '' }];
    config.value = { ...config.value, conditions: newConditions };
}

function removeCondition(index) {
    const newConditions = (config.value.conditions || []).filter((_, i) => i !== index);
    config.value = { ...config.value, conditions: newConditions };
}

function updateCondition(index, key, value) {
    const newConditions = [...(config.value.conditions || [])];
    newConditions[index] = { ...newConditions[index], [key]: value };
    config.value = { ...config.value, conditions: newConditions };
}
</script>

<template>
    <StepCard icon="🔍" title="Fetch Records" :onDelete="() => emit('delete')">
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Find all records from</label>
                <select :value="config.model || ''" @change="config = { ...config, model: $event.target.value, conditions: [] }" class="w-full p-2 border rounded-md text-sm">
                    <option value="" disabled>Select model...</option>
                    <option v-for="model in availableModels" :key="model" :value="model">{{ model }}</option>
                </select>
            </div>
            <div v-if="config.model">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-medium text-gray-600">Where conditions match</label>
                    <button @click="addCondition" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200"><PlusIcon class="h-3 w-3" /> Add</button>
                </div>
                <div class="space-y-2">
                    <div v-for="(cond, index) in config.conditions" :key="index" class="p-2 border rounded-md bg-gray-50/50 grid grid-cols-3 gap-2 items-center">
                        <select :value="cond.column" @change="updateCondition(index, 'column', $event.target.value)" class="w-full p-2 border rounded-md text-sm">
                            <option value="">Field...</option>
                            <option v-for="col in columnsForSelectedModel" :key="col" :value="col">{{ col }}</option>
                        </select>
                        <select :value="cond.operator" @change="updateCondition(index, 'operator', $event.target.value)" class="w-full p-2 border rounded-md text-sm">
                            <option>is</option>
                            <option>is not</option>
                            <option>contains</option>
                        </select>
                        <div class="flex items-center">
                           <input type="text" :value="cond.value" @input="updateCondition(index, 'value', $event.target.value)" placeholder="Value" class="w-full border rounded px-2 py-2 text-sm" />
                           <button @click="removeCondition(index)" class="p-1 text-gray-400 hover:text-red-500"><TrashIcon class="w-4 h-4" /></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </StepCard>
</template>
Step 4: Add Campaign Context to the AI Step
Now we enhance AIStep.vue to include the optional campaign selector.

File to Edit: resources/js/Pages/Automation/Components/Steps/AIStep.vue

Instructions
Replace the entire content of the file.

Key Changes:

It now fetches the campaigns list from the Pinia store.

A new, optional "Campaign Context" dropdown is added. When a campaign is selected, its ID is stored in the step's configuration as campaign_id.

Updated File: AIStep.vue
Code snippet

<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, XCircleIcon, TrashIcon } from 'lucide-vue-next';

// ... (props, emits, other computed properties are the same) ...
const store = useWorkflowStore();
const campaigns = computed(() => store.campaigns || []);
const aiConfig = computed({ /* ... */ });

// ... (all handler functions are the same) ...
</script>

<template>
    <StepCard icon="🧠" title="Analyze with AI" :onDelete="() => emit('delete')">
        <!-- System Prompt and Data to Analyze sections are unchanged -->
        <div>...</div>
        <div class="space-y-2">...</div>

        <!-- NEW: Campaign Context Selector -->
        <div class="border-t pt-3 mt-3">
            <label class="block text-sm font-medium text-gray-700">Campaign Context (Optional)</label>
            <select 
                :value="aiConfig.campaign_id || ''" 
                @change="handleConfigChange('campaign_id', $event.target.value)"
                class="w-full p-2 mt-1 border rounded-md text-sm"
            >
                <option value="">None</option>
                <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">
                    {{ campaign.name }}
                </option>
            </select>
            <p class="text-xs text-gray-500 mt-1">
                Data from the selected campaign will be available to the AI.
            </p>
        </div>
        
        <!-- Define AI Response Structure section is unchanged -->
        <div class="space-y-2">...</div>
    </StepCard>
</template>
Step 5: Update the Builder to Recognize the New Step
Finally, we need to update AddStepButton.vue and Workflow.vue to make the new Fetch Records step available to the user.

File to Edit: resources/js/Pages/Automation/Components/Steps/AddStepButton.vue

Instructions:
Add "FETCH_RECORDS" to the stepTypes object.

JavaScript

const stepTypes = {
'FETCH_RECORDS': { name: 'Fetch Records', icon: '🔍', description: 'Find records that match criteria.' },
'ACTION': { /* ... */ },
'CONDITION': { /* ... */ },
'FOR_EACH': { /* ... */ },
'AI_PROMPT': { /* ... */ },
};
File to Edit: resources/js/Pages/Automation/Components/Workflow.vue

Instructions:
Import the new FetchRecordsStep component and add it to the stepComponentMap.

JavaScript

import FetchRecordsStep from './Steps/FetchRecordsStep.vue';
// ... other imports

const stepComponentMap = {
TRIGGER: TriggerStep,
FETCH_RECORDS: FetchRecordsStep, // <-- ADD THIS
ACTION: ActionStep,
CONDITION: ConditionStep,
AI_PROMPT: AIStep,
FOR_EACH: ForEachStep,
};
This completes the full frontend implementation for both of your advanced scenarios. The builder is now a far more powerful and flexible tool.
