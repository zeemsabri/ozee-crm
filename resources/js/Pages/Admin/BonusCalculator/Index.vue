<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TeamMetricsChart from '@/Components/BonusCalculator/TeamMetricsChart.vue'; // The new chart component

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const year = ref(currentYear);
const month = ref(currentMonth);
const loading = ref(false);
const error = ref('');
const result = ref(null);

const monthOptions = [
    { value: 1, label: 'January' }, { value: 2, label: 'February' },
    { value: 3, label: 'March' }, { value: 4, label: 'April' },
    { value: 5, label: 'May' }, { value: 6, label: 'June' },
    { value: 7, label: 'July' }, { value: 8, label: 'August' },
    { value: 9, label: 'September' }, { value: 10, label: 'October' },
    { value: 11, label: 'November' }, { value: 12, label: 'December' },
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

const formatCurrency = (n, precision = 2) => {
    if (n === null || n === undefined) return '-';
    const numericValue = Number(n);
    const options = {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: precision,
        minimumFractionDigits: precision
    };
    try {
        return new Intl.NumberFormat('en-PK', options).format(numericValue);
    } catch (e) {
        return `PKR ${numericValue.toFixed(precision)}`;
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
            if (data.employees) {
                data.employees.forEach(emp => emp.showDetails = false);
            }
            if (data.contractors) {
                data.contractors.forEach(con => con.showDetails = false);
            }
            if (data.team_metrics) {
                data.team_metrics.showDetails = false;
            }
            result.value = data;
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to calculate bonuses.';
    } finally {
        loading.value = false;
    }
};

const employeeTotalBonus = computed(() => {
    if (!result.value?.employees) return 0;
    return result.value.employees.reduce((acc, emp) => {
        const totalAwardAmount = emp.awards.reduce((awardAcc, award) => awardAcc + (Number(award.amount) || 0), 0);
        return acc + totalAwardAmount;
    }, 0);
});

const employeeTotalPoints = computed(() => {
    if (!result.value?.employees) return 0;
    return result.value.employees.reduce((acc, emp) => acc + (Number(emp.points) || 0), 0);
});

const employeeCostPerPoint = computed(() => {
    if (employeeTotalPoints.value === 0) return 0;
    return employeeTotalBonus.value / employeeTotalPoints.value;
});

const contractorTotalBonus = computed(() => {
    if (!result.value?.contractors) return 0;
    return result.value.contractors.reduce((acc, con) => {
        const totalAwardAmount = con.awards.reduce((awardAcc, award) => awardAcc + (Number(award.amount) || 0), 0);
        return acc + totalAwardAmount;
    }, 0);
});

const contractorProjectBreakdown = computed(() => {
    if (!result.value?.contractors) return [];
    const breakdown = {};
    result.value.contractors.forEach(contractor => {
        contractor.awards.forEach(award => {
            if (award.details && award.details.includes('Sum of approved bonuses for:')) {
                const projectName = award.details.split(': ')[1];
                if (!breakdown[projectName]) {
                    breakdown[projectName] = 0;
                }
                breakdown[projectName] += Number(award.amount);
            }
        });
    });
    return Object.keys(breakdown).map(projectName => ({
        project: projectName,
        amount: breakdown[projectName],
    }));
});

const toggleDetails = (user) => {
    user.showDetails = !user.showDetails;
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
                        <!-- Controls Section -->
                        <div class="flex flex-col md:flex-row items-end justify-between gap-4 p-4 mb-8 bg-gray-100 rounded-lg">
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Year" />
                                    <SelectDropdown v-model="year" :options="yearOptions" class="mt-1 w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Month" />
                                    <SelectDropdown v-model="month" :options="monthOptions" class="mt-1 w-full" />
                                </div>
                            </div>
                            <div class="flex-shrink-0 w-full md:w-auto">
                                <PrimaryButton @click="calculate" :disabled="loading" class="w-full mt-4 md:mt-0">
                                    <span v-if="loading">Calculating...</span>
                                    <span v-else>Calculate</span>
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="error" class="mt-6 p-3 bg-red-50 text-red-700 rounded">{{ error }}</div>

                        <div v-if="result && !error" class="mt-6 space-y-8">
                            <!-- Overall Summary Section -->
                            <div class="p-6 rounded-xl bg-gradient-to-br from-indigo-50 to-white shadow-lg">
                                <!-- Top row: Period and Total Budget -->
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Bonus Period</p>
                                        <p class="text-3xl font-bold text-gray-800">{{ result.period }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-500">Total Budget</p>
                                        <p class="text-3xl font-bold text-gray-800">{{ formatCurrency(result.total_budget, 0) }}</p>
                                    </div>
                                </div>

                                <!-- Middle row: Employee and Contractor Pools -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                    <!-- Employee Pool -->
                                    <div class="bg-white p-6 rounded-lg shadow-sm border border-blue-200">
                                        <h3 class="text-lg font-semibold text-blue-600 mb-2">Employee Pool</h3>
                                        <p class="text-3xl font-bold text-blue-600 mb-4">{{ formatCurrency(result.employee_pool_allocated) }}</p>
                                        <div class="space-y-2 text-sm text-gray-600">
                                            <div class="flex justify-between">
                                                <span class="font-medium">Total Bonus Amount</span>
                                                <span class="font-bold">{{ formatCurrency(employeeTotalBonus) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="font-medium">Total Points</span>
                                                <span class="font-bold">{{ employeeTotalPoints.toFixed(0) }} pts</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="font-medium">Cost Per Point</span>
                                                <span class="font-bold">{{ formatCurrency(employeeCostPerPoint, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contractor Pool -->
                                    <div class="bg-white p-6 rounded-lg shadow-sm border border-teal-200">
                                        <h3 class="text-lg font-semibold text-teal-600 mb-2">Contractor Pool</h3>
                                        <p class="text-3xl font-bold text-teal-600 mb-4">{{ formatCurrency(result.contractor_pool_allocated) }}</p>
                                        <div class="space-y-2 text-sm text-gray-600">
                                            <div class="flex justify-between">
                                                <span class="font-medium">Total Bonus Amount</span>
                                                <span class="font-bold">{{ formatCurrency(contractorTotalBonus) }}</span>
                                            </div>
                                            <div class="mt-4">
                                                <p class="font-medium text-gray-700">Project Performance Bonus Breakdown</p>
                                                <div v-if="contractorProjectBreakdown.length > 0" class="space-y-1 mt-2 pl-4 border-l border-gray-200">
                                                    <div v-for="item in contractorProjectBreakdown" :key="item.project" class="flex justify-between items-center text-xs">
                                                        <span class="truncate">{{ item.project }}</span>
                                                        <span class="font-semibold text-green-600">{{ formatCurrency(item.amount) }}</span>
                                                    </div>
                                                </div>
                                                <div v-else class="text-xs italic text-gray-500 mt-2">No project bonuses to display.</div>
                                            </div>
                                            <div class="flex justify-between mt-4 border-t pt-2">
                                                <span class="font-medium">Unallocated Pool</span>
                                                <span class="font-bold">{{ formatCurrency(result.contractor_metrics.project_performance_bonus_pool.amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Team Metrics Section (with the new chart component) -->
                            <div class="space-y-4">
                                <h3 class="text-2xl font-bold text-gray-800">Team Metrics</h3>
                                <TeamMetricsChart v-if="result.team_metrics" :metrics="result.team_metrics" />
                                <div v-else class="text-gray-600 p-4 border rounded-lg bg-gray-50">
                                    No team metrics available for this period.
                                </div>
                            </div>

                            <!-- Employees List Section -->
                            <div class="space-y-4">
                                <h3 class="text-2xl font-bold text-gray-800">Employee Bonuses</h3>
                                <div v-if="result.employees?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No employee bonuses were calculated for this period.</div>
                                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="employee in result.employees" :key="employee.user_id" class="bg-white p-5 rounded-2xl shadow-md transition-all duration-200 ease-in-out border-l-4 border-blue-500 hover:shadow-lg">
                                        <div class="flex justify-between items-center cursor-pointer" @click="toggleDetails(employee)">
                                            <div class="flex items-center">
                                                <div class="flex-1">
                                                    <h4 class="text-xl font-bold text-gray-800">{{ employee.name }}</h4>
                                                    <span v-if="employee.points" class="text-sm font-semibold text-gray-600">{{ employee.points }} pts</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-bold text-green-600">{{ formatCurrency(employee.awards.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0)) }}</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" :class="{'rotate-180': employee.showDetails}" class="h-5 w-5 text-gray-400 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-show="employee.showDetails" class="mt-4 border-t border-gray-200 pt-4 space-y-2">
                                            <div v-for="award in employee.awards" :key="award.award" class="flex justify-between items-center text-sm">
                                                <span class="font-medium text-gray-700">{{ award.award }}</span>
                                                <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contractors List Section -->
                            <div class="space-y-4">
                                <h3 class="text-2xl font-bold text-gray-800">Contractor Bonuses</h3>
                                <div v-if="result.contractors?.length === 0" class="text-gray-600 p-4 border rounded-lg bg-gray-50">No contractor bonuses were calculated for this period.</div>
                                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="contractor in result.contractors" :key="contractor.user_id" class="bg-white p-5 rounded-2xl shadow-md transition-all duration-200 ease-in-out border-l-4 border-teal-500 hover:shadow-lg">
                                        <div class="flex justify-between items-center cursor-pointer" @click="toggleDetails(contractor)">
                                            <div class="flex items-center">
                                                <div class="flex-1">
                                                    <h4 class="text-xl font-bold text-gray-800">{{ contractor.name }}</h4>
                                                    <span v-if="contractor.points" class="text-sm font-semibold text-gray-600">{{ contractor.points }} pts</span>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-bold text-green-600">{{ formatCurrency(contractor.awards.reduce((acc, curr) => acc + (Number(curr.amount) || 0), 0)) }}</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" :class="{'rotate-180': contractor.showDetails}" class="h-5 w-5 text-gray-400 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </div>
                                        <div v-show="contractor.showDetails" class="mt-4 border-t border-gray-200 pt-4 space-y-2">
                                            <div v-for="award in contractor.awards" :key="award.award" class="flex flex-col items-start text-sm">
                                                <div class="flex justify-between w-full items-center">
                                                    <span class="font-medium text-gray-700">{{ award.award }}</span>
                                                    <span class="font-bold text-green-600">{{ formatCurrency(award.amount) }}</span>
                                                </div>
                                                <p v-if="award.details" class="text-xs text-gray-500 mt-1">{{ award.details }}</p>
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
