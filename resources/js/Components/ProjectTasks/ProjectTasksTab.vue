<script setup>
import { ref, onMounted, computed, reactive, watch } from 'vue'; // Added 'watch'
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TaskList from '@/Components/TaskList.vue';
import * as taskState from '@/Utils/taskState.js';
import * as notification from '@/Utils/notification.js';

// Import the new/modified modal components
import EditTaskModal from '@/Components/ProjectTasks/EditTaskModal.vue'; // Renamed
import CreateTaskModal from '@/Components/ProjectTasks/CreateTaskModal.vue'; // New
import MilestoneFormModal from '@/Components/ProjectTasks/MilestoneFormModal.vue';
import TaskNoteModal from '@/Components/ProjectTasks/TaskNoteModal.vue';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    projectUsers: {
        type: Array,
        default: () => [],
    },
    canManageProjects: {
        type: Boolean,
        required: true,
    },
    tasksFilter: {
        type: String,
        default: 'all',
    },
});

const emit = defineEmits(['tasksUpdated', 'openTaskDetailSidebar', 'open-create-task-modal', 'filter-changed']);

const tasks = ref([]);
const loadingTasks = ref(true);
const tasksError = ref('');

// Modals visibility state
const showEditTaskModal = ref(false); // Renamed from showTaskModal
const selectedTaskForEdit = ref(null); // Used for editing a task

const showMilestoneModal = ref(false);

const showTaskNoteModal = ref(false);
const taskForNote = ref(null); // The specific task to add a note to

// Block task modal
const showBlockTaskModal = ref(false);
const selectedTaskForBlock = ref(null);
const blockReason = ref('');

// Task data needed for modals and details
const taskTypes = ref([]);
const milestones = ref([]);
const loadingTaskTypes = ref(false);
const loadingMilestones = ref(false);

// Task filters
const taskFilters = reactive({
    status: '',
    assigned_to_user_id: null,
    milestone_id: null,
    due_date_range: ''
});

// Options for filter dropdowns (mapped for SelectDropdown)
const statusFilterOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'To Do', label: 'To Do' },
    { value: 'In Progress', label: 'In Progress' },
    { value: 'Done', label: 'Done' },
    { value: 'Blocked', label: 'Blocked' },
    { value: 'Archived', label: 'Archived' },
];

const assignedToFilterOptions = computed(() => {
    const options = [{ value: null, label: 'All Users' }, { value: -1, label: 'Unassigned' }];
    options.push(...props.projectUsers.map(user => ({
        value: user.id,
        label: user.name
    })));
    return options;
});

const milestoneFilterOptions = computed(() => {
    const options = [{ value: null, label: 'All Milestones' }, { value: -1, label: 'No Milestone' }];
    options.push(...milestones.value.map(m => ({
        value: m.id,
        label: m.name
    })));
    return options;
});

const dueDateOptions = [
    { value: '', label: 'All Dates' },
    { value: 'today', label: 'Due Today' },
    { value: 'this_week', label: 'Due This Week' },
    { value: 'next_week', label: 'Due Next Week' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'no_date', label: 'No Due Date' }
];

// Computed property for sorted milestones (for timeline)
const sortedMilestones = computed(() => {
    if (!milestones.value || !milestones.value.length) return [];
    return [...milestones.value].sort((a, b) => {
        if (!a.completion_date) return 1;
        if (!b.completion_date) return -1;
        return new Date(a.completion_date) - new Date(b.completion_date);
    });
});

