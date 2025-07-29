<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue'; // Potentially for contract details
import InputError from '@/Components/InputError.vue';
import MultiSelectWithRoles from '@/Components/MultiSelectWithRoles.vue'; // Custom component
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    projectForm: {
        type: Object,
        required: true,
        default: () => ({
            id: null,
            client_ids: [],
            user_ids: [],
            contract_details: '',
        })
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
    clients: { // All available clients for selection
        type: Array,
        default: () => []
    },
    users: { // All available users for selection
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
    clientSaving: {
        type: Boolean,
        default: false
    },
    clientSaveSuccess: {
        type: Boolean,
        default: false
    },
    clientSaveError: {
        type: String,
        default: ''
    },
    userSaving: {
        type: Boolean,
        default: false
    },
    userSaveSuccess: {
        type: Boolean,
        default: false
    },
    userSaveError: {
        type: String,
        default: ''
    },
});

const emit = defineEmits(['update:projectForm', 'saveClients', 'saveUsers']);

// Computed property for v-model binding
const localProjectForm = computed({
    get: () => props.projectForm,
    set: (value) => emit('update:projectForm', value)
});

// Pass through events to the parent component
const handleSaveClients = () => {
    emit('saveClients', localProjectForm.value.client_ids);
};

const handleSaveUsers = () => {
    emit('saveUsers', localProjectForm.value.user_ids);
};
</script>

<template>
    <div class="space-y-8">
        <!-- Clients Section -->
        <div v-if="canViewProjectClients" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-5">Project Clients</h3>
            <MultiSelectWithRoles
                label="Select Clients"
                :items="clients"
                v-model:selectedItems="localProjectForm.client_ids"
                :roleOptions="clientRoleOptions"
                roleType="client"
                :error="errors.client_ids ? errors.client_ids[0] : ''"
                placeholder="Search and select clients to add"
                :disabled="!canManageProjectClients"
                :readonly="!canManageProjectClients && canViewProjectClients"
                :showRemoveButton="canManageProjectClients"
                class="mb-4"
            />
            <div v-if="canManageProjectClients" class="mt-6 flex justify-end">
                <PrimaryButton @click="handleSaveClients" :disabled="clientSaving"
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
        <div class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-5">Contract Information</h3>
            <InputLabel for="contract_details" value="Contract Details" class="mb-1" />
            <textarea
                id="contract_details"
                class="block w-full px-4 py-2 border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors duration-200 resize-y min-h-[120px]"
                v-model="localProjectForm.contract_details"
                :disabled="!canManageProjectClients"
                placeholder="Enter contract terms, agreements, or important notes about the client relationship."
            ></textarea>
            <InputError :message="errors.contract_details ? errors.contract_details[0] : ''" class="mt-2" />
        </div>

        <!-- Assign Users Section -->
        <div v-if="canViewProjectUsers" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
            <h3 class="text-xl font-semibold text-gray-800 mb-5">Assign Project Users</h3>
            <MultiSelectWithRoles
                label="Assign Users"
                :items="users"
                v-model:selectedItems="localProjectForm.user_ids"
                :roleOptions="userRoleOptions"
                roleType="project"
                :defaultRoleId="2"
                :error="errors.user_ids ? errors.user_ids[0] : ''"
                placeholder="Search and assign users to this project"
                :disabled="!canManageProjectUsers"
                :readonly="!canManageProjectUsers && canViewProjectUsers"
                :showRemoveButton="canManageProjectUsers"
                class="mb-4"
            />
            <div v-if="canManageProjectUsers" class="mt-6 flex justify-end">
                <PrimaryButton @click="handleSaveUsers" :disabled="userSaving"
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

