<script setup>
import { ref, onMounted, inject, computed } from 'vue';
import TicketNotesSidebar from './TicketNotesSidebar.vue'; // Import the notes sidebar
import CreateTaskModal from './CreateTaskModal.vue'; // Import the new task creation modal

const props = defineProps({
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
});

const emits = defineEmits(['add-activity']); // Emit for general dashboard activity log

const isLoading = ref(true);
const tasks = ref([]); // Reactive state for tasks/tickets
const apiError = ref(null); // To store any API errors
const taskSearchQuery = ref(''); // New: Search query for tasks

// State for the Task Notes Sidebar
const selectedTaskForNotes = ref(null);
const showNotesSidebar = ref(false);

// State for the Create Task Modal
const showCreateTaskModal = ref(false);

// Inject the showModal service from the parent (ClientDashboard.vue)
const { showModal } = inject('modalService');

// --- Computed properties for task statistics ---
const totalTasksCount = computed(() => tasks.value.length);
const completedTasksCount = computed(() => tasks.value.filter(task => task.status.toLowerCase() === 'completed').length);
const pendingTasksCount = computed(() => tasks.value.filter(task => ['to do', 'in progress'].includes(task.status.toLowerCase())).length);
const overdueTasksCount = computed(() => {
    const now = new Date();
    return tasks.value.filter(task =>
        task.due_date && new Date(task.due_date) < now && task.status.toLowerCase() !== 'completed'
    ).length;
});

// Computed property for filtered tasks
const filteredTasks = computed(() => {
    if (!taskSearchQuery.value) {
        return tasks.value;
    }
    const query = taskSearchQuery.value.toLowerCase();
    return tasks.value.filter(task =>
        task.name.toLowerCase().includes(query) ||
        (task.description && task.description.toLowerCase().includes(query)) ||
        task.status.toLowerCase().includes(query)
    );
});

// Function to fetch tasks from the API
const fetchTasks = async () => {
    isLoading.value = true;
    apiError.value = null; // Clear previous API errors
    try {
        const response = await fetch(`/api/client-api/project/${props.projectId}/tasks`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to fetch tasks.');
            throw new Error(errorMessage);
        }

        tasks.value = data; // Update tasks with fetched data
    } catch (err) {
        console.error("Error fetching tasks:", err);
        apiError.value = err.message || 'An unexpected error occurred while fetching tasks.';
        showModal('Error', apiError.value, 'alert'); // Display error using the shared modal service
    } finally {
        isLoading.value = false;
    }
};

// Method to open the notes sidebar for a specific task
const openNotesSidebar = (task) => {
    selectedTaskForNotes.value = task;
    showNotesSidebar.value = true;
};

// Method to handle a new note being added (triggers a re-fetch of tasks to get updated notes)
const handleNoteAdded = () => {
    fetchTasks(); // Re-fetch all tasks to ensure the latest notes are displayed
    emits('add-activity', 'A new note was added to a task.'); // Log activity to dashboard
};

// Method to open the Create Task Modal
const openCreateTaskModal = () => {
    showCreateTaskModal.value = true;
};

// Method to handle successful task creation from the modal
const handleTaskCreated = (newTask) => {
    // Add the newly created task to the local tasks list
    tasks.value.unshift(newTask);
    emits('add-activity', `New task "${newTask.name}" created.`); // Log activity
};

