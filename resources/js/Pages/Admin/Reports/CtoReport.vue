<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { 
    CalendarIcon, 
    ClockIcon, 
    UserIcon, 
    ArrowPathIcon, 
    CheckCircleIcon, 
    XCircleIcon, 
    ExclamationTriangleIcon,
    QuestionMarkCircleIcon,
    SparklesIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChatBubbleBottomCenterTextIcon,
    DocumentTextIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    users: {
        type: Array,
        default: () => []
    }
});

const usersList = ref(props.users);
const dates = ref([]);
const reportData = ref([]);
const loading = ref(false);

const selectedUserId = ref('');
const dateStart = ref('');
const dateEnd = ref('');

// Modal details state
const isModalOpen = ref(false);
const activeUser = ref(null);
const activeDetail = ref(null);

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/admin/api/cto-report', {
            params: {
                user_id: selectedUserId.value,
                date_start: dateStart.value,
                date_end: dateEnd.value
            }
        });
        usersList.value = res.data.users;
        dates.value = res.data.dates;
        reportData.value = res.data.reportData;
    } catch (e) {
        console.error("Error fetching report data", e);
    } finally {
        loading.value = false;
    }
};

const formatDateShort = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const day = date.getDate();
    const weekday = date.toLocaleDateString('en-AU', { weekday: 'short' });
    return { day, weekday };
};

const formatDateFull = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-AU', { 
        weekday: 'long', 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
};

const getCellStatus = (detail) => {
    // Determine cell styling and descriptors
    if (!detail.has_availability) {
        return {
            bg: 'bg-zinc-50 border-zinc-200 text-zinc-500 hover:bg-zinc-100/80',
            label: 'No availability submitted',
            icon: 'unsubmitted'
        };
    }
    if (!detail.is_available) {
        return {
            bg: 'bg-rose-50 border-rose-100 text-rose-700 hover:bg-rose-100/60',
            label: 'Off Day / Unavailable',
            icon: 'off'
        };
    }
    // Available
    if (detail.actual_online_hours > 0) {
        return {
            bg: 'bg-emerald-50 border-emerald-100 text-emerald-800 hover:bg-emerald-100/60',
            label: `${detail.actual_online_hours}h worked`,
            icon: 'worked'
        };
    }
    // Available but no hours logged
    return {
        bg: 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100/60',
        label: 'No hours recorded',
        icon: 'absent'
    };
};

const showCellDetails = (user, detail) => {
    activeUser.value = user;
    activeDetail.value = detail;
    isModalOpen.value = true;
};

// Calculate summary stats for each user row
const getUserSummary = (user) => {
    let daysAvailable = 0;
    let daysUnavailable = 0;
    let daysWorked = 0;
    let totalHours = 0;
    let aiReports = 0;
    
    Object.values(user.daily_details).forEach(day => {
        if (day.has_availability) {
            if (day.is_available) daysAvailable++;
            else daysUnavailable++;
        }
        if (day.actual_online_hours > 0) {
            daysWorked++;
            totalHours += day.actual_online_hours;
        }
        if (day.has_ai_report) {
            aiReports++;
        }
    });

    return {
        daysAvailable,
        daysUnavailable,
        daysWorked,
        totalHours: totalHours.toFixed(1),
        aiReports
    };
};

onMounted(() => {
    const now = new Date();
    // Default to last 14 days
    const start = new Date(now.getTime() - 13 * 24 * 60 * 60 * 1000);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];
    fetchReport();
});
</script>

