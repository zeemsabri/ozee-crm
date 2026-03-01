<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import moment from 'moment';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import {
    LayoutDashboardIcon, SparklesIcon, AlarmClockIcon, Clock9Icon, GaugeIcon, SplitIcon,
    CalendarIcon, ChevronDownIcon, XIcon, GlobeIcon, HistoryIcon, LightbulbIcon, PlusIcon, InfoIcon
} from 'lucide-vue-next';

// From props (provided by Admin controller)
const props = defineProps({
    users: {
        type: Array,
        default: () => []
    }
});

const selectedUserIds = ref([]);
const selectedDate = ref(moment().format('YYYY-MM-DD'));
const reports = ref([]);
const loading = ref(false);

const activeReportIndex = ref(0);

const activeReport = computed(() => {
    if (reports.value.length === 0) return null;
    return reports.value[activeReportIndex.value] || null;
});

const activeUser = computed(() => {
    if (!activeReport.value) return null;
    return activeReport.value.user;
});

const formatTime = (timeStr) => {
    if(!timeStr) return '--:--';
    const split = timeStr.split(':');
    return `${split[0]}:${split[1]}`;
}

const stats = computed(() => {
    if (!activeReport.value) return null;
    return activeReport.value.stats_json || {};
});

const tasks = computed(() => {
    if (!activeReport.value) return [];
    return activeReport.value.tasks_json || [];
});

const timelineSlots = computed(() => {
    if (!activeReport.value) return Array(144).fill(0);
    return activeReport.value.timeline_json || Array(144).fill(0);
});

async function fetchReports() {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        selectedUserIds.value.forEach(id => params.append('user_ids[]', id));
        params.append('date', selectedDate.value);
        params.append('all', '1');

        const { data } = await axios.get('/api/productivity/snapshots', { params });
        reports.value = data;
        activeReportIndex.value = 0; // reset
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to load reports');
    } finally {
        loading.value = false;
    }
}

async function recreateReport() {
    if (!activeUser.value) return;
    loading.value = true;
    try {
        const { data } = await axios.post('/api/productivity/snapshots', {
            user_id: activeUser.value.id,
            date: selectedDate.value,
            recreate: true
        });
        window.toast?.success('Report regenerated successfully!');
        await fetchReports();
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to regenerate report');
    } finally {
        loading.value = false;
    }
}

async function deleteReport() {
    if (!activeReport.value) return;
    if (!confirm('Are you sure you want to delete this snapshot?')) return;
    loading.value = true;
    try {
        await axios.delete(`/api/productivity/snapshots/${activeReport.value.id}`);
        window.toast?.success('Report deleted successfully!');
        await fetchReports();
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to delete report');
    } finally {
        loading.value = false;
    }
}

async function generateNewReportForSelected() {
    if(selectedUserIds.value.length === 0) {
        alert("Please select a user to generate a report.");
        return;
    }
    const userId = selectedUserIds.value[0]; // Just generate for the first selected for now
    loading.value = true;
    try {
        const { data } = await axios.post('/api/productivity/snapshots', {
            user_id: userId,
            date: selectedDate.value,
        });
        window.toast?.success('Report generated successfully!');
        await fetchReports();
    } catch (e) {
        if(e.response?.status === 422) {
             alert(e.response.data.message);
        } else {
             window.toast?.error('Failed to generate report');
        }
    } finally {
        loading.value = false;
    }
}

const expandedTasks = ref({});
const toggleTask = (taskId) => {
    expandedTasks.value[taskId] = !expandedTasks.value[taskId];
};

onMounted(() => {
    // If we have users but none selected, select the first one to start
    if (props.users && props.users.length > 0) {
        selectedUserIds.value = [props.users[0].value];
    }
    fetchReports();
});

const timelineColors = (slotData) => {
    if (slotData === 1) return 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.3)] z-10 scale-y-[1.4] hover:scale-y-[1.6]';
    if (slotData === 2) return 'bg-amber-400 opacity-80 z-10 scale-y-[1.1] hover:scale-y-[1.3]';
    return 'bg-zinc-100 hover:scale-y-[1.2]'; // 0 or offline
};

const getTimelineLabel = (index) => {
    const totalMinutes = index * 10;
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
};
</script>

