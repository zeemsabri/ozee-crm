<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import ChartComponent from '@/Components/ChartComponent.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import { 
    UserIcon, CalendarIcon, ClockIcon, ExclamationTriangleIcon, ChevronDownIcon, ChevronUpIcon,
    PrinterIcon, DocumentTextIcon, ChatBubbleLeftIcon, CheckCircleIcon, QuestionMarkCircleIcon,
    PlusIcon, XMarkIcon
} from '@heroicons/vue/24/outline';

const props = defineProps();

const reportData = ref([]);
const charts = ref({});
const users = ref([]);
const projects = ref([]); // Projects list for manual entry
const filters = ref({
    user_ids: [],
    date_start: '',
    date_end: '',
});
const loading = ref(false);

const selectedUserIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');

// Manual Entry State
const showManualDialog = ref(false);
const manualEntryForm = ref({
    user_id: null,
    user_name: '',
    name: '',
    project_id: '',
    hours: '',
    date: '' // Defaults to today if empty
});

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
            project_id: manualEntryForm.value.project_id || null, // Ensure explicit null if empty string
            manual_effort_override: parseFloat(manualEntryForm.value.hours),
            date: manualEntryForm.value.date
        });
        
        showManualDialog.value = false;
        fetchReport(); // Refresh data
        
    } catch (e) {
        console.error('Failed to create manual entry', e);
        alert('Failed to save entry. Please check fields.');
    }
};

// Format seconds to HH:MM:SS
const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '-';
    // If negative (weird adjustment?), handle abs
    const absSeconds = Math.abs(seconds);
    const h = Math.floor(absSeconds / 3600);
    const m = Math.floor((absSeconds % 3600) / 60);
    const s = absSeconds % 60;
    return `${h}h ${m}m`; // Simplified for report
};

// Expand/Collapse state for user cards
const expandedUsers = ref({});
const toggleUserExpand = (userId) => {
    expandedUsers.value[userId] = !expandedUsers.value[userId];
};

// Calculator State
const effortGuideMode = ref('developer'); // 'developer' or 'admin' 
const calcDifficulty = ref(1);
const calcScope = ref(1);
const taskVolume = ref(1); // For Admin Calculator

const calculatedPoints = computed(() => {
    if (effortGuideMode.value === 'admin') {
        const vol = taskVolume.value;
        const pts = vol === 1 ? 1 : (vol === 2 ? 2 : (vol === 3 ? 3 : 5));
        return pts;
    }
    
    // Developer logic
    const raw = calcDifficulty.value * calcScope.value;
    // Map to nearest Fibonacci: 1, 2, 3, 5, 8, 13
    const fib = [1, 2, 3, 5, 8, 13];
    return fib.reduce((prev, curr) => {
        return (Math.abs(curr - raw) < Math.abs(prev - raw) ? curr : prev);
    });
});

const showEffortHelp = ref(false);

// Expand/Collapse state for individual tasks
const expandedTasks = ref({});
const toggleTaskExpand = (taskId) => {
    expandedTasks.value[taskId] = !expandedTasks.value[taskId];
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
        projects.value = response.data.projects || [];
        
        // Update local filters if needed
        if (!dateStart.value && response.data.filters.date_start) {
            dateStart.value = response.data.filters.date_start;
        }
// ...
        if (!dateEnd.value && response.data.filters.date_end) {
             dateEnd.value = response.data.filters.date_end;
        }

        if (reportData.value.length === 1) {
            expandedUsers.value[reportData.value[0].user_id] = true;
        }

    } catch (error) {
        console.error('Error fetching productivity report:', error);
    } finally {
        loading.value = false;
    }
};

const handleManualOverride = async (task, userReport, event) => {
    const newVal = event.target.value;
    
    // Update local state for immediate feedback without reload
    const hours = newVal === '' ? null : parseFloat(newVal);
    task.manual_effort_override = hours;
    
    // Recalc task usage
    if (hours !== null && !isNaN(hours)) {
        task.used_seconds = hours * 3600;
    } else {
        task.used_seconds = task.total_seconds;
    }
    
    // Recalc user totals
    let totalSecs = 0;
    userReport.tasks.forEach(t => {
        totalSecs += t.used_seconds;
    });
    userReport.total_seconds = totalSecs;
    userReport.total_hours = (totalSecs / 3600).toFixed(2);

    // Background API call
    try {
        await window.axios.post(`/api/tasks/${task.task_id}/productivity-meta`, {
            manual_effort_override: hours
        });
    } catch (e) {
        console.error('Failed to update manual effort', e);
    }
};

