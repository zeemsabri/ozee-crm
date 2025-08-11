<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { usePermissions } from '@/Directives/permissions.js';

const { canDo } = usePermissions();
const canViewBudgets = canDo('view_monthly_budgets');

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const year = ref(currentYear);
const month = ref(currentMonth);
const loading = ref(false);
const error = ref('');
const result = ref(null);

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

const yearOptions = computed(() => {
    const start = currentYear - 3;
    const end = currentYear + 3;
    const arr = [];
    for (let y = end; y >= start; y--) {
        arr.push({ value: y, label: String(y) });
    }
    return arr;
});

const formatCurrency = (n) => {
    if (n === null || n === undefined) return '-';
    try {
        return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(n);
    } catch (e) {
        return `PKR ${Number(n).toFixed(0)}`;
    }
};

const calculate = async () => {
    loading.value = true;
    error.value = '';
    result.value = null;
    try {
        const { data } = await axios.get('/admin/bonus-calculator/calculate', { params: { year: year.value, month: month.value } });
        if (data && data.error) {
            error.value = data.error;
        } else {
            result.value = data;
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to calculate bonuses.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Bonus Calculator" />

        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bonus Calculator</h2>
        </template>

        <div class="py-6 bg-gray-50">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="!canViewBudgets" class="text-red-600">You do not have permission to view this page.</div>
                        <div v-else>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <InputLabel value="Year" />
                                    <SelectDropdown v-model="year" :options="yearOptions" class="mt-1 w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Month" />
                                    <SelectDropdown v-model="month" :options="monthOptions" class="mt-1 w-full" />
                                </div>
                                <div class="md:col-span-2 flex items-end">
                                    <PrimaryButton @click="calculate" :disabled="loading" class="mt-4">
                                        <span v-if="loading">Calculating...</span>
                                        <span v-else>Calculate</span>
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-if="error" class="mt-6 p-3 bg-red-50 text-red-700 rounded">{{ error }}</div>

                            <div v-if="result && !error" class="mt-6 space-y-8">
                                <!-- Summary Stats -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                        <p class="text-sm font-medium text-gray-500">Period</p>
                                        <p class="text-xl font-bold text-gray-800">{{ result.period }}</p>
                                    </div>
                                    <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                        <p class="text-sm font-medium text-gray-500">Total Monthly Budget</p>
                                        <p class="text-xl font-bold text-gray-800">{{ formatCurrency(result.total_budget) }}</p>
                                    </div>
                                    <div class="bg-gray-100 p-4 rounded-lg shadow-sm">
                                        <p class="text-sm font-medium text-gray-500">Employee Pool Allocated</p>
                                        <p class="text-xl font-bold text-gray-800">{{ formatCurrency(result.employee_pool_allocated) }}</p>
                                    </div>
                                    <div class="bg-gray-100 p-4 rounded-lg shadow-sm lg:col-span-1 md:col-span-1">
                                        <p class="text-sm font-medium text-gray-500">Contractor Pool Allocated</p>
                                        <p class="text-xl font-bold text-gray-800">{{ formatCurrency(result.contractor_pool_allocated) }}</p>
                                    </div>
                                </div>

                                <!-- Employee Bonuses -->
                                <div>
                                    <h3 class="text-2xl font-bold mb-4">Employee Bonuses</h3>
                                    <div v-if="result.employees?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No employee bonuses were calculated for this period.</div>
                                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div v-for="employee in result.employees" :key="employee.user_id" class="bg-white p-6 rounded-lg shadow-md border-t-4 border-blue-500" :class="{'border-purple-500': employee.user_id === null}">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4 class="text-xl font-bold text-gray-800">{{ employee.name }}</h4>
                                                <span v-if="employee.points" class="text-lg font-bold text-gray-600">{{ employee.points }} pts</span>
                                            </div>
                                            <div class="space-y-3">
                                                <div v-for="award in employee.awards" class="flex justify-between items-center text-sm">
                                                    <span class="font-medium text-gray-700">{{ award.award }}</span>
                                                    <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contractor Bonuses -->
                                <div>
                                    <h3 class="text-2xl font-bold mb-4">Contractor Bonuses</h3>
                                    <div v-if="result.contractors?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No contractor bonuses were calculated for this period.</div>
                                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div v-for="contractor in result.contractors" :key="contractor.user_id" class="bg-white p-6 rounded-lg shadow-md border-t-4 border-teal-500">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4 class="text-xl font-bold text-gray-800">{{ contractor.name }}</h4>
                                                <span v-if="contractor.points" class="text-lg font-bold text-gray-600">{{ contractor.points }} pts</span>
                                            </div>
                                            <div class="space-y-3">
                                                <div v-for="award in contractor.awards" class="flex justify-between items-center text-sm">
                                                    <span class="font-medium text-gray-700">{{ award.award }}</span>
                                                    <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
