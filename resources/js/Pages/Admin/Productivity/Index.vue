<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import moment from 'moment';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import {
    LayoutDashboardIcon, SparklesIcon, AlarmClockIcon, Clock9Icon, GaugeIcon, SplitIcon,
    CalendarIcon, ChevronDownIcon, XIcon, GlobeIcon, HistoryIcon, LightbulbIcon, PlusIcon, InfoIcon,
    MessageSquareIcon, SendIcon, SaveIcon, UserIcon, ShieldCheckIcon
} from 'lucide-vue-next';

// From props (provided by Admin controller)
const props = defineProps({
    users: {
        type: Array,
        default: () => []
    }
});

const selectedUserId = ref(null);
const selectedDate = ref(moment().subtract(1, 'days').format('YYYY-MM-DD'));
const reports = ref([]);
const loading = ref(false);
const pollingInterval = ref(null);
const isChecking = ref(false);
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
    if (!activeReport.value || !activeReport.value.timeline_json) return Array(144).fill(0);
    return activeReport.value.timeline_json;
});

const availabilityRegions = computed(() => {
    if (!activeReport.value || !activeReport.value.availability || !Array.isArray(activeReport.value.availability.time_slots)) return [];
    
    return activeReport.value.availability.time_slots.map(slot => {
        if (!slot?.start_time || !slot?.end_time) return { left: '0%', width: '0%' };
        
        const startParts = String(slot.start_time).split(':');
        const endParts = String(slot.end_time).split(':');
        
        if (startParts.length < 2 || endParts.length < 2) return { left: '0%', width: '0%' };
        
        const startMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1]);
        const endMinutes = parseInt(endParts[0]) * 60 + parseInt(endParts[1]);
        
        const left = (startMinutes / 1440) * 100;
        const width = Math.max(0, ((endMinutes - startMinutes) / 1440) * 100);
        
        return { left: `${left}%`, width: `${width}%` };
    });
});

const feedbackData = computed(() => {
    return activeReport.value?.feedback_json || {};
});

const adminFeedback = ref('');
const taskFeedback = ref({});

watch(() => activeReport.value?.id, (newId, oldId) => {
    if (activeReport.value && newId !== oldId) {
        adminFeedback.value = '';
        taskFeedback.value = {};
    }
}, { immediate: true });

async function saveAdminFeedback() {
    if (!activeReport.value || !adminFeedback.value.trim()) return;
    loading.value = true;
    try {
        const { data } = await axios.post(`/api/productivity/snapshots/${activeReport.value.id}/feedback`, {
            admin_feedback: adminFeedback.value,
        });
        activeReport.value.feedback_json = data.feedback;
        adminFeedback.value = ''; // Reset after log
        window.toast?.success('Audit log added');
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to save feedback');
    } finally {
        loading.value = false;
    }
}

async function saveSingleTaskFeedback(taskId) {
    if (!activeReport.value || !taskFeedback.value[taskId]?.trim()) return;
    try {
        const payload = {
            task_feedback: { [taskId]: taskFeedback.value[taskId] }
        };
        const { data } = await axios.post(`/api/productivity/snapshots/${activeReport.value.id}/feedback`, payload);
        activeReport.value.feedback_json = data.feedback;
        taskFeedback.value[taskId] = ''; // Reset this task's input
        window.toast?.success('Task audit log added');
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to save task feedback');
    }
}