// Computed property for filtered tasks (remains the same logic)
const filteredTasks = computed(() => {
    if (!tasks.value.length) return [];

    // Debug the tasks array structure
    console.log('ProjectTasksTab: Tasks array:', tasks.value);

    return tasks.value.filter(task => {
        // Status filter
        if (taskFilters.status && task.status !== taskFilters.status) {
            return false;
        }

        // Assigned user filter
        if (taskFilters.assigned_to_user_id !== null) {
            const assignedUserId = parseInt(taskFilters.assigned_to_user_id);
            if (assignedUserId === -1) { // Special case for "Unassigned"
                if (task.assigned_to !== 'Unassigned' && task.assigned_to_id !== null) { // Check both string and ID
                    return false;
                }
            } else { // Filter by specific user ID
                if (task.assigned_to_id !== assignedUserId) {
                    return false;
                }
            }
        }

        // Milestone filter
        if (taskFilters.milestone_id !== null) {
            const milestoneId = parseInt(taskFilters.milestone_id);
            if (milestoneId === -1) { // Special case for "No Milestone"
                if (task.milestone_id) { // Check if task has any milestone
                    return false;
                }
            } else { // Filter by specific milestone ID
                if (task.milestone_id !== milestoneId) {
                    return false;
                }
            }
        }


        // Due date filter
        if (taskFilters.due_date_range) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const taskDueDate = task.due_date ? new Date(task.due_date) : null;

            switch (taskFilters.due_date_range) {
                case 'today':
                    if (!taskDueDate || taskDueDate.toDateString() !== today.toDateString()) {
                        return false;
                    }
                    break;

                case 'this_week': {
                    if (!taskDueDate) return false;

                    const endOfWeek = new Date(today);
                    endOfWeek.setDate(today.getDate() + (6 - today.getDay())); // Sunday is 0, Saturday is 6

                    if (taskDueDate < today || taskDueDate > endOfWeek) {
                        return false;
                    }
                    break;
                }

                case 'next_week': {
                    if (!taskDueDate) return false;

                    const startOfNextWeek = new Date(today);
                    startOfNextWeek.setDate(today.getDate() + (7 - today.getDay()));

                    const endOfNextWeek = new Date(startOfNextWeek);
                    endOfNextWeek.setDate(startOfNextWeek.getDate() + 6);

                    if (taskDueDate < startOfNextWeek || taskDueDate > endOfNextWeek) {
                        return false;
                    }
                    break;
                }

                case 'overdue': {
                    if (!taskDueDate || taskDueDate >= today) {
                        return false;
                    }
                    break;
                }

                case 'no_date': {
                    if (taskDueDate) {
                        return false;
                    }
                    break;
                }
            }
        }

        return true;
    });
});

// Reset all filters
const resetFilters = () => {
    taskFilters.status = '';
    taskFilters.assigned_to_user_id = null;
    taskFilters.milestone_id = null;
    taskFilters.due_date_range = '';
};

// Fetch tasks for the project
const fetchProjectTasks = async () => {
    loadingTasks.value = true;
    tasksError.value = '';
    try {
        const rawTasks = await taskState.fetchProjectTasks(props.projectId);
        console.log('ProjectTasksTab: Raw API response from fetchProjectTasks:', rawTasks);
        tasks.value = rawTasks;
        emit('tasksUpdated', tasks.value); // Emit updated tasks to parent (e.g., Show.vue)
    } catch (error) {
        tasksError.value = 'Failed to load tasks data.';
        console.error('Error fetching project tasks:', error);
        if (error.response && error.response.data && error.response.data.message) {
            tasksError.value = error.response.data.message;
        }
    } finally {
        loadingTasks.value = false;
    }
};

// Handle task updates from TaskList component
const handleTaskUpdated = async (updatedTask) => {
    // Refresh the tasks list to reflect the changes
    await fetchProjectTasks();
};

// Fetch task types (for task modal)
const fetchTaskTypes = async () => {
    loadingTaskTypes.value = true;
    try {
        const response = await window.axios.get('/api/task-types');
        taskTypes.value = response.data;
    } catch (error) {
        console.error('Error fetching task types:', error);
    } finally {
        loadingTaskTypes.value = false;
    }
};

