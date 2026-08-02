<template>
    <AppLayout title="CEO Financial Dashboard">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Financial Command Center
                </h2>
                <!-- Date Filter -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 bg-white rounded-md shadow-sm px-3 py-2 border border-gray-200">
                        <span class="text-sm text-gray-500 font-medium">Filter:</span>
                        <input type="date" v-model="form.start_date" class="border-none bg-transparent text-sm font-medium focus:ring-0 p-0 text-gray-700 w-32" />
                        <span class="text-gray-400">to</span>
                        <input type="date" v-model="form.end_date" class="border-none bg-transparent text-sm font-medium focus:ring-0 p-0 text-gray-700 w-32" />
                    </div>
                    <button @click="applyFilters" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors shadow-sm">
                        Apply
                    </button>
                    <button @click="setPreset('last_week')" class="text-sm font-medium text-gray-500 hover:text-gray-700">Last Week</button>
                    <button @click="setPreset('this_month')" class="text-sm font-medium text-gray-500 hover:text-gray-700">This Month</button>
                    <button @click="setPreset('ytd')" class="text-sm font-medium text-gray-500 hover:text-gray-700">YTD</button>
                </div>
            </div>
        </template>

        <LiveFxTicker :rates="currencyRates" />

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Top Banner: Accrual Overview -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="border-b md:border-b-0 md:border-r border-slate-700 pb-6 md:pb-0 pr-0 md:pr-6">
                            <p class="text-slate-400 text-sm font-medium mb-1 uppercase tracking-wider">Total Invoiced (Accrual)</p>
                            <p class="text-3xl font-bold text-white">A$ {{ formatCurrency(dashboardData.overview.total_invoiced_revenue_aud) }}</p>
                        </div>
                        <div class="border-b md:border-b-0 md:border-r border-slate-700 pb-6 md:pb-0 px-0 md:px-6">
                            <p class="text-slate-400 text-sm font-medium mb-1 uppercase tracking-wider">Total Bills Logged (Accrual)</p>
                            <p class="text-3xl font-bold text-white">A$ {{ formatCurrency(dashboardData.overview.total_bills_logged_aud) }}</p>
                        </div>
                        <div class="pl-0 md:pl-6">
                            <p class="text-slate-400 text-sm font-medium mb-1 uppercase tracking-wider">Net Profit (Accrual)</p>
                            <p class="text-3xl font-bold" :class="dashboardData.overview.net_accrual_profit_aud >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                                A$ {{ formatCurrency(dashboardData.overview.net_accrual_profit_aud) }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button 
                            @click="activeTab = 'projects'"
                            :class="[
                                activeTab === 'projects' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium'
                            ]"
                        >
                            Project Health Grid
                        </button>
                        <button 
                            @click="activeTab = 'cashflow'"
                            :class="[
                                activeTab === 'cashflow' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium'
                            ]"
                        >
                            Cash Management Timeline
                        </button>
                    </nav>
                </div>

                <!-- Project Health Tab -->
                <div v-show="activeTab === 'projects'">
                    <div v-if="dashboardData.projects.length === 0" class="bg-white rounded-lg p-12 text-center text-gray-500 shadow-sm border border-gray-100">
                        No financial activity found for the selected date range.
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <ProjectHealthCard 
                            v-for="projectData in dashboardData.projects" 
                            :key="projectData.project.id" 
                            :data="projectData" 
                        />
                    </div>
                </div>

                <!-- Cash Flow Tab -->
                <div v-show="activeTab === 'cashflow'">
                    <CashManagementTimeline :data="timelineData" @addInstruction="openInstructionModal" />
                </div>
            </div>
        </div>

        <!-- Instruction Modal -->
        <InstructionModal
            :show="showInstructionModal"
            :type="instructionType"
            :id="instructionId"
            :users="users"
            @close="showInstructionModal = false"
        />
        
        <!-- Flash Messages -->
        <div v-if="$page.props.flash.success" class="fixed bottom-4 right-4 bg-green-50 text-green-800 p-4 rounded-lg shadow-lg border border-green-200 z-50">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash.error" class="fixed bottom-4 right-4 bg-red-50 text-red-800 p-4 rounded-lg shadow-lg border border-red-200 z-50">
            {{ $page.props.flash.error }}
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LiveFxTicker from '@/Components/Financial/LiveFxTicker.vue';
import ProjectHealthCard from '@/Components/Financial/ProjectHealthCard.vue';
import CashManagementTimeline from '@/Components/Financial/CashManagementTimeline.vue';
import InstructionModal from '@/Components/Financial/InstructionModal.vue';

const props = defineProps({
    dashboardData: Object,
    timelineData: Object,
    currencyRates: Object,
    filters: Object,
    users: Array,
});

const activeTab = ref('projects');
const showInstructionModal = ref(false);
const instructionType = ref('');
const instructionId = ref(null);

const openInstructionModal = (item) => {
    instructionType.value = item.type;
    instructionId.value = item.id;
    showInstructionModal.value = true;
};

const form = useForm({
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
});

const applyFilters = () => {
    router.get(route('dashboard.ceo-financial'), {
        start_date: form.start_date,
        end_date: form.end_date
    }, { preserveState: true });
};

const setPreset = (preset) => {
    const today = new Date();
    let start = new Date();
    let end = new Date();

    if (preset === 'last_week') {
        // Previous Saturday to Last Friday
        const dayOfWeek = today.getDay(); // 0 is Sunday
        const daysSinceLastFriday = (dayOfWeek + 2) % 7;
        end.setDate(today.getDate() - daysSinceLastFriday);
        start = new Date(end);
        start.setDate(end.getDate() - 6);
    } else if (preset === 'this_month') {
        start = new Date(today.getFullYear(), today.getMonth(), 1);
        end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    } else if (preset === 'ytd') {
        start = new Date(today.getFullYear(), 0, 1);
        end = today;
    }

    form.start_date = start.toISOString().split('T')[0];
    form.end_date = end.toISOString().split('T')[0];
    applyFilters();
};

const formatCurrency = (val) => {
    return Number(val).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>
