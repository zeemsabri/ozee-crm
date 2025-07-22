<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive, watch } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { useAuthUser, usePermissions, useGlobalPermissions, fetchGlobalPermissions } from '@/Directives/permissions';

// Use the permission utilities
const authUser = useAuthUser();

// Use global permissions
const { permissions: globalPermissions, loading: permissionsLoading, error: permissionsError } = useGlobalPermissions();

// Set up permission checking functions
const { canDo, canView, canManage } = usePermissions();

// Check if user can compose emails
const canComposeEmails = computed(() => {
    return canDo('compose_emails').value || true; // Default to true for backward compatibility
});

// Reactive state for data
const projects = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');
const successMessage = ref('');

// Email form state
const emailForm = reactive({
    project_id: '',
    client_id: '', // Will be derived from project selection
    subject: '',
    body: '',
});

// Computed properties for UI/Logic
// Projects are already filtered by the backend based on permissions
const assignedProjects = computed(() => {
    // Return all projects from the backend, which are already filtered
    // based on user's permissions (both global and project-specific)
    return projects.value;
});

const selectedProjectClient = computed(() => {
    const project = projects.value.find(p => p.id === emailForm.project_id);
    return project ? project.client : null;
});

// --- Fetch Initial Data ---
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Use the new API endpoint that only returns necessary data for email composer
        const projectsResponse = await window.axios.get('/api/projects-for-email');
        projects.value = projectsResponse.data;

    } catch (error) {
        generalError.value = 'Failed to load projects.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// --- Watch for Project Selection Change ---
watch(() => emailForm.project_id, (newProjectId) => {
    if (newProjectId) {
        const project = projects.value.find(p => p.id === newProjectId);
        if (project && project.client && project.client.id) {
            emailForm.client_id = project.client.id;
        }
    } else {
        emailForm.client_id = '';
    }
});

// --- Submit Email for Approval ---
const submitEmailForApproval = async () => {
    errors.value = {};
    generalError.value = '';
    successMessage.value = '';

    // Frontend validation for project selection
    if (!emailForm.project_id) {
        errors.value.project_id = ['Please select a project.'];
        return;
    }
    if (!emailForm.client_id) {
        generalError.value = 'Selected project does not have an associated client. Please select a different project.';
        return;
    }

    try {
        // Get the client's email from the server
        const clientEmailResponse = await window.axios.get(`/api/clients/${emailForm.client_id}/email`);
        const clientEmail = clientEmailResponse.data.email;

        // Automatically set status to 'pending_approval' for submission
        const payload = {
            ...emailForm,
            status: 'pending_approval',
            client_email: clientEmail // Add the client's email to the payload
        };

        await window.axios.post('/api/emails', payload);

        successMessage.value = 'Email submitted for approval successfully!';
        // Reset form after successful submission
        emailForm.project_id = '';
        emailForm.client_id = '';
        emailForm.subject = '';
        emailForm.body = '';
        errors.value = {}; // Clear any previous errors

    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to submit email. An unexpected error occurred.';
            console.error('Error submitting email:', error);
        }
    }
};

// --- Check for query parameters ---
const checkQueryParams = () => {
    // Get the URL query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');

    // If project_id is in the URL, set it in the form
    if (projectId) {
        emailForm.project_id = projectId;
    }
};

// --- Lifecycle Hook ---
onMounted(async () => {
    // Fetch global permissions first
    try {
        console.log('Fetching global permissions...');
        const permissions = await fetchGlobalPermissions();
        console.log('Global permissions fetched:', permissions);
    } catch (error) {
        console.error('Error fetching global permissions:', error);
    }

    // Proceed with loading data without redirection
    fetchInitialData().then(() => {
        // After data is loaded, check for query parameters
        checkQueryParams();
    });

    // Log permission status after all data is loaded
    console.log('All data loaded, permission status:');
    console.log('- Global permissions:', globalPermissions.value);
    console.log('- Permissions loading:', permissionsLoading.value);
    console.log('- Permissions error:', permissionsError.value);
    console.log('- Can compose emails:', canComposeEmails.value);
});
</script>

<template>
    <Head title="Compose Email" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Compose New Email</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-6">Create New Email for Client</h3>

                        <div v-if="!canComposeEmails" class="text-red-600 mb-4">
                            You do not have permission to compose emails. Please contact your administrator.
                        </div>
                        <div v-else-if="loading" class="text-gray-600 mb-4">Loading data...</div>
                        <div v-else-if="generalError" class="text-red-600 mb-4">{{ generalError }}</div>
                        <div v-else-if="assignedProjects.length === 0" class="text-gray-600 mb-4">
                            No projects assigned to you for email composition.
                            <span v-if="authUser.role === 'contractor'">Please ensure you are assigned to a project.</span>
                            <span v-else>Please ensure there are projects available.</span>
                        </div>
                        <div v-else>
                            <form @submit.prevent="submitEmailForApproval">
                                <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    <span class="block sm:inline">{{ successMessage }}</span>
                                </div>

                                <div class="mb-4">
                                    <InputLabel for="project_id" value="Select Project" />
                                    <select id="project_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="emailForm.project_id" required>
                                        <option value="" disabled>Select a Project</option>
                                        <option v-for="project in assignedProjects" :key="project.id" :value="project.id">
                                            {{ project.name }} (Client: {{ project.client ? project.client.name : 'N/A' }})
                                        </option>
                                    </select>
                                    <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
                                </div>

                                <!-- Client email field is intentionally hidden from UI -->
                                <div class="mb-4">
                                    <InputLabel for="to_client_name" value="To (Client)" />
                                    <TextInput id="to_client_name" type="text" class="mt-1 block w-full bg-gray-100"
                                               :value="selectedProjectClient ? selectedProjectClient.name : ''" readonly />
                                    <InputError v-if="!selectedProjectClient && emailForm.project_id" message="Selected project has no client." class="mt-2" />
                                </div>

                                <div class="mb-4">
                                    <InputLabel for="subject" value="Subject" />
                                    <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="emailForm.subject" required />
                                    <InputError :message="errors.subject ? errors.subject[0] : ''" class="mt-2" />
                                </div>

                                <div class="mb-6">
                                    <InputLabel for="body" value="Email Body" />
                                    <RichTextEditor
                                        id="body"
                                        v-model="emailForm.body"
                                        placeholder="Compose your email here..."
                                        height="300px"
                                    />
                                    <InputError :message="errors.body ? errors.body[0] : ''" class="mt-2" />
                                </div>

                                <div class="flex items-center justify-end">
                                    <PrimaryButton type="submit">Submit for Approval</PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
