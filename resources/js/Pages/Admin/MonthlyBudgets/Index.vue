<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from '@/Directives/permissions.js';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const { canDo } = usePermissions();

// Permissions
const canViewBudgets = canDo('view_monthly_budgets');
const canManageBudgets = canDo('manage_monthly_budgets');

// Reactive state
const monthlyBudgets = ref([]);
const loading = ref(true);
const generalError = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Form for creating/editing
const form = useForm({
    id: null,
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1, // JavaScript months are 0-indexed
    total_budget_pkr: 0,
    consistent_contributor_pool_pkr: 0,
    high_achiever_pool_pkr: 0,
    team_total_points: 0,
    points_value_pkr: 0,
    most_improved_award_pkr: 0,
    first_place_award_pkr: 0,
});

// State for budget being deleted
const budgetToDelete = ref(null);

// Month options for dropdown
const monthOptions = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

// Year options for dropdown (current year and 5 years before/after)
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let i = currentYear - 5; i <= currentYear + 5; i++) {
        years.push({ value: i, label: i.toString() });
    }
    return years;
});

// Helper to get month name
const getMonthName = (monthNumber) => {
    return monthOptions.find(m => m.value === monthNumber)?.label || '';
};

// --- Fetch Monthly Budgets ---
const fetchMonthlyBudgets = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await axios.get('/admin/monthly-budgets/all');
        monthlyBudgets.value = response.data;
    } catch (error) {
        generalError.value = 'Failed to fetch monthly budgets.';
        console.error('Error fetching monthly budgets:', error);
    } finally {
        loading.value = false;
    }
};

// --- Create Monthly Budget ---
const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    // Set default values to current year and month
    form.year = new Date().getFullYear();
    form.month = new Date().getMonth() + 1;
    showCreateModal.value = true;
};

const handleCreationSuccess = () => {
    showCreateModal.value = false;
    fetchMonthlyBudgets();
};

// --- Edit Monthly Budget ---
const openEditModal = (budget) => {
    form.reset();
    form.clearErrors();
    form.id = budget.id;
    form.year = budget.year;
    form.month = budget.month;
    form.total_budget_pkr = budget.total_budget_pkr;
    form.consistent_contributor_pool_pkr = budget.consistent_contributor_pool_pkr;
    form.high_achiever_pool_pkr = budget.high_achiever_pool_pkr;
    form.team_total_points = budget.team_total_points;
    form.points_value_pkr = budget.points_value_pkr;
    form.most_improved_award_pkr = budget.most_improved_award_pkr;
    form.first_place_award_pkr = budget.first_place_award_pkr;
    showEditModal.value = true;
};

const handleUpdateSuccess = () => {
    showEditModal.value = false;
    fetchMonthlyBudgets();
};

// --- Delete Monthly Budget ---
const confirmBudgetDeletion = (budget) => {
    budgetToDelete.value = budget;
    showDeleteModal.value = true;
};

const deleteMonthlyBudget = () => {
    axios.delete(`/admin/monthly-budgets/${budgetToDelete.value.id}`)
        .then(() => {
            showDeleteModal.value = false;
            fetchMonthlyBudgets();
        })
        .catch(error => {
            generalError.value = 'Failed to delete monthly budget.';
            console.error('Error deleting monthly budget:', error);
        });
};

// Fetch monthly budgets when component is mounted
onMounted(() => {
    fetchMonthlyBudgets();
});
</script>

