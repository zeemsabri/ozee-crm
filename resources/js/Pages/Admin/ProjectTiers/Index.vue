<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions.js';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Import the new component

const { canDo } = usePermissions();

// Permissions
const canCreateTiers = canDo('create_project_tiers');
const canEditTiers = canDo('edit_project_tiers');
const canDeleteTiers = canDo('delete_project_tiers');

// Reactive state
const projectTiers = ref([]);
const loading = ref(true);
const generalError = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Form for creating/editing
const form = useForm({
    id: null,
    name: '',
    point_multiplier: 1.0,
    min_profit_margin_percentage: 0,
    max_profit_margin_percentage: 100,
    min_client_amount_pkr: 0,
    max_client_amount_pkr: 1000000,
});

// State for tier being deleted
const tierToDelete = ref(null);

// --- Fetch Project Tiers ---
const fetchProjectTiers = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await axios.get('/api/project-tiers');
        projectTiers.value = response.data;
    } catch (error) {
        generalError.value = 'Failed to fetch project tiers.';
        console.error('Error fetching project tiers:', error);
    } finally {
        loading.value = false;
    }
};

// --- Create Project Tier ---
const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    showCreateModal.value = true;
};

const handleCreationSuccess = () => {
    showCreateModal.value = false;
    fetchProjectTiers();
};

// --- Edit Project Tier ---
const openEditModal = (tier) => {
    form.reset();
    form.clearErrors();
    form.id = tier.id;
    form.name = tier.name;
    form.point_multiplier = tier.point_multiplier;
    form.min_profit_margin_percentage = tier.min_profit_margin_percentage;
    form.max_profit_margin_percentage = tier.max_profit_margin_percentage;
    form.min_client_amount_pkr = tier.min_client_amount_pkr;
    form.max_client_amount_pkr = tier.max_client_amount_pkr;
    showEditModal.value = true;
};

const handleUpdateSuccess = () => {
    showEditModal.value = false;
    fetchProjectTiers();
};

// --- Delete Project Tier ---
const confirmTierDeletion = (tier) => {
    tierToDelete.value = tier;
    showDeleteModal.value = true;
};

const deleteProjectTier = () => {
    axios.delete(`/api/project-tiers/${tierToDelete.value.id}`)
        .then(() => {
            showDeleteModal.value = false;
            fetchProjectTiers();
        })
        .catch(error => {
            generalError.value = 'Failed to delete project tier.';
            console.error('Error deleting project tier:', error);
        });
};

// Fetch project tiers when component is mounted
onMounted(() => {
    fetchProjectTiers();
});
</script>

