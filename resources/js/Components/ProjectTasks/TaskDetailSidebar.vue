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
import BlockReasonModal from '@/Components/BlockReasonModal.vue';
import { SaveIcon } from "lucide-vue-next";
import { usePermissions, useProjectRole, useProjectPermissions } from '@/Directives/permissions.js';

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

// Derived project id from task's milestone
const projectIdFromTask = computed(() => task.value?.milestone?.project_id || null);

// Permissions (for gating due date edit, etc.)
// Ensure project permissions are loaded
useProjectPermissions(projectIdFromTask);

// Build a minimal project ref for role resolution
const projectForRole = computed(() => {
    const pid = projectIdFromTask.value;
    if (!pid) return null;
    return { id: pid };
});
const userProjectRole = useProjectRole(projectForRole);
const { canDo, canManage } = usePermissions(projectIdFromTask);
const canManageProjects = canManage('projects', userProjectRole);
const canChangeDueDate = computed(() => canDo('change_due_date', userProjectRole).value === true || canManageProjects.value === true);

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

// Files
const taskFiles = ref([]);
const loadingFiles = ref(false);
const uploadingFiles = ref(false);
const selectedFiles = ref([]);

const fetchTaskFiles = async () => {
    if (!props.taskId) return;
    loadingFiles.value = true;
    try {
        const res = await window.axios.get('/api/files', { params: { model_type: 'Task', model_id: props.taskId } });
        taskFiles.value = res.data || [];
    } catch (e) {
        console.error('Error fetching task files', e);
    } finally {
        loadingFiles.value = false;
    }
};

const onFilesPicked = (e) => {
    selectedFiles.value = Array.from(e.target.files || []);
};

const uploadSelectedFiles = async () => {
    if (!selectedFiles.value.length) return;
    uploadingFiles.value = true;
    const form = new FormData();
    selectedFiles.value.forEach((f, i) => form.append(`files[${i}]`, f));
    form.append('model_type', 'Task');
    form.append('model_id', props.taskId);
    try {
        await window.axios.post('/api/files', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        notification.success('Files uploaded');
        selectedFiles.value = [];
        await fetchTaskFiles();
    } catch (e) {
        console.error('Upload failed', e);
        notification.error('Failed to upload files');
    } finally {
        uploadingFiles.value = false;
    }
};

const deleteTaskFile = async (file) => {
    const confirmed = await notification.confirmPrompt(`Delete file "${file.filename}"?`, { confirmText: 'Delete', cancelText: 'Cancel', type: 'warning' });
    if (!confirmed) return;
    try {
        await window.axios.delete(`/api/files/${file.id}`);
        notification.success('File deleted');
        await fetchTaskFiles();
    } catch (e) {
        console.error('Delete failed', e);
        notification.error('Failed to delete file');
    }
};

// --- File preview helpers (similar to DeliverableViewerModal's getDisplayContent) ---
const showFileViewer = ref(false);
const activeFile = ref(null);

const openFileViewer = (file) => {
    activeFile.value = file;
    showFileViewer.value = true;
};

const closeFileViewer = () => {
    showFileViewer.value = false;
    activeFile.value = null;
};

const getThumbnailSrc = (file) => {
    if (!file) return null;
    // Prefer generated thumbnail URL when present
    if (file.thumbnail_url) return file.thumbnail_url;
    // Fallback: if it's an image and we have a direct file URL
    if (file.mime_type && file.mime_type.includes('image') && file.path_url) {
        return file.path_url;
    }
    return null;
};

const computeDisplayForFile = (file) => {
    if (!file) return { type: 'none' };
    const url = file.path_url || null;
    const mimeType = (file.mime_type || '').toLowerCase();

    if (!url) return { type: 'none' };

    // Images: show as image
    if (mimeType.includes('image')) {
        return { type: 'image', src: url };
    }
    // PDFs and many text types render well in iframe
    if (mimeType.includes('pdf') || mimeType.includes('text') || mimeType.includes('html')) {
        return { type: 'iframe', src: url };
    }

    // Default: provide external link
    return { type: 'external_link', url };
};

const displayContentForActiveFile = computed(() => computeDisplayForFile(activeFile.value));

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
        fetchTaskFiles();
    } else {
        task.value = null; // Clear task details if no taskId
        taskFiles.value = [];
    }
}, { immediate: true });

// --- Inline Editing Functions ---
// Local cache of project users loaded on-demand
const projectUsersLocal = ref([]);
const loadingProjectUsers = ref(false);

