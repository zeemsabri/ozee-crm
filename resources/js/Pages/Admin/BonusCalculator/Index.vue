<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import FiltersForm from '@/Components/BonusCalculator/FiltersForm.vue';

// Chart.js library import
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const year = ref(currentYear);
const month = ref(currentMonth);
const loading = ref(false);
const error = ref('');
const result = ref(null);
const currentView = ref('dashboard'); // New state for view switching

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
            result.value = data;
        }
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to calculate bonuses.';
    } finally {
        loading.value = false;
    }
};

// Computed properties for the dashboard
const distributionPercentage = computed(() => {
    if (!result.value?.summary) return 0;
    return (result.value.summary.total_distributed_pkr / result.value.summary.total_budget_pkr) * 100;
});

const topUsers = computed(() => {
    if (!result.value?.users) return [];
    // Sort users by total_bonus_pkr in descending order
    return [...result.value.users]
        .sort((a, b) => b.total_bonus_pkr - a.total_bonus_pkr);
});

const getUserName = (userId) => {
    if (!result.value?.users) return 'Unknown User';
    const user = result.value.users.find(u => u.user_id === userId);
    return user ? user.name : 'Unknown User';
};

const projectBreakdown = computed(() => {
    if (!result.value?.awards_details) return [];
    const projects = {};
    result.value.awards_details.forEach(award => {
        if (award.award_id === 'project_performance_bonus') {
            award.recipients.forEach(recipient => {
                if (recipient.awards) {
                    recipient.awards.forEach(subAward => {
                        const projectName = subAward.project_details.project_name;
                        if (!projects[projectName]) {
                            projects[projectName] = { distributed_pkr: 0, recipients: [] };
                        }
                        projects[projectName].distributed_pkr += subAward.amount_pkr;
                        projects[projectName].recipients.push({
                            name: getUserName(recipient.user_id),
                            amount_pkr: subAward.amount_pkr
                        });
                    });
                }
            });
        }
    });
    return Object.entries(projects).map(([name, data]) => ({
        name,
        distributed_pkr: data.distributed_pkr,
        recipients: data.recipients
    })).sort((a, b) => b.distributed_pkr - a.distributed_pkr);
});