<template>
    <Head title="Project Tiers" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Project Tiers</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Manage Project Tiers</h3>

                        <div v-if="canCreateTiers" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New Project Tier
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading project tiers...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="projectTiers.length === 0" class="text-gray-600">No project tiers found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Point Multiplier</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit Margin (%)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Amount (PKR)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="tier in projectTiers" :key="tier.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ tier.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ tier.point_multiplier }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ tier.min_profit_margin_percentage }}% - {{ tier.max_profit_margin_percentage }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ tier.min_client_amount_pkr.toLocaleString() }} - {{ tier.max_client_amount_pkr.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton
                                                v-if="canEditTiers"
                                                @click="openEditModal(tier)">
                                                Edit
                                            </PrimaryButton>
                                            <DangerButton
                                                v-if="canDeleteTiers"
                                                @click="confirmTierDeletion(tier)">
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <BaseFormModal
            :show="showCreateModal"
            title="Create New Project Tier"
            api-endpoint="/api/project-tiers"
            http-method="post"
            :form-data="form"
            submit-button-text="Create Tier"
            success-message="Project tier created successfully!"
            @close="showCreateModal = false"
            @submitted="handleCreationSuccess"
        >
            <template #default="{ errors }">
                <div class="mb-4">
                    <InputLabel for="create_name" value="Name" />
                    <TextInput id="create_name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                    <InputError :message="errors.name" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_point_multiplier" value="Point Multiplier" />
                    <p class="text-sm text-gray-600 mb-1">
                        The multiplier applied to all points earned on projects in this tier (e.g., 1.50 for Tier 1).
                    </p>
                    <TextInput id="create_point_multiplier" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.point_multiplier" required />
                    <InputError :message="errors.point_multiplier" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_min_profit_margin" value="Minimum Profit Margin (%)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The minimum profit margin a project must have to be in this tier.
                    </p>
                    <TextInput id="create_min_profit_margin" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" v-model="form.min_profit_margin_percentage" required />
                    <InputError :message="errors.min_profit_margin_percentage" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_max_profit_margin" value="Maximum Profit Margin (%)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The maximum profit margin a project can have to be in this tier.
                    </p>
                    <TextInput id="create_max_profit_margin" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" v-model="form.max_profit_margin_percentage" required />
                    <InputError :message="errors.max_profit_margin_percentage" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_min_client_amount" value="Minimum Client Amount (PKR)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The minimum total agreed-upon amount for a project to qualify for this tier.
                    </p>
                    <TextInput id="create_min_client_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.min_client_amount_pkr" required />
                    <InputError :message="errors.min_client_amount_pkr" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_max_client_amount" value="Maximum Client Amount (PKR)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The maximum total agreed-upon amount for a project to qualify for this tier.
                    </p>
                    <TextInput id="create_max_client_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.max_client_amount_pkr" required />
                    <InputError :message="errors.max_client_amount_pkr" class="mt-2" />
                </div>
            </template>
        </BaseFormModal>

        <BaseFormModal
            :show="showEditModal"
            title="Edit Project Tier"
            :api-endpoint="`/api/project-tiers/${form.id}`"
            http-method="put"
            :form-data="form"
            submit-button-text="Update Tier"
            success-message="Project tier updated successfully!"
            @close="showEditModal = false"
            @submitted="handleUpdateSuccess"
        >
            <template #default="{ errors }">
                <div class="mb-4">
                    <InputLabel for="edit_name" value="Name" />
                    <TextInput id="edit_name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus />
                    <InputError :message="errors.name" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_point_multiplier" value="Point Multiplier" />
                    <p class="text-sm text-gray-600 mb-1">
                        The multiplier applied to all points earned on projects in this tier (e.g., 1.50 for Tier 1).
                    </p>
                    <TextInput id="edit_point_multiplier" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.point_multiplier" required />
                    <InputError :message="errors.point_multiplier" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_min_profit_margin" value="Minimum Profit Margin (%)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The minimum profit margin a project must have to be in this tier.
                    </p>
                    <TextInput id="edit_min_profit_margin" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" v-model="form.min_profit_margin_percentage" required />
                    <InputError :message="errors.min_profit_margin_percentage" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_max_profit_margin" value="Maximum Profit Margin (%)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The maximum profit margin a project can have to be in this tier.
                    </p>
                    <TextInput id="edit_max_profit_margin" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" v-model="form.max_profit_margin_percentage" required />
                    <InputError :message="errors.max_profit_margin_percentage" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_min_client_amount" value="Minimum Client Amount (PKR)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The minimum total agreed-upon amount for a project to qualify for this tier.
                    </p>
                    <TextInput id="edit_min_client_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.min_client_amount_pkr" required />
                    <InputError :message="errors.min_client_amount_pkr" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_max_client_amount" value="Maximum Client Amount (PKR)" />
                    <p class="text-sm text-gray-600 mb-1">
                        The maximum total agreed-upon amount for a project to qualify for this tier.
                    </p>
                    <TextInput id="edit_max_client_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.max_client_amount_pkr" required />
                    <InputError :message="errors.max_client_amount_pkr" class="mt-2" />
                </div>
            </template>
        </BaseFormModal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this project tier?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All projects associated with this tier will have their tier set to null.
                </p>
                <div v-if="tierToDelete" class="mt-4 text-gray-800">
                    <strong>Tier:</strong> {{ tierToDelete.name }}
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteProjectTier">Delete Tier</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
