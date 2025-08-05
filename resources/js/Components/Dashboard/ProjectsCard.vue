<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

// Props
const props = defineProps({
    projectCount: Number,
});

// Emits
const emit = defineEmits(['open-notes-modal', 'open-standup-modal']);

// Reactive state for Projects section
const projects = ref([]);
const loadingProjects = ref(true);
const projectsError = ref('');
const expandProjects = ref(false);
const projectSearchQuery = ref('');

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

// Handler for opening notes modal
const openNotesModal = (projectId) => {
    emit('open-notes-modal', projectId);
};

// Handler for opening standup modal
const openStandupModal = (projectId) => {
    emit('open-standup-modal', projectId);
};
</script>

<template>
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
</template>
