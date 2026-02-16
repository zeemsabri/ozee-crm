<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed, onUnmounted } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import {
    ClockIcon,
    ArrowTrendingUpIcon,
    CalendarIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    GlobeAltIcon,
    DevicePhoneMobileIcon,
    ComputerDesktopIcon
} from '@heroicons/vue/24/outline';

const activities = ref([]);
const stats = ref({
    total_hours: 0,
    total_minutes: 0,
    total_events: 0,
    top_domain: '-',
    top_domain_time: 0
});
const productivity = ref({
    score: 0,
    productive_time: 0,
    unproductive_time: 0,
    idle_time: 0,
    social_media_time: 0,
    active_time: 0
});
const charts = ref({
    domain_dist: [],
    hourly_trend: [],
    category_breakdown: [],
    idle_breakdown: []
});
const users = ref([]);
const loading = ref(false);
const openCategoryDropdown = ref(null);

const categoryOptions = [
    { value: 'productive', label: 'Productive' },
    { value: 'development', label: 'Development' },
    { value: 'communication', label: 'Communication' },
    { value: 'social_media', label: 'Social Media' },
    { value: 'neutral', label: 'Neutral' },
    { value: 'unproductive', label: 'Unproductive' }
];

const selectedUserIds = ref([]);
const dateStart = ref(new Date().toISOString().split('T')[0]);
const dateEnd = ref(new Date().toISOString().split('T')[0]);

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/api/activity-report', {
            params: {
                user_ids: selectedUserIds.value.join(','),
                date_start: dateStart.value,
                date_end: dateEnd.value,
            }
        });
        activities.value = res.data.activities;
        stats.value = res.data.stats;
        productivity.value = res.data.productivity || productivity.value;
        charts.value = res.data.charts;
        users.value = res.data.users;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const formatDuration = (totalSeconds) => {
    if (!totalSeconds) return '0s';
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
};

const formatTime = (dateString) => {
    return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const toggleCategoryDropdown = (activityId) => {
    openCategoryDropdown.value = openCategoryDropdown.value === activityId ? null : activityId;
};

const updateActivityCategory = async (activityId, newCategory) => {
    try {
        const res = await window.axios.patch(`/api/activities/${activityId}/category`, {
            category: newCategory
        });
        
        // Update the activity in the local state
        const activity = activities.value.find(a => a.id === activityId);
        if (activity) {
            activity.category = newCategory;
        }
        
        // Close dropdown
        openCategoryDropdown.value = null;
        
        // Refresh the report to update charts and stats
        await fetchReport();
    } catch (error) {
        console.error('Failed to update category:', error);
        alert('Failed to update category. Please try again.');
    }
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest('.relative.inline-block')) {
        openCategoryDropdown.value = null;
    }
};

