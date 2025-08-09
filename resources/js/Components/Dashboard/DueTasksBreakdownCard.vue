<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';

// Reactive state for Task statistics section
const taskStats = ref({
    total_due_tasks: 0,
    projects: [] // Stores ALL fetched task projects
});
const loadingTasks = ref(true);
const taskError = ref('');
const expandTasks = ref(false);
const taskSearchQuery = ref('');

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

// Toggles the visibility of the tasks section and fetches data if expanding
const toggleTasks = () => {
    expandTasks.value = !expandTasks.value;
    if (expandTasks.value && taskStats.value.projects.length === 0) { // Only fetch if not already fetched
        fetchTaskStatistics();
    }
};

// Fetch data on component mount
onMounted(() => {
    fetchTaskStatistics(); // To get total_due_tasks count initially
});
</script>

<template>
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
</template>
