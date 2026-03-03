<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Search, Clock, Activity, User as UserIcon, Calendar, CheckCircle, PauseCircle, PlayCircle, XCircle, RefreshCw } from 'lucide-vue-next';
import moment from 'moment';

const props = defineProps({
    users: Array
});

const searchTerm = ref('');
const selectedUser = ref(null);
const showLogModal = ref(false);
const dateRange = ref({
    start: moment().format('YYYY-MM-DD'),
    end: moment().format('YYYY-MM-DD')
});
const userLogs = ref([]);
const loadingLogs = ref(false);

const filteredUsers = computed(() => {
    if (!searchTerm.value) return props.users;
    const term = searchTerm.value.toLowerCase();
    return props.users.filter(u => 
        u.name.toLowerCase().includes(term) || 
        (u.active_task && u.active_task.name.toLowerCase().includes(term))
    );
});

const onlineUsers = computed(() => filteredUsers.value.filter(u => u.is_online));
const offlineUsers = computed(() => filteredUsers.value.filter(u => !u.is_online));

const openLogModal = async (user) => {
    selectedUser.value = user;
    showLogModal.value = true;
    fetchLogs();
};

const fetchLogs = async () => {
    if (!selectedUser.value) return;
    loadingLogs.value = true;
    try {
        const response = await window.axios.get(route('admin.live-status.logs', selectedUser.value.id), {
            params: {
                start_date: dateRange.value.start,
                end_date: dateRange.value.end
            }
        });
        userLogs.value = response.data.logs;
    } catch (error) {
        console.error('Error fetching logs:', error);
    } finally {
        loadingLogs.value = false;
    }
};

const formatTimestamp = (timestamp) => {
    return moment(timestamp).format('MMM D, h:mm:ss A');
};

const getStatusColor = (status) => {
    return status === 'online' ? 'text-green-500 bg-green-50' : 'text-gray-500 bg-gray-50';
};

const calculateDuration = (startTime) => {
    if (!startTime || !currentTime.value) return '0s'; 
    const start = moment.utc(startTime);
    const now = moment.utc();
    const duration = moment.duration(now.diff(start));
    
    const hours = Math.floor(duration.asHours());
    const minutes = duration.minutes();
    const seconds = duration.seconds();
    
    let parts = [];
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0 || hours > 0) parts.push(`${minutes}m`);
    parts.push(`${seconds}s`);
    
    return parts.join(' ');
};

const currentTime = ref(moment().valueOf());
const refreshing = ref(false);
const refreshData = () => {
    refreshing.value = true;
    router.reload({ 
        only: ['users'],
        onFinish: () => {
            refreshing.value = false;
        }
    });
};

let refreshInterval = null;
let timerInterval = null;
onMounted(() => {
    refreshInterval = setInterval(() => {
        refreshData();
    }, 5 * 60 * 1000); // 5 minutes

    timerInterval = setInterval(() => {
        currentTime.value = moment().valueOf();
    }, 1000);
});

onBeforeUnmount(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});
</script>

