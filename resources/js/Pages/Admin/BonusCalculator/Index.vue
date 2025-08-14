<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import TeamMetricsChart from '@/Components/BonusCalculator/TeamMetricsChart.vue'; // The new chart component
import FiltersForm from '@/Components/BonusCalculator/FiltersForm.vue';
import SummaryStats from '@/Components/BonusCalculator/SummaryStats.vue';
import EmployeesList from '@/Components/BonusCalculator/EmployeesList.vue';
import ContractorsList from '@/Components/BonusCalculator/ContractorsList.vue';

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
                        <FiltersForm
                            v-model:year="year"
                            v-model:month="month"
                            :year-options="yearOptions"
                            :month-options="monthOptions"
                            :loading="loading"
                            @calculate="calculate"
                        />

                        <div v-if="error" class="mt-6 p-3 bg-red-50 text-red-700 rounded">{{ error }}</div>

                        <div v-if="result && !error" class="mt-6 space-y-8">
                            <!-- Overall Summary Section -->
                            <SummaryStats
                                :result="result"
                                :employee-total-bonus="employeeTotalBonus"
                                :employee-total-points="employeeTotalPoints"
                                :employee-cost-per-point="employeeCostPerPoint"
                                :contractor-total-bonus="contractorTotalBonus"
                                :contractor-project-breakdown="contractorProjectBreakdown"
                                :format-currency="formatCurrency"
                            />

                            <!-- Team Metrics Section (with the new chart component) -->
                            <div class="space-y-4">
                                <h3 class="text-2xl font-bold text-gray-800">Team Metrics</h3>
                                <TeamMetricsChart v-if="result.team_metrics" :metrics="result.team_metrics" />
                                <div v-else class="text-gray-600 p-4 border rounded-lg bg-gray-50">
                                    No team metrics available for this period.
                                </div>
                            </div>

                            <!-- Employees List Section -->
                            <EmployeesList :employees="result.employees" :format-currency="formatCurrency" />

                            <!-- Contractors List Section -->
                            <ContractorsList :contractors="result.contractors" :format-currency="formatCurrency" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
