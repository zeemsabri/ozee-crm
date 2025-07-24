<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import NotesModal from '@/Components/NotesModal.vue';

// Props
const props = defineProps({
    projectCount: Number,
});

// Reactive state
const projects = ref([]);
const loading = ref(true);
const error = ref('');
const expandProjects = ref(false);

// Task statistics state
const taskStats = ref({
    total_due_tasks: 0,
    projects: []
});
const loadingTasks = ref(false);
const taskError = ref('');
const expandTasks = ref(false);

// Notes modal state
const showNotesModal = ref(false);
const selectedProjectId = ref(null);

// Fetch projects
const fetchProjects = async () => {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.get('/api/projects-simplified');
        projects.value = response.data;
    } catch (err) {
        error.value = 'Failed to load projects';
        console.error('Error fetching projects:', err);
    } finally {
        loading.value = false;
    }
};

// Fetch task statistics
const fetchTaskStatistics = async () => {
    loadingTasks.value = true;
    taskError.value = '';
    try {
        const response = await axios.get('/api/task-statistics');
        taskStats.value = response.data;
    } catch (err) {
        taskError.value = 'Failed to load task statistics';
        console.error('Error fetching task statistics:', err);
    } finally {
        loadingTasks.value = false;
    }
};

// Toggle projects section
const toggleProjects = () => {
    expandProjects.value = !expandProjects.value;
    if (expandProjects.value && projects.value.length === 0) {
        fetchProjects();
    }
};

// Toggle tasks section
const toggleTasks = () => {
    expandTasks.value = !expandTasks.value;
    if (expandTasks.value && taskStats.value.projects.length === 0) {
        fetchTaskStatistics();
    }
};

// Open notes modal
const openNotesModal = (projectId) => {
    selectedProjectId.value = projectId;
    showNotesModal.value = true;
};

// Handle note added
const handleNoteAdded = () => {
    // Refresh projects list
    fetchProjects();
};

onMounted(() => {
    // We don't fetch projects initially, only when the user expands the section

    // Fetch task statistics to display the total count
    fetchTaskStatistics();
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                <!-- Projects Card -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Projects</h3>
                            <button
                                @click="toggleProjects"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                {{ expandProjects ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">
                            You have access to <span class="font-bold">{{ projectCount }}</span> project(s).
                        </p>

                        <!-- Expandable Projects List -->
                        <div v-if="expandProjects" class="mt-4">
                            <div v-if="loading" class="text-sm text-gray-500">Loading projects...</div>
                            <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
                            <div v-else-if="projects.length === 0" class="text-sm text-gray-500">No projects found.</div>
                            <div v-else class="mt-3">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="project in projects" :key="project.id">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">{{ project.name }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm capitalize">{{ project.status.replace('_', ' ') }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                <div class="flex space-x-2">
                                                    <Link :href="`/projects/${project.id}`" class="text-xs bg-blue-500 hover:bg-blue-700 text-white py-1 px-2 rounded">
                                                        View
                                                    </Link>
<!--                                                    <Link :href="`/emails/compose?project_id=${project.id}`" class="text-xs bg-green-500 hover:bg-green-700 text-white py-1 px-2 rounded">-->
<!--                                                        Email-->
<!--                                                    </Link>-->
                                                    <button @click="openNotesModal(project.id)" class="text-xs bg-purple-500 hover:bg-purple-700 text-white py-1 px-2 rounded">
                                                        Notes
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unread Email Card -->
<!--                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">-->
<!--                    <div class="p-6 text-gray-900">-->
<!--                        <h3 class="text-lg font-medium text-gray-900">Unread Email</h3>-->
<!--                        <p class="mt-1 text-sm text-gray-600">-->
<!--                            You have 0 unread emails.-->
<!--                        </p>-->
<!--                    </div>-->
<!--                </div>-->

                <!-- Due Tasks Card -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">Due Tasks</h3>
                            <button
                                @click="toggleTasks"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                {{ expandTasks ? 'Collapse' : 'Expand' }}
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">
                            You have <span class="font-bold">{{ taskStats.total_due_tasks }}</span> task(s) due.
                        </p>

                        <!-- Expandable Tasks List -->
                        <div v-if="expandTasks" class="mt-4">
                            <div v-if="loadingTasks" class="text-sm text-gray-500">Loading tasks...</div>
                            <div v-else-if="taskError" class="text-sm text-red-500">{{ taskError }}</div>
                            <div v-else-if="taskStats.projects.length === 0" class="text-sm text-gray-500">No due tasks found.</div>
                            <div v-else class="mt-3">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project Name</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Tasks</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Today</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Me</th>
                                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="project in taskStats.projects" :key="project.id">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">{{ project.name }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">{{ project.due_tasks }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">{{ project.due_today }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">{{ project.assigned_to_me }}</td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                <div class="flex space-x-2">
                                                    <Link :href="`/projects/${project.id}`" class="text-xs bg-blue-500 hover:bg-blue-700 text-white py-1 px-2 rounded">
                                                        View
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notice Board Card -->
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-medium text-gray-900">Notice Board</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            No new announcements at this time.
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
    </AuthenticatedLayout>
</template>
