<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
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

// Import all calculation logic from the new file
import {
    calculateEmployeeBonusPool,
    calculateContractorBonusPool,
    calculateHighAchieverPool,
    calculateConsistentContributorPool,
    calculatePointsValue,
    awardPercentages
} from './budgetCalculations.js';

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
    month: new Date().getMonth() + 1,
    total_budget_pkr: 50000,
    number_of_employees: 0,
    number_of_contractors: 0,
    employee_pool_input: '', // New input field for user to control employee pool
    team_total_points: 0,
    // These will be calculated automatically
    employee_bonus_pool_pkr: 0,
    contractor_bonus_pool_pkr: 0,
    consistent_contributor_pool_pkr: 0,
    high_achiever_pool_pkr: 0,
    points_value_pkr: 0,
    most_improved_award_pkr: 0,
    first_place_award_pkr: 0,
    second_place_award_pkr: 0,
    third_place_award_pkr: 0,
    contractor_of_the_month_award_pkr: 0,
});

// Computed properties that use the imported functions
const employeeBonusPool = computed(() => calculateEmployeeBonusPool(form.total_budget_pkr, form.employee_pool_input, form.number_of_employees, form.number_of_contractors));
const contractorBonusPool = computed(() => calculateContractorBonusPool(form.total_budget_pkr, employeeBonusPool.value));
const highAchieverPool = computed(() => calculateHighAchieverPool(employeeBonusPool.value));
const consistentContributorPool = computed(() => calculateConsistentContributorPool(employeeBonusPool.value));
const pointsValuePkr = computed(() => calculatePointsValue(consistentContributorPool.value, form.team_total_points));

const firstPlaceAward = computed(() => highAchieverPool.value * awardPercentages.first_place_award);
const secondPlaceAward = computed(() => highAchieverPool.value * awardPercentages.second_place_award);
const thirdPlaceAward = computed(() => highAchieverPool.value * awardPercentages.third_place_award);
const mostImprovedAward = computed(() => highAchieverPool.value * awardPercentages.most_improved_award);
const contractorOfTheMonthAward = computed(() => contractorBonusPool.value * awardPercentages.contractor_of_the_month_award);

// Watch for changes to the total budget, headcount, and employee pool input to update the form data
watch([() => form.total_budget_pkr, () => form.employee_pool_input, () => form.number_of_employees, () => form.number_of_contractors], () => {
    form.employee_bonus_pool_pkr = employeeBonusPool.value;
    form.contractor_bonus_pool_pkr = contractorBonusPool.value;
    form.high_achiever_pool_pkr = highAchieverPool.value;
    form.consistent_contributor_pool_pkr = consistentContributorPool.value;
    form.most_improved_award_pkr = mostImprovedAward.value;
    form.first_place_award_pkr = firstPlaceAward.value;
    form.second_place_award_pkr = secondPlaceAward.value;
    form.third_place_award_pkr = thirdPlaceAward.value;
    form.contractor_of_the_month_award_pkr = contractorOfTheMonthAward.value;
});

