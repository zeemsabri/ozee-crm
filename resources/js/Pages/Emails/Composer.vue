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
import Multiselect from 'vue-multiselect';
import { useAuthUser, usePermissions, useGlobalPermissions, fetchGlobalPermissions } from '@/Directives/permissions';
import 'vue-multiselect/dist/vue-multiselect.css';

// Use the permission utilities
const authUser = useAuthUser();
const { permissions: globalPermissions, loading: permissionsLoading, error: permissionsError } = useGlobalPermissions();
const { canDo } = usePermissions();

// Check if user can compose emails
const canComposeEmails = computed(() => {
    return canDo('compose_emails').value || true;
});

// Reactive state for data
const projects = ref([]);
const clients = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');
const successMessage = ref('');

// Email form state
const emailForm = reactive({
    project_id: '',
    client_ids: [], // Changed to array for multi-select
    subject: '',
    body: '',
});

// Computed properties for UI/Logic
const assignedProjects = computed(() => {
    return projects.value;
});

const selectedClients = computed(() => {
    return clients.value.filter(c => emailForm.client_ids.includes(c.id));
});

// Filter clients based on selected project
const filteredClients = computed(() => {
    if (!emailForm.project_id) {
        return [];
    }

    const selectedProject = projects.value.find(p => p.id === emailForm.project_id);
    if (!selectedProject || !selectedProject.clients) {
        return [];
    }

    const projectClientIds = selectedProject.clients.map(c => c.id);
    return clients.value.filter(client => projectClientIds.includes(client.id));
});

// Fetch initial data
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await window.axios.get('/api/projects-for-email');
        projects.value = response.data.projects;
        clients.value = response.data.clients;
    } catch (error) {
        generalError.value = 'Failed to load projects and clients.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// Watch for project selection change
watch(() => emailForm.project_id, (newProjectId) => {
    // Always clear selected clients when project changes
    emailForm.client_ids = [];
});

// Submit email for approval
const submitEmailForApproval = async () => {
    errors.value = {};
    generalError.value = '';
    successMessage.value = '';

    if (!emailForm.project_id) {
        errors.value.project_id = ['Please select a project.'];
        return;
    }
    if (!emailForm.client_ids || emailForm.client_ids.length === 0) {
        errors.value.client_ids = ['Please select at least one client.'];
        return;
    }

    try {
        // Format client_ids as array of objects with id property
        const formattedClientIds = emailForm.client_ids.map(clientId => {
            // Check if clientId is already an object with an id property
            if (typeof clientId === 'object' && clientId !== null) {
                return { id: clientId.id };
            }
            // Otherwise, assume it's a simple ID value
            return { id: clientId };
        });

        const payload = {
            project_id: emailForm.project_id,
            client_ids: formattedClientIds,
            subject: emailForm.subject,
            body: emailForm.body,
            status: 'pending_approval',
        };

        await window.axios.post('/api/emails', payload);

        successMessage.value = 'Email submitted for approval successfully!';
        emailForm.project_id = '';
        emailForm.client_ids = [];
        emailForm.subject = '';
        emailForm.body = '';
        errors.value = {};

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

// Check for query parameters
const checkQueryParams = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const projectId = urlParams.get('project_id');
    if (projectId) {
        emailForm.project_id = projectId;
    }
};

// Lifecycle hook
onMounted(async () => {
    try {
        console.log('Fetching global permissions...');
        const permissions = await fetchGlobalPermissions();
        console.log('Global permissions fetched:', permissions);
    } catch (error) {
        console.error('Error fetching global permissions:', error);
    }

    fetchInitialData().then(() => {
        checkQueryParams();
    });

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
                                            {{ project.name }} (Clients: {{ project.clients.length ? project.clients.map(c => c.name).join(', ') : 'N/A' }})
                                        </option>
                                    </select>
                                    <InputError :message="errors.project_id ? errors.project_id[0] : ''" class="mt-2" />
                                </div>

                                <div class="mb-4">
                                    <InputLabel for="client_ids" value="To (Clients)" />
                                    <Multiselect
                                        id="client_ids"
                                        v-model="emailForm.client_ids"
                                        :options="filteredClients"
                                        :multiple="true"
                                        :close-on-select="true"
                                        :clear-on-select="false"
                                        :preserve-search="true"
                                        placeholder="Select one or more clients"
                                        label="name"
                                        track-by="id"
                                        :searchable="true"
                                        :allow-empty="true"
                                    >
                                        <template #option="{ option }">
                                            {{ option.name }}
                                        </template>
                                        <template #tag="{ option, remove }">
                                            <span class="multiselect__tag">
                                                {{ option.name }}
                                                <i class="multiselect__tag-icon" @click="remove(option)"></i>
                                            </span>
                                        </template>
                                    </Multiselect>
                                    <InputError :message="errors.client_ids ? errors.client_ids[0] : ''" class="mt-2" />
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

<style>
.multiselect {
    min-height: 38px;
}
.multiselect__tags {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
}
.multiselect__tag {
    background: #e5e7eb;
    color: #374151;
}
.multiselect__tag-icon:after {
    color: #6b7280;
}
</style>
