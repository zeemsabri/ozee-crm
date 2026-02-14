<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import {
    UserIcon, CalendarIcon, ClockIcon, ExclamationTriangleIcon, ChevronDownIcon, ChevronUpIcon,
    PrinterIcon, CheckCircleIcon, QuestionMarkCircleIcon, PlusIcon, XMarkIcon,
    ArrowTrendingUpIcon, BriefcaseIcon, CheckBadgeIcon, ChartBarIcon
} from '@heroicons/vue/24/outline';

import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';
import EffortEstimationGuide from '@/Components/EffortEstimationGuide.vue';

const reportData = ref([]);
const charts = ref({});
const users = ref([]);
const projects = ref([]);
const loading = ref(false);

const selectedUserIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');

// --- Restored UI State ---
const expandedUsers = ref({});
const expandedTasks = ref({});
const showManualDialog = ref(false);
const manualEntryForm = ref({
    user_id: null,
    user_name: '',
    name: '',
    project_id: '',
    hours: '',
    date: ''
});

const showTaskDetailSidebar = ref(false);
const selectedTaskId = ref(null);
const selectedProjectId = ref(null);
const taskDetailProjectUsers = ref([]);
const showEffortHelp = ref(false);

// --- Navigation & UI Handlers ---
const toggleUserExpand = (userId) => {
    expandedUsers.value[userId] = !expandedUsers.value[userId];
};

const toggleTaskExpand = (taskId) => {
    expandedTasks.value[taskId] = !expandedTasks.value[taskId];
};

const openTaskDetail = async (task) => {
    if (!task || !task.task_id) return;
    selectedTaskId.value = task.task_id;
    selectedProjectId.value = task.project_id || 0;
    
    // Fetch project users for the sidebar
    if (selectedProjectId.value) {
        try {
            const res = await window.axios.get(`/api/projects/${selectedProjectId.value}/sections/clients-users`);
            taskDetailProjectUsers.value = res.data.users || [];
        } catch (e) {
            console.error('Failed to fetch project users', e);
        }
    }

    showTaskDetailSidebar.value = true;
};

const openManualEntry = (userReport) => {
    manualEntryForm.value = {
        user_id: userReport.user_id,
        user_name: userReport.user_name,
        name: '',
        project_id: '',
        hours: '',
        date: new Date().toISOString().split('T')[0]
    };
    showManualDialog.value = true;
};

const closeManualEntry = () => {
    showManualDialog.value = false;
};

const submitManualEntry = async () => {
    if (!manualEntryForm.value.name || !manualEntryForm.value.hours) return;
    try {
        await window.axios.post('/api/tasks/manual-effort', {
            name: manualEntryForm.value.name,
            assigned_to_user_id: manualEntryForm.value.user_id,
            project_id: manualEntryForm.value.project_id || null,
            manual_effort_override: parseFloat(manualEntryForm.value.hours),
            date: manualEntryForm.value.date
        });
        showManualDialog.value = false;
        fetchReport();
    } catch (e) {
        console.error('Failed to create manual entry', e);
    }
};

const handleTaskUpdated = (updatedTask) => {
    for (let userReport of reportData.value) {
        const index = userReport.tasks.findIndex(t => t.task_id === updatedTask.id);
        if (index !== -1) {
            const t = userReport.tasks[index];
            t.task_name = updatedTask.name;
            t.effort = updatedTask.effort;
            t.priority = updatedTask.priority;
            t.status = updatedTask.status?.value || updatedTask.status;
            t.due_date = updatedTask.due_date;
            t.subtasks = updatedTask.subtasks || [];
            
            // Recalculate checklist string
            if (t.subtasks.length > 0) {
                const total = t.subtasks.length;
                const done = t.subtasks.filter(s => s.status === 'done').length;
                t.checklist = `${done}/${total}`;
            }
            break;
        }
    }
};

const getChecklistTooltip = (task) => {
    if (!task.subtasks?.length) return 'No items';
    return task.subtasks.map(s => `${s.status === 'done' ? '✅' : '⬜'} ${s.name}`).join('\n');
};

// --- Live Reactive Calculations (KPIs & Insights) ---

