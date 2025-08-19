<script setup>
import { computed, ref, onMounted, watch, reactive } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import MultiSelectWithRoles from '@/Components/MultiSelectWithRoles.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { success, error } from '@/Utils/notification';
import { fetchProjectSectionData, fetchClients as fetchAllClients, fetchUsers as fetchAllUsers } from '@/Components/ProjectForm/useProjectData';

const props = defineProps({
    projectId: { // Now accepts projectId directly
        type: [Number, String],
        required: true
    },
    errors: {
        type: Object,
        default: () => ({})
    },
    clientRoleOptions: {
        type: Array,
        default: () => []
    },
    userRoleOptions: {
        type: Array,
        default: () => []
    },
    clients: { // All available clients for selection (passed from parent)
        type: Array,
        default: () => []
    },
    users: { // All available users for selection (passed from parent)
        type: Array,
        default: () => []
    },
    canViewProjectClients: {
        type: Boolean,
        default: false
    },
    canManageProjectClients: {
        type: Boolean,
        default: false
    },
    canViewProjectUsers: {
        type: Boolean,
        default: false
    },
    canManageProjectUsers: {
        type: Boolean,
        default: false
    },
    isSaving: { // Overall page saving state
        type: Boolean,
        default: false
    }
});

// Local reactive state for this section's data
const localClientsUsersForm = reactive({
    client_ids: [],
    user_ids: [],
    contract_details: '',
});

const clientSaving = ref(false);
const clientSaveSuccess = ref(false);
const clientSaveError = ref('');

const userSaving = ref(false);
const userSaveSuccess = ref(false);
const userSaveError = ref('');


// Function to fetch clients and users data for this specific tab
const fetchClientsUsersData = async () => {
    if (!props.projectId) return;

    try {
        const data = await fetchProjectSectionData(props.projectId, 'client', {
            canViewProjectClients: props.canViewProjectClients,
            canManageProjectClients: props.canManageProjectClients,
            canViewProjectUsers: props.canViewProjectUsers,
            canManageProjectUsers: props.canManageProjectUsers,
        });

        if (data) {
            localClientsUsersForm.client_ids = data.clients ? data.clients.map(client => ({
                id: client.id,
                role_id: client.pivot?.role_id || (props.clientRoleOptions.length > 0 ? props.clientRoleOptions[0].value : null)
            })) : [];
            localClientsUsersForm.user_ids = data.users ? data.users.map(user => ({
                id: user.id,
                role_id: user.pivot?.role_id || (props.userRoleOptions.length > 0 ? props.userRoleOptions[0].value : null)
            })) : [];
            localClientsUsersForm.contract_details = data.contract_details || '';
        }
    } catch (err) {
        console.error('Error fetching clients/users data:', err);
        error('Failed to load clients and users data.');
    }
};
// Watch for projectId changes to re-fetch data
watch(() => props.projectId, async (newId) => {
    if (newId) {
        await fetchClientsUsersData();
    }
}, { immediate: true }); // Immediate ensures it runs on initial mount too

// Function to save clients
const handleSaveClients = async () => {
    if (!props.projectId) {
        error('Project ID is missing. Cannot save clients.');
        return;
    }
    if (!localClientsUsersForm.client_ids || localClientsUsersForm.client_ids.length === 0) {
        error('Please select at least one client to save.');
        return;
    }

    clientSaving.value = true;
    clientSaveSuccess.value = false;
    clientSaveError.value = '';

    try {
        await window.axios.post(`/api/projects/${props.projectId}/attach-clients`, {
            client_ids: localClientsUsersForm.client_ids
        });
        success('Clients saved successfully!');
        clientSaveSuccess.value = true;
        // Re-fetch data for this section to ensure consistency after save
        await fetchClientsUsersData();
    } catch (err) {
        console.error('Error saving clients:', err);
        clientSaveError.value = err.response?.data?.message || 'Failed to save clients.';
        error(clientSaveError.value);
    } finally {
        clientSaving.value = false;
        setTimeout(() => clientSaveSuccess.value = false, 3000); // Hide success message after 3 seconds
    }
};

