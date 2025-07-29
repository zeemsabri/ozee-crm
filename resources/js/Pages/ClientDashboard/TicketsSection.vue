<script setup>
import { ref, onMounted, inject } from 'vue';
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
    // The 'tickets' prop from parent is no longer strictly needed as tasks are fetched internally
});

const emits = defineEmits(['add-activity']); // Emit for general dashboard activity log

const isLoading = ref(true);
const tasks = ref([]); // Reactive state for tasks/tickets
const apiError = ref(null); // To store any API errors

// State for the Task Notes Sidebar
const selectedTaskForNotes = ref(null);
const showNotesSidebar = ref(false);

// State for the Create Task Modal
const showCreateTaskModal = ref(false);

// Inject the showModal service from the parent (ClientDashboard.vue)
const { showModal } = inject('modalService');

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
    // This provides immediate UI update without a full re-fetch if not strictly necessary
    tasks.value.unshift(newTask);
    // Optionally sort if you have a specific order, e.g., by creation date or due date
    // tasks.value.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    emits('add-activity', `New task "${newTask.title}" created.`); // Log activity
};

// Initial data load when the component is mounted
onMounted(() => {
    fetchTasks();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)] relative">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Tickets</h2>
            <button @click="openCreateTaskModal"
                    class="bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 transition-colors shadow-md"
            >
                Add New Ticket
            </button>
        </div>

        <!-- Conditional rendering based on loading, errors, or empty state -->
        <div v-if="isLoading" class="text-center text-gray-600 py-8">Loading tasks...</div>
        <div v-else-if="apiError" class="text-center text-red-600 py-8">{{ apiError }}</div>
        <div v-else-if="tasks.length === 0" class="text-center text-gray-500 py-8">No tasks found for this project.</div>
        <div v-else class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">Title</th>
                    <th class="py-3 px-6 text-left">Description</th>
                    <th class="py-3 px-6 text-left">Status</th>
                    <th class="py-3 px-6 text-left">Due Date</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700 text-sm font-light">
                <tr v-for="task in tasks" :key="task.id" class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-6 text-left whitespace-nowrap">{{ task.name }}</td>
                    <td class="py-3 px-6 text-left">{{ task.description }}</td>
                    <td class="py-3 px-6 text-left">
                            <span :class="{
                                'px-3 py-1 rounded-full text-xs font-semibold': true,
                                'bg-green-200 text-green-800': task.status === 'completed',
                                'bg-yellow-200 text-yellow-800': task.status === 'pending',
                                'bg-blue-200 text-blue-800': task.status === 'in_progress',
                                'bg-red-200 text-red-800': task.status === 'blocked',
                            }">
                                {{ task.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()) }}
                            </span>
                    </td>
                    <td class="py-3 px-6 text-left">{{ task.due_date ? new Date(task.due_date).toLocaleDateString() : 'N/A' }}</td>
                    <td class="py-3 px-6 text-center">
                        <div class="flex item-center justify-center">
                            <button @click="openNotesSidebar(task)"
                                    class="w-8 h-8 rounded-full bg-blue-500 hover:bg-blue-600 text-white flex items-center justify-center text-xs font-bold transition-colors shadow-md mr-2"
                                    title="View Notes"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2v2h8V6H6zm0 4v2h4v-2H6zm0 4v2h4v-2H6zm5-4v6h3v-6h-3z"/>
                                </svg>
                            </button>
                            <!-- Potentially add other actions here like 'Mark Complete' if allowed for client -->
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
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
/* No specific styles needed for this component, Tailwind handles most */
</style>