// Helper to format due date
const formatDueDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Helper to get status class
const getStatusClass = (status) => {
    switch (status.toLowerCase()) {
        case 'completed': return 'bg-green-100 text-green-800';
        case 'to do': return 'bg-yellow-100 text-yellow-800';
        case 'in progress': return 'bg-blue-100 text-blue-800';
        case 'blocked': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

// Initial data load when the component is mounted
onMounted(() => {
    fetchTasks();
});
</script>

<template>
    <div class="p-6 bg-white rounded-xl shadow-lg font-inter min-h-[calc(100vh-6rem)]">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Your Tasks</h2>
            <button @click="openCreateTaskModal"
                    class="bg-indigo-600 text-white py-2 px-5 rounded-lg font-semibold hover:bg-indigo-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus mr-2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                Add New Task
            </button>
        </div>

        <!-- Task Statistics Section -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-500 text-white p-4 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <div class="text-3xl font-bold">{{ totalTasksCount }}</div>
                    <div class="text-sm">Total Tasks</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-todo"><rect x="3" y="5" width="6" height="6" rx="1"/><path d="m3 17h.01"/><path d="M13 5h8"/><path d="M13 9h8"/><path d="M13 13h8"/><path d="M13 17h8"/><path d="m3 13h.01"/></svg>
            </div>
            <div class="bg-green-500 text-white p-4 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <div class="text-3xl font-bold">{{ completedTasksCount }}</div>
                    <div class="text-sm">Completed</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check-circle-2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <div class="bg-yellow-500 text-white p-4 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <div class="text-3xl font-bold">{{ pendingTasksCount }}</div>
                    <div class="text-sm">Pending</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-hourglass"><path d="M6 2v6a6 6 0 0 0 6 6 6 6 0 0 0 6-6V2"/><path d="M6 22v-6a6 6 0 0 1 6-6 6 6 0 0 1 6 6v6"/></svg>
            </div>
            <div class="bg-red-500 text-white p-4 rounded-lg shadow-md flex items-center justify-between">
                <div>
                    <div class="text-3xl font-bold">{{ overdueTasksCount }}</div>
                    <div class="text-sm">Overdue</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-alert-triangle"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </div>
        </div>

        <!-- Task Search Bar -->
        <div class="relative mb-6">
            <input
                type="text"
                v-model="taskSearchQuery"
                placeholder="Search tasks by title, description, or status..."
                class="w-full p-3 pl-10 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
                aria-label="Search Tasks"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-gray-400 w-5 h-5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
        </div>

        <!-- Conditional rendering based on loading, errors, or empty state -->
        <div v-if="isLoading" class="text-center text-gray-600 py-12">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Loading tasks...</p>
        </div>
        <div v-else-if="apiError" class="text-center text-red-600 py-12">
            <p class="font-semibold mb-2">Error loading tasks:</p>
            <p>{{ apiError }}</p>
        </div>
        <div v-else-if="tasks.length === 0" class="text-center text-gray-500 py-12">
            <p class="text-lg mb-2">No tasks found for this project.</p>
            <p>Click "Add New Task" to get started!</p>
        </div>
        <div v-else-if="filteredTasks.length === 0 && taskSearchQuery" class="text-center text-gray-500 py-12">
            <p class="text-lg mb-2">No tasks match your search "{{ taskSearchQuery }}".</p>
            <p>Try a different keyword or clear your search.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="task in filteredTasks" :key="task.id" class="bg-gray-50 rounded-lg shadow-sm p-5 border border-gray-200 flex flex-col justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ task.name }}</h3>
                    <p class="text-sm text-gray-700 mb-3 line-clamp-2">{{ task.description || 'No description provided.' }}</p>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600 mb-4">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar mr-1 text-gray-500"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                        <span>Due: {{ formatDueDate(task.due_date) }}</span>
                    </div>
                    <span :class="['px-3 py-1 rounded-full text-xs font-bold capitalize', getStatusClass(task.status)]">
                        {{ task.status.replace(/_/g, ' ') }}
                    </span>
                </div>
                <div class="mt-auto">
                    <button @click="openNotesSidebar(task)"
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center justify-center"
                            title="View Notes"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-notebook-text mr-2"><path d="M2 6h4"/><path d="M2 10h4"/><path d="M2 14h4"/><path d="M2 18h4"/><path d="M7 2h14a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M10 8h6"/><path d="M10 12h6"/><path d="M10 16h6"/></svg>
                        View Notes
                    </button>
                </div>
            </div>
        </div>

        <!-- Task Notes Sidebar Component -->
        <TicketNotesSidebar
            v-model:isOpen="showNotesSidebar"
            note-for="tasks"
            :selected-item="selectedTaskForNotes"
            :initialAuthToken="initialAuthToken"
            :projectId="projectId"
            @note-added-success="handleNoteAdded"
        />

        <!-- Create Task Modal Component -->
        <CreateTaskModal
            v-model:isOpen="showCreateTaskModal"
            :initialAuthToken="initialAuthToken"
            :projectId="projectId"
            @task-created-success="handleTaskCreated"
        />
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
/* Specific styling for search input to place icon inside */
.relative input[type="text"] {
    padding-left: 2.5rem; /* Adjust padding to make space for the icon */
}
</style>