// Function to save users
const handleSaveUsers = async () => {
    if (!props.projectId) {
        error('Project ID is missing. Cannot save users.');
        return;
    }
    if (!localClientsUsersForm.user_ids || localClientsUsersForm.user_ids.length === 0) {
        error('Please select at least one user to save.');
        return;
    }

    userSaving.value = true;
    userSaveSuccess.value = false;
    userSaveError.value = '';

    try {
        await window.axios.post(`/api/projects/${props.projectId}/attach-users`, {
            user_ids: localClientsUsersForm.user_ids
        });
        success('Users saved successfully!');
        userSaveSuccess.value = true;
        // Re-fetch data for this section to ensure consistency after save
        await fetchClientsUsersData();
    } catch (err) {
        console.error('Error saving users:', err);
        userSaveError.value = err.response?.data?.message || 'Failed to save users.';
        error(userSaveError.value);
    } finally {
        userSaving.value = false;
        setTimeout(() => userSaveSuccess.value = false, 3000); // Hide success message after 3 seconds
    }
};



// Initial data fetch on component mount
onMounted(() => {
    // The watch handler with { immediate: true } will handle the initial fetch
    // when props.projectId is first available.
});
</script>

<template>
    <div class="space-y-8">
        <!-- Clients Section -->
        <div v-if="canViewProjectClients" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-5">Project Clients</h3>
            <MultiSelectWithRoles
                label="Select Clients"
                :items="clients"
                v-model:selectedItems="localClientsUsersForm.client_ids"
                :roleOptions="clientRoleOptions"
                roleType="client"
                :error="errors.client_ids ? errors.client_ids[0] : ''"
                placeholder="Search and select clients to add"
                :disabled="!canManageProjectClients || isSaving || clientSaving"
                :readonly="!canManageProjectClients && canViewProjectClients"
                :showRemoveButton="canManageProjectClients"
                class="mb-4"
            />
            <div v-if="canManageProjectClients" class="mt-6 flex justify-end">
                <PrimaryButton @click="handleSaveClients" :disabled="clientSaving || isSaving"
                               class="px-6 py-3 rounded-lg text-lg shadow-md hover:shadow-lg transition-all duration-200"
                >
                    <span v-if="clientSaving">Saving Clients...</span>
                    <span v-else>Save Clients</span>
                </PrimaryButton>
            </div>
            <div v-if="clientSaveSuccess" class="mt-3 text-green-600 text-sm flex items-center">
                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Clients saved successfully!
            </div>
            <div v-if="clientSaveError" class="mt-3 text-red-600 text-sm flex items-center">
                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ clientSaveError }}
            </div>
        </div>

        <!-- Contract Details -->
<!--        <div class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">-->
<!--            <h3 class="text-xl font-semibold text-gray-800 mb-5">Contract Information</h3>-->
<!--            <InputLabel for="contract_details" value="Contract Details" class="mb-1" />-->
<!--            <textarea-->
<!--                id="contract_details"-->
<!--                class="block w-full px-4 py-2 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-200 resize-y min-h-[120px]"-->
<!--                v-model="localClientsUsersForm.contract_details"-->
<!--                :disabled="!canManageProjectClients || isSaving"-->
<!--                placeholder="Enter contract terms, agreements, or important notes about the client relationship."-->
<!--            ></textarea>-->
<!--            <InputError :message="errors.contract_details ? errors.contract_details[0] : ''" class="mt-2" />-->
<!--        </div>-->

        <!-- Assign Users Section -->
        <div v-if="canViewProjectUsers" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-5">Assign Project Users</h3>
            <MultiSelectWithRoles
                label="Assign Users"
                :items="users"
                v-model:selectedItems="localClientsUsersForm.user_ids"
                :roleOptions="userRoleOptions"
                roleType="project"
                :defaultRoleId="2"
                :error="errors.user_ids ? errors.user_ids[0] : ''"
                placeholder="Search and assign users to this project"
                :disabled="!canManageProjectUsers || isSaving || userSaving"
                :readonly="!canManageProjectUsers && canViewProjectUsers"
                :showRemoveButton="canManageProjectUsers"
                class="mb-4"
            />
            <div v-if="canManageProjectUsers" class="mt-6 flex justify-end">
                <PrimaryButton @click="handleSaveUsers" :disabled="userSaving || isSaving"
                               class="px-6 py-3 rounded-lg text-lg shadow-md hover:shadow-lg transition-all duration-200"
                >
                    <span v-if="userSaving">Saving Users...</span>
                    <span v-else>Save Users</span>
                </PrimaryButton>
            </div>
            <div v-if="userSaveSuccess" class="mt-3 text-green-600 text-sm flex items-center">
                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Users saved successfully!
            </div>
            <div v-if="userSaveError" class="mt-3 text-red-600 text-sm flex items-center">
                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ userSaveError }}
            </div>
        </div>
    </div>
</template>
