<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import {
    BriefcaseIcon, CalendarIcon, ClockIcon, ChevronDownIcon, ChevronUpIcon,
    PrinterIcon, ChatBubbleLeftRightIcon, EnvelopeIcon, DocumentTextIcon,
    SparklesIcon, UserIcon, ArrowPathIcon, CheckCircleIcon,
    HandRaisedIcon, BoltIcon, MagnifyingGlassIcon
} from '@heroicons/vue/24/outline';

import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';

const reportData = ref([]);
const projectsList = ref([]);
const loading = ref(false);
const exportSuccess = ref(false);
const exportProjectSuccess = ref({});

const selectedProjectIds = ref([]);
const dateStart = ref('');
const dateEnd = ref('');
const highlightDate = ref('');

// UI State
const expandedProjects = ref({});
const activeTabs = ref({});
const showTaskDetailSidebar = ref(false);
const selectedTaskId = ref(null);
const selectedProjectId = ref(null);
const taskDetailProjectUsers = ref([]);
const viewMode = ref('summary'); // 'summary' or 'projects'

const toggleProjectExpand = (projectId) => {
    expandedProjects.value[projectId] = !expandedProjects.value[projectId];
};

const setTab = (projectId, tabName) => {
    activeTabs.value[projectId] = tabName;
};

// --- LOGIC FOR HIGHLIGHTED SUMMARY ---

const isHighlighted = (dateString) => {
    if (!highlightDate.value || !dateString) return false;
    const highlight = highlightDate.value.split('T')[0];
    const target = dateString.split('T')[0];
    return highlight === target;
};

// This aggregates everything from the highlightDate and groups it by User
const highlightedActivityByUser = computed(() => {
    const userMap = {};

    const getOrCreateUser = (name) => {
        const userName = name || 'Unassigned/System';
        if (!userMap[userName]) {
            userMap[userName] = {
                tasksDone: [],
                tasksUpdated: [],
                notes: [],
                standups: [],
                meetings: [],
                emails: []
            };
        }
        return userMap[userName];
    };

    reportData.value.forEach(project => {
        // 1. Process Tasks
        project.tasks?.forEach(task => {
            if (isHighlighted(task.updated_at)) {
                const user = getOrCreateUser(task.assigned_to);
                if (task.status === 'Done') {
                    user.tasksDone.push({ ...task, projectName: project.name });
                } else {
                    user.tasksUpdated.push({ ...task, projectName: project.name });
                }
            }
        });

        // 2. Process Notes/Standups/Meetings
        project.project_notes?.forEach(note => {
            if (isHighlighted(note.created_at)) {
                const user = getOrCreateUser(note.creator_name);
                if (note.type === 'standup') user.standups.push({ ...note, projectName: project.name });
                else if (note.type === 'meeting_minutes') user.meetings.push({ ...note, projectName: project.name });
                else user.notes.push({ ...note, projectName: project.name });
            }
        });

        // 3. Process Emails
        project.emails?.forEach(email => {
            if (isHighlighted(email.created_at)) {
                const user = getOrCreateUser(email.sender);
                user.emails.push({ ...email, projectName: project.name });
            }
        });
    });

    return userMap;
});

const activeUsersCount = computed(() => Object.keys(highlightedActivityByUser.value).length);

// --- SIDEBAR & FETCH ---

