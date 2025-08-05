<script setup>
import { ref, computed, onMounted, defineExpose, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import TaskList from '@/Components/TaskList.vue';
import * as taskState from '@/Utils/taskState.js';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

// Props
const props = defineProps({
    // We are no longer passing assignedTasksRef as a prop, as defineExpose is the better way to expose methods.
});

// Emits
const emit = defineEmits(['view-due-overdue-tasks', 'task-counts-updated']);

// Assigned tasks state
const assignedTasks = ref([]);
const loadingAssignedTasks = ref(true);
const assignedTasksError = ref('');
const expandAssignedTasks = ref(false);
const assignedTasksSearchQuery = ref('');

// Filter for assigned tasks - can be 'all', 'due-overdue'
const assignedTasksFilter = ref('all');

// Fetches all tasks assigned to the current user
const fetchAssignedTasks = async () => {
    loadingAssignedTasks.value = true;
    assignedTasksError.value = '';
    try {
        assignedTasks.value = await taskState.fetchAssignedTasks();
        // Emit updated task counts after a successful fetch
        emit('task-counts-updated', {
            overdue: overdueTasksCount.value,
            dueToday: dueTodayTasksCount.value
        });
    } catch (err) {
        assignedTasksError.value = 'Failed to load assigned tasks';
        console.error('Error fetching assigned tasks:', err);
    } finally {
        loadingAssignedTasks.value = false;
    }
};

// Computed property for client-side task filtering
const filteredAssignedTasks = computed(() => {
    if (!assignedTasksSearchQuery.value) {
        return assignedTasks.value; // If no search query, return all tasks
    }
    const lowerCaseQuery = assignedTasksSearchQuery.value.toLowerCase();
    return assignedTasks.value.filter(task =>
        task.name.toLowerCase().includes(lowerCaseQuery) ||
        (task.milestone?.name && task.milestone.name.toLowerCase().includes(lowerCaseQuery)) ||
        (task.project?.name && task.project.name.toLowerCase().includes(lowerCaseQuery))
    );
});

// Filtered assigned tasks based on the current filter
const filteredAssignedTasksWithFilter = computed(() => {
    if (assignedTasksFilter.value === 'due-overdue') {
        return filteredAssignedTasks.value.filter(task =>
            (task.due_date && new Date(task.due_date) < new Date()) || // overdue
            (task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()) // due today
        );
    }
    return filteredAssignedTasks.value;
});

// Toggles the visibility of the assigned tasks section and fetches data if expanding
const toggleAssignedTasks = () => {
    expandAssignedTasks.value = !expandAssignedTasks.value;
    if (expandAssignedTasks.value && assignedTasks.value.length === 0) { // Only fetch if not already fetched
        fetchAssignedTasks();
    }
};

// Handler for task updates from TaskList component
const handleTaskUpdated = async (updatedTask) => {
    // Refresh the tasks list to reflect the changes
    await fetchAssignedTasks();
};

// Handler for opening task details from TaskList component
const handleOpenTaskDetail = (taskId, taskProjectId) => {
    if (taskProjectId) {
        // If projectId is passed directly, use it
        openTaskDetailSidebar(taskId, taskProjectId);
    } else {
        // Fallback to finding the task in assignedTasks
        const task = assignedTasks.value.find(t => t.id === taskId);
        if (task) {
            openTaskDetailSidebar(taskId, task.project_id);
        } else {
            console.error('Task not found and no projectId provided');
        }
    }
};

// Computed properties for task counts
const overdueTasksCount = computed(() =>
    assignedTasks.value.filter(task => task.due_date && new Date(task.due_date) < new Date()).length
);

const dueTodayTasksCount = computed(() =>
    assignedTasks.value.filter(task => task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()).length
);

const inProgressTasksCount = computed(() =>
    assignedTasks.value.filter(task => task.status === 'In Progress').length
);

// New method to be called from the parent component
const assignedTasksRef = ref(null);
const showDueOverdueTasksAndScroll = () => {
    // Expand the section and set the filter
    expandAssignedTasks.value = true;
    assignedTasksFilter.value = 'due-overdue';

    // Fetch tasks if they haven't been fetched yet
    if (assignedTasks.value.length === 0) {
        fetchAssignedTasks();
    }

    // Scroll to this component
    nextTick(() => {
        assignedTasksRef.value.scrollIntoView({ behavior: 'smooth' });
    });
};

// Expose the method to the parent component
defineExpose({ showDueOverdueTasksAndScroll });

// Fetch data on component mount
onMounted(() => {
    fetchAssignedTasks();
});
</script>

<template>
    <div ref="assignedTasksRef" class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Your Assigned Tasks</h3>
                <div class="flex space-x-4 mt-2">
                    <div class="flex items-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-800 font-medium text-xs mr-2">
                            {{ overdueTasksCount }}
                        </span>
                        <span class="text-sm text-gray-600">Overdue</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-800 font-medium text-xs mr-2">
                            {{ dueTodayTasksCount }}
                        </span>
                        <span class="text-sm text-gray-600">Due Today</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-800 font-medium text-xs mr-2">
                            {{ inProgressTasksCount }}
                        </span>
                        <span class="text-sm text-gray-600">In Progress</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div v-if="expandAssignedTasks && assignedTasksFilter === 'due-overdue'" class="text-sm text-amber-600 font-medium">
                    Showing due and overdue tasks
                    <button @click="assignedTasksFilter = 'all'" class="ml-2 text-blue-600 hover:text-blue-800 underline">
                        Show all
                    </button>
                </div>
                <button
                    @click="toggleAssignedTasks"
                    class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition ease-in-out duration-150 w-48"
                >
                    {{ expandAssignedTasks ? 'Collapse Tasks' : 'View My Tasks (' + assignedTasks.length + ')' }}
                </button>
            </div>
        </div>

        <div v-if="expandAssignedTasks" class="mt-4">
            <div v-if="assignedTasksError" class="text-center text-sm text-red-500 py-6">{{ assignedTasksError }}</div>
            <div v-else-if="assignedTasks.length === 0 && !loadingAssignedTasks" class="text-center text-sm text-gray-500 py-6">
                {{ assignedTasksFilter === 'due-overdue' ? 'No due or overdue tasks found.' : 'No assigned tasks found.' }}
            </div>
            <div v-else class="relative">
                <!-- Loading overlay for assigned tasks -->
                <div v-if="loadingAssignedTasks" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-10">
                    <svg class="animate-spin h-8 w-8 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-3 text-purple-700">Loading assigned tasks...</span>
                </div>

                <TaskList
                    :tasks="filteredAssignedTasksWithFilter"
                    :show-project-column="true"
                    @task-updated="handleTaskUpdated"
                    @open-task-detail="handleOpenTaskDetail"
                />
            </div>
        </div>
    </div>
</template>
