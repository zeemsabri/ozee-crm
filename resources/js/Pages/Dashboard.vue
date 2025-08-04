<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NotesModal from '@/Components/NotesModal.vue';
import AvailabilityPrompt from '@/Components/Availability/AvailabilityPrompt.vue';
import TextInput from '@/Components/TextInput.vue';
import StandupModal from '@/Components/StandupModal.vue';
import TaskNotificationPrompt from '@/Components/TaskNotificationPrompt.vue';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

const authUser = computed(() => usePage().props.auth.user);

// Props
const props = defineProps({
    projectCount: Number,
});

// Reactive state for Projects section
const projects = ref([]); // Stores ALL fetched projects
const loadingProjects = ref(true);
const projectsError = ref('');
const expandProjects = ref(false);
const projectSearchQuery = ref('');

// Reactive state for Task statistics section
const taskStats = ref({
    total_due_tasks: 0,
    projects: [] // Stores ALL fetched task projects
});
const loadingTasks = ref(true);
const taskError = ref('');
const expandTasks = ref(false);
const taskSearchQuery = ref('');

// Weekly availability state
const weeklyAvailability = ref({
    availabilities: [],
    start_date: '',
    end_date: ''
});
const loadingAvailability = ref(false);
const availabilityError = ref('');

// Notes modal state
const showNotesModal = ref(false);
const selectedProjectId = ref(null);

// Standup modal state
const showStandupModal = ref(false);
const selectedProjectIdForStandup = ref(null);

// Assigned tasks state
const assignedTasks = ref([]);
const loadingAssignedTasks = ref(true);
const assignedTasksError = ref('');
const expandAssignedTasks = ref(false);
const assignedTasksSearchQuery = ref('');

// Helper function to format dates for display
const formatDateDisplay = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric'
    });
};

// --- Project Section Logic ---

// Fetches ALL projects from the API (only once when expanded)
const fetchProjects = async () => {
    loadingProjects.value = true;
    projectsError.value = '';
    try {
        // No 'params: { search: ... }' here as we fetch all and filter client-side
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
    } catch (err) {
        projectsError.value = 'Failed to load projects';
        console.error('Error fetching projects:', err);
    } finally {
        loadingProjects.value = false;
    }
};

// Computed property for client-side project filtering
const filteredProjects = computed(() => {
    if (!projectSearchQuery.value) {
        return projects.value; // If no search query, return all projects
    }
    const lowerCaseQuery = projectSearchQuery.value.toLowerCase();
    return projects.value.filter(project =>
        project.name.toLowerCase().includes(lowerCaseQuery) ||
        (project.user_role && project.user_role.toLowerCase().includes(lowerCaseQuery)) ||
        project.status.toLowerCase().includes(lowerCaseQuery.replace(' ', '_'))
    );
});

// Toggles the visibility of the projects section and fetches data if expanding
const toggleProjects = () => {
    expandProjects.value = !expandProjects.value;
    if (expandProjects.value && projects.value.length === 0) { // Only fetch if not already fetched
        fetchProjects();
    }
};

// Helper: Returns Tailwind CSS classes for project status badges
const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active':
            return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 capitalize';
        case 'pending':
            return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 capitalize';
        case 'on_hold':
            return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 capitalize';
        default:
            return 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 capitalize';
    }
};

// --- Task Section Logic ---

// Fetches ALL task statistics from the API (only once when expanded)
const fetchTaskStatistics = async () => {
    loadingTasks.value = true;
    taskError.value = '';
    try {
        // No 'params: { search: ... }' here as we fetch all and filter client-side
        const response = await axios.get('/api/task-statistics');
        taskStats.value = response.data; // Assign the full response data
    } catch (err) {
        taskError.value = 'Failed to load task statistics';
        console.error('Error fetching task statistics:', err);
    } finally {
        loadingTasks.value = false;
    }
};

// Computed property for client-side task filtering
const filteredTaskProjects = computed(() => {
    if (!taskSearchQuery.value) {
        return taskStats.value.projects; // If no search query, return all task projects
    }
    const lowerCaseQuery = taskSearchQuery.value.toLowerCase();
    return taskStats.value.projects.filter(project =>
        project.name.toLowerCase().includes(lowerCaseQuery)
    );
});


// Toggles the visibility of the tasks section and fetches data if expanding
const toggleTasks = () => {
    expandTasks.value = !expandTasks.value;
    if (expandTasks.value && taskStats.value.projects.length === 0) { // Only fetch if not already fetched
        fetchTaskStatistics();
    }
};