const stats = computed(() => {
    let totalHrs = 0;
    let totalEffort = 0;
    let completedCount = 0;
    let lateCount = 0;

    reportData.value.forEach(u => {
        totalHrs += parseFloat(u.total_hours || 0);
        u.tasks.forEach(t => {
            totalEffort += parseInt(t.effort || 0);
            if (t.status === 'Done') completedCount++;
            if (t.is_late) lateCount++;
        });
    });

    return {
        totalHours: totalHrs.toFixed(1),
        velocity: totalEffort,
        completed: completedCount,
        late: lateCount
    };
});

const dailyInsights = computed(() => {
    const daily = {};

    reportData.value.forEach(u => {
        u.tasks.forEach(t => {
            // Aggregate Points by Due Date if completed
            const pointDate = t.due_date || 'N/A';
            if (t.status === 'Done') {
                if (!daily[pointDate]) daily[pointDate] = { hours: 0, points: 0 };
                daily[pointDate].points += parseInt(t.effort || 0);
            }

            // Aggregate Hours by Activity logs
            (t.sessions || []).forEach(s => {
                const date = (s.start || '').split(' ')[0];
                if (date) {
                    if (!daily[date]) daily[date] = { hours: 0, points: 0 };
                    daily[date].hours += (parseFloat(s.duration_seconds || 0) / 3600);
                }
            });

            // Handle specific manual overrides if no logs exist
            if (t.manual_effort_override && (!t.sessions || t.sessions.length === 0)) {
                const date = t.due_date || 'Manual';
                if (!daily[date]) daily[date] = { hours: 0, points: 0 };
                daily[date].hours += parseFloat(t.manual_effort_override);
            }
        });
    });

    return Object.keys(daily).sort().reverse().map(date => {
        const item = daily[date];
        const efficiency = item.hours > 0 ? (item.points / item.hours).toFixed(1) : 0;
        return { date, ...item, efficiency };
    });
});

// --- Data Fetching & Persistence ---

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/api/productivity/report', {
            params: {
                user_ids: selectedUserIds.value.join(','),
                date_start: dateStart.value,
                date_end: dateEnd.value,
            }
        });
        reportData.value = res.data.reportData.details;
        charts.value = res.data.reportData.charts;
        users.value = res.data.users;
        projects.value = res.data.projects;

        if (reportData.value.length === 1) {
            expandedUsers.value[reportData.value[0].user_id] = true;
        }
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const handleMetaUpdate = async (task, user, field, value) => {
    // 1. Instant Local Update (Re-calcs totals and KPIs immediately)
    if (field === 'manual_effort_override') {
        const hours = value === '' || value === null ? null : parseFloat(value);
        task.manual_effort_override = hours;

        // Update task duration for UI displays
        if (hours !== null && !isNaN(hours)) {
            task.used_seconds = hours * 3600;
        } else {
            task.used_seconds = task.total_seconds || 0;
        }

        // Recalculate user total hours locally
        let totalSecs = 0;
        user.tasks.forEach(t => {
            totalSecs += parseFloat(t.used_seconds || 0);
        });
        user.total_seconds = totalSecs;
        user.total_hours = (totalSecs / 3600).toFixed(2);
    } else {
        task[field] = value;
    }

    // 2. Background API Call
    try {
        await window.axios.post(`/api/tasks/${task.task_id}/productivity-meta`, {
            [field]: value
        });
    } catch (e) {
        console.error("Persistence failed", e);
    }
};

const formatDuration = (sec) => {
    if (sec === null || sec === undefined) return '0h';
    const h = Math.floor(Math.abs(sec) / 3600);
    const m = Math.floor((Math.abs(sec) % 3600) / 60);
    return `${h}h ${m}m`;
};

onMounted(() => fetchReport());
const printReport = () => window.print();

</script>

