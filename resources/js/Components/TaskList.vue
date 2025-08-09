<!-- TaskList.vue -->
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
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

// State for view mode and localStorage persistence
const viewMode = ref('card'); // Default to card view
const currentDate = ref(new Date());

onMounted(() => {
    const savedViewMode = localStorage.getItem('taskListViewMode');
    if (savedViewMode) {
        viewMode.value = savedViewMode;
    }
});

const setViewMode = (mode) => {
    viewMode.value = mode;
    localStorage.setItem('taskListViewMode', mode);
};

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
        await taskState.deleteTask(task);
        emit('task-updated', null); // Signal parent to refresh the list
    } catch (error) {
        console.error('Error deleting task:', error);
        // The taskState utility handles notifications
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

// Calendar View Logic
const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const today = new Date();
today.setHours(0, 0, 0, 0);

const startOfMonth = computed(() => {
    return new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), 1);
});

const endOfMonth = computed(() => {
    return new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 0);
});

const formattedMonthYear = computed(() => {
    return currentDate.value.toLocaleString('en-US', { month: 'long', year: 'numeric' });
});

const calendarDays = computed(() => {
    const days = [];
    const firstDayIndex = startOfMonth.value.getDay();
    const lastDay = endOfMonth.value.getDate();

    // Add empty days at the start
    for (let i = 0; i < firstDayIndex; i++) {
        days.push(null);
    }

    // Add days of the month
    for (let i = 1; i <= lastDay; i++) {
        days.push(new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), i));
    }

    return days;
});

const prevMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};

const tasksByDate = computed(() => {
    const tasksMap = {};
    const allFilteredTasks = [...filteredActiveTasks.value, ...filteredCompletedTasks.value];

    allFilteredTasks.forEach(task => {
        if (task.due_date) {
            const date = new Date(task.due_date);
            const dateString = date.toLocaleDateString();
            if (!tasksMap[dateString]) {
                tasksMap[dateString] = [];
            }
            tasksMap[dateString].push(task);
        }
    });

    return tasksMap;
});

const isToday = (date) => {
    return date && date.toDateString() === today.toDateString();
};
</script>