// --- Weekly Availability Logic ---

// Fetches user's weekly availability from the API
const fetchWeeklyAvailability = async () => {
    loadingAvailability.value = true;
    availabilityError.value = '';
    try {
        const now = new Date();
        const startDate = new Date(now);
        startDate.setDate(now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1)); // Set to Monday
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6); // Set to Sunday

        const formatDateForApi = (date) => {
            return date.toISOString().split('T')[0];
        };

        const response = await axios.get('/api/availabilities', {
            params: {
                user_id: authUser.value.id,
                start_date: formatDateForApi(startDate),
                end_date: formatDateForApi(endDate)
            }
        });

        weeklyAvailability.value = response.data;
    } catch (err) {
        availabilityError.value = 'Failed to load availability data';
        console.error('Error fetching weekly availability:', err);
    } finally {
        loadingAvailability.value = false;
    }
};

// Helper: Get day name (e.g., 'Mon') for a given day index (1-7 for Mon-Sun)
const getDayName = (dayIndex) => {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return days[dayIndex - 1];
};

// Helper: Get formatted date for a given day index (e.g., 'Jul 29')
const getDayDate = (dayIndex) => {
    const date = new Date(weeklyAvailability.value.start_date);
    date.setDate(date.getDate() + dayIndex - 1);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Helper: Determines the availability status and relevant data for a given day index
const getAvailabilityStatus = (dayIndex) => {
    const date = new Date(weeklyAvailability.value.start_date);
    date.setDate(date.getDate() + dayIndex - 1);
    const currentDateString = date.toISOString().split('T')[0];

    const dayAvailabilities = weeklyAvailability.value.availabilities.filter(
        a => a.date.split('T')[0] === currentDateString
    );

    if (dayAvailabilities.length === 0) {
        return { status: 'not-set' };
    }

    const isUnavailable = dayAvailabilities.some(a => !a.is_available);
    if (isUnavailable) {
        const unavailableEntry = dayAvailabilities.find(a => !a.is_available);
        return { status: 'unavailable', reason: unavailableEntry?.reason || 'Not specified' };
    }

    const allTimeSlots = [];
    dayAvailabilities.forEach(availability => {
        if (availability.is_available && availability.time_slots && Array.isArray(availability.time_slots)) {
            availability.time_slots.forEach(slot => {
                allTimeSlots.push(`${slot.start_time} - ${slot.end_time}`);
            });
        }
    });

    return { status: 'available', slots: allTimeSlots.length > 0 ? allTimeSlots : ['All Day'] };
};

// Helper: Returns Tailwind CSS classes for the availability day card based on its status
const getDayAvailabilityClass = (dayIndex) => {
    const statusInfo = getAvailabilityStatus(dayIndex);
    let classes = '';

    if (statusInfo.status === 'available') {
        classes = 'border-green-300 bg-green-50/50';
    } else if (statusInfo.status === 'unavailable') {
        classes = 'border-red-300 bg-red-50/50';
    } else {
        classes = 'border-gray-200 bg-gray-50'; // Not set
    }

    const today = new Date();
    const currentDayDate = new Date(weeklyAvailability.value.start_date);
    currentDayDate.setDate(currentDayDate.getDate() + dayIndex - 1);

    if (today.toDateString() === currentDayDate.toDateString()) {
        classes += ' ring-2 ring-indigo-500 ring-offset-2';
    }

    return classes;
};

// --- Notes Modal Logic ---

const openNotesModal = (projectId) => {
    selectedProjectId.value = projectId;
    showNotesModal.value = true;
};

const handleNoteAdded = () => {
    fetchProjects(); // Re-fetch projects if a note is added, in case it changes a project's state
};

// --- Standup Modal Logic ---

const openStandupModal = (projectId) => {
    selectedProjectIdForStandup.value = projectId;
    showStandupModal.value = true;
};

const handleStandupAdded = () => {
    showStandupModal.value = false;
    // Optionally refresh relevant data after standup is added
    // fetchProjects();
    // fetchTaskStatistics();
};

// --- Assigned Tasks Logic ---

// Fetches all tasks assigned to the current user
const fetchAssignedTasks = async () => {
    loadingAssignedTasks.value = true;
    assignedTasksError.value = '';
    try {
        const response = await axios.get('/api/assigned-tasks');
        assignedTasks.value = response.data;
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

// Toggles the visibility of the assigned tasks section and fetches data if expanding
const toggleAssignedTasks = () => {
    expandAssignedTasks.value = !expandAssignedTasks.value;
    if (expandAssignedTasks.value && assignedTasks.value.length === 0) { // Only fetch if not already fetched
        fetchAssignedTasks();
    }
};

// Opens the task detail sidebar for a specific task
const viewTaskDetails = (taskId, projectId) => {
    openTaskDetailSidebar(taskId, projectId);
};

// Reference to the assigned tasks section for scrolling
const assignedTasksRef = ref(null);

// Filter for assigned tasks - can be 'all', 'due-overdue'
const assignedTasksFilter = ref('all');

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

// Handle view button click from notification prompt
const handleViewDueAndOverdueTasks = () => {
    // Expand the assigned tasks section if not already expanded
    if (!expandAssignedTasks.value) {
        expandAssignedTasks.value = true;
        // Need to wait for the DOM to update before scrolling
        setTimeout(() => {
            // Set filter to show only due and overdue tasks
            assignedTasksFilter.value = 'due-overdue';
            // Scroll to the assigned tasks section
            if (assignedTasksRef.value) {
                assignedTasksRef.value.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    } else {
        // Set filter to show only due and overdue tasks
        assignedTasksFilter.value = 'due-overdue';
        // Scroll to the assigned tasks section
        if (assignedTasksRef.value) {
            assignedTasksRef.value.scrollIntoView({ behavior: 'smooth' });
        }
    }
};

// --- Lifecycle Hook ---
onMounted(() => {
    // Initial fetch for summary stats (these are always displayed or used for total counts)
    fetchTaskStatistics(); // To get total_due_tasks count initially
    fetchWeeklyAvailability();
    // Fetch assigned tasks count for initial display
    fetchAssignedTasks();
    // Projects are only fetched when the user expands the section for the first time
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Task Notification Prompt -->
                <TaskNotificationPrompt
                    :overdue-tasks="assignedTasks.filter(task => task.due_date && new Date(task.due_date) < new Date()).length"
                    :due-today-tasks="assignedTasks.filter(task => task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()).length"
                    @view-tasks="handleViewDueAndOverdueTasks"
                />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Welcome Card / Quick Stats -->
                    <div class="md:col-span-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow-xl p-8 text-white flex flex-col sm:flex-row items-center justify-between transition-all duration-300 hover:shadow-2xl">
                        <div>
                            <h3 class="text-3xl font-bold mb-2">Hello, {{ authUser.name }}!</h3>
                            <p class="text-md opacity-90">Here's a quick overview of your activities and progress.</p>
                        </div>
                        <div class="mt-6 sm:mt-0 sm:ml-8 text-center sm:text-right">
                            <p class="text-5xl font-extrabold">{{ props.projectCount }}</p>
                            <p class="text-sm opacity-90">Total Projects</p>
                        </div>
                    </div>

                    <!-- Total Due Tasks Card -->
                    <div class="bg-white rounded-lg shadow-xl p-8 flex flex-col justify-between transition-all duration-300 hover:shadow-2xl">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Pending Tasks</h3>
                        <div class="flex items-center justify-between">
                            <p class="text-5xl font-extrabold text-indigo-600">{{ taskStats.total_due_tasks }}</p>
                            <svg class="w-12 h-12 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                        <p class="text-sm text-gray-600 mt-4">Tasks currently due across all your projects.</p>
                    </div>

                    <!-- Availability Prompt (Conditionally displayed, spans full width) -->
                    <div class="md:col-span-3">
                        <AvailabilityPrompt />
                    </div>

                    <!-- Weekly Availability Card -->
                    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Your Weekly Availability</h3>
                        <p class="mb-6 text-md text-gray-600">
                            Availability for:
                            <span class="font-semibold text-indigo-700">
                                {{ weeklyAvailability.start_date ? formatDateDisplay(weeklyAvailability.start_date) : '' }}
                                to
                                {{ weeklyAvailability.end_date ? formatDateDisplay(weeklyAvailability.end_date) : '' }}
                            </span>
                        </p>

                        <div v-if="loadingAvailability" class="text-center text-sm text-gray-500 py-6">Loading availability data...</div>
                        <div v-else-if="availabilityError" class="text-center text-sm text-red-500 py-6">{{ availabilityError }}</div>
                        <div v-else-if="!weeklyAvailability.availabilities || weeklyAvailability.availabilities.length === 0"
                             class="text-center text-sm text-gray-500 py-6">
                            No availability data found for this week.
                        </div>
                        <div v-else class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-4 mt-4">
                            <!-- Day blocks for Availability -->
                            <div v-for="i in 7" :key="'availability-day-' + i"
                                 class="flex flex-col items-center justify-start p-4 border rounded-lg shadow-sm h-36 overflow-hidden text-clip relative group"
                                 :class="getDayAvailabilityClass(i)">
                                <span class="text-sm font-bold text-gray-800">{{ getDayName(i) }}</span>
                                <span class="text-xs text-gray-600 mt-0.5">{{ getDayDate(i) }}</span>
                                <div class="mt-3 text-center text-xs w-full">
                                    <template v-if="getAvailabilityStatus(i).status === 'available'">
                                        <p class="text-green-700 font-bold">Available</p>
                                        <p v-if="getAvailabilityStatus(i).slots.length > 0" class="text-gray-600 leading-tight mt-1">
                                            {{ getAvailabilityStatus(i).slots.join(', ') }}
                                        </p>
                                    </template>
                                    <template v-else-if="getAvailabilityStatus(i).status === 'unavailable'">
                                        <p class="text-red-700 font-bold">Unavailable</p>
                                        <p v-if="getAvailabilityStatus(i).reason" class="text-gray-600 leading-tight mt-1 break-words">
                                            Reason: {{ getAvailabilityStatus(i).reason }}
                                        </p>
                                    </template>
                                    <template v-else>
                                        <p class="text-gray-500 font-medium">Not Set</p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned Tasks Card -->
                    <div ref="assignedTasksRef" class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">Your Assigned Tasks</h3>
                                <div class="flex space-x-4 mt-2">
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-100 text-red-800 font-medium text-xs mr-2">
                                            {{ assignedTasks.filter(task => task.due_date && new Date(task.due_date) < new Date()).length }}
                                        </span>
                                        <span class="text-sm text-gray-600">Overdue</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-yellow-100 text-yellow-800 font-medium text-xs mr-2">
                                            {{ assignedTasks.filter(task => task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()).length }}
                                        </span>
                                        <span class="text-sm text-gray-600">Due Today</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-100 text-green-800 font-medium text-xs mr-2">
                                            {{ assignedTasks.filter(task => task.status === 'In Progress').length }}
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
                            <div class="mb-6">
                                <TextInput
                                    v-model="assignedTasksSearchQuery"
                                    placeholder="Search tasks by name, milestone, or project..."
                                    class="w-full"
                                    :disabled="loadingAssignedTasks"
                                />
                            </div>

                            <div v-if="assignedTasksError" class="text-center text-sm text-red-500 py-6">{{ assignedTasksError }}</div>
                            <div v-else-if="filteredAssignedTasksWithFilter.length === 0 && !loadingAssignedTasks" class="text-center text-sm text-gray-500 py-6">
                                {{ assignedTasksFilter === 'due-overdue' ? 'No due or overdue tasks found.' : 'No assigned tasks found matching your search.' }}
                            </div>
                            <div v-else class="mt-3 overflow-x-auto rounded-lg border border-gray-200 shadow-sm relative">
                                <!-- Loading overlay for assigned tasks -->
                                <div v-if="loadingAssignedTasks" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-10">
                                    <svg class="animate-spin h-8 w-8 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="ml-3 text-purple-700">Loading assigned tasks...</span>
                                </div>

                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Milestone</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="task in filteredAssignedTasksWithFilter" :key="task.id"
                                        class="hover:bg-gray-50 transition-colors duration-100"
                                        :class="{
                                            'bg-red-50': task.due_date && new Date(task.due_date) < new Date(),
                                            'bg-yellow-50': task.due_date && new Date(task.due_date).toDateString() === new Date().toDateString()
                                        }">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ task.name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ task.milestone?.name || 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ task.project?.name || 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ task.due_date ? formatDateDisplay(task.due_date) : 'No due date' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <PrimaryButton
                                                    @click="viewTaskDetails(task.id, task.project_id)"
                                                    class="px-3 py-1 text-xs leading-4 bg-purple-600 hover:bg-purple-700"
                                                >
                                                    View
                                                </PrimaryButton>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Projects Card -->
                    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2 sm:mb-0">Your Projects</h3>
                            <button
                                @click="toggleProjects"
                                class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition ease-in-out duration-150 w-48"
                            >
                                {{ expandProjects ? 'Collapse Projects' : 'View My Projects (' + projectCount + ')' }}
                            </button>
                        </div>

                        <div v-if="expandProjects" class="mt-4">
                            <div class="mb-6">
                                <TextInput
                                    v-model="projectSearchQuery"
                                    placeholder="Search projects by name, role, or status..."
                                    class="w-full"
                                    :disabled="loadingProjects"
                                />
                            </div>

                            <div v-if="projectsError" class="text-center text-sm text-red-500 py-6">{{ projectsError }}</div>
                            <div v-else-if="filteredProjects.length === 0 && !loadingProjects" class="text-center text-sm text-gray-500 py-6">No projects found matching your search.</div>
                            <div v-else class="mt-3 overflow-x-auto rounded-lg border border-gray-200 shadow-sm relative">
                                <!-- Loading overlay for projects -->
                                <div v-if="loadingProjects" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-10">
                                    <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="ml-3 text-indigo-700">Loading projects...</span>
                                </div>

                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project Name</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Your Role</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="project in filteredProjects" :key="project.id" class="hover:bg-gray-50 transition-colors duration-100">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ project.name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                            {{ project.user_role || 'N/A' }} <!-- Display project.user_role if available, else 'N/A' -->
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span :class="getStatusBadgeClass(project.status)">
                                                    {{ project.status.replace('_', ' ') }}
                                                </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <Link :href="`/projects/${project.id}`" class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring active:bg-indigo-700 transition ease-in-out duration-150">
                                                    View
                                                </Link>
                                                <PrimaryButton @click="openNotesModal(project.id)" class="px-3 py-1 text-xs leading-4 bg-purple-600 hover:bg-purple-700">
                                                    Notes
                                                </PrimaryButton>
                                                <PrimaryButton @click="openStandupModal(project.id)" class="px-3 py-1 text-xs leading-4 bg-green-600 hover:bg-green-700">
                                                    Standup
                                                </PrimaryButton>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Due Tasks Breakdown Card -->
                    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2 sm:mb-0">Due Tasks Breakdown</h3>
                            <button
                                @click="toggleTasks"
                                class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition ease-in-out duration-150 w-48"
                            >
                                {{ expandTasks ? 'Collapse Tasks' : 'My Task Summary' }}
                            </button>
                        </div>

                        <div v-if="expandTasks" class="mt-4">
                            <div class="mb-6">
                                <TextInput
                                    v-model="taskSearchQuery"
                                    placeholder="Search tasks by project name..."
                                    class="w-full"
                                    :disabled="loadingTasks"
                                />
                            </div>

                            <div v-if="taskError" class="text-center text-sm text-red-500 py-6">{{ taskError }}</div>
                            <div v-else-if="filteredTaskProjects.length === 0 && !loadingTasks" class="text-center text-sm text-gray-500 py-6">No due tasks found matching your search.</div>
                            <div v-else class="mt-3 overflow-x-auto rounded-lg border border-gray-200 shadow-sm relative">
                                <!-- Loading overlay for tasks -->
                                <div v-if="loadingTasks" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-10">
                                    <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="ml-3 text-blue-700">Loading tasks...</span>
                                </div>

                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project Name</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Due</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Today</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To Me</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="project in filteredTaskProjects" :key="project.id" class="hover:bg-gray-50 transition-colors duration-100">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ project.name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ project.due_tasks }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ project.due_today }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">{{ project.assigned_to_me }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <div class="flex space-x-2">
                                                <Link :href="`/projects/${project.id}`" class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring active:bg-blue-700 transition ease-in-out duration-150">
                                                    View Project
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Notice Board Card -->
                    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">Notice Board</h3>
                        <p class="text-md text-gray-600">
                            No new announcements at this time. Check back later for important updates!
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes Modal -->
        <NotesModal
            :show="showNotesModal"
            :project-id="selectedProjectId"
            @close="showNotesModal = false"
            @note-added="handleNoteAdded"
        />

        <!-- Standup Modal -->
        <StandupModal
            :show="showStandupModal"
            :project-id="selectedProjectIdForStandup"
            @close="showStandupModal = false"
            @standup-added="handleStandupAdded"
        />
    </AuthenticatedLayout>
</template>