<template>
    <Head title="Activity Hub 2.0" />
    <AuthenticatedLayout>
    <div class="min-h-screen pb-20 font-sans text-zinc-900 bg-zinc-50">
        <!-- Filter Controls (Admin Level) -->
        <section class="bg-white border-b border-zinc-200 px-8 py-5 shadow-sm">
            <div class="max-w-[1600px] mx-auto grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-5 relative z-50">
                    <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">Team Members</label>
                    <MultiSelectDropdown v-model="selectedUserIds" :options="props.users" :is-multi="true" placeholder="Select members" />
                </div>
                <div class="md:col-span-3">
                    <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">Reporting Date</label>
                    <div class="flex items-center gap-3 border border-zinc-200 rounded-2xl px-4 py-2 bg-zinc-50/50">
                        <CalendarIcon class="w-4 h-4 text-zinc-400" />
                        <input type="date" v-model="selectedDate" class="bg-transparent border-none text-xs font-bold text-zinc-700 focus:ring-0 p-0 w-full" />
                    </div>
                </div>
                <div class="md:col-span-4 flex gap-3 h-[42px]">
                    <button @click="fetchReports" :disabled="loading" class="flex-1 bg-zinc-900 text-white rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-zinc-200 hover:bg-zinc-800 active:scale-95 transition-all disabled:opacity-50">
                        {{ loading ? 'Loading...' : 'Refresh Insights' }}
                    </button>
                    <button @click="generateNewReportForSelected" :disabled="loading" class="flex-1 bg-indigo-600 text-white rounded-2xl font-bold text-xs uppercase tracking-widest shadow-xl shadow-indigo-200 hover:bg-indigo-700 active:scale-95 transition-all disabled:opacity-50">
                       Generate Report
                    </button>
                </div>
            </div>
            
            <!-- User Tabs (if multiple reports loaded) -->
             <div v-if="reports.length > 1" class="max-w-[1600px] mx-auto mt-6 flex gap-2 overflow-x-auto pb-2 custom-scrollbar">
                 <button 
                    v-for="(r, idx) in reports" :key="r.id"
                    @click="activeReportIndex = idx"
                    :class="activeReportIndex === idx ? 'bg-indigo-50 border-indigo-200 text-indigo-700 ring-2 ring-indigo-500/20' : 'bg-white border-zinc-200 text-zinc-600 hover:bg-zinc-50'"
                    class="px-4 py-2 rounded-xl border text-xs font-bold flex items-center gap-2 transition-all whitespace-nowrap"
                 >
                    <img v-if="r.user?.avatar_url" :src="r.user.avatar_url" class="w-5 h-5 rounded-full" />
                    {{ r.user?.name }}
                 </button>
             </div>
        </section>

        <main class="max-w-[1600px] mx-auto p-8 space-y-8" v-if="activeReport">
            
            <!-- Snapshot Header Actions -->
            <div class="flex justify-between items-center bg-white/85 backdrop-blur-md p-5 rounded-[2.5rem] border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                <div class="flex items-center gap-4 pl-2">
                    <img v-if="activeUser?.avatar_url" :src="activeUser.avatar_url" class="w-12 h-12 rounded-2xl shadow-md border border-zinc-100" />
                    <div>
                        <h1 class="text-sm font-black text-zinc-900 tracking-tight">{{ activeUser?.name }}</h1>
                        <p class="text-xs text-zinc-500 font-medium">Snapshot Date: <span class="text-zinc-700 font-bold">{{ selectedDate }}</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3 pr-2">
                     <button @click="recreateReport" :disabled="loading" class="text-xs font-bold text-zinc-600 border border-zinc-200 bg-white px-4 py-2.5 rounded-xl hover:bg-zinc-50 disabled:opacity-50 transition-all">
                         Regenerate Data
                     </button>
                     <button @click="deleteReport" :disabled="loading" class="text-xs font-bold text-rose-600 border border-rose-200 bg-rose-50 px-4 py-2.5 rounded-xl hover:bg-rose-100 disabled:opacity-50 transition-all">
                         Delete Snapshot
                     </button>
                </div>
            </div>

            <!-- AI Intelligence Summary (Placeholder) -->
            <section class="shadow-[0_0_20px_rgba(99,102,241,0.1)] border border-indigo-500/20 bg-white p-6 rounded-[2.5rem] flex items-center gap-6 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-indigo-50/50 to-purple-50/50 pointer-events-none"></div>
                <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 shrink-0 relative z-10">
                    <SparklesIcon class="w-7 h-7" />
                </div>
                <div class="flex-1 relative z-10">
                    <h2 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-1">AI Intelligence Summary</h2>
                    <p class="text-sm text-zinc-600 leading-relaxed font-medium">
                        Based on the generated heartbeat logs, {{ activeUser?.name }} recorded <span class="text-zinc-900 font-bold bg-zinc-100 px-1.5 py-0.5 rounded">{{ stats?.actual_online_minutes }}m online duration</span>.
                        Comprehensive AI bottleneck detection and workflow friction insights are currently generating and will be available in subsequent platform updates.
                    </p>
                </div>
            </section>

            <!-- Simple KPIs for Admin Staff -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Punctuality -->
                <div class="bg-white/85 backdrop-blur-md p-6 rounded-3xl border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-2xl"><AlarmClockIcon class="w-5 h-5" /></div>
                        <span class="text-[9px] font-black text-rose-500 bg-rose-50 px-2 py-1 rounded-lg border border-rose-100">FIRST SEEN</span>
                    </div>
                    <h3 class="text-zinc-400 text-[10px] font-black uppercase tracking-widest">Punctuality Adherence</h3>
                    <div class="text-3xl font-black text-zinc-900 mt-1">{{ formatTime(stats?.first_seen) }}</div>
                    <p class="text-[10px] text-zinc-400 mt-2 font-medium italic">First recorded heartbeat</p>
                </div>

                <!-- Hours Gap -->
                <div class="bg-white/85 backdrop-blur-md p-6 rounded-3xl border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-2xl"><Clock9Icon class="w-5 h-5" /></div>
                        <span class="text-[9px] font-black text-amber-600 bg-amber-50 px-2 py-1 rounded-lg border border-amber-100">HOURS GAP</span>
                    </div>
                    <h3 class="text-zinc-400 text-[10px] font-black uppercase tracking-widest">Promised vs. Actual</h3>
                    <div class="text-3xl font-black text-zinc-900 mt-1 flex items-baseline gap-2">
                        {{ Math.round((stats?.actual_online_minutes || 0) / 60 * 10) / 10 }}h 
                        <span class="text-xs text-zinc-400 font-medium">/ {{ Math.round((stats?.promised_minutes || 0) / 60 * 10) / 10 }}h slot</span>
                    </div>
                    <p class="text-[10px] text-zinc-400 mt-2 font-medium italic">Recorded time vs allocated slots</p>
                </div>

                <!-- Working Intensity -->
                <div class="bg-white/85 backdrop-blur-md p-6 rounded-3xl border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-2xl"><GaugeIcon class="w-5 h-5" /></div>
                        <span class="text-[9px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100">OPTIMAL</span>
                    </div>
                    <h3 class="text-zinc-400 text-[10px] font-black uppercase tracking-widest">Active Engagement</h3>
                    <div class="text-3xl font-black text-zinc-900 mt-1">
                        {{ stats?.actual_online_minutes > 0 ? Math.round((stats?.active_minutes / stats?.actual_online_minutes) * 100) : 0 }}% 
                        <span class="text-xs text-zinc-400 font-medium">focused</span>
                    </div>
                    <p class="text-[10px] text-zinc-400 mt-2 font-medium italic">Active time vs total idle ratio</p>
                </div>

                <!-- Context Shifts -->
                <div class="bg-white/85 backdrop-blur-md p-6 rounded-3xl border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-2.5 bg-purple-50 text-purple-600 rounded-2xl"><SplitIcon class="w-5 h-5" /></div>
                        <span class="text-[9px] font-black text-zinc-500 bg-zinc-50 px-2 py-1 rounded-lg border border-zinc-100">STABLE</span>
                    </div>
                    <h3 class="text-zinc-400 text-[10px] font-black uppercase tracking-widest">Context Switches</h3>
                    <div class="text-3xl font-black text-zinc-900 mt-1">{{ stats?.context_switches }} <span class="text-xs text-zinc-400 font-medium">shifts</span></div>
                    <p class="text-[10px] text-zinc-400 mt-2 font-medium italic">Application/domain changes</p>
                </div>
            </section>

            <!-- Diagnostic Timeline -->
            <section class="bg-white/85 backdrop-blur-md rounded-[2.5rem] p-10 border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] relative overflow-hidden">
                <div class="flex justify-between items-end mb-12">
                    <div>
                        <h2 class="text-2xl font-black text-zinc-900 tracking-tight">Daily Presence Barcode</h2>
                        <p class="text-sm text-zinc-400 font-medium">A 24-hour visual distribution of <span class="text-emerald-500 font-bold">Active Heartbeats</span> vs <span class="text-amber-500 font-bold">Idle State</span>.</p>
                    </div>
                    <div class="flex items-center gap-6 text-[10px] font-black uppercase tracking-widest text-zinc-400">
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-emerald-500 rounded-sm shadow-sm"></div> Active Work</div>
                        <div class="flex items-center gap-2 text-amber-500"><div class="w-3 h-3 rounded-sm border bg-amber-400 shadow-sm"></div> Idle Break</div>
                        <div class="flex items-center gap-2 text-zinc-300"><div class="w-3 h-3 rounded-sm border bg-zinc-100 shadow-sm"></div> Offline</div>
                    </div>
                </div>

                <div class="relative pt-12 pb-8">
                    <!-- Placeholder Availability overlay - in a real app this would map to actual times -->
                    <div class="absolute inset-y-0 bg-indigo-600/[0.035] border-x-2 border-dashed border-indigo-600/10 pointer-events-none rounded-2xl z-0" style="left: 10%; width: 75%;"></div>

                    <div class="absolute top-0 left-0 right-0 flex justify-between text-[10px] font-black text-zinc-300 uppercase tracking-tighter border-b border-zinc-100 pb-2">
                        <span>00:00</span>
                        <span>04:00</span>
                        <span>08:00</span>
                        <span>12:00</span>
                        <span>16:00</span>
                        <span>20:00</span>
                        <span>24:00</span>
                    </div>

                    <div class="space-y-8 relative z-10 pt-4">
                        <div class="relative h-12 flex items-center group">
                            <span class="absolute md:-left-28 -left-0 -top-4 md:top-auto text-[9px] font-black text-zinc-400 uppercase tracking-tighter">Live Activity</span>
                            
                            <!-- Heartbeat Barcode (144 items) -->
                            <div class="grid grid-cols-[repeat(144,minmax(0,1fr))] gap-[1px] h-8 w-full bg-white p-[2px] rounded-xl border border-zinc-200 shadow-sm">
                                <template v-for="(slotData, idx) in timelineSlots" :key="'slot-'+idx">
                                    <div 
                                        :class="['w-full h-full rounded-[1px] transition-all cursor-pointer relative group/slot flex flex-col justify-end', timelineColors(slotData)]"
                                    >
                                        <div class="invisible group-hover/slot:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-3 bg-zinc-900 border border-zinc-700 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg z-50 whitespace-nowrap pointer-events-none shadow-xl">
                                            {{ getTimelineLabel(idx) }} - {{ getTimelineLabel(idx+1) }} : {{ slotData === 1 ? 'Active Track' : (slotData === 2 ? 'Idle State' : 'No Signal') }}
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </section>

            <!-- Task Table with Evidence Logs -->
            <div class="bg-white/85 backdrop-blur-md rounded-[2.5rem] border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] overflow-hidden">
                <div class="px-8 py-6 border-b border-zinc-100 flex justify-between items-center bg-white/50">
                    <div>
                        <h2 class="text-xl font-black text-zinc-900 tracking-tight">Active Task Analysis</h2>
                        <p class="text-xs text-zinc-400 font-medium">Verify task durations with raw activity evidence grouped by issue.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                        <tr class="text-[10px] font-black uppercase tracking-[0.15em] text-zinc-400 border-b border-zinc-50 bg-zinc-50/50">
                            <th class="px-8 py-5">Task Objective</th>
                            <th class="px-8 py-5">Total Tracked</th>
                            <th class="px-8 py-5">Work Fidelity</th>
                            <th class="px-8 py-5">Idle Extent</th>
                            <th class="px-8 py-5 text-right w-20">Log</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-50">
                        
                        <template v-for="task in tasks" :key="'tsk-'+task.task_id">
                            <tr @click="toggleTask(task.task_id)" class="group hover:bg-zinc-50/80 cursor-pointer transition-all">
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center border border-indigo-100 shrink-0">
                                            <LayoutDashboardIcon class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <div class="font-black text-zinc-700">{{ task.name }}</div>
                                            <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter mt-0.5">ID: {{ task.task_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6 font-mono font-bold text-zinc-600">
                                   {{ Math.round(task.active_mins + task.idle_mins) }} m
                                </td>
                                <td class="px-8 py-6">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-black text-emerald-600 w-8">
                                            {{ Math.round((task.active_mins / (task.active_mins + task.idle_mins || 1)) * 100) }}%
                                        </span>
                                        <div class="flex-1 h-2 w-20 bg-zinc-100 rounded-full overflow-hidden border border-zinc-200 shadow-inner">
                                            <div class="h-full bg-emerald-500 transition-all shadow-[0_0_10px_rgba(16,185,129,0.3)]" :style="{ width: ((task.active_mins / (task.active_mins + task.idle_mins || 1)) * 100) + '%' }"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-6">
                                    <span class="text-xs font-black text-amber-500 bg-amber-50 px-2 py-1 rounded-md border border-amber-100">
                                        {{ Math.round((task.idle_mins / (task.active_mins + task.idle_mins || 1)) * 100) }}% Interrupted
                                    </span>
                                </td>
                                <td class="px-8 py-6 text-right">
                                    <ChevronDownIcon :class="['w-5 h-5 text-zinc-300 transition-transform inline-block', expandedTasks[task.task_id] ? 'rotate-180 text-zinc-600' : 'rotate-0']" />
                                </td>
                            </tr>
                            
                            <!-- Task Evidence Drill-down Rows -->
                            <tr v-if="expandedTasks[task.task_id]" class="bg-zinc-50/70 border-t-0">
                                <td colspan="5" class="px-8 py-10 shadow-inner">
                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                                        <!-- URL Activity / Top Domains -->
                                        <div class="space-y-4">
                                            <h4 class="text-[10px] font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2 mb-4">
                                                <GlobeIcon class="w-3.5 h-3.5" /> High-Intensity Platforms Used
                                            </h4>
                                            <div class="bg-white rounded-[1.5rem] border border-zinc-200 overflow-hidden shadow-sm p-4">
                                                <div v-if="!task.top_domains?.length" class="text-xs italic text-zinc-500 text-center py-4">No specific platform data recorded.</div>
                                                <ul v-else class="space-y-3">
                                                    <li v-for="(domain, dIdx) in task.top_domains" :key="dIdx" class="flex justify-between items-center text-xs px-2 py-1 hover:bg-zinc-50 rounded-lg">
                                                        <span class="font-bold text-zinc-700 font-mono">{{ domain }}</span>
                                                        <span class="bg-zinc-100 text-zinc-500 px-2 py-0.5 rounded border border-zinc-200 text-[9px] font-black uppercase">Active Source</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- System Events -->
                                        <div class="space-y-4">
                                            <h4 class="text-[10px] font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2 mb-4">
                                                <HistoryIcon class="w-3.5 h-3.5" /> System Lifecycle Events
                                            </h4>
                                            
                                            <div v-if="!task.system_events?.length" class="text-xs italic text-zinc-500 bg-white p-6 rounded-[1.5rem] border text-center shadow-sm">
                                                No spatie system events tracked for this task.
                                            </div>
                                            <div v-else class="space-y-6 pt-2 pl-4 border-l-2 border-zinc-200 ml-2">
                                                 <div v-for="(log, lIdx) in task.system_events" :key="lIdx" class="relative pl-8">
                                                    <div class="absolute -left-[25px] top-0.5 w-3 h-3 bg-zinc-400 rounded-full border-2 border-white shadow-sm ring-1 ring-zinc-200"></div>
                                                    <p class="text-[11px] font-black text-zinc-800 uppercase tracking-tighter">{{ log.event }}</p>
                                                    <p class="text-[10px] text-zinc-400 font-bold mt-0.5">{{ log.time }}</p>
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        
                        <tr v-if="tasks.length === 0">
                            <td colspan="5" class="px-8 py-12 text-center text-zinc-500 italic text-sm">
                                <div class="w-16 h-16 bg-zinc-100 rounded-3xl flex items-center justify-center mx-auto mb-4 border border-zinc-200 shadow-inner">
                                    <LayoutDashboardIcon class="w-6 h-6 text-zinc-300" />
                                </div>
                                No task-specific tracking data recorded for this period.
                            </td>
                        </tr>

                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
        
        <main class="max-w-[1600px] mx-auto p-8 pt-12" v-else-if="reports.length === 0 && !loading">
            <div class="bg-white/85 backdrop-blur-md p-16 rounded-[2.5rem] text-center border shadow-sm border-white">
                <div class="w-20 h-20 bg-zinc-50 rounded-[2rem] flex items-center justify-center mx-auto mb-6 border border-zinc-200 shadow-inner">
                    <HistoryIcon class="h-8 w-8 text-zinc-300" />
                </div>
                <h3 class="font-black text-zinc-900 text-xl tracking-tight">Data Not Processed</h3>
                <p class="text-zinc-500 text-sm mt-2 mb-8 max-w-sm mx-auto font-medium">No productivity snapshot is currently compiled for the selected date and personnel.</p>
                <button @click="generateNewReportForSelected" :disabled="loading" class="bg-zinc-900 text-white font-bold py-3.5 px-8 text-xs uppercase tracking-widest rounded-2xl hover:bg-zinc-800 active:scale-95 transition-all shadow-xl shadow-zinc-200">
                    Compile New Snapshot
                </button>
            </div>
        </main>
        
    </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Scoped overrides if needed */
.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 10px; }
</style>