// Fetch milestones for the project (for task modal)
const fetchMilestones = async () => {
    loadingMilestones.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/milestones`);
        milestones.value = response.data;
    } catch (error) {
        console.error('Error fetching milestones:', error);
    } finally {
        loadingMilestones.value = false;
    }
};

// --- Modal Handlers ---

// Create Task Modal (new)
const openCreateTaskModal = () => {
    // We emit to the parent (Show.vue) to open the global CreateTaskModal
    emit('open-create-task-modal');
};

// Edit Task Modal (modified)
const editTask = async (task) => {
    selectedTaskForEdit.value = task; // Set the task for edit mode
    await fetchTaskTypes(); // Ensure data is loaded before opening
    await fetchMilestones();
    showEditTaskModal.value = true;
};

const handleTaskSaved = () => {
    // This function is called when EditTaskModal or CreateTaskModal successfully saves/updates a task
    fetchProjectTasks(); // Refresh the list of tasks
};

// View Task Details Sidebar
const viewTaskDetails = (task) => {
    console.log('ProjectTasksTab: Task object:', task);
    console.log('ProjectTasksTab: Emitting open-task-detail-sidebar for taskId:', task.id, 'projectId:', props.projectId);
    emit('openTaskDetailSidebar', task.id, props.projectId);
};

// Milestone Form Modal
const openAddMilestoneModal = () => {
    showMilestoneModal.value = true;
};

const handleMilestoneSaved = (newMilestone) => {
    // Add the new milestone directly to the local list
    milestones.value.push(newMilestone);
    // Refresh tasks in case this new milestone affects filtering or display
    fetchProjectTasks();
};

// Task Note Modal
const openAddTaskNoteModal = (task) => {
    taskForNote.value = task;
    showTaskNoteModal.value = true;
};

const handleTaskNoteAdded = () => {
    // A note was added, refresh tasks (could affect task status or simply for re-render)
    fetchProjectTasks();
};

// Mark Task as Completed
const markTaskAsCompleted = async (task) => {
    try {
        await window.axios.post(`/api/tasks/${task.id}/complete`);
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error marking task as completed:', error);
        alert('Failed to mark task as completed. Please try again.');
    }
};

// Start Task (change status to In Progress)
const startTask = async (task) => {
    try {
        notification.info('Starting task...');
        await window.axios.post(`/api/tasks/${task.id}/start`);
        notification.success('Task started successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error starting task:', error);
        notification.error('Failed to start task. Please try again.');
    }
};

// Pause Task (change status from In Progress to Paused)
const pauseTask = async (task) => {
    try {
        notification.info('Pausing task...');
        await window.axios.post(`/api/tasks/${task.id}/pause`);
        notification.success('Task paused successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error pausing task:', error);
        notification.error('Failed to pause task. Please try again.');
    }
};

// Resume Task (change status from Paused to In Progress)
const resumeTask = async (task) => {
    try {
        notification.info('Resuming task...');
        await window.axios.post(`/api/tasks/${task.id}/resume`);
        notification.success('Task resumed successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error resuming task:', error);
        notification.error('Failed to resume task. Please try again.');
    }
};

// Open Block Task Modal
const openBlockTaskModal = (task) => {
    selectedTaskForBlock.value = task;
    blockReason.value = '';
    showBlockTaskModal.value = true;
};

// Block Task (change status to Blocked)
const blockTask = async () => {
    if (!selectedTaskForBlock.value) return;
    if (!blockReason.value.trim()) {
        notification.warning('Please provide a reason for blocking the task');
        return;
    }

    try {
        notification.info('Blocking task...');
        await window.axios.post(`/api/tasks/${selectedTaskForBlock.value.id}/block`, {
            reason: blockReason.value
        });
        notification.success('Task blocked successfully');
        showBlockTaskModal.value = false;
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error blocking task:', error);
        notification.error('Failed to block task. Please try again.');
    }
};

// Unblock Task (change status from Blocked back to previous status)
const unblockTask = async (task) => {
    try {
        notification.info('Unblocking task...');
        await window.axios.post(`/api/tasks/${task.id}/unblock`);
        notification.success('Task unblocked successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error unblocking task:', error);
        notification.error('Failed to unblock task. Please try again.');
    }
};

// Delete Task (only for To Do tasks)
const deleteTask = async (task) => {
    if (task.status !== 'To Do') {
        notification.warning('Only tasks in To Do status can be deleted');
        return;
    }

    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return;
    }

    try {
        notification.info('Deleting task...');
        await window.axios.delete(`/api/tasks/${task.id}`);
        notification.success('Task deleted successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error deleting task:', error);
        notification.error('Failed to delete task. Please try again.');
    }
};

// Revise Task (change status from Done back to To Do)
const reviseTask = async (task) => {
    try {
        notification.info('Revising task...');
        await window.axios.post(`/api/tasks/${task.id}/revise`);
        notification.success('Task revised successfully');
        await fetchProjectTasks();
    } catch (error) {
        console.error('Error revising task:', error);
        notification.error('Failed to revise task. Please try again.');
    }
};

onMounted(() => {
    fetchProjectTasks();
    // These are fetched on mount to populate the filters.
    fetchTaskTypes();
    fetchMilestones();
});

// Watch projectUsers, taskTypes, milestones props to ensure dropdowns are updated if parent changes them
watch(() => props.projectUsers, () => {
    // No explicit action needed here, as computed properties for dropdowns will react
});
watch(() => props.taskTypes, () => {
    // No explicit action needed here
});
watch(() => props.milestones, () => {
    // No explicit action needed here
});

// Watch tasksFilter prop to update internal filters
watch(() => props.tasksFilter, (newFilter) => {
    if (newFilter === 'due-overdue') {
        // Set filters to show only due and overdue tasks
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Reset other filters first
        taskFilters.status = '';
        taskFilters.assigned_to_user_id = null;
        taskFilters.milestone_id = null;

        // Set due date filter to show overdue and due today tasks
        taskFilters.due_date_range = 'overdue';

        // Highlight overdue and due today tasks in the UI
        // This is handled by the CSS classes in the template
    } else if (newFilter === 'all') {
        // Reset all filters
        taskFilters.status = '';
        taskFilters.assigned_to_user_id = null;
        taskFilters.milestone_id = null;
        taskFilters.due_date_range = '';
    }

    // Emit the filter change back to parent
    emit('filter-changed', newFilter);
});
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Project Tasks</h4>
            <div class="flex gap-2">
                <SecondaryButton @click="fetchProjectTasks" :disabled="loadingTasks" class="text-indigo-600 hover:text-indigo-800">
                    <span v-if="!loadingTasks">Refresh</span>
                    <span v-else>Loading...</span>
                </SecondaryButton>
                <PrimaryButton v-if="canManageProjects" class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="openCreateTaskModal">
                    Add Task
                </PrimaryButton>
            </div>
        </div>

        <!-- Milestone Timeline -->
        <div class="mb-6 overflow-x-auto">
            <div v-if="loadingMilestones" class="text-center text-gray-600 text-sm animate-pulse py-4">
                Loading milestones...
            </div>
            <div v-else-if="!sortedMilestones.length" class="text-center py-4">
                <p class="text-gray-400 text-sm">No milestones found for this project.</p>
            </div>
            <div v-else class="relative py-8">
                <!-- Timeline Line -->
                <div class="absolute h-1 bg-gray-200 top-1/2 left-0 right-0 transform -translate-y-1/2"></div>

                <!-- Milestone Markers -->
                <div class="relative flex justify-between">
                    <div
                        v-for="(milestone, index) in sortedMilestones"
                        :key="milestone.id"
                        class="flex flex-col items-center relative z-10"
                        :class="{'ml-4': index === 0, 'mr-4': index === sortedMilestones.length - 1}"
                    >
                        <!-- Milestone Marker -->
                        <div
                            class="w-6 h-6 rounded-full shadow-lg flex items-center justify-center"
                            :class="{
                                'bg-gray-300': milestone.status === 'Not Started',
                                'bg-blue-500': milestone.status === 'In Progress',
                                'bg-green-500': milestone.status === 'Completed',
                                'bg-red-500': milestone.status === 'Overdue'
                            }"
                        >
                            <svg v-if="milestone.status === 'Completed'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else-if="milestone.status === 'In Progress'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <svg v-else-if="milestone.status === 'Overdue'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span v-else class="w-2 h-2 bg-white rounded-full"></span>
                        </div>

                        <!-- Milestone Name (above) -->
                        <div class="absolute -top-8 transform -translate-x-1/2 left-1/2 w-32">
                            <p class="text-xs font-medium text-gray-700 text-center truncate" :title="milestone.name">
                                {{ milestone.name }}
                            </p>
                        </div>

                        <!-- Milestone Date (below) -->
                        <div class="absolute top-8 transform -translate-x-1/2 left-1/2">
                            <p class="text-xs text-gray-500 whitespace-nowrap">
                                {{ milestone.completion_date ? new Date(milestone.completion_date).toLocaleDateString() : 'No date' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Filters -->
        <div class="mb-6 bg-gray-50 p-4 rounded-lg">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="status-filter" value="Status" />
                    <SelectDropdown
                        id="status-filter"
                        v-model="taskFilters.status"
                        :options="statusFilterOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="All Statuses"
                        class="mt-1"
                    />
                </div>

                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="assigned-filter" value="Assigned To" />
                    <SelectDropdown
                        id="assigned-filter"
                        v-model="taskFilters.assigned_to_user_id"
                        :options="assignedToFilterOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="All Users"
                        class="mt-1"
                    />
                </div>

                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="milestone-filter" value="Milestone" />
                    <SelectDropdown
                        id="milestone-filter"
                        v-model="taskFilters.milestone_id"
                        :options="milestoneFilterOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="All Milestones"
                        class="mt-1"
                    />
                </div>

                <div class="flex-1 min-w-[200px]">
                    <InputLabel for="due-date-filter" value="Due Date" />
                    <SelectDropdown
                        id="due-date-filter"
                        v-model="taskFilters.due_date_range"
                        :options="dueDateOptions"
                        value-key="value"
                        label-key="label"
                        placeholder="All Dates"
                        class="mt-1"
                    />
                </div>

                <div class="flex items-end">
                    <button
                        type="button"
                        @click="resetFilters"
                        class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-md transition-colors"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Filter summary -->
            <div v-if="Object.values(taskFilters).some(v => v !== null && v !== '')" class="mt-3 text-sm text-gray-600">
                <p>
                    Showing {{ filteredTasks.length }} of {{ tasks.length }} tasks
                    <span v-if="filteredTasks.length === 0" class="text-red-600 font-medium">
                        (No tasks match the current filters)
                    </span>
                </p>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loadingTasks" class="text-center text-gray-600 text-sm animate-pulse py-4">
            Loading tasks...
        </div>

        <!-- Error State -->
        <div v-else-if="tasksError" class="text-center py-4">
            <p class="text-red-600 text-sm font-medium">{{ tasksError }}</p>
        </div>

        <!-- Tasks Table -->
        <div v-else-if="tasks.length" class="overflow-x-auto">
            <TaskList
                :tasks="filteredTasks"
                :project-id="projectId"
                :show-project-column="false"
                @task-updated="handleTaskUpdated"
                @open-task-detail="viewTaskDetails"
            />
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-4">
            <p class="text-gray-400 text-sm">No tasks found for this project.</p>
            <p class="text-gray-500 text-sm mt-2">
                Click the "Add Task" button to create a new task.
            </p>
        </div>

        <!-- Edit Task Modal -->
        <EditTaskModal
            :show="showEditTaskModal"
            :project-id="projectId"
            :selected-task="selectedTaskForEdit"
            :project-users="projectUsers"
            :task-types="taskTypes"
            :milestones="milestones"
            :loading-task-types="loadingTaskTypes"
            :loading-milestones="loadingMilestones"
            @close="showEditTaskModal = false"
            @saved="handleTaskSaved"
            @open-add-milestone-modal="openAddMilestoneModal"
        />

        <!-- Milestone Form Modal -->
        <MilestoneFormModal
            :show="showMilestoneModal"
            :project-id="projectId"
            @close="showMilestoneModal = false"
            @saved="handleMilestoneSaved"
        />

        <!-- Task Note Modal -->
        <TaskNoteModal
            :show="showTaskNoteModal"
            :task-for-note="taskForNote"
            @close="showTaskNoteModal = false"
            @note-added="handleTaskNoteAdded"
        />

        <!-- Block Task Modal -->
        <Modal :show="showBlockTaskModal" @close="showBlockTaskModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Block Task
                </h2>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">
                        Please provide a reason for blocking this task:
                    </p>
                    <textarea
                        v-model="blockReason"
                        class="w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        rows="3"
                        placeholder="Enter reason for blocking..."
                    ></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="showBlockTaskModal = false">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        @click="blockTask"
                        :disabled="!blockReason.trim()"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        Block Task
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
