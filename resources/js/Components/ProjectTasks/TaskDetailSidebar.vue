<!-- TaskDetailSidebar.vue -->
<script setup>
import { ref, watch, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import moment from "moment";
import TaskNoteModal from '@/Components/ProjectTasks/TaskNoteModal.vue';
import TaskHistoryList from '@/Components/ProjectTasks/TaskHistoryList.vue';
import * as notification from '@/Utils/notification.js';
import * as taskState from '@/Utils/taskState.js';
import Modal from "@/Components/Modal.vue";
import ChecklistComponent from '@/Components/ChecklistComponent.vue';
import ChecklistCreator from '@/Components/ChecklistCreator.vue';
import { SaveIcon } from "lucide-vue-next";

const props = defineProps({
    taskId: {
        type: Number,
        required: true,
    },
    projectId: {
        type: Number,
        required: true,
    },
    projectUsers: {
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

// Checklist creation
const showChecklistCreator = ref(false);
const newChecklist = ref([{ name: '', completed: false }]);
const isSavingChecklist = ref(false);

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
    // We can use the central utility function now
    try {
        await taskState.deleteTask(task.value);
        emit('task-deleted', task.value.id);
        emit('close');
    } catch (error) {
        console.error('Error deleting task:', error);
    }
};

const startTask = async () => {
    try {
        task.value = await taskState.startTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error starting task:', error);
    }
};

const pauseTask = async () => {
    try {
        task.value = await taskState.pauseTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error pausing task:', error);
    }
};

const resumeTask = async () => {
    try {
        task.value = await taskState.resumeTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error resuming task:', error);
    }
};

const completeTask = async () => {
    try {
        task.value = await taskState.completeTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error completing task:', error);
    }
};

const reviseTask = async () => {
    try {
        task.value = await taskState.reviseTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error revising task:', error);
    }
};

// Toggle checklist creator visibility
const toggleChecklistCreator = () => {
    showChecklistCreator.value = !showChecklistCreator.value;
    if (showChecklistCreator.value) {
        // Initialize with existing checklist if available
        if (task.value.details?.checklist && task.value.details.checklist.length > 0) {
            newChecklist.value = JSON.parse(JSON.stringify(task.value.details.checklist));
            // Ensure there's an empty item at the end for adding new items
            if (newChecklist.value[newChecklist.value.length - 1].name !== '') {
                newChecklist.value.push({ name: '', completed: false });
            }
        } else {
            // Start with one empty item
            newChecklist.value = [{ name: '', completed: false }];
        }
    }
};

// Save checklist to task details
const saveChecklist = async () => {
    if (isSavingChecklist.value) return;

    isSavingChecklist.value = true;

    try {
        // Filter out empty checklist items
        const filteredChecklist = newChecklist.value.filter(item => item.name.trim() !== '');

        // Prepare the payload
        const payload = {
            details: {
                ...task.value.details,
                checklist: filteredChecklist
            }
        };

        // Make API call
        const response = await window.axios.patch(`/api/tasks/${task.value.id}`, payload);

        // Update local task data
        task.value = response.data;

        // Hide checklist creator
        showChecklistCreator.value = false;

        // Show success notification
        notification.success('Checklist saved successfully');

        // Notify parent of update
        emit('task-updated', task.value);
    } catch (err) {
        console.error('Error saving checklist:', err);
        notification.error('Failed to save checklist');
    } finally {
        isSavingChecklist.value = false;
    }
};

const blockTask = async () => {
    // This will open a modal in the parent component to get the reason
    // We need to emit an event to the parent to handle this.
    // Let's create a new modal for this in TaskDetailSidebar for simplicity.
    // Or we can assume the parent will handle it. Given the current code structure,
    // let's integrate a simple modal here.
};

// New state for the block modal inside the sidebar
const showBlockTaskModal = ref(false);
const blockReason = ref('');

const openBlockTaskModal = () => {
    blockReason.value = '';
    showBlockTaskModal.value = true;
};

const handleBlockTask = async () => {
    if (!blockReason.value.trim()) {
        notification.warning('Please provide a reason for blocking the task');
        return;
    }

    try {
        task.value = await taskState.blockTask(task.value, blockReason.value);
        notification.success('Task blocked successfully');
        emit('task-updated', task.value);
        fetchTaskActivities();
        showBlockTaskModal.value = false;
    } catch (error) {
        console.error('Error blocking task:', error);
    }
};

const unblockTask = async () => {
    try {
        task.value = await taskState.unblockTask(task.value);
        emit('task-updated', task.value);
        fetchTaskActivities();
    } catch (error) {
        console.error('Error unblocking task:', error);
    }
};

const taskStatusClass = computed(() => {
    if (!task.value) return '';
    return taskState.getTaskStatusClasses(task.value.status);
});

// New computed property to find the latest block activity details
const latestBlockActivity = computed(() => {
    if (!taskActivities.value || task.value?.status !== 'Blocked') {
        return null;
    }
    const blockActivity = taskActivities.value.find(
        (activity) =>
            activity.event === 'updated' &&
            activity.properties?.attributes?.status === 'Blocked'
    );
    if (!blockActivity) {
        return null;
    }

    console.log(blockActivity.properties)
    return {
        causer: blockActivity.causer?.name || 'Unknown',
        reason: blockActivity.properties?.attributes?.block_reason || 'No reason provided.'
    };
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

                <!-- Action Buttons -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <!-- Start/Resume/Pause Button -->
                    <PrimaryButton
                        v-if="task.status === 'To Do'"
                        @click="startTask"
                        class="bg-blue-600 hover:bg-blue-700"
                    >
                        Start Task
                    </PrimaryButton>
                    <PrimaryButton
                        v-else-if="task.status === 'In Progress'"
                        @click="pauseTask"
                        class="bg-orange-600 hover:bg-orange-700"
                    >
                        Pause Task
                    </PrimaryButton>
                    <PrimaryButton
                        v-else-if="task.status === 'Paused'"
                        @click="resumeTask"
                        class="bg-blue-600 hover:bg-blue-700"
                    >
                        Resume Task
                    </PrimaryButton>
                    <PrimaryButton
                        v-else-if="task.status === 'Blocked'"
                        @click="unblockTask"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        Unblock Task
                    </PrimaryButton>

                    <!-- Block/Complete/Revise Button -->
                    <PrimaryButton
                        v-if="task.status === 'To Do' || task.status === 'In Progress' || task.status === 'Paused'"
                        @click="openBlockTaskModal"
                        class="bg-red-600 hover:bg-red-700"
                    >
                        Block Task
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="task.status === 'In Progress'"
                        @click="completeTask"
                        class="bg-green-600 hover:bg-green-700"
                    >
                        Complete Task
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="task.status === 'Done'"
                        @click="reviseTask"
                        class="bg-yellow-600 hover:bg-yellow-700"
                    >
                        Revise Task
                    </PrimaryButton>
                </div>
                <div v-if="task.status === 'Done'" class="text-xs text-gray-500 mt-1">
                    Revising will change the task status back to "To Do"
                </div>

            </div>

            <!-- Latest Block Reason -->
            <div v-if="task.status === 'Blocked' && latestBlockActivity" class="bg-yellow-50 border-l-4 border-red-400 p-4 rounded-lg shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <!-- Heroicon name: solid/exclamation-triangle -->
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.487 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11.75 13a.75.75 0 01-1.5 0v-2.25a.75.75 0 011.5 0V13zm-1.5-7.5a.75.75 0 011.5 0v1.5a.75.75 0 01-1.5 0V5.5z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Task Blocked</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <p>This task was blocked by **{{ latestBlockActivity.causer }}** with the following reason:</p>
                            <p>{{ latestBlockActivity.reason }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Checklist -->
            <div class="space-y-4 p-4 bg-white rounded-lg shadow-sm mb-4">
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <h5 class="text-lg font-semibold text-gray-800">Task Checklist</h5>
                    <button
                        @click="toggleChecklistCreator"
                        class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center"
                    >
                        <span v-if="!showChecklistCreator">{{ task.details?.checklist && task.details.checklist.length > 0 ? 'Edit Checklist' : 'Add Checklist' }}</span>
                        <span v-else>Cancel</span>
                    </button>
                </div>

                <!-- Checklist Creator -->
                <div v-if="showChecklistCreator" class="mb-4">
                    <ChecklistCreator
                        v-model="newChecklist"
                        placeholder="e.g., Review design, Update documentation"
                        label="Checklist Items"
                        density="notes"
                    />
                    <div class="flex justify-end mt-4">
                        <PrimaryButton
                            @click="saveChecklist"
                            :disabled="isSavingChecklist"
                            class="flex items-center space-x-1"
                        >
                            <SaveIcon class="h-4 w-4" />
                            <span>Save Checklist</span>
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Checklist Display -->
                <ChecklistComponent
                    v-if="task.details?.checklist && task.details.checklist.length > 0 && !showChecklistCreator"
                    :items="task.details.checklist"
                    :api-endpoint="`/api/tasks/${task.id}`"
                    title="Items to complete:"
                    :payload-transformer="(items) => ({
                        details: {
                            ...task.details,
                            checklist: items
                        }
                    })"
                />

                <!-- Empty state when no checklist -->
                <div v-if="(!task.details?.checklist || task.details.checklist.length === 0) && !showChecklistCreator" class="text-gray-500 text-sm py-2">
                    No checklist items for this task yet. Click "Add Checklist" to create one.
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

        <!-- Action Buttons section -->
        <div v-if="task && task.status !== 'Done'" class="mt-4 flex flex-col items-center">
            <DangerButton @click="deleteTask" :disabled="task.status !== 'To Do'">
                Delete Task
            </DangerButton>
            <p v-if="task.status !== 'To Do'" class="text-xs text-red-500 mt-1">Task can only be deleted in 'To Do' status.</p>
        </div>


        <!-- Task Note Modal -->
        <TaskNoteModal
            :show="showTaskNoteModal"
            :task-for-note="task"
            @close="showTaskNoteModal = false"
            @note-added="handleNoteAdded"
        />

        <!-- Block Task Modal for Sidebar -->
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
                        @click="handleBlockTask"
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
