<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import { UserIcon, CalendarIcon, ClockIcon, ExclamationTriangleIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';

const props = defineProps(); // No props passed from Inertia anymore

const reportData = ref([]);
const charts = ref({});
const users = ref([]);
const filters = ref({
    user_ids: [],
    date_start: '',
    date_end: '',
});
const loading = ref(false);

const selectedUserIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');

// Format seconds to HH:MM:SS
const formatDuration = (seconds) => {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h}h ${m}m ${s}s`;
};

// Expand/Collapse state for user cards
const expandedUsers = ref({});
const toggleUserExpand = (userId) => {
    expandedUsers.value[userId] = !expandedUsers.value[userId];
};

const fetchReport = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get('/api/productivity/report', {
            params: {
                user_ids: selectedUserIds.value.join(','),
                date_start: dateStart.value,
                date_end: dateEnd.value,
            }
        });
        
        reportData.value = response.data.reportData.details;
        charts.value = response.data.reportData.charts;
        users.value = response.data.users;
        
        // Update local filters if needed, or just keep input state
        if (!dateStart.value && response.data.filters.date_start) {
            dateStart.value = response.data.filters.date_start;
        }
        if (!dateEnd.value && response.data.filters.date_end) {
             dateEnd.value = response.data.filters.date_end;
        }

        // Auto-expand if single result
        if (reportData.value.length === 1) {
            expandedUsers.value[reportData.value[0].user_id] = true;
        }

    } catch (error) {
        console.error('Error fetching productivity report:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchReport();
});

const applyFilters = () => {
    fetchReport();
};

</script>

<template>
    <Head title="Productivity Report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Productivity Report</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Filters Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="col-span-1 md:col-span-2">
                             <label class="block text-sm font-medium text-gray-700 mb-1">Users</label>
                             <MultiSelectDropdown
                                 v-model="selectedUserIds"
                                 :options="users"
                                 :is-multi="true"
                                 placeholder="Select Users..."
                                 class="w-full"
                             />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input 
                                type="date" 
                                v-model="dateStart"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input 
                                type="date" 
                                v-model="dateEnd"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button 
                            @click="applyFilters" 
                            :disabled="loading"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        >
                            <span v-if="loading">Loading...</span>
                            <span v-else>Generate Report</span>
                        </button>
                    </div>
                </div>

                <!-- Charts Section -->
                <div v-if="reportData.length > 0 && charts.daily_trend" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Daily Trend -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 col-span-2">
                         <h3 class="text-lg font-medium text-gray-900 mb-4">Productivity Trend (Daily)</h3>
                         <ChartComponent 
                            :data="charts.daily_trend" 
                            type="line" 
                            chartId="dailyTrendChart"
                         />
                    </div>

                    <!-- Project Dist -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Time by Project</h3>
                        <ChartComponent 
                            :data="charts.project_dist" 
                            type="pie" 
                            chartId="projectDistChart"
                         />
                    </div>
                </div>

                <!-- Results -->
                <div v-if="reportData.length === 0 && !loading" class="text-center py-10 text-gray-500">
                    <ClockIcon class="h-12 w-12 mx-auto text-gray-300 mb-2" />
                    <p>No data found.</p>
                </div>
                
                <div v-if="loading" class="text-center py-10">
                    <p class="text-gray-500">Loading Report...</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="userReport in reportData" :key="userReport.user_id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        
                        <!-- User Header (Collapsible Trigger) -->
                        <div 
                            @click="toggleUserExpand(userReport.user_id)"
                            class="p-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center space-x-4">
                                <img :src="userReport.avatar" alt="" class="h-10 w-10 rounded-full bg-gray-200" />
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">{{ userReport.user_name }}</h3>
                                    <p class="text-sm text-gray-500">{{ userReport.tasks.length }} tasks worked on</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-indigo-600">{{ userReport.total_hours }} hrs</div>
                                    <div class="text-xs text-gray-500">Total Time</div>
                                </div>
                                <component :is="expandedUsers[userReport.user_id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400" />
                            </div>
                        </div>

                        <!-- Expanded Details -->
                        <div v-show="expandedUsers[userReport.user_id]" class="border-t border-gray-200 bg-gray-50 p-6 space-y-4">
                            <div v-if="userReport.tasks.length === 0" class="text-sm text-gray-500 italic">No tasks recorded in this period.</div>
                            
                            <div v-for="task in userReport.tasks" :key="task.task_id" class="bg-white border rounded-lg p-4 shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-medium text-indigo-600 hover:text-indigo-800">
                                            {{ task.task_name }}
                                        </h4>
                                        <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ task.project_name }}</span>
                                    </div>
                                    <span class="font-mono text-sm font-semibold text-gray-700 bg-gray-100 px-2 py-1 rounded">
                                        {{ formatDuration(task.total_seconds) }}
                                    </span>
                                </div>

                                <!-- Sessions Table -->
                                <div class="mt-3 overflow-x-auto">
                                    <table class="min-w-full text-xs text-left text-gray-500">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2">Start</th>
                                                <th class="px-3 py-2">End</th>
                                                <th class="px-3 py-2">Duration</th>
                                                <th class="px-3 py-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(session, idx) in task.sessions" :key="idx" class="border-b last:border-0 hover:bg-gray-50">
                                                <td class="px-3 py-2 font-mono">{{ session.start }}</td>
                                                <td class="px-3 py-2 font-mono">
                                                    {{ session.end }}
                                                    <span v-if="session.type === 'ongoing'" class="ml-1 text-green-600 font-bold">(Active)</span>
                                                </td>
                                                <td class="px-3 py-2 font-medium text-gray-900">
                                                    {{ formatDuration(session.duration_seconds) }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span v-if="session.type === 'auto_capped'" class="inline-flex items-center text-orange-600" title="System capped duration due to missing pause">
                                                        <ExclamationTriangleIcon class="h-4 w-4 mr-1" />
                                                        Capped
                                                    </span>
                                                    <span v-else-if="session.type === 'ongoing'" class="text-green-600">Running</span>
                                                    <span v-else class="text-gray-400">Recorded</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
