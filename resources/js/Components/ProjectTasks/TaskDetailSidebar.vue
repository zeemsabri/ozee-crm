<script setup>
import { ref, watch, computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import moment from "moment";
import TaskNoteModal from '@/Components/ProjectTasks/TaskNoteModal.vue'; // Re-use existing modal

const props = defineProps({
    taskId: {
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
const newAssignedToId = ref(null);
const newDueDate = ref(null);

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
    } catch (error) {
        taskError.value = 'Failed to load task details.';
        console.error('Error fetching task details:', error);
    } finally {
        loadingTask.value = false;
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
        alert('Failed to update assigned user. Please try again.');
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
        alert('Failed to update due date. Please try again.');
    } finally {
        editingDueDate.value = false;
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
        await window.axios.delete(`/api/tasks/${task.value.id}`);
        emit('task-deleted', task.value.id); // Notify parent of deletion
        emit('close'); // Close sidebar after deletion
    } catch (error) {
        console.error('Error deleting task:', error);
        alert('Failed to delete task. Please try again.');
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
</script>

<template>
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
        </div>

        <!-- Inline Editable Details -->
        <div class="space-y-4 p-4 bg-white rounded-lg shadow-sm">
            <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Task Attributes</h5>

            <!-- Assigned To -->
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                <InputLabel class="min-w-[100px] text-gray-600">Assigned To:</InputLabel>
                <div class="flex-1 text-right">
                    <div v-if="!editingAssignedTo" @click="editingAssignedTo = true" class="cursor-pointer text-indigo-600 hover:text-indigo-800">
                        {{ task.assigned_to?.name || 'Unassigned' }} <span class="text-xs text-gray-400 ml-1">(Click to edit)</span>
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
            <!-- Created At -->
            <div class="flex items-center justify-between py-2 border-b border-gray-100">
                <InputLabel class="min-w-[100px] text-gray-600">Created:</InputLabel>
                <span class="text-gray-700">{{ task.created_at ? new Date(task.created_at).toLocaleDateString() : 'N/A' }}</span>
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

        <!-- Task History (Placeholder) -->
<!--        <div class="bg-white p-4 rounded-lg shadow-sm">-->
<!--            <h5 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">History</h5>-->
<!--            <div class="text-gray-500 text-sm py-2">Task history will be displayed here. (Requires backend implementation)</div>-->
<!--            &lt;!&ndash; Example history item:-->
<!--            <div class="p-3 bg-gray-50 rounded-md mb-2">-->
<!--                <p class="text-sm text-gray-700">Status changed from 'To Do' to 'In Progress'</p>-->
<!--                <p class="text-xs text-gray-500 mt-1">By John Doe on 2023-10-26</p>-->
<!--            </div>-->
<!--            &ndash;&gt;-->
<!--        </div>-->

        <!-- Action Buttons -->
<!--        <div class="mt-6 flex justify-end space-x-3 p-4 bg-white rounded-lg shadow-sm">-->
<!--            <DangerButton @click="deleteTask">Delete Task</DangerButton>-->
<!--        </div>-->

        <!-- Task Note Modal -->
        <TaskNoteModal
            :show="showTaskNoteModal"
            :task-for-note="task"
            @close="showTaskNoteModal = false"
            @note-added="handleNoteAdded"
        />
    </div>
    <div v-else class="text-center py-8 text-gray-500">
        Select a task to view details.
    </div>
</template>
