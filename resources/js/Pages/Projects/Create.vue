<script setup>
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProjectFormBasicInfo from '@/Components/ProjectForm/ProjectFormBasicInfo.vue';
import { success, error } from '@/Utils/notification';
import { useGlobalPermissions } from '@/Directives/permissions'; // Import useGlobalPermissions

// Fetch global permissions
const { permissions: globalPermissions, loading: globalPermissionsLoading } = useGlobalPermissions();

// Define props passed from the Inertia controller
const props = defineProps({
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true }, // Although not used directly in BasicInfo, kept for consistency
    sourceOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Inertia errors
});

// Reactive state for the project form data
const projectForm = reactive({
    id: null,
    name: '',
    description: '',
    website: '',
    social_media_link: '',
    preferred_keywords: '',
    google_chat_id: '',
    google_drive_link: '',
    logo: null, // Will hold the File object for upload
    status: 'active',
    project_type: '',
    source: '',
    tags: [],
    timezone: null,
});

// Reactive state for general error messages not tied to specific fields
const generalError = ref('');
const isSaving = ref(false); // Local loading state

// Computed property to check if the user can manage projects globally
// This relies on the globalPermissions being loaded
const canManageProjects = ref(true); // Assuming ability to create means can manage for new project
const canManageProjectBasicDetails = ref(true); // Assuming ability to create means can manage basic details

// Function to handle the submission from ProjectFormBasicInfo
const handleBasicInfoSubmit = async (formData, isNewProject, logoFile) => {
    generalError.value = ''; // Clear previous general errors
    isSaving.value = true; // Set saving state to true

    try {
        const dataToSubmit = new FormData();
        for (const key in formData) {
            if (formData[key] !== null && formData[key] !== undefined) {
                if (Array.isArray(formData[key])) {
                    formData[key].forEach((item, index) => {
                        if (typeof item === 'object' && item !== null) {
                            // For objects in arrays (e.g., tags), stringify them
                            dataToSubmit.append(`${key}[${index}]`, JSON.stringify(item));
                        } else {
                            dataToSubmit.append(`${key}[]`, item); // Append for simple arrays
                        }
                    });
                } else {
                    dataToSubmit.append(key, formData[key]);
                }
            }
        }

        if (logoFile) {
            dataToSubmit.append('logo', logoFile);
        }

        const response = await window.axios.post('/api/projects', dataToSubmit, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        success('Project created successfully!');
        const newProjectId = response.data.id; // Adjust based on your API response structure
        router.visit(route('projects.edit', { project: newProjectId })); // Redirect to edit page
    } catch (err) {
        console.error('Project creation failed:', err);
        if (err.response && err.response.data && err.response.data.message) {
            generalError.value = err.response.data.message;
        } else {
            generalError.value = 'An unexpected error occurred during project creation.';
        }
        error('Failed to create project. Please check the form for errors.');
    } finally {
        isSaving.value = false; // Reset saving state
    }
};
</script>

<template>
    <Head title="Create New Project" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create New Project</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- General Error Display (from parent) -->
                    <div v-if="generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
                        <span class="block sm:inline">{{ generalError }}</span>
                    </div>

                    <!-- Pass props and handle the submit event from ProjectFormBasicInfo -->
                    <ProjectFormBasicInfo
                        v-model:projectForm="projectForm"
                        :errors="props.errors"
                        :statusOptions="props.statusOptions"
                        :sourceOptions="props.sourceOptions"
                        :canManageProjects="canManageProjects"
                        :canManageProjectBasicDetails="canManageProjectBasicDetails"
                        :isSaving="isSaving"
                        @submit="handleBasicInfoSubmit"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