const aiReport = computed(() => {
    if (!activeReport.value || !activeReport.value.ai_report_json) return null;
    let raw = activeReport.value.ai_report_json;

    // Check if it's empty or invalid
    if (typeof raw === 'object' && raw !== null) {
        if (Array.isArray(raw) && raw.length === 0) return null;
        if (!Array.isArray(raw) && Object.keys(raw).length === 0) return null;
        
        // If it is an object but doesn't have the expected keys, it might be partial or invalid
        if (!Array.isArray(raw) && !raw.headline && !raw.engagement_narrative) return null;

        return raw;
    }

    if (typeof raw !== 'string') return null;

    // Helper to cleanup unquoted text JSON failure from AI
    const repairSloppyJson = (jsonString) => {
        let text = jsonString.trim();
        
        // Handle double-encoded strings: "{\"key\": \"val\"}"
        if (text.startsWith('"') && text.endsWith('"') && text.includes('\\"')) {
            try {
                text = JSON.parse(text); 
            } catch(e) {}
        }

        // 1. Try standard parse first
        try {
            return JSON.parse(text);
        } catch (e) {}

        // 2. Heuristic: Logic to handle unquoted values with commas
        const keys = [
            'headline', 'attendance_summary', 'focus_rating', 'engagement_narrative', 
            'accuracy_tip', 'improvement_suggestions', 'task_deep_dives', 'status'
        ];

        // We want to find the positions of all keys in the string
        let keyPositions = [];
        keys.forEach(key => {
            const pattern = new RegExp(`"${key}"\\s*:`, 'g');
            let match;
            while ((match = pattern.exec(text)) !== null) {
                keyPositions.push({ key, start: match.index, end: pattern.lastIndex });
            }
        });

        // Sort by start position
        keyPositions.sort((a, b) => a.start - b.start);

        if (keyPositions.length === 0) return null;

        const result = {};
        for (let i = 0; i < keyPositions.length; i++) {
            const current = keyPositions[i];
            const next = keyPositions[i + 1];
            
            let valStart = current.end;
            let valEnd = next ? next.start : text.lastIndexOf('}');
            
            let value = text.substring(valStart, valEnd).trim();
            
            // Clean up trailing commas
            if (value.endsWith(',')) value = value.slice(0, -1).trim();
            
            // If it's already quoted, just parse it
            if (value.startsWith('"') && value.endsWith('"')) {
                try {
                    result[current.key] = JSON.parse(value);
                } catch(e) {
                    result[current.key] = value.slice(1, -1);
                }
            } 
            // If it's an array/object start, try to parse it
            else if (value.startsWith('[') || value.startsWith('{')) {
                try {
                    result[current.key] = JSON.parse(value);
                } catch(e) {
                    // If nested unquoted array, this is harder, but let's try a simple fix
                    result[current.key] = value; 
                }
            }
            else {
                // Unquoted string value - this was our main bug!
                result[current.key] = value;
            }
        }
        
        return Object.keys(result).length > 0 ? result : null;
    };

    const repaired = repairSloppyJson(raw);
    
    // Normalize arrays
    if (repaired) {
        if (typeof repaired.improvement_suggestions === 'string') {
            repaired.improvement_suggestions = repaired.improvement_suggestions
                .replace(/^\[|\]$/g, '')
                .split('","')
                .map(s => s.replace(/^"|"$/g, '').trim());
        }
        if (typeof repaired.task_deep_dives === 'string') {
            try {
                // If it was captured as a string but looks like JSON, try one last parse
                repaired.task_deep_dives = JSON.parse(repaired.task_deep_dives);
            } catch(e) {}
        }
    }
    
    return repaired;
});

const isProcessing = computed(() => {
    if (!activeReport.value) return false;
    
    // Check if aiReport computed returns null (which means no valid data yet)
    if (aiReport.value) return false;
    
    return activeReport.value.status === 'pending';
});

async function fetchReports(silent = false) {
    if (!selectedUserId.value) {
        reports.value = [];
        return;
    }
    if (!silent) loading.value = true;
    else isChecking.value = true;
    try {
        const params = new URLSearchParams();
        params.append('user_ids[]', selectedUserId.value);
        params.append('date', selectedDate.value);
        params.append('all', '1');

        const { data } = await axios.get('/api/productivity/snapshots', { params });
        
        // Normalize: handle Arrays, Paginator objects { data: [] }, and single objects
        let normalizedData = [];
        if (Array.isArray(data)) {
            normalizedData = data;
        } else if (data && typeof data === 'object') {
            if (Array.isArray(data.data)) {
                // It's a paginator
                normalizedData = data.data;
            } else if (data.id) {
                // It's a single model object
                normalizedData = [data];
            } else {
                normalizedData = [];
            }
        }
        
        // Preserve index if we are just polling
        const prevId = activeReport.value?.id;
        reports.value = normalizedData;
        
        if (prevId) {
            const newIndex = normalizedData.findIndex(r => r && r.id === prevId);
            if (newIndex !== -1) activeReportIndex.value = newIndex;
            else activeReportIndex.value = 0;
        } else {
            activeReportIndex.value = 0;
        }
    } catch (e) {
        console.error("Fetch reports error:", e);
        if (!silent) window.toast?.error('Failed to load reports');
    } finally {
        if (!silent) loading.value = false;
        setTimeout(() => {
            isChecking.value = false;
        }, 800);
    }
}

const startPolling = () => {
    if (pollingInterval.value) return;
    pollingInterval.value = setInterval(() => {
        if (isProcessing.value) {
            fetchReports(true);
        } else {
            stopPolling();
        }
    }, 10000);
};