<template>
    <Head title="Productivity Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Productivity Dashboard</h2>
                <button @click="printReport" class="flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 shadow-sm transition">
                    <PrinterIcon class="h-4 w-4 mr-2" /> Export PDF
                </button>
            </div>
        </template>

        <div class="py-8 bg-gray-50 min-h-screen print:bg-white print:py-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Filter Panel -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
                        <div class="col-span-2">
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Team Members</label>
                            <MultiSelectDropdown v-model="selectedUserIds" :options="users" :is-multi="true" placeholder="Search team members..." />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Range Start</label>
                            <input type="date" v-model="dateStart" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 uppercase mb-2 block">Range End</label>
                            <input type="date" v-model="dateEnd" class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500" />
                        </div>
                        <button @click="fetchReport" :disabled="loading" class="w-full bg-indigo-600 text-white font-bold py-2.5 rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                            {{ loading ? 'Generating...' : 'Refresh Report' }}
                        </button>
                    </div>
                </div>

                <!-- KPI Scorecards -->
                <div v-if="reportData.length" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <ClockIcon class="h-6 w-6 text-indigo-500" />
                            <span class="text-xs font-bold text-gray-400">Total Hours</span>
                        </div>
                        <div class="text-3xl font-black text-gray-900">{{ stats.totalHours }}h</div>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <ArrowTrendingUpIcon class="h-6 w-6 text-green-500" />
                            <span class="text-xs font-bold text-gray-400">Total Velocity</span>
                        </div>
                        <div class="text-3xl font-black text-gray-900">{{ stats.velocity }} pts</div>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <CheckBadgeIcon class="h-6 w-6 text-blue-500" />
                            <span class="text-xs font-bold text-gray-400">Tasks Completed</span>
                        </div>
                        <div class="text-3xl font-black text-gray-900">{{ stats.completed }}</div>
                    </div>
                    <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <ExclamationTriangleIcon class="h-6 w-6 text-red-500" />
                            <span class="text-xs font-bold text-gray-400">Overdue Items</span>
                        </div>
                        <div class="text-3xl font-black text-red-600">{{ stats.late }}</div>
                    </div>
                </div>

                <!-- Chart Grid -->
                <div v-if="charts.daily_trend" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center justify-between">
                            <div class="flex items-center">
                                <ArrowTrendingUpIcon class="h-4 w-4 mr-2 text-indigo-600" /> Activity vs Output Trend
                            </div>
                            <div class="flex gap-4 text-[10px] font-bold uppercase tracking-wider">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500"></span> Hours</span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Points</span>
                            </div>
                        </h3>
                        <ChartComponent :data="charts.daily_trend" type="line" height="250" />
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-6 flex items-center">
                            <BriefcaseIcon class="h-4 w-4 mr-2 text-green-600" /> Project Load
                        </h3>
                        <ChartComponent :data="charts.project_dist" type="pie" height="250" />
                    </div>
                </div>

                <!-- Daily Performance Table -->
                <div v-if="dailyInsights.length" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/30">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center">
                            <ChartBarIcon class="h-4 w-4 mr-2 text-indigo-600" /> Daily Productivity Breakdown
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50/50 text-[10px] uppercase font-bold text-gray-500 tracking-wider">
                            <tr>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3 text-right">Hours Logged</th>
                                <th class="px-6 py-3 text-right">Points Done</th>
                                <th class="px-6 py-3 text-right">Efficiency (Pts/Hr)</th>
                                <th class="px-6 py-3 w-1/3">Velocity Meter</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            <tr v-for="day in dailyInsights" :key="day.date" class="hover:bg-gray-50/50">
                                <td class="px-6 py-4 font-bold text-gray-900">{{ day.date }}</td>
                                <td class="px-6 py-4 text-right font-mono">{{ day.hours.toFixed(1) }}h</td>
                                <td class="px-6 py-4 text-right">
                                    <span class="px-2 py-1 rounded bg-green-50 text-green-700 font-bold text-xs">{{ day.points }} pts</span>
                                </td>
                                <td class="px-6 py-4 text-right font-black" :class="day.efficiency > 2 ? 'text-green-600' : 'text-gray-500'">
                                    {{ day.efficiency }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-indigo-500 h-full rounded-full transition-all duration-500" :style="{ width: Math.min(day.points * 10, 100) + '%' }"></div>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Detailed Breakdown per User -->
                <div v-for="user in reportData" :key="user.user_id" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div
                        @click="toggleUserExpand(user.user_id)"
                        class="p-6 flex items-center justify-between bg-gray-50/50 cursor-pointer hover:bg-gray-100 transition print:cursor-default"
                    >
                        <div class="flex items-center space-x-4">
                            <img :src="user.avatar" class="h-12 w-12 rounded-full border-2 border-white shadow-sm" />
                            <div>
                                <h4 class="font-bold text-gray-900">{{ user.user_name }}</h4>
                                <p class="text-xs text-gray-500 uppercase tracking-widest font-bold">{{ user.tasks.length }} Tasks Managed</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-6">
                            <button
                                @click.stop="openManualEntry(user)"
                                class="p-2 rounded-full hover:bg-white text-gray-400 hover:text-indigo-600 transition print:hidden"
                                title="Add Manual Activity"
                            >
                                <PlusIcon class="h-5 w-5" />
                            </button>
                            <div class="text-right">
                                <div class="text-2xl font-black text-indigo-600">{{ user.total_hours }} hrs</div>
                                <p class="text-[10px] text-gray-400 font-bold uppercase">Total Charged</p>
                            </div>
                            <component :is="expandedUsers[user.user_id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400 print:hidden" />
                        </div>
                    </div>

                    <div v-show="expandedUsers[user.user_id]" class="p-0 overflow-x-auto border-t border-gray-100">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-4 py-3">Task Detail</th>
                                <th class="px-4 py-3">Status / Due</th>
                                <th class="px-4 py-3 text-center">Checklist</th>
                                <th class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        Effort
                                        <QuestionMarkCircleIcon class="h-3 w-3 cursor-pointer" @click="showEffortHelp = true" />
                                    </div>
                                </th>
                                <th class="px-4 py-3 text-right">Manual (Hrs)</th>
                                <th class="px-4 py-3 text-right">Total Time</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            <template v-for="task in user.tasks" :key="task.task_id">
                                <tr class="hover:bg-indigo-50/30 transition">
                                    <td class="px-4 py-4 cursor-pointer text-gray-300 hover:text-indigo-600" @click="toggleTaskExpand(task.task_id)">
                                        <component :is="expandedTasks[task.task_id] ? ChevronUpIcon : ChevronDownIcon" class="h-4 w-4" />
                                    </td>
                                    <td class="px-4 py-4">
                                        <div
                                            @click="openTaskDetail(task)"
                                            class="font-bold text-gray-900 mb-0.5 cursor-pointer hover:text-indigo-600 hover:underline transition print:no-underline"
                                        >
                                            {{ task.task_name }}
                                        </div>
                                        <div class="text-[10px] text-indigo-500 font-bold uppercase">{{ task.project_name }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col gap-1">
                                                <span :class="{
                                                    'bg-green-100 text-green-700': task.status === 'Done',
                                                    'bg-yellow-100 text-yellow-700': task.status === 'In Progress',
                                                    'bg-gray-100 text-gray-600': task.status === 'To Do'
                                                }" class="px-2 py-0.5 rounded text-[9px] font-black uppercase w-fit">
                                                    {{ task.status }}
                                                </span>
                                            <div class="flex items-center gap-1" :class="task.is_late ? 'text-red-500' : 'text-gray-400'">
                                                <CalendarIcon class="h-3 w-3" />
                                                <span class="text-[10px] font-bold">{{ task.due_date || 'No Date' }}</span>
                                                <span v-if="task.is_late" class="text-[8px] font-black uppercase bg-red-100 px-1 rounded">Late</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center overflow-visible">
                                        <div class="group relative inline-block">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 cursor-help">
                                                    <CheckCircleIcon class="h-3 w-3 mr-1" />
                                                    {{ task.checklist || '0/0' }}
                                                </span>
                                            <!-- Checklist Hover Detail -->
                                            <div v-if="task.subtasks?.length" class="absolute top-full mt-1 left-1/2 -translate-x-1/2 w-52 bg-gray-900 text-white text-[10px] rounded shadow-2xl z-50 hidden group-hover:block p-3 border border-gray-700">
                                                <div class="mb-2 font-black border-b border-gray-700 pb-1 uppercase tracking-tighter text-gray-400">Task Checklist Detail</div>
                                                <div class="max-h-48 overflow-y-auto space-y-1.5">
                                                    <div v-for="st in task.subtasks" :key="st.id" class="flex items-start gap-2 text-left">
                                                        <span class="shrink-0 mt-0.5">{{ st.status === 'done' ? '✅' : '⬜' }}</span>
                                                        <span :class="{'opacity-50 line-through': st.status === 'done'}" class="leading-tight">{{ st.name }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <input type="number" v-model="task.effort" @change="handleMetaUpdate(task, user, 'effort', task.effort)" class="w-16 text-right border-0 bg-transparent focus:ring-1 focus:ring-indigo-200 rounded text-xs font-bold" />
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <input type="number" step="0.5" v-model="task.manual_effort_override" @change="handleMetaUpdate(task, user, 'manual_effort_override', task.manual_effort_override)" class="w-16 text-right border-0 bg-transparent focus:ring-1 focus:ring-indigo-200 rounded text-xs font-bold" />
                                    </td>
                                    <td class="px-4 py-4 text-right font-black text-gray-900">
                                        {{ formatDuration(task.used_seconds) }}
                                    </td>
                                </tr>
                                <!-- Activity Log Expanded Row -->
                                <tr v-if="expandedTasks[task.task_id]" class="bg-gray-50/50">
                                    <td colspan="7" class="px-12 py-4">
                                        <div class="text-[10px] font-black text-gray-400 uppercase mb-3 tracking-widest">Activity Session Logs</div>
                                        <div v-if="task.sessions?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <div v-for="(session, idx) in task.sessions" :key="idx" class="bg-white p-3 rounded-lg border border-gray-100 shadow-sm flex items-center justify-between">
                                                <div>
                                                    <div class="text-[9px] font-bold text-gray-400 uppercase">{{ session.type === 'ongoing' ? 'Running' : 'Recorded' }}</div>
                                                    <div class="text-[11px] font-mono text-gray-600">{{ (session.start || '').split(' ')[1] }} - {{ session.end === 'Now' ? 'Now' : (session.end || '').split(' ')[1] }}</div>
                                                </div>
                                                <div class="text-xs font-black text-indigo-600">
                                                    {{ formatDuration(session.duration_seconds) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">No activity logs tracked for this specific period.</div>
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebars -->
        <RightSidebar v-model:show="showEffortHelp" title="Effort Estimation Guide" :initialWidth="30">
            <template #content><EffortEstimationGuide /></template>
        </RightSidebar>

        <RightSidebar v-model:show="showTaskDetailSidebar" title="Task Details" :initialWidth="45">
            <template #content>
                <TaskDetailSidebar
                    v-if="selectedTaskId"
                    :task-id="selectedTaskId"
                    :project-id="selectedProjectId"
                    :project-users="taskDetailProjectUsers"
                    @close="showTaskDetailSidebar = false"
                    @task-updated="handleTaskUpdated"
                />
            </template>
        </RightSidebar>

        <!-- Manual Entry Dialog -->
        <div v-if="showManualDialog" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="closeManualEntry"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transition-all transform scale-100">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900">Add Manual Activity</h3>
                    <button @click="closeManualEntry" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="h-6 w-6" /></button>
                </div>
                <form @submit.prevent="submitManualEntry" class="p-6 space-y-4">
                    <p class="text-sm text-gray-500">Manual entry for: <span class="font-bold text-indigo-600">{{ manualEntryForm.user_name }}</span></p>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Task Title</label>
                        <input type="text" v-model="manualEntryForm.name" required class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 text-sm" placeholder="Work description..." />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Project Link</label>
                        <select v-model="manualEntryForm.project_id" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 text-sm">
                            <option value="">General / Internal</option>
                            <option v-for="p in projects" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                            <input type="date" v-model="manualEntryForm.date" required class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hours Logged</label>
                            <input type="number" step="0.1" v-model="manualEntryForm.hours" required class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 text-sm" placeholder="e.g. 1.5" />
                        </div>
                    </div>
                    <div class="pt-4 flex gap-3">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white font-bold py-2.5 rounded-xl hover:bg-indigo-700 transition">Save Log</button>
                        <button type="button" @click="closeManualEntry" class="px-6 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    body { background: white; }
    .max-w-7xl { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
    .shadow-sm, .rounded-xl { border: 1px solid #e5e7eb !important; shadow: none !important; }
}
</style>