onMounted(() => {
    fetchReport();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
    <Head title="Activity Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Activity Dashboard</h2>
                <div class="text-sm text-gray-500">Productivity telemetry & reporting</div>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- Filter Panel -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Team Members</label>
                            <MultiSelectDropdown v-model="selectedUserIds" :options="users" :is-multi="true" placeholder="All Users" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">From</label>
                            <input type="date" v-model="dateStart" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">To</label>
                            <input type="date" v-model="dateEnd" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <button @click="fetchReport" :disabled="loading" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                            {{ loading ? 'Filtering...' : 'Filter' }}
                        </button>
                    </div>
                </div>

                <!-- KPI Scorecards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Productivity Score -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <ArrowTrendingUpIcon class="h-8 w-8 opacity-80" :class="{
                                'text-green-500': productivity.score >= 70,
                                'text-yellow-500': productivity.score >= 50 && productivity.score < 70,
                                'text-red-500': productivity.score < 50
                            }" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Productivity Score</span>
                        </div>
                        <div class="text-3xl font-black" :class="{
                            'text-green-600': productivity.score >= 70,
                            'text-yellow-600': productivity.score >= 50 && productivity.score < 70,
                            'text-red-600': productivity.score < 50
                        }">{{ productivity.score }}%</div>
                        <p class="text-[10px] text-gray-400 mt-2">Weighted productivity index</p>
                    </div>

                    <!-- Total Active Time -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <ClockIcon class="h-8 w-8 text-blue-500 opacity-80" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Active Time</span>
                        </div>
                        <div class="text-3xl font-black text-blue-600">
                            {{ stats.total_hours > 0 ? stats.total_hours + ' hrs' : stats.total_minutes + ' mins' }}
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">Aggregated active sessions</p>
                    </div>
                    
                    <!-- Idle Time -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <ClockIcon class="h-8 w-8 text-amber-500 opacity-80" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Idle Time</span>
                        </div>
                        <div class="text-3xl font-black text-amber-600">{{ productivity.idle_time }} mins</div>
                        <p class="text-[10px] text-gray-400 mt-2">Away from keyboard</p>
                    </div>

                    <!-- Top Domain -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <GlobeAltIcon class="h-8 w-8 text-indigo-500 opacity-80" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Top Domain</span>
                        </div>
                        <div class="text-2xl font-black text-gray-900 truncate" :title="stats.top_domain">{{ stats.top_domain }}</div>
                        <p class="text-[10px] text-gray-400 mt-2">{{ stats.top_domain_time }} mins total</p>
                    </div>

                    <!-- Social Media Usage -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <DevicePhoneMobileIcon class="h-8 w-8 text-pink-500 opacity-80" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Social Media</span>
                        </div>
                        <div class="text-3xl font-black text-pink-600">{{ productivity.social_media_time }} mins</div>
                        <p class="text-[10px] text-gray-400 mt-2">Facebook, Instagram, etc.</p>
                    </div>

                    <!-- Activity Sessions -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm transition-all hover:shadow-md">
                        <div class="flex items-center justify-between mb-2">
                            <ListBulletIcon class="h-8 w-8 text-purple-500 opacity-80" />
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Activity Sessions</span>
                        </div>
                        <div class="text-3xl font-black text-gray-900">{{ stats.total_events }}</div>
                        <p class="text-[10px] text-gray-400 mt-2">Continuous domain blocks</p>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category Breakdown -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center">
                            <ArrowTrendingUpIcon class="h-4 w-4 mr-2 text-green-600" /> Productivity Breakdown
                        </h3>
                        <div class="h-[300px]">
                             <ChartComponent :data="{
                                labels: charts.category_breakdown.map(d => d.label),
                                datasets: [{
                                    data: charts.category_breakdown.map(d => d.duration),
                                    backgroundColor: charts.category_breakdown.map(d => d.color)
                                }]
                             }" type="pie" height="300" />
                        </div>
                        <div class="mt-4 space-y-2">
                            <div v-for="cat in charts.category_breakdown" :key="cat.category" class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></div>
                                    <span class="font-medium">{{ cat.label }}</span>
                                </div>
                                <span class="text-gray-600">{{ cat.duration }} mins ({{ cat.percentage }}%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Idle vs Active Timeline -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center">
                            <CalendarIcon class="h-4 w-4 mr-2 text-blue-600" /> Idle vs Active Timeline
                        </h3>
                        <div class="h-[300px]">
                            <ChartComponent :data="{
                                labels: charts.idle_breakdown.map(d => d.hour),
                                datasets: [
                                    {
                                        label: 'Active',
                                        data: charts.idle_breakdown.map(d => d.active),
                                        backgroundColor: '#10b981',
                                        borderRadius: 4
                                    },
                                    {
                                        label: 'Idle',
                                        data: charts.idle_breakdown.map(d => d.idle),
                                        backgroundColor: '#f59e0b',
                                        borderRadius: 4
                                    }
                                ]
                            }" type="bar" height="300" y-label="Minutes" :options="{ scales: { x: { stacked: true }, y: { stacked: true } } }" />
                        </div>
                    </div>

                    <!-- Domain Distribution -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center">
                            <GlobeAltIcon class="h-4 w-4 mr-2 text-indigo-600" /> Time Spent by Domain (Minutes)
                        </h3>
                        <div class="h-[300px]">
                             <ChartComponent :data="{
                                labels: charts.domain_dist.map(d => d.label),
                                datasets: [{
                                    data: charts.domain_dist.map(d => d.value),
                                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#6366f1', '#ec4899', '#f43f5e', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316']
                                }]
                             }" type="pie" height="300" />
                        </div>
                    </div>

                    <!-- Hourly Trend -->
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center">
                            <CalendarIcon class="h-4 w-4 mr-2 text-blue-600" /> Activity Timeline (Minutes Active)
                        </h3>
                        <div class="h-[300px]">
                            <ChartComponent :data="{
                                labels: charts.hourly_trend.map(d => d.label),
                                datasets: [{
                                    label: 'Minutes Active',
                                    data: charts.hourly_trend.map(d => d.value),
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 4
                                }]
                            }" type="bar" height="300" y-label="Minutes" />
                        </div>
                    </div>
                </div>

                <!-- Activity Log Table -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900">Activity Log & Durations</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-[10px] uppercase font-bold text-gray-500 tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Time</th>
                                    <th class="px-6 py-3">User</th>
                                    <th class="px-6 py-3">Domain</th>
                                    <th class="px-6 py-3">Category</th>
                                    <th class="px-6 py-3">Page Title</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <th class="px-6 py-3">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="activity in activities" :key="activity.id" class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-500 font-mono text-xs" :title="activity.local_time">
                                        {{ activity.local_time_formatted || formatTime(activity.recorded_at) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-3">
                                            <div v-if="activity.user?.avatar || activity.user?.avatar_url" class="h-6 w-6 rounded-full overflow-hidden bg-gray-100">
                                                <img :src="activity.user.avatar || activity.user.avatar_url" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center text-[10px] font-bold text-indigo-600 uppercase">
                                                {{ activity.user?.name?.charAt(0) || '?' }}
                                            </div>
                                            <span class="font-medium text-gray-900 text-xs">{{ activity.user?.name || 'Unknown' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-blue-50 text-blue-700 text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-tight">
                                            {{ activity.domain }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 relative">
                                        <div class="relative inline-block">
                                            <button
                                                v-if="activity.category"
                                                @click="toggleCategoryDropdown(activity.id)"
                                                class="text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-tight cursor-pointer hover:opacity-80 transition-opacity"
                                                :class="{
                                                    'bg-green-100 text-green-700': activity.category === 'productive' || activity.category === 'development',
                                                    'bg-purple-100 text-purple-700': activity.category === 'communication',
                                                    'bg-pink-100 text-pink-700': activity.category === 'social_media',
                                                    'bg-gray-100 text-gray-700': activity.category === 'neutral',
                                                    'bg-red-100 text-red-700': activity.category === 'unproductive'
                                                }"
                                            >
                                                {{ activity.category }} ▾
                                            </button>
                                            <span v-else class="text-gray-400 text-xs">-</span>
                                            
                                            <!-- Dropdown Menu -->
                                            <div 
                                                v-if="openCategoryDropdown === activity.id"
                                                class="absolute z-50 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg"
                                            >
                                                <button
                                                    v-for="cat in categoryOptions"
                                                    :key="cat.value"
                                                    @click="updateActivityCategory(activity.id, cat.value)"
                                                    class="w-full text-left px-3 py-2 text-xs hover:bg-gray-50 first:rounded-t-lg last:rounded-b-lg transition-colors"
                                                    :class="{
                                                        'bg-green-50': cat.value === 'productive' || cat.value === 'development',
                                                        'bg-purple-50': cat.value === 'communication',
                                                        'bg-pink-50': cat.value === 'social_media',
                                                        'bg-gray-50': cat.value === 'neutral',
                                                        'bg-red-50': cat.value === 'unproductive'
                                                    }"
                                                >
                                                    {{ cat.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 max-w-xs truncate text-xs text-gray-600" :title="activity.title">
                                        <div class="flex items-center gap-2">
                                            <span v-if="activity.is_incognito" class="text-gray-400" title="Incognito Mode">🕶️</span>
                                            <span>{{ activity.title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span 
                                            :class="{
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-tight': true,
                                                'bg-green-100 text-green-700': activity.idle_state === 'active',
                                                'bg-amber-100 text-amber-700': activity.idle_state === 'idle',
                                                'bg-gray-100 text-gray-700': !activity.idle_state || activity.idle_state === 'locked' || activity.idle_state === 'unknown'
                                            }"
                                        >
                                            {{ activity.idle_state || 'unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-emerald-600 font-black text-xs">
                                            {{ formatDuration(activity.duration) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="activities.length === 0 && !loading">
                                    <td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">
                                        No activity data found for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