<template>
    <Head title="User Live Status" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="text-2xl font-bold text-gray-800 tracking-tight flex items-center">
                    <Activity class="w-6 h-6 mr-2 text-indigo-600" />
                    User Live Status
                </h2>
                <div class="flex items-center gap-3 max-w-sm w-full">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <Search class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            type="text"
                            v-model="searchTerm"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-xl leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent sm:text-sm shadow-sm transition-all duration-200"
                            placeholder="Search users or tasks..."
                        />
                    </div>
                    <button 
                        @click="refreshData"
                        class="p-2.5 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all active:scale-95 shadow-sm group"
                        :disabled="refreshing"
                        title="Refresh Data"
                    >
                        <RefreshCw :class="['w-4 h-4 text-gray-500 group-hover:text-indigo-600', { 'animate-spin': refreshing }]" />
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                <div class="bg-white/60 backdrop-blur-md rounded-2xl p-6 border border-white shadow-sm flex items-center">
                    <div class="p-3 bg-green-100 rounded-xl mr-4 text-green-600">
                        <PlayCircle class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Online Users</p>
                        <p class="text-2xl font-bold text-gray-900">{{ onlineUsers.length }}</p>
                    </div>
                </div>
                <div class="bg-white/60 backdrop-blur-md rounded-2xl p-6 border border-white shadow-sm flex items-center">
                    <div class="p-3 bg-gray-100 rounded-xl mr-4 text-gray-600">
                        <PauseCircle class="w-6 h-6" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Offline Users</p>
                        <p class="text-2xl font-bold text-gray-900">{{ offlineUsers.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Online Section -->
            <section class="mb-12">
                <div class="flex items-center mb-6">
                    <div class="w-2 h-2 rounded-full bg-green-500 mr-2 shadow-[0_0_8px_rgba(34,197,94,0.6)] animate-pulse"></div>
                    <h3 class="text-lg font-semibold text-gray-800 uppercase tracking-wider text-sm">Online Now</h3>
                    <span class="ml-3 px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ onlineUsers.length }}</span>
                </div>
                <div v-if="onlineUsers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="user in onlineUsers" :key="user.id" 
                        class="group relative bg-white rounded-3xl p-6 shadow-sm border border-gray-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden"
                        @click="openLogModal(user)"
                    >
                        <!-- Hover Effect Decor -->
                        <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-50 rounded-full -mr-12 -mt-12 group-hover:bg-indigo-100 transition-colors"></div>
                        
                        <div class="relative flex items-center mb-4">
                            <img :src="user.avatar" :alt="user.name" class="w-14 h-14 rounded-2xl object-cover shadow-sm ring-2 ring-white" />
                            <div class="ml-4">
                                <h4 class="font-bold text-gray-900 text-lg group-hover:text-indigo-600 transition-colors">{{ user.name }}</h4>
                                <div class="flex items-center gap-2">
                                    <span class="bg-green-100 text-green-700 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full">Active</span>
                                    <span class="text-[10px] text-gray-400 font-semibold uppercase flex items-center">
                                        <Clock class="w-3 h-3 mr-1" />
                                        {{ calculateDuration(user.online_since) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-start">
                                <div class="mt-1 p-1 bg-indigo-50 rounded-lg mr-3">
                                    <Activity class="w-3.5 h-3.5 text-indigo-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-gray-400 font-medium">Current Working Task</p>
                                    <p v-if="user.active_task" class="text-sm font-semibold text-gray-800 truncate">
                                        {{ user.active_task.name }}
                                    </p>
                                    <p v-else class="text-sm text-gray-400 italic">No active task</p>
                                </div>
                            </div>
                            <div v-if="user.active_task" class="flex items-center text-[10px] text-gray-400 ml-9">
                                <Activity class="w-3 h-3 mr-1" />
                                <span class="truncate">{{ user.active_task.project_name }}</span>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-50 flex justify-between items-center text-xs">
                            <span class="text-indigo-500 font-medium group-hover:translate-x-1 transition-transform inline-flex items-center">
                                View Activity History
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl p-12 text-center">
                    <UserIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p class="text-gray-500 font-medium">No users are currently online.</p>
                </div>
            </section>

            <!-- Offline Section -->
            <section>
                <div class="flex items-center mb-6">
                    <div class="w-2 h-2 rounded-full bg-gray-300 mr-2"></div>
                    <h3 class="text-lg font-semibold text-gray-800 uppercase tracking-wider text-sm">Offline</h3>
                    <span class="ml-3 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-bold">{{ offlineUsers.length }}</span>
                </div>
                <div v-if="offlineUsers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 opacity-80">
                    <div v-for="user in offlineUsers" :key="user.id" 
                        class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 cursor-pointer"
                        @click="openLogModal(user)"
                    >
                        <div class="flex items-center mb-4">
                            <img :src="user.avatar" :alt="user.name" class="w-12 h-12 rounded-2xl grayscale opacity-60" />
                            <div class="ml-4">
                                <h4 class="font-bold text-gray-700">{{ user.name }}</h4>
                                <span class="text-[10px] font-medium text-gray-400">Away</span>
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 flex items-center">
                            <Clock class="w-3 h-3 mr-1.5" />
                            View activity logs
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- History Modal -->
        <Modal :show="showLogModal" @close="showLogModal = false" maxWidth="2xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center text-left">
                        <img v-if="selectedUser" :src="selectedUser.avatar" class="w-10 h-10 rounded-xl mr-3" />
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ selectedUser?.name }}</h2>
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">
                                {{ selectedUser?.timezone }} Timezone
                            </p>
                        </div>
                    </div>
                    <button @click="showLogModal = false" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <XCircle class="w-6 h-6 text-gray-400" />
                    </button>
                </div>

                <div class="bg-gray-50 rounded-2xl p-4 mb-6 border border-gray-100">
                    <div class="flex flex-col sm:flex-row gap-4 items-end">
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Range Start</label>
                            <div class="relative">
                                <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input type="date" v-model="dateRange.start" class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all" />
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Range End</label>
                            <div class="relative">
                                <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                <input type="date" v-model="dateRange.end" class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all" />
                            </div>
                        </div>
                        <button 
                            @click="fetchLogs" 
                            class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all active:scale-95 disabled:opacity-50"
                            :disabled="loadingLogs"
                        >
                            {{ loadingLogs ? 'Loading...' : 'Filter' }}
                        </button>
                    </div>
                </div>

                <div class="max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    <div v-if="userLogs.length > 0" class="space-y-4">
                        <div v-for="log in userLogs" :key="log.id" class="relative pl-8 pb-4">
                            <!-- Timeline Line -->
                            <div class="absolute left-3 top-0 bottom-0 w-0.5 bg-gray-100"></div>
                            <!-- Icon -->
                            <div :class="['absolute left-0 top-1 p-1 rounded-full border-2 border-white shadow-sm ring-1 ring-gray-100', 
                                log.properties.status === 'online' ? 'bg-green-500' : 'bg-gray-400']">
                                <div class="w-1.5 h-1.5 rounded-full bg-white"></div>
                            </div>

                            <div class="bg-white border border-gray-100 rounded-2xl p-4 transition-all hover:border-indigo-100 hover:bg-indigo-50/10">
                                <div class="flex justify-between items-start mb-2">
                                    <span :class="['text-[10px] font-bold uppercase px-2 py-0.5 rounded-full', 
                                        log.properties.status === 'online' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600']">
                                        {{ log.properties.status }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-medium">{{ formatTimestamp(log.created_at) }}</span>
                                </div>
                                <p class="text-sm text-gray-800 font-medium">User became {{ log.properties.status }}</p>
                                <div v-if="log.properties.data" class="mt-2 text-[10px] text-gray-400 bg-gray-50/50 rounded-lg p-2 border border-gray-50">
                                    <div class="grid grid-cols-2 gap-2">
                                        <span v-if="log.properties.data.browser">Browser: {{ log.properties.data.browser }}</span>
                                        <span v-if="log.properties.data.hostname">Device: {{ log.properties.data.hostname }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="!loadingLogs" class="text-center py-12">
                        <Clock class="w-10 h-10 text-gray-200 mx-auto mb-2" />
                        <p class="text-gray-400 text-sm">No activity records found for this period.</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <SecondaryButton @click="showLogModal = false" class="rounded-xl px-6">Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #cbd5e0;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    background: transparent;
    bottom: 0;
    color: transparent;
    cursor: pointer;
    left: 0;
    position: absolute;
    right: 0;
    top: 0;
    width: auto;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
