<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProjectForm from '@/Components/ProjectForm.vue'; // The main form component
import { useGlobalPermissions } from '@/Directives/permissions'; // Import useGlobalPermissions
import { fetchProjectSectionData } from '@/Components/ProjectForm/useProjectData'; // Import the data fetching utility

// Fetch global permissions (used by ProjectForm internally for permission checks)
const { permissions: globalPermissions, loading: globalPermissionsLoading } = useGlobalPermissions();

// Define props passed from the Inertia controller
const props = defineProps({
    // project prop now only needs to contain the ID for the initial fetch
    project: {
        type: Object, // Can be { id: number } or empty {} for new projects
        default: () => ({}),
    },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Inertia errors
});

// Reactive state for the project data that ProjectForm will use
const currentProjectData = ref({}); // This will hold the full project object fetched via API
const isLoadingPage = ref(true); // Overall loading state for the page
const generalError = ref(''); // General error message for the page

// Watch for changes in the incoming 'project' prop (primarily for initial load of ID)
// and trigger the full project data fetch
watch(() => props.project, async (newProject) => {
    if (newProject && newProject.id) {
        isLoadingPage.value = true;
        generalError.value = ''; // Clear previous errors
        try {
            // Fetch the full basic project data using the API
            const data = await fetchProjectSectionData(newProject.id, 'basic', {}); // Permissions not strictly needed for basic fetch here
            if (data) {
                currentProjectData.value = data;
            } else {
                generalError.value = 'Failed to load project details. Project data is empty.';
            }
        } catch (err) {
            console.error('Error fetching project details for Edit page:', err);
            generalError.value = err.response?.data?.message || 'Failed to load project details. Please try again.';
        } finally {
            isLoadingPage.value = false;
        }
    } else {
        // If no project ID is provided (e.g., if this component was somehow loaded without an ID),
        // ensure loading is false and currentProjectData is empty.
        isLoadingPage.value = false;
        currentProjectData.value = {};
    }
}, { immediate: true, deep: true });


// Handle closing the form (e.g., navigate back to projects index)
const handleCloseForm = () => {
    router.visit(route('projects.index')); // Assuming you have a projects.index route
};

// On mount, ensure initial fetch is triggered if project ID is available
onMounted(() => {
    // The watch handler with { immediate: true } will handle the initial fetch
    // when props.project is first available.
});
</script>

<template>
    <Head :title="`Edit Project: ${currentProjectData.name || 'Loading...'}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Project: <span class="text-indigo-600">{{ currentProjectData.name || 'N/A' }}</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- General Error Display for the entire page -->
                    <div v-if="generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
                        <span class="block sm:inline">{{ generalError }}</span>
                    </div>

                    <!-- Loading Indicator for the entire ProjectForm -->
                    <div v-if="isLoadingPage" class="text-center py-8 text-gray-500 text-lg">
                        <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading project details...
                    </div>

                    <ProjectForm
                        v-else
                        :project="currentProjectData"
                        :statusOptions="statusOptions"
                        :departmentOptions="departmentOptions"
                        :sourceOptions="sourceOptions"
                        :clientRoleOptions="clientRoleOptions"
                        :userRoleOptions="userRoleOptions"
                        :paymentTypeOptions="paymentTypeOptions"
                        :errors="props.errors"
                        :isSaving="isLoadingPage"
                        @close="handleCloseForm"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

