<script setup>
import { ref, watch, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import moment from "moment";
import TaskNoteModal from '@/Components/ProjectTasks/TaskNoteModal.vue'; // Re-use existing modal
import TaskHistoryList from '@/Components/ProjectTasks/TaskHistoryList.vue';
import * as notification from '@/Utils/notification.js';

const props = defineProps({
    taskId: {
        type: Number,
        required: true,
    },
    projectId: {
        type: Number,
        required: true,
    },
    projectUsers: { // Pass project.users for assignee dropdown
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'task-updated', 'task-deleted']);

const task = ref(null);
const loadingTask = ref(true);
const taskError = ref('');

// State for inline editing
const editingAssignedTo = ref(false);
const editingDueDate = ref(false);
const editingPriority = ref(false);
const newAssignedToId = ref(null);
const newDueDate = ref(null);
const newPriority = ref(null);

// Task history
const taskActivities = ref([]);
const loadingActivities = ref(false);

// Priority options
const priorityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
];

// Modals
const showTaskNoteModal = ref(false);

const fetchTaskDetails = async () => {
    loadingTask.value = true;
    taskError.value = '';
    try {
        const response = await window.axios.get(`/api/tasks/${props.taskId}`);
        task.value = response.data;
        // Initialize inline edit fields with current task data
        newAssignedToId.value = task.value.assigned_to_id || null;
        newDueDate.value = task.value.due_date || null;
        newPriority.value = task.value.priority || 'medium';

        // Fetch task activities after loading task details
        fetchTaskActivities();
    } catch (error) {
        taskError.value = 'Failed to load task details.';
        console.error('Error fetching task details:', error);
    } finally {
        loadingTask.value = false;
    }
};

const fetchTaskActivities = async () => {
    loadingActivities.value = true;
    try {
        const response = await window.axios.get(`/api/activities`, {
            params: {
                subject_type: 'App\\Models\\Task',
                subject_id: props.taskId
            }
        });
        taskActivities.value = response.data;
    } catch (error) {
        console.error('Error fetching task activities:', error);
        // Don't show error to user, just log it
    } finally {
        loadingActivities.value = false;
    }
};

watch(() => props.taskId, (newTaskId) => {
    if (newTaskId) {
        fetchTaskDetails();
    } else {
        task.value = null; // Clear task details if no taskId
    }
}, { immediate: true });

// --- Inline Editing Functions ---
const assignedToOptions = computed(() => {
    return props.projectUsers.map(user => ({
        value: user.id,
        label: user.name
    }));
});

const saveAssignedTo = async () => {
    if (!task.value || newAssignedToId.value === task.value.assigned_to_id) {
        editingAssignedTo.value = false;
        return;
    }
    try {
        const response = await window.axios.patch(`/api/tasks/${task.value.id}`, {
            assigned_to_user_id: newAssignedToId.value
        });
        task.value = response.data; // Update local task data with response
        emit('task-updated', task.value); // Notify parent of update
    } catch (error) {
        console.error('Error updating assigned user:', error);
        // Revert on error
        newAssignedToId.value = task.value.assigned_to_id;
        notification.error('Failed to update assigned user. Please try again.');
    } finally {
        editingAssignedTo.value = false;
    }
};

const saveDueDate = async () => {
    if (!task.value || newDueDate.value === task.value.due_date) {
        editingDueDate.value = false;
        return;
    }
    try {
        const response = await window.axios.patch(`/api/tasks/${task.value.id}`, {
            due_date: newDueDate.value
        });
        task.value = response.data; // Update local task data with response
        emit('task-updated', task.value); // Notify parent of update
    } catch (error) {
        console.error('Error updating due date:', error);
        // Revert on error
        newDueDate.value = task.value.due_date;
        notification.error('Failed to update due date. Please try again.');
    } finally {
        editingDueDate.value = false;
    }
};

const savePriority = async () => {
    if (!task.value || newPriority.value === task.value.priority) {
        editingPriority.value = false;
        return;
    }
    try {
        const response = await window.axios.patch(`/api/tasks/${task.value.id}`, {
            priority: newPriority.value
        });
        task.value = response.data; // Update local task data with response
        emit('task-updated', task.value); // Notify parent of update
    } catch (error) {
        console.error('Error updating priority:', error);
        // Revert on error
        newPriority.value = task.value.priority;
        notification.error('Failed to update priority. Please try again.');
    } finally {
        editingPriority.value = false;
    }
};

// --- Other Actions ---
const openAddTaskNoteModal = () => {
    showTaskNoteModal.value = true;
};

const handleNoteAdded = () => {
    fetchTaskDetails(); // Re-fetch task to get updated notes
    showTaskNoteModal.value = false;
};

const deleteTask = async () => {
    if (!confirm('Are you sure you want to delete this task? This action cannot be undone.')) {
        return;
    }
    try {
        notification.info('Deleting task...');
        await window.axios.delete(`/api/tasks/${task.value.id}`);
        notification.success('Task deleted successfully');
        emit('task-deleted', task.value.id); // Notify parent of deletion
        emit('close'); // Close sidebar after deletion
    } catch (error) {
        console.error('Error deleting task:', error);
        notification.error('Failed to delete task. Please try again.');
    }
};

const startTask = async () => {
    if (!task.value || task.value.status === 'In Progress') {
        return;
    }
    try {
        notification.info('Starting task...');
        const response = await window.axios.post(`/api/tasks/${task.value.id}/start`);
        task.value = response.data; // Update local task data with response
        notification.success('Task started successfully');
        emit('task-updated', task.value); // Notify parent of update
        fetchTaskActivities(); // Refresh activities after status change
    } catch (error) {
        console.error('Error starting task:', error);
        notification.error('Failed to start task. Please try again.');
    }
};

const completeTask = async () => {
    if (!task.value || task.value.status === 'Done') {
        return;
    }

    // Prevent completing a task that hasn't been started
    if (task.value.status !== 'In Progress') {
        notification.warning('Task must be started before it can be completed');
        return;
    }

    try {
        notification.info('Completing task...');
        const response = await window.axios.patch(`/api/tasks/${task.value.id}/complete`);
        task.value = response.data; // Update local task data with response
        notification.success('Task completed successfully');
        emit('task-updated', task.value); // Notify parent of update
        fetchTaskActivities(); // Refresh activities after status change
    } catch (error) {
        console.error('Error completing task:', error);
        if (error.response && error.response.status === 422) {
            notification.warning(error.response.data.message || 'Task must be started before it can be completed');
        } else {
            notification.error('Failed to complete task. Please try again.');
        }
    }
};

const reviseTask = async () => {
    if (!task.value || task.value.status !== 'Done') {
        return;
    }

    try {
        notification.info('Revising task...');
        const response = await window.axios.post(`/api/tasks/${task.value.id}/revise`);
        task.value = response.data; // Update local task data with response
        notification.success('Task revised successfully');
        emit('task-updated', task.value); // Notify parent of update
        fetchTaskActivities(); // Refresh activities after status change
    } catch (error) {
        console.error('Error revising task:', error);
        if (error.response && error.response.status === 422) {
            notification.warning(error.response.data.message || 'Only completed tasks can be revised');
        } else {
            notification.error('Failed to revise task. Please try again.');
        }
    }
};

const taskStatusClass = computed(() => {
    if (!task.value) return '';
    return {
        'bg-yellow-100 text-yellow-800': task.value.status === 'To Do',
        'bg-blue-100 text-blue-800': task.value.status === 'In Progress',
        'bg-green-100 text-green-800': task.value.status === 'Done',
        'bg-red-100 text-red-800': task.value.status === 'Blocked',
        'bg-gray-100 text-gray-800': task.value.status === 'Archived'
    };
});

// Expose action buttons for the parent component to use in the footer slot
defineExpose({
    task,
    startTask,
    completeTask,
    reviseTask,
    deleteTask
});
</script>

<template>
    <div>
        <div v-if="loadingTask" class="text-center py-8 text-gray-500">
            Loading task details...
        </div>
        <div v-else-if="taskError" class="text-center py-8 text-red-600">
            {{ taskError }}
        </div>
        <div v-else-if="task" class="space-y-6">
            <!-- Task Overview -->
            <div class="bg-gray-50 p-4 rounded-lg shadow-sm">
                <h4 class="text-xl font-bold text-gray-900 mb-2">{{ task.name }}</h4>
                <div class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
                    <span class="px-2 py-1 rounded-full text-xs font-medium" :class="taskStatusClass">
                        {{ task.status }}
                    </span>
                    <span v-if="task.task_type" class="text-indigo-600 font-medium">
                        |  {{ task.task_type?.name }}  |
                    </span>
                    <br />
                    <span v-if="task.milestone?.name" class="text-purple-600 font-medium">
                        {{ task.milestone?.name }}
                    </span>
                </div>
                <p class="text-gray-700 leading-relaxed">{{ task.description || 'No description provided.' }}</p>

                <!-- Revise Button for Completed Tasks -->
                <div v-if="task.status === 'Done'" class="mt-4">
                    <PrimaryButton @click="reviseTask" class="bg-yellow-600 hover:bg-yellow-700 transition-colors">
                        Revise Task
                    </PrimaryButton>
                    <p class="text-xs text-gray-500 mt-1">Revising will change the task status back to "To Do"</p>
                </div>
            </div>

            <!-- Inline Editable Details -->
            <div class="space-y-4 p-4 bg-white rounded-lg shadow-sm">
                <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Task Attributes</h5>

                <!-- Assigned To -->
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <InputLabel class="min-w-[100px] text-gray-600">Assigned To:</InputLabel>
                    <div class="flex-1 text-right">
                        <div v-if="!editingAssignedTo"
                             @click="task.status !== 'Done' ? editingAssignedTo = true : notification.warning('Cannot change assignment for a completed task. Use the Revise button to change the task status first.')"
                             class="cursor-pointer"
                             :class="{'text-indigo-600 hover:text-indigo-800': task.status !== 'Done', 'text-gray-500': task.status === 'Done'}">
                            {{ task.assigned_to?.name || 'Unassigned' }}
                            <span v-if="task.status !== 'Done'" class="text-xs text-gray-400 ml-1">(Click to edit)</span>
                        </div>
                        <div v-else class="flex items-center space-x-2">
                            <SelectDropdown
                                id="edit-assigned-to"
                                v-model="newAssignedToId"
                                :options="assignedToOptions"
                                value-key="value"
                                label-key="label"
                                placeholder="Unassigned"
                                class="mt-1 flex-1"
                                :allow-empty="true"
                            />
                            <button @click="saveAssignedTo" class="text-green-600 hover:text-green-800 text-sm font-medium">Save</button>
                            <button @click="editingAssignedTo = false" class="text-gray-600 hover:text-gray-800 text-sm font-medium">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Due Date -->
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <InputLabel class="min-w-[100px] text-gray-600">Due Date:</InputLabel>
                    <div class="flex-1 text-right">
                        <div v-if="!editingDueDate" @click="editingDueDate = true" class="cursor-pointer text-indigo-600 hover:text-indigo-800">
                            {{ task.due_date ? moment(task.due_date).format('MMM D, YYYY') : 'No Due Date' }} <span class="text-xs text-gray-400 ml-1">(Click to edit)</span>
                        </div>
                        <div v-else class="flex items-center space-x-2">
                            <TextInput
                                id="edit-due-date"
                                v-model="newDueDate"
                                type="date"
                                class="mt-1 flex-1"
                            />
                            <button @click="saveDueDate" class="text-green-600 hover:text-green-800 text-sm font-medium">Save</button>
                            <button @click="editingDueDate = false" class="text-gray-600 hover:text-gray-800 text-sm font-medium">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Priority -->
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <InputLabel class="min-w-[100px] text-gray-600">Priority:</InputLabel>
                    <div class="flex-1 text-right">
                        <div v-if="!editingPriority"
                             @click="task.status !== 'Done' ? editingPriority = true : notification.warning('Cannot change priority for a completed task. Use the Revise button to change the task status first.')"
                             class="cursor-pointer"
                             :class="{'text-indigo-600 hover:text-indigo-800': task.status !== 'Done', 'text-gray-500': task.status === 'Done'}">
                            <span :class="{
                                'text-red-600 font-medium': task.priority === 'high',
                                'text-yellow-600 font-medium': task.priority === 'medium',
                                'text-green-600 font-medium': task.priority === 'low'
                            }">
                                {{ task.priority ? task.priority.charAt(0).toUpperCase() + task.priority.slice(1) : 'Medium' }}
                            </span>
                            <span v-if="task.status !== 'Done'" class="text-xs text-gray-400 ml-1">(Click to edit)</span>
                        </div>
                        <div v-else class="flex items-center space-x-2">
                            <SelectDropdown
                                id="edit-priority"
                                v-model="newPriority"
                                :options="priorityOptions"
                                value-key="value"
                                label-key="label"
                                placeholder="Select priority"
                                class="mt-1 flex-1"
                            />
                            <button @click="savePriority" class="text-green-600 hover:text-green-800 text-sm font-medium">Save</button>
                            <button @click="editingPriority = false" class="text-gray-600 hover:text-gray-800 text-sm font-medium">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Created At -->
                <div class="flex items-center justify-between py-2 border-b border-gray-100">
                    <InputLabel class="min-w-[100px] text-gray-600">Created:</InputLabel>
                    <span class="text-gray-700">{{ task.created_at ? new Date(task.created_at).toLocaleDateString() : 'N/A' }}</span>
                </div>

                <!-- Tags -->
                <div class="flex items-start py-2 border-b border-gray-100">
                    <InputLabel class="min-w-[100px] text-gray-600 mt-1">Tags:</InputLabel>
                    <div class="flex-1 flex flex-wrap gap-2">
                        <span
                            v-for="tag in task.tags"
                            :key="tag.id"
                            class="inline-flex items-center px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 rounded-full shadow-sm"
                        >
                            {{ tag.name }}
                        </span>
                        <span v-if="!task.tags || task.tags.length === 0" class="text-gray-500 text-sm">No tags</span>
                    </div>
                </div>
            </div>

            <!-- Task Notes -->
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h5 class="text-lg font-semibold text-gray-800">Notes</h5>
                    <PrimaryButton @click="openAddTaskNoteModal" class="bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        Add Note
                    </PrimaryButton>
                </div>
                <div v-if="task.notes && task.notes.length > 0" class="space-y-3">
                    <div v-for="note in task.notes" :key="note.id" class="p-3 bg-gray-50 rounded-md">
                        <p class="text-sm text-gray-700">{{ note.content }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            By {{ note.creator_name || 'Unknown' }} on {{ new Date(note.created_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>
                <div v-else class="text-gray-500 text-sm py-2">No notes for this task yet.</div>
            </div>

            <!-- Task History -->
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">History</h5>
                <TaskHistoryList
                    :activities="taskActivities"
                    :loading="loadingActivities"
                />
            </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
            Select a task to view details.
        </div>

        <!-- Task Note Modal -->
        <TaskNoteModal
            :show="showTaskNoteModal"
            :task-for-note="task"
            @close="showTaskNoteModal = false"
            @note-added="handleNoteAdded"
        />
    </div>
</template>