<template>
    <div class="space-y-6 font-sans">
        <!-- Controls and Search -->
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 space-y-4 md:space-y-0">
            <div class="w-full md:w-1/2">
                <TextInput
                    v-model="searchQuery"
                    placeholder="Search tasks by name, milestone, or project..."
                    class="w-full text-sm py-2 px-4 rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 transition-all duration-200"
                />
            </div>
            <div class="flex items-center space-x-2">
                <button
                    @click="setViewMode('card')"
                    class="p-2 rounded-lg transition-colors duration-200"
                    :class="{'bg-gray-200 text-indigo-600 shadow-inner': viewMode === 'card', 'text-gray-400 hover:text-gray-600': viewMode !== 'card'}"
                    aria-label="Switch to Card View"
                >
                    <!-- Card View Icon (Inline SVG) -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-grid"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
                </button>
                <button
                    @click="setViewMode('list')"
                    class="p-2 rounded-lg transition-colors duration-200"
                    :class="{'bg-gray-200 text-indigo-600 shadow-inner': viewMode === 'list', 'text-gray-400 hover:text-gray-600': viewMode !== 'list'}"
                    aria-label="Switch to List View"
                >
                    <!-- List View Icon (Inline SVG) -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-todo"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M12 12H7"/><path d="M7 18h10"/></svg>
                </button>
                <button
                    @click="setViewMode('calendar')"
                    class="p-2 rounded-lg transition-colors duration-200"
                    :class="{'bg-gray-200 text-indigo-600 shadow-inner': viewMode === 'calendar', 'text-gray-400 hover:text-gray-600': viewMode !== 'calendar'}"
                    aria-label="Switch to Calendar View"
                >
                    <!-- Calendar View Icon (Inline SVG) -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-days"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M12 16h2"/><path d="M8 16h.01"/><path d="M16 20h.01"/><path d="M12 20h.01"/><path d="M8 20h.01"/></svg>
                </button>
            </div>
        </div>

        <!-- Active Tasks Section -->
        <div class="space-y-4" v-if="viewMode !== 'calendar'">
            <h3 class="text-xl font-bold text-gray-800">Active Tasks</h3>
            <div v-if="filteredActiveTasks.length === 0" class="text-center py-8 text-gray-500 bg-white rounded-xl shadow-sm">
                No active tasks found.
            </div>

            <!-- Card View -->
            <div v-if="viewMode === 'card'" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="task in filteredActiveTasks"
                    :key="task.id"
                    class="relative bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-200 cursor-pointer group"
                    :class="{
                        'border-l-4 border-red-500': task.due_date && new Date(task.due_date) < new Date(),
                        'border-l-4 border-yellow-500': task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString() && new Date(task.due_date) >= new Date(),
                        'border-l-4 border-gray-200': !(task.due_date && new Date(task.due_date) < new Date()) && !(task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString())
                    }"
                    @click="viewTaskDetails(task)"
                >
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="text-lg font-semibold text-gray-900 pr-10">{{ task.name }}</h4>
                        <div class="absolute top-6 right-6">
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium"
                                :class="taskState.getTaskStatusClasses(task.status)"
                            >
                                {{ task.status }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600 mb-4">
                        <span class="inline-flex items-center gap-1">
                             <!-- Priority Icon -->
                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flag"
                                  :class="{
                                    'text-red-500': task.priority === 'high',
                                    'text-yellow-500': task.priority === 'medium',
                                    'text-green-500': task.priority === 'low'
                                }"
                             >
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                                <line x1="4" x2="4" y1="22" y2="15"/>
                            </svg>
                            <span class="font-medium"
                                  :class="{
                                    'text-red-500': task.priority === 'high',
                                    'text-yellow-500': task.priority === 'medium',
                                    'text-green-500': task.priority === 'low'
                                }"
                            >
                                {{ taskState.formatPriority(task.priority) }}
                            </span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <!-- Due Date Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar text-gray-400">
                                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                                <line x1="16" x2="16" y1="2" y2="6"/>
                                <line x1="8" x2="8" y1="2" y2="6"/>
                                <line x1="3" x2="21" y1="10" y2="10"/>
                            </svg>
                            <span>{{ formatDate(task.due_date) }}</span>
                        </span>
                        <span v-if="task.milestone?.name" class="inline-flex items-center gap-1">
                            <!-- Milestone Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-landmark text-gray-400">
                                <line x1="2" x2="22" y1="22" y2="22"/>
                                <path d="M6 18v3"/>
                                <path d="M10 18v3"/>
                                <path d="M14 18v3"/>
                                <path d="M18 18v3"/>
                                <path d="M8 22v-4-1.5a.5.5 0 011-1.5h6a.5.5 0 011 1.5V18v4"/>
                                <path d="M12 12V4"/>
                                <path d="M8 4h8"/>
                                <path d="M12 4L8 8"/>
                                <path d="M12 4l4 4"/>
                                <path d="M8 8H6a2 2 0 00-2 2v10"/>
                                <path d="M16 8h2a2 2 0 012 2v10"/>
                            </svg>
                            <span>{{ task.milestone.name }}</span>
                        </span>
                    </div>

                    <div class="flex justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 absolute bottom-6 right-6">
                        <!-- Action Buttons -->
                        <PrimaryButton
                            v-if="task.status === 'To Do'"
                            @click.stop="startTask(task)"
                            class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 transition-colors rounded-lg shadow-md"
                        >
                            Start
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status === 'In Progress'"
                            @click.stop="pauseTask(task)"
                            class="px-3 py-1 text-xs bg-orange-500 hover:bg-orange-600 transition-colors rounded-lg shadow-md"
                        >
                            Pause
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status === 'Paused'"
                            @click.stop="resumeTask(task)"
                            class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 transition-colors rounded-lg shadow-md"
                        >
                            Resume
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status === 'Blocked'"
                            @click.stop="unblockTask(task)"
                            class="px-3 py-1 text-xs bg-green-500 hover:bg-green-600 transition-colors rounded-lg shadow-md"
                        >
                            Unblock
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status !== 'Blocked' && task.status !== 'Done'"
                            @click.stop="openBlockTaskModal(task)"
                            class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 transition-colors rounded-lg shadow-md"
                        >
                            Block
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status === 'In Progress'"
                            @click.stop="completeTask(task)"
                            class="px-3 py-1 text-xs bg-green-500 hover:bg-green-600 transition-colors rounded-lg shadow-md"
                        >
                            Complete
                        </PrimaryButton>
                        <PrimaryButton
                            v-if="task.status === 'To Do'"
                            @click.stop="deleteTask(task)"
                            class="px-3 py-1 text-xs bg-gray-400 hover:bg-gray-500 transition-colors rounded-lg shadow-md"
                            :disabled="task.status !== 'To Do'"
                        >
                            Delete
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else-if="viewMode === 'list'" class="bg-white overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
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
                        class="hover:bg-gray-50 transition-colors group"
                        :class="{
                            'bg-red-50': task.due_date && new Date(task.due_date) < new Date(),
                            'bg-yellow-50': task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()
                        }"
                        @click="viewTaskDetails(task)"
                    >
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
                            <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <PrimaryButton
                                    @click.stop="viewTaskDetails(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-purple-500 hover:bg-purple-600"
                                >
                                    View
                                </PrimaryButton>

                                <!-- Start Button -->
                                <PrimaryButton
                                    v-if="task.status === 'To Do'"
                                    @click.stop="startTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-blue-500 hover:bg-blue-600"
                                >
                                    Start
                                </PrimaryButton>

                                <!-- Pause Button -->
                                <PrimaryButton
                                    v-if="task.status === 'In Progress'"
                                    @click.stop="pauseTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-orange-500 hover:bg-orange-600"
                                >
                                    Pause
                                </PrimaryButton>

                                <!-- Resume Button -->
                                <PrimaryButton
                                    v-if="task.status === 'Paused'"
                                    @click.stop="resumeTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-blue-500 hover:bg-blue-600"
                                >
                                    Resume
                                </PrimaryButton>

                                <!-- Block Button -->
                                <PrimaryButton
                                    v-if="task.status !== 'Blocked' && task.status !== 'Done'"
                                    @click.stop="openBlockTaskModal(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-red-500 hover:bg-red-600"
                                >
                                    Block
                                </PrimaryButton>

                                <!-- Unblock Button -->
                                <PrimaryButton
                                    v-if="task.status === 'Blocked'"
                                    @click.stop="unblockTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-green-500 hover:bg-green-600"
                                >
                                    Unblock
                                </PrimaryButton>

                                <!-- Complete Button -->
                                <PrimaryButton
                                    v-if="task.status === 'In Progress'"
                                    @click.stop="completeTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-green-500 hover:bg-green-600"
                                >
                                    Complete
                                </PrimaryButton>

                                <!-- Delete Button (only for To Do tasks) -->
                                <PrimaryButton
                                    v-if="task.status === 'To Do'"
                                    @click.stop="deleteTask(task)"
                                    class="px-2 py-0.5 text-xs leading-4 bg-gray-400 hover:bg-gray-500"
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
        </div>

        <!-- Calendar View -->
        <div v-else-if="viewMode === 'calendar'" class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <button @click="prevMonth" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <h3 class="text-xl font-bold text-gray-800">{{ formattedMonthYear }}</h3>
                <button @click="nextMonth" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-7 text-center text-sm font-semibold text-gray-500 border-b border-gray-200">
                <div v-for="day in daysOfWeek" :key="day" class="p-2">{{ day }}</div>
            </div>
            <div class="grid grid-cols-7">
                <div
                    v-for="(day, index) in calendarDays"
                    :key="index"
                    class="p-2 border border-gray-200 h-28 relative overflow-y-auto"
                    :class="{'text-gray-400 bg-gray-50': !day, 'bg-white': day, 'border-2 border-indigo-500 rounded-md shadow-md': isToday(day)}"
                >
                    <span v-if="day" class="font-bold" :class="{'text-indigo-600': isToday(day)}">{{ day.getDate() }}</span>
                    <div v-if="day" class="mt-1 space-y-1">
                        <div
                            v-for="task in tasksByDate[day.toLocaleDateString()]"
                            :key="task.id"
                            class="px-2 py-0.5 rounded-full text-xs font-medium truncate cursor-pointer"
                            :class="taskState.getTaskStatusClasses(task.status)"
                            @click.stop="viewTaskDetails(task)"
                            :title="task.name"
                        >
                            {{ task.name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Tasks Section -->
        <div class="border-t border-gray-200 pt-6 mt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">Completed Tasks ({{ completedTasks.length }})</h3>
                <button
                    @click="showCompletedTasks = !showCompletedTasks"
                    class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center transition-colors duration-200"
                >
                    {{ showCompletedTasks ? 'Hide' : 'Show' }}
                    <svg class="w-4 h-4 ml-1 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                         :class="{'rotate-180': showCompletedTasks}">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>

            <div v-if="showCompletedTasks && viewMode !== 'calendar'">
                <!-- Card View for Completed Tasks -->
                <div v-if="viewMode === 'card'" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="task in filteredCompletedTasks"
                        :key="task.id"
                        class="relative bg-white rounded-xl shadow-lg p-6 transition-all duration-200 cursor-pointer group opacity-75"
                        @click="viewTaskDetails(task)"
                    >
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-lg font-semibold text-gray-400 line-through pr-10">{{ task.name }}</h4>
                            <div class="absolute top-6 right-6">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium"
                                    :class="taskState.getTaskStatusClasses(task.status)"
                                >
                                    {{ task.status }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500 mb-4">
                            <span class="inline-flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flag text-gray-400">
                                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                                    <line x1="4" x2="4" y1="22" y2="15"/>
                                </svg>
                                <span class="font-medium">{{ taskState.formatPriority(task.priority) }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar text-gray-400">
                                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                                    <line x1="16" x2="16" y1="2" y2="6"/>
                                    <line x1="8" x2="8" y1="2" y2="6"/>
                                    <line x1="3" x2="21" y1="10" y2="10"/>
                                </svg>
                                <span>{{ formatDate(task.due_date) }}</span>
                            </span>
                            <span v-if="task.milestone?.name" class="inline-flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-landmark text-gray-400">
                                    <line x1="2" x2="22" y1="22" y2="22"/>
                                    <path d="M6 18v3"/>
                                    <path d="M10 18v3"/>
                                    <path d="M14 18v3"/>
                                    <path d="M18 18v3"/>
                                    <path d="M8 22v-4-1.5a.5.5 0 011-1.5h6a.5.5 0 011 1.5V18v4"/>
                                    <path d="M12 12V4"/>
                                    <path d="M8 4h8"/>
                                    <path d="M12 4L8 8"/>
                                    <path d="M12 4l4 4"/>
                                    <path d="M8 8H6a2 2 0 00-2 2v10"/>
                                    <path d="M16 8h2a2 2 0 012 2v10"/>
                                </svg>
                                <span>{{ task.milestone.name }}</span>
                            </span>
                        </div>

                        <div class="flex justify-end space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 absolute bottom-6 right-6">
                            <!-- Action Buttons -->
                            <PrimaryButton
                                @click.stop="reviseTask(task)"
                                class="px-3 py-1 text-xs bg-yellow-500 hover:bg-yellow-600 transition-colors rounded-lg shadow-md"
                            >
                                Revise
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- List View for Completed Tasks -->
                <div v-else-if="viewMode === 'list'" class="bg-white overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
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
                        <tr v-for="task in filteredCompletedTasks" :key="task.id" class="hover:bg-gray-50 transition-colors group" @click="viewTaskDetails(task)">
                            <td class="px-4 py-3 text-sm text-gray-900 line-through">{{ task.name }}</td>
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
                                <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                    <PrimaryButton
                                        @click.stop="viewTaskDetails(task)"
                                        class="px-2 py-0.5 text-xs leading-4 bg-purple-500 hover:bg-purple-600"
                                    >
                                        View
                                    </PrimaryButton>

                                    <!-- Revise Button -->
                                    <PrimaryButton
                                        @click.stop="reviseTask(task)"
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

            <div v-if="filteredCompletedTasks.length === 0 && showCompletedTasks && viewMode !== 'calendar'" class="text-center py-8 text-gray-500 bg-white rounded-xl shadow-sm">
                No completed tasks found.
            </div>
        </div>


        <!-- Block Task Modal -->
        <Modal :show="showBlockTaskModal" @close="showBlockTaskModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Block Task</h3>
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