<template>
    <Head title="CTO Availability & Productivity Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-black text-2xl text-zinc-900 tracking-tight">CTO Report</h2>
                    <p class="text-sm text-zinc-500 font-medium">Evaluate availability submission rates, raw heartbeats, and AI productivity logs</p>
                </div>
                <div class="flex gap-3 w-full md:w-auto">
                    <button @click="fetchReport" :disabled="loading" class="flex-1 md:flex-initial flex items-center justify-center px-4 py-2.5 bg-zinc-900 text-white rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-zinc-800 transition active:scale-95 disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4 mr-2" :class="{'animate-spin': loading}" /> Refresh Report
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 bg-zinc-50 min-h-screen">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                <!-- 1. FILTERS & METADATA -->
                <div class="bg-white rounded-3xl shadow-sm border border-zinc-200/80 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                        <div class="relative z-20">
                            <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">Team Member</label>
                            <select v-model="selectedUserId" class="w-full rounded-xl border-zinc-200 text-xs font-bold text-zinc-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300">
                                <option value="">All Personnel</option>
                                <option v-for="u in usersList" :key="u.value" :value="u.value">{{ u.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">Start Date</label>
                            <div class="flex items-center border border-zinc-200 rounded-xl px-3 py-2 bg-zinc-50/50">
                                <CalendarIcon class="w-4 h-4 text-zinc-400 mr-2" />
                                <input type="date" v-model="dateStart" class="bg-transparent border-none text-xs font-bold text-zinc-700 focus:ring-0 p-0 w-full" />
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">End Date</label>
                            <div class="flex items-center border border-zinc-200 rounded-xl px-3 py-2 bg-zinc-50/50">
                                <CalendarIcon class="w-4 h-4 text-zinc-400 mr-2" />
                                <input type="date" v-model="dateEnd" class="bg-transparent border-none text-xs font-bold text-zinc-700 focus:ring-0 p-0 w-full" />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button @click="fetchReport" :disabled="loading" class="w-full bg-indigo-600 text-white rounded-xl py-2.5 text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-150 hover:bg-indigo-700 transition active:scale-95 disabled:opacity-50">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Legend of Status Indicators -->
                <div class="flex flex-wrap gap-6 items-center px-4 py-3 bg-white border border-zinc-200 rounded-2xl shadow-sm text-[10px] font-bold text-zinc-500 uppercase tracking-wider">
                    <span class="text-zinc-400">Legend:</span>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-100 border border-emerald-200 flex items-center justify-center text-emerald-800 text-[8px]">✓</span>
                        <span>Available & Worked (Hours Shown)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-800 text-[8px]">!</span>
                        <span>Available but Offline / Absent</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-100 border border-rose-200 flex items-center justify-center text-rose-800 text-[8px]">✕</span>
                        <span>Unavailable / Off Day</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-zinc-100 border border-zinc-200 flex items-center justify-center text-zinc-500 text-[8px]">-</span>
                        <span>No Availability Submitted</span>
                    </div>
                </div>

                <!-- 2. REPORT MATRIX GRID -->
                <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto min-w-full">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-zinc-50/70 border-b border-zinc-100">
                                    <th class="sticky left-0 bg-zinc-50 z-10 px-6 py-5 text-left text-[10px] font-black text-zinc-400 uppercase tracking-widest border-r border-zinc-100 min-w-[200px]">
                                        Team Member
                                    </th>
                                    <th v-for="d in dates" :key="d" class="px-4 py-3 text-center border-r border-zinc-100 min-w-[85px] last:border-r-0">
                                        <div class="text-[10px] font-bold text-zinc-400 uppercase tracking-tighter">{{ formatDateShort(d).weekday }}</div>
                                        <div class="text-sm font-black text-zinc-700">{{ formatDateShort(d).day }}</div>
                                    </th>
                                    <th class="px-6 py-5 text-right text-[10px] font-black text-zinc-400 uppercase tracking-widest min-w-[150px]">
                                        Summary
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr v-for="user in reportData" :key="user.user_id" class="hover:bg-zinc-50/50 transition duration-150">
                                    <!-- User Bio column -->
                                    <td class="sticky left-0 bg-white z-10 px-6 py-5 border-r border-zinc-100">
                                        <div class="flex items-center gap-3">
                                            <img :src="user.avatar_url" class="w-9 h-9 rounded-xl border border-zinc-100 object-cover shadow-sm shrink-0" />
                                            <div>
                                                <div class="text-sm font-black text-zinc-800 leading-tight">{{ user.user_name }}</div>
                                                <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mt-0.5">{{ user.timezone || 'UTC' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Date cells matrix -->
                                    <td v-for="d in dates" :key="d" class="p-2 border-r border-zinc-100 text-center last:border-r-0">
                                        <div 
                                            @click="showCellDetails(user, user.daily_details[d])"
                                            :class="[
                                                getCellStatus(user.daily_details[d]).bg,
                                                'group cursor-pointer rounded-xl border p-2 flex flex-col items-center justify-center min-h-[56px] transition relative'
                                            ]"
                                        >
                                            <!-- Indicator icons & indicators -->
                                            <div class="flex items-center gap-1.5">
                                                <!-- Available & Worked -->
                                                <template v-if="getCellStatus(user.daily_details[d]).icon === 'worked'">
                                                    <span class="text-xs font-black tracking-tight">{{ user.daily_details[d].actual_online_hours }}h</span>
                                                    <SparklesIcon v-if="user.daily_details[d].has_ai_report" class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                                                </template>
                                                
                                                <!-- Available but Offline -->
                                                <template v-else-if="getCellStatus(user.daily_details[d]).icon === 'absent'">
                                                    <ExclamationTriangleIcon class="w-4 h-4 text-amber-500" />
                                                </template>
                                                
                                                <!-- Unavailable / Off -->
                                                <template v-else-if="getCellStatus(user.daily_details[d]).icon === 'off'">
                                                    <span class="text-[9px] font-black uppercase tracking-tight opacity-75">Off</span>
                                                </template>
                                                
                                                <!-- Unsubmitted -->
                                                <template v-else>
                                                    <span class="text-zinc-300 font-extrabold text-sm">-</span>
                                                </template>
                                            </div>
                                            
                                            <!-- Sub details like late / left early indicator dots -->
                                            <div class="flex gap-1 mt-1 justify-center">
                                                <span v-if="user.daily_details[d].was_late" class="w-1.5 h-1.5 rounded-full bg-amber-500" title="Late Arrival"></span>
                                                <span v-if="user.daily_details[d].left_early" class="w-1.5 h-1.5 rounded-full bg-purple-500" title="Left Early"></span>
                                                <span v-if="user.daily_details[d].did_not_show_up" class="w-1.5 h-1.5 rounded-full bg-red-650" title="Did not show up"></span>
                                            </div>

                                            <!-- Simple visual feedback on hover -->
                                            <div class="absolute inset-0 rounded-xl bg-zinc-950/[0.03] opacity-0 group-hover:opacity-100 transition duration-150"></div>
                                        </div>
                                    </td>
                                    
                                    <!-- Row stats summary -->
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex flex-col gap-0.5 justify-end">
                                            <div class="text-[11px] font-black text-zinc-800 leading-tight">
                                                {{ getUserSummary(user).totalHours }} hrs worked
                                            </div>
                                            <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest">
                                                Available: {{ getUserSummary(user).daysAvailable }}d / Off: {{ getUserSummary(user).daysUnavailable }}d
                                            </div>
                                            <div class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest" v-if="getUserSummary(user).aiReports > 0">
                                                ★ {{ getUserSummary(user).aiReports }} AI summaries
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="reportData.length === 0 && !loading">
                                    <td :colspan="dates.length + 2" class="px-8 py-16 text-center text-zinc-400 italic text-sm">
                                        No team availability or productivity logs found for this date range.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. DAILY DETAILS SLIDE-OVER / MODAL -->
                <div v-if="isModalOpen && activeDetail" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4 bg-zinc-950/45 backdrop-blur-sm">
                    <div class="bg-white rounded-[2.5rem] border border-zinc-200 shadow-2xl max-w-4xl w-full max-h-[85vh] overflow-hidden flex flex-col">
                        
                        <!-- Modal Header -->
                        <div class="px-8 py-6 border-b border-zinc-100 flex justify-between items-center bg-zinc-55/40">
                            <div class="flex items-center gap-3">
                                <img :src="activeUser.avatar_url" class="w-11 h-11 rounded-xl border object-cover shadow-sm" />
                                <div>
                                    <h3 class="text-lg font-black text-zinc-900 leading-tight">{{ activeUser.user_name }}</h3>
                                    <p class="text-xs text-zinc-500 font-medium">{{ formatDateFull(activeDetail.date) }}</p>
                                </div>
                            </div>
                            <button @click="isModalOpen = false" class="text-zinc-400 hover:text-zinc-600 font-extrabold text-lg p-2.5 bg-zinc-50 rounded-xl">
                                ✕
                            </button>
                        </div>

                        <!-- Modal Scrollable Body -->
                        <div class="p-8 overflow-y-auto space-y-8 flex-1 custom-scrollbar">
                            
                            <!-- A. Availability Details -->
                            <div class="bg-zinc-50/50 border border-zinc-200/60 rounded-3xl p-6">
                                <h4 class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <CalendarIcon class="w-4 h-4 text-zinc-400" /> Availability & Timeslots
                                </h4>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center py-2 border-b border-zinc-100">
                                            <span class="text-xs text-zinc-500 font-medium">Availability Submitted:</span>
                                            <span v-if="activeDetail.has_availability" class="text-xs font-black uppercase tracking-wider text-emerald-600">Yes</span>
                                            <span v-else class="text-xs font-black uppercase tracking-wider text-zinc-400">No</span>
                                        </div>
                                        
                                        <div class="flex justify-between items-center py-2 border-b border-zinc-100">
                                            <span class="text-xs text-zinc-500 font-medium">Status:</span>
                                            <span v-if="activeDetail.has_availability && activeDetail.is_available" class="text-xs font-black uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-100">Available</span>
                                            <span v-else-if="activeDetail.has_availability" class="text-xs font-black uppercase tracking-wider text-rose-600 bg-rose-50 px-2 py-0.5 rounded-lg border border-rose-100">Unavailable / Off</span>
                                            <span v-else class="text-xs font-black uppercase tracking-wider text-zinc-400">N/A</span>
                                        </div>

                                        <div v-if="activeDetail.reason" class="pt-2">
                                            <span class="text-xs text-zinc-500 font-bold block mb-1">Reason / Off Comment:</span>
                                            <p class="text-xs text-zinc-600 font-semibold italic bg-white p-3 border border-zinc-150 rounded-xl">
                                                "{{ activeDetail.reason }}"
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <span class="text-xs text-zinc-500 font-bold block mb-2">Submitted Slot Hours:</span>
                                        <div v-if="activeDetail.time_slots && activeDetail.time_slots.length > 0" class="space-y-2">
                                            <div v-for="(slot, idx) in activeDetail.time_slots" :key="idx" class="flex justify-between items-center bg-white border border-zinc-200/80 rounded-xl px-4 py-2 text-xs font-semibold text-zinc-700 shadow-sm">
                                                <span>Slot {{ idx + 1 }}</span>
                                                <span class="font-mono font-bold">{{ slot.start_time }} - {{ slot.end_time }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-zinc-450 italic p-4 text-center bg-white border border-dashed rounded-xl">
                                            No time slots configured.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- B. Productivity Hours & Heartbeats -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                                <div class="bg-white border border-zinc-200 rounded-3xl p-5 shadow-sm text-center">
                                    <div class="mx-auto w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center mb-3">
                                        <ClockIcon class="w-5 h-5" />
                                    </div>
                                    <span class="text-zinc-400 text-[9px] font-black uppercase tracking-wider block">Promised Hours</span>
                                    <span class="text-2xl font-black text-zinc-800 block mt-1">{{ activeDetail.promised_hours }}h</span>
                                </div>
                                <div class="bg-white border border-zinc-200 rounded-3xl p-5 shadow-sm text-center">
                                    <div class="mx-auto w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-3">
                                        <CheckCircleIcon class="w-5 h-5" />
                                    </div>
                                    <span class="text-zinc-400 text-[9px] font-black uppercase tracking-wider block">Actual Work (Online)</span>
                                    <span class="text-2xl font-black text-zinc-800 block mt-1">{{ activeDetail.actual_online_hours }}h</span>
                                </div>
                                <div class="bg-white border border-zinc-200 rounded-3xl p-5 shadow-sm text-center">
                                    <div class="mx-auto w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center mb-3">
                                        <sparklesIcon class="w-5 h-5" />
                                    </div>
                                    <span class="text-zinc-400 text-[9px] font-black uppercase tracking-wider block">AI Evaluation</span>
                                    <span class="text-xs font-black uppercase tracking-wider block mt-3" :class="activeDetail.has_ai_report ? 'text-indigo-600' : 'text-zinc-455'">
                                        {{ activeDetail.has_ai_report ? 'Analyzed' : 'No report yet' }}
                                    </span>
                                </div>
                            </div>

                            <!-- C. AI Daily Report Details -->
                            <div v-if="activeDetail.has_ai_report" class="bg-gradient-to-r from-indigo-50/40 to-purple-50/40 border border-indigo-100 rounded-3xl p-6 relative overflow-hidden">
                                <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                                    <SparklesIcon class="w-24 h-24 text-indigo-600" />
                                </div>
                                <h4 class="text-[10px] font-black text-indigo-650 uppercase tracking-widest mb-4 flex items-center gap-1.5">
                                    <SparklesIcon class="w-4 h-4 text-indigo-600" /> AI Daily Intelligence Summary
                                </h4>
                                
                                <div class="space-y-4">
                                    <div>
                                        <span class="text-[10px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Headline</span>
                                        <p class="text-sm text-zinc-850 font-black italic">
                                            "{{ activeDetail.ai_report.headline }}"
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                                        <div class="md:col-span-2">
                                            <span class="text-[10px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Engagement Narrative</span>
                                            <p class="text-xs text-zinc-650 leading-relaxed font-semibold">
                                                {{ activeDetail.ai_report.engagement_narrative }}
                                            </p>
                                        </div>
                                        <div>
                                            <span class="text-[10px] font-black text-zinc-400 uppercase tracking-widest block mb-1">Focus Rating</span>
                                            <span class="inline-block text-xs font-black text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-1 mt-1">
                                                {{ activeDetail.ai_report.focus_rating || 'N/A' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- D. Tasks Breakdown -->
                            <div class="space-y-4">
                                <h4 class="text-[10px] font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                    <DocumentTextIcon class="w-4 h-4 text-zinc-400" /> Active Tasks breakdown
                                </h4>
                                
                                <div v-if="activeDetail.tasks && activeDetail.tasks.length > 0" class="divide-y divide-zinc-100 border border-zinc-200 rounded-2xl overflow-hidden bg-white shadow-sm">
                                    <div v-for="task in activeDetail.tasks" :key="task.task_id" class="p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 hover:bg-zinc-50/50 transition">
                                        <div>
                                            <div class="text-xs font-black text-zinc-750">{{ task.name }}</div>
                                            <div class="text-[9px] font-bold text-zinc-450 uppercase tracking-tight mt-0.5">Project: {{ task.project_name || 'N/A' }}</div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-mono font-bold text-zinc-600 block">{{ Math.round(task.active_mins + task.idle_mins) }}m tracked</span>
                                            <span class="text-[9px] text-emerald-600 bg-emerald-50 border border-emerald-100 rounded px-1.5 py-0.5 font-bold uppercase tracking-wider inline-block mt-1">
                                                {{ Math.round((task.active_mins / (task.active_mins + task.idle_mins || 1)) * 100) }}% Active
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-xs text-zinc-400 italic text-center p-8 bg-zinc-50 rounded-2xl border border-dashed">
                                    No task activity details recorded for this date.
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="px-8 py-5 border-t border-zinc-100 flex justify-end bg-zinc-50">
                            <button @click="isModalOpen = false" class="px-5 py-2 bg-zinc-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-zinc-800 transition">
                                Close Details
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom scrollbar rules */
.custom-scrollbar::-webkit-scrollbar {
    height: 6px;
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e4e4e7;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #d4d4d8;
}
</style>