const handleEffortChange = async (task, event) => {
    const newVal = event.target.value;
    try {
        await window.axios.post(`/api/tasks/${task.task_id}/productivity-meta`, {
            effort: newVal
        });
    } catch (e) {
         console.error('Failed to update effort', e);
    }
};

const handlePriorityChange = async (task, event) => {
    const newVal = event.target.value;
    try {
        await window.axios.post(`/api/tasks/${task.task_id}/productivity-meta`, {
            priority: newVal
        });
        // Only task updated, no need to refetch full report
    } catch (e) {
         console.error('Failed to update priority', e);
    }
};

onMounted(() => {
    fetchReport();
});

const applyFilters = () => {
    fetchReport();
};

const getChecklistTooltip = (task) => {
    if (!task.subtasks || !Array.isArray(task.subtasks) || task.subtasks.length === 0) {
        return 'No items';
    }
    return task.subtasks.map(s => {
        const icon = s.status === 'done' ? '✅' : '⬜';
        return `${icon} ${s.name}`;
    }).join('\n');
};

const printReport = () => {
    window.print();
};

</script>

<template>
    <Head title="Productivity Report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Productivity Report</h2>
                <div class="flex space-x-2">
                     <button 
                        @click="printReport"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none transition ease-in-out duration-150"
                    >
                        <PrinterIcon class="h-4 w-4 mr-2" />
                        Print / PDF
                    </button>
                    <!-- Email button could go here -->
                </div>
            </div>
        </template>

        <div class="py-12 print:py-0 print:m-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 print:w-full print:max-w-none print:px-0">
                
                <!-- Filters Card (Hidden on Print) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 print:hidden">
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

                <!-- Print Header -->
                <div class="hidden print:block text-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">Productivity Report</h1>
                    <p class="text-gray-600">{{ dateStart }} - {{ dateEnd }}</p>
                </div>

                <!-- Charts Section -->
                <div v-if="reportData.length > 0 && charts.daily_trend" class="grid grid-cols-1 md:grid-cols-3 gap-6 print:break-inside-avoid">
                    
                    <!-- Daily Trend -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 col-span-2 print:border print:shadow-none">
                         <h3 class="text-lg font-medium text-gray-900 mb-4">Productivity Trend (Daily)</h3>
                         <ChartComponent 
                            :data="charts.daily_trend" 
                            type="line" 
                            chartId="dailyTrendChart"
                         />
                    </div>

                    <!-- Project Dist -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 print:border print:shadow-none">
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
                
                <div v-if="loading" class="text-center py-10 print:hidden">
                    <p class="text-gray-500">Loading Report...</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="userReport in reportData" :key="userReport.user_id" class="bg-white overflow-hidden shadow-sm sm:rounded-lg print:shadow-none print:border-b-2 print:rounded-none">
                        
                        <!-- User Header (Collapsible Trigger) -->
                        <div 
                            @click="toggleUserExpand(userReport.user_id)"
                            class="p-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors print:cursor-default print:hover:bg-white"
                        >
                            <div class="flex items-center space-x-4">
                                <img :src="userReport.avatar" alt="" class="h-10 w-10 rounded-full bg-gray-200 print:hidden" />
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">{{ userReport.user_name }}</h3>
                                    <p class="text-sm text-gray-500">{{ userReport.tasks.length }} tasks worked on</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <button 
                                    @click.stop="openManualEntry(userReport)" 
                                    class="p-1 rounded-full hover:bg-gray-200 text-gray-400 hover:text-indigo-600 transition-colors print:hidden"
                                    title="Add Manual Activity"
                                >
                                    <PlusIcon class="h-5 w-5" />
                                </button>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-indigo-600 print:text-black">{{ userReport.total_hours }} hrs</div>
                                    <div class="text-xs text-gray-500">Total Charged Hours</div>
                                </div>
                                <component :is="expandedUsers[userReport.user_id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400 print:hidden" />
                            </div>
                        </div>

                        <!-- Expanded Details -->
                        <div v-show="expandedUsers[userReport.user_id] || true" class="border-t border-gray-200 bg-gray-50 p-6 space-y-4 print:bg-white print:block">
                            <!-- Helper for print to ensure they are expanded -->
                            
                            <div v-if="userReport.tasks.length === 0" class="text-sm text-gray-500 italic">No tasks recorded in this period.</div>
                            
                            <!-- Detailed Table View -->
                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full text-sm text-left text-gray-500">
                                    <thead class="text-gray-700 uppercase bg-gray-100 print:bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 w-5"></th>
                                            <th class="px-3 py-3 w-1/4">Task / Project</th>
                                            <th class="px-3 py-3 w-1/6">Info</th>
                                            <th class="px-3 py-3 text-center">Checklist</th>
                                            <th class="px-3 py-3 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    Est. Effort
                                                    <QuestionMarkCircleIcon class="h-4 w-4 text-gray-400 cursor-pointer hover:text-indigo-600" @click="showEffortHelp = true" />
                                                </div>
                                            </th>
                                            <th class="px-3 py-3 text-right">Logged Time</th>
                                            <th class="px-3 py-3 text-right w-24">Manual (Hrs)</th>
                                            <th class="px-3 py-3 text-right">Used Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <template v-for="task in userReport.tasks" :key="task.task_id">
                                            <tr class="bg-white hover:bg-gray-50">
                                                <td class="px-3 py-3 align-top cursor-pointer text-gray-400 hover:text-gray-600" @click="toggleTaskExpand(task.task_id)">
                                                    <component :is="expandedTasks[task.task_id] ? ChevronUpIcon : ChevronDownIcon" class="h-4 w-4" />
                                                </td>
                                                <td class="px-3 py-3 align-top">
                                                    <div class="font-medium text-indigo-600 print:text-black">{{ task.task_name }}</div>
                                                    <div class="text-xs text-gray-500 mt-1">{{ task.project_name }}</div>
                                                    <div v-if="task.description" class="text-xs text-gray-400 mt-1 line-clamp-2 print:line-clamp-none">{{ task.description }}</div>
                                                </td>
                                                <td class="px-3 py-3 align-top space-y-1">
                                                    <div class="flex flex-col gap-1">
                                                        <div class="text-xs border border-gray-200 rounded px-2 py-1 inline-block bg-gray-50">
                                                            Prio: {{ task.priority || 'Medium' }}
                                                        </div>
                                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                                                            {{ task.status }}
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center gap-1" :class="{'text-red-600': task.is_late, 'text-gray-500': !task.is_late}">
                                                        <CalendarIcon class="h-3 w-3" />
                                                        <span class="text-xs">{{ task.due_date || 'No Due Date' }}</span>
                                                        <span v-if="task.is_late" class="text-[10px] font-bold uppercase bg-red-100 px-1 rounded">Late</span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 text-center align-top">
                                                     <div class="inline-flex flex-col items-center group relative">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 cursor-help"
                                                            :title="getChecklistTooltip(task)">
                                                            <CheckCircleIcon class="h-3 w-3 mr-1" />
                                                            {{ task.checklist }}
                                                         </span>
                                                         
                                                         <!-- Hover Popover (Tailwind group-hover) -->
                                                         <div v-if="task.subtasks && Array.isArray(task.subtasks) && task.subtasks.length > 0" 
                                                              class="absolute top-full mt-2 left-1/2 transform -translate-x-1/2 w-48 bg-gray-800 text-white text-xs rounded p-2 z-50 hidden group-hover:block shadow-lg">
                                                              <div class="mb-1 font-semibold text-gray-300 border-b border-gray-600 pb-1">Checklist</div>
                                                              <div v-for="st in task.subtasks" :key="st.id" class="flex items-start gap-1 py-0.5 truncate">
                                                                  <span class="text-[10px]">{{ st.status === 'done' ? '✅' : '⬜' }}</span>
                                                                  <span :class="{'opacity-50 line-through': st.status === 'done'}">{{ st.name }}</span>
                                                              </div>
                                                         </div>

                                                        <div v-if="task.subtasks && Array.isArray(task.subtasks) && task.subtasks.length > 0 && expandedTasks[task.task_id]" class="mt-2 text-left w-full text-xs text-gray-500 print:block hidden">
                                                            <div v-for="st in task.subtasks" :key="st.id" class="flex items-center gap-1">
                                                                <span class="w-2 h-2 rounded-full" :class="st.status === 'done' ? 'bg-green-400' : 'bg-gray-300'"></span>
                                                                <span :class="{'line-through': st.status === 'done'}">{{ st.name }}</span>
                                                            </div>
                                                        </div>
                                                     </div>
                                                </td>
                                                <td class="px-3 py-3 text-right align-top">
                                                    <input 
                                                        type="number" 
                                                        step="1" 
                                                        :value="task.effort" 
                                                        @change="handleEffortChange(task, $event)"
                                                        placeholder="Pts"
                                                        class="w-16 text-xs text-right border-gray-300 rounded shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-1 print:hidden"
                                                    />
                                                    <span class="hidden print:block font-mono">{{ task.effort || '-' }}</span>
                                                </td>
                                                <td class="px-3 py-3 text-right font-mono align-top">
                                                    {{ formatDuration(task.total_seconds) }}
                                                    <!-- Warning for outliers -->
                                                    <div v-if="task.sessions.some(s => s.type.includes('capped'))" class="text-orange-500 flex justify-end mt-1" title="Contains auto-capped sessions">
                                                        <ExclamationTriangleIcon class="h-4 w-4" />
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 text-right align-top">
                                                     <!-- Manual Override Input -->
                                                     <input 
                                                        type="number" 
                                                        step="0.1" 
                                                        :value="task.manual_effort_override" 
                                                        @change="handleManualOverride(task, userReport, $event)"
                                                        placeholder="-"
                                                        class="w-20 text-xs text-right border-gray-300 rounded shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-1 print:hidden"
                                                    />
                                                    <span class="hidden print:block font-mono">{{ task.manual_effort_override || '-' }}</span>
                                                </td>
                                                <td class="px-3 py-3 text-right font-bold text-gray-900 align-top">
                                                    {{ formatDuration(task.used_seconds) }}
                                                </td>
                                            </tr>
                                            <!-- Collapsible Logs Row -->
                                            <tr v-if="expandedTasks[task.task_id]" class="bg-gray-50 print:bg-white">
                                                <td colspan="8" class="px-4 py-3 border-t border-gray-100">
                                                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Wait List / Activity Log</div>
                                                    <table class="w-full text-xs text-left text-gray-500 inner-table">
                                                         <thead>
                                                            <tr class="border-b border-gray-200">
                                                                <th class="py-1">Start</th>
                                                                <th class="py-1">End</th>
                                                                <th class="py-1">Duration</th>
                                                                <th class="py-1">Type</th>
                                                            </tr>
                                                         </thead>
                                                         <tbody>
                                                            <tr v-for="(session, idx) in task.sessions" :key="idx" class="border-b border-gray-100 last:border-0">
                                                                <td class="py-1 font-mono">{{ session.start }}</td>
                                                                <td class="py-1 font-mono">{{ session.end }}</td>
                                                                <td class="py-1 font-medium">{{ formatDuration(session.duration_seconds) }}</td>
                                                                <td class="py-1">
                                                                    <span v-if="session.type.includes('capped')" class="text-orange-600 flex items-center gap-1">
                                                                        <ExclamationTriangleIcon class="h-3 w-3" /> Auto-Capped
                                                                    </span>
                                                                    <span v-else-if="session.type === 'ongoing'" class="text-green-600 font-bold">Running</span>
                                                                    <span v-else>Recorded</span>
                                                                </td>
                                                            </tr>
                                                         </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <!-- Effort Guide Sidebar -->
        <RightSidebar
            v-if="showEffortHelp"
            :show="showEffortHelp"
            @update:show="showEffortHelp = $event"
            title="Effort Estimation Guide"
            :initialWidth="30"
        >
            <!-- ... content ... -->
            <template #content>
                <!-- ... (existing content) ... -->
                <div class="space-y-6 text-sm text-gray-700">
                    
                    <!-- Role Toggle -->
                    <div class="flex justify-center bg-gray-100 p-1 rounded-lg mb-4">
                        <button 
                            @click="effortGuideMode = 'developer'"
                            class="flex-1 py-1 text-xs font-semibold rounded-md transition-colors"
                            :class="effortGuideMode === 'developer' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Developer
                        </button>
                        <button 
                            @click="effortGuideMode = 'admin'"
                            class="flex-1 py-1 text-xs font-semibold rounded-md transition-colors"
                            :class="effortGuideMode === 'admin' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                        >
                            Admin / Staff
                        </button>
                    </div>

                    <!-- Developer Content -->
                    <template v-if="effortGuideMode === 'developer'">
                        <section>
                            <h4 class="font-bold text-gray-900 text-lg mb-2">What are Story Points?</h4>
                            <p class="mb-2">Story points estimate the <span class="font-semibold">effort</span> required to implement a task. They consider:</p>
                            <ul class="list-disc pl-5 space-y-1">
                                <li><span class="font-semibold">Complexity:</span> How difficult is the logic?</li>
                                <li><span class="font-semibold">Risk:</span> How much uncertainty is there?</li>
                                <li><span class="font-semibold">Repetition:</span> How tedious is the work?</li>
                            </ul>
                        </section>

                        <section class="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                            <h4 class="font-bold text-indigo-900 text-md mb-3">Quick Reference</h4>
                            <div class="grid grid-cols-1 gap-3">
                                <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                                    <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">1</span>
                                    <div>
                                        <span class="font-bold block text-indigo-900">Tiny / Trivial</span>
                                        <span class="text-xs">Typo fix, color change, simple config. &lt; 1 hour.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                                    <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">2</span>
                                    <div>
                                        <span class="font-bold block text-indigo-900">Small / Routine</span>
                                        <span class="text-xs">Add field, update text, standard bug fix. 1-4 hours.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                                    <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">3</span>
                                    <div>
                                        <span class="font-bold block text-indigo-900">Medium</span>
                                        <span class="text-xs">New simple feature, standard page dev, component logic. 4-8 hours (1 day).</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start border-b border-indigo-200 pb-2">
                                    <span class="bg-indigo-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">5</span>
                                    <div>
                                        <span class="font-bold block text-indigo-900">Large</span>
                                        <span class="text-xs">Complex feature, integration, heavy logic. 2-3 days.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start">
                                    <span class="bg-yellow-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">8+</span>
                                    <div>
                                        <span class="font-bold block text-yellow-900">Ex-Large (Break Down!)</span>
                                        <span class="text-xs">Full module, major refactor. Consider splitting into smaller subtasks. 3-5+ days.</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="border-t pt-4">
                            <h4 class="font-bold text-gray-900 text-md mb-3">Estimation Calculator</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase">Difficulty</label>
                                    <select v-model="calcDifficulty" class="w-full mt-1 rounded border-gray-300 text-sm">
                                        <option :value="1">Low (Straightforward)</option>
                                        <option :value="2">Medium (Some Logic/Unknowns)</option>
                                        <option :value="3">High (Complex logic/High Risk)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase">Scope / Size</label>
                                    <select v-model="calcScope" class="w-full mt-1 rounded border-gray-300 text-sm">
                                        <option :value="1">Change (Text/Style)</option>
                                        <option :value="2">Component / Function</option>
                                        <option :value="3">Page / Feature</option>
                                        <option :value="5">Module / Epic</option>
                                    </select>
                                </div>

                                <div class="bg-gray-100 p-4 rounded text-center">
                                    <span class="text-xs text-gray-500 uppercase block mb-1">Suggested Points</span>
                                    <span class="text-3xl font-bold text-indigo-600">{{ calculatedPoints }}</span>
                                </div>
                            </div>
                        </section>
                    </template>

                    <!-- Admin Content -->
                    <template v-else>
                        <section>
                            <h4 class="font-bold text-gray-900 text-lg mb-2">Admin Effort Points</h4>
                            <p class="mb-2">For admin and management tasks, points are based on <span class="font-semibold">Volume</span> and <span class="font-semibold">Time Required</span>.</p>
                        </section>

                        <section class="bg-green-50 p-4 rounded-lg border border-green-100">
                            <h4 class="font-bold text-green-900 text-md mb-3">Quick Reference (Admin)</h4>
                            <div class="grid grid-cols-1 gap-3">
                                <div class="flex gap-3 items-start border-b border-green-200 pb-2">
                                    <span class="bg-green-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">1</span>
                                    <div>
                                        <span class="font-bold block text-green-900">Quick Task</span>
                                        <span class="text-xs">Email reply, filing, updating a record. &lt; 30 mins to 1 hour.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start border-b border-green-200 pb-2">
                                    <span class="bg-green-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">2</span>
                                    <div>
                                        <span class="font-bold block text-green-900">Routine Task</span>
                                        <span class="text-xs">Weekly meeting notes, processing stack of invoices. ~1-2 hours.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start border-b border-green-200 pb-2">
                                    <span class="bg-green-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">3</span>
                                    <div>
                                        <span class="font-bold block text-green-900">Half Day</span>
                                        <span class="text-xs">Deep focus admin, organizing system, audits. ~3-4 hours.</span>
                                    </div>
                                </div>
                                <div class="flex gap-3 items-start">
                                    <span class="bg-green-600 text-white font-bold w-8 h-8 flex items-center justify-center rounded-full shrink-0">5</span>
                                    <div>
                                        <span class="font-bold block text-green-900">Full Day / Complex</span>
                                        <span class="text-xs">Quarterly reporting, strategy planning day. Full day effort.</span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="border-t pt-4">
                            <h4 class="font-bold text-gray-900 text-md mb-3">Admin Calculator</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase">Task Type / Volume</label>
                                    <select v-model="taskVolume" class="w-full mt-1 rounded border-gray-300 text-sm">
                                        <option :value="1">Quick Task / Ad-hoc (1 Pt)</option>
                                        <option :value="2">Routine / Batch Work (2 Pts)</option>
                                        <option :value="3">Deep Focus / Half Day (3 Pts)</option>
                                        <option :value="5">Major Project / Full Day (5 Pts)</option>
                                    </select>
                                </div>

                                <div class="bg-gray-100 p-4 rounded text-center">
                                    <span class="text-xs text-gray-500 uppercase block mb-1">Suggested Points</span>
                                    <span class="text-3xl font-bold text-green-600">{{ calculatedPoints }}</span>
                                </div>
                            </div>
                        </section>
                    </template>

                </div>
            </template>
        </RightSidebar>
        
        <!-- Manual Entry Modal -->
        <div v-if="showManualDialog" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeManualEntry"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div class="hidden sm:block absolute top-0 right-0 pt-4 pr-4">
                        <button type="button" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none" @click="closeManualEntry">
                            <span class="sr-only">Close</span>
                             <XMarkIcon class="h-6 w-6" />
                        </button>
                    </div>
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Add Manual Activity
                            </h3>
                            <div class="mt-2 text-sm text-gray-500 mb-4">
                                Log work for <span class="font-bold">{{ manualEntryForm.user_name }}</span>. This will create a new entry.
                            </div>
                            
                            <form @submit.prevent="submitManualEntry" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description / Task Name *</label>
                                    <input type="text" v-model="manualEntryForm.name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g. Client Emails, RnD" />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Project (Optional)</label>
                                    <select v-model="manualEntryForm.project_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">- General / Internal -</option>
                                        <option v-for="p in projects" :key="p.value" :value="p.value">{{ p.label }}</option>
                                    </select>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Date *</label>
                                        <input type="date" v-model="manualEntryForm.date" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Hours *</label>
                                        <input type="number" step="0.1" v-model="manualEntryForm.hours" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="0.0" />
                                    </div>
                                </div>

                                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                                        Save Entry
                                    </button>
                                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:text-gray-500 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm" @click="closeManualEntry">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
  /* Hides everything that is not the printable area */
  body * {
    visibility: hidden;
  }
  
  /* We need to selectively allow visibility for children of the print area */
  /* But Inertia layouts are tricky. Usually better to duplicate the content into a print-only div 
     or hide siblings. here we just hide specific classes like .print:hidden */
  
  body, #app, main { 
      visibility: visible; 
      background: white !important;
      margin: 0;
      padding: 0;
  }

  nav, header, footer, .sidebar, button {
    display: none !important;
  }
  
}
</style>