// Keep points_value_pkr in sync with calculated value when inputs change
watch([() => form.team_total_points, () => consistentContributorPool.value], () => {
    form.points_value_pkr = pointsValuePkr.value;
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
    form.year = new Date().getFullYear();
    form.month = new Date().getMonth() + 1;
    form.total_budget_pkr = 50000;
    form.number_of_employees = 0;
    form.number_of_contractors = 0;
    form.employee_pool_input = '';
    form.team_total_points = 0;
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
    form.number_of_employees = budget.number_of_employees;
    form.number_of_contractors = budget.number_of_contractors;
    form.employee_pool_input = budget.employee_pool_input; // Preserve saved override value for editing
    form.employee_bonus_pool_pkr = budget.employee_bonus_pool_pkr;
    form.contractor_bonus_pool_pkr = budget.contractor_bonus_pool_pkr;
    form.consistent_contributor_pool_pkr = budget.consistent_contributor_pool_pkr;
    form.high_achiever_pool_pkr = budget.high_achiever_pool_pkr;
    form.team_total_points = budget.team_total_points;
    form.points_value_pkr = budget.points_value_pkr;
    form.most_improved_award_pkr = budget.most_improved_award_pkr;
    form.first_place_award_pkr = budget.first_place_award_pkr;
    form.second_place_award_pkr = budget.second_place_award_pkr;
    form.third_place_award_pkr = budget.third_place_award_pkr;
    form.contractor_of_the_month_award_pkr = budget.contractor_of_the_month_award_pkr;
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Pool (PKR)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contractor Pool (PKR)</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="budget in monthlyBudgets" :key="budget.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ getMonthName(budget.month) }} {{ budget.year }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.total_budget_pkr.toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.employee_bonus_pool_pkr ? budget.employee_bonus_pool_pkr.toLocaleString() : 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ budget.contractor_bonus_pool_pkr ? budget.contractor_bonus_pool_pkr.toLocaleString() : 'N/A' }}</td>
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

                <div class="mb-6">
                    <InputLabel for="create_total_budget" value="Total Monthly Budget (PKR)" />
                    <TextInput id="create_total_budget" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.total_budget_pkr" required />
                    <InputError :message="errors.total_budget_pkr" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <InputLabel for="create_number_of_employees" value="Number of Employees" />
                        <TextInput id="create_number_of_employees" type="number" min="0" class="mt-1 block w-full" v-model="form.number_of_employees" required />
                        <InputError :message="errors.number_of_employees" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="create_number_of_contractors" value="Number of Contractors" />
                        <TextInput id="create_number_of_contractors" type="number" min="0" class="mt-1 block w-full" v-model="form.number_of_contractors" required />
                        <InputError :message="errors.number_of_contractors" class="mt-2" />
                    </div>
                </div>

                <div class="mb-6">
                    <InputLabel for="create_employee_pool_input" value="Employee Bonus Pool Amount or % (Optional Override)" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">Enter a fixed amount (e.g., 20000) or a percentage (e.g., 40%) to override the headcount-based calculation.</p>
                    </div>
                    <TextInput id="create_employee_pool_input" type="text" class="mt-1 block w-full" v-model="form.employee_pool_input" />
                    <InputError :message="errors.employee_pool_input" class="mt-2" />
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Budget Breakdown (Calculated)</h4>
                    <p class="text-sm text-gray-600 mb-4">Pools are calculated based on headcount or your override input.</p>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">Employee Bonus Pool:</strong> <span>PKR {{ employeeBonusPool.toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Contractor Bonus Pool:</strong> <span>PKR {{ contractorBonusPool.toLocaleString() }}</span>
                        </li>
                    </ul>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Employee Pool Allocations (PKR {{ employeeBonusPool.toLocaleString() }})</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">High Achiever Pool:</strong> <span>PKR {{ highAchieverPool.toLocaleString() }}</span>
                            <ul class="ml-4 list-disc list-inside text-gray-600">
                                <li>1st Place ({{ (awardPercentages.first_place_award * 100).toFixed(0) }}%): <span>PKR {{ firstPlaceAward.toLocaleString() }}</span></li>
                                <li>2nd Place ({{ (awardPercentages.second_place_award * 100).toFixed(0) }}%): <span>PKR {{ secondPlaceAward.toLocaleString() }}</span></li>
                                <li>3rd Place ({{ (awardPercentages.third_place_award * 100).toFixed(0) }}%): <span>PKR {{ thirdPlaceAward.toLocaleString() }}</span></li>
                                <li>Most Improved ({{ (awardPercentages.most_improved_award * 100).toFixed(0) }}%): <span>PKR {{ mostImprovedAward.toLocaleString() }}</span></li>
                            </ul>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Consistent Contributor Pool:</strong> <span>PKR {{ consistentContributorPool.toLocaleString() }}</span>
                            <ul class="ml-4 list-disc list-inside text-gray-600">
                                <li>Bronze Tier (1,000 - 1,499 pts): <span>PKR 500</span></li>
                                <li>Silver Tier (1,500 - 1,999 pts): <span>PKR 1,000</span></li>
                                <li>Gold Tier (2,000+ pts): <span>PKR 2,000</span></li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Contractor Pool Allocations (PKR {{ contractorBonusPool.toLocaleString() }})</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">Contractor of the Month:</strong> <span>PKR {{ contractorOfTheMonthAward.toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Project Performance Bonus (Remaining):</strong> <span>PKR {{ (contractorBonusPool - contractorOfTheMonthAward).toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Project Performance Bonus:</strong> This is a separate bonus of <span>5%</span> of the contractor's agreed amount.
                        </li>
                    </ul>
                </div>

                <div class="mb-4">
                    <InputLabel for="create_team_total_points" value="Team Total Points" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">The sum of all points earned by the entire team this month. This value is required to calculate the Points Value.</p>
                    </div>
                    <TextInput id="create_team_total_points" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.team_total_points" required />
                    <InputError :message="errors.team_total_points" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel value="Calculated Points Value (PKR)" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">The cash value of a single point (Consistent Contributor Pool / Team Total Points).</p>
                    </div>
                    <TextInput type="number" step="0.0001" min="0" class="mt-1 block w-full bg-gray-100" :value="form.points_value_pkr" disabled />
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

                <div class="mb-6">
                    <InputLabel for="edit_total_budget" value="Total Monthly Budget (PKR)" />
                    <TextInput id="edit_total_budget" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.total_budget_pkr" required />
                    <InputError :message="errors.total_budget_pkr" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <InputLabel for="edit_number_of_employees" value="Number of Employees" />
                        <TextInput id="edit_number_of_employees" type="number" min="0" class="mt-1 block w-full" v-model="form.number_of_employees" required />
                        <InputError :message="errors.number_of_employees" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="edit_number_of_contractors" value="Number of Contractors" />
                        <TextInput id="edit_number_of_contractors" type="number" min="0" class="mt-1 block w-full" v-model="form.number_of_contractors" required />
                        <InputError :message="errors.number_of_contractors" class="mt-2" />
                    </div>
                </div>

                <div class="mb-6">
                    <InputLabel for="edit_employee_pool_input" value="Employee Bonus Pool Amount or % (Optional Override)" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">Enter a fixed amount (e.g., 20000) or a percentage (e.g., 40%) to override the headcount-based calculation.</p>
                    </div>
                    <TextInput id="edit_employee_pool_input" type="text" class="mt-1 block w-full" v-model="form.employee_pool_input" />
                    <InputError :message="errors.employee_pool_input" class="mt-2" />
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Budget Breakdown (Calculated)</h4>
                    <p class="text-sm text-gray-600 mb-4">Pools are calculated based on headcount or your override input.</p>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">Employee Bonus Pool:</strong> <span>PKR {{ employeeBonusPool.toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Contractor Bonus Pool:</strong> <span>PKR {{ contractorBonusPool.toLocaleString() }}</span>
                        </li>
                    </ul>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Employee Pool Allocations (PKR {{ employeeBonusPool.toLocaleString() }})</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">High Achiever Pool:</strong> <span>PKR {{ highAchieverPool.toLocaleString() }}</span>
                            <ul class="ml-4 list-disc list-inside text-gray-600">
                                <li>1st Place ({{ (awardPercentages.first_place_award * 100).toFixed(0) }}%): <span>PKR {{ firstPlaceAward.toLocaleString() }}</span></li>
                                <li>2nd Place ({{ (awardPercentages.second_place_award * 100).toFixed(0) }}%): <span>PKR {{ secondPlaceAward.toLocaleString() }}</span></li>
                                <li>3rd Place ({{ (awardPercentages.third_place_award * 100).toFixed(0) }}%): <span>PKR {{ thirdPlaceAward.toLocaleString() }}</span></li>
                                <li>Most Improved ({{ (awardPercentages.most_improved_award * 100).toFixed(0) }}%): <span>PKR {{ mostImprovedAward.toLocaleString() }}</span></li>
                            </ul>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Consistent Contributor Pool:</strong> <span>PKR {{ consistentContributorPool.toLocaleString() }}</span>
                            <ul class="ml-4 list-disc list-inside text-gray-600">
                                <li>Bronze Tier (1,000 - 1,499 pts): <span>PKR 500</span></li>
                                <li>Silver Tier (1,500 - 1,999 pts): <span>PKR 1,000</span></li>
                                <li>Gold Tier (2,000+ pts): <span>PKR 2,000</span></li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50 mb-6">
                    <h4 class="font-bold text-gray-800 text-lg mb-2">Contractor Pool Allocations (PKR {{ contractorBonusPool.toLocaleString() }})</h4>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li>
                            <strong class="font-medium text-gray-900">Contractor of the Month:</strong> <span>PKR {{ contractorOfTheMonthAward.toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Project Performance Bonus (Remaining):</strong> <span>PKR {{ (contractorBonusPool - contractorOfTheMonthAward).toLocaleString() }}</span>
                        </li>
                        <li>
                            <strong class="font-medium text-gray-900">Project Performance Bonus:</strong> This is a separate bonus of <span>5%</span> of the contractor's agreed amount.
                        </li>
                    </ul>
                </div>

                <div class="mb-4">
                    <InputLabel for="edit_team_total_points" value="Team Total Points" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">The sum of all points earned by the entire team this month. This value is required to calculate the Points Value.</p>
                    </div>
                    <TextInput id="edit_team_total_points" type="number" step="0.01" min="0" class="mt-1 block w-full" v-model="form.team_total_points" required />
                    <InputError :message="errors.team_total_points" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel value="Calculated Points Value (PKR)" />
                    <div class="flex items-center space-x-2">
                        <p class="text-sm text-gray-600">The cash value of a single point (Consistent Contributor Pool / Team Total Points).</p>
                    </div>
                    <TextInput type="number" step="0.0001" min="0" class="mt-1 block w-full bg-gray-100" :value="form.points_value_pkr" disabled />
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