// Chart initialization and rendering logic
const renderCharts = () => {
    // We now make sure to only render charts if we're on the dashboard view
    if (!result.value || currentView.value !== 'dashboard') return;

    // Clear previous charts if they exist
    const poolAllocationCanvas = document.getElementById('poolAllocationChart');
    const poolDistributionCanvas = document.getElementById('poolDistributionChart');

    // Destroy existing charts to prevent them from stacking
    if (poolAllocationCanvas) {
        const existingChart = Chart.getChart(poolAllocationCanvas);
        if (existingChart) existingChart.destroy();
    }
    if (poolDistributionCanvas) {
        const existingChart = Chart.getChart(poolDistributionCanvas);
        if (existingChart) existingChart.destroy();
    }

    // Chart.js configuration for the donut chart showing pool allocation
    if (poolAllocationCanvas) {
        const poolAllocationCtx = poolAllocationCanvas.getContext('2d');
        const poolAllocatedData = Object.keys(result.value.summary.pools).map(key => result.value.summary.pools[key].allocated_pkr);
        const poolAllocationLabels = Object.keys(result.value.summary.pools).map(key => key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
        new Chart(poolAllocationCtx, {
            type: 'doughnut',
            data: {
                labels: poolAllocationLabels,
                datasets: [{
                    data: poolAllocatedData,
                    backgroundColor: ['#6366F1', '#F97316', '#A855F7'],
                    hoverBackgroundColor: ['#818cf8', '#fdba74', '#c084fc'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Fix for charts growing tall
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#6B7280', // Gray-500 for light theme
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((acc, curr) => acc + curr, 0);
                                const percentage = ((value / total) * 100).toFixed(2);
                                return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }


    // Chart.js configuration for the bar chart showing distributed vs allocated
    if (poolDistributionCanvas) {
        const poolDistributionCtx = poolDistributionCanvas.getContext('2d');
        const poolLabels = Object.keys(result.value.summary.pools).map(key => key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
        const distributedData = Object.values(result.value.summary.pools).map(pool => pool.distributed_pkr);
        const allocatedData = Object.values(result.value.summary.pools).map(pool => pool.allocated_pkr);
        new Chart(poolDistributionCtx, {
            type: 'bar',
            data: {
                labels: poolLabels,
                datasets: [{
                    label: 'Distributed',
                    data: distributedData,
                    backgroundColor: '#A855F7',
                    borderColor: '#8B5CF6',
                    borderWidth: 1,
                    borderRadius: 8,
                }, {
                    label: 'Allocated',
                    data: allocatedData,
                    backgroundColor: '#F97316',
                    borderColor: '#F97316',
                    borderWidth: 1,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Fix for charts growing tall
                scales: {
                    x: {
                        stacked: false,
                        grid: {
                            color: '#E5E7EB' // Gray-200 for light theme
                        },
                        ticks: {
                            color: '#6B7280' // Gray-500
                        }
                    },
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        grid: {
                            color: '#E5E7EB'
                        },
                        ticks: {
                            color: '#6B7280',
                            callback: (value) => formatCurrency(value)
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#6B7280',
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });
    }
};

// Watch for changes in the result data and re-render charts
watch([result, currentView], async ([newResult, newView]) => {
    // Only attempt to render charts if new data is available AND we are on the dashboard view.
    if (newResult && newView === 'dashboard') {
        // Use nextTick to ensure the canvas elements are in the DOM before we try to access them.
        await nextTick();
        renderCharts();
    }
});

// Initial calculation on component mount
onMounted(() => {
    calculate();
});

</script>

<template>
    <AuthenticatedLayout>
        <Head title="Bonus Calculator" />

        <div class="py-6 bg-gray-100 text-gray-900 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Controls Section -->
                        <FiltersForm
                            v-model:year="year"
                            v-model:month="month"
                            :year-options="yearOptions"
                            :month-options="monthOptions"
                            :loading="loading"
                            @calculate="calculate"
                        />

                        <!-- Main Dashboard Content -->
                        <div v-if="loading" class="text-center p-8">
                            <span class="text-lg text-gray-500">Loading dashboard data...</span>
                        </div>
                        <div v-else-if="error" class="text-center p-8 text-red-500">
                            <p>Error: {{ error }}</p>
                        </div>
                        <div v-else-if="result">
                            <!-- View Switcher -->
                            <div class="flex border-b border-gray-200 mb-8">
                                <button
                                    @click="currentView = 'dashboard'"
                                    :class="{'border-blue-500 text-blue-600': currentView === 'dashboard', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': currentView !== 'dashboard'}"
                                    class="py-4 px-6 font-semibold text-sm border-b-2 transition-colors duration-200"
                                >
                                    Summary Dashboard
                                </button>
                                <button
                                    @click="currentView = 'users'"
                                    :class="{'border-blue-500 text-blue-600': currentView === 'users', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': currentView !== 'users'}"
                                    class="py-4 px-6 font-semibold text-sm border-b-2 transition-colors duration-200"
                                >
                                    All Users
                                </button>
                                <button
                                    @click="currentView = 'awards'"
                                    :class="{'border-blue-500 text-blue-600': currentView === 'awards', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': currentView !== 'awards'}"
                                    class="py-4 px-6 font-semibold text-sm border-b-2 transition-colors duration-200"
                                >
                                    Awards Breakdown
                                </button>
                                <button
                                    @click="currentView = 'projects'"
                                    :class="{'border-blue-500 text-blue-600': currentView === 'projects', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': currentView !== 'projects'}"
                                    class="py-4 px-6 font-semibold text-sm border-b-2 transition-colors duration-200"
                                >
                                    Projects
                                </button>
                            </div>

                            <!-- Dashboard View -->
                            <div v-if="currentView === 'dashboard'">
                                <!-- Dashboard Header -->
                                <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12">
                                    <div class="mb-4 md:mb-0">
                                        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800">Bonus Distribution Dashboard</h1>
                                        <p class="text-lg text-gray-500 mt-2">Insights for {{ result.period }}</p>
                                    </div>
                                    <div class="bg-gray-100 p-4 md:p-6 rounded-2xl shadow-md border border-gray-200">
                                        <h2 class="text-xl font-semibold text-gray-700">Total Budget</h2>
                                        <p class="text-4xl font-bold text-green-600 mt-2">{{ formatCurrency(result.summary.total_budget_pkr) }} PKR</p>
                                    </div>
                                </header>

                                <!-- Summary Metrics -->
                                <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 hover:shadow-lg transition-all duration-200">
                                        <h3 class="text-lg font-semibold text-gray-700">Total Distributed</h3>
                                        <p class="text-3xl font-bold text-purple-600 mt-1">{{ formatCurrency(result.summary.total_distributed_pkr) }} PKR</p>
                                        <div class="w-full h-2 bg-gray-200 rounded-full mt-4 overflow-hidden">
                                            <div class="h-full bg-purple-500 rounded-full transition-all duration-500" :style="{ width: `${distributionPercentage}%` }"></div>
                                        </div>
                                        <p class="text-sm text-gray-500 mt-2">{{ distributionPercentage.toFixed(2) }}% of budget distributed</p>
                                    </div>

                                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 hover:shadow-lg transition-all duration-200">
                                        <h3 class="text-lg font-semibold text-gray-700">Employee Pool</h3>
                                        <p class="text-3xl font-bold text-amber-600 mt-1">{{ formatCurrency(result.summary.pools.employee.distributed_pkr) }} PKR</p>
                                        <p class="text-sm text-gray-500 mt-1">Distributed from {{ formatCurrency(result.summary.pools.employee.allocated_pkr) }} PKR allocated</p>
                                        <div class="w-full h-2 bg-gray-200 rounded-full mt-4 overflow-hidden">
                                            <div class="h-full bg-yellow-500 rounded-full transition-all duration-500" :style="{ width: `${(result.summary.pools.employee.distributed_pkr / result.summary.pools.employee.allocated_pkr * 100).toFixed(2)}%` }"></div>
                                        </div>
                                    </div>

                                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 hover:shadow-lg transition-all duration-200">
                                        <h3 class="text-lg font-semibold text-gray-700">Contractor Pool</h3>
                                        <p class="text-3xl font-bold text-orange-600 mt-1">{{ formatCurrency(result.summary.pools.contractor.distributed_pkr) }} PKR</p>
                                        <p class="text-sm text-gray-500 mt-1">Distributed from {{ formatCurrency(result.summary.pools.contractor.allocated_pkr) }} PKR allocated</p>
                                        <div class="w-full h-2 bg-gray-200 rounded-full mt-4 overflow-hidden">
                                            <div class="h-full bg-orange-500 rounded-full transition-all duration-500" :style="{ width: `${(result.summary.pools.contractor.distributed_pkr / result.summary.pools.contractor.allocated_pkr * 100).toFixed(2)}%` }"></div>
                                        </div>
                                    </div>
                                </section>

                                <!-- Main Charts Section -->
                                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-12">
                                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                                        <h3 class="text-2xl font-bold mb-4 text-gray-800">Bonus Pool Allocation</h3>
                                        <div class="h-80"> <!-- Fixed height container for the chart -->
                                            <canvas id="poolAllocationChart"></canvas>
                                        </div>
                                    </div>
                                    <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                                        <h3 class="text-2xl font-bold mb-4 text-gray-800">Distribution vs. Allocation</h3>
                                        <div class="h-80"> <!-- Fixed height container for the chart -->
                                            <canvas id="poolDistributionChart"></canvas>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <!-- All Users View (replaces old Top Performers) -->
                            <div v-if="currentView === 'users'">
                                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800 mb-8">All Users Performance</h1>
                                <div class="bg-white rounded-2xl shadow-md border border-gray-200 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Bonus</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Point Change (MoM)</th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="user in topUsers" :key="user.user_id">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ user.name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ user.user_type.charAt(0).toUpperCase() + user.user_type.slice(1) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-bold">{{ formatCurrency(user.total_bonus_pkr) }} PKR</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm" :class="{'text-green-600': user.point_increase_from_last_month > 0, 'text-red-600': user.point_increase_from_last_month < 0, 'text-gray-500': user.point_increase_from_last_month === 0}">
                                                    <span class="flex items-center">
                                                        <span v-if="user.point_increase_from_last_month > 0" class="mr-1">▲</span>
                                                        <span v-else-if="user.point_increase_from_last_month < 0" class="mr-1">▼</span>
                                                        <span>{{ user.point_increase_from_last_month > 0 ? '+' : '' }}{{ user.point_increase_from_last_month }} points</span>
                                                    </span>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Awards Breakdown View -->
                            <div v-if="currentView === 'awards'">
                                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800 mb-8">Awards Breakdown</h1>
                                <div class="space-y-6">
                                    <div v-for="award in result.awards_details" :key="award.award_id" class="p-6 bg-white rounded-2xl shadow-md border border-gray-200">
                                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                                            <h4 class="text-2xl font-semibold text-blue-600">{{ award.award_name }}</h4>
                                            <span class="text-md text-gray-500 mt-2 md:mt-0">{{ award.user_type }}</span>
                                        </div>
                                        <p class="text-md text-gray-600">Distributed: <span class="font-bold text-purple-600">{{ formatCurrency(award.distributed_pkr) }} PKR</span></p>
                                        <div class="mt-6 space-y-4">
                                            <div v-for="recipient in award.recipients" :key="recipient.user_id" class="p-4 bg-gray-100 rounded-lg border border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-lg font-medium text-gray-800">{{ getUserName(recipient.user_id) }}</p>
                                                    <p class="text-lg font-semibold text-green-600">
                                                        <!-- Corrected calculation for Project Performance Bonus, otherwise use the single amount -->
                                                        {{
                                                            formatCurrency(recipient.awards
                                                                ? recipient.awards.reduce((sum, subAward) => sum + subAward.amount_pkr, 0)
                                                                : recipient.amount_pkr
                                                            )
                                                        }} PKR
                                                    </p>
                                                </div>
                                                <p class="text-sm text-gray-500 mt-1">{{ recipient.award_title }}</p>
                                                <ul v-if="recipient.awards && recipient.awards.length" class="mt-2 text-sm text-gray-500 list-disc list-inside">
                                                    <li v-for="subAward in recipient.awards" :key="subAward.project_details.project_name">
                                                        {{ subAward.award_title }} for '{{ subAward.project_details.project_name }}' - {{ formatCurrency(subAward.amount_pkr) }} PKR
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Projects View -->
                            <div v-if="currentView === 'projects'">
                                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-800 mb-8">Project Performance Breakdown</h1>
                                <div class="space-y-6">
                                    <div v-for="project in projectBreakdown" :key="project.name" class="p-6 bg-white rounded-2xl shadow-md border border-gray-200">
                                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                                            <h4 class="text-2xl font-semibold text-blue-600">{{ project.name }}</h4>
                                            <p class="text-md text-gray-600 mt-2 md:mt-0">Total Bonus: <span class="font-bold text-green-600">{{ formatCurrency(project.distributed_pkr) }} PKR</span></p>
                                        </div>
                                        <div class="mt-4 space-y-2">
                                            <div v-for="recipient in project.recipients" :key="recipient.name" class="flex justify-between items-center p-3 bg-gray-100 rounded-md">
                                                <p class="text-md text-gray-700">{{ recipient.name }}</p>
                                                <p class="text-md font-semibold text-green-600">{{ formatCurrency(recipient.amount_pkr) }} PKR</p>
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

<style>
/*
    You can add custom styles here if needed.
    All Tailwind CSS classes are already included via AuthenticatedLayout.
*/
</style>
