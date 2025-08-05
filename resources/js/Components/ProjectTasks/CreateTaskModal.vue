<script setup>
import { reactive, ref, watch, computed, onMounted } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import MilestoneFormModal from './MilestoneFormModal.vue'; // Import the MilestoneFormModal

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
    tags: [], // For tag IDs
    tags_data: [], // For tag objects with id and name
});

const taskStatuses = ['To Do', 'In Progress', 'Done', 'Blocked', 'Archived'];
const priorityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
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

// NEW: State for MilestoneFormModal visibility
const showMilestoneModal = ref(false);

// Computed properties for BaseFormModal
const modalTitle = 'Create New Task';
const apiEndpoint = '/api/tasks';
const httpMethod = 'post';
const submitButtonText = 'Create Task';
const successMessage = 'Task created successfully!';

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
            tags: [], // Reset tags
            tags_data: [], // Reset tags data
        });

        // If no projectId passed, fetch projects for selection
        if (!props.projectId) {
            await fetchProjects();
        } else {
            // If projectId is passed, fetch data for that specific project
            await fetchAssociatedData(props.projectId);
        }
    }
}, { immediate: true });

// Watch for changes in taskForm.project_id (if user selects it)
watch(() => taskForm.project_id, async (newProjectId) => {
    if (newProjectId) {
        await fetchAssociatedData(newProjectId);
    } else {
        // Clear associated data if no project is selected
        projectUsers.value = [];
        taskTypes.value = [];
        milestones.value = [];
    }
});

