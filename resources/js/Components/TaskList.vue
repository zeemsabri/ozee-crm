<script setup>
import { ref, computed, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import * as taskState from '@/Utils/taskState.js';
import * as notification from '@/Utils/notification.js';

const props = defineProps({
    tasks: {
        type: Array,
        required: true
    },
    projectId: {
        type: Number,
        default: null
    },
    showProjectColumn: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['task-updated', 'open-task-detail']);

// State for completed tasks section
const showCompletedTasks = ref(false);
const searchQuery = ref('');

// State for block task modal
const showBlockTaskModal = ref(false);
const selectedTaskForBlock = ref(null);
const blockReason = ref('');

// Computed properties for filtering tasks
const activeTasks = computed(() => {
    return props.tasks.filter(task => task.status !== 'Done');
});

const completedTasks = computed(() => {
    return props.tasks.filter(task => task.status === 'Done');
});

// Filtered tasks based on search query
const filteredActiveTasks = computed(() => {
    if (!searchQuery.value) {
        return activeTasks.value;
    }

    const query = searchQuery.value.toLowerCase();
    return activeTasks.value.filter(task =>
        task.name.toLowerCase().includes(query) ||
        (task.milestone?.name && task.milestone.name.toLowerCase().includes(query)) ||
        (task.project?.name && task.project.name.toLowerCase().includes(query))
    );
});

const filteredCompletedTasks = computed(() => {
    if (!searchQuery.value) {
        return completedTasks.value;
    }

    const query = searchQuery.value.toLowerCase();
    return completedTasks.value.filter(task =>
        task.name.toLowerCase().includes(query) ||
        (task.milestone?.name && task.milestone.name.toLowerCase().includes(query)) ||
        (task.project?.name && task.project.name.toLowerCase().includes(query))
    );
});

// Task action methods
const viewTaskDetails = (task) => {
    console.log('TaskList: Task object:', task);
    console.log('TaskList: Emitting open-task-detail with taskId:', task.id, 'projectId:', props.projectId);
    emit('open-task-detail', task.id, props.projectId);
};

const startTask = async (task) => {
    try {
        const updatedTask = await taskState.startTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error starting task:', error);
    }
};

const pauseTask = async (task) => {
    try {
        const updatedTask = await taskState.pauseTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error pausing task:', error);
    }
};

const resumeTask = async (task) => {
    try {
        const updatedTask = await taskState.resumeTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error resuming task:', error);
    }
};

const completeTask = async (task) => {
    try {
        const updatedTask = await taskState.completeTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error completing task:', error);
    }
};

const openBlockTaskModal = (task) => {
    selectedTaskForBlock.value = task;
    blockReason.value = '';
    showBlockTaskModal.value = true;
};

const blockTask = async () => {
    if (!selectedTaskForBlock.value) return;

    try {
        const updatedTask = await taskState.blockTask(selectedTaskForBlock.value, blockReason.value);
        emit('task-updated', updatedTask);
        showBlockTaskModal.value = false;
    } catch (error) {
        console.error('Error blocking task:', error);
    }
};

const unblockTask = async (task) => {
    try {
        const updatedTask = await taskState.unblockTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error unblocking task:', error);
    }
};

const deleteTask = async (task) => {
    try {
        if (await taskState.deleteTask(task)) {
            emit('task-updated', null);
        }
    } catch (error) {
        console.error('Error deleting task:', error);
    }
};

const reviseTask = async (task) => {
    try {
        const updatedTask = await taskState.reviseTask(task);
        emit('task-updated', updatedTask);
    } catch (error) {
        console.error('Error revising task:', error);
    }
};

// Format date for display
const formatDate = (dateString) => {
    if (!dateString) return 'No due date';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
};
</script>

<template>
    <div class="space-y-6">
        <!-- Search input -->
        <div class="mb-4">
            <TextInput
                v-model="searchQuery"
                placeholder="Search tasks by name, milestone, or project..."
                class="w-full"
            />
        </div>

        <!-- Active Tasks Section -->
        <div class="bg-white overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Milestone</th>
                        <th v-if="showProjectColumn" scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="task in filteredActiveTasks" :key="task.id"
                        class="hover:bg-gray-50 transition-colors"
                        :class="{
                            'bg-red-50': task.due_date && new Date(task.due_date) < new Date(),
                            'bg-yellow-50': task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()
                        }">
                        <td class="px-4 py-3 text-sm text-gray-900">{{ task.name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="taskState.getTaskStatusClasses(task.status)"
                            >
                                {{ task.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="taskState.getTaskPriorityClasses(task.priority)"
                            >
                                {{ taskState.formatPriority(task.priority) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ task.milestone?.name || 'N/A' }}
                        </td>
                        <td v-if="showProjectColumn" class="px-4 py-3 text-sm text-gray-700">
                            {{ task.project?.name || 'N/A' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            {{ formatDate(task.due_date) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex space-x-2">
                                <PrimaryButton
                                    @click="viewTaskDetails(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-purple-500 hover:bg-purple-600"
                                >
                                    View
                                </PrimaryButton>

                                <!-- Start Button -->
                                <PrimaryButton
                                    v-if="task.status === 'To Do'"
                                    @click="startTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-blue-500 hover:bg-blue-600"
                                >
                                    Start
                                </PrimaryButton>

                                <!-- Pause Button -->
                                <PrimaryButton
                                    v-if="task.status === 'In Progress'"
                                    @click="pauseTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-orange-500 hover:bg-orange-600"
                                >
                                    Pause
                                </PrimaryButton>

                                <!-- Resume Button -->
                                <PrimaryButton
                                    v-if="task.status === 'Paused'"
                                    @click="resumeTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-blue-500 hover:bg-blue-600"
                                >
                                    Resume
                                </PrimaryButton>

                                <!-- Block Button -->
                                <PrimaryButton
                                    v-if="task.status !== 'Blocked' && task.status !== 'Done'"
                                    @click="openBlockTaskModal(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-red-500 hover:bg-red-600"
                                >
                                    Block
                                </PrimaryButton>

                                <!-- Unblock Button -->
                                <PrimaryButton
                                    v-if="task.status === 'Blocked'"
                                    @click="unblockTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-green-500 hover:bg-green-600"
                                >
                                    Unblock
                                </PrimaryButton>

                                <!-- Complete Button -->
                                <PrimaryButton
                                    v-if="task.status === 'In Progress'"
                                    @click="completeTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-green-500 hover:bg-green-600"
                                >
                                    Complete
                                </PrimaryButton>

                                <!-- Delete Button (only for To Do tasks) -->
                                <PrimaryButton
                                    v-if="task.status === 'To Do'"
                                    @click="deleteTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-red-500 hover:bg-red-600"
                                    :disabled="task.status !== 'To Do'"
                                >
                                    Delete
                                </PrimaryButton>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredActiveTasks.length === 0">
                        <td :colspan="showProjectColumn ? 7 : 6" class="px-4 py-8 text-center text-gray-500">
                            No active tasks found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Completed Tasks Section -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-semibold text-gray-700">Completed Tasks ({{ completedTasks.length }})</h3>
                <button
                    @click="showCompletedTasks = !showCompletedTasks"
                    class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center"
                >
                    {{ showCompletedTasks ? 'Hide' : 'Show' }}
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="showCompletedTasks ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"></path>
                    </svg>
                </button>
            </div>

            <div v-if="showCompletedTasks" class="bg-white overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Milestone</th>
                            <th v-if="showProjectColumn" scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="task in filteredCompletedTasks" :key="task.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ task.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="taskState.getTaskStatusClasses(task.status)"
                                >
                                    {{ task.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="taskState.getTaskPriorityClasses(task.priority)"
                                >
                                    {{ taskState.formatPriority(task.priority) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ task.milestone?.name || 'N/A' }}
                            </td>
                            <td v-if="showProjectColumn" class="px-4 py-3 text-sm text-gray-700">
                                {{ task.project?.name || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ formatDate(task.due_date) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex space-x-2">
                                    <PrimaryButton
                                        @click="viewTaskDetails(task)"
                                        class="px-2 py-0.5 text-xs leading-4 bg-purple-500 hover:bg-purple-600"
                                    >
                                        View
                                    </PrimaryButton>

                                    <!-- Revise Button -->
                                    <PrimaryButton
                                        @click="reviseTask(task)"
                                        class="px-2 py-0.5 text-xs leading-4 bg-yellow-500 hover:bg-yellow-600"
                                    >
                                        Revise
                                    </PrimaryButton>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredCompletedTasks.length === 0">
                            <td :colspan="showProjectColumn ? 7 : 6" class="px-4 py-8 text-center text-gray-500">
                                No completed tasks found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Block Task Modal -->
        <Modal :show="showBlockTaskModal" @close="showBlockTaskModal = false">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Block Task</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Please provide a reason for blocking this task. This will be recorded in the task history.
                </p>
                <div class="mb-4">
                    <TextInput
                        v-model="blockReason"
                        placeholder="Enter reason for blocking the task"
                        class="w-full"
                    />
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