const assignedToOptions = computed(() => {
    const source = (projectUsersLocal.value && projectUsersLocal.value.length)
        ? projectUsersLocal.value
        : props.projectUsers;
    return (source || []).map(user => ({
        value: user.id,
        label: user.name
    }));
});

const fetchProjectUsersForTask = async () => {
    const pid = projectIdFromTask.value;
    if (!pid) return;
    loadingProjectUsers.value = true;
    try {
        const res = await window.axios.get(`/api/projects/${pid}/users`);
        projectUsersLocal.value = res.data || [];
    } catch (e) {
        console.error('Failed to fetch project users', e);
        notification.error('Could not load project users');
    } finally {
        loadingProjectUsers.value = false;
    }
};

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
        fetchTaskActivities();
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
        fetchTaskActivities();
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

// New state for the block modal (reused via BlockReasonModal)
const showBlockTaskModal = ref(false);

const openBlockTaskModal = () => {
    showBlockTaskModal.value = true;
};

const confirmBlockTask = async (reason) => {
    try {
        task.value = await taskState.blockTask(task.value, reason);
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
                <div class="flex flex-wrap items-center gap-y-1 gap-x-2 text-sm text-gray-600 mb-4">
                    <span class="px-2 py-1 rounded-full text-xs font-medium" :class="taskStatusClass">
                        {{ task.status }}
                    </span>
                    <span v-if="task.task_type" class="text-indigo-600 font-medium">
                        |  {{ task.task_type?.name }}  |
                    </span>
                    <div class="w-full flex items-center gap-1 mt-1">
                        <span v-if="task.milestone?.project" class="text-blue-700 font-bold uppercase tracking-wider text-[10px] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100">
                            {{ task.milestone?.project?.name }}
                        </span>
                        <span v-if="task.milestone?.project && task.milestone?.name" class="text-gray-400 mx-0.5">/</span>
                        <span v-if="task.milestone?.name" class="text-purple-600 font-medium">
                            {{ task.milestone?.name }}
                        </span>
                    </div>
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

            <!-- Approval Required Warning (shown above the Task Checklist) -->
            <div v-if="task.needs_approval" class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-lg shadow-sm">
                <div class="flex items-start">
                    <svg class="h-5 w-5 text-amber-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.487 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0V9a1 1 0 112 0v4zm-1 4a1.25 1.25 0 100-2.5A1.25 1.25 0 0010 17z" clip-rule="evenodd" />
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-semibold text-amber-800">Approval required</h3>
                        <p class="mt-1 text-sm text-amber-700">
                            This task needs approval<span v-if="task.creator_name"> from <span class="font-medium">{{ task.creator_name }}</span></span>.
                        </p>
                        <p class="text-red-500">Do Not, complete the task and make sure you report it to {{ task.creator_name }} or Project Manager</p>
                    </div>
                </div>
            </div>

            <!-- QA Required Note -->
            <div v-if="task.requires_qa" class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded-lg shadow-sm">
                <div class="flex items-start gap-2">
                    <!-- Heroicon: Shield Check -->
                    <svg class="h-5 w-5 text-blue-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.375c-1.148 0-2.285-.22-3.351-.651l-2.262-.906a1.125 1.125 0 00-1.537 1.04V11.25c0 5.157 3.33 9.8 8.25 11.25 4.92-1.45 8.25-6.093 8.25-11.25V5.858a1.125 1.125 0 00-1.537-1.04l-2.262.906A9.568 9.568 0 0112 6.375z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 11.25"/></svg>
                    <div class="text-sm text-blue-800">
                        This task requires QA verification once completed.
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
                             @click="task.status !== 'Done' ? (fetchProjectUsersForTask(), editingAssignedTo = true) : notification.warning('Cannot change assignment for a completed task. Use the Revise button to change the task status first.')"
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
                        <div v-if="!editingDueDate"
                             @click="canChangeDueDate ? editingDueDate = true : null"
                             class="cursor-pointer"
                             :class="canChangeDueDate ? 'text-indigo-600 hover:text-indigo-800' : 'text-gray-700 cursor-default'">
                            {{ task.due_date ? moment(task.due_date).format('MMM D, YYYY') : 'No Due Date' }}
                            <span v-if="canChangeDueDate" class="text-xs text-gray-400 ml-1">(Click to edit)</span>
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

            <!-- Task Files -->
            <div class="bg-white p-4 rounded-lg shadow-sm">
                <div class="mb-4 border-b pb-3 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h5 class="text-lg font-semibold text-gray-800">Files</h5>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition disabled:opacity-60 disabled:cursor-not-allowed"
                                    :disabled="uploadingFiles || selectedFiles.length === 0"
                                    @click="uploadSelectedFiles">
                                <svg v-if="uploadingFiles" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span>{{ uploadingFiles ? 'Uploading...' : 'Upload' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Fancy choose files -->
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center px-4 py-2 rounded-md border border-dashed border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 cursor-pointer transition">
                            <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M4 12l1.5-1.5M20 12l-1.5-1.5M12 4v12"/></svg>
                            <span class="font-medium">Choose Files</span>
                            <input class="absolute inset-0 opacity-0 cursor-pointer" type="file" multiple @change="onFilesPicked" :disabled="uploadingFiles" />
                        </label>
                        <p class="text-xs text-gray-500">You can select multiple files. Images, PDFs, Docs are supported.</p>
                    </div>

                    <!-- Selected files chips -->
                    <div v-if="selectedFiles.length" class="flex flex-wrap gap-2">
                        <span v-for="(sf, idx) in selectedFiles" :key="idx" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 text-gray-800 text-xs">
                            <svg class="h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <span class="max-w-[160px] truncate" :title="sf.name">{{ sf.name }}</span>
                            <button type="button" class="text-gray-500 hover:text-red-600" @click="selectedFiles = selectedFiles.filter((_,i)=>i!==idx)">×</button>
                        </span>
                    </div>
                </div>

                <!-- Files list -->
                <div>
                    <div v-if="loadingFiles" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <div v-for="i in 6" :key="i" class="h-28 bg-gray-100 rounded animate-pulse"></div>
                    </div>
                    <div v-else-if="taskFiles.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        <div v-for="f in taskFiles" :key="f.id" class="group relative rounded-lg border border-gray-200 overflow-hidden bg-white shadow-sm hover:shadow-md transition">
                            <div class="aspect-video bg-gray-50 flex items-center justify-center overflow-hidden">
                                <img v-if="getThumbnailSrc(f)" :src="getThumbnailSrc(f)" :alt="f.filename" class="object-cover w-full h-full" @click="openFileViewer(f)" />
                                <div v-else class="text-gray-400" @click="openFileViewer(f)">
                                    <svg class="h-10 w-10" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                            </div>
                            <div class="p-2 flex items-center justify-between">
                                <button class="text-xs text-blue-600 hover:underline truncate max-w-[70%] text-left" :title="f.filename" @click="openFileViewer(f)">{{ f.filename }}</button>
                                <button class="text-red-600 hover:text-red-800 text-xs" @click="deleteTaskFile(f)">Delete</button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500">No files attached.</div>
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
        <BlockReasonModal
            :show="showBlockTaskModal"
            title="Block Task"
            confirm-text="Block Task"
            placeholder="Enter reason for blocking..."
            @close="showBlockTaskModal = false"
            @confirm="confirmBlockTask"
        />

        <!-- File Viewer Modal -->
        <Modal :show="showFileViewer" @close="closeFileViewer">
            <div class="p-4 sm:p-6 w-full max-w-4xl">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-lg font-semibold text-gray-800 truncate" :title="activeFile && activeFile.filename ? activeFile.filename : ''">{{ (activeFile && activeFile.filename) ? activeFile.filename : 'Preview' }}</h3>
                    <button class="text-gray-500 hover:text-gray-700" @click="closeFileViewer">✕</button>
                </div>
                <div class="bg-black/5 rounded-lg overflow-hidden" style="min-height: 320px;">
                    {{ displayContentForActiveFile.type }}
                    <template v-if="displayContentForActiveFile.type === 'iframe'">
                        <iframe :src="displayContentForActiveFile.src" class="w-full h-[70vh]" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                    </template>
                    <template v-else-if="displayContentForActiveFile.type === 'image'">
                        <img :src="displayContentForActiveFile.src" alt="Preview" class="w-full h-auto" />
                    </template>

                    <template v-else-if="displayContentForActiveFile.type === 'external_link'">
                        <div class="p-6 text-center text-gray-700">
                            <p>This file can't be embedded. You can open it using the link below:</p>
                            <a :href="displayContentForActiveFile.url" target="_blank" class="text-indigo-600 hover:underline break-all">{{ displayContentForActiveFile.url }}</a>
                        </div>
                    </template>
                    <template v-else>
                        <div class="p-6 text-center text-gray-500">No preview available.</div>
                    </template>
                </div>
            </div>
        </Modal>

    </div>
</template>