const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

watch(isProcessing, (processing) => {
    if (processing) startPolling();
    else stopPolling();
}, { immediate: true });

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
        reports.value = []; // Clear current view
        await fetchReports();
    } catch (e) {
        console.error(e);
        window.toast?.error('Failed to delete report');
    } finally {
        loading.value = false;
    }
}

async function generateNewReportForSelected() {
    if(!selectedUserId.value) {
        alert("Please select a user to generate a report.");
        return;
    }
    const userId = selectedUserId.value; 
    loading.value = true;
    try {
        const { data } = await axios.post('/api/productivity/snapshots', {
            user_id: userId,
            date: selectedDate.value,
        });
        window.toast?.success('Report generation started!');
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
    // No default user selection as requested
});

import { onUnmounted } from 'vue';
onUnmounted(() => stopPolling());

const timelineColors = (slotData) => {
    if (slotData === 1) return 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.3)] z-10 scale-y-[1.4] hover:scale-y-[1.6]';
    if (slotData === 2) return 'bg-amber-400 opacity-80 z-10 scale-y-[1.1] hover:scale-y-[1.3]';
    return 'bg-zinc-100 hover:scale-y-[1.2]';
};

const getTimelineLabel = (index) => {
    const totalMinutes = index * 10;
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
};

const getTaskAnalysis = (taskId) => {
    if (!aiReport.value || !aiReport.value.task_deep_dives) return null;
    const taskDeepDive = aiReport.value.task_deep_dives.find(d => d.task_id == taskId);
    return taskDeepDive ? taskDeepDive.analysis : null;
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
                    <label class="text-[10px] font-black text-zinc-400 uppercase tracking-widest mb-2 block">Team Member</label>
                    <SelectDropdown v-model="selectedUserId" :options="props.users" placeholder="Select a team member" @change="fetchReports" />
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

            <!-- AI Intelligence Summary -->
            <section class="shadow-[0_0_20px_rgba(99,102,241,0.1)] border border-indigo-500/20 bg-white p-6 rounded-[2.5rem] flex items-center gap-6 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-indigo-50/50 to-purple-50/50 pointer-events-none"></div>
                
                <div v-if="isProcessing && !aiReport" class="flex-1 flex items-center justify-between gap-4 relative z-10 py-2">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin"></div>
                        <div>
                            <h2 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-1">AI Generating Analysis...</h2>
                            <p class="text-xs text-zinc-500 font-medium italic">Our AI is currently auditing heartbeat logs and context switches. This usually takes 30-60 seconds.</p>
                        </div>
                    </div>
                    
                    <div v-if="isChecking" class="flex items-center gap-2 bg-indigo-600/10 px-4 py-2 rounded-2xl border border-indigo-200/50 animate-pulse">
                        <SparklesIcon class="w-3.5 h-3.5 text-indigo-600 animate-bounce" />
                        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Checking for Data...</span>
                    </div>
                </div>

                <template v-else-if="aiReport">
                    <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 shrink-0 relative z-10 transition-transform hover:scale-105">
                        <SparklesIcon class="w-7 h-7" />
                    </div>
                    <div class="flex-1 relative z-10">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col">
                                <h2 class="text-sm font-black text-indigo-600 uppercase tracking-widest mb-1">AI Intelligence Summary</h2>
                                <div v-if="isChecking" class="flex items-center gap-1.5 text-emerald-600 animate-pulse mt-[-4px] mb-1">
                                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                    <span class="text-[9px] font-bold uppercase tracking-widest">Syncing New Data...</span>
                                </div>
                            </div>
                            <span class="text-[9px] font-black text-emerald-600 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-100 uppercase tracking-widest">{{ aiReport.focus_rating }}</span>
                        </div>
                        <p class="text-sm text-zinc-900 font-black leading-tight mb-2 pr-10">"{{ aiReport.headline }}"</p>
                        <p class="text-sm text-zinc-600 leading-relaxed font-medium mb-4">
                            {{ aiReport.engagement_narrative }}
                        </p>
                        
                        <div v-if="aiReport.improvement_suggestions?.length" class="mt-4 pt-4 border-t border-zinc-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="(tip, tIdx) in aiReport.improvement_suggestions" :key="'tip-'+tIdx" class="flex items-start gap-2 group">
                                <LightbulbIcon class="w-4 h-4 text-amber-500 shrink-0 mt-0.5 group-hover:scale-110 transition-transform" />
                                <p class="text-[11px] text-zinc-500 font-semibold leading-normal">{{ tip }}</p>
                            </div>
                        </div>

                        <div v-if="aiReport.accuracy_tip" class="mt-4 flex items-center gap-2 bg-emerald-50 px-3 py-2 rounded-xl border border-emerald-100 w-fit">
                            <InfoIcon class="w-3.5 h-3.5 text-emerald-600" />
                            <span class="text-[10px] font-black text-emerald-700 uppercase tracking-tight">{{ aiReport.accuracy_tip }}</span>
                        </div>
                    </div>
                </template>

                <div v-else class="flex-1 relative z-10 flex items-center gap-4">
                     <div class="w-12 h-12 bg-zinc-100 rounded-xl flex items-center justify-center text-zinc-400">
                        <InfoIcon class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-zinc-400 uppercase tracking-widest mb-1">AI Analysis Unavailable</h2>
                        <p class="text-xs text-zinc-500 font-medium">Comprehensive AI narratives are generated automatically. If you don't see one, try regenerating the report.</p>
                    </div>
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
                    <!-- Availability overlay -->
                    <template v-if="availabilityRegions.length">
                        <div 
                            v-for="(region, rIdx) in availabilityRegions" :key="'avail-'+rIdx"
                            class="absolute inset-y-0 bg-indigo-600/[0.04] border-x border-dashed border-indigo-600/10 pointer-events-none rounded-sm z-0" 
                            :style="{ left: region.left, width: region.width }"
                        ></div>
                    </template>
                    <div v-else class="absolute inset-y-0 bg-rose-50/30 border-x-2 border-dashed border-rose-200/20 pointer-events-none rounded-2xl z-0" style="left: 0; width: 100%;"></div>

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

            <!-- Feedback Section -->
            <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- User Feedback -->
                <div class="bg-white/85 backdrop-blur-md rounded-[2.5rem] p-8 border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-zinc-100 rounded-xl">
                            <UserIcon class="w-5 h-5 text-zinc-600" />
                        </div>
                        <h2 class="text-sm font-black text-zinc-900 uppercase tracking-widest">Self Feedback</h2>
                    </div>
                    
                    <div v-if="feedbackData.user_feedback" class="space-y-4">
                        <div class="bg-zinc-50 rounded-2xl p-5 border border-zinc-100">
                             <p class="text-sm text-zinc-700 font-medium italic leading-relaxed">
                                "{{ feedbackData.user_feedback }}"
                             </p>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-zinc-400 uppercase tracking-tight">
                            <Clock9Icon class="w-3 h-3" />
                            Submitted At: {{ feedbackData.user_feedback_at }}
                        </div>
                    </div>
                    <div v-else class="text-center py-10">
                        <MessageSquareIcon class="w-10 h-10 text-zinc-100 mx-auto mb-3" />
                        <p class="text-xs text-zinc-400 font-medium italic">No feedback submitted by the user yet.</p>
                    </div>
                </div>

                <!-- Admin/Staff Feedback -->
                <div class="bg-white/85 backdrop-blur-md rounded-[2.5rem] p-8 border border-white shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] border-indigo-100 shadow-indigo-50/20">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-indigo-50 rounded-xl">
                                <ShieldCheckIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <h2 class="text-sm font-black text-indigo-600 uppercase tracking-widest">Team Audit Feedback</h2>
                        </div>
                        <button @click="saveAdminFeedback" :disabled="loading" class="text-[10px] bg-indigo-600 text-white font-black px-4 py-2 rounded-xl uppercase tracking-widest hover:bg-indigo-700 transition-all flex items-center gap-2">
                            <SaveIcon class="w-3 h-3" /> Save Audit
                        </button>
                    </div>

                    <div v-if="feedbackData.admin_feedbacks?.length" class="mb-6 space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        <div v-for="(log, lIdx) in feedbackData.admin_feedbacks" :key="'admin-log-'+lIdx" class="bg-indigo-50/50 rounded-2xl p-4 border border-indigo-100/50 relative group">
                            <p class="text-[11px] text-zinc-700 font-medium leading-relaxed mb-2">{{ log.comment }}</p>
                            <div class="flex justify-between items-center text-[8px] font-black text-indigo-400 uppercase tracking-widest">
                                <span>{{ log.by_name }}</span>
                                <span>{{ log.at }}</span>
                            </div>
                        </div>
                    </div>

                    <textarea 
                        v-model="adminFeedback"
                        placeholder="Add professional notes, observations, or guidance for this report..."
                        class="w-full h-24 bg-zinc-50 border-zinc-200 rounded-2xl text-xs font-medium text-zinc-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-300 p-4 transition-all placeholder:text-zinc-400"
                    ></textarea>
                </div>
            </section>
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
                                    <div class="space-y-10">
                                        <!-- AI Task Analysis -->
                                        <div v-if="getTaskAnalysis(task.task_id)" class="bg-indigo-50/50 border border-indigo-100 p-6 rounded-[2rem] relative overflow-hidden">
                                            <div class="absolute top-0 right-0 p-4 opacity-10 pointer-events-none">
                                                <SparklesIcon class="w-16 h-16 text-indigo-600" />
                                            </div>
                                            <h4 class="text-[10px] font-black text-indigo-600 uppercase tracking-widest flex items-center gap-2 mb-3">
                                                <SparklesIcon class="w-3.5 h-3.5" /> AI Diagnostic Analysis
                                            </h4>
                                            <p class="text-sm text-zinc-700 leading-relaxed font-medium relative z-10">
                                                {{ getTaskAnalysis(task.task_id) }}
                                            </p>
                                        </div>

                                        <!-- Admin Task Feedback -->
                                        <div class="bg-white rounded-[2rem] border border-zinc-200 p-6 shadow-sm">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4 class="text-[10px] font-black text-zinc-400 uppercase tracking-widest flex items-center gap-2">
                                                    <MessageSquareIcon class="w-3.5 h-3.5" /> Staff Task Evaluation
                                                </h4>
                                                <button @click="saveSingleTaskFeedback(task.task_id)" class="text-[9px] font-black text-indigo-600 uppercase tracking-widest flex items-center gap-1.5 hover:text-indigo-800">
                                                    <SaveIcon class="w-3 h-3" /> Update Task Note
                                                </button>
                                            </div>

                                            <div v-if="feedbackData.task_feedbacks?.[task.task_id]?.length" class="mb-4 space-y-3">
                                                <div v-for="(log, tlIdx) in feedbackData.task_feedbacks[task.task_id]" :key="'tasklog-'+tlIdx" class="bg-zinc-50 border border-zinc-100 rounded-xl p-3">
                                                    <p class="text-[10px] text-zinc-600 font-medium mb-1.5">{{ log.comment }}</p>
                                                    <div class="flex justify-between items-center text-[7px] font-black text-zinc-400 uppercase tracking-widest">
                                                        <span>{{ log.by_name }}</span>
                                                        <span>{{ log.at }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <textarea 
                                                v-model="taskFeedback[task.task_id]"
                                                placeholder="Add notes specifically about this task's fidelity or execution..."
                                                class="w-full h-16 bg-white border-zinc-200 rounded-xl text-[11px] font-medium text-zinc-700 focus:ring-1 focus:ring-indigo-500/30 p-3 transition-all placeholder:text-zinc-300"
                                            ></textarea>
                                        </div>

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
        
        <main class="max-w-[1600px] mx-auto p-8 pt-12" v-else-if="loading && reports.length === 0">
            <div class="bg-white/85 backdrop-blur-md p-16 rounded-[2.5rem] text-center border shadow-sm border-white">
                <div class="w-16 h-16 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mx-auto mb-6"></div>
                <h3 class="font-black text-zinc-900 text-xl tracking-tight">Accessing Insight Vault...</h3>
                <p class="text-zinc-500 text-sm mt-2 font-medium italic">We are compiling activity logs and availability data. Please wait.</p>
            </div>
        </main>
        
        <main class="max-w-[1600px] mx-auto p-8 pt-12" v-else-if="!selectedUserId">
            <div class="bg-white/85 backdrop-blur-md p-16 rounded-[2.5rem] text-center border shadow-sm border-white">
                <div class="w-20 h-20 bg-indigo-50 rounded-[2rem] flex items-center justify-center mx-auto mb-6 border border-indigo-200 shadow-inner">
                    <UserIcon class="h-8 w-8 text-indigo-400" />
                </div>
                <h3 class="font-black text-zinc-900 text-xl tracking-tight">Personnel Audit Required</h3>
                <p class="text-zinc-500 text-sm mt-2 mb-8 max-w-sm mx-auto font-medium">Please select a team member from the dropdown above to begin the productivity evaluation.</p>
            </div>
        </main>
        
        <main class="max-w-[1600px] mx-auto p-8 pt-12" v-else-if="reports.length === 0">
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