<template>
    <Head title="Monthly Budgets" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monthly Budgets</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Manage Monthly Budgets</h3>

                        <div v-if="canManageBudgets" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New Monthly Budget
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading monthly budgets...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="monthlyBudgets.length === 0" class="text-gray-600">No monthly budgets found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Budget (PKR)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Points</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Point Value (PKR)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="budget in monthlyBudgets" :key="budget.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ getMonthName(budget.month) }} {{ budget.year }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.total_budget_pkr.toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.team_total_points.toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.points_value_pkr.toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton
                                                v-if="canManageBudgets"
                                                @click="openEditModal(budget)">
                                                Edit
                                            </PrimaryButton>
                                            <DangerButton
                                                v-if="canManageBudgets"
                                                @click="confirmBudgetDeletion(budget)">
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
            title="Create New Monthly Budget"
            api-endpoint="/admin/monthly-budgets"
            http-method="post"
            :form-data="form"
            submit-button-text="Create Budget"
            success-message="Monthly budget created successfully!"
            @close="showCreateModal = false"
            @submitted="handleCreationSuccess"
        >
            <template #default="{ errors }">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="create_year" value="Year" />
                        <SelectDropdown
                            id="create_year"
                            v-model="form.year"
                            :options="yearOptions"
                            placeholder="Select Year"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="errors.year" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_month" value="Month" />
                        <SelectDropdown
                            id="create_month"
                            v-model="form.month"
                            :options="monthOptions"
                            placeholder="Select Month"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="errors.month" class="mt-2" />
                    </div>
                </div>
                <div class="mb-4">
                    <InputLabel for="create_total_budget" value="Total Budget (PKR)" />
                    <TextInput id="create_total_budget" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.total_budget_pkr" required />
                    <InputError :message="errors.total_budget_pkr" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="create_consistent_contributor_pool" value="Consistent Contributor Pool (PKR)" />
                        <TextInput id="create_consistent_contributor_pool" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.consistent_contributor_pool_pkr" required />
                        <InputError :message="errors.consistent_contributor_pool_pkr" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_high_achiever_pool" value="High Achiever Pool (PKR)" />
                        <TextInput id="create_high_achiever_pool" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.high_achiever_pool_pkr" required />
                        <InputError :message="errors.high_achiever_pool_pkr" class="mt-2" />
                    </div>
                </div>
                <div class="mb-4">
                    <InputLabel for="create_team_total_points" value="Team Total Points" />
                    <TextInput id="create_team_total_points" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.team_total_points" required />
                    <InputError :message="errors.team_total_points" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="create_points_value" value="Points Value (PKR)" />
                    <TextInput id="create_points_value" type="number" step="0.0001" min="0" class="mt-1 block w-full" v-model="form.points_value_pkr" required />
                    <InputError :message="errors.points_value_pkr" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="create_most_improved_award" value="Most Improved Award (PKR)" />
                        <TextInput id="create_most_improved_award" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.most_improved_award_pkr" required />
                        <InputError :message="errors.most_improved_award_pkr" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_first_place_award" value="First Place Award (PKR)" />
                        <TextInput id="create_first_place_award" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.first_place_award_pkr" required />
                        <InputError :message="errors.first_place_award_pkr" class="mt-2" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <BaseFormModal
            :show="showEditModal"
            title="Edit Monthly Budget"
            :api-endpoint="`/admin/monthly-budgets/${form.id}`"
            http-method="put"
            :form-data="form"
            submit-button-text="Update Budget"
            success-message="Monthly budget updated successfully!"
            @close="showEditModal = false"
            @submitted="handleUpdateSuccess"
        >
            <template #default="{ errors }">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="edit_year" value="Year" />
                        <TextInput id="edit_year" type="number" class="mt-1 block w-full" v-model="form.year" disabled />
                    </div>
                    <div>
                        <InputLabel for="edit_month" value="Month" />
                        <TextInput id="edit_month" type="text" class="mt-1 block w-full" :value="getMonthName(form.month)" disabled />
                    </div>
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_total_budget" value="Total Budget (PKR)" />
                    <TextInput id="edit_total_budget" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.total_budget_pkr" required />
                    <InputError :message="errors.total_budget_pkr" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="edit_consistent_contributor_pool" value="Consistent Contributor Pool (PKR)" />
                        <TextInput id="edit_consistent_contributor_pool" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.consistent_contributor_pool_pkr" required />
                        <InputError :message="errors.consistent_contributor_pool_pkr" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_high_achiever_pool" value="High Achiever Pool (PKR)" />
                        <TextInput id="edit_high_achiever_pool" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.high_achiever_pool_pkr" required />
                        <InputError :message="errors.high_achiever_pool_pkr" class="mt-2" />
                    </div>
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_team_total_points" value="Team Total Points" />
                    <TextInput id="edit_team_total_points" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.team_total_points" required />
                    <InputError :message="errors.team_total_points" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="edit_points_value" value="Points Value (PKR)" />
                    <TextInput id="edit_points_value" type="number" step="0.0001" min="0" class="mt-1 block w-full" v-model="form.points_value_pkr" required />
                    <InputError :message="errors.points_value_pkr" class="mt-2" />
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <InputLabel for="edit_most_improved_award" value="Most Improved Award (PKR)" />
                        <TextInput id="edit_most_improved_award" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.most_improved_award_pkr" required />
                        <InputError :message="errors.most_improved_award_pkr" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_first_place_award" value="First Place Award (PKR)" />
                        <TextInput id="edit_first_place_award" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.first_place_award_pkr" required />
                        <InputError :message="errors.first_place_award_pkr" class="mt-2" />
                    </div>
                </div>
            </template>
        </BaseFormModal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this monthly budget?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All points calculations based on this budget will be affected.
                </p>
                <div v-if="budgetToDelete" class="mt-4 text-gray-800">
                    <strong>Period:</strong> {{ getMonthName(budgetToDelete.month) }} {{ budgetToDelete.year }}
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteMonthlyBudget">Delete Budget</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
