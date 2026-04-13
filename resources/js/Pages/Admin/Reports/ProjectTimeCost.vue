<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import { CurrencyDollarIcon, ClockIcon, BriefcaseIcon, UserGroupIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const reportData = ref([]);
const projectsList = ref([]);
const globalStats = ref({});
const loading = ref(false);

const selectedProjectId = ref(null);
const dateStart = ref('');
const dateEnd = ref('');
const includeArchived = ref(false);

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/admin/api/project-time-cost', {
            params: { 
                project_id: selectedProjectId.value, 
                date_start: dateStart.value, 
                date_end: dateEnd.value,
                include_archived: includeArchived.value 
            }
        });
        reportData.value = res.data.reportData;
        projectsList.value = res.data.projects;
        globalStats.value = res.data.global_stats;
    } catch (e) { 
        console.error(e); 
    } finally { 
        loading.value = false; 
    }
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(amount || 0);
};

const formatHours = (minutes) => {
    return (minutes / 60).toFixed(2) + ' hrs';
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-AU', { day: '2-digit', month: 'short', year: 'numeric' });
};

onMounted(() => {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];
    fetchReport();
});

</script>

<template>
    <Head title="Project Time & Cost Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div>
                    <h2 class="font-black text-2xl text-gray-900 tracking-tight">Project Time & Cost Report</h2>
                    <p class="text-sm text-gray-500 font-medium">Analyze total time spent, income, and expenses per project</p>
                </div>
                <div class="flex gap-3">
                    <button @click="fetchReport" :disabled="loading" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 transition disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4 mr-2" :class="{'animate-spin': loading}" /> Refresh
                    </button>
                    <button @click="window.print()" class="flex items-center px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                        PDF
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 bg-gray-50 min-h-screen print:bg-white print:py-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 1. FILTERS -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Select Project</label>
                            <select v-model="selectedProjectId" class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-bold text-gray-700">
                                <option :value="null">All Projects</option>
                                <option v-for="p in projectsList" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Range Start</label>
                            <input type="date" v-model="dateStart" class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-bold text-gray-700" />
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Range End</label>
                            <input type="date" v-model="dateEnd" class="w-full rounded-xl border-gray-200 text-sm focus:ring-indigo-500 font-bold text-gray-700" />
                        </div>
                        <div class="flex items-center h-full mb-2">
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input type="checkbox" v-model="includeArchived" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-5 w-5" />
                                <span class="text-sm font-bold text-gray-700">Fetch Archived</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. GLOBAL METRICS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6" v-if="globalStats">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Time Spent</p>
                            <h3 class="text-2xl font-black text-gray-900 mt-1">{{ formatHours(globalStats.total_minutes) }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600"><ClockIcon class="h-6 w-6"/></div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Income</p>
                            <h3 class="text-2xl font-black text-green-600 mt-1">{{ formatCurrency(globalStats.total_income) }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-600"><CurrencyDollarIcon class="h-6 w-6"/></div>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Expense</p>
                            <h3 class="text-2xl font-black text-red-600 mt-1">{{ formatCurrency(globalStats.total_expense) }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-2xl bg-red-50 flex items-center justify-center text-red-600"><CurrencyDollarIcon class="h-6 w-6"/></div>
                    </div>
                </div>

                <!-- 3. PROJECTS LIST -->
                <div class="space-y-6">
                    <div v-if="reportData.length === 0 && !loading" class="text-center py-12 bg-white rounded-2xl shadow-sm border border-gray-200">
                        <p class="text-gray-500 font-bold">No projects matched the criteria.</p>
                    </div>

                    <div v-for="project in reportData" :key="project.id" class="bg-white rounded-[2rem] border border-gray-200 shadow-sm overflow-hidden mb-8 transition hover:shadow-md">
                        <!-- Project Header -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between border-b border-gray-100 bg-gray-50/50">
                            <div class="flex items-center space-x-5">
                                <div class="h-14 w-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 shadow-inner">
                                    <BriefcaseIcon class="h-8 w-8" />
                                </div>
                                <div>
                                    <h4 class="font-black text-xl text-gray-900 flex items-center gap-2">
                                        {{ project.name }}
                                        <span v-if="project.is_archived" class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest bg-red-100 text-red-600">Archived</span>
                                    </h4>
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                            Created: {{ formatDate(project.created_at) }}
                                        </span>
                                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                            Active Duration: {{ project.active_duration_days }} Days
                                        </span>
                                        <span v-if="project.is_archived" class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                            Archived: {{ formatDate(project.deleted_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Metrics Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-0 border-b border-gray-100">
                            <div class="p-6 border-r border-gray-100 last:border-r-0">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Time</p>
                                <h3 class="text-xl font-black text-gray-900 mt-1">{{ formatHours(project.time_stats.total_minutes) }}</h3>
                            </div>
                            <div class="p-6 border-r border-gray-100 last:border-r-0">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Income</p>
                                <h3 class="text-xl font-black text-green-600 mt-1">{{ formatCurrency(project.cost_stats.total_income) }}</h3>
                            </div>
                            <div class="p-6">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Expense</p>
                                <h3 class="text-xl font-black text-red-600 mt-1">{{ formatCurrency(project.cost_stats.total_expense) }}</h3>
                            </div>
                        </div>

                        <!-- User Breakdown -->
                        <div class="p-6">
                            <h5 class="text-[10px] font-black uppercase tracking-widest text-gray-500 flex items-center gap-2 mb-4">
                                <UserGroupIcon class="h-4 w-4" /> User Breakdown
                            </h5>
                            
                            <div v-if="project.user_breakdown && project.user_breakdown.length > 0" class="overflow-x-auto rounded-xl border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-black text-gray-500 uppercase tracking-widest">User</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-black text-gray-500 uppercase tracking-widest">Active Time</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-black text-gray-500 uppercase tracking-widest">Idle Time</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-black text-gray-500 uppercase tracking-widest">Total Time</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="user in project.user_breakdown" :key="user.user_id" class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="h-8 w-8 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold mr-3 text-xs">
                                                        {{ user.user_name ? user.user_name.substring(0, 2).toUpperCase() : '?' }}
                                                    </div>
                                                    <div class="text-sm font-bold text-gray-900">{{ user.user_name || 'Unknown' }}</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">{{ formatHours(user.active_minutes) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 text-gray-400">{{ formatHours(user.idle_minutes) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">{{ formatHours(user.total_minutes) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-6 bg-gray-50 rounded-xl border border-gray-100 text-gray-500 text-sm font-bold">
                                No user activity logged for this period.
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .bg-gray-50 { background: white !important; }
    .shadow-sm, .rounded-xl, .rounded-\[2rem\] { box-shadow: none !important; border: 1px solid #eee !important; border-radius: 1rem !important; }
}
</style>
