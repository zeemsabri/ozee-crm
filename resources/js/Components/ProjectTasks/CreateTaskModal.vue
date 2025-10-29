<script setup>
import { reactive, ref, watch, computed, onMounted } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import MilestoneFormModal from './MilestoneFormModal.vue'; // Import the MilestoneFormModal
import PrimaryButton from '@/Components/PrimaryButton.vue';
import * as notification from '@/Utils/notification.js';
import SchedulePickerModal from '@/Components/Scheduler/SchedulePickerModal.vue';
import { useEmbeddedScheduler } from '@/Composables/useEmbeddedScheduler.js';

const props = defineProps({
    show: Boolean,
    projectId: { // Optional projectId
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const taskForm = reactive({
    name: '',
    description: '',
    assigned_to_user_id: null,
    due_date: null,
    status: 'To Do',
    task_type_id: null,
    milestone_id: null,
    project_id: props.projectId, // Initialize with prop
    priority: 'medium', // Default priority
    needs_approval: false, // Whether this task needs approval
    requires_qa: false, // Whether this task requires QA verification after completion
    tags: [], // For tag IDs
    tags_data: [], // For tag objects with id and name
});

const taskStatuses = ['To Do', 'In Progress', 'Done', 'Blocked', 'Archived'];
const priorityOptions = [
    { value: 'low', label: 'Low', icon: 'M13 17l-5-5 1.4-1.4 3.6 3.6 7.6-7.6L22 8l-9 9z' }, // Placeholder simple check, can be replaced
    { value: 'medium', label: 'Medium', icon: 'M6 12h12' }, // Placeholder simple dash
    { value: 'high', label: 'High', icon: 'M5 9l7-7 7 7' }, // Placeholder simple up arrow
];

// Data for dropdowns, fetched dynamically
const projects = ref([]);
const projectUsers = ref([]);
const taskTypes = ref([]);
const milestones = ref([]);

// Loading states
const loadingProjects = ref(true);
const loadingProjectUsers = ref(false);
const loadingTaskTypes = ref(false);
const loadingMilestones = ref(false);

// State for MilestoneFormModal visibility
const showMilestoneModal = ref(false);

// Refs for toggling optional "add-on" fields
const showTagsInput = ref(false);
const showAttachmentsInput = ref(false);
const showScheduleInput = ref(false);
const showApprovalInput = ref(false);
const showQaInput = ref(false);

// Computed properties for BaseFormModal
const modalTitle = 'Create New Task';
const apiEndpoint = '/api/tasks';
const httpMethod = 'post';
const submitButtonText = 'Create Task';
const successMessage = 'Task created successfully!';

// Embedded Scheduler state and helpers
const { showScheduleModal, scheduleDraft, open, close, onSaveDraft, attachAfterCreate } = useEmbeddedScheduler();

const scheduleSummary = computed(() => {
    const d = scheduleDraft.value;
    if (!d) return null;
    const time = d.time || '00:00';
    switch (d.mode) {
        case 'once':
            return `One-time at ${String(d.start_at || '').replace('T', ' ')}`;
        case 'daily':
            return `Daily at ${time}`;
        case 'weekly': {
            const names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            const label = (d.days_of_week || []).map(i => names[i]).join(', ');
            return `Weekly on ${label || '—'} at ${time}`;
        }
        case 'monthly':
            if (d.day_of_month) return `Monthly on day ${d.day_of_month} at ${time}`;
            return `Monthly on the ${['First','Second','Third','Fourth','Fifth'][(d.nth||1)-1]} ${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.dow_for_monthly||1]} at ${time}`;
        case 'yearly': {
            const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return `Yearly on ${monthNames[(d.month||1)-1]} ${d.day_of_month||1} at ${time}`;
        }
        case 'cron':
            return `Custom cron: ${d.cron || '* * * * *'}`;
    }
    return null;
});

// Before submit, if a schedule draft exists, send it nested so backend creates it atomically
const beforeSubmit = async () => {
    if (scheduleDraft.value) {
        taskForm.schedule = { ...scheduleDraft.value };
    } else if (taskForm.schedule) {
        delete taskForm.schedule;
    }
    return true;
};

// Watch for changes in `show` prop to initialize form data and fetch necessary data
watch(() => props.show, async (newValue) => {
    if (newValue) {
        // Reset form
        Object.assign(taskForm, {
            name: '',
            description: '',
            assigned_to_user_id: null,
            due_date: null,
            status: 'To Do',
            task_type_id: null,
            milestone_id: null,
            project_id: props.projectId, // Re-set project_id from prop
            priority: 'medium', // Reset to default priority
            needs_approval: false,
            requires_qa: false,
            tags: [], // Reset tags
            tags_data: [], // Reset tags data
        });

        // Reset add-on visibility
        showTagsInput.value = false;
        showAttachmentsInput.value = false;
        showScheduleInput.value = false;
        showApprovalInput.value = false;
        showQaInput.value = false;

        // If no projectId passed, fetch projects for selection
        if (!props.projectId) {
            await fetchProjects();
        } else {
            // If projectId is passed, fetch data for that specific project
            await fetchAssociatedData(props.projectId);
        }
    }
}, { immediate: true });


// --- WIZARD LOGIC ---
// These watchers create the cascading, wizard-like flow.
// If a user goes back and changes a parent field, the child fields are reset and hidden.
watch(() => taskForm.description, (newVal) => {
    if (newVal.length < 10) taskForm.milestone_id = null;
});
watch(() => taskForm.milestone_id, () => {
    taskForm.due_date = null;
});
watch(() => taskForm.due_date, () => {
    taskForm.assigned_to_user_id = null;
});
watch(() => taskForm.assigned_to_user_id, () => {
    taskForm.task_type_id = null;
});
watch(() => taskForm.task_type_id, () => {
    taskForm.priority = 'medium'; // Reset to default
});


// Watch for changes in taskForm.project_id to fetch associated data
watch(() => taskForm.project_id, async (newProjectId) => {
    if (newProjectId) {
        await fetchAssociatedData(newProjectId);
    } else {
        projectUsers.value = [];
        taskTypes.value = [];
        milestones.value = [];
    }
});

const fetchProjects = async () => {
    loadingProjects.value = true;
    try {
        const response = await window.axios.get('/api/projects-simplified');
        projects.value = response.data.map(p => ({ value: p.id, label: p.name }));
    } catch (error) {
        console.error('Error fetching projects:', error);
        projects.value = [];
    } finally {
        loadingProjects.value = false;
    }
};

const fetchAssociatedData = async (projectId) => {
    if (!projectId) return;
    loadingProjectUsers.value = true;
    loadingTaskTypes.value = true;
    loadingMilestones.value = true;
    try {
        const [usersRes, typesRes, milestonesRes] = await Promise.all([
            window.axios.get(`/api/projects/${projectId}/users`),
            window.axios.get('/api/task-types'),
            window.axios.get(`/api/projects/${projectId}/milestones`),
        ]);
        projectUsers.value = usersRes.data.map(user => ({ value: user.id, label: user.name }));
        taskTypes.value = typesRes.data.map(type => ({ value: type.id, label: type.name }));
        milestones.value = milestonesRes.data; // Store full milestone object
    } catch (error) {
        console.error('Error fetching associated task data:', error);
        projectUsers.value = [];
        taskTypes.value = [];
        milestones.value = [];
    } finally {
        loadingProjectUsers.value = false;
        loadingTaskTypes.value = false;
        loadingMilestones.value = false;
    }
};

// --- Due Date Constraints ---
const minDueDate = computed(() => new Date().toISOString().split('T')[0]);

const maxDueDate = computed(() => {
    if (!taskForm.milestone_id) return '';
    const selectedMilestone = milestones.value.find(m => m.id === taskForm.milestone_id);
    if (!selectedMilestone || !selectedMilestone.completion_date) return '';
    return selectedMilestone.completion_date.split('T')[0]; // Format to YYYY-MM-DD
});


// --- FILE HANDLING & SAVE ---
const selectedFiles = ref([]);
const onFilesChange = (e) => {
    selectedFiles.value = Array.from(e.target.files || []);
};
const uploadTaskFiles = async (taskId) => {
    if (!selectedFiles.value.length) return;
    const form = new FormData();
    selectedFiles.value.forEach((f, i) => form.append(`files[${i}]`, f));
    form.append('model_type', 'Task');
    form.append('model_id', taskId);
    try {
        await window.axios.post('/api/files', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        notification.success('Files uploaded to Google Drive.');
    } catch (e) {
        console.error('Failed to upload task files', e);
        notification.error('Failed to upload files.');
    }
};
const handleSaved = async (responseData) => {
    try {
        const taskId = responseData?.id || responseData?.task?.id || responseData?.data?.id;
        if (taskId) {
            await uploadTaskFiles(taskId);
            const alreadyAttached = !!(responseData?.attached_schedule_id || responseData?.data?.attached_schedule_id);
            if (!alreadyAttached && scheduleDraft.value) {
                await attachAfterCreate('task', taskId);
            }
        }
    } finally {
        scheduleDraft.value = null;
        emit('saved', responseData);
        emit('close');
        selectedFiles.value = [];
    }
};


// --- MODAL CONTROLS ---
const closeModal = () => {
    emit('close');
};
const openMilestoneModal = () => {
    if (taskForm.project_id) {
        showMilestoneModal.value = true;
    } else {
        alert('Please select a project before adding a milestone.');
    }
};
const handleMilestoneSaved = (newMilestone) => {
    showMilestoneModal.value = false;
    if (newMilestone && newMilestone.id && newMilestone.name) {
        milestones.value.push(newMilestone);
        taskForm.milestone_id = newMilestone.id;
    }
};

// --- OPTIONS FOR DROPDOWNS ---
const assignedToOptions = computed(() => projectUsers.value);
const taskTypeOptions = computed(() => taskTypes.value);
const milestoneOptions = computed(() => milestones.value);

</script>

<template>
    <BaseFormModal
        :show="show"
        :title="modalTitle"
        :api-endpoint="apiEndpoint"
        :http-method="httpMethod"
        :form-data="taskForm"
        :submit-button-text="submitButtonText"
        :success-message="successMessage"
        :before-submit="beforeSubmit"
        @close="closeModal"
        @submitted="handleSaved"
        max-width="4xl"
    >
        <template #default="{ errors }">
            <div>
                <!-- Project Selection (only if projectId is NOT provided) -->
                <div v-if="!projectId" class="mb-4">
                    <SelectDropdown id="task-project" v-model="taskForm.project_id" :options="projects" value-key="value" label-key="label" placeholder="Select a project" :required="true" />
                    <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
                </div>

                <div v-if="taskForm.project_id" class="flex flex-col md:flex-row md:gap-8">
                    <!-- ===== LEFT PANE: CORE DETAILS ===== -->
                    <div class="flex-grow flex flex-col space-y-4">
                        <TextInput id="task-name" v-model="taskForm.name" type="text" class="block w-full text-xl font-semibold border-gray-200" placeholder="Task Title" required />
                        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />

                        <div v-if="taskForm.name" class="flex-grow flex flex-col">
                            <textarea id="task-description" v-model="taskForm.description" class="flex-grow w-full rounded-md border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add a description..."></textarea>
                            <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                            <p v-if="taskForm.name && taskForm.description.length < 10" class="text-xs text-gray-500 mt-1">
                                Enter at least 10 characters to continue.
                            </p>
                        </div>
                    </div>

                    <!-- ===== RIGHT PANE: WIZARD-LIKE METADATA ===== -->
                    <div class="w-full md:w-72 flex-shrink-0 mt-6 md:mt-0 space-y-5">
                        <div v-if="taskForm.description.length >= 10">
                            <!-- Milestone & Status -->
                            <div class="space-y-5">
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-gray-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 20.25h12m-7.5-3.75v3.75m.75-12.75l3 3m0 0l3-3m-3 3v11.25m-4.5 0L7.5 21m0 0H6" /></svg>
                                    <div class="flex-grow flex items-center gap-2">
                                        <SelectDropdown id="task-milestone" v-model="taskForm.milestone_id" :options="milestoneOptions" value-key="id" label-key="name" placeholder="Select Milestone" class="flex-grow" required />
                                        <button type="button" @click="openMilestoneModal" :disabled="!taskForm.project_id || loadingMilestones" class="p-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100 disabled:opacity-50" title="Add New Milestone" >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <InputError :message="errors.milestone_id ? errors.milestone_id[0] : ''" class="mt-2" />
                                <div class="flex items-center gap-3">
                                    <svg class="h-5 w-5 text-gray-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 9.563C9 9.252 9.252 9 9.563 9h4.874c.311 0 .563.252.563.563v4.874c0 .311-.252.563-.563.563H9.564A.562.562 0 019 14.437V9.564z" /></svg>
                                    <SelectDropdown id="task-status" v-model="taskForm.status" :options="taskStatuses.map(s => ({ value: s, label: s }))" value-key="value" label-key="label" class="flex-grow" />
                                </div>
                            </div>

                            <!-- Due Date -->
                            <div v-if="taskForm.milestone_id" class="mt-5 flex items-center gap-3">
                                <svg class="h-5 w-5 text-gray-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                                <TextInput id="task-due-date" v-model="taskForm.due_date" type="date" class="flex-grow" :min="minDueDate" :max="maxDueDate" />
                            </div>

                            <!-- Assignee -->
                            <div v-if="taskForm.due_date" class="mt-5 flex items-center gap-3">
                                <svg class="h-5 w-5 text-gray-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                <SelectDropdown id="task-assigned-to" v-model="taskForm.assigned_to_user_id" :options="assignedToOptions" value-key="value" label-key="label" placeholder="Unassigned" class="flex-grow" :allow-empty="true" />
                            </div>

                            <!-- Task Type -->
                            <div v-if="taskForm.assigned_to_user_id" class="mt-5 flex items-center gap-3">
                                <svg class="h-5 w-5 text-gray-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" /></svg>
                                <SelectDropdown id="task-type" v-model="taskForm.task_type_id" :options="taskTypeOptions" value-key="value" label-key="label" placeholder="Select Type" class="flex-grow" required />
                            </div>

                            <!-- Priority -->
                            <div v-if="taskForm.task_type_id" class="mt-5 space-y-2">
                                <InputLabel value="Priority" />
                                <div class="flex items-center justify-between rounded-md bg-gray-100 p-1">
                                    <button type="button" @click="taskForm.priority = 'low'" class="px-3 py-1 text-sm rounded-md w-full flex items-center justify-center gap-2" :class="taskForm.priority === 'low' ? 'bg-white shadow' : 'text-gray-600 hover:bg-gray-200'">
                                        <svg class="h-4 w-4 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3" /></svg>
                                        Low
                                    </button>
                                    <button type="button" @click="taskForm.priority = 'medium'" class="px-3 py-1 text-sm rounded-md w-full flex items-center justify-center gap-2" :class="taskForm.priority === 'medium' ? 'bg-white shadow' : 'text-gray-600 hover:bg-gray-200'">
                                        <svg class="h-4 w-4 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5" /></svg>
                                        Medium
                                    </button>
                                    <button type="button" @click="taskForm.priority = 'high'" class="px-3 py-1 text-sm rounded-md w-full flex items-center justify-center gap-2" :class="taskForm.priority === 'high' ? 'bg-white shadow' : 'text-gray-600 hover:bg-gray-200'">
                                        <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" /></svg>
                                        High
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== ADD-ONS SECTION ===== -->
                <div v-if="taskForm.description.length >= 10" class="mt-6 border-t pt-4">
                    <div class="space-y-4 mb-4">
                        <div v-if="showTagsInput"><TagInput v-model="taskForm.tags" :initialTags="taskForm.tags_data" label="Tags" placeholder="Add tags..." :error="errors.tags ? errors.tags[0] : ''" :disabled="!taskForm.project_id" /></div>
                        <div v-if="showAttachmentsInput">
                            <InputLabel for="task-files" value="Attachments" />
                            <input id="task-files" type="file" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100" multiple @change="onFilesChange" />
                        </div>
                        <div v-if="showApprovalInput" class="flex items-center">
                            <input id="needs-approval" type="checkbox" v-model="taskForm.needs_approval" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                            <label for="needs-approval" class="ml-2 block text-sm text-gray-700">This task needs approval</label>
                        </div>
                        <div v-if="showQaInput" class="flex items-center">
                            <input id="requires-qa" type="checkbox" v-model="taskForm.requires_qa" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                            <label for="requires-qa" class="ml-2 block text-sm text-gray-700">Required QA Task</label>
                        </div>
                        <div v-if="showScheduleInput" class="flex items-center justify-between">
                            <div>
                                <InputLabel value="Schedule" />
                                <div v-if="scheduleSummary" class="text-sm text-gray-700">Planned: {{ scheduleSummary }}</div>
                                <div v-else class="text-sm text-gray-500">No schedule attached</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="open" class="px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100">{{ scheduleSummary ? 'Edit Schedule' : 'Add Schedule' }}</button>
                                <button v-if="scheduleDraft" type="button" @click="scheduleDraft = null" class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="showTagsInput = !showTagsInput" class="flex items-center gap-1 text-sm text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded-md transition-colors" :class="{'bg-gray-300': showTagsInput}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a2 2 0 012 2v5a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm0 0v11a2 2 0 002 2h5a2 2 0 002-2v-5a2 2 0 00-2-2H7z" /></svg>Tag</button>
                        <button type="button" @click="showAttachmentsInput = !showAttachmentsInput" class="flex items-center gap-1 text-sm text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded-md transition-colors" :class="{'bg-gray-300': showAttachmentsInput}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>Attachment</button>
                        <button type="button" @click="showApprovalInput = !showApprovalInput" class="flex items-center gap-1 text-sm text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded-md transition-colors" :class="{'bg-gray-300': showApprovalInput}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Approval</button>
                        <button type="button" @click="showQaInput = !showQaInput" class="flex items-center gap-1 text-sm text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded-md transition-colors" :class="{'bg-gray-300': showQaInput}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6l7 3v4c0 4.418-2.239 8-7 9-4.761-1-7-4.582-7-9V9l7-3z"/></svg>QA</button>
                        <button type="button" @click="showScheduleInput = !showScheduleInput" class="flex items-center gap-1 text-sm text-gray-600 hover:text-black bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded-md transition-colors" :class="{'bg-gray-300': showScheduleInput}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>Schedule</button>
                    </div>
                </div>
            </div>
        </template>
    </BaseFormModal>

    <!-- Schedule Picker Modal -->
    <SchedulePickerModal :show="showScheduleModal" title="Add Schedule" @close="close" @save="onSaveDraft" />

    <!-- Milestone Creation Modal -->
    <MilestoneFormModal
        :show="showMilestoneModal"
        :project-id="taskForm.project_id"
        @close="showMilestoneModal = false"
        @saved="handleMilestoneSaved"
    />
</template>