const fetchProjects = async () => {
    loadingProjects.value = true;
    try {
        const response = await window.axios.get('/api/projects-simplified'); // Adjust this endpoint as needed
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
        milestones.value = milestonesRes.data.map(milestone => ({ value: milestone.id, label: milestone.name }));
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

// Function to handle the successful submission from BaseFormModal
const handleSaved = (responseData) => {
    emit('saved', responseData);
    emit('close');
};

// Pass through the close event
const closeModal = () => {
    emit('close');
};

// NEW: Function to open MilestoneFormModal
const openMilestoneModal = () => {
    if (taskForm.project_id) {
        showMilestoneModal.value = true;
    } else {
        // Optionally, show a message to the user that a project must be selected first
        alert('Please select a project before adding a milestone.'); // Replace with a proper notification
    }
};

// NEW: Function to handle a newly created milestone
const handleMilestoneSaved = (newMilestone) => {
    // Close the milestone modal
    showMilestoneModal.value = false;

    // Add the new milestone to the local milestones list
    if (newMilestone && newMilestone.id && newMilestone.name) {
        const newOption = { value: newMilestone.id, label: newMilestone.name };
        // Ensure it's not already in the list to prevent duplicates if API re-fetches
        if (!milestones.value.some(m => m.value === newOption.value)) {
            milestones.value.push(newOption);
        }
        // Automatically select the newly created milestone
        taskForm.milestone_id = newOption.value;
    }
};


// Computed options for SelectDropdowns
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
        @close="closeModal"
        @submitted="handleSaved"
    >
        <template #default="{ errors }">
            <div class="space-y-4">
                <!-- Project Selection (only if projectId is NOT provided) -->
                <div v-if="!projectId">
                    <InputLabel for="task-project" value="Project" />
                    <div v-if="loadingProjects" class="text-sm text-gray-500">Loading projects...</div>
                    <SelectDropdown
                        v-else
                        id="task-project"
                        v-model="taskForm.project_id"
                        :options="projects"
                        value-key="value"
                        label-key="label"
                        placeholder="Select a project"
                        class="mt-1"
                        :required="true"
                    />
                    <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
                </div>

                <!-- Task Name -->
                <div>
                    <InputLabel for="task-name" value="Task Name" />
                    <TextInput
                        id="task-name"
                        v-model="taskForm.name"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Enter task name"
                        required
                    />
                    <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                </div>

                <!-- Task Description -->
                <div>
                    <InputLabel for="task-description" value="Description" />
                    <textarea
                        id="task-description"
                        v-model="taskForm.description"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Enter task description"
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>

                <!-- Task Status -->
                <div>
                    <InputLabel for="task-status" value="Status" />
                    <SelectDropdown
                        id="task-status"
                        v-model="taskForm.status"
                        :options="taskStatuses.map(s => ({ value: s, label: s }))"
                        value-key="value"
                        label-key="label"
                        placeholder="Select status"
                        class="mt-1"
                    />
                    <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
                </div>

                <!-- Task Priority -->
                <div>
                    <InputLabel for="task-priority" value="Priority" />
                    <SelectDropdown
                        id="task-priority"
                        v-model="taskForm.priority"
                        :options="priorityOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="Select priority"
                        class="mt-1"
                    />
                    <InputError :message="errors.priority ? errors.priority[0] : ''" class="mt-2" />
                </div>

                <!-- Due Date -->
                <div>
                    <InputLabel for="task-due-date" value="Due Date" />
                    <TextInput
                        id="task-due-date"
                        v-model="taskForm.due_date"
                        type="date"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="errors.due_date ? errors.due_date[0] : ''" class="mt-2" />
                </div>

                <!-- Assigned To -->
                <div>
                    <InputLabel for="task-assigned-to" value="Assigned To" />
                    <div v-if="loadingProjectUsers" class="text-sm text-gray-500">Loading users...</div>
                    <SelectDropdown
                        v-else
                        id="task-assigned-to"
                        v-model="taskForm.assigned_to_user_id"
                        :options="assignedToOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="Unassigned"
                        class="mt-1"
                        :allow-empty="true"
                    />
                    <InputError :message="errors.assigned_to_user_id ? errors.assigned_to_user_id[0] : ''" class="mt-2" />
                </div>

                <!-- Task Type -->
                <div>
                    <InputLabel for="task-type" value="Task Type" />
                    <div v-if="loadingTaskTypes" class="text-sm text-gray-500">Loading task types...</div>
                    <SelectDropdown
                        v-else
                        id="task-type"
                        v-model="taskForm.task_type_id"
                        :options="taskTypeOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="Select a task type"
                        class="mt-1"
                        :allow-empty="true"
                    />
                    <InputError :message="errors.task_type_id ? errors.task_type_id[0] : ''" class="mt-2" />
                </div>

                <!-- Milestone -->
                <div>
                    <InputLabel for="task-milestone" value="Milestone" />
                    <div class="flex items-center gap-2 mt-1">
                        <div v-if="loadingMilestones" class="text-sm text-gray-500">Loading milestones...</div>
                        <SelectDropdown
                            v-else
                            id="task-milestone"
                            v-model="taskForm.milestone_id"
                            :options="milestoneOptions"
                            value-key="value"
                            label-key="label"
                            placeholder="No milestone"
                            class="flex-grow"
                            :allow-empty="true"
                            :disabled="!taskForm.project_id"
                        />
                        <button
                            type="button"
                            @click="openMilestoneModal"
                            :disabled="!taskForm.project_id || loadingMilestones"
                            class="px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-md hover:bg-blue-100 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Add New Milestone"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>
                    </div>
                    <InputError :message="errors.milestone_id ? errors.milestone_id[0] : ''" class="mt-2" />
                </div>

                <!-- Tags -->
                <div>
                    <TagInput
                        v-model="taskForm.tags"
                        :initialTags="taskForm.tags_data"
                        label="Tags"
                        placeholder="Search or add tags"
                        :error="errors.tags ? errors.tags[0] : ''"
                        :disabled="!taskForm.project_id"
                    />
                </div>
            </div>
        </template>
    </BaseFormModal>

    <!-- Milestone Creation Modal -->
    <MilestoneFormModal
        :show="showMilestoneModal"
        :project-id="taskForm.project_id"
        @close="showMilestoneModal = false"
        @saved="handleMilestoneSaved"
    />
</template>