const openTaskDetail = async (task) => {
    if (!task || !task.id) return;
    selectedTaskId.value = task.id;
    selectedProjectId.value = task.project_id || 0;

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

const fetchReport = async () => {
    loading.value = true;
    try {
        const res = await window.axios.get('/api/productivity/project-report', {
            params: {
                project_ids: selectedProjectIds.value.join(','),
                date_start: dateStart.value,
                date_end: dateEnd.value,
            }
        });
        reportData.value = res.data.reportData;
        projectsList.value = res.data.projects;

        reportData.value.forEach(p => {
            if (p.has_activity) {
                expandedProjects.value[p.id] = true;
                activeTabs.value[p.id] = 'todo';
            }
        });
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleTimeString('en-AU', { hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    const now = new Date();
    // Default highlight to yesterday if it's early morning, or today
    highlightDate.value = now.toISOString().split('T')[0];

    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    dateStart.value = start.toISOString().split('T')[0];
    dateEnd.value = now.toISOString().split('T')[0];

    fetchReport();
});

const getNotesByType = (notes, typeStr) => {
    if (!notes) return [];
    if (typeStr === 'note') return notes.filter(n => !n.type || n.type === 'note' || n.type === 'general');
    return notes.filter(n => n.type === typeStr);
};

</script>

<template>
    <Head title="Morning Briefing Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center print:hidden">
                <div>
                    <h2 class="font-black text-2xl text-gray-900 tracking-tight">Morning Briefing</h2>
                    <p class="text-sm text-gray-500 font-medium">Daily productivity & action point generator</p>
                </div>
                <div class="flex gap-3">
                    <button @click="fetchReport" :disabled="loading" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold hover:bg-indigo-700 shadow-sm transition disabled:opacity-50">
                        <ArrowPathIcon class="h-4 w-4 mr-2" :class="{'animate-spin': loading}" />
                        Refresh Data
                    </button>
                    <button @click="window.print()" class="flex items-center px-4 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 shadow-sm transition">
                        <PrinterIcon class="h-4 w-4 mr-2" /> PDF
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 bg-gray-50 min-h-screen print:bg-white print:py-0">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 1. CRITICAL FILTERS & NAV -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 print:hidden">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                        <div class="md:col-span-1">
                            <label class="text-[10px] font-black text-indigo-600 uppercase mb-2 block flex items-center gap-1">
                                <SparklesIcon class="h-3 w-3" /> Focus Date (Yesterday/Today)
                            </label>
                            <input type="date" v-model="highlightDate" class="w-full rounded-xl border-indigo-200 bg-indigo-50 text-indigo-900 font-bold text-sm focus:ring-indigo-500 shadow-inner" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-bold text-gray-400 uppercase mb-2 block tracking-widest">Filter Project Scope</label>
                            <MultiSelectDropdown v-model="selectedProjectIds" :options="projectsList" :is-multi="true" placeholder="Analyze All Projects" />
                        </div>
                        <div class="flex bg-gray-100 p-1 rounded-xl">
                            <button @click="viewMode = 'summary'" :class="viewMode === 'summary' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-black uppercase transition">Summary</button>
                            <button @click="viewMode = 'projects'" :class="viewMode === 'projects' ? 'bg-white shadow-sm text-indigo-600' : 'text-gray-500'" class="flex-1 py-1.5 px-3 rounded-lg text-xs font-black uppercase transition">Projects</button>
                        </div>
                    </div>
                </div>

                <!-- 2. THE COMMAND CENTER (HIGHLIGHTED ACTIVITY BY USER) -->
                <section v-if="viewMode === 'summary' || activeUsersCount > 0" class="space-y-4">
                    <div class="flex items-center justify-between px-2">
                        <h3 class="text-lg font-black text-gray-800 flex items-center gap-2">
                            <BoltIcon class="h-5 w-5 text-amber-500" />
                            Activity Snapshot: {{ highlightDate }}
                        </h3>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ activeUsersCount }} Contributors</span>
                    </div>

                    <div v-if="activeUsersCount === 0" class="bg-white p-10 rounded-3xl border-2 border-dashed border-gray-200 text-center">
                        <MagnifyingGlassIcon class="h-10 w-10 text-gray-300 mx-auto mb-3" />
                        <p class="text-gray-500 font-bold text-lg">No activity recorded for this specific date.</p>
                        <p class="text-sm text-gray-400">Try selecting a different date or refreshing the data.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="(data, userName) in highlightedActivityByUser" :key="userName"
                             class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden flex flex-col transition hover:shadow-md">

                            <!-- User Header -->
                            <div class="p-5 bg-gray-50/50 border-b border-gray-100 flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-black shadow-sm">
                                    {{ userName.substring(0,2).toUpperCase() }}
                                </div>
                                <div>
                                    <h4 class="font-black text-gray-900 leading-tight">{{ userName }}</h4>
                                    <p class="text-[10px] text-indigo-500 font-bold uppercase tracking-tighter">Daily Output</p>
                                </div>
                            </div>

                            <div class="p-5 space-y-6 flex-1">
                                <!-- Standups First (The "Plan") -->
                                <div v-if="data.standups.length > 0">
                                    <h5 class="text-[10px] font-black text-orange-500 uppercase tracking-widest mb-3 flex items-center gap-1">
                                        <HandRaisedIcon class="h-3 w-3" /> Standup Log
                                    </h5>
                                    <div v-for="s in data.standups" :key="s.id" class="text-xs text-gray-600 bg-orange-50/50 p-3 rounded-xl border border-orange-100 mb-2 italic">
                                        {{ s.content }}
                                        <p class="text-[9px] font-black mt-2 text-orange-400">{{ s.projectName }}</p>
                                    </div>
                                </div>

                                <!-- Tasks Done -->
                                <div v-if="data.tasksDone.length > 0">
                                    <h5 class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-3 flex items-center gap-1">
                                        <CheckCircleIcon class="h-3 w-3" /> Completed
                                    </h5>
                                    <ul class="space-y-2">
                                        <li v-for="t in data.tasksDone" :key="t.id" class="group">
                                            <div class="flex items-start gap-2">
                                                <div class="mt-1 h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                                <div>
                                                    <p class="text-xs font-bold text-gray-800 group-hover:text-indigo-600 cursor-pointer" @click="openTaskDetail(t)">{{ t.name }}</p>
                                                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tight">{{ t.projectName }}</p>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Emails & Comms -->
                                <div v-if="data.emails.length > 0">
                                    <h5 class="text-[10px] font-black text-purple-600 uppercase tracking-widest mb-3 flex items-center gap-1">
                                        <EnvelopeIcon class="h-3 w-3" /> Communications
                                    </h5>
                                    <ul class="space-y-2">
                                        <li v-for="e in data.emails" :key="e.id" class="bg-purple-50 p-2 rounded-lg border border-purple-100">
                                            <p class="text-xs font-bold text-gray-800 truncate">{{ e.subject }}</p>
                                            <p class="text-[9px] text-purple-400 font-black uppercase">{{ e.projectName }}</p>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Minutes/Notes -->
                                <div v-if="data.meetings.length > 0">
                                    <h5 class="text-[10px] font-black text-teal-600 uppercase tracking-widest mb-3 flex items-center gap-1">
                                        <UserIcon class="h-3 w-3" /> Drafted Minutes
                                    </h5>
                                    <div v-for="m in data.meetings" :key="m.id" class="text-[11px] text-gray-600 border-l-2 border-teal-200 pl-3 py-1">
                                        {{ m.content.substring(0, 100) }}...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <hr v-if="viewMode === 'projects'" class="border-gray-200 my-10" />

                <!-- 3. DETAILED PROJECT VIEW (REDUCED PRIORITY) -->
                <div v-if="viewMode === 'projects'" class="space-y-6">
                    <h3 class="text-sm font-black text-gray-400 uppercase tracking-[0.2em] px-2">Deep Dive by Project</h3>
                    <div v-for="project in reportData" :key="project.id" class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden mb-6">
                        <!-- Project Header -->
                        <div @click="toggleProjectExpand(project.id)" class="p-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-center space-x-4">
                                <div class="h-10 w-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <BriefcaseIcon class="h-6 w-6" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg text-gray-900">{{ project.name }}</h4>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-gray-100 text-gray-600">{{ project.status }}</span>
                                </div>
                            </div>
                            <component :is="expandedProjects[project.id] ? ChevronUpIcon : ChevronDownIcon" class="h-5 w-5 text-gray-400" />
                        </div>

                        <!-- Content (Same as previous but simplified) -->
                        <div v-show="expandedProjects[project.id]" class="border-t border-gray-100 bg-gray-50/20">
                            <div class="flex border-b border-gray-100 bg-white px-4">
                                <button @click="setTab(project.id, 'todo')" :class="activeTabs[project.id] === 'todo' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-400'" class="py-3 px-4 border-b-2 text-[10px] font-black uppercase tracking-widest">Active Tasks</button>
                                <button @click="setTab(project.id, 'notes')" :class="activeTabs[project.id] === 'notes' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400'" class="py-3 px-4 border-b-2 text-[10px] font-black uppercase tracking-widest">Notes</button>
                                <button @click="setTab(project.id, 'emails')" :class="activeTabs[project.id] === 'emails' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-400'" class="py-3 px-4 border-b-2 text-[10px] font-black uppercase tracking-widest">Emails</button>
                            </div>
                            <div class="p-6">
                                <div v-show="activeTabs[project.id] === 'todo'" class="space-y-3">
                                    <div v-for="task in project.tasks?.filter(t => t.status !== 'Done')" :key="task.id" @click="openTaskDetail(task)" class="bg-white p-4 rounded-xl border border-gray-100 cursor-pointer hover:border-indigo-200 transition">
                                        <p class="font-bold text-gray-900 text-sm">{{ task.name }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1 uppercase font-bold">{{ task.assigned_to }} • {{ task.status }}</p>
                                    </div>
                                </div>
                                <div v-show="activeTabs[project.id] === 'notes'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-for="note in project.project_notes" :key="note.id" class="bg-white p-4 rounded-xl border border-gray-100 text-xs leading-relaxed text-gray-600">
                                        "{{ note.content }}"
                                        <div class="mt-2 text-[9px] font-black text-indigo-500 uppercase">{{ note.creator_name }} • {{ note.type }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <RightSidebar v-model:show="showTaskDetailSidebar" title="Task Review" :initialWidth="40">
            <template #content>
                <TaskDetailSidebar v-if="selectedTaskId" :task-id="selectedTaskId" :project-id="selectedProjectId" :project-users="taskDetailProjectUsers" @close="showTaskDetailSidebar = false" />
            </template>
        </RightSidebar>

    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
    .bg-gray-50 { background: white !important; }
    .shadow-sm, .rounded-3xl { box-shadow: none !important; border: 1px solid #eee !important; }
}
</style>
